<?php

declare(strict_types=1);
namespace App\Modules\Reconciliation\Application;

use App\Exceptions\ApiException;
use App\Modules\Access\Application\AccessGate;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use CodeIgniter\Database\BaseConnection;

/** Shared online lock boundary for Cash effects; caller owns transaction and commit. */
final class CashControlGuard
{
    public function __construct(private readonly BaseConnection $database) {}

    public function lockShop(ShopContext $context): void
    {
        if ($this->database->transDepth < 1) {
            throw new \LogicException(
                "Cash control requires the owning transaction.",
            );
        }
        $tenant = $this->database
            ->query("SELECT id FROM tenants WHERE id=? FOR UPDATE", [
                $context->tenantId,
            ])
            ->getRowArray();
        $shop = $this->database
            ->query(
                "SELECT id FROM shops WHERE tenant_id=? AND id=? FOR UPDATE",
                [$context->tenantId, $context->shopId],
            )
            ->getRowArray();
        if (!$tenant || !$shop) {
            throw new ApiException(
                "RESOURCE_NOT_FOUND",
                "The Shop is unavailable.",
                404,
            );
        }
        $access = (new AccessGate($this->database))->evaluate(
            $context->tenantId,
            $context->shopId,
        );
        if (!$access->permitsMutation()) {
            throw new ApiException($access->mode, $access->message, 403);
        }
    }

    /** Invoke BEFORE original Sale/Product/Position/Receivable locks, only when there is a Cash effect. */
    public function assertCanAffectCash(ShopContext $context): void
    {
        $this->lockShop($context);
        $baseline = $this->database
            ->query(
                "SELECT id FROM reconciliation_sessions WHERE tenant_id=? AND shop_id=? AND scope='CASH' AND control_kind='OPENING' AND status='FINALIZED' ORDER BY finalized_at DESC,id DESC LIMIT 1 FOR UPDATE",
                [$context->tenantId, $context->shopId],
            )
            ->getRowArray();
        if (!$baseline) {
            throw new ApiException(
                "OPENING_CASH_REQUIRED",
                "Finalize Opening Cash before recording a Cash transaction.",
                409,
            );
        }
        $active = $this->database
            ->query(
                "SELECT id FROM reconciliation_sessions WHERE tenant_id=? AND shop_id=? AND scope IN ('CASH','FULL') AND status IN ('COUNTING','COUNTS_COMMITTED','REVIEW') FOR UPDATE",
                [$context->tenantId, $context->shopId],
            )
            ->getRowArray();
        if ($active) {
            throw new ApiException(
                "CASH_CONTROL_ACTIVE",
                "Finish the active Cash control before recording a Cash transaction.",
                409,
            );
        }
    }
}
