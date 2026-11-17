<?php

declare(strict_types=1);

namespace App\Modules\Reconciliation\Application;

use App\Modules\TenantShop\Infrastructure\ShopContext;
use CodeIgniter\Database\BaseConnection;

final class StockControlGuard
{
    public function __construct(private readonly BaseConnection $database) {}

    /** Invoke before source/Product/position locks; never owns or commits a transaction. */
    public function assertCanAffectStock(ShopContext $context, array $permissions = []): void
    {
        $guard = new ControlScopeGuard($this->database);
        $guard->lockRoots($context, $permissions);
        $guard->assertAvailable($context, 'STOCK');
    }
}
