<?php

declare(strict_types=1);

namespace App\Modules\TenantShop\Application;

use App\Exceptions\ApiException;
use App\Modules\Audit\Application\AuditService;
use App\Modules\Audit\Domain\AuditActor;
use App\Modules\Audit\Domain\AuditAppendCommand;
use App\Modules\Audit\Domain\AuditCategory;
use App\Modules\Identity\Application\MembershipInvariantService;
use App\Support\UtcTimestamp;
use App\Support\Uuid;
use App\Modules\Sync\Application\ChangeSequenceService;
use App\Modules\Sync\Domain\ChangeCommand;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class OnboardingService
{
    private const LOCK_NAME = 'dawatrack:identity-membership';

    public function __construct(
        private readonly BaseConnection $database,
        private readonly AuditService $audit,
    ) {
    }

    /**
     * Creates the Shield Owner and complete one-Shop Tenant graph in one transaction.
     * The advisory lock serializes global identity, hostname and one-Shop checks.
     *
     * @param array<string, mixed> $input
     * @return array{tenant_public_id: string, shop_public_id: string, hostname: string, owner_email: string}
     */
    public function onboard(array $input, int $adminUserId): array
    {
        $data = $this->normalizeAndValidate($input);
        $this->acquireLock();

        try {
            $this->database->transException(true)->transBegin();
            $this->assertPrerequisites($data['owner_email'], $data['hostname']);

            $now = UtcTimestamp::now();
            $owner = $this->createOwner($data);
            $ownerUserId = (int) $owner['user_id'];
            (new MembershipInvariantService($this->database))->assertUserMayActivate($ownerUserId);

            $tenantPublicId = Uuid::v4();
            $this->database->table('tenants')->insert([
                'public_id' => $tenantPublicId,
                'legal_or_business_name' => $data['business_name'],
                'status' => 'ACTIVE',
                'activated_at' => $now,
                'created_by_user_id' => $adminUserId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $tenantId = (int) $this->database->insertID();

            $this->assertNoShopExists($tenantId);
            $shopPublicId = Uuid::v4();
            $this->database->table('shops')->insert([
                'public_id' => $shopPublicId,
                'tenant_id' => $tenantId,
                'name' => $data['shop_name'],
                'contact_phone' => $data['contact_phone'],
                'contact_email' => $data['contact_email'],
                'address_line_1' => $data['address_line_1'],
                'address_line_2' => $data['address_line_2'],
                'city_town' => $data['city_town'],
                'state_region' => $data['state_region'],
                'country_code' => 'NG',
                'timezone_name' => 'Africa/Lagos',
                'currency_code' => 'NGN',
                'status' => 'ACTIVE',
                'activated_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $shopId = (int) $this->database->insertID();

            $membershipPublicId = Uuid::v4();
            $this->database->table('tenant_memberships')->insert([
                'public_id' => $membershipPublicId,
                'tenant_id' => $tenantId,
                'user_id' => $ownerUserId,
                'role' => 'PRIMARY_OWNER',
                'status' => 'ACTIVE',
                'activated_at' => $now,
                'created_by_user_id' => $adminUserId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $membershipId = (int) $this->database->insertID();

            $brandingPublicId = $this->createBranding($tenantId, $shopId, $adminUserId, $data['shop_name'], $now);
            $domainPublicId = $this->createPlatformDomain($tenantId, $shopId, $adminUserId, $data['hostname'], $now);
            $subscription = $this->createTrial($tenantId, $adminUserId, $now);

            $platformActor = AuditActor::platformUser($adminUserId, $tenantId, $shopId);
            $this->audit->append(new AuditAppendCommand(
                actor: $platformActor,
                actionCode: 'owner.created',
                category: AuditCategory::SECURITY,
                resourceType: 'tenant_membership',
                resourcePublicId: $membershipPublicId,
                reason: $data['reason'],
                metadata: ['role' => 'PRIMARY_OWNER', 'status' => 'ACTIVE'],
            ));
            $this->audit->append(new AuditAppendCommand(
                actor: $platformActor,
                actionCode: 'tenant.onboarded',
                category: AuditCategory::TENANT,
                resourceType: 'tenant',
                resourcePublicId: $tenantPublicId,
                reason: $data['reason'],
                metadata: [
                    'shop_public_id' => $shopPublicId,
                    'owner_membership_public_id' => $membershipPublicId,
                    'subscription_public_id' => $subscription['public_id'],
                    'plan' => 'TRIAL',
                ],
            ));
            (new ChangeSequenceService($this->database, service('requestContext')))->publish([
                new ChangeCommand($tenantId, null, 'tenant', $tenantPublicId),
                new ChangeCommand($tenantId, $shopId, 'shop', $shopPublicId),
                new ChangeCommand($tenantId, $shopId, 'tenant_membership', $membershipPublicId),
                new ChangeCommand($tenantId, $shopId, 'shop_branding_profile', $brandingPublicId),
                new ChangeCommand($tenantId, $shopId, 'shop_domain', $domainPublicId),
                new ChangeCommand($tenantId, null, 'subscription', $subscription['public_id']),
            ]);

            if (! $this->database->transCommit()) {
                throw new ApiException('ONBOARDING_FAILED', 'The Tenant could not be committed.', 500);
            }

            return [
                'tenant_public_id' => $tenantPublicId,
                'shop_public_id' => $shopPublicId,
                'hostname' => $data['hostname'],
                'owner_email' => $data['owner_email'],
            ];
        } catch (Throwable $exception) {
            $this->database->transRollback();
            throw $exception;
        } finally {
            $this->database->query('SELECT RELEASE_LOCK(?)', [self::LOCK_NAME]);
        }
    }

    /** @return array<string, mixed> */
    private function normalizeAndValidate(array $input): array
    {
        $fields = [
            'business_name' => trim((string) ($input['business_name'] ?? '')),
            'shop_name' => trim((string) ($input['shop_name'] ?? '')),
            'contact_phone' => trim((string) ($input['contact_phone'] ?? '')) ?: null,
            'contact_email' => strtolower(trim((string) ($input['contact_email'] ?? ''))) ?: null,
            'address_line_1' => trim((string) ($input['address_line_1'] ?? '')),
            'address_line_2' => trim((string) ($input['address_line_2'] ?? '')) ?: null,
            'city_town' => trim((string) ($input['city_town'] ?? '')),
            'state_region' => trim((string) ($input['state_region'] ?? '')),
            'platform_subdomain' => strtolower(trim((string) ($input['platform_subdomain'] ?? ''))),
            'owner_name' => trim((string) ($input['owner_name'] ?? '')),
            'owner_email' => strtolower(trim((string) ($input['owner_email'] ?? ''))),
            'owner_password' => (string) ($input['owner_password'] ?? ''),
            'reason' => trim((string) ($input['reason'] ?? '')),
        ];
        $errors = [];
        foreach (['business_name', 'shop_name', 'address_line_1', 'city_town', 'state_region', 'platform_subdomain', 'owner_name', 'owner_email', 'owner_password', 'reason'] as $required) {
            if ($fields[$required] === '') {
                $errors[$required] = ['This field is required.'];
            }
        }
        foreach (['business_name' => 160, 'shop_name' => 160, 'address_line_1' => 180, 'address_line_2' => 180, 'city_town' => 100, 'state_region' => 100, 'owner_name' => 100, 'reason' => 500] as $field => $maximum) {
            if ($fields[$field] !== null && mb_strlen((string) $fields[$field]) > $maximum) {
                $errors[$field] = ["Use no more than {$maximum} characters."];
            }
        }
        if (! filter_var($fields['owner_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['owner_email'] = ['Enter a valid Owner email address.'];
        }
        if ($fields['contact_email'] !== null && ! filter_var($fields['contact_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['contact_email'] = ['Enter a valid contact email address.'];
        }
        if (mb_strlen($fields['owner_email']) > 254) {
            $errors['owner_email'] = ['Use no more than 254 characters.'];
        }
        if ($fields['contact_email'] !== null && mb_strlen($fields['contact_email']) > 254) {
            $errors['contact_email'] = ['Use no more than 254 characters.'];
        }
        if ($fields['contact_phone'] !== null && (mb_strlen($fields['contact_phone']) > 32 || preg_match('/^\+?[0-9().\s-]+$/', $fields['contact_phone']) !== 1)) {
            $errors['contact_phone'] = ['Enter a valid phone number of no more than 32 characters.'];
        }
        if (strlen($fields['owner_password']) < 12) {
            $errors['owner_password'] = ['Use a password of at least 12 characters.'];
        }
        if (preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $fields['platform_subdomain']) !== 1) {
            $errors['platform_subdomain'] = ['Use a lowercase DNS label without a scheme, path, or port.'];
        }
        if ($errors !== []) {
            throw new ApiException('VALIDATION_FAILED', 'Correct the highlighted onboarding fields.', 422, $errors);
        }

        $platformDomain = strtolower(trim((string) env('DAWATRACK_PLATFORM_DOMAIN', 'dawatrack.local')));
        $fields['hostname'] = $fields['platform_subdomain'] . '.' . $platformDomain;
        if (strlen($fields['hostname']) > 253) {
            throw new ApiException('VALIDATION_FAILED', 'Correct the highlighted onboarding fields.', 422, [
                'platform_subdomain' => ['The resulting hostname exceeds 253 characters.'],
            ]);
        }

        return $fields;
    }

    private function acquireLock(): void
    {
        $row = $this->database->query('SELECT GET_LOCK(?, 10) AS lock_acquired', [self::LOCK_NAME])->getRowArray();
        if ((int) ($row['lock_acquired'] ?? 0) !== 1) {
            throw new ApiException('ONBOARDING_BUSY', 'Another onboarding operation is in progress. Please retry.', 409);
        }
    }

    private function assertPrerequisites(string $ownerEmail, string $hostname): void
    {
        if ($this->database->table('sync_generations')->where('status', 'ACTIVE')->countAllResults() !== 1) {
            throw new ApiException('SYNC_GENERATION_REQUIRED', 'The platform recovery generation is not ready.', 409);
        }
        if ($this->database->table('subscription_plans')->where(['code' => 'TRIAL', 'is_active' => 1])->countAllResults() !== 1) {
            throw new ApiException('TRIAL_PLAN_REQUIRED', 'The Trial plan is not available.', 409);
        }
        if ($this->database->table('shop_domains')->where('hostname', $hostname)->countAllResults() > 0) {
            throw new ApiException('HOSTNAME_UNAVAILABLE', 'That platform address is unavailable.', 409, ['platform_subdomain' => ['Choose another platform subdomain.']]);
        }
        if (auth()->getProvider()->findByCredentials(['email' => $ownerEmail]) !== null) {
            throw new ApiException('OWNER_IDENTITY_UNAVAILABLE', 'That Owner identity cannot be used.', 409, ['owner_email' => ['Use an email not assigned to another account.']]);
        }
    }

    /** @return array{user_id: int} */
    private function createOwner(array $data): array
    {
        $provider = auth()->getProvider();
        $user = new User([
            'username' => $data['owner_name'],
            'email' => $data['owner_email'],
            'password' => $data['owner_password'],
            'active' => 1,
        ]);
        if (! $provider->save($user)) {
            throw new ApiException('VALIDATION_FAILED', 'The Owner identity is invalid.', 422, ['owner_email' => ['The Owner identity could not be created.']]);
        }
        $userId = (int) $provider->getInsertID();
        $savedUser = $provider->findById($userId);
        if ($savedUser !== null && method_exists($savedUser, 'forcePasswordReset')) {
            $savedUser->forcePasswordReset();
            $provider->save($savedUser);
        }

        return ['user_id' => $userId];
    }

    private function assertNoShopExists(int $tenantId): void
    {
        if ($this->database->table('shops')->where('tenant_id', $tenantId)->countAllResults() !== 0) {
            throw new ApiException('ONE_SHOP_INVARIANT', 'Phase 1 permits exactly one Shop per Tenant.', 409);
        }
    }

    private function createBranding(int $tenantId, int $shopId, int $adminUserId, string $shopName, string $now): string
    {
        $publicId = Uuid::v4();
        $this->database->table('shop_branding_profiles')->insert([
            'public_id' => $publicId,
            'tenant_id' => $tenantId,
            'shop_id' => $shopId,
            'brand_display_name' => $shopName,
            'primary_colour' => '#155D46',
            'secondary_colour' => '#17231F',
            'accent_colour' => '#F0A202',
            'theme_colour' => '#155D46',
            'background_colour' => '#F4F7F5',
            'pwa_full_name' => $shopName,
            'pwa_short_name' => mb_substr($shopName, 0, 24),
            'application_title' => mb_substr($shopName, 0, 100),
            'configuration_version' => 1,
            'updated_by_user_id' => $adminUserId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return $publicId;
    }

    private function createPlatformDomain(int $tenantId, int $shopId, int $adminUserId, string $hostname, string $now): string
    {
        $publicId = Uuid::v4();
        $this->database->table('shop_domains')->insert([
            'public_id' => $publicId,
            'tenant_id' => $tenantId,
            'shop_id' => $shopId,
            'hostname' => $hostname,
            'domain_type' => 'PLATFORM_SUBDOMAIN',
            'verification_status' => 'VERIFIED',
            'is_active' => true,
            'is_primary' => true,
            'verification_method' => 'PLATFORM',
            'certificate_status' => 'ACTIVE',
            'verified_at' => $now,
            'activated_at' => $now,
            'created_by_admin_user_id' => $adminUserId,
            'verified_by_admin_user_id' => $adminUserId,
            'updated_by_user_id' => $adminUserId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return $publicId;
    }

    /** @return array{public_id: string} */
    private function createTrial(int $tenantId, int $adminUserId, string $now): array
    {
        $plan = $this->database->table('subscription_plans')->where(['code' => 'TRIAL', 'is_active' => 1])->get()->getRowArray();
        if ($plan === null) {
            throw new ApiException('TRIAL_PLAN_REQUIRED', 'The Trial plan is not available.', 409);
        }
        $publicId = Uuid::v4();
        $endsAt = (new DateTimeImmutable($now, new DateTimeZone('UTC')))->modify('+30 days')->format('Y-m-d H:i:s.u');
        $this->database->table('subscriptions')->insert([
            'public_id' => $publicId,
            'tenant_id' => $tenantId,
            'subscription_plan_id' => $plan['id'],
            'entitlement_type' => 'TRIAL',
            'starts_at' => $now,
            'ends_at' => $endsAt,
            'plan_code_snapshot' => $plan['code'],
            'max_active_staff_snapshot' => $plan['max_active_staff'],
            'price_amount_snapshot' => $plan['monthly_price_amount'] ?? '0.00',
            'currency_code' => 'NGN',
            'created_by_user_id' => $adminUserId,
            'reason' => 'Initial onboarding trial',
            'created_at' => $now,
        ]);
        $subscriptionId = (int) $this->database->insertID();
        $this->database->table('subscription_events')->insert([
            'public_id' => Uuid::v4(),
            'tenant_id' => $tenantId,
            'subscription_id' => $subscriptionId,
            'event_type' => 'CREATED',
            'effective_at' => $now,
            'reason' => 'Initial onboarding trial',
            'actor_user_id' => $adminUserId,
            'created_at' => $now,
        ]);

        return ['public_id' => $publicId];
    }
}
