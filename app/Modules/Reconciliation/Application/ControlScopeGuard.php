<?php

declare(strict_types=1);

namespace App\Modules\Reconciliation\Application;

use App\Enums\PermissionCode;
use App\Exceptions\ApiException;
use App\Modules\Access\Application\AccessGate;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use CodeIgniter\Database\BaseConnection;

/** Caller owns the transaction. Roots precede every control/source/Product lock. */
final class ControlScopeGuard
{
    public function __construct(private readonly BaseConnection $database) {}

    /** Recheck authority under Tenant -> Shop -> membership locks, before domain locks. */
    public function lockRoots(ShopContext $context, array $permissions = []): array
    {
        if ($this->database->transDepth < 1) {
            throw new \LogicException('Control protection requires an owning transaction.');
        }
        $tenant = $this->database->query('SELECT id FROM tenants WHERE id=? FOR UPDATE', [$context->tenantId])->getRowArray();
        $shop = $this->database->query('SELECT id FROM shops WHERE tenant_id=? AND id=? FOR UPDATE', [$context->tenantId, $context->shopId])->getRowArray();
        if (!$tenant || !$shop) {
            throw new ApiException('RESOURCE_NOT_FOUND', 'The Shop is unavailable.', 404);
        }
        $member = $this->database->query(
            "SELECT id,role FROM tenant_memberships WHERE tenant_id=? AND id=? AND user_id=? AND status='ACTIVE' FOR UPDATE",
            [$context->tenantId, $context->membershipId, $context->userId],
        )->getRowArray();
        if (!$member) {
            throw new ApiException('PERMISSION_DENIED', 'Active Shop membership is required.', 403);
        }
        foreach ($permissions as $permission) {
            $known = PermissionCode::accepts($permission);
            $allowed = $member['role'] === 'PRIMARY_OWNER' || $this->database->table('membership_permission_grants')->where([
                'tenant_id' => $context->tenantId, 'membership_id' => $context->membershipId,
                'permission_code' => $permission, 'revoked_at' => null,
            ])->countAllResults() === 1;
            if (!$known || !$allowed) {
                throw new ApiException('PERMISSION_DENIED', 'You do not have permission for this action.', 403);
            }
        }
        $access = (new AccessGate($this->database))->evaluate($context->tenantId, $context->shopId);
        if (!$access->permitsMutation()) {
            throw new ApiException($access->mode, $access->message, 403);
        }
        return $member;
    }

    /** Roots must already be held. Scope matching permits disjoint CASH and STOCK controls. */
    public function assertAvailable(ShopContext $context, string $scope): void
    {
        if ($this->database->transDepth < 1 || !in_array($scope, ['CASH', 'STOCK', 'FULL'], true)) {
            throw new \LogicException('Use a valid scope inside the owning transaction.');
        }
        $scopes = $scope === 'FULL' ? ['CASH', 'STOCK', 'FULL'] : [$scope, 'FULL'];
        $marks = implode(',', array_fill(0, count($scopes), '?'));
        $active = $this->database->query(
            "SELECT id FROM reconciliation_sessions WHERE tenant_id=? AND shop_id=? AND scope IN ($marks) AND status IN ('COUNTING','COUNTS_COMMITTED','REVIEW') ORDER BY id FOR UPDATE",
            [$context->tenantId, $context->shopId, ...$scopes],
        )->getRowArray();
        if ($active) {
            $code = $scope === 'CASH' ? 'CASH_CONTROL_ACTIVE' : 'STOCK_CONTROL_ACTIVE';
            throw new ApiException($code, 'Finish or cancel the open cash or stock check before saving this shop record.', 409);
        }
    }
}
