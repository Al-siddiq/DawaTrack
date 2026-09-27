<?php

declare(strict_types=1);

namespace App\Modules\TenantShop\Application;

use App\Modules\Audit\Application\AuditService;
use App\Support\Uuid;
use CodeIgniter\Database\BaseConnection;
use DomainException;

final class OnboardingService
{
    public function __construct(private BaseConnection $database, private AuditService $audit)
    {
    }

    /** Atomically creates the one-Shop Tenant, Owner, white-label defaults, platform hostname and Trial evidence. */
    public function onboard(array $input, int $adminUserId): array
    {
        $this->assertRequired($input);
        $now = gmdate('Y-m-d H:i:s.u');
        $tenantPublicId = Uuid::v4();
        $shopPublicId = Uuid::v4();
        $slug = strtolower(trim((string) $input['platform_subdomain']));
        if (preg_match('/^[a-z0-9](?:[a-z0-9-]{1,61}[a-z0-9])?$/', $slug) !== 1) {
            throw new DomainException('The platform subdomain is invalid.');
        }

        $this->database->transException(true)->transStart();
        $this->database->query("SELECT GET_LOCK('dawatrack:onboarding', 10)");
        try {
            $this->database->table('tenants')->insert([
                'public_id' => $tenantPublicId, 'legal_or_business_name' => trim($input['business_name']), 'status' => 'ACTIVE',
                'activated_at' => $now, 'created_by_user_id' => $adminUserId, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $tenantId = (int) $this->database->insertID();
            $this->database->table('shops')->insert([
                'public_id' => $shopPublicId, 'tenant_id' => $tenantId, 'name' => trim($input['shop_name']),
                'contact_phone' => $input['contact_phone'] ?: null, 'contact_email' => $input['contact_email'] ?: null,
                'address_line_1' => trim($input['address_line_1']), 'address_line_2' => $input['address_line_2'] ?: null,
                'city_town' => trim($input['city_town']), 'state_region' => trim($input['state_region']),
                'country_code' => 'NG', 'timezone_name' => 'Africa/Lagos', 'currency_code' => 'NGN', 'status' => 'ACTIVE',
                'activated_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $shopId = (int) $this->database->insertID();
            $this->database->table('tenant_memberships')->insert([
                'public_id' => Uuid::v4(), 'tenant_id' => $tenantId, 'user_id' => (int) $input['owner_user_id'],
                'role' => 'PRIMARY_OWNER', 'status' => 'ACTIVE', 'activated_at' => $now, 'created_by_user_id' => $adminUserId,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $membershipId = (int) $this->database->insertID();
            $this->database->table('shop_branding_profiles')->insert([
                'public_id' => Uuid::v4(), 'tenant_id' => $tenantId, 'shop_id' => $shopId,
                'brand_display_name' => trim($input['shop_name']), 'primary_colour' => '#155D46', 'secondary_colour' => '#17231F',
                'accent_colour' => '#F0A202', 'theme_colour' => '#155D46', 'background_colour' => '#F4F7F5',
                'pwa_full_name' => trim($input['shop_name']), 'pwa_short_name' => mb_substr(trim($input['shop_name']), 0, 24),
                'application_title' => trim($input['shop_name']), 'configuration_version' => 1,
                'updated_by_user_id' => $adminUserId, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $hostname = $slug . '.' . strtolower((string) env('DAWATRACK_PLATFORM_DOMAIN', 'dawatrack.local'));
            $this->database->table('shop_domains')->insert([
                'public_id' => Uuid::v4(), 'tenant_id' => $tenantId, 'shop_id' => $shopId, 'hostname' => $hostname,
                'domain_type' => 'PLATFORM_SUBDOMAIN', 'verification_status' => 'VERIFIED', 'is_active' => true, 'is_primary' => true,
                'verification_method' => 'PLATFORM', 'certificate_status' => 'ACTIVE', 'verified_at' => $now, 'activated_at' => $now,
                'created_by_admin_user_id' => $adminUserId, 'verified_by_admin_user_id' => $adminUserId,
                'updated_by_user_id' => $adminUserId, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $plan = $this->database->table('subscription_plans')->where('code', 'TRIAL')->where('is_active', 1)->get()->getRowArray();
            if (! $plan) {
                throw new DomainException('The Trial plan is not configured.');
            }
            $subscriptionPublicId = Uuid::v4();
            $endsAt = gmdate('Y-m-d H:i:s.u', strtotime('+30 days'));
            $this->database->table('subscriptions')->insert([
                'public_id' => $subscriptionPublicId, 'tenant_id' => $tenantId, 'subscription_plan_id' => $plan['id'],
                'entitlement_type' => 'TRIAL', 'starts_at' => $now, 'ends_at' => $endsAt,
                'plan_code_snapshot' => $plan['code'], 'max_active_staff_snapshot' => $plan['max_active_staff'],
                'price_amount_snapshot' => $plan['monthly_price_amount'] ?? '0.00', 'currency_code' => 'NGN',
                'created_by_user_id' => $adminUserId, 'reason' => 'Initial onboarding trial', 'created_at' => $now,
            ]);
            $subscriptionId = (int) $this->database->insertID();
            $this->database->table('subscription_events')->insert([
                'public_id' => Uuid::v4(), 'tenant_id' => $tenantId, 'subscription_id' => $subscriptionId,
                'event_type' => 'CREATED', 'effective_at' => $now, 'reason' => 'Initial onboarding trial',
                'actor_user_id' => $adminUserId, 'created_at' => $now,
            ]);
            $this->audit->record([
                'tenant_id' => $tenantId, 'shop_id' => $shopId, 'actor_type' => 'USER', 'actor_user_id' => $adminUserId,
                'action_code' => 'tenant.onboarded', 'category' => 'TENANT', 'resource_type' => 'tenant',
                'resource_public_id' => $tenantPublicId, 'reason' => trim($input['reason']),
                'change_metadata' => ['shop_public_id' => $shopPublicId, 'owner_membership_id' => $membershipId, 'plan' => 'TRIAL'],
            ]);
        } finally {
            $this->database->query("SELECT RELEASE_LOCK('dawatrack:onboarding')");
        }
        $this->database->transComplete();

        return ['tenant_public_id' => $tenantPublicId, 'shop_public_id' => $shopPublicId, 'hostname' => $hostname];
    }

    private function assertRequired(array $input): void
    {
        foreach (['business_name','shop_name','owner_user_id','address_line_1','city_town','state_region','platform_subdomain','reason'] as $field) {
            if (! isset($input[$field]) || trim((string) $input[$field]) === '') {
                throw new DomainException("{$field} is required.");
            }
        }
    }
}
