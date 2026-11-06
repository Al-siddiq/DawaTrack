<?php
declare(strict_types=1);
namespace App\Modules\Inventory\Application;
use App\Exceptions\ApiException;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use App\Support\Uuid;
use CodeIgniter\Database\{BaseConnection, RawSql};

final class CommercialStockParticipant
{
    public function __construct(private readonly BaseConnection $database) {}
    public function incoming(
        ShopContext $c,
        array $position,
        array $header,
        array $item,
        string $now,
        string $correlation,
        string $originalState,
    ): array {
        $this->scope($c, $position, $header, $item);
        $next = (new InventoryDecimal($this->database))->incoming(
            $position["expected_quantity"],
            $position["average_unit_cost"],
            $position["cost_basis_state"],
            $item["quantity"],
            $item["unit_cost_snapshot"],
            true,
        );
        if ($originalState === "UNESTABLISHED") {
            $next["state"] = "UNESTABLISHED";
        }
        $id = $this->movement(
            $c,
            $position,
            $item,
            "CUSTOMER_RETURN",
            "SALE_ITEM",
            $item["quantity"],
            $item["unit_cost_snapshot"],
            $next,
            $header["occurred_at"],
            $now,
            $correlation,
            ["sale_item_id" => $item["id"]],
        );
        return [
            "position" => $this->apply($position, $next, $id["id"], $now),
            "movement_public_id" => $id["public_id"],
        ];
    }
    public function reversal(
        ShopContext $c,
        array $position,
        array $original,
        string $state,
        string $now,
        string $correlation,
    ): array {
        if (
            $this->database->transDepth < 1 ||
            (int) $position["tenant_id"] !== $c->tenantId ||
            (int) $position["shop_id"] !== $c->shopId ||
            (int) $original["tenant_id"] !== $c->tenantId ||
            (int) $original["shop_id"] !== $c->shopId ||
            (int) $position["id"] !== (int) $original["stock_position_id"] ||
            (int) $position["product_id"] !== (int) $original["product_id"]
        ) {
            throw new \LogicException(
                "Commercial stock reversal requires scoped prelocked position.",
            );
        }
        if (
            $position["expected_quantity"] !== $original["quantity_after"] ||
            $position["average_unit_cost"] !== $original["average_cost_after"]
        ) {
            throw new ApiException(
                "REVERSAL_STOCK_DEPENDENCY",
                "Later stock activity prevents direct reversal. Resolve its linked evidence first.",
                409,
            );
        }
        $next = [
            "quantity" => $original["quantity_before"],
            "average" => $original["average_cost_before"],
            "state" => $state,
        ];
        $delta = str_starts_with($original["quantity_delta"], "-")
            ? substr($original["quantity_delta"], 1)
            : "-" . $original["quantity_delta"];
        $id = $this->movement(
            $c,
            $position,
            $original,
            "REVERSAL",
            "REVERSAL",
            $delta,
            $original["unit_cost_basis"],
            $next,
            $now,
            $now,
            $correlation,
            [
                "reversal_of_movement_id" => $original["id"],
                "reason_code" => "COMMERCIAL_REVERSAL",
            ],
        );
        return [
            "position" => $this->apply($position, $next, $id["id"], $now),
            "movement_public_id" => $id["public_id"],
        ];
    }
    private function scope(ShopContext $c, array $p, array $h, array $i): void
    {
        if (
            $this->database->transDepth < 1 ||
            (int) $p["tenant_id"] !== $c->tenantId ||
            (int) $p["shop_id"] !== $c->shopId ||
            (int) $i["tenant_id"] !== $c->tenantId ||
            (int) $i["shop_id"] !== $c->shopId ||
            (int) $h["id"] !== (int) $i["sale_id"] ||
            (int) $p["product_id"] !== (int) $i["product_id"]
        ) {
            throw new \LogicException(
                "Commercial stock requires an owning transaction and scoped prelocked position.",
            );
        }
    }
    private function movement(
        ShopContext $c,
        array $p,
        array $item,
        string $type,
        string $source,
        string $delta,
        string $cost,
        array $next,
        string $occurred,
        string $now,
        string $correlation,
        array $links,
    ): array {
        $public = Uuid::v4();
        $this->database->table("stock_movements")->insert(
            [
                "public_id" => $public,
                "tenant_id" => $c->tenantId,
                "shop_id" => $c->shopId,
                "product_id" => $item["product_id"],
                "stock_position_id" => $p["id"],
                "movement_type" => $type,
                "source_kind" => $source,
                "quantity_delta" => $delta,
                "unit_cost_basis" => $cost,
                "quantity_before" => $p["expected_quantity"],
                "quantity_after" => $next["quantity"],
                "average_cost_before" => $p["average_unit_cost"],
                "average_cost_after" => $next["average"],
                "occurred_at" => $occurred,
                "posted_at" => $now,
                "created_by_user_id" => $c->userId,
                "correlation_id" => $correlation,
                "created_at" => $now,
            ] + $links,
        );
        return [
            "id" => (int) $this->database->insertID(),
            "public_id" => $public,
        ];
    }
    private function apply(
        array $p,
        array $next,
        int $movement,
        string $now,
    ): array {
        $values = [
            "expected_quantity" => $next["quantity"],
            "average_unit_cost" => $next["average"],
            "cost_basis_state" => $next["state"],
            "last_movement_id" => $movement,
            "version" => new RawSql("version + 1"),
            "updated_at" => $now,
        ];
        $this->database
            ->table("stock_positions")
            ->where("id", $p["id"])
            ->update($values);
        return array_replace($p, $values, [
            "version" => (int) $p["version"] + 1,
        ]);
    }
    /** Remove only exact reversal pairs; active later evidence must remain visible to suffix eligibility. */
    public function assertLatest(
        array $chain,
        array $movements,
        array $position,
    ): void {
        $stack = [];
        $byId = [];
        $states = (new CostStateReconstructor())->beforeStates($chain);
        $after = (new CostStateReconstructor())->afterStates($chain);
        foreach ($chain as $m) {
            $id = (int) $m["id"];
            $byId[$id] = $m;
            if (
                $m["source_kind"] === "REVERSAL" &&
                $stack &&
                end($stack) === (int) $m["reversal_of_movement_id"]
            ) {
                $o = $byId[(int) $m["reversal_of_movement_id"]];
                if (
                    $m["quantity_before"] !== $o["quantity_after"] ||
                    $m["quantity_after"] !== $o["quantity_before"] ||
                    $m["average_cost_before"] !== $o["average_cost_after"] ||
                    $m["average_cost_after"] !== $o["average_cost_before"] ||
                    $after[$id] !== $states[(int) $o["id"]]
                ) {
                    throw new ApiException(
                        "COMMERCIAL_EVIDENCE_INVALID",
                        "A later compensation did not restore its original state.",
                        409,
                    );
                }
                array_pop($stack);
            } else {
                $stack[] = $id;
            }
        }
        $ids = array_map(static fn($m) => (int) $m["id"], $movements);
        sort($ids, SORT_NUMERIC);
        if (array_slice($stack, -count($ids)) !== $ids) {
            $later = array_values(
                array_filter(
                    $stack,
                    static fn($movementId) => $movementId > min($ids) &&
                        !in_array($movementId, $ids, true),
                ),
            );
            $references = array_values(
                array_filter(
                    array_map(
                        static fn($movementId) => $byId[$movementId][
                            "public_id"
                        ] ?? null,
                        array_slice($later, -20),
                    ),
                ),
            );
            throw new ApiException(
                "REVERSAL_STOCK_DEPENDENCY",
                "Later unreversed stock activity prevents this reversal. Resolve the later evidence first.",
                409,
                ["movement_public_ids" => $references],
            );
        }
        $last = end($movements);
        $latest = end($chain);
        if (
            !$latest ||
            (int) $position["last_movement_id"] !== (int) $latest["id"]
        ) {
            throw new ApiException(
                "COMMERCIAL_EVIDENCE_INVALID",
                "The Stock pointer does not match current movement evidence.",
                409,
            );
        }
        if (
            $position["expected_quantity"] !== $last["quantity_after"] ||
            $position["average_unit_cost"] !== $last["average_cost_after"] ||
            $position["cost_basis_state"] !== $after[(int) $last["id"]]
        ) {
            throw new ApiException(
                "COMMERCIAL_EVIDENCE_INVALID",
                "Current Stock does not match the eligible evidence chain.",
                409,
            );
        }
    }
}
