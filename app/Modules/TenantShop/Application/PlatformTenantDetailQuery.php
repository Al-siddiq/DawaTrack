<?php

declare(strict_types=1);

namespace App\Modules\TenantShop\Application;

use App\Exceptions\ApiException;
use App\Support\UtcTimestamp;
use CodeIgniter\Database\BaseConnection;

final class PlatformTenantDetailQuery
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    /** @return array<string, mixed> */
    public function find(string $publicId): array
    {
        if (! preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i', $publicId)) {
            throw new ApiException('RESOURCE_NOT_FOUND', 'Tenant not found.', 404);
        }

        $tenant = $this->db->query(<<<'SQL'
SELECT t.public_id AS tenant_public_id,
       t.legal_or_business_name AS business_name,
       t.status AS tenant_status,
       t.status_reason,
       t.activated_at,
       t.suspended_at,
       t.closed_at,
       t.created_at AS tenant_created_at,
       s.public_id AS shop_public_id,
       s.name AS shop_name,
       s.status AS shop_status,
       s.contact_phone,
       s.contact_email,
       s.address_line_1,
       s.address_line_2,
       s.city_town,
       s.state_region,
       s.country_code,
       s.timezone_name,
       s.currency_code
FROM tenants t
LEFT JOIN shops s ON s.tenant_id = t.id
WHERE t.public_id = ?
LIMIT 1
SQL, [$publicId])->getRowArray();

        if ($tenant === null) {
            throw new ApiException('RESOURCE_NOT_FOUND', 'Tenant not found.', 404);
        }

        $asOf = UtcTimestamp::now();
        $owner = $this->db->table('tenant_memberships')
            ->select('status')
            ->where(['tenant_id' => $this->tenantId($publicId), 'role' => 'PRIMARY_OWNER', 'status' => 'ACTIVE'])
            ->get()->getRowArray();

        $tenantId = $this->tenantId($publicId);
        $shopId = $this->shopId($tenantId);
        $staffCount = (int) $this->db->table('tenant_memberships')
            ->where(['tenant_id' => $tenantId, 'role' => 'STAFF', 'status' => 'ACTIVE'])
            ->countAllResults();

        $periods = $this->db->query(<<<'SQL'
SELECT sub.public_id,
       sub.entitlement_type,
       sub.plan_code_snapshot AS plan_code,
       sub.starts_at,
       sub.ends_at,
       sub.max_active_staff_snapshot,
       plan.name AS plan_name
FROM subscriptions sub
INNER JOIN subscription_plans plan ON plan.id = sub.subscription_plan_id
WHERE sub.tenant_id = ?
ORDER BY sub.starts_at DESC, sub.id DESC
LIMIT 5
SQL, [$tenantId])->getResultArray();

        $current = null;
        $next = null;
        $history = [];
        foreach ($periods as $period) {
            if ($period['starts_at'] <= $asOf && $period['ends_at'] > $asOf) {
                if ($current === null) {
                    $current = $period;
                }
            } elseif ($period['starts_at'] > $asOf) {
                if ($next === null || $period['starts_at'] < $next['starts_at']) {
                    $next = $period;
                }
            } elseif ($period['ends_at'] <= $asOf && count($history) < 3) {
                $history[] = $period;
            }
        }

        $domain = null;
        if ($shopId !== null) {
            $domain = $this->db->table('shop_domains')
                ->select('hostname, verification_status, certificate_status, is_active, is_primary, verified_at, activated_at')
                ->where(['tenant_id' => $tenantId, 'shop_id' => $shopId, 'is_active' => 1, 'is_primary' => 1])
                ->get()->getRowArray();
        }

        $devices = ['state' => 'UNAVAILABLE', 'registered' => null, 'active' => null, 'revoked' => null];
        if ($this->db->tableExists('registered_devices') && in_array('status', $this->db->getFieldNames('registered_devices'), true)) {
            $counts = $this->db->query(
                "SELECT COUNT(*) AS registered,
                    SUM(CASE WHEN status = 'ACTIVE' THEN 1 ELSE 0 END) AS active,
                    SUM(CASE WHEN status = 'REVOKED' THEN 1 ELSE 0 END) AS revoked
                 FROM registered_devices WHERE tenant_id = ?",
                [$tenantId],
            )->getRowArray() ?? [];
            $devices = [
                'state' => 'AVAILABLE',
                'registered' => (int) ($counts['registered'] ?? 0),
                'active' => (int) ($counts['active'] ?? 0),
                'revoked' => (int) ($counts['revoked'] ?? 0),
            ];
        }

        $sync = ['state' => 'UNAVAILABLE', 'active_generation_count' => null, 'generation_state' => 'UNAVAILABLE'];
        if ($this->db->tableExists('sync_generations')) {
            $active = (int) $this->db->table('sync_generations')->where('status', 'ACTIVE')->countAllResults();
            $sync = [
                'state' => 'AVAILABLE',
                'active_generation_count' => $active,
                'generation_state' => $active === 1 ? 'READY' : 'ATTENTION',
            ];
        }

        return [
            'tenant' => [
                'public_id' => $tenant['tenant_public_id'],
                'business_name' => $tenant['business_name'],
                'status' => $tenant['tenant_status'],
                'status_reason' => $tenant['status_reason'],
                'activated_at' => $tenant['activated_at'],
                'suspended_at' => $tenant['suspended_at'],
                'closed_at' => $tenant['closed_at'],
                'created_at' => $tenant['tenant_created_at'],
            ],
            'shop' => $tenant['shop_public_id'] === null ? null : [
                'public_id' => $tenant['shop_public_id'],
                'name' => $tenant['shop_name'],
                'status' => $tenant['shop_status'],
                'contact_phone' => $tenant['contact_phone'],
                'contact_email' => $tenant['contact_email'],
                'address_line_1' => $tenant['address_line_1'],
                'address_line_2' => $tenant['address_line_2'],
                'city_town' => $tenant['city_town'],
                'state_region' => $tenant['state_region'],
                'country_code' => $tenant['country_code'],
                'timezone_name' => $tenant['timezone_name'],
                'currency_code' => $tenant['currency_code'],
            ],
            'primary_owner' => ['membership_status' => $owner['status'] ?? 'NOT_FOUND'],
            'active_staff_count' => $staffCount,
            'entitlements' => [
                'current' => $current,
                'next' => $next,
                'historical_periods' => $history,
                'history_limit' => 3,
            ],
            'primary_domain' => $domain,
            'devices' => $devices,
            'sync_foundation' => $sync,
            'as_of' => str_replace(' ', 'T', $asOf) . 'Z',
        ];
    }

    private function tenantId(string $publicId): int
    {
        return (int) $this->db->table('tenants')->select('id')->where('public_id', $publicId)->get()->getRowArray()['id'];
    }

    private function shopId(int $tenantId): ?int
    {
        $row = $this->db->table('shops')->select('id')->where('tenant_id', $tenantId)->get()->getRowArray();
        return $row === null ? null : (int) $row['id'];
    }
}
