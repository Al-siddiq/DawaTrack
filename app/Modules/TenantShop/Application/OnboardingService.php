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
     * @return array{tenant_public_id: string, shop_public_id: string, hostname: string, owner_email: string, owner_membership_public_id: string, owner_user_id: int, owner_identity_reused: bool, tenant_status: string, shop_status: string, owner_membership_status: string}
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
            $membershipInvariants = new MembershipInvariantService($this->database);
            $membershipInvariants->assertUserMayActivate($ownerUserId);
            $membershipInvariants->assertUserMayBeInvitedAsPrimaryOwner($ownerUserId);

            $tenantPublicId = Uuid::v4();
            $this->database->table('tenants')->insert([
                'public_id' => $tenantPublicId,
                'legal_or_business_name' => $data['business_name'],
                'status' => 'ONBOARDING',
                'activated_at' => null,
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
                'status' => 'INACTIVE',
                'activated_at' => null,
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
                'status' => 'INVITED',
                'activated_at' => null,
                'created_by_user_id' => $adminUserId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $membershipId = (int) $this->database->insertID();

            $brandingPublicId = $this->createBranding($tenantId, $shopId, $adminUserId, $data['shop_name'], $now);
            $domainPublicId = $this->createPlatformDomain($tenantId, $shopId, $adminUserId, $data['hostname'], $now);

            $platformActor = AuditActor::platformUser($adminUserId, $tenantId, $shopId);
            $this->audit->append(new AuditAppendCommand(
                actor: $platformActor,
                actionCode: 'owner.created',
                category: AuditCategory::SECURITY,
                resourceType: 'tenant_membership',
                resourcePublicId: $membershipPublicId,
                reason: $data['reason'],
                metadata: ['role' => 'PRIMARY_OWNER', 'status' => 'INVITED', 'identity_reused' => $ownerIdentityReused],
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
                    'owner_status' => 'INVITED',
                    'tenant_status' => 'ONBOARDING',
                    'owner_identity_reused' => $ownerIdentityReused,
                'tenant_status' => 'ONBOARDING',
                'shop_status' => 'INACTIVE',
                'owner_membership_status' => 'INVITED',
                ],
            ));
            (new ChangeSequenceService($this->database, service('requestContext')))->publish([
                new ChangeCommand($tenantId, null, 'tenant', $tenantPublicId),
                new ChangeCommand($tenantId, $shopId, 'shop', $shopPublicId),
                new ChangeCommand($tenantId, $shopId, 'tenant_membership', $membershipPublicId),
                new ChangeCommand($tenantId, $shopId, 'shop_branding_profile', $brandingPublicId),
                new ChangeCommand($tenantId, $shopId, 'shop_domain', $domainPublicId),
            ]);

            if (! $this->database->transCommit()) {
                throw new ApiException('ONBOARDING_FAILED', 'The Tenant could not be committed.', 500);
            }

            return [
                'tenant_public_id' => $tenantPublicId,
                'shop_public_id' => $shopPublicId,
                'hostname' => $data['hostname'],
                'owner_email' => $data['owner_email'],
                'owner_membership_public_id' => $membershipPublicId,
                'owner_user_id' => $ownerUserId,
                'owner_identity_reused' => $ownerIdentityReused,
            ];
        } catch (Throwable $exception) {
            $this->database->transRollback();
            throw $exception;
        } finally {
            $this->database->query('SELECT RELEASE_LOCK(?)', [self::LOCK_NAME]);
        }
    }

    /**
     * Completes a Primary Owner invitation after Shield magic-link authentication.
     *
     * @return array{tenant_public_id: string, shop_public_id: string, trial_public_id: string, password_changed: bool}
     */
    public function completeOwnerActivation(int $userId, ?string $newPassword = null): array
    {
        $this->acquireLock();
        try {
            $this->database->transException(true)->transBegin();
            try {
                $membership = $this->database->query(<<<'SQL'
SELECT m.id AS membership_id,
       m.public_id AS membership_public_id,
       m.tenant_id,
       m.user_id,
       t.public_id AS tenant_public_id,
       t.status AS tenant_status,
       s.id AS shop_id,
       s.public_id AS shop_public_id,
       s.status AS shop_status
FROM tenant_memberships m
INNER JOIN tenants t ON t.id = m.tenant_id
INNER JOIN shops s ON s.tenant_id = t.id
WHERE m.user_id = ?
  AND m.role = 'PRIMARY_OWNER'
  AND m.status = 'INVITED'
LIMIT 1
FOR UPDATE
SQL, [$userId])->getRowArray();

                if ($membership === null || $membership['tenant_status'] !== 'ONBOARDING' || $membership['shop_status'] !== 'INACTIVE') {
                    throw new ApiException('OWNER_INVITATION_NOT_PENDING', 'There is no pending Owner invitation to activate.', 409);
                }
                if ($this->database->table('tenant_memberships')
                    ->where(['user_id' => $userId, 'role' => 'PRIMARY_OWNER', 'status' => 'INVITED'])
                    ->countAllResults() !== 1) {
                    throw new ApiException('OWNER_INVITATION_AMBIGUOUS', 'The Owner invitation state needs Platform Administrator review.', 409);
                }
                $membershipInvariants = new MembershipInvariantService($this->database);
                $membershipInvariants->assertUserMayBeInvitedAsPrimaryOwner($userId, (int) $membership['tenant_id']);
                if ($this->database->table('shops')->where('tenant_id', (int) $membership['tenant_id'])->countAllResults() !== 1) {
                    throw new ApiException('ONE_SHOP_INVARIANT', 'The Tenant Shop relationship needs Platform Administrator review.', 409);
                }
                if ($this->database->table('sync_generations')->where('status', 'ACTIVE')->countAllResults() !== 1) {
                    throw new ApiException('SYNC_GENERATION_REQUIRED', 'The platform recovery generation is not ready. Contact the Platform Administrator.', 409);
                }
                if ($this->database->table('subscription_plans')->where(['code' => 'TRIAL', 'is_active' => 1])->countAllResults() !== 1) {
                    throw new ApiException('TRIAL_PLAN_REQUIRED', 'The Trial plan is not available. Contact the Platform Administrator.', 409);
                }

                $provider = auth()->getProvider();
                $user = $provider->findById($userId);
                if ($user === null || ! $user->active || $user->inGroup('platform_admin')) {
                    throw new ApiException('OWNER_IDENTITY_UNAVAILABLE', 'The Owner identity is unavailable.', 409);
                }
                $emailIdentity = $this->database->table('auth_identities')
                    ->where(['user_id' => $userId, 'type' => 'email_password'])
                    ->get()->getRowArray();
                $requiresReset = $emailIdentity !== null && (int) ($emailIdentity['force_reset'] ?? 0) === 1;
                if ($requiresReset && ($newPassword === null || $newPassword === '')) {
                    throw new ApiException('VALIDATION_FAILED', 'Choose a new password to finish setting up your account.', 422, ['password' => ['A new password is required.']]);
                }
                if (! $requiresReset && $newPassword !== null) {
                    throw new ApiException('OWNER_PASSWORD_STATE_CHANGED', 'The Owner password state changed. Reload and try again.', 409);
                }

                (new MembershipInvariantService($this->database))->assertUserMayActivate($userId, (int) $membership['membership_id']);
                $now = UtcTimestamp::now();
                $passwordChanged = false;
                if ($requiresReset) {
                    $user->password = $newPassword;
                    if (! $provider->save($user)) {
                        throw new ApiException('OWNER_PASSWORD_SETUP_FAILED', 'The new password could not be saved.', 500);
                    }
                    $user->undoForcePasswordReset();
                    $passwordChanged = true;
                }

                $this->database->table('tenant_memberships')->where('id', (int) $membership['membership_id'])->update([
                    'status' => 'ACTIVE',
                    'activated_at' => $now,
                    'deactivated_at' => null,
                    'updated_at' => $now,
                ]);
                $this->database->table('tenants')->where('id', (int) $membership['tenant_id'])->update([
                    'status' => 'ACTIVE',
                    'activated_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->database->table('shops')->where('id', (int) $membership['shop_id'])->update([
                    'status' => 'ACTIVE',
                    'activated_at' => $now,
                    'deactivated_at' => null,
                    'updated_at' => $now,
                ]);

                $trial = $this->createTrial((int) $membership['tenant_id'], $userId, $now);
                $this->audit->append(new AuditAppendCommand(
                    actor: AuditActor::identityUser($userId),
                    actionCode: 'owner.activated',
                    category: AuditCategory::SECURITY,
                    resourceType: 'tenant_membership',
                    resourcePublicId: $membership['membership_public_id'],
                    reason: 'Owner accepted the onboarding invitation.',
                    metadata: [
                        'membership_status' => 'ACTIVE',
                        'tenant_status' => 'ACTIVE',
                        'shop_status' => 'ACTIVE',
                        'trial_created' => true,
                    ],
                ));
                if ($passwordChanged) {
                    $this->audit->append(new AuditAppendCommand(
                        actor: AuditActor::identityUser($userId),
                        actionCode: 'identity.password_changed',
                        category: AuditCategory::SECURITY,
                        resourceType: 'user_identity',
                        reason: 'Owner completed the invitation password setup.',
                        metadata: ['forced_reset' => true],
                    ));
                }

                (new ChangeSequenceService($this->database, service('requestContext')))->publish([
                    new ChangeCommand((int) $membership['tenant_id'], null, 'tenant', $membership['tenant_public_id']),
                    new ChangeCommand((int) $membership['tenant_id'], (int) $membership['shop_id'], 'shop', $membership['shop_public_id']),
                    new ChangeCommand((int) $membership['tenant_id'], (int) $membership['shop_id'], 'tenant_membership', $membership['membership_public_id']),
                    new ChangeCommand((int) $membership['tenant_id'], null, 'subscription', $trial['public_id']),
                ]);

                if (! $this->database->transCommit()) {
                    throw new ApiException('OWNER_ACTIVATION_FAILED', 'Owner activation could not be committed.', 500);
                }

                return [
                    'tenant_public_id' => $membership['tenant_public_id'],
                    'shop_public_id' => $membership['shop_public_id'],
                    'trial_public_id' => $trial['public_id'],
                    'password_changed' => $passwordChanged,
                ];
            } catch (Throwable $exception) {
                $this->database->transRollback();
                throw $exception;
            }
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
            'reason' => is_string($input['reason'] ?? null) ? trim($input['reason']) : '',
        ];
        $errors = [];
        foreach (['business_name', 'shop_name', 'address_line_1', 'city_town', 'state_region', 'platform_subdomain', 'owner_name', 'owner_email', 'reason'] as $required) {
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
        $existingOwner = auth()->getProvider()->findByCredentials(['email' => $ownerEmail]);
        if ($existingOwner !== null) {
            if (! $existingOwner->active || $existingOwner->inGroup('platform_admin')) {
                throw new ApiException('OWNER_IDENTITY_UNAVAILABLE', 'That Owner identity cannot be used.', 409, ['owner_email' => ['Choose an active non-Platform account.']]);
            }
            $membershipInvariants = new MembershipInvariantService($this->database);
            $membershipInvariants->assertUserMayActivate((int) $existingOwner->id);
            $membershipInvariants->assertUserMayBeInvitedAsPrimaryOwner((int) $existingOwner->id);
        }
    }

    /** @return array{user_id: int, identity_reused: bool} */
    private function createOwner(array $data): array
    {
        $provider = auth()->getProvider();
        $user = $provider->findByCredentials(['email' => $data['owner_email']]);
        $identityReused = $user !== null;
        if ($user === null) {
            $user = new User([
                'username' => $data['owner_name'],
                'email' => $data['owner_email'],
                'password' => 'Dt!' . bin2hex(random_bytes(24)) . 'Aa9!',
                'active' => 1,
            ]);
            if (! $provider->save($user)) {
                throw new ApiException('VALIDATION_FAILED', 'The Owner identity is invalid.', 422, ['owner_email' => ['The Owner identity could not be created.']]);
            }
            $user = $provider->findById((int) $provider->getInsertID());
        }
        if ($user === null || ! $user->active || $user->inGroup('platform_admin')) {
            throw new ApiException('OWNER_IDENTITY_UNAVAILABLE', 'That Owner identity cannot be used.', 409);
        }

        $emailIdentity = $this->database->table('auth_identities')
            ->where(['user_id' => (int) $user->id, 'type' => 'email_password'])
            ->get()->getRowArray();
        if ($emailIdentity === null) {
            throw new ApiException('OWNER_IDENTITY_UNAVAILABLE', 'The Owner email identity cannot receive a secure invitation.', 409);
        }
        if ((int) ($emailIdentity['force_reset'] ?? 0) !== 1) {
            $user->forcePasswordReset();
            if (! $provider->save($user)) {
                throw new ApiException('OWNER_IDENTITY_UNAVAILABLE', 'The Owner password setup state could not be prepared.', 409);
            }
        }
        $emailIdentity = $this->database->table('auth_identities')
            ->where(['user_id' => (int) $user->id, 'type' => 'email_password'])
            ->get()->getRowArray();
        if ($emailIdentity === null || (int) ($emailIdentity['force_reset'] ?? 0) !== 1) {
            throw new ApiException('OWNER_IDENTITY_UNAVAILABLE', 'The Owner password setup state could not be prepared.', 409);
        }

        return ['user_id' => (int) $user->id, 'identity_reused' => $identityReused];
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
