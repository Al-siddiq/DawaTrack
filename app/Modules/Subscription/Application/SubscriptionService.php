<?php

declare(strict_types=1);

namespace App\Modules\Subscription\Application;

use App\Exceptions\ApiException;
use App\Modules\Audit\Application\AuditService;
use App\Support\SensitiveReason;
use App\Support\UtcTimestamp;
use App\Support\Uuid;
use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class SubscriptionService
{
    private const ALLOWED_OPERATIONS = [
        'ACTIVATE',
        'RENEW',
        'UPGRADE',
        'DOWNGRADE',
        'EXTEND',
    ];

    private const EVENT_TYPES = [
        'ACTIVATE' => 'CREATED',
        'RENEW' => 'RENEWED',
        'UPGRADE' => 'UPGRADED',
        'DOWNGRADE' => 'DOWNGRADED',
        'EXTEND' => 'EXTENDED',
    ];

    private const MEMBERSHIP_LOCK = 'dawatrack:identity-membership';

    public function __construct(
        private readonly BaseConnection $db,
        private readonly AuditService $audit,
    ) {
    }

    /** @return array<string, mixed> */
    public function state(string $tenantPublicId): array
    {
        $tenant = $this->tenant($tenantPublicId);

        return [
            'tenant' => $tenant,
            'subscription' => (new SubscriptionQuery($this->db))
                ->forTenant((int) $tenant['id']),
        ];
    }

    /**
     * Creates an immutable subscription period together with its
     * corresponding subscription event and Audit evidence.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function create(
        string $tenantPublicId,
        int $actor,
        array $input,
    ): array {
        $operation = $this->operation($input);
        $reason = SensitiveReason::require(
            (string) ($input['reason'] ?? ''),
        );
        $reference = $this->externalReference($input);

        /*
         * Resolve the Tenant before advisory-lock acquisition so that the
         * lock name is stable. The Tenant row is read again using
         * SELECT ... FOR UPDATE inside the transaction.
         */
        $tenant = $this->tenant($tenantPublicId);
        $tenantId = (int) $tenant['id'];

        $subscriptionLock = 'dawatrack:subscription:' . $tenantId;

        $this->lock(self::MEMBERSHIP_LOCK);

        try {
            $this->lock($subscriptionLock);
        } catch (Throwable $exception) {
            $this->releaseLock(self::MEMBERSHIP_LOCK);

            throw $exception;
        }

        try {
            $this->db
                ->transException(true)
                ->transBegin();

            try {
                $tenant = $this->lockTenant($tenantId);

                if ($tenant['status'] === 'CLOSED') {
                    throw new ApiException(
                        'CLOSED',
                        'A closed Tenant cannot receive a new entitlement.',
                        403,
                    );
                }

                $periods = $this->db
                    ->table('subscriptions')
                    ->where('tenant_id', $tenantId)
                    ->orderBy('starts_at', 'ASC')
                    ->get()
                    ->getResultArray();

                $latest = $periods === []
                    ? null
                    : $periods[array_key_last($periods)];

                $planCode = strtoupper(
                    trim((string) ($input['plan_code'] ?? '')),
                );

                if ($operation === 'EXTEND') {
                    $planCode = 'TRIAL';
                }

                $plan = $this->db
                    ->table('subscription_plans')
                    ->where([
                        'code' => $planCode,
                        'is_active' => 1,
                    ])
                    ->get()
                    ->getRowArray();

                if (
                    $plan === null
                    || ($operation === 'ACTIVATE' && $planCode === 'TRIAL')
                ) {
                    throw new ApiException(
                        'VALIDATION_FAILED',
                        'Choose an active approved plan.',
                        422,
                        [
                            'plan_code' => [
                                'Choose Starter or Growth, or Trial only for extension.',
                            ],
                        ],
                    );
                }

                $start = $this->date(
                    (string) ($input['starts_at'] ?? ''),
                    'starts_at',
                );

                $end = $this->date(
                    (string) ($input['ends_at'] ?? ''),
                    'ends_at',
                );

                if ($end <= $start) {
                    throw new ApiException(
                        'VALIDATION_FAILED',
                        'End must be after start.',
                        422,
                        [
                            'ends_at' => [
                                'Use a half-open period whose end is after its start.',
                            ],
                        ],
                    );
                }

                $startText = $start->format('Y-m-d H:i:s.u');
                $endText = $end->format('Y-m-d H:i:s.u');

                $this->validateOperation(
                    $operation,
                    $planCode,
                    $plan,
                    $latest,
                    $start,
                    $end,
                    $startText,
                );

                $activeStaff = $this->db
                    ->table('tenant_memberships')
                    ->where([
                        'tenant_id' => $tenantId,
                        'role' => 'STAFF',
                        'status' => 'ACTIVE',
                    ])
                    ->countAllResults();

                if (
                    $operation === 'DOWNGRADE'
                    && $activeStaff > (int) $plan['max_active_staff']
                ) {
                    throw new ApiException(
                        'SUBSCRIPTION_STAFF_CAP_CONFLICT',
                        'Active Staff exceeds the target plan limit.',
                        409,
                        [
                            'active_staff' => [
                                (string) $activeStaff,
                            ],
                            'target_limit' => [
                                (string) $plan['max_active_staff'],
                            ],
                        ],
                    );
                }

                $overlap = $this->db
                    ->table('subscriptions')
                    ->where('tenant_id', $tenantId)
                    ->where('starts_at <', $endText)
                    ->where('ends_at >', $startText)
                    ->countAllResults();

                if ($overlap > 0) {
                    throw new ApiException(
                        'SUBSCRIPTION_OVERLAP',
                        'The requested entitlement overlaps an existing period. Schedule a transition at or after the current period ends.',
                        409,
                    );
                }

                $publicId = Uuid::v4();
                $createdAt = UtcTimestamp::now();

                $this->db
                    ->table('subscriptions')
                    ->insert([
                        'public_id' => $publicId,
                        'tenant_id' => $tenantId,
                        'subscription_plan_id' => $plan['id'],
                        'entitlement_type' => $planCode === 'TRIAL'
                            ? 'TRIAL'
                            : 'PAID',
                        'starts_at' => $startText,
                        'ends_at' => $endText,
                        'plan_code_snapshot' => $plan['code'],
                        'max_active_staff_snapshot' => $plan['max_active_staff'],
                        'price_amount_snapshot' => $this->priceSnapshot($plan),
                        'currency_code' => $plan['currency_code'] ?? 'NGN',
                        'external_reference' => $reference,
                        'supersedes_subscription_id' => $latest['id'] ?? null,
                        'created_by_user_id' => $actor,
                        'reason' => $reason,
                        'created_at' => $createdAt,
                    ]);

                $subscriptionId = (int) $this->db->insertID();

                $this->db
                    ->table('subscription_events')
                    ->insert([
                        'public_id' => Uuid::v4(),
                        'tenant_id' => $tenantId,
                        'subscription_id' => $subscriptionId,
                        'event_type' => self::EVENT_TYPES[$operation],
                        'previous_subscription_id' => $latest['id'] ?? null,
                        'effective_at' => $startText,
                        'external_reference' => $reference,
                        'reason' => $reason,
                        'actor_user_id' => $actor,
                        'created_at' => $createdAt,
                    ]);

                $this->audit->record([
                    'tenant_id' => $tenantId,
                    'actor_type' => 'USER',
                    'actor_user_id' => $actor,
                    'action_code' => 'subscription.'
                        . strtolower($operation),
                    'category' => 'SUBSCRIPTION',
                    'resource_type' => 'subscription',
                    'resource_public_id' => $publicId,
                    'reason' => $reason,
                    'change_metadata' => [
                        'plan_code' => $plan['code'],
                        'starts_at' => $startText,
                        'ends_at' => $endText,
                        'max_active_staff' => (int) $plan['max_active_staff'],
                        'operation' => $operation,
                    ],
                ]);

                if (! $this->db->transCommit()) {
                    throw new ApiException(
                        'REQUEST_FAILED',
                        'Subscription could not be committed.',
                        500,
                    );
                }
            } catch (Throwable $exception) {
                $this->db->transRollback();

                throw $exception;
            }
        } finally {
            $this->releaseLock($subscriptionLock);
            $this->releaseLock(self::MEMBERSHIP_LOCK);
        }

        /*
         * Read the authoritative state only after the write transaction has
         * committed and advisory locks have been released.
         */
        return $this->state($tenantPublicId);
    }

    public function recordExpiries(int $actor): int
    {
        $count = 0;

        $expiredSubscriptions = $this->db
            ->table('subscriptions')
            ->where('ends_at <=', UtcTimestamp::now())
            ->get()
            ->getResultArray();

        foreach ($expiredSubscriptions as $subscription) {
            $lockName = 'dawatrack:subscription-expiry:'
                . $subscription['id'];

            $this->lock($lockName);

            try {
                $this->db
                    ->transException(true)
                    ->transBegin();

                try {
                    $alreadyRecorded = $this->db
                        ->table('subscription_events')
                        ->where([
                            'subscription_id' => $subscription['id'],
                            'event_type' => 'EXPIRED',
                        ])
                        ->countAllResults() > 0;

                    if ($alreadyRecorded) {
                        if (! $this->db->transCommit()) {
                            throw new ApiException(
                                'REQUEST_FAILED',
                                'Subscription expiry check could not be committed.',
                                500,
                            );
                        }

                        continue;
                    }

                    $createdAt = UtcTimestamp::now();
                    $reason = 'Time-derived entitlement expiry recorded';

                    $this->db
                        ->table('subscription_events')
                        ->insert([
                            'public_id' => Uuid::v4(),
                            'tenant_id' => $subscription['tenant_id'],
                            'subscription_id' => $subscription['id'],
                            'event_type' => 'EXPIRED',
                            'effective_at' => $subscription['ends_at'],
                            'reason' => $reason,
                            'actor_user_id' => $actor,
                            'created_at' => $createdAt,
                        ]);

                    $this->audit->record([
                        'tenant_id' => $subscription['tenant_id'],
                        'actor_type' => 'USER',
                        'actor_user_id' => $actor,
                        'action_code' => 'subscription.expired_recorded',
                        'category' => 'SUBSCRIPTION',
                        'resource_type' => 'subscription',
                        'resource_public_id' => $subscription['public_id'],
                        'reason' => $reason,
                    ]);

                    if (! $this->db->transCommit()) {
                        throw new ApiException(
                            'REQUEST_FAILED',
                            'Subscription expiry could not be committed.',
                            500,
                        );
                    }

                    $count++;
                } catch (Throwable $exception) {
                    $this->db->transRollback();

                    throw $exception;
                }
            } finally {
                $this->releaseLock($lockName);
            }
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function operation(array $input): string
    {
        $operation = strtoupper(
            trim((string) ($input['operation'] ?? '')),
        );

        if (! in_array($operation, self::ALLOWED_OPERATIONS, true)) {
            throw new ApiException(
                'VALIDATION_FAILED',
                'Choose a supported subscription operation.',
                422,
                [
                    'operation' => [
                        'Choose activate, renew, upgrade, downgrade, or extend.',
                    ],
                ],
            );
        }

        return $operation;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function externalReference(array $input): ?string
    {
        $reference = trim(
            (string) ($input['external_reference'] ?? ''),
        );

        if ($reference === '') {
            return null;
        }

        if (mb_strlen($reference) > 120) {
            throw new ApiException(
                'VALIDATION_FAILED',
                'Reference is too long.',
                422,
                [
                    'external_reference' => [
                        'Use 120 characters or fewer.',
                    ],
                ],
            );
        }

        return $reference;
    }

    /**
     * @param array<string, mixed> $plan
     * @param array<string, mixed>|null $latest
     */
    private function validateOperation(
        string $operation,
        string $planCode,
        array $plan,
        ?array $latest,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        string $startText,
    ): void {
        if ($operation === 'EXTEND') {
            if (
                $latest === null
                || $latest['plan_code_snapshot'] !== 'TRIAL'
                || $startText !== $latest['ends_at']
            ) {
                throw new ApiException(
                    'CONFLICT',
                    'Trial extension must begin exactly when the latest Trial ends.',
                    409,
                );
            }

            $interval = $start->diff($end);
            $days = (int) $interval->format('%a');

            $hasPartialDay = $interval->h !== 0
                || $interval->i !== 0
                || $interval->s !== 0
                || $interval->f > 0;

            if (
                $hasPartialDay
                || $days < 1
                || $days > 30
            ) {
                throw new ApiException(
                    'VALIDATION_FAILED',
                    'Trial extension must add between 1 and 30 whole days.',
                    422,
                );
            }

            return;
        }

        if (
            in_array(
                $operation,
                ['RENEW', 'UPGRADE', 'DOWNGRADE'],
                true,
            )
            && $latest === null
        ) {
            throw new ApiException(
                'CONFLICT',
                'There is no prior entitlement for this operation.',
                409,
            );
        }

        if ($operation === 'RENEW' && $latest !== null) {
            if ($planCode !== $latest['plan_code_snapshot']) {
                throw new ApiException(
                    'CONFLICT',
                    'Renewal must retain the previous plan; use upgrade or downgrade.',
                    409,
                );
            }

            if ($startText !== $latest['ends_at']) {
                throw new ApiException(
                    'CONFLICT',
                    'Renewal must begin exactly when the latest entitlement ends.',
                    409,
                );
            }
        }

        if (
            $operation === 'UPGRADE'
            && $latest !== null
            && (int) $plan['max_active_staff']
                <= (int) $latest['max_active_staff_snapshot']
        ) {
            throw new ApiException(
                'CONFLICT',
                'The selected plan is not an upgrade.',
                409,
            );
        }

        if (
            $operation === 'DOWNGRADE'
            && $latest !== null
            && (int) $plan['max_active_staff']
                >= (int) $latest['max_active_staff_snapshot']
        ) {
            throw new ApiException(
                'CONFLICT',
                'The selected plan is not a downgrade.',
                409,
            );
        }
    }

    /**
     * Prevent an unconfigured paid plan from being recorded as a free
     * immutable entitlement.
     *
     * @param array<string, mixed> $plan
     */
    private function priceSnapshot(array $plan): string
    {
        if ($plan['monthly_price_amount'] === null) {
            throw new ApiException(
                'SUBSCRIPTION_PRICE_NOT_CONFIGURED',
                'The selected plan does not have a configured price.',
                409,
            );
        }

        return (string) $plan['monthly_price_amount'];
    }

    /** @return array<string, mixed> */
    private function tenant(string $publicId): array
    {
        $tenant = $this->db
            ->table('tenants')
            ->where('public_id', $publicId)
            ->get()
            ->getRowArray();

        if ($tenant === null) {
            throw new ApiException(
                'RESOURCE_NOT_FOUND',
                'Tenant not found.',
                404,
            );
        }

        return $tenant;
    }

    /**
     * Lock the Tenant root so subscription changes serialize with
     * Tenant lifecycle transitions.
     *
     * @return array<string, mixed>
     */
    private function lockTenant(int $tenantId): array
    {
        $tenant = $this->db
            ->query(
                'SELECT * FROM tenants WHERE id = ? FOR UPDATE',
                [$tenantId],
            )
            ->getRowArray();

        if ($tenant === null) {
            throw new ApiException(
                'RESOURCE_NOT_FOUND',
                'Tenant not found.',
                404,
            );
        }

        return $tenant;
    }

    private function lock(string $name): void
    {
        $row = $this->db
            ->query(
                'SELECT GET_LOCK(?, 10) AS acquired',
                [$name],
            )
            ->getRowArray();

        if ((int) ($row['acquired'] ?? 0) !== 1) {
            throw new ApiException(
                'CONFLICT',
                'Another subscription operation is in progress.',
                409,
            );
        }
    }

    private function releaseLock(string $name): void
    {
        $this->db->query(
            'SELECT RELEASE_LOCK(?)',
            [$name],
        );
    }

    private function date(
        string $value,
        string $field,
    ): DateTimeImmutable {
        $value = trim($value);

        if (
            preg_match(
                '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})?$/',
                $value,
            ) !== 1
        ) {
            throw new ApiException(
                'VALIDATION_FAILED',
                'Enter valid ISO-8601 entitlement dates.',
                422,
                [
                    $field => [
                        'Use an ISO-8601 date and time.',
                    ],
                ],
            );
        }

        try {
            $date = new DateTimeImmutable(
                $value,
                new DateTimeZone('UTC'),
            );
        } catch (Throwable) {
            throw new ApiException(
                'VALIDATION_FAILED',
                'Enter valid UTC entitlement dates.',
                422,
                [
                    $field => [
                        'Enter a valid date and time.',
                    ],
                ],
            );
        }

        return $date->setTimezone(
            new DateTimeZone('UTC'),
        );
    }
}