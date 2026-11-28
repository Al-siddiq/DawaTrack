<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application;

use App\Support\UtcTimestamp;
use CodeIgniter\Database\BaseConnection;

final class PlatformDashboardQuery
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        $asOf = UtcTimestamp::now();
        $tenantCounts = array_fill_keys(['ONBOARDING', 'ACTIVE', 'SUSPENDED', 'CLOSED'], 0);
        foreach ($this->db->query('SELECT status, COUNT(*) AS total FROM tenants GROUP BY status')->getResultArray() as $row) {
            if (array_key_exists($row['status'], $tenantCounts)) {
                $tenantCounts[$row['status']] = (int) $row['total'];
            }
        }

        $shopCounts = ['ACTIVE' => 0, 'INACTIVE' => 0];
        foreach ($this->db->query('SELECT status, COUNT(*) AS total FROM shops GROUP BY status')->getResultArray() as $row) {
            if (array_key_exists($row['status'], $shopCounts)) {
                $shopCounts[$row['status']] = (int) $row['total'];
            }
        }

        $entitlements = $this->db->query(<<<'SQL'
SELECT
    SUM(CASE WHEN current_count = 1 AND current_trial = 1 THEN 1 ELSE 0 END) AS trial_current,
    SUM(CASE WHEN current_count = 1 AND current_paid = 1 THEN 1 ELSE 0 END) AS paid_current,
    SUM(CASE WHEN current_count = 0 AND future_count = 0 AND latest_ended IS NOT NULL THEN 1 ELSE 0 END) AS expired,
    SUM(CASE WHEN future_count > 0 THEN 1 ELSE 0 END) AS scheduled,
    SUM(CASE WHEN current_count > 1 THEN 1 ELSE 0 END) AS integrity_issues
FROM (
    SELECT
        t.id,
        SUM(CASE WHEN s.id IS NOT NULL AND s.starts_at <= clock.as_of AND s.ends_at > clock.as_of THEN 1 ELSE 0 END) AS current_count,
        MAX(CASE WHEN s.starts_at <= clock.as_of AND s.ends_at > clock.as_of AND s.entitlement_type = 'TRIAL' THEN 1 ELSE 0 END) AS current_trial,
        MAX(CASE WHEN s.starts_at <= clock.as_of AND s.ends_at > clock.as_of AND s.entitlement_type = 'PAID' THEN 1 ELSE 0 END) AS current_paid,
        SUM(CASE WHEN s.starts_at > clock.as_of THEN 1 ELSE 0 END) AS future_count,
        MAX(CASE WHEN s.ends_at <= clock.as_of THEN s.ends_at ELSE NULL END) AS latest_ended
    FROM tenants t
    CROSS JOIN (SELECT ? AS as_of) clock
    LEFT JOIN subscriptions s ON s.tenant_id = t.id
    GROUP BY t.id
) entitlement_state
SQL, [$asOf])->getRowArray() ?? [];

        $platformAdmins = (int) $this->db->query(
            "SELECT COUNT(DISTINCT u.id) AS total
             FROM users u
             INNER JOIN auth_groups_users gu ON gu.user_id = u.id
             WHERE gu.`group` = 'platform_admin' AND u.active = 1 AND u.deleted_at IS NULL",
        )->getRowArray()['total'];

        $devices = [
            'state' => 'UNAVAILABLE',
            'registered' => null,
            'active' => null,
            'revoked' => null,
        ];
        if ($this->db->tableExists('registered_devices')) {
            $fields = $this->db->getFieldNames('registered_devices');
            if (in_array('status', $fields, true) && in_array('tenant_id', $fields, true)
                && in_array('shop_id', $fields, true) && in_array('public_id', $fields, true)
                && in_array('credential_hash', $fields, true)) {
                $deviceCounts = $this->db->query(
                    "SELECT COUNT(*) AS registered,
                        SUM(CASE WHEN status = 'ACTIVE' THEN 1 ELSE 0 END) AS active,
                        SUM(CASE WHEN status = 'REVOKED' THEN 1 ELSE 0 END) AS revoked
                     FROM registered_devices",
                )->getRowArray() ?? [];
                $devices = [
                    'state' => 'AVAILABLE',
                    'registered' => (int) ($deviceCounts['registered'] ?? 0),
                    'active' => (int) ($deviceCounts['active'] ?? 0),
                    'revoked' => (int) ($deviceCounts['revoked'] ?? 0),
                ];
            }
        }

        $sync = ['state' => 'UNAVAILABLE', 'active_generation_count' => null, 'generation_state' => 'UNAVAILABLE'];
        if ($this->db->tableExists('sync_generations') && in_array('status', $this->db->getFieldNames('sync_generations'), true)) {
            $active = (int) ($this->db->table('sync_generations')->where('status', 'ACTIVE')->countAllResults());
            $sync = [
                'state' => 'AVAILABLE',
                'active_generation_count' => $active,
                'generation_state' => $active === 1 ? 'READY' : 'ATTENTION',
            ];
        }

        $upcoming = $this->db->query(<<<'SQL'
SELECT t.public_id AS tenant_public_id,
       t.legal_or_business_name AS business_name,
       s.name AS shop_name,
       sub.plan_code_snapshot AS plan_code,
       sub.ends_at
FROM subscriptions sub
INNER JOIN tenants t ON t.id = sub.tenant_id
LEFT JOIN shops s ON s.tenant_id = t.id
CROSS JOIN (SELECT ? AS as_of) clock
WHERE sub.starts_at <= clock.as_of
  AND sub.ends_at > clock.as_of
  AND sub.ends_at <= DATE_ADD(clock.as_of, INTERVAL 30 DAY)
ORDER BY sub.ends_at ASC, t.id ASC
LIMIT 10
SQL, [$asOf])->getResultArray();

        return [
            'metrics' => [
                'tenants' => [
                    'total' => array_sum($tenantCounts),
                    'onboarding' => $tenantCounts['ONBOARDING'],
                    'active' => $tenantCounts['ACTIVE'],
                    'suspended' => $tenantCounts['SUSPENDED'],
                    'closed' => $tenantCounts['CLOSED'],
                ],
                'shops' => [
                    'total' => array_sum($shopCounts),
                    'active' => $shopCounts['ACTIVE'],
                    'inactive' => $shopCounts['INACTIVE'],
                ],
                'entitlements' => [
                    'trial_current' => (int) ($entitlements['trial_current'] ?? 0),
                    'paid_current' => (int) ($entitlements['paid_current'] ?? 0),
                    'expired' => (int) ($entitlements['expired'] ?? 0),
                    'scheduled' => (int) ($entitlements['scheduled'] ?? 0),
                    'integrity_issues' => (int) ($entitlements['integrity_issues'] ?? 0),
                ],
                'platform_administrators' => ['enabled' => $platformAdmins],
                'devices' => $devices,
                'sync_foundation' => $sync,
            ],
            'upcoming_expiries' => $upcoming,
            'as_of' => $this->iso($asOf),
            'freshness' => ['state' => 'CURRENT', 'as_of' => $this->iso($asOf)],
        ];
    }

    private function iso(string $value): string
    {
        return str_replace(' ', 'T', $value) . 'Z';
    }
}
