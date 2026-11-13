<?php

declare(strict_types=1);

namespace App\Modules\FinancialMovement\Application;

use App\Modules\TenantShop\Infrastructure\ShopContext;
use CodeIgniter\Database\BaseConnection;

/** Internal row append only. Owning command validates action, signs, links, locks and evidence. */
final class FinancialMovementPostingParticipant
{
    public function __construct(private readonly BaseConnection $database) {}

    public function append(ShopContext $context, array $row): void
    {
        if ($this->database->transDepth < 1) {
            throw new \LogicException('Financial movement append requires its owning transaction.');
        }
        // Scope and actor cannot be overridden by an internal payload merge.
        unset($row['id'], $row['tenant_id'], $row['shop_id'], $row['created_by_user_id']);
        $this->database->table('financial_movements')->insert($row + [
            'tenant_id' => $context->tenantId, 'shop_id' => $context->shopId, 'created_by_user_id' => $context->userId,
        ]);
    }
}
