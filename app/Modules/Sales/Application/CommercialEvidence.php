<?php
declare(strict_types=1);
namespace App\Modules\Sales\Application;
use App\Exceptions\ApiException;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use App\Modules\Reconciliation\Application\CashAmount;
use CodeIgniter\Database\BaseConnection;

final class CommercialEvidence
{
    public function __construct(public readonly BaseConnection $db) {}
    public function header(
        ShopContext $c,
        string $uuid,
        bool $lock = false,
    ): array {
        $row = $this->db
            ->query(
                "SELECT * FROM sales WHERE tenant_id=? AND shop_id=? AND public_id=?" .
                    ($lock ? " FOR UPDATE" : ""),
                [$c->tenantId, $c->shopId, DraftInput::uuid($uuid)],
            )
            ->getRowArray();
        if (!$row) {
            throw new ApiException(
                "SALE_NOT_FOUND",
                "The commercial record was not found.",
                404,
            );
        }
        return $row;
    }
    public function root(ShopContext $c, array $row, bool $lock = false): array
    {
        $id = $row["original_sale_id"] ?? $row["id"];
        $r = $this->db
            ->query(
                "SELECT * FROM sales WHERE tenant_id=? AND shop_id=? AND id=?" .
                    ($lock ? " FOR UPDATE" : ""),
                [$c->tenantId, $c->shopId, $id],
            )
            ->getRowArray();
        if (
            !$r ||
            $r["kind"] !== "SALE" ||
            $r["status"] !== "POSTED" ||
            $r["reversal_of_sale_id"] !== null ||
            $r["original_sale_id"] !== null
        ) {
            throw new ApiException(
                "ORIGINAL_SALE_REQUIRED",
                "Select an original posted Sale.",
                409,
            );
        }
        return $r;
    }
    public function reversal(ShopContext $c, int $id): ?array
    {
        return $this->db
            ->table("sales")
            ->where([
                "tenant_id" => $c->tenantId,
                "shop_id" => $c->shopId,
                "reversal_of_sale_id" => $id,
                "status" => "POSTED",
            ])
            ->get()
            ->getRowArray();
    }
    public function ordinary(array $row): void
    {
        if ($row["reversal_of_sale_id"] !== null) {
            throw new ApiException(
                "REVERSAL_OF_REVERSAL_NOT_ALLOWED",
                "A compensation cannot itself be reversed. Prepare a justified replacement transaction.",
                409,
            );
        }
    }
    public function lines(ShopContext $c, int $id, bool $cost = true): array
    {
        return $this->db
            ->query(
                "SELECT " .
                    ($cost
                        ? "i.*"
                        : "i.id,i.public_id,i.tenant_id,i.shop_id,i.sale_id,i.product_id,i.original_sale_item_id,i.line_number,i.quantity,i.unit_selling_price,i.line_sales_amount") .
                    ",p.public_id product_public_id,p.name product_name,p.unit_name FROM sale_items i JOIN products p ON p.tenant_id=i.tenant_id AND p.id=i.product_id WHERE i.tenant_id=? AND i.shop_id=? AND i.sale_id=? ORDER BY i.line_number,i.id",
                [$c->tenantId, $c->shopId, $id],
            )
            ->getResultArray();
    }
    public function returned(ShopContext $c, int $item): array
    {
        return $this->db
            ->query(
                "SELECT COALESCE(SUM(i.quantity),0.0000) quantity,COALESCE(SUM(i.line_sales_amount),0.00) amount,COALESCE(SUM(i.line_cogs_amount),0.00) cogs FROM sale_items i JOIN sales r ON r.tenant_id=i.tenant_id AND r.shop_id=i.shop_id AND r.id=i.sale_id WHERE i.tenant_id=? AND i.shop_id=? AND i.original_sale_item_id=? AND r.kind='RETURN' AND r.status='POSTED' AND r.reversal_of_sale_id IS NULL AND NOT EXISTS (SELECT 1 FROM sales x WHERE x.tenant_id=r.tenant_id AND x.shop_id=r.shop_id AND x.reversal_of_sale_id=r.id AND x.status='POSTED')",
                [$c->tenantId, $c->shopId, $item],
            )
            ->getRowArray();
    }
    public function movement(ShopContext $c, int $item): array
    {
        $rows = $this->db
            ->query(
                "SELECT * FROM stock_movements WHERE tenant_id=? AND shop_id=? AND sale_item_id=? AND source_kind='SALE_ITEM' ORDER BY id",
                [$c->tenantId, $c->shopId, $item],
            )
            ->getResultArray();
        if (count($rows) !== 1) {
            throw new ApiException(
                "COMMERCIAL_EVIDENCE_INVALID",
                "The original line must have exactly one stock movement. Investigate its evidence.",
                409,
            );
        }
        return $rows[0];
    }
    public function positions(ShopContext $c, array $lines): array
    {
        $ids = array_values(
            array_unique(
                array_map(static fn($i) => (int) $i["product_id"], $lines),
            ),
        );
        sort($ids, SORT_NUMERIC);
        if (!$ids) {
            throw new ApiException(
                "RETURN_EMPTY",
                "Select at least one original line.",
                422,
            );
        }
        $m = implode(",", array_fill(0, count($ids), "?"));
        $products = $this->db
            ->query(
                "SELECT id FROM products WHERE tenant_id=? AND id IN ($m) ORDER BY id FOR UPDATE",
                [$c->tenantId, ...$ids],
            )
            ->getResultArray();
        $positions = $this->db
            ->query(
                "SELECT * FROM stock_positions WHERE tenant_id=? AND shop_id=? AND product_id IN ($m) ORDER BY product_id FOR UPDATE",
                [$c->tenantId, $c->shopId, ...$ids],
            )
            ->getResultArray();
        if (
            count($products) !== count($ids) ||
            count($positions) !== count($ids)
        ) {
            throw new ApiException(
                "INVENTORY_STATE_CONFLICT",
                "A required scoped Product or position is missing.",
                409,
            );
        }
        return array_column($positions, null, "product_id");
    }
    public function credit(
        ShopContext $c,
        int $sale,
        bool $lock = false,
    ): ?array {
        $r = $this->db
            ->query(
                "SELECT * FROM receivables WHERE tenant_id=? AND shop_id=? AND sale_id=?" .
                    ($lock ? " FOR UPDATE" : ""),
                [$c->tenantId, $c->shopId, $sale],
            )
            ->getRowArray();
        if (!$r) {
            return null;
        }
        if (
            $this->db->tableExists("debt_payment_allocations") &&
            $this->db
                ->table("debt_payment_allocations")
                ->where("receivable_id", $r["id"])
                ->countAllResults() > 0
        ) {
            throw new ApiException(
                "COLLECTION_DEPENDENCY",
                "Collected debt requires the collection module settlement contract before this correction.",
                409,
            );
        }
        $v = (string) $this->db
            ->query(
                "SELECT CAST(? AS DECIMAL(38,2))+COALESCE(SUM(amount_delta),0.00) value FROM receivable_adjustments WHERE tenant_id=? AND shop_id=? AND receivable_id=?",
                [$r["original_amount"], $c->tenantId, $c->shopId, $r["id"]],
            )
            ->getRowArray()["value"];
        if (
            str_starts_with($v, "-") ||
            str_starts_with(
                SaleMoney::difference(
                    $r["original_amount"],
                    CashAmount::parse($v),
                ),
                "-",
            )
        ) {
            throw new ApiException(
                "COMMERCIAL_EVIDENCE_INVALID",
                "The original debt has an inconsistent adjustment balance.",
                409,
            );
        }
        $r["outstanding"] = CashAmount::parse($v);
        return $r;
    }
    public function capacity(ShopContext $c, int $root): array
    {
        $out = ["CASH" => "0.00", "TRANSFER" => "0.00"];
        foreach ($out as $channel => $_) {
            $row = $this->db
                ->query(
                    "SELECT (SELECT COALESCE(SUM(amount),0.00) FROM sale_payments WHERE tenant_id=? AND shop_id=? AND sale_id=? AND direction='RECEIPT' AND channel=?)-(SELECT COALESCE(SUM(p.amount),0.00) FROM sale_payments p JOIN sales r ON r.tenant_id=p.tenant_id AND r.shop_id=p.shop_id AND r.id=p.sale_id WHERE r.tenant_id=? AND r.shop_id=? AND r.original_sale_id=? AND r.kind='RETURN' AND r.status='POSTED' AND r.reversal_of_sale_id IS NULL AND p.direction='REFUND' AND p.channel=? AND NOT EXISTS(SELECT 1 FROM sales x WHERE x.tenant_id=r.tenant_id AND x.shop_id=r.shop_id AND x.reversal_of_sale_id=r.id AND x.status='POSTED')) value",
                    [
                        $c->tenantId,
                        $c->shopId,
                        $root,
                        $channel,
                        $c->tenantId,
                        $c->shopId,
                        $root,
                        $channel,
                    ],
                )
                ->getRowArray();
            if (str_starts_with((string) $row["value"], "-")) {
                throw new ApiException(
                    "COMMERCIAL_EVIDENCE_INVALID",
                    "Effective refunds exceed original receipts.",
                    409,
                );
            }
            $out[$channel] = CashAmount::parse((string) $row["value"]);
        }
        return $out;
    }
    public function allocation(
        array $original,
        array $already,
        string $quantity,
    ): array {
        foreach (
            ["amount" => "line_sales_amount", "cogs" => "line_cogs_amount"]
            as $key => $column
        ) {
            if (
                str_starts_with(
                    SaleMoney::difference($original[$column], $already[$key]),
                    "-",
                )
            ) {
                throw new ApiException(
                    "COMMERCIAL_EVIDENCE_INVALID",
                    "Effective prior Returns exceed original cents.",
                    409,
                );
            }
        }
        $q = (new CommercialDecimal($this->db))->positiveQuantity($quantity);
        $sum = (string) $this->db
            ->query(
                "SELECT CAST(? AS DECIMAL(38,4))+CAST(? AS DECIMAL(38,4)) value",
                [$already["quantity"], $q],
            )
            ->getRowArray()["value"];
        $over = (int) $this->db
            ->query(
                "SELECT CAST(? AS DECIMAL(38,4))>CAST(? AS DECIMAL(38,4)) value",
                [$sum, $original["quantity"]],
            )
            ->getRowArray()["value"];
        if ($over) {
            throw new ApiException(
                "RETURN_QUANTITY_EXCEEDED",
                "Another Return used some of this quantity. Reload the original Sale and review again.",
                409,
            );
        }
        $result = ["quantity" => $q];
        foreach (
            ["amount" => "line_sales_amount", "cogs" => "line_cogs_amount"]
            as $key => $column
        ) {
            $v = (string) $this->db
                ->query(
                    "SELECT GREATEST(0.00,LEAST(CAST(? AS DECIMAL(38,2))-CAST(? AS DECIMAL(38,2)),ROUND(CAST(? AS DECIMAL(38,12))*CAST(? AS DECIMAL(38,12))/CAST(? AS DECIMAL(38,12)),2)-CAST(? AS DECIMAL(38,2)))) value",
                    [
                        $original[$column],
                        $already[$key],
                        $original[$column],
                        $sum,
                        $original["quantity"],
                        $already[$key],
                    ],
                )
                ->getRowArray()["value"];
            $result[$column] = CashAmount::parse($v);
        }
        return $result;
    }
    public function quote(
        ShopContext $c,
        array $root,
        array $lines,
        bool $lockCredit = false,
    ): array {
        if ($this->reversal($c, (int) $root["id"])) {
            throw new ApiException(
                "ORIGINAL_SALE_REVERSED",
                "This original Sale has been reversed.",
                409,
            );
        }
        $originals = array_column(
            $this->lines($c, (int) $root["id"]),
            null,
            "id",
        );
        $allocated = [];
        $seen = [];
        foreach ($lines as $line) {
            $o = $originals[$line["original_sale_item_id"]] ?? null;
            if (!$o || isset($seen[$o["id"]])) {
                throw new ApiException(
                    "RETURN_LINE_INVALID",
                    "Choose each original Sale line at most once.",
                    422,
                );
            }
            $seen[$o["id"]] = true;
            $a = $this->allocation(
                $o,
                $this->returned($c, (int) $o["id"]),
                (string) $line["quantity"],
            );
            $allocated[] = array_replace($line, $a, [
                "product_id" => $o["product_id"],
                "unit_selling_price" => $o["unit_selling_price"],
                "unit_cost_snapshot" => $o["unit_cost_snapshot"],
            ]);
        }
        if (!$allocated) {
            throw new ApiException(
                "RETURN_EMPTY",
                "Select at least one original line.",
                422,
            );
        }
        $total = SaleMoney::sum(array_column($allocated, "line_sales_amount"));
        $credit = $this->credit($c, (int) $root["id"], $lockCredit);
        $owed = $credit["outstanding"] ?? "0.00";
        $reduction = str_starts_with(SaleMoney::difference($total, $owed), "-")
            ? $total
            : $owed;
        return [
            "lines" => $allocated,
            "total_amount" => $total,
            "debt_reduction" => $reduction,
            "refund_amount" => SaleMoney::difference($total, $reduction),
            "credit" => $credit,
            "capacity" => $this->capacity($c, (int) $root["id"]),
        ];
    }
    public function fingerprint(array $value): string
    {
        return hash("sha256", json_encode($value, JSON_THROW_ON_ERROR));
    }
    public function review(array $input, string $actual): void
    {
        if (
            !is_string($input["review_fingerprint"] ?? null) ||
            !hash_equals($actual, $input["review_fingerprint"])
        ) {
            throw new ApiException(
                "COMMERCIAL_REVIEW_STALE",
                "Availability or settlement changed. Review again before confirming.",
                409,
            );
        }
    }
}
