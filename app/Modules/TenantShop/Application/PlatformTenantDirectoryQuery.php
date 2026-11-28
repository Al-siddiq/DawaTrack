<?php

declare(strict_types=1);

namespace App\Modules\TenantShop\Application;

use App\Exceptions\ApiException;
use App\Support\UtcTimestamp;
use CodeIgniter\Database\BaseConnection;

final class PlatformTenantDirectoryQuery
{
    private const TENANT_STATUSES = ['ONBOARDING', 'ACTIVE', 'SUSPENDED', 'CLOSED'];
    private const SHOP_STATUSES = ['ACTIVE', 'INACTIVE'];
    private const ENTITLEMENT_STATUSES = ['TRIAL', 'ACTIVE', 'EXPIRED', 'SCHEDULED', 'NONE'];

    public function __construct(private readonly BaseConnection $db)
    {
    }

    /** @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    public function search(array $input): array
    {
        $filters = $this->validate($input);
        $asOf = UtcTimestamp::now();
        $base = $this->baseSql();
        $where = ['1 = 1'];
        $params = [];

        if ($filters['q'] !== '') {
            $needle = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']) . '%';
            $where[] = "(directory.tenant_public_id LIKE ? ESCAPE '!'
                OR directory.business_name LIKE ? ESCAPE '!'
                OR directory.shop_public_id LIKE ? ESCAPE '!'
                OR directory.shop_name LIKE ? ESCAPE '!'
                OR directory.primary_hostname LIKE ? ESCAPE '!')";
            array_push($params, $needle, $needle, $needle, $needle, $needle);
        }
        if ($filters['tenant_status'] !== '') {
            $where[] = 'directory.tenant_status = ?';
            $params[] = $filters['tenant_status'];
        }
        if ($filters['shop_status'] !== '') {
            $where[] = 'directory.shop_status = ?';
            $params[] = $filters['shop_status'];
        }
        if ($filters['entitlement_status'] !== '') {
            $where[] = 'directory.entitlement_status = ?';
            $params[] = $filters['entitlement_status'];
        }

        $predicate = implode(' AND ', $where);
        $totalRow = $this->db->query(
            "SELECT COUNT(*) AS total FROM ({$base}) directory WHERE {$predicate}",
            array_merge([$asOf], $params),
        )->getRowArray();
        $total = (int) ($totalRow['total'] ?? 0);
        $offset = ($filters['page'] - 1) * $filters['per_page'];

        $rows = $this->db->query(
            "SELECT directory.tenant_public_id,
                    directory.business_name,
                    directory.tenant_status,
                    directory.tenant_created_at,
                    directory.shop_public_id,
                    directory.shop_name,
                    directory.shop_status,
                    directory.primary_hostname,
                    directory.active_staff_count,
                    directory.entitlement_status,
                    directory.current_plan_code,
                    directory.current_plan_name,
                    directory.current_starts_at,
                    directory.current_ends_at,
                    directory.future_count
             FROM ({$base}) directory
             WHERE {$predicate}
             ORDER BY directory.tenant_created_at DESC, directory.sort_id DESC
             LIMIT " . $filters['per_page'] . " OFFSET " . $offset,
            array_merge([$asOf], $params),
        )->getResultArray();

        $items = array_map(static function (array $row): array {
            $shop = $row['shop_public_id'] === null ? null : [
                'public_id' => $row['shop_public_id'],
                'name' => $row['shop_name'],
                'status' => $row['shop_status'],
                'primary_hostname' => $row['primary_hostname'],
            ];

            return [
                'tenant_public_id' => $row['tenant_public_id'],
                'business_name' => $row['business_name'],
                'tenant_status' => $row['tenant_status'],
                'tenant_created_at' => $row['tenant_created_at'],
                'shop' => $shop,
                'entitlement' => [
                    'status' => $row['entitlement_status'],
                    'plan_code' => $row['current_plan_code'],
                    'plan_name' => $row['current_plan_name'],
                    'starts_at' => $row['current_starts_at'],
                    'ends_at' => $row['current_ends_at'],
                    'scheduled_count' => (int) $row['future_count'],
                ],
                'active_staff_count' => (int) $row['active_staff_count'],
            ];
        }, $rows);

        return [
            'items' => $items,
            'pagination' => [
                'page' => $filters['page'],
                'per_page' => $filters['per_page'],
                'total' => $total,
                'total_pages' => (int) ceil($total / $filters['per_page']),
            ],
            'as_of' => str_replace(' ', 'T', $asOf) . 'Z',
        ];
    }

    /** @return array<string, mixed> */
    private function validate(array $input): array
    {
        $query = $input['q'] ?? '';
        if (! is_string($query) || mb_strlen(trim($query)) > 100) {
            throw new ApiException('VALIDATION_FAILED', 'Check the Tenant search value.', 422, [
                'q' => ['Use a search value of no more than 100 characters.'],
            ]);
        }

        $page = $this->integer($input['page'] ?? '1', 'page', 1, 1000000);
        $perPage = $this->integer($input['per_page'] ?? '25', 'per_page', 1, 50);
        $tenantStatus = $this->enum($input['tenant_status'] ?? '', 'tenant_status', self::TENANT_STATUSES);
        $shopStatus = $this->enum($input['shop_status'] ?? '', 'shop_status', self::SHOP_STATUSES);
        $entitlementStatus = $this->enum($input['entitlement_status'] ?? '', 'entitlement_status', self::ENTITLEMENT_STATUSES);

        return [
            'q' => trim($query),
            'page' => $page,
            'per_page' => $perPage,
            'tenant_status' => $tenantStatus,
            'shop_status' => $shopStatus,
            'entitlement_status' => $entitlementStatus,
        ];
    }

    private function integer(mixed $value, string $field, int $min, int $max): int
    {
        if (! is_scalar($value) || ! preg_match('/\A[0-9]+\z/', (string) $value)) {
            throw new ApiException('VALIDATION_FAILED', 'Check the Tenant directory filters.', 422, [
                $field => ['Enter a valid whole number.'],
            ]);
        }
        $number = (int) $value;
        if ($number < $min || $number > $max) {
            throw new ApiException('VALIDATION_FAILED', 'Check the Tenant directory filters.', 422, [
                $field => ["Use a value from {$min} to {$max}."],
            ]);
        }
        return $number;
    }

    /** @param list<string> $allowed */
    private function enum(mixed $value, string $field, array $allowed): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (! is_string($value) || ! in_array($value, $allowed, true)) {
            throw new ApiException('VALIDATION_FAILED', 'Check the Tenant directory filters.', 422, [
                $field => ['Choose one of the available filter values.'],
            ]);
        }
        return $value;
    }

    private function baseSql(): string
    {
        return <<<'SQL'
SELECT
    t.id AS sort_id,
    t.public_id AS tenant_public_id,
    t.legal_or_business_name AS business_name,
    t.status AS tenant_status,
    t.created_at AS tenant_created_at,
    s.public_id AS shop_public_id,
    s.name AS shop_name,
    s.status AS shop_status,
    d.hostname AS primary_hostname,
    COALESCE(staff.active_staff_count, 0) AS active_staff_count,
    COALESCE(ent.current_count, 0) AS current_count,
    COALESCE(ent.future_count, 0) AS future_count,
    ent.current_plan_code,
    ent.current_starts_at,
    ent.current_ends_at,
    CASE
        WHEN COALESCE(ent.current_count, 0) > 1 THEN 'INTEGRITY_ISSUE'
        WHEN COALESCE(ent.current_count, 0) = 1 AND ent.current_type = 'TRIAL' THEN 'TRIAL'
        WHEN COALESCE(ent.current_count, 0) = 1 AND ent.current_type = 'PAID' THEN 'ACTIVE'
        WHEN COALESCE(ent.future_count, 0) > 0 THEN 'SCHEDULED'
        WHEN ent.latest_ended IS NOT NULL THEN 'EXPIRED'
        ELSE 'NONE'
    END AS entitlement_status
FROM tenants t
LEFT JOIN shops s ON s.tenant_id = t.id
LEFT JOIN (
    SELECT tenant_id, COUNT(*) AS active_staff_count
    FROM tenant_memberships
    WHERE role = 'STAFF' AND status = 'ACTIVE'
    GROUP BY tenant_id
) staff ON staff.tenant_id = t.id
LEFT JOIN (
    SELECT
        sub.tenant_id,
        SUM(CASE WHEN sub.starts_at <= clock.as_of AND sub.ends_at > clock.as_of THEN 1 ELSE 0 END) AS current_count,
        MAX(CASE WHEN sub.starts_at <= clock.as_of AND sub.ends_at > clock.as_of THEN sub.entitlement_type ELSE NULL END) AS current_type,
        MAX(CASE WHEN sub.starts_at <= clock.as_of AND sub.ends_at > clock.as_of THEN sub.plan_code_snapshot ELSE NULL END) AS current_plan_code,
        MAX(CASE WHEN sub.starts_at <= clock.as_of AND sub.ends_at > clock.as_of THEN plan.name ELSE NULL END) AS current_plan_name,
        MAX(CASE WHEN sub.starts_at <= clock.as_of AND sub.ends_at > clock.as_of THEN sub.starts_at ELSE NULL END) AS current_starts_at,
        MAX(CASE WHEN sub.starts_at <= clock.as_of AND sub.ends_at > clock.as_of THEN sub.ends_at ELSE NULL END) AS current_ends_at,
        SUM(CASE WHEN sub.starts_at > clock.as_of THEN 1 ELSE 0 END) AS future_count,
        MAX(CASE WHEN sub.ends_at <= clock.as_of THEN sub.ends_at ELSE NULL END) AS latest_ended
    FROM subscriptions sub
    INNER JOIN subscription_plans plan ON plan.id = sub.subscription_plan_id
    CROSS JOIN (SELECT ? AS as_of) clock
    GROUP BY sub.tenant_id
) ent ON ent.tenant_id = t.id
LEFT JOIN shop_domains d
    ON d.tenant_id = t.id
    AND d.shop_id = s.id
    AND d.is_active = 1
    AND d.is_primary = 1
    AND d.verification_status = 'VERIFIED'
    AND d.certificate_status = 'ACTIVE'
SQL;
    }
}
