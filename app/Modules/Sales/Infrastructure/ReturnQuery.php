<?php
declare(strict_types=1);
namespace App\Modules\Sales\Infrastructure;

use App\Exceptions\ApiException;
use App\Modules\Sales\Application\{
    CommercialAccess,
    CommercialEvidence,
    DraftInput,
    SaleMoney,
};
use App\Modules\Inventory\Application\CommercialCostHistory;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use CodeIgniter\Database\BaseConnection;

/** Explicit safe read model for WP4-B. Correction commands and browser UI arrive later. */
final class ReturnQuery
{
    private readonly CommercialEvidence $e;
    public function __construct(
        private readonly BaseConnection $db,
        private readonly CommercialAccess $access,
    ) {
        $this->e = new CommercialEvidence($db);
    }
    public function page(ShopContext $c, array $input): array
    {
        $this->access->read($c, "sale.view");
        $page = max(1, min(100000, (int) ($input["page"] ?? 1)));
        $size = max(1, min(50, (int) ($input["per_page"] ?? 20)));
        $status = $input["status"] ?? "POSTED";
        if (!in_array($status, ["DRAFT", "POSTED"], true)) {
            throw new ApiException(
                "VALIDATION_FAILED",
                "Choose DRAFT or POSTED Returns.",
                422,
            );
        }
        $q = $this->db
            ->table("sales r")
            ->select(
                "r.public_id,r.status,r.total_amount,r.occurred_at,r.posted_at,r.updated_at,o.public_id original_sale_public_id",
            )
            ->join(
                "sales o",
                "o.tenant_id=r.tenant_id AND o.shop_id=r.shop_id AND o.id=r.original_sale_id",
            )
            ->where([
                "r.tenant_id" => $c->tenantId,
                "r.shop_id" => $c->shopId,
                "r.kind" => "RETURN",
                "r.reversal_of_sale_id" => null,
                "r.status" => $status,
            ]);
        if (isset($input["original_sale_public_id"])) {
            $q->where(
                "o.public_id",
                DraftInput::uuid($input["original_sale_public_id"]),
            );
        }
        $total = $q->countAllResults(false);
        return [
            "items" => $q
                ->orderBy("r.id", "DESC")
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
    public function eligible(ShopContext $c, string $id): array
    {
        $this->access->read($c, "sale.view");
        $this->access->read($c, "sale.return");
        $row = $this->e->header($c, $id);
        $root = $this->e->root($c, $row);
        if (
            $row["id"] != $root["id"] ||
            $this->e->reversal($c, (int) $root["id"])
        ) {
            throw new ApiException(
                "ORIGINAL_SALE_REQUIRED",
                "Choose an unreversed original posted Sale.",
                409,
            );
        }
        $out = [];
        foreach ($this->e->lines($c, (int) $root["id"], false) as $line) {
            $used = $this->e->returned($c, (int) $line["id"]);
            $remaining = (string) $this->db
                ->query(
                    "SELECT CAST(? AS DECIMAL(38,4))-CAST(? AS DECIMAL(38,4)) value",
                    [$line["quantity"], $used["quantity"]],
                )
                ->getRowArray()["value"];
            $out[] = [
                "public_id" => $line["public_id"],
                "product_public_id" => $line["product_public_id"],
                "product_name" => $line["product_name"],
                "unit_name" => $line["unit_name"],
                "original_quantity" => $line["quantity"],
                "remaining_quantity" => $remaining,
                "unit_selling_price" => $line["unit_selling_price"],
            ];
        }
        return [
            "original_sale_public_id" => $root["public_id"],
            "items" => $out,
        ];
    }
    public function detail(ShopContext $c, string $id): array
    {
        $this->access->read($c, "sale.view");
        $r = $this->e->header($c, $id);
        if ($r["kind"] !== "RETURN" || $r["reversal_of_sale_id"] !== null) {
            throw new ApiException(
                "RETURN_NOT_FOUND",
                "A normal Return was not found.",
                404,
            );
        }
        $root = $this->e->root($c, $r);
        $out = array_intersect_key(
            $r,
            array_flip([
                "public_id",
                "kind",
                "status",
                "subtotal_amount",
                "total_amount",
                "currency_code",
                "occurred_at",
                "posted_at",
                "updated_at",
                "received_amount_snapshot",
                "credit_created_amount_snapshot",
            ]),
        );
        $out["original_sale_public_id"] = $root["public_id"];
        $out["immutable"] = $r["status"] === "POSTED";
        $out["estimate_only"] = !$out["immutable"];
        $profit =
            $out["immutable"] && $this->access->allows($c, "sale.profit.view");
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
                ->select("public_id")
                ->where([
                    "tenant_id" => $c->tenantId,
                    "shop_id" => $c->shopId,
                    "id" => $line["original_sale_item_id"],
                ])
                ->get()
                ->getRowArray();
            $item["original_sale_item_public_id"] =
                $original["public_id"] ?? null;
            if ($out["immutable"]) {
                $m = $this->e->movement($c, (int) $line["id"]);
                $item += [
                    "movement_public_id" => $m["public_id"],
                    "quantity_before" => $m["quantity_before"],
                    "quantity_after" => $m["quantity_after"],
                    "movement_posted_at" => $m["posted_at"],
                ];
                if ($profit) {
                    $source = $this->e->movement(
                        $c,
                        (int) $line["original_sale_item_id"],
                    );
                    $state = (new CommercialCostHistory($this->db))->before(
                        $c->tenantId,
                        $c->shopId,
                        (int) $line["product_id"],
                        (int) $source["id"],
                    );
                    $item += [
                        "unit_cost_snapshot" => $line["unit_cost_snapshot"],
                        "line_cogs_amount" => $line["line_cogs_amount"],
                        "cost_basis_state" => $state,
                    ];
                    $provisional = $provisional || $state === "UNESTABLISHED";
                }
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
                $margin === "0.00"
                    ? $margin
                    : (str_starts_with($margin, "-")
                        ? substr($margin, 1)
                        : "-" . $margin);
            $out["profit_provisional"] = $provisional;
        }
        $scope = [
            "tenant_id" => $c->tenantId,
            "shop_id" => $c->shopId,
            "sale_id" => $r["id"],
        ];
        $out["payments"] = $this->db
            ->table("sale_payments")
            ->select("public_id,direction,channel,amount,occurred_at,posted_at")
            ->where($scope)
            ->orderBy("id")
            ->get()
            ->getResultArray();
        $out["credit_adjustments"] = $this->db
            ->table("receivable_adjustments a")
            ->select(
                "a.public_id,a.adjustment_type,a.amount_delta,a.reason,rc.public_id receivable_public_id",
            )
            ->join(
                "receivables rc",
                "rc.tenant_id=a.tenant_id AND rc.shop_id=a.shop_id AND rc.id=a.receivable_id",
            )
            ->where([
                "a.tenant_id" => $c->tenantId,
                "a.shop_id" => $c->shopId,
                "a.sale_id" => $r["id"],
            ])
            ->orderBy("a.id")
            ->get()
            ->getResultArray();
        $out["reason"] =
            $this->db
                ->table("audit_events")
                ->select("reason")
                ->where([
                    "tenant_id" => $c->tenantId,
                    "shop_id" => $c->shopId,
                    "resource_type" => "sale",
                    "resource_public_id" => $r["public_id"],
                    "action_code" => "sale.return_posted",
                ])
                ->orderBy("id", "DESC")
                ->get()
                ->getRowArray()["reason"] ?? null;
        return $out;
    }
}
