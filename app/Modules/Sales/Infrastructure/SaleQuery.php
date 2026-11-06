<?php
declare(strict_types=1);
namespace App\Modules\Sales\Infrastructure;
use App\Exceptions\ApiException;
use App\Modules\Inventory\Application\CommercialCostHistory;
use App\Modules\Sales\Application\{
    CommercialAccess,
    CommercialEvidence,
    DraftInput,
    SaleMoney,
};
use App\Modules\TenantShop\Infrastructure\ShopContext;
use CodeIgniter\Database\BaseConnection;

final class SaleQuery
{
    private readonly CommercialEvidence $e;
    public function __construct(
        private readonly BaseConnection $database,
        private readonly CommercialAccess $access,
    ) {
        $this->e = new CommercialEvidence($database);
    }
    public function page(ShopContext $c, array $input): array
    {
        $this->access->read($c, "sale.view");
        $page = max(1, min(100000, (int) ($input["page"] ?? 1)));
        $size = max(1, min(50, (int) ($input["per_page"] ?? 20)));
        $kind = $input["kind"] ?? "SALE";
        if (
            !in_array(
                $kind,
                ["ALL", "SALE", "RETURN", "COMPENSATION", "DRAFT_RETURN"],
                true,
            )
        ) {
            throw new ApiException(
                "VALIDATION_FAILED",
                "Choose an operation type.",
                422,
            );
        }
        $q = $this->database
            ->table("sales s")
            ->select(
                "s.public_id,s.kind,s.status,s.total_amount,s.received_amount_snapshot,s.credit_created_amount_snapshot,s.occurred_at,s.posted_at,s.updated_at,c.display_name customer_name,u.username posted_by_name,reverse_sale.public_id reversal_public_id,s.reversal_of_sale_id",
            )
            ->join(
                "customers c",
                "c.tenant_id=s.tenant_id AND c.id=s.customer_id",
                "left",
            )
            ->join("users u", "u.id=s.posted_by_user_id", "left")
            ->join(
                "sales reverse_sale",
                "reverse_sale.tenant_id=s.tenant_id AND reverse_sale.shop_id=s.shop_id AND reverse_sale.reversal_of_sale_id=s.id AND reverse_sale.status='POSTED'",
                "left",
            )
            ->where(["s.tenant_id" => $c->tenantId, "s.shop_id" => $c->shopId]);
        if ($kind === "DRAFT_RETURN") {
            $q->where(["s.status" => "DRAFT", "s.kind" => "RETURN"]);
        } else {
            $q->where("s.status", "POSTED");
        }
        if (in_array($kind, ["SALE", "RETURN"], true)) {
            $q->where(["s.kind" => $kind, "s.reversal_of_sale_id" => null]);
        }
        if ($kind === "COMPENSATION") {
            $q->where("s.reversal_of_sale_id IS NOT NULL", null, false);
        }
        $search = DraftInput::text($input["search"] ?? "", "search", 160);
        if ($search !== null) {
            $q->groupStart()
                ->like("s.public_id", $search)
                ->orLike("c.display_name", $search)
                ->groupEnd();
        }
        $from = DraftInput::dueDate($input["from"] ?? null);
        $to = DraftInput::dueDate($input["to"] ?? null);
        if ($from !== null && $to !== null && $from > $to) {
            throw new ApiException(
                "VALIDATION_FAILED",
                "End date must follow start date.",
                422,
            );
        }
        if ($from) {
            $q->where("s.posted_at >=", $from . " 00:00:00.000000");
        }
        if ($to) {
            $q->where("s.posted_at <=", $to . " 23:59:59.999999");
        }
        $total = $q->countAllResults(false);
        $items = $q
            ->orderBy("s.created_at", "DESC")
            ->orderBy("s.id", "DESC")
            ->get($size, ($page - 1) * $size)
            ->getResultArray();
        foreach ($items as &$item) {
            $item["operation_type"] =
                $item["reversal_of_sale_id"] !== null
                    ? "COMPENSATION"
                    : $item["kind"];
            unset($item["reversal_of_sale_id"]);
        }
        unset($item);
        return [
            "items" => $items,
            "pagination" => [
                "page" => $page,
                "per_page" => $size,
                "total" => $total,
                "total_pages" => (int) ceil($total / $size),
            ],
        ];
    }
    public function state(ShopContext $c, string $id): array
    {
        $this->access->read($c, "sale.view");
        $row = $this->e->header($c, $id);
        if ($row["status"] === "DRAFT" && $row["kind"] === "SALE") {
            return (new SaleDraftQuery($this->database, $this->access))->detail(
                $c,
                $id,
            );
        }
        return $this->detail($c, $row);
    }
    private function detail(ShopContext $c, array $row): array
    {
        $profit = $this->access->allows($c, "sale.profit.view");
        $out = array_intersect_key(
            $row,
            array_flip([
                "public_id",
                "kind",
                "status",
                "total_amount",
                "subtotal_amount",
                "currency_code",
                "occurred_at",
                "posted_at",
                "updated_at",
                "received_amount_snapshot",
                "credit_created_amount_snapshot",
            ]),
        );
        $out["operation_type"] =
            $row["reversal_of_sale_id"] !== null
                ? "COMPENSATION"
                : $row["kind"];
        $out["customer_name"] = null;
        $out["customer_public_id"] = null;
        if ($row["customer_id"] !== null) {
            $customer = $this->database
                ->table("customers")
                ->select("public_id,display_name")
                ->where([
                    "tenant_id" => $c->tenantId,
                    "id" => $row["customer_id"],
                ])
                ->get()
                ->getRowArray();
            $out["customer_name"] = $customer["display_name"] ?? null;
            $out["customer_public_id"] = $customer["public_id"] ?? null;
        }
        $out["posted_by_name"] =
            $row["posted_by_user_id"] === null
                ? null
                : $this->database
                        ->table("users")
                        ->select("username")
                        ->where("id", $row["posted_by_user_id"])
                        ->get()
                        ->getRowArray()["username"] ?? null;
        foreach (
            [
                "original_sale_id" => "original_sale_public_id",
                "reversal_of_sale_id" => "reversal_of_public_id",
                "replacement_for_sale_id" => "replacement_for_public_id",
            ]
            as $column => $field
        ) {
            $out[$field] = null;
            if ($row[$column] !== null) {
                $out[$field] =
                    $this->database
                        ->table("sales")
                        ->select("public_id")
                        ->where([
                            "tenant_id" => $c->tenantId,
                            "shop_id" => $c->shopId,
                            "id" => $row[$column],
                        ])
                        ->get()
                        ->getRowArray()["public_id"] ?? null;
            }
        }
        $reverse = $this->e->reversal($c, (int) $row["id"]);
        $out["reversal_public_id"] = $reverse["public_id"] ?? null;
        $replacement = $this->database
            ->table("sales")
            ->select("public_id,status,kind")
            ->where([
                "tenant_id" => $c->tenantId,
                "shop_id" => $c->shopId,
                "replacement_for_sale_id" => $row["id"],
            ])
            ->get()
            ->getRowArray();
        $out["replacement"] = $replacement;
        $out["returns"] = [];
        if ($row["kind"] === "SALE" && $row["reversal_of_sale_id"] === null) {
            $out["returns"] = $this->database
                ->query(
                    "SELECT r.public_id,r.status,r.total_amount,x.public_id reversal_public_id FROM sales r LEFT JOIN sales x ON x.tenant_id=r.tenant_id AND x.shop_id=r.shop_id AND x.reversal_of_sale_id=r.id AND x.status='POSTED' WHERE r.tenant_id=? AND r.shop_id=? AND r.original_sale_id=? AND r.kind='RETURN' AND r.reversal_of_sale_id IS NULL ORDER BY r.id DESC LIMIT 50",
                    [$c->tenantId, $c->shopId, $row["id"]],
                )
                ->getResultArray();
        }
        $out["items"] = [];
        $provisional = false;
        $history = new CommercialCostHistory($this->database);
        foreach ($this->e->lines($c, (int) $row["id"], $profit) as $line) {
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
            $item["original_sale_item_public_id"] = null;
            if ($line["original_sale_item_id"] !== null) {
                $item["original_sale_item_public_id"] =
                    $this->database
                        ->table("sale_items")
                        ->select("public_id")
                        ->where("id", $line["original_sale_item_id"])
                        ->get()
                        ->getRowArray()["public_id"] ?? null;
            }
            if ($row["status"] === "POSTED") {
                $m = $this->e->movement(
                    $c,
                    (int) ($row["reversal_of_sale_id"] !== null
                        ? $line["original_sale_item_id"]
                        : $line["id"]),
                );
                if ($row["reversal_of_sale_id"] !== null) {
                    $m = $this->database
                        ->table("stock_movements")
                        ->where([
                            "tenant_id" => $c->tenantId,
                            "shop_id" => $c->shopId,
                            "reversal_of_movement_id" => $m["id"],
                        ])
                        ->get()
                        ->getRowArray();
                    if (!$m) {
                        throw new ApiException(
                            "COMMERCIAL_EVIDENCE_INVALID",
                            "Compensation stock evidence is missing.",
                            409,
                        );
                    }
                }
                $item += [
                    "movement_public_id" => $m["public_id"],
                    "quantity_before" => $m["quantity_before"],
                    "quantity_after" => $m["quantity_after"],
                    "movement_posted_at" => $m["posted_at"],
                ];
                if ($profit) {
                    $source = $line;
                    $hops = 0;
                    while ($source["original_sale_item_id"] !== null) {
                        if (++$hops > 4) {
                            throw new ApiException(
                                "COMMERCIAL_EVIDENCE_INVALID",
                                "Commercial line lineage is cyclic or unsupported.",
                                409,
                            );
                        }
                        $source = $this->database
                            ->table("sale_items")
                            ->where([
                                "tenant_id" => $c->tenantId,
                                "shop_id" => $c->shopId,
                                "id" => $source["original_sale_item_id"],
                            ])
                            ->get()
                            ->getRowArray();
                        if (!$source) {
                            throw new ApiException(
                                "COMMERCIAL_EVIDENCE_INVALID",
                                "Original cost lineage is missing.",
                                409,
                            );
                        }
                    }
                    $original = $this->e->movement($c, (int) $source["id"]);
                    $state = $history->before(
                        $c->tenantId,
                        $c->shopId,
                        (int) $line["product_id"],
                        (int) $original["id"],
                    );
                    $item += [
                        "unit_cost_snapshot" => $line["unit_cost_snapshot"],
                        "line_cogs_amount" => $line["line_cogs_amount"],
                        "cost_basis_state" => $state,
                        "gross_profit" => SaleMoney::difference(
                            $line["line_sales_amount"],
                            $line["line_cogs_amount"],
                        ),
                    ];
                    if ($state === "UNESTABLISHED") {
                        $provisional = true;
                    }
                }
            }
            $out["items"][] = $item;
        }
        $out["profit_visible"] = $profit && $row["status"] === "POSTED";
        if ($out["profit_visible"]) {
            $out["total_cogs_amount"] = SaleMoney::sum(
                array_column($out["items"], "line_cogs_amount"),
            );
            $out["gross_profit"] = SaleMoney::difference(
                $row["total_amount"],
                $out["total_cogs_amount"],
            );
            $out["profit_provisional"] = $provisional;
            $out["profit_direction"] =
                $row["kind"] === "SALE" ? "INCREASE" : "DECREASE";
            $g = $out["gross_profit"];
            $out["gross_profit_effect"] =
                $row["kind"] === "RETURN" && $g !== "0.00"
                    ? (str_starts_with($g, "-")
                        ? substr($g, 1)
                        : "-" . $g)
                    : $g;
        }
        $out["payments"] = $this->database
            ->table("sale_payments")
            ->select("public_id,direction,channel,amount,occurred_at,posted_at")
            ->where([
                "tenant_id" => $c->tenantId,
                "shop_id" => $c->shopId,
                "sale_id" => $row["id"],
            ])
            ->orderBy("id")
            ->get()
            ->getResultArray();
        $out["credit_adjustments"] = $this->database
            ->table("receivable_adjustments a")
            ->select(
                "a.public_id,a.adjustment_type,a.amount_delta,a.reason,r.public_id receivable_public_id",
            )
            ->join(
                "receivables r",
                "r.tenant_id=a.tenant_id AND r.shop_id=a.shop_id AND r.id=a.receivable_id",
            )
            ->where([
                "a.tenant_id" => $c->tenantId,
                "a.shop_id" => $c->shopId,
                "a.sale_id" => $row["id"],
            ])
            ->orderBy("a.id")
            ->get()
            ->getResultArray();
        $out["receivable"] = $this->database
            ->table("receivables")
            ->select("public_id,original_amount,due_date,occurred_at,posted_at")
            ->where([
                "tenant_id" => $c->tenantId,
                "shop_id" => $c->shopId,
                "sale_id" => $row["id"],
            ])
            ->get()
            ->getRowArray();
        $out["reason"] =
            $this->database
                ->table("audit_events")
                ->select("reason")
                ->where([
                    "tenant_id" => $c->tenantId,
                    "shop_id" => $c->shopId,
                    "resource_type" => "sale",
                    "resource_public_id" => $row["public_id"],
                ])
                ->whereIn("action_code", [
                    "sale.return_posted",
                    "sale.reversed",
                ])
                ->orderBy("id", "DESC")
                ->get()
                ->getRowArray()["reason"] ?? null;
        $out["estimate_only"] = $row["status"] === "DRAFT";
        $out["immutable"] = $row["status"] === "POSTED";
        if (
            $row["kind"] === "RETURN" &&
            $row["status"] === "DRAFT" &&
            $this->access->allows($c, "sale.return")
        ) {
            $out["original_items"] = $this->eligible(
                $c,
                $this->e->root($c, $row)["public_id"],
            );
        }
        return $out;
    }
    public function eligible(ShopContext $c, string $id): array
    {
        $this->access->read($c, "sale.view");
        $this->access->read($c, "sale.return");
        $root = $this->e->header($c, $id);
        $original = $this->e->root($c, $root);
        if ($root["id"] != $original["id"]) {
            throw new ApiException(
                "ORIGINAL_SALE_REQUIRED",
                "Choose an original Sale.",
                409,
            );
        }
        $out = [];
        foreach ($this->e->lines($c, (int) $root["id"], false) as $line) {
            $returned = $this->e->returned($c, (int) $line["id"]);
            $remaining = (string) $this->database
                ->query(
                    "SELECT CAST(? AS DECIMAL(38,4))-CAST(? AS DECIMAL(38,4)) value",
                    [$line["quantity"], $returned["quantity"]],
                )
                ->getRowArray()["value"];
            $out[] = [
                "public_id" => $line["public_id"],
                "product_name" => $line["product_name"],
                "unit_name" => $line["unit_name"],
                "original_quantity" => $line["quantity"],
                "remaining_quantity" => $remaining,
                "unit_selling_price" => $line["unit_selling_price"],
            ];
        }
        return $out;
    }
    public function receivable(ShopContext $c, string $id): array
    {
        $this->access->read($c, "sale.view");
        $r = $this->database
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
                "r.tenant_id" => $c->tenantId,
                "r.shop_id" => $c->shopId,
                "r.public_id" => DraftInput::uuid($id),
                "s.status" => "POSTED",
                "s.kind" => "SALE",
            ])
            ->get()
            ->getRowArray();
        if (!$r) {
            throw new ApiException(
                "RECEIVABLE_NOT_FOUND",
                "The Sale credit was not found.",
                404,
            );
        }
        return $r;
    }
}
