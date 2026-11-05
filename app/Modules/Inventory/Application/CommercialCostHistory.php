<?php
declare(strict_types=1);
namespace App\Modules\Inventory\Application;
use CodeIgniter\Database\BaseConnection;

/** One Inventory-owned historical reader used by posting, Returns, reversals and receipt display. */
final class CommercialCostHistory
{
    public function __construct(private readonly BaseConnection $database) {}
    public function chain(
        int $tenant,
        int $shop,
        int $product,
        ?int $through = null,
        bool $current = false,
    ): array {
        $sql = "SELECT m.*,o.source_kind original_source_kind,original_sale_move.id original_sale_movement_id
FROM stock_movements m
LEFT JOIN stock_movements o ON o.tenant_id=m.tenant_id AND o.shop_id=m.shop_id AND o.id=m.reversal_of_movement_id
LEFT JOIN sale_items returned ON returned.tenant_id=m.tenant_id AND returned.shop_id=m.shop_id AND returned.id=m.sale_item_id
LEFT JOIN stock_movements original_sale_move ON original_sale_move.tenant_id=m.tenant_id AND original_sale_move.shop_id=m.shop_id AND original_sale_move.sale_item_id=returned.original_sale_item_id AND original_sale_move.source_kind='SALE_ITEM' AND original_sale_move.movement_type='SALE'
WHERE m.tenant_id=? AND m.shop_id=? AND m.product_id=?";
        $args = [$tenant, $shop, $product];
        if ($through !== null) {
            $sql .= " AND m.id<=?";
            $args[] = $through;
        }
        return $this->database
            ->query(
                $sql . " ORDER BY m.id" . ($current ? " FOR UPDATE" : ""),
                $args,
            )
            ->getResultArray();
    }
    public function before(
        int $tenant,
        int $shop,
        int $product,
        int $movement,
    ): string {
        return (new CostStateReconstructor())->beforeStates(
            $this->chain($tenant, $shop, $product, $movement),
        )[$movement] ?? "UNESTABLISHED";
    }
    public function returnEra(int $tenant, int $shop, int $product): bool
    {
        return $this->database
            ->query(
                "SELECT id FROM stock_movements WHERE tenant_id=? AND shop_id=? AND product_id=? AND movement_type='CUSTOMER_RETURN' LIMIT 1 FOR UPDATE",
                [$tenant, $shop, $product],
            )
            ->getRowArray() !== null;
    }
}
