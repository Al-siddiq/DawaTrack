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
        $this->database
            ->table("stock_movements")
            ->insert(
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
}
