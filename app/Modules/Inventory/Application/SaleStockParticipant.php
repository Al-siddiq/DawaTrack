<?php

declare(strict_types=1);
namespace App\Modules\Inventory\Application;

use App\Exceptions\ApiException;
use App\Modules\Sales\Application\CommercialDecimal;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use App\Support\Uuid;
use CodeIgniter\Database\{BaseConnection, RawSql};

/** Inventory-owned participant. Caller owns the transaction and prelocks every Product/position in ascending ID order. */
final class SaleStockParticipant
{
    public function __construct(private readonly BaseConnection $database) {}
    public function outgoing(
        ShopContext $context,
        array $position,
        array $sale,
        array $item,
        string $postedAt,
        string $correlation,
    ): array {
        if ($this->database->transDepth < 1) {
            throw new \LogicException(
                "Sale stock requires an owning transaction.",
            );
        }
        if (
            (int) $position["tenant_id"] !== $context->tenantId ||
            (int) $position["shop_id"] !== $context->shopId ||
            (int) $position["product_id"] !== (int) $item["product_id"] ||
            (int) $sale["tenant_id"] !== $context->tenantId ||
            (int) $sale["shop_id"] !== $context->shopId ||
            (int) $item["sale_id"] !== (int) $sale["id"] ||
            (int) $item["tenant_id"] !== $context->tenantId ||
            (int) $item["shop_id"] !== $context->shopId
        ) {
            throw new \LogicException("Sale stock scope mismatch.");
        }
        $decimal = new CommercialDecimal($this->database);
        $quantity = $decimal->positiveQuantity((string) $item["quantity"]);
        // Compare the wide result before narrowing; MySQL SELECT casts can otherwise silently saturate.
        $after = (string) $this->database
            ->query(
                "SELECT CAST(? AS DECIMAL(38,4))-CAST(? AS DECIMAL(38,4)) quantity",
                [$position["expected_quantity"], $quantity],
            )
            ->getRowArray()["quantity"];
        if (!preg_match('/^-?(?:0|[1-9][0-9]{0,14})\.[0-9]{4}$/D', $after)) {
            throw new ApiException(
                "STOCK_QUANTITY_OVERFLOW",
                "This Sale would exceed supported Stock quantity precision.",
                422,
            );
        }
        $projection = new InventoryProjection($this->database);
        $state = $projection->stateAfterNonAcquisition(
            $position["cost_basis_state"],
            $after,
        );
        $cost = (string) $position["average_unit_cost"];
        $cogs = $decimal->lineCogs($quantity, $cost);
        $public = Uuid::v4();
        $this->database->table("stock_movements")->insert([
            "public_id" => $public,
            "tenant_id" => $context->tenantId,
            "shop_id" => $context->shopId,
            "product_id" => $item["product_id"],
            "stock_position_id" => $position["id"],
            "movement_type" => "SALE",
            "quantity_delta" => "-" . $quantity,
            "unit_cost_basis" => $cost,
            "quantity_before" => $position["expected_quantity"],
            "quantity_after" => $after,
            "average_cost_before" => $cost,
            "average_cost_after" => $cost,
            "source_kind" => "SALE_ITEM",
            "sale_item_id" => $item["id"],
            "occurred_at" => $sale["occurred_at"],
            "posted_at" => $postedAt,
            "created_by_user_id" => $context->userId,
            "correlation_id" => $correlation,
            "created_at" => $postedAt,
        ]);
        $movementId = (int) $this->database->insertID();
        $this->database
            ->table("stock_positions")
            ->where("id", $position["id"])
            ->update([
                "expected_quantity" => $after,
                "cost_basis_state" => $state,
                "last_movement_id" => $movementId,
                "version" => new RawSql("version + 1"),
                "updated_at" => $postedAt,
            ]);
        return [
            "position" => array_replace($position, [
                "expected_quantity" => $after,
                "cost_basis_state" => $state,
                "last_movement_id" => $movementId,
            ]),
            "unit_cost_snapshot" => $cost,
            "line_cogs_amount" => $cogs,
            "movement_public_id" => $public,
        ];
    }
}
