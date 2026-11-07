<?php

declare(strict_types=1);
namespace App\Modules\Sales\Infrastructure;

use App\Exceptions\ApiException;
use App\Modules\Inventory\Application\CostStateReconstructor;
use App\Modules\Sales\Application\{CommercialAccess, DraftInput, SaleMoney};
use App\Modules\TenantShop\Infrastructure\ShopContext;
use CodeIgniter\Database\BaseConnection;

final class SaleQuery
{
    public function __construct(
        private readonly BaseConnection $database,
        private readonly CommercialAccess $access,
    ) {}
    public function page(ShopContext $context, array $input): array
    {
        $this->access->read($context, "sale.view");
        $page = max(1, min(100000, (int) ($input["page"] ?? 1)));
        $size = max(1, min(50, (int) ($input["per_page"] ?? 20)));
        $search = DraftInput::text($input["search"] ?? "", "search", 160);
        $from = DraftInput::dueDate($input["from"] ?? null);
        $to = DraftInput::dueDate($input["to"] ?? null);
        if ($from !== null && $to !== null && $from > $to) {
            throw new ApiException(
                "VALIDATION_FAILED",
                "The end date must follow the start date.",
                422,
            );
        }
        $q = $this->database
            ->table("sales s")
            ->select(
                "s.public_id,s.total_amount,s.received_amount_snapshot,s.credit_created_amount_snapshot,s.occurred_at,s.posted_at,c.display_name customer_name,u.username posted_by_name",
            )
            ->join(
                "customers c",
                "c.tenant_id=s.tenant_id AND c.id=s.customer_id",
                "left",
            )
            ->join("users u", "u.id=s.posted_by_user_id", "left")
            ->where([
                "s.tenant_id" => $context->tenantId,
                "s.shop_id" => $context->shopId,
                "s.kind" => "SALE",
                "s.status" => "POSTED",
            ]);
        if ($search !== null) {
            $q->groupStart()
                ->like("s.public_id", $search)
                ->orLike("c.display_name", $search)
                ->groupEnd();
        }
        // History date filters explicitly use UTC posting dates, never a guessed Shop offset.
        if ($from !== null) {
            $q->where("s.posted_at >=", $from . " 00:00:00.000000");
        }
        if ($to !== null) {
            $q->where("s.posted_at <=", $to . " 23:59:59.999999");
        }
        $total = $q->countAllResults(false);
        return [
            "items" => $q
                ->orderBy("s.posted_at", "DESC")
                ->orderBy("s.id", "DESC")
                ->get($size, ($page - 1) * $size)
                ->getResultArray(),
            "pagination" => [
                "page" => $page,
                "per_page" => $size,
                "total" => $total,
                "total_pages" => (int) ceil($total / $size),
            ],
        ];
    }
    public function state(ShopContext $context, string $id): array
    {
        $this->access->read($context, "sale.view");
        $id = DraftInput::uuid($id);
        $row = $this->database
            ->table("sales")
            ->select("status")
            ->where([
                "tenant_id" => $context->tenantId,
                "shop_id" => $context->shopId,
                "public_id" => $id,
                "kind" => "SALE",
            ])
            ->get()
            ->getRowArray();
        if (!$row) {
            throw new ApiException(
                "SALE_NOT_FOUND",
                "The Sale was not found.",
                404,
            );
        }
        if ($row["status"] === "DRAFT") {
            return (new SaleDraftQuery($this->database, $this->access))->detail(
                $context,
                $id,
            );
        }
        return $this->receipt($context, $id);
    }
    private function receipt(ShopContext $context, string $id): array
    {
        $row = $this->database
            ->table("sales s")
            ->select(
                "s.id,s.public_id,s.kind,s.status,s.currency_code,s.subtotal_amount,s.total_amount,s.received_amount_snapshot,s.credit_created_amount_snapshot,s.occurred_at,s.device_local_offset_minutes,s.posted_at,c.public_id customer_public_id,c.display_name customer_name,u.username posted_by_name",
            )
            ->join(
                "customers c",
                "c.tenant_id=s.tenant_id AND c.id=s.customer_id",
                "left",
            )
            ->join("users u", "u.id=s.posted_by_user_id", "left")
            ->where([
                "s.tenant_id" => $context->tenantId,
                "s.shop_id" => $context->shopId,
                "s.public_id" => $id,
                "s.kind" => "SALE",
                "s.status" => "POSTED",
            ])
            ->get()
            ->getRowArray();
        if (!$row) {
            throw new ApiException(
                "SALE_NOT_FOUND",
                "The Sale receipt was not found.",
                404,
            );
        }
        $profit = $this->access->allows($context, "sale.profit.view");
        $items = $this->database
            ->table("sale_items i")
            ->select(
                "i.public_id,i.line_number,i.quantity,i.unit_selling_price,i.line_sales_amount,p.public_id product_public_id,p.name product_name,p.unit_name,m.public_id movement_public_id,m.quantity_before,m.quantity_after,m.posted_at movement_posted_at" .
                    ($profit
                        ? ",i.unit_cost_snapshot,i.line_cogs_amount,m.id movement_internal_id,i.product_id"
                        : ""),
            )
            ->join(
                "products p",
                "p.tenant_id=i.tenant_id AND p.id=i.product_id",
            )
            ->join(
                "stock_movements m",
                "m.tenant_id=i.tenant_id AND m.shop_id=i.shop_id AND m.sale_item_id=i.id AND m.movement_type='SALE' AND m.source_kind='SALE_ITEM'",
                "left",
            )
            ->where([
                "i.tenant_id" => $context->tenantId,
                "i.shop_id" => $context->shopId,
                "i.sale_id" => $row["id"],
            ])
            ->orderBy("i.line_number")
            ->get()
            ->getResultArray();
        if ($profit) {
            // Reconstruct the state BEFORE each historical movement; never use today's Product position as historical cost provenance.
            $productIds = array_values(
                array_unique(array_column($items, "product_id")),
            );
            // Each Product starts its own independent cost-state chain.
            $states = [];
            foreach ($productIds as $productId) {
                $last = max(
                    array_column(
                        array_filter(
                            $items,
                            static fn($item) => $item["product_id"] ==
                                $productId,
                        ),
                        "movement_internal_id",
                    ),
                );
                $productChain = $this->database
                    ->query(
                        "SELECT m.id,m.source_kind,m.quantity_after,m.reversal_of_movement_id,o.source_kind original_source_kind FROM stock_movements m LEFT JOIN stock_movements o ON o.tenant_id=m.tenant_id AND o.shop_id=m.shop_id AND o.id=m.reversal_of_movement_id WHERE m.tenant_id=? AND m.shop_id=? AND m.product_id=? AND m.id<=? ORDER BY m.id",
                        [
                            $context->tenantId,
                            $context->shopId,
                            $productId,
                            $last,
                        ],
                    )
                    ->getResultArray();
                $states += (new CostStateReconstructor())->beforeStates(
                    $productChain,
                );
            }
            $provisional = false;
            foreach ($items as &$item) {
                $item["cost_basis_state"] =
                    $states[(int) $item["movement_internal_id"]] ??
                    "UNESTABLISHED";
                $item["gross_profit"] = SaleMoney::difference(
                    $item["line_sales_amount"],
                    $item["line_cogs_amount"],
                );
                if ($item["cost_basis_state"] === "UNESTABLISHED") {
                    $provisional = true;
                }
                unset($item["product_id"], $item["movement_internal_id"]);
            }
            unset($item);
            $row["total_cogs_amount"] = SaleMoney::sum(
                array_column($items, "line_cogs_amount"),
            );
            $row["gross_profit"] = SaleMoney::difference(
                $row["total_amount"],
                $row["total_cogs_amount"],
            );
            $row["profit_provisional"] = $provisional;
        }
        $row["items"] = $items;
        $row["profit_visible"] = $profit;
        $row["payments"] = $this->database
            ->table("sale_payments")
            ->select(
                "public_id,direction,channel,amount,external_reference,occurred_at,posted_at",
            )
            ->where([
                "tenant_id" => $context->tenantId,
                "shop_id" => $context->shopId,
                "sale_id" => $row["id"],
            ])
            ->orderBy("id")
            ->get()
            ->getResultArray();
        $row["receivable"] = $this->database
            ->table("receivables")
            ->select("public_id,original_amount,due_date,occurred_at,posted_at")
            ->where([
                "tenant_id" => $context->tenantId,
                "shop_id" => $context->shopId,
                "sale_id" => $row["id"],
            ])
            ->get()
            ->getRowArray();
        unset($row["id"]);
        $row["estimate_only"] = false;
        $row["immutable"] = true;
        return $row;
    }
    public function receivable(ShopContext $context, string $id): array
    {
        $this->access->read($context, "sale.view");
        $row = $this->database
            ->table("receivables r")
            ->select(
                "r.public_id,r.original_amount,r.due_date,r.occurred_at,r.posted_at,s.public_id sale_public_id,c.public_id customer_public_id,c.display_name customer_name",
            )
            ->join(
                "sales s",
                "s.tenant_id=r.tenant_id AND s.shop_id=r.shop_id AND s.id=r.sale_id",
            )
            ->join(
                "customers c",
                "c.tenant_id=r.tenant_id AND c.id=r.customer_id",
            )
            ->where([
                "r.tenant_id" => $context->tenantId,
                "r.shop_id" => $context->shopId,
                "r.public_id" => DraftInput::uuid($id),
                "s.status" => "POSTED",
                "s.kind" => "SALE",
            ])
            ->get()
            ->getRowArray();
        if (!$row) {
            throw new ApiException(
                "RECEIVABLE_NOT_FOUND",
                "The Sale credit was not found.",
                404,
            );
        }
        return $row;
    }
}
