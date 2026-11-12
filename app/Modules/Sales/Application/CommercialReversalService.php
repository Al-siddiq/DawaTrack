<?php
declare(strict_types=1);
namespace App\Modules\Sales\Application;
use App\Exceptions\ApiException;
use App\Libraries\Http\RequestContext;
use App\Modules\Inventory\Application\{
    CommercialStockParticipant,
    CommercialCostHistory,
    CostStateReconstructor,
};
use App\Modules\Reconciliation\Application\CashControlGuard;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use App\Support\{UtcTimestamp, SensitiveReason, Uuid};
use CodeIgniter\Database\BaseConnection;

final class CommercialReversalService
{
    private readonly CommercialEvidence $e;
    public function __construct(
        private readonly BaseConnection $db,
        private readonly CommercialAccess $access,
        private readonly CommercialEvents $events,
        private readonly RequestContext $request,
    ) {
        $this->e = new CommercialEvidence($db);
    }
    private function payments(ShopContext $c, int $id): array
    {
        return $this->db
            ->table("sale_payments")
            ->where([
                "tenant_id" => $c->tenantId,
                "shop_id" => $c->shopId,
                "sale_id" => $id,
            ])
            ->orderBy("id")
            ->get()
            ->getResultArray();
    }
    private function prepare(ShopContext $c, string $id, bool $cashGuard): array
    {
        $pre = $this->e->header($c, $id);
        $this->e->ordinary($pre);
        if ($pre["status"] !== "POSTED") {
            throw new ApiException(
                "SALE_NOT_POSTED",
                "Only posted evidence can be reversed.",
                409,
            );
        }
        $payments = $this->payments($c, (int) $pre["id"]);
        if (
            $cashGuard &&
            array_filter($payments, static fn($p) => $p["channel"] === "CASH")
        ) {
            (new CashControlGuard($this->db))->assertCanAffectCash($c);
        }
        $root = $this->e->root($c, $pre, true);
        $row = $this->e->header($c, $id, true);
        if ($this->e->reversal($c, (int) $row["id"])) {
            throw new ApiException(
                "SALE_ALREADY_REVERSED",
                "This operation already has a compensation.",
                409,
            );
        }
        if ($row["kind"] === "SALE") {
            $active = $this->db
                ->query(
                    "SELECT r.public_id FROM sales r WHERE r.tenant_id=? AND r.shop_id=? AND r.original_sale_id=? AND r.kind='RETURN' AND r.status='POSTED' AND r.reversal_of_sale_id IS NULL AND NOT EXISTS(SELECT 1 FROM sales x WHERE x.tenant_id=r.tenant_id AND x.shop_id=r.shop_id AND x.reversal_of_sale_id=r.id AND x.status='POSTED') LIMIT 1",
                    [$c->tenantId, $c->shopId, $row["id"]],
                )
                ->getRowArray();
            if ($active) {
                throw new ApiException(
                    "SALE_HAS_EFFECTIVE_RETURNS",
                    "Resolve the active Return before reversing this Sale.",
                    409,
                    ["return_public_id" => [$active["public_id"]]],
                );
            }
        } elseif ($this->e->reversal($c, (int) $root["id"])) {
            throw new ApiException(
                "ORIGINAL_SALE_REVERSED",
                "The original Sale is already reversed.",
                409,
            );
        }
        if (
            $row["customer_id"] != $root["customer_id"] ||
            $row["subtotal_amount"] !== $row["total_amount"]
        ) {
            throw new ApiException(
                "COMMERCIAL_EVIDENCE_INVALID",
                "Source header lineage or totals are inconsistent.",
                409,
            );
        }
        $lines = $this->e->lines($c, (int) $row["id"]);
        if (
            SaleMoney::sum(array_column($lines, "line_sales_amount")) !==
            $row["total_amount"]
        ) {
            throw new ApiException(
                "COMMERCIAL_EVIDENCE_INVALID",
                "Source line totals do not match the header.",
                409,
            );
        }
        if ($row["kind"] === "RETURN") {
            $originalLines = array_column(
                $this->e->lines($c, (int) $root["id"]),
                null,
                "id",
            );
            foreach ($lines as $line) {
                $o = $originalLines[$line["original_sale_item_id"]] ?? null;
                if (
                    !$o ||
                    $o["product_id"] != $line["product_id"] ||
                    $o["unit_cost_snapshot"] !== $line["unit_cost_snapshot"] ||
                    $o["unit_selling_price"] !== $line["unit_selling_price"]
                ) {
                    throw new ApiException(
                        "COMMERCIAL_EVIDENCE_INVALID",
                        "Return source attribution is inconsistent.",
                        409,
                    );
                }
            }
        }
        $positions = $this->e->positions($c, $lines);
        $moves = [];
        $groups = [];
        $history = new CommercialCostHistory($this->db);
        $states = [];
        foreach ($lines as $line) {
            $m = $this->e->movement($c, (int) $line["id"]);
            $expectedType =
                $row["kind"] === "SALE" ? "SALE" : "CUSTOMER_RETURN";
            $expectedQuantity =
                $row["kind"] === "SALE"
                    ? "-" . $line["quantity"]
                    : $line["quantity"];
            if (
                $m["movement_type"] !== $expectedType ||
                $m["quantity_delta"] !== $expectedQuantity ||
                $m["product_id"] != $line["product_id"] ||
                $m["unit_cost_basis"] !== $line["unit_cost_snapshot"]
            ) {
                throw new ApiException(
                    "COMMERCIAL_EVIDENCE_INVALID",
                    "Source line and stock evidence do not agree.",
                    409,
                );
            }
            $m["item"] = $line;
            $moves[] = $m;
            $groups[$m["product_id"]][] = $m;
        }
        foreach ($groups as $product => $group) {
            usort(
                $group,
                static fn($a, $b) => (int) $a["id"] <=> (int) $b["id"],
            );
            $chain = $history->chain(
                $c->tenantId,
                $c->shopId,
                (int) $product,
                null,
                true,
            );
            (new CommercialStockParticipant($this->db))->assertLatest(
                $chain,
                $group,
                $positions[$product],
            );
            $states += (new CostStateReconstructor())->beforeStates($chain);
        }
        $credit = $this->e->credit($c, (int) $root["id"], true);
        if (
            $credit &&
            ($credit["customer_id"] != $root["customer_id"] ||
                $credit["original_amount"] !==
                    $root["credit_created_amount_snapshot"])
        ) {
            throw new ApiException(
                "COMMERCIAL_EVIDENCE_INVALID",
                "Original Receivable attribution is inconsistent.",
                409,
            );
        }
        $adjustments = $this->db
            ->table("receivable_adjustments")
            ->where([
                "tenant_id" => $c->tenantId,
                "shop_id" => $c->shopId,
                "sale_id" => $row["id"],
            ])
            ->orderBy("id")
            ->get()
            ->getResultArray();
        if (
            $row["kind"] === "SALE" &&
            ($credit["outstanding"] ?? "0.00") !==
                $row["credit_created_amount_snapshot"]
        ) {
            throw new ApiException(
                "RECEIVABLE_DEPENDENCY",
                "Original credit is not fully available for exact cancellation. Investigate its linked evidence.",
                409,
            );
        }
        if ($row['kind'] === 'SALE' && $credit && ($credit['collected_amount'] !== '0.00' || $credit['adjustment_delta'] !== '0.00')) {
            throw new ApiException('RECEIVABLE_DEPENDENCY', 'Undo the linked customer payments and eligible bill changes before cancelling this sale.', 409);
        }
        if ($row["kind"] === "SALE" && $adjustments) {
            throw new ApiException(
                "RECEIVABLE_DEPENDENCY",
                "Unexpected source Sale adjustments prevent direct compensation.",
                409,
            );
        }
        if ($row["kind"] === "RETURN") {
            foreach ($adjustments as $a) {
                if (
                    $a["adjustment_type"] !== "RETURN_REDUCTION" ||
                    !str_starts_with($a["amount_delta"], "-") ||
                    !$credit ||
                    $a["receivable_id"] != $credit["id"]
                ) {
                    throw new ApiException(
                        "COMMERCIAL_EVIDENCE_INVALID",
                        "Return credit evidence cannot be reversed safely.",
                        409,
                    );
                }
            }
        }
        $cash = SaleMoney::sum(
            array_column(
                array_filter(
                    $payments,
                    static fn($p) => $p["channel"] === "CASH",
                ),
                "amount",
            ),
        );
        $transfer = SaleMoney::sum(
            array_column(
                array_filter(
                    $payments,
                    static fn($p) => $p["channel"] === "TRANSFER",
                ),
                "amount",
            ),
        );
        $debt =
            $row["kind"] === "SALE"
                ? $credit["outstanding"] ?? "0.00"
                : SaleMoney::sum(
                    array_map(
                        static fn($a) => substr($a["amount_delta"], 1),
                        $adjustments,
                    ),
                );
        if (
            SaleMoney::sum([$cash, $transfer, $debt]) !== $row["total_amount"]
        ) {
            throw new ApiException(
                "COMMERCIAL_EVIDENCE_INVALID",
                "Money plus credit evidence does not match the operation being reversed.",
                409,
            );
        }
        foreach ($payments as $payment) {
            if (
                !in_array($payment["channel"], ["CASH", "TRANSFER"], true) ||
                $payment["amount"] === "0.00" ||
                ($payment["reversal_of_payment_id"] ?? null) !== null ||
                $payment["direction"] !==
                    ($row["kind"] === "SALE" ? "RECEIPT" : "REFUND")
            ) {
                throw new ApiException(
                    "COMMERCIAL_EVIDENCE_INVALID",
                    "Unexpected original money direction.",
                    409,
                );
            }
        }
        $fingerprint = $this->e->fingerprint([
            "source" => $row["public_id"],
            "positions" => $positions,
            "payments" => array_map(
                static fn($p) => [
                    $p["id"],
                    $p["amount"],
                    $p["channel"],
                    $p["direction"],
                ],
                $payments,
            ),
            "credit" => $credit["outstanding"] ?? null,
            "adjustments" => array_map(
                static fn($a) => [$a["id"], $a["amount_delta"]],
                $adjustments,
            ),
        ]);
        return compact(
            "root",
            "row",
            "lines",
            "positions",
            "moves",
            "states",
            "credit",
            "adjustments",
            "payments",
            "cash",
            "transfer",
            "debt",
            "fingerprint",
        );
    }
    public function preview(ShopContext $c, string $id, array $input): array
    {
        DraftInput::only($input, []);
        return $this->access->mutate(
            $c,
            ["sale.view", "sale.reverse"],
            function () use ($c, $id) {
                $q = $this->prepare($c, $id, false);
                return [
                    "source_public_id" => $q["row"]["public_id"],
                    "source_kind" => $q["row"]["kind"],
                    "total_amount" => $q["row"]["total_amount"],
                    "cash_amount" => $q["cash"],
                    "transfer_amount" => $q["transfer"],
                    "debt_amount" => $q["debt"],
                    "money_direction" =>
                        $q["row"]["kind"] === "SALE" ? "REFUND" : "RECEIPT",
                    "debt_direction" =>
                        $q["row"]["kind"] === "SALE" ? "CANCEL" : "RESTORE",
                    "line_count" => count($q["lines"]),
                    "review_fingerprint" => $q["fingerprint"],
                ];
            },
        );
    }
    public function reverse(ShopContext $c, string $id, array $input): string
    {
        DraftInput::only($input, ["public_id", "reason", "review_fingerprint"]);
        $public = DraftInput::uuid($input["public_id"] ?? null);
        $reason = SensitiveReason::require(
            DraftInput::text($input["reason"] ?? null, "reason", 500, true),
        );
        return $this->access->mutate(
            $c,
            ["sale.view", "sale.reverse"],
            function () use ($c, $id, $input, $public, $reason) {
                $pre = $this->e->header($c, $id);
                $this->e->ordinary($pre);
                $existing = $this->e->reversal($c, (int) $pre["id"]);
                if ($existing) {
                    if ($existing["public_id"] === $public) {
                        return $public;
                    }
                    throw new ApiException(
                        "SALE_ALREADY_REVERSED",
                        "This operation already has a compensation. Reload its receipt.",
                        409,
                    );
                }
                $q = $this->prepare($c, $id, true);
                $this->e->review($input, $q["fingerprint"]);
                $row = $q["row"];
                $root = $q["root"];
                $now = UtcTimestamp::now();
                $correlation = $this->request->requestId();
                $this->db->table("sales")->insert([
                    "public_id" => $public,
                    "tenant_id" => $c->tenantId,
                    "shop_id" => $c->shopId,
                    "kind" => $row["kind"] === "SALE" ? "RETURN" : "SALE",
                    "status" => "POSTED",
                    "customer_id" => $row["customer_id"],
                    "original_sale_id" => $root["id"],
                    "subtotal_amount" => $row["subtotal_amount"],
                    "total_amount" => $row["total_amount"],
                    "received_amount_snapshot" => null,
                    "credit_created_amount_snapshot" => null,
                    "reversal_of_sale_id" => $row["id"],
                    "occurred_at" => $now,
                    "posted_at" => $now,
                    "posted_by_user_id" => $c->userId,
                    "created_by_user_id" => $c->userId,
                    "correlation_id" => $correlation,
                    "created_at" => $now,
                    "updated_at" => $now,
                ]);
                $internal = (int) $this->db->insertID();
                foreach ($q["lines"] as $line) {
                    $this->db->table("sale_items")->insert([
                        "public_id" => Uuid::v4(),
                        "tenant_id" => $c->tenantId,
                        "shop_id" => $c->shopId,
                        "sale_id" => $internal,
                        "product_id" => $line["product_id"],
                        "original_sale_item_id" => $line["id"],
                        "line_number" => $line["line_number"],
                        "quantity" => $line["quantity"],
                        "unit_selling_price" => $line["unit_selling_price"],
                        "line_sales_amount" => $line["line_sales_amount"],
                        "unit_cost_snapshot" => $line["unit_cost_snapshot"],
                        "line_cogs_amount" => $line["line_cogs_amount"],
                        "created_at" => $now,
                        "updated_at" => $now,
                    ]);
                }
                $moves = $q["moves"];
                usort(
                    $moves,
                    static fn($a, $b) => (int) $b["id"] <=> (int) $a["id"],
                );
                $positions = $q["positions"];
                $stock = new CommercialStockParticipant($this->db);
                $movementPublic = [];
                foreach ($moves as $m) {
                    $effect = $stock->reversal(
                        $c,
                        $positions[$m["product_id"]],
                        $m,
                        $q["states"][(int) $m["id"]],
                        $now,
                        $correlation,
                    );
                    $positions[$m["product_id"]] = $effect["position"];
                    $movementPublic[] = $effect["movement_public_id"];
                }
                foreach ($q["payments"] as $p) {
                    $expected = $row["kind"] === "SALE" ? "RECEIPT" : "REFUND";
                    if ($p["direction"] !== $expected) {
                        throw new ApiException(
                            "COMMERCIAL_EVIDENCE_INVALID",
                            "Unexpected payment direction prevents reversal.",
                            409,
                        );
                    }
                    $this->events->payment(
                        $c,
                        $internal,
                        $p["direction"] === "RECEIPT" ? "REFUND" : "RECEIPT",
                        $p["channel"],
                        $p["amount"],
                        $now,
                        $now,
                        $correlation,
                        (int) $p["id"],
                    );
                }
                if ($row["kind"] === "SALE" && $q["debt"] !== "0.00") {
                    $this->events->adjustment(
                        $c,
                        $q["credit"],
                        $internal,
                        "CORRECTION_DECREASE",
                        "-" . $q["debt"],
                        $reason,
                        $now,
                        $now,
                        $correlation,
                    );
                }
                if ($row["kind"] === "RETURN") {
                    foreach ($q["adjustments"] as $a) {
                        $this->events->adjustment(
                            $c,
                            $q["credit"],
                            $internal,
                            "REVERSAL",
                            substr($a["amount_delta"], 1),
                            $reason,
                            $now,
                            $now,
                            $correlation,
                            (int) $a["id"],
                        );
                    }
                }
                $this->events->audit($c, "sale.reversed", $public, $reason, [
                    "source_public_id" => $row["public_id"],
                    "original_sale_public_id" => $root["public_id"],
                    "source_kind" => $row["kind"],
                    "total_amount" => $row["total_amount"],
                ]);
                $customer =
                    $root["customer_id"] === null
                        ? null
                        : $this->db
                            ->table("customers")
                            ->select("public_id")
                            ->where([
                                "tenant_id" => $c->tenantId,
                                "id" => $root["customer_id"],
                            ])
                            ->get()
                            ->getRowArray()["public_id"];
                $this->events->publish(
                    $c,
                    [$public, $row["public_id"], $root["public_id"]],
                    $movementPublic,
                    array_column($q["lines"], "product_public_id"),
                    $q["credit"],
                    $customer,
                );
                return $public;
            },
        );
    }
    public function replacement(
        ShopContext $c,
        string $id,
        array $input,
    ): string {
        DraftInput::only($input, ["public_id", "reason"]);
        $public = DraftInput::uuid($input["public_id"] ?? null);
        $reason = SensitiveReason::require(
            DraftInput::text($input["reason"] ?? null, "reason", 500, true),
        );
        return $this->access->mutate(
            $c,
            ["sale.view", "sale.reverse"],
            function () use ($c, $id, $public, $reason) {
                $pre = $this->e->header($c, $id);
                $root = $this->e->root($c, $pre, true);
                $row = $this->e->header($c, $id, true);
                $this->e->ordinary($row);
                if (
                    $row["status"] !== "POSTED" ||
                    !$this->e->reversal($c, (int) $row["id"])
                ) {
                    throw new ApiException(
                        "REPLACEMENT_REQUIRES_REVERSAL",
                        "Reverse the original operation before preparing a replacement.",
                        409,
                    );
                }
                $old = $this->db
                    ->table("sales")
                    ->where([
                        "tenant_id" => $c->tenantId,
                        "shop_id" => $c->shopId,
                        "replacement_for_sale_id" => $row["id"],
                    ])
                    ->get()
                    ->getRowArray();
                if ($old) {
                    if ($old["public_id"] === $public) {
                        return $public;
                    }
                    throw new ApiException(
                        "REPLACEMENT_EXISTS",
                        "A direct replacement already exists. Open its linked draft or receipt.",
                        409,
                    );
                }
                if (
                    $row["kind"] === "RETURN" &&
                    $this->e->reversal($c, (int) $root["id"])
                ) {
                    throw new ApiException(
                        "ORIGINAL_SALE_REVERSED",
                        "The root Sale was reversed; a Return replacement is no longer eligible.",
                        409,
                    );
                }
                $replacementLines = $this->e->lines($c, (int) $row["id"]);
                if ($row["kind"] === "RETURN") {
                    $replacementLines = $this->e->quote(
                        $c,
                        $root,
                        $replacementLines,
                        true,
                    )["lines"];
                }
                $now = UtcTimestamp::now();
                $this->db->table("sales")->insert([
                    "public_id" => $public,
                    "tenant_id" => $c->tenantId,
                    "shop_id" => $c->shopId,
                    "kind" => $row["kind"],
                    "status" => "DRAFT",
                    "customer_id" => $row["customer_id"],
                    "original_sale_id" =>
                        $row["kind"] === "RETURN" ? $root["id"] : null,
                    "replacement_for_sale_id" => $row["id"],
                    "occurred_at" => $now,
                    "created_by_user_id" => $c->userId,
                    "created_at" => $now,
                    "updated_at" => $now,
                ]);
                $internal = (int) $this->db->insertID();
                foreach ($replacementLines as $line) {
                    $price =
                        $row["kind"] === "SALE"
                            ? $this->db
                                ->table("products")
                                ->select("current_selling_price")
                                ->where([
                                    "tenant_id" => $c->tenantId,
                                    "id" => $line["product_id"],
                                ])
                                ->get()
                                ->getRowArray()["current_selling_price"]
                            : $line["unit_selling_price"];
                    $amount =
                        $row["kind"] === "RETURN"
                            ? $line["line_sales_amount"]
                            : (new CommercialDecimal($this->db))->lineAmount(
                                $line["quantity"],
                                $price,
                            );
                    $this->db->table("sale_items")->insert([
                        "public_id" => Uuid::v4(),
                        "tenant_id" => $c->tenantId,
                        "shop_id" => $c->shopId,
                        "sale_id" => $internal,
                        "product_id" => $line["product_id"],
                        "original_sale_item_id" =>
                            $row["kind"] === "RETURN"
                                ? $line["original_sale_item_id"]
                                : null,
                        "line_number" => $line["line_number"],
                        "quantity" => $line["quantity"],
                        "unit_selling_price" => $price,
                        "line_sales_amount" => $amount,
                        "unit_cost_snapshot" =>
                            $row["kind"] === "RETURN"
                                ? $line["unit_cost_snapshot"]
                                : "0.000000",
                        "line_cogs_amount" =>
                            $row["kind"] === "RETURN"
                                ? $line["line_cogs_amount"]
                                : "0.00",
                        "created_at" => $now,
                        "updated_at" => $now,
                    ]);
                }
                $total = (string) $this->db
                    ->query(
                        "SELECT COALESCE(SUM(line_sales_amount),0.00) value FROM sale_items WHERE sale_id=?",
                        [$internal],
                    )
                    ->getRowArray()["value"];
                $total = \App\Modules\Reconciliation\Application\CashAmount::parse(
                    $total,
                );
                $this->db
                    ->table("sales")
                    ->where("id", $internal)
                    ->update([
                        "subtotal_amount" => $total,
                        "total_amount" => $total,
                    ]);
                $this->events->audit(
                    $c,
                    "sale.replacement_prepared",
                    $public,
                    $reason,
                    [
                        "source_public_id" => $row["public_id"],
                        "kind" => $row["kind"],
                    ],
                );
                return $public;
            },
        );
    }
}
