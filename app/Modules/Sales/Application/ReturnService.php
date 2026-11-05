<?php
declare(strict_types=1);
namespace App\Modules\Sales\Application;
use App\Exceptions\ApiException;
use App\Libraries\Http\RequestContext;
use App\Modules\Inventory\Application\{
    CommercialStockParticipant,
    CommercialCostHistory,
};
use App\Modules\Reconciliation\Application\{CashAmount, CashControlGuard};
use App\Modules\TenantShop\Infrastructure\ShopContext;
use App\Support\{UtcTimestamp, SensitiveReason};
use CodeIgniter\Database\BaseConnection;

final class ReturnService
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
    public function create(
        ShopContext $c,
        string $original,
        array $input,
    ): string {
        DraftInput::only($input, ["public_id"]);
        $id = DraftInput::uuid($input["public_id"] ?? null);
        return $this->access->mutate(
            $c,
            ["sale.view", "sale.return"],
            function () use ($c, $original, $id) {
                $source = $this->e->header($c, $original, true);
                $root = $this->e->root($c, $source);
                if (
                    $source["id"] != $root["id"] ||
                    $this->e->reversal($c, (int) $root["id"])
                ) {
                    throw new ApiException(
                        "ORIGINAL_SALE_REQUIRED",
                        "Return against an unreversed original Sale.",
                        409,
                    );
                }
                $old = $this->db
                    ->table("sales")
                    ->where([
                        "tenant_id" => $c->tenantId,
                        "shop_id" => $c->shopId,
                        "public_id" => $id,
                    ])
                    ->get()
                    ->getRowArray();
                if ($old) {
                    if (
                        $old["kind"] !== "RETURN" ||
                        $old["original_sale_id"] != $root["id"] ||
                        $old["reversal_of_sale_id"] !== null
                    ) {
                        throw new ApiException(
                            "COMMERCIAL_CONFLICT",
                            "This UUID belongs to a different operation.",
                            409,
                        );
                    }
                    return $id;
                }
                $now = UtcTimestamp::now();
                $this->db->table("sales")->insert([
                    "public_id" => $id,
                    "tenant_id" => $c->tenantId,
                    "shop_id" => $c->shopId,
                    "kind" => "RETURN",
                    "status" => "DRAFT",
                    "original_sale_id" => $root["id"],
                    "customer_id" => $root["customer_id"],
                    "occurred_at" => $now,
                    "created_by_user_id" => $c->userId,
                    "created_at" => $now,
                    "updated_at" => $now,
                ]);
                return $id;
            },
        );
    }
    private function draft(ShopContext $c, string $id): array
    {
        $pre = $this->e->header($c, $id);
        $root = $this->e->root($c, $pre, true);
        $row = $this->e->header($c, $id, true);
        if (
            $row["kind"] !== "RETURN" ||
            $row["reversal_of_sale_id"] !== null ||
            $row["status"] !== "DRAFT"
        ) {
            throw new ApiException(
                "RETURN_STATE_CONFLICT",
                "Only a normal Return draft may be changed.",
                409,
            );
        }
        return [$root, $row];
    }
    public function update(ShopContext $c, string $id, array $input): void
    {
        DraftInput::only($input, [
            "expected_updated_at",
            "items",
            "occurred_at",
            "device_local_offset_minutes",
        ]);
        $this->access->mutate(
            $c,
            ["sale.view", "sale.return"],
            function () use ($c, $id, $input) {
                [$root, $row] = $this->draft($c, $id);
                DraftInput::version(
                    $row,
                    $input["expected_updated_at"] ?? null,
                );
                $items = $input["items"] ?? null;
                if (
                    !is_array($items) ||
                    !array_is_list($items) ||
                    count($items) > 65535
                ) {
                    throw new ApiException(
                        "VALIDATION_FAILED",
                        "Use a bounded list of original lines.",
                        422,
                    );
                }
                $originals = array_column(
                    $this->e->lines($c, (int) $root["id"]),
                    null,
                    "public_id",
                );
                $lines = [];
                $seen = [];
                foreach ($items as $entry) {
                    $entry =
                        $entry instanceof \stdClass ? (array) $entry : $entry;
                    if (!is_array($entry)) {
                        throw new ApiException(
                            "VALIDATION_FAILED",
                            "Use Return line objects.",
                            422,
                        );
                    }
                    DraftInput::only($entry, [
                        "public_id",
                        "original_sale_item_public_id",
                        "quantity",
                    ]);
                    $uuid = DraftInput::uuid(
                        $entry["original_sale_item_public_id"] ?? null,
                    );
                    $o = $originals[$uuid] ?? null;
                    if (!$o || isset($seen[$uuid])) {
                        throw new ApiException(
                            "RETURN_LINE_INVALID",
                            "Choose each original Sale line once.",
                            422,
                        );
                    }
                    $seen[$uuid] = true;
                    $q = DraftInput::text(
                        $entry["quantity"] ?? null,
                        "quantity",
                        20,
                        true,
                    );
                    $a = $this->e->allocation(
                        $o,
                        $this->e->returned($c, (int) $o["id"]),
                        $q,
                    );
                    $lines[] = $a + [
                        "public_id" => DraftInput::uuid(
                            $entry["public_id"] ?? null,
                        ),
                        "original_sale_item_id" => $o["id"],
                        "product_id" => $o["product_id"],
                        "unit_selling_price" => $o["unit_selling_price"],
                        "unit_cost_snapshot" => $o["unit_cost_snapshot"],
                    ];
                }
                if ($this->e->reversal($c, (int) $root["id"])) {
                    throw new ApiException(
                        "ORIGINAL_SALE_REVERSED",
                        "The original Sale was reversed.",
                        409,
                    );
                }
                $this->db
                    ->table("sale_items")
                    ->where("sale_id", $row["id"])
                    ->delete();
                $now = DraftInput::nextTime($row["updated_at"]);
                foreach ($lines as $i => $line) {
                    $this->db->table("sale_items")->insert(
                        $line + [
                            "tenant_id" => $c->tenantId,
                            "shop_id" => $c->shopId,
                            "sale_id" => $row["id"],
                            "line_number" => $i + 1,
                            "created_at" => $now,
                            "updated_at" => $now,
                        ],
                    );
                }
                $total = SaleMoney::sum(
                    array_column($lines, "line_sales_amount"),
                );
                $values = [
                    "subtotal_amount" => $total,
                    "total_amount" => $total,
                    "updated_at" => $now,
                ];
                if (array_key_exists("occurred_at", $input)) {
                    $values["occurred_at"] = DraftInput::occurred(
                        $input["occurred_at"],
                    );
                }
                if (array_key_exists("device_local_offset_minutes", $input)) {
                    $values["device_local_offset_minutes"] = DraftInput::offset(
                        $input["device_local_offset_minutes"],
                    );
                }
                $this->db
                    ->table("sales")
                    ->where("id", $row["id"])
                    ->update($values);
            },
        );
    }
    private function settlement(
        ShopContext $c,
        array $root,
        array $row,
        array $input,
        bool $lockCredit = false,
    ): array {
        $quote = $this->e->quote(
            $c,
            $root,
            $this->e->lines($c, (int) $row["id"]),
            $lockCredit,
        );
        $cash = CashAmount::parse(
            array_key_exists("cash_amount", $input)
                ? $input["cash_amount"]
                : "0.00",
        );
        $transfer = CashAmount::parse(
            array_key_exists("transfer_amount", $input)
                ? $input["transfer_amount"]
                : "0.00",
        );
        if (SaleMoney::sum([$cash, $transfer]) !== $quote["refund_amount"]) {
            throw new ApiException(
                "RETURN_REFUND_MISMATCH",
                "Cash plus Transfer refund must equal the remaining refund after debt reduction.",
                422,
                [
                    "refund_amount" => [$quote["refund_amount"]],
                    "debt_reduction" => [$quote["debt_reduction"]],
                ],
            );
        }
        foreach (
            ["CASH" => $cash, "TRANSFER" => $transfer]
            as $channel => $amount
        ) {
            if (
                str_starts_with(
                    SaleMoney::difference(
                        $quote["capacity"][$channel],
                        $amount,
                    ),
                    "-",
                )
            ) {
                throw new ApiException(
                    "RETURN_REFUND_CAP_EXCEEDED",
                    "The chosen refund exceeds remaining original receipts for " .
                        $channel .
                        ".",
                    409,
                );
            }
        }
        $quote["cash_amount"] = $cash;
        $quote["transfer_amount"] = $transfer;
        $quote["review_fingerprint"] = $this->e->fingerprint([
            "version" => $row["updated_at"],
            "lines" => array_map(
                static fn($l) => [
                    $l["public_id"],
                    $l["quantity"],
                    $l["line_sales_amount"],
                    $l["line_cogs_amount"],
                ],
                $quote["lines"],
            ),
            "credit" => $quote["credit"]["outstanding"] ?? "0.00",
            "reduction" => $quote["debt_reduction"],
            "capacity" => $quote["capacity"],
            "cash" => $cash,
            "transfer" => $transfer,
        ]);
        return $quote;
    }
    public function estimate(ShopContext $c, string $id): array
    {
        $this->access->read($c, "sale.view");
        $this->access->read($c, "sale.return");
        $row = $this->e->header($c, $id);
        if (
            $row["kind"] !== "RETURN" ||
            $row["status"] !== "DRAFT" ||
            $row["reversal_of_sale_id"] !== null
        ) {
            throw new ApiException(
                "RETURN_STATE_CONFLICT",
                "Estimate a normal Return draft only.",
                409,
            );
        }
        $root = $this->e->root($c, $row);
        $q = $this->e->quote($c, $root, $this->e->lines($c, (int) $row["id"]));
        return $this->safeQuote($q);
    }
    private function safeQuote(array $q): array
    {
        return [
            "total_amount" => $q["total_amount"],
            "debt_reduction" => $q["debt_reduction"],
            "refund_amount" => $q["refund_amount"],
            "cash_capacity" => $q["capacity"]["CASH"],
            "transfer_capacity" => $q["capacity"]["TRANSFER"],
        ] +
            array_intersect_key(
                $q,
                array_flip([
                    "cash_amount",
                    "transfer_amount",
                    "review_fingerprint",
                ]),
            );
    }
    public function preview(ShopContext $c, string $id, array $input): array
    {
        DraftInput::only($input, [
            "expected_updated_at",
            "cash_amount",
            "transfer_amount",
        ]);
        return $this->access->mutate(
            $c,
            ["sale.view", "sale.return"],
            function () use ($c, $id, $input) {
                [$root, $row] = $this->draft($c, $id);
                DraftInput::version(
                    $row,
                    $input["expected_updated_at"] ?? null,
                );
                return $this->safeQuote(
                    $this->settlement($c, $root, $row, $input),
                );
            },
        );
    }
    public function post(ShopContext $c, string $id, array $input): void
    {
        DraftInput::only($input, [
            "expected_updated_at",
            "cash_amount",
            "transfer_amount",
            "reason",
            "review_fingerprint",
        ]);
        $reason = SensitiveReason::require(
            DraftInput::text($input["reason"] ?? null, "reason", 500, true),
        );
        $this->access->mutate(
            $c,
            ["sale.view", "sale.return"],
            function () use ($c, $id, $input, $reason) {
                $pre = $this->e->header($c, $id);
                if (
                    $pre["kind"] !== "RETURN" ||
                    $pre["reversal_of_sale_id"] !== null
                ) {
                    throw new ApiException(
                        "RETURN_STATE_CONFLICT",
                        "Select a normal Return.",
                        409,
                    );
                }
                if ($pre["status"] === "POSTED") {
                    return;
                }
                $cash = CashAmount::parse(
                    array_key_exists("cash_amount", $input)
                        ? $input["cash_amount"]
                        : "0.00",
                );
                if ($cash !== "0.00") {
                    (new CashControlGuard($this->db))->assertCanAffectCash($c);
                }
                [$root, $row] = $this->draft($c, $id);
                DraftInput::version(
                    $row,
                    $input["expected_updated_at"] ?? null,
                );
                $lines = $this->e->lines($c, (int) $row["id"]);
                $positions = $this->e->positions($c, $lines);
                $q = $this->settlement($c, $root, $row, $input, true);
                $this->e->review($input, $q["review_fingerprint"]);
                $now = UtcTimestamp::now();
                $correlation = $this->request->requestId();
                $stock = new CommercialStockParticipant($this->db);
                $history = new CommercialCostHistory($this->db);
                $movements = [];
                $products = [];
                foreach ($q["lines"] as $line) {
                    $original = $this->e->movement(
                        $c,
                        (int) $line["original_sale_item_id"],
                    );
                    if (
                        $original["movement_type"] !== "SALE" ||
                        $original["product_id"] != $line["product_id"] ||
                        $original["unit_cost_basis"] !==
                            $line["unit_cost_snapshot"]
                    ) {
                        throw new ApiException(
                            "COMMERCIAL_EVIDENCE_INVALID",
                            "Original Sale stock attribution is inconsistent.",
                            409,
                        );
                    }
                    $state = $history->before(
                        $c->tenantId,
                        $c->shopId,
                        (int) $line["product_id"],
                        (int) $original["id"],
                    );
                    $effect = $stock->incoming(
                        $c,
                        $positions[$line["product_id"]],
                        $row,
                        $line,
                        $now,
                        $correlation,
                        $state,
                    );
                    $positions[$line["product_id"]] = $effect["position"];
                    $movements[] = $effect["movement_public_id"];
                    $products[] = $line["product_public_id"];
                    $this->db
                        ->table("sale_items")
                        ->where("id", $line["id"])
                        ->update([
                            "line_sales_amount" => $line["line_sales_amount"],
                            "line_cogs_amount" => $line["line_cogs_amount"],
                            "unit_selling_price" => $line["unit_selling_price"],
                            "unit_cost_snapshot" => $line["unit_cost_snapshot"],
                            "updated_at" => $now,
                        ]);
                }
                foreach (
                    [
                        "CASH" => $q["cash_amount"],
                        "TRANSFER" => $q["transfer_amount"],
                    ]
                    as $channel => $amount
                ) {
                    $this->events->payment(
                        $c,
                        (int) $row["id"],
                        "REFUND",
                        $channel,
                        $amount,
                        $row["occurred_at"],
                        $now,
                        $correlation,
                    );
                }
                if ($q["debt_reduction"] !== "0.00") {
                    $this->events->adjustment(
                        $c,
                        $q["credit"],
                        (int) $row["id"],
                        "RETURN_REDUCTION",
                        "-" . $q["debt_reduction"],
                        $reason,
                        $row["occurred_at"],
                        $now,
                        $correlation,
                    );
                }
                $this->db
                    ->table("sales")
                    ->where("id", $row["id"])
                    ->update([
                        "subtotal_amount" => $q["total_amount"],
                        "total_amount" => $q["total_amount"],
                        "status" => "POSTED",
                        "posted_at" => $now,
                        "posted_by_user_id" => $c->userId,
                        "correlation_id" => $correlation,
                        "updated_at" => $now,
                    ]);
                $this->events->audit(
                    $c,
                    "sale.return_posted",
                    $row["public_id"],
                    $reason,
                    [
                        "original_sale_public_id" => $root["public_id"],
                        "total_amount" => $q["total_amount"],
                        "refund_amount" => $q["refund_amount"],
                        "debt_reduction" => $q["debt_reduction"],
                    ],
                );
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
                    [$root["public_id"], $row["public_id"]],
                    $movements,
                    $products,
                    $q["credit"],
                    $customer,
                );
            },
        );
    }
    public function discard(ShopContext $c, string $id, array $input): void
    {
        DraftInput::only($input, ["expected_updated_at", "reason"]);
        $reason = SensitiveReason::require(
            DraftInput::text($input["reason"] ?? null, "reason", 500, true),
        );
        $this->access->mutate(
            $c,
            ["sale.view", "sale.return"],
            function () use ($c, $id, $input, $reason) {
                [$root, $row] = $this->draft($c, $id);
                DraftInput::version(
                    $row,
                    $input["expected_updated_at"] ?? null,
                );
                $this->db
                    ->table("sale_items")
                    ->where("sale_id", $row["id"])
                    ->delete();
                $this->db->table("sales")->where("id", $row["id"])->delete();
                $this->events->audit(
                    $c,
                    "sale.draft_discarded",
                    $row["public_id"],
                    $reason,
                    ["kind" => "RETURN"],
                );
            },
        );
    }
}
