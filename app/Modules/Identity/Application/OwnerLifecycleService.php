<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Exceptions\ApiException;
use App\Modules\Audit\Application\AuditService;
use App\Modules\Audit\Domain\AuditActor;
use App\Modules\Audit\Domain\AuditAppendCommand;
use App\Modules\Audit\Domain\AuditCategory;
use App\Modules\Sync\Application\ChangeSequenceService;
use App\Modules\Sync\Domain\ChangeCommand;
use App\Modules\Sync\Domain\ChangeKind;
use App\Security\UserSessionRevocationService;
use App\Support\SensitiveReason;
use App\Support\UtcTimestamp;
use App\Support\Uuid;
use CodeIgniter\Database\BaseConnection;
use Throwable;

final class OwnerLifecycleService
{
    private const MEMBERSHIP_LOCK = 'dawatrack:identity-membership';

    public function __construct(
        private readonly BaseConnection $db,
        private readonly AuditService $audit,
        private readonly UserSessionRevocationService $sessionRevocation,
    ) {
    }

    /**
     * @return array{tenant_public_id: string, owner_membership_public_id: string, former_owner_user_id: int}
     */
    public function reassign(
        string $tenantPublicId,
        string $expectedCurrentMembershipPublicId,
        string $targetEmail,
        int $adminUserId,
        string $reason,
        bool $confirmed,
    ): array {
        $reason = SensitiveReason::require($reason);
        $targetEmail = strtolower(trim($targetEmail));
        if (! filter_var($targetEmail, FILTER_VALIDATE_EMAIL) || mb_strlen($targetEmail) > 254) {
            throw new ApiException('VALIDATION_FAILED', 'Enter a valid target Owner email address.', 422, [
                'target_email' => ['Enter a valid email address of no more than 254 characters.'],
            ]);
        }
        if (! $confirmed) {
            throw new ApiException('CONFIRMATION_REQUIRED', 'Confirm the reviewed Primary Owner transfer before continuing.', 422);
        }

        $this->lock();
        $formerOwnerUserId = 0;
        $targetUserId = 0;
        try {
            $this->db->transException(true)->transBegin();
            try {
                $tenant = $this->db->query(<<<'SQL'
SELECT t.id AS tenant_id,
       t.public_id AS tenant_public_id,
       t.status AS tenant_status,
       s.id AS shop_id
FROM tenants t
INNER JOIN shops s ON s.tenant_id = t.id
WHERE t.public_id = ?
LIMIT 1
FOR UPDATE
SQL, [$tenantPublicId])->getRowArray();
                if ($tenant === null) {
                    throw new ApiException('RESOURCE_NOT_FOUND', 'Tenant not found.', 404);
                }
                if ($tenant['tenant_status'] !== 'ACTIVE') {
                    throw new ApiException('OWNER_REASSIGNMENT_UNAVAILABLE', 'Owner reassignment is available only after Tenant activation.', 409);
                }

                $current = $this->db->table('tenant_memberships')
                    ->where([
                        'tenant_id' => (int) $tenant['tenant_id'],
                        'role' => 'PRIMARY_OWNER',
                        'status' => 'ACTIVE',
                    ])->get()->getRowArray();
                if ($current === null || ! hash_equals((string) $current['public_id'], $expectedCurrentMembershipPublicId)) {
                    throw new ApiException('OWNER_STATE_CHANGED', 'The current Primary Owner changed. Reload the Tenant details before trying again.', 409);
                }

                $target = auth()->getProvider()->findByCredentials(['email' => $targetEmail]);
                if ($target === null || ! $target->active || $target->inGroup('platform_admin')) {
                    throw new ApiException('OWNER_IDENTITY_UNAVAILABLE', 'The target identity cannot become a Primary Owner.', 409, [
                        'target_email' => ['Choose an existing active non-Platform account.'],
                    ]);
                }
                if ((int) $target->id === (int) $current['user_id']) {
                    throw new ApiException('OWNER_UNCHANGED', 'The selected account is already the Primary Owner.', 409);
                }

                $targetMembership = $this->db->table('tenant_memberships')
                    ->where(['tenant_id' => (int) $tenant['tenant_id'], 'user_id' => (int) $target->id])
                    ->get()->getRowArray();
                (new MembershipInvariantService($this->db))->assertUserMayActivate(
                    (int) $target->id,
                    $targetMembership === null ? null : (int) $targetMembership['id'],
                );

                $now = UtcTimestamp::now();
                $formerGrantCount = $this->db->table('membership_permission_grants')
                    ->where(['membership_id' => (int) $current['id']])
                    ->where('revoked_at', null)
                    ->countAllResults();
                if ($formerGrantCount > 0) {
                    $this->db->table('membership_permission_grants')
                        ->where(['membership_id' => (int) $current['id']])
                        ->where('revoked_at', null)
                        ->update(['revoked_by_user_id' => $adminUserId, 'revoked_at' => $now]);
                }
                $targetGrantCount = $targetMembership === null ? 0 : $this->db->table('membership_permission_grants')
                    ->where(['membership_id' => (int) $targetMembership['id']])
                    ->where('revoked_at', null)
                    ->countAllResults();
                if ($targetGrantCount > 0) {
                    $this->db->table('membership_permission_grants')
                        ->where(['membership_id' => (int) $targetMembership['id']])
                        ->where('revoked_at', null)
                        ->update(['revoked_by_user_id' => $adminUserId, 'revoked_at' => $now]);
                }

                $this->db->table('tenant_memberships')->where('id', (int) $current['id'])->update([
                    'status' => 'DEACTIVATED',
                    'deactivated_at' => $now,
                    'updated_at' => $now,
                ]);

                if ($targetMembership === null) {
                    $newMembershipPublicId = Uuid::v4();
                    $this->db->table('tenant_memberships')->insert([
                        'public_id' => $newMembershipPublicId,
                        'tenant_id' => (int) $tenant['tenant_id'],
                        'user_id' => (int) $target->id,
                        'role' => 'PRIMARY_OWNER',
                        'status' => 'ACTIVE',
                        'activated_at' => $now,
                        'deactivated_at' => null,
                        'created_by_user_id' => $adminUserId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } else {
                    $newMembershipPublicId = (string) $targetMembership['public_id'];
                    $this->db->table('tenant_memberships')->where('id', (int) $targetMembership['id'])->update([
                        'role' => 'PRIMARY_OWNER',
                        'status' => 'ACTIVE',
                        'activated_at' => $now,
                        'deactivated_at' => null,
                        'updated_at' => $now,
                    ]);
                }

                $formerOwnerUserId = (int) $current['user_id'];
                $targetUserId = (int) $target->id;
                foreach ([$formerOwnerUserId, $targetUserId] as $affectedUserId) {
                    $this->db->table('auth_remember_tokens')->where('user_id', $affectedUserId)->delete();
                    $this->db->table('auth_identities')
                        ->where('user_id', $affectedUserId)
                        ->whereIn('type', ['magic-link', 'access_token', 'hmac', 'email_2fa'])
                        ->delete();
                }
                $this->audit->append(new AuditAppendCommand(
                    actor: AuditActor::platformUser($adminUserId, (int) $tenant['tenant_id'], (int) $tenant['shop_id']),
                    actionCode: 'owner.reassigned',
                    category: AuditCategory::SECURITY,
                    resourceType: 'tenant_membership',
                    resourcePublicId: $newMembershipPublicId,
                    reason: $reason,
                    metadata: [
                        'former_owner_membership_public_id' => $current['public_id'],
                        'new_owner_membership_public_id' => $newMembershipPublicId,
                        'target_membership_reused' => $targetMembership !== null,
                        'former_owner_grants_revoked' => $formerGrantCount,
                        'target_staff_grants_revoked' => $targetGrantCount,
                    ],
                ));

                (new ChangeSequenceService($this->db, service('requestContext')))->publish([
                    new ChangeCommand((int) $tenant['tenant_id'], (int) $tenant['shop_id'], 'tenant_membership', (string) $current['public_id'], ChangeKind::DEACTIVATE),
                    new ChangeCommand((int) $tenant['tenant_id'], (int) $tenant['shop_id'], 'tenant_membership', $newMembershipPublicId, ChangeKind::UPSERT),
                ]);

                if (! $this->db->transCommit()) {
                    throw new ApiException('OWNER_REASSIGNMENT_FAILED', 'The Primary Owner transfer could not be committed.', 500);
                }

                $result = [
                    'tenant_public_id' => $tenant['tenant_public_id'],
                    'owner_membership_public_id' => $newMembershipPublicId,
                    'former_owner_user_id' => $formerOwnerUserId,
                ];
            } catch (Throwable $exception) {
                $this->db->transRollback();
                throw $exception;
            }
        } finally {
            $this->releaseLock();
        }

        $this->sessionRevocation->revokeAll($formerOwnerUserId);
        $this->sessionRevocation->revokeAll($targetUserId);
        return $result;
    }

    private function lock(): void
    {
        $row = $this->db->query('SELECT GET_LOCK(?, 10) AS acquired', [self::MEMBERSHIP_LOCK])->getRowArray();
        if ((int) ($row['acquired'] ?? 0) !== 1) {
            throw new ApiException('OWNER_BUSY', 'Another Owner operation is in progress. Please retry.', 409);
        }
    }

    private function releaseLock(): void
    {
        $this->db->query('SELECT RELEASE_LOCK(?)', [self::MEMBERSHIP_LOCK]);
    }
}
