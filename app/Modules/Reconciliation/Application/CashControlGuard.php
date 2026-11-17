<?php

declare(strict_types=1);
namespace App\Modules\Reconciliation\Application;

use App\Exceptions\ApiException;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use CodeIgniter\Database\BaseConnection;

/** Shared online lock boundary for Cash effects; caller owns transaction and commit. */
final class CashControlGuard
{
    public function __construct(private readonly BaseConnection $database) {}

    public function lockShop(ShopContext $context): void
    {
        (new ControlScopeGuard($this->database))->lockRoots($context);
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
        (new ControlScopeGuard($this->database))->assertAvailable($context, 'CASH');
    }
}
