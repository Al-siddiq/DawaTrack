<?php
declare(strict_types=1);
namespace App\Modules\Sales\Infrastructure;
use App\Exceptions\ApiException;
use App\Modules\Sales\Application\{
    CommercialAccess,
    CommercialEvidence,
    SaleMoney,
};
use App\Modules\Inventory\Application\CommercialCostHistory;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use CodeIgniter\Database\BaseConnection;

/** Read-back and recovery for the WP4-C API; never expose compensation as a new ordinary Sale. */
final class CommercialCorrectionQuery
{
    private readonly CommercialEvidence $e;
    public function __construct(
        private readonly BaseConnection $db,
        private readonly CommercialAccess $access,
    ) {
        $this->e = new CommercialEvidence($db);
    }
    private function related(ShopContext $c, string $column, int $id): ?array
    {
        return $this->db
            ->table("sales")
            ->select("public_id,kind,status")
            ->where([
                "tenant_id" => $c->tenantId,
                "shop_id" => $c->shopId,
                $column => $id,
            ])
            ->get()
            ->getRowArray();
    }
    public function state(ShopContext $c, string $uuid): array
    {
        $this->access->read($c, "sale.view");
        $r = $this->e->header($c, $uuid);
        $this->e->ordinary($r);
        if ($r["status"] !== "POSTED") {
            throw new ApiException(
                "SALE_NOT_POSTED",
                "Choose an ordinary posted Sale or Return.",
                409,
            );
        }
        $root = $this->e->root($c, $r);
        $rev = $this->e->reversal($c, (int) $r["id"]);
        $replacement = $this->related(
            $c,
            "replacement_for_sale_id",
            (int) $r["id"],
        );
        return [
            "public_id" => $r["public_id"],
            "kind" => $r["kind"],
            "status" => $r["status"],
            "original_sale_public_id" => $root["public_id"],
            "reversal_public_id" => $rev["public_id"] ?? null,
            "replacement" => $replacement
                ? array_intersect_key(
                    $replacement,
                    array_flip(["public_id", "kind", "status"]),
                )
                : null,
        ];
    }
    public function detail(ShopContext $c, string $uuid): array
    {
        $this->access->read($c, "sale.view");
        $r = $this->e->header($c, $uuid);
        if ($r["reversal_of_sale_id"] === null || $r["status"] !== "POSTED") {
            throw new ApiException(
                "COMPENSATION_NOT_FOUND",
                "A posted compensation was not found.",
                404,
            );
        }
        $root = $this->e->root($c, $r);
        $source = $this->related($c, "id", (int) $r["reversal_of_sale_id"]);
        if (!$source) {
            throw new ApiException(
                "COMMERCIAL_EVIDENCE_INVALID",
                "Compensated source is missing.",
                409,
            );
        }
        $out = array_intersect_key(
            $r,
            array_flip([
                "public_id",
                "kind",
                "status",
                "currency_code",
                "subtotal_amount",
                "total_amount",
                "received_amount_snapshot",
                "credit_created_amount_snapshot",
                "occurred_at",
                "posted_at",
            ]),
        );
        $out += [
            "operation_type" => "COMPENSATION",
            "immutable" => true,
            "original_sale_public_id" => $root["public_id"],
            "reversal_of_public_id" => $source["public_id"],
        ];
        $profit = $this->access->allows($c, "sale.profit.view");
        $out["profit_visible"] = $profit;
        $out["items"] = [];
        $provisional = false;
        foreach ($this->e->lines($c, (int) $r["id"], $profit) as $line) {
            $item = array_intersect_key(
                $line,
                array_flip([
                    "public_id",
                    "line_number",
                    "quantity",
                    "unit_selling_price",
                    "line_sales_amount",
                    "product_public_id",
                    "product_name",
                    "unit_name",
                ]),
            );
            $original = $this->db
                ->table("sale_items")
                ->select("id,public_id,original_sale_item_id")
                ->where([
                    "tenant_id" => $c->tenantId,
                    "shop_id" => $c->shopId,
                    "sale_id" => $r["reversal_of_sale_id"],
                    "id" => $line["original_sale_item_id"],
                ])
                ->get()
                ->getRowArray();
            if (!$original) {
                throw new ApiException(
                    "COMMERCIAL_EVIDENCE_INVALID",
                    "Immediate source line is missing.",
                    409,
                );
            }
            $item["original_sale_item_public_id"] = $original["public_id"];
            $movement = $this->e->movement($c, (int) $original["id"]);
            $inverse = $this->db
                ->table("stock_movements")
                ->where([
                    "tenant_id" => $c->tenantId,
                    "shop_id" => $c->shopId,
                    "reversal_of_movement_id" => $movement["id"],
                ])
                ->get()
                ->getRowArray();
            if (!$inverse) {
                throw new ApiException(
                    "COMMERCIAL_EVIDENCE_INVALID",
                    "Inverse movement is missing.",
                    409,
                );
            }
            $item += [
                "movement_public_id" => $inverse["public_id"],
                "reversal_of_movement_public_id" => $movement["public_id"],
                "quantity_before" => $inverse["quantity_before"],
                "quantity_after" => $inverse["quantity_after"],
                "movement_posted_at" => $inverse["posted_at"],
            ];
            if ($profit) {
                $basis =
                    $original["original_sale_item_id"] === null
                        ? $movement
                        : $this->e->movement(
                            $c,
                            (int) $original["original_sale_item_id"],
                        );
                $state = (new CommercialCostHistory($this->db))->before(
                    $c->tenantId,
                    $c->shopId,
                    (int) $line["product_id"],
                    (int) $basis["id"],
                );
                $item += [
                    "unit_cost_snapshot" => $line["unit_cost_snapshot"],
                    "line_cogs_amount" => $line["line_cogs_amount"],
                    "cost_basis_state" => $state,
                ];
                $provisional = $provisional || $state === "UNESTABLISHED";
            }
            $out["items"][] = $item;
        }
        if ($profit) {
            $out["total_cogs_amount"] = SaleMoney::sum(
                array_column($out["items"], "line_cogs_amount"),
            );
            $margin = SaleMoney::difference(
                $r["total_amount"],
                $out["total_cogs_amount"],
            );
            $out["gross_profit_effect"] =
                $r["kind"] === "RETURN" && $margin !== "0.00"
                    ? (str_starts_with($margin, "-")
                        ? substr($margin, 1)
                        : "-" . $margin)
                    : $margin;
            $out["profit_provisional"] = $provisional;
        }
        $out["payments"] = [];
        foreach (
            $this->db
                ->table("sale_payments")
                ->where([
                    "tenant_id" => $c->tenantId,
                    "shop_id" => $c->shopId,
                    "sale_id" => $r["id"],
                ])
                ->orderBy("id")
                ->get()
                ->getResultArray()
            as $p
        ) {
            $original = $this->db
                ->table("sale_payments")
                ->select("public_id")
                ->where([
                    "tenant_id" => $c->tenantId,
                    "shop_id" => $c->shopId,
                    "id" => $p["reversal_of_payment_id"],
                ])
                ->get()
                ->getRowArray();
            $out["payments"][] = array_intersect_key(
                $p,
                array_flip([
                    "public_id",
                    "direction",
                    "channel",
                    "amount",
                    "occurred_at",
                    "posted_at",
                ]),
            ) + [
                "reversal_of_payment_public_id" =>
                    $original["public_id"] ?? null,
            ];
        }
        $out["credit_adjustments"] = [];
        foreach (
            $this->db
                ->table("receivable_adjustments")
                ->where([
                    "tenant_id" => $c->tenantId,
                    "shop_id" => $c->shopId,
                    "sale_id" => $r["id"],
                ])
                ->orderBy("id")
                ->get()
                ->getResultArray()
            as $a
        ) {
            $credit = $this->db
                ->table("receivables")
                ->select("public_id")
                ->where([
                    "tenant_id" => $c->tenantId,
                    "shop_id" => $c->shopId,
                    "id" => $a["receivable_id"],
                ])
                ->get()
                ->getRowArray();
            $original =
                $a["reversal_of_adjustment_id"] === null
                    ? null
                    : $this->db
                        ->table("receivable_adjustments")
                        ->select("public_id")
                        ->where([
                            "tenant_id" => $c->tenantId,
                            "shop_id" => $c->shopId,
                            "id" => $a["reversal_of_adjustment_id"],
                        ])
                        ->get()
                        ->getRowArray();
            $out["credit_adjustments"][] = array_intersect_key(
                $a,
                array_flip([
                    "public_id",
                    "adjustment_type",
                    "amount_delta",
                    "reason",
                    "occurred_at",
                    "posted_at",
                ]),
            ) + [
                "receivable_public_id" => $credit["public_id"] ?? null,
                "reversal_of_adjustment_public_id" =>
                    $original["public_id"] ?? null,
            ];
        }
        $out["reason"] =
            $this->db
                ->table("audit_events")
                ->select("reason")
                ->where([
                    "tenant_id" => $c->tenantId,
                    "shop_id" => $c->shopId,
                    "resource_type" => "sale",
                    "resource_public_id" => $uuid,
                    "action_code" => "sale.reversed",
                ])
                ->orderBy("id", "DESC")
                ->get()
                ->getRowArray()["reason"] ?? null;
        return $out;
    }
}
