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
    /**
     * Read several product histories in bounded batches while preserving the same
     * movement lineage projection used by chain(). Each product is capped at its
     * requested historical movement, so later movements cannot rewrite its state.
     *
     * @param array<int,int> $throughByProduct product ID => maximum movement ID
     * @return array<int,array<int,array<string,mixed>>> product ID => ordered movement rows
     */
    public function chainsThrough(int $tenant, int $shop, array $throughByProduct): array
    {
        $targets = [];
        foreach ($throughByProduct as $product => $through) {
            $product = (int) $product;
            $through = (int) $through;
            if ($product > 0 && $through > 0) {
                $targets[$product] = $through;
            }
        }
        if (!$targets) {
            return [];
        }

        $out = [];
        foreach (array_chunk($targets, 500, true) as $batch) {
            $parts = [];
            $binds = [];
            foreach ($batch as $product => $through) {
                $parts[] = 'SELECT ? product_id, ? max_movement_id';
                $binds[] = $product;
                $binds[] = $through;
            }
            $sql = "SELECT m.*,o.source_kind original_source_kind,original_sale_move.id original_sale_movement_id
FROM stock_movements m
JOIN (" . implode(" UNION ALL ", $parts) . ") targets
  ON targets.product_id=m.product_id AND m.id<=targets.max_movement_id
LEFT JOIN stock_movements o ON o.tenant_id=m.tenant_id AND o.shop_id=m.shop_id AND o.id=m.reversal_of_movement_id
LEFT JOIN sale_items returned ON returned.tenant_id=m.tenant_id AND returned.shop_id=m.shop_id AND returned.id=m.sale_item_id
LEFT JOIN stock_movements original_sale_move ON original_sale_move.tenant_id=m.tenant_id AND original_sale_move.shop_id=m.shop_id AND original_sale_move.sale_item_id=returned.original_sale_item_id AND original_sale_move.source_kind='SALE_ITEM' AND original_sale_move.movement_type='SALE'
WHERE m.tenant_id=? AND m.shop_id=? ORDER BY m.product_id,m.id";
            $rows = $this->database->query($sql, [...$binds, $tenant, $shop])->getResultArray();
            foreach ($rows as $row) {
                $out[(int) $row['product_id']][] = $row;
            }
        }
        return $out;
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
