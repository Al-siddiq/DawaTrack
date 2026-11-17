<?php

declare(strict_types=1);

namespace App\Modules\Reconciliation\Application;

use App\Exceptions\ApiException;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use CodeIgniter\Database\BaseConnection;

/** Server-side source-read boundary for standard blind counting, across user sessions. */
final class BlindReadPolicy
{
    public function __construct(private readonly BaseConnection $database) {}

    public function assertSourceVisible(ShopContext $context, string $path): void
    {
        $prefix = '/api/v1/shop/';
        if (!str_starts_with($path, $prefix)) {
            return;
        }
        $resource = explode('/', substr($path, strlen($prefix)))[0];
        $stock = in_array($resource, ['products', 'stock-intakes', 'stock-movements', 'sales', 'sale-drafts', 'sale-draft-lookups', 'returns', 'commercial-compensations'], true);
        $cash = in_array($resource, ['financial-movements', 'opening-cash', 'sales', 'sale-drafts', 'returns', 'commercial-compensations',
            'customers', 'debts', 'debt-bills', 'debt-payments', 'debt-adjustments', 'sale-credit'], true);
        $audit = $resource === 'audit-events';
        if (!$stock && !$cash && !$audit) {
            return;
        }
        $rows = $this->database->query(
            "SELECT scope,started_by_user_id FROM reconciliation_sessions WHERE tenant_id=? AND shop_id=? AND control_kind='STANDARD' AND status='COUNTING'",
            [$context->tenantId, $context->shopId],
        )->getResultArray();
        if (!$rows) {
            return;
        }
        $member = $this->database->table('tenant_memberships')->where([
            'tenant_id' => $context->tenantId, 'id' => $context->membershipId, 'user_id' => $context->userId, 'status' => 'ACTIVE',
        ])->get()->getRowArray();
        $counter = $member && ($member['role'] === 'PRIMARY_OWNER' || $this->database->table('membership_permission_grants')->where([
            'tenant_id' => $context->tenantId, 'membership_id' => $context->membershipId,
            'permission_code' => 'reconciliation.count', 'revoked_at' => null,
        ])->countAllResults() > 0);
        foreach ($rows as $row) {
            if (!$counter && (int) $row['started_by_user_id'] !== $context->userId) {
                continue;
            }
            if ($audit || ($stock && in_array($row['scope'], ['STOCK', 'FULL'], true)) || ($cash && in_array($row['scope'], ['CASH', 'FULL'], true))) {
                throw new ApiException('BLIND_COUNT_ACTIVE', 'Confirm your count before opening the shop records that show how much should be there.', 409);
            }
        }
    }
    /** Also protects predecessor control DTOs/notes from becoming a blind-count bypass. */
    public function blocksScope(ShopContext $context, string $scope): bool
    {
        $rows = $this->database->query(
            "SELECT started_by_user_id FROM reconciliation_sessions WHERE tenant_id=? AND shop_id=? AND control_kind='STANDARD' AND status='COUNTING' AND scope IN (?, 'FULL')",
            [$context->tenantId, $context->shopId, $scope],
        )->getResultArray();
        if (!$rows) {
            return false;
        }
        $member = $this->database->table('tenant_memberships')->where([
            'tenant_id' => $context->tenantId, 'id' => $context->membershipId, 'user_id' => $context->userId, 'status' => 'ACTIVE',
        ])->get()->getRowArray();
        if ($member && ($member['role'] === 'PRIMARY_OWNER' || $this->database->table('membership_permission_grants')->where([
            'tenant_id' => $context->tenantId, 'membership_id' => $context->membershipId,
            'permission_code' => 'reconciliation.count', 'revoked_at' => null,
        ])->countAllResults() > 0)) {
            return true;
        }
        return in_array($context->userId, array_map('intval', array_column($rows, 'started_by_user_id')), true);
    }

}
