<?php

declare(strict_types=1);
namespace App\Modules\Sales\Application;

use App\Exceptions\ApiException;
use App\Libraries\Http\RequestContext;
use App\Modules\Audit\Application\AuditService;
use App\Modules\Audit\Domain\{AuditActor, AuditAppendCommand, AuditCategory};
use App\Modules\Inventory\Application\SaleStockParticipant;
use App\Modules\Reconciliation\Application\{CashAmount, CashControlGuard};
use App\Modules\Sync\Application\ChangeSequenceService;
use App\Modules\Sync\Domain\ChangeCommand;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use App\Support\{UtcTimestamp, Uuid};
use CodeIgniter\Database\BaseConnection;

final class SalePostingService
{
    public function __construct(
        private readonly BaseConnection $database,
        private readonly CommercialAccess $access,
        private readonly AuditService $audit,
        private readonly ChangeSequenceService $changes,
        private readonly RequestContext $requestContext,
    ) {}
    public function post(ShopContext $context, string $id, array $input): void
    {
        DraftInput::only($input, [
            "expected_updated_at",
            "cash_amount",
            "transfer_amount",
            "due_date",
        ]);
        $id = DraftInput::uuid($id);
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
        $due = DraftInput::dueDate($input["due_date"] ?? null);
        $this->access->mutate(
            $context,
            ["sale.view", "sale.record"],
            function () use ($context, $id, $input, $cash, $transfer, $due) {
                // Tenant/Shop roots are held by CommercialAccess, serializing post with all draft commands.
                // Already-posted retry returns the existing receipt without applying today's Cash guard or inputs again.
                $existing = $this->database
                    ->table("sales")
                    ->select("id,status,replacement_for_sale_id")
                    ->where([
                        "tenant_id" => $context->tenantId,
                        "shop_id" => $context->shopId,
                        "public_id" => $id,
                        "kind" => "SALE",
                        "reversal_of_sale_id" => null,
                    ])
                    ->get()
                    ->getRowArray();
                if (!$existing) {
                    throw new ApiException(
                        "SALE_NOT_FOUND",
                        "The Sale was not found.",
                        404,
                    );
                }
                if ($existing["status"] === "POSTED") {
                    return;
                }
                if ($cash !== "0.00") {
                    (new CashControlGuard(
                        $this->database,
                    ))->assertCanAffectCash($context);
                }
                if (($existing["replacement_for_sale_id"] ?? null) !== null) {
                    $original = $this->database
                        ->query(
                            "SELECT id,kind,status,reversal_of_sale_id FROM sales WHERE tenant_id=? AND shop_id=? AND id=? FOR UPDATE",
                            [
                                $context->tenantId,
                                $context->shopId,
                                $existing["replacement_for_sale_id"],
                            ],
                        )
                        ->getRowArray();
                    if (
                        !$original ||
                        $original["kind"] !== "SALE" ||
                        $original["status"] !== "POSTED" ||
                        $original["reversal_of_sale_id"] !== null ||
                        !$this->database
                            ->table("sales")
                            ->where([
                                "tenant_id" => $context->tenantId,
                                "shop_id" => $context->shopId,
                                "reversal_of_sale_id" => $original["id"],
                                "status" => "POSTED",
                            ])
                            ->countAllResults()
                    ) {
                        throw new ApiException(
                            "REPLACEMENT_REQUIRES_REVERSAL",
                            "The replacement source must remain a reversed original Sale.",
                            409,
                        );
                    }
                }
                $sale = $this->database
                    ->query(
                        "SELECT * FROM sales WHERE tenant_id=? AND shop_id=? AND public_id=? AND kind='SALE' FOR UPDATE",
                        [$context->tenantId, $context->shopId, $id],
                    )
                    ->getRowArray();
                if (!$sale || $sale["status"] !== "DRAFT") {
                    throw new ApiException(
                        "SALE_STATE_CONFLICT",
                        "Reload the Sale before continuing.",
                        409,
                    );
                }
                DraftInput::version(
                    $sale,
                    $input["expected_updated_at"] ?? null,
                );
                if (
                    $sale["original_sale_id"] !== null ||
                    $sale["reversal_of_sale_id"] !== null
                ) {
                    throw new ApiException(
                        "SALE_STATE_CONFLICT",
                        "This command posts ordinary forward Sales only.",
                        409,
                    );
                }
                if ($sale["customer_id"] !== null) {
                    $customer = $this->database
                        ->query(
                            "SELECT id FROM customers WHERE tenant_id=? AND id=? AND status='ACTIVE' FOR UPDATE",
                            [$context->tenantId, $sale["customer_id"]],
                        )
                        ->getRowArray();
                    if (!$customer) {
                        throw new ApiException(
                            "CUSTOMER_INACTIVE",
                            "Select an active Customer before posting.",
                            409,
                        );
                    }
                }
                DraftInput::occurred($sale["occurred_at"]);
                $items = $this->database
                    ->query(
                        "SELECT * FROM sale_items WHERE tenant_id=? AND shop_id=? AND sale_id=? ORDER BY line_number,id FOR UPDATE",
                        [$context->tenantId, $context->shopId, $sale["id"]],
                    )
                    ->getResultArray();
                if (!$items) {
                    throw new ApiException(
                        "SALE_DRAFT_EMPTY",
                        "Add at least one Product before posting.",
                        422,
                    );
                }
                $ids = array_values(
                    array_unique(
                        array_map(
                            static fn($item) => (int) $item["product_id"],
                            $items,
                        ),
                    ),
                );
                sort($ids, SORT_NUMERIC);
                $marks = implode(",", array_fill(0, count($ids), "?"));
                $products = $this->database
                    ->query(
                        "SELECT id,public_id,status,current_selling_price FROM products WHERE tenant_id=? AND id IN ($marks) ORDER BY id FOR UPDATE",
                        [$context->tenantId, ...$ids],
                    )
                    ->getResultArray();
                if (
                    count($products) !== count($ids) ||
                    array_filter(
                        $products,
                        static fn($p) => $p["status"] !== "ACTIVE",
                    )
                ) {
                    throw new ApiException(
                        "PRODUCT_INACTIVE",
                        "Every Product must be active before posting.",
                        409,
                    );
                }
                $positions = $this->database
                    ->query(
                        "SELECT * FROM stock_positions WHERE tenant_id=? AND shop_id=? AND product_id IN ($marks) ORDER BY product_id FOR UPDATE",
                        [$context->tenantId, $context->shopId, ...$ids],
                    )
                    ->getResultArray();
                if (count($positions) !== count($ids)) {
                    throw new ApiException(
                        "INVENTORY_STATE_CONFLICT",
                        "A required Stock Position is unavailable.",
                        409,
                    );
                }
                $productMap = array_column($products, null, "id");
                $positionMap = array_column($positions, null, "product_id");
                $decimal = new CommercialDecimal($this->database);
                $lineAmounts = [];
                foreach ($items as &$item) {
                    $item["quantity"] = $decimal->positiveQuantity(
                        (string) $item["quantity"],
                    );
                    $price = CashAmount::parse($item["unit_selling_price"]);
                    if (
                        $price !==
                            $productMap[$item["product_id"]][
                                "current_selling_price"
                            ] &&
                        !$this->access->allows($context, "sale.price_override")
                    ) {
                        throw new ApiException(
                            "SALE_PRICE_PERMISSION_REQUIRED",
                            "A Product price changed. Save its current price or obtain price-override permission.",
                            403,
                        );
                    }
                    if ($item["original_sale_item_id"] !== null) {
                        throw new ApiException(
                            "SALE_STATE_CONFLICT",
                            "Original-linked lines are not forward Sale lines.",
                            409,
                        );
                    }
                    $item["unit_selling_price"] = $price;
                    $item["line_sales_amount"] = $decimal->lineAmount(
                        $item["quantity"],
                        $price,
                    );
                    $lineAmounts[] = $item["line_sales_amount"];
                }
                unset($item);
                $total = SaleMoney::sum($lineAmounts);
                $composition = SaleMoney::composition($total, $cash, $transfer);
                $credit = $composition["credit_amount"];
                if ($credit !== "0.00" && $sale["customer_id"] === null) {
                    throw new ApiException(
                        "CUSTOMER_REQUIRED",
                        "Select an active Customer for credit.",
                        422,
                    );
                }
                if ($due !== null && $credit === "0.00") {
                    throw new ApiException(
                        "VALIDATION_FAILED",
                        "A due date applies only to credit.",
                        422,
                        [
                            "due_date" => [
                                "Clear the due date for a fully paid Sale.",
                            ],
                        ],
                    );
                }
                $now = UtcTimestamp::now();
                $correlation = $this->requestContext->requestId();
                $stock = new SaleStockParticipant($this->database);
                $changes = [];
                $cogs = [];
                $unknownCostLines = 0;
                // Locks are already acquired. Repeated Product lines execute deterministically in line order.
                foreach ($items as $item) {
                    if (
                        $positionMap[$item["product_id"]][
                            "cost_basis_state"
                        ] === "UNESTABLISHED"
                    ) {
                        $unknownCostLines++;
                    }
                    $effect = $stock->outgoing(
                        $context,
                        $positionMap[$item["product_id"]],
                        $sale,
                        $item,
                        $now,
                        $correlation,
                    );
                    $positionMap[$item["product_id"]] = $effect["position"];
                    $cogs[] = $effect["line_cogs_amount"];
                    $this->database
                        ->table("sale_items")
                        ->where("id", $item["id"])
                        ->update([
                            "quantity" => $item["quantity"],
                            "unit_selling_price" => $item["unit_selling_price"],
                            "line_sales_amount" => $item["line_sales_amount"],
                            "unit_cost_snapshot" =>
                                $effect["unit_cost_snapshot"],
                            "line_cogs_amount" => $effect["line_cogs_amount"],
                            "updated_at" => $now,
                        ]);
                    $changes[] = new ChangeCommand(
                        $context->tenantId,
                        $context->shopId,
                        "stock_movement",
                        $effect["movement_public_id"],
                    );
                }
                // Reject aggregate COGS overflow as well as each individual line overflow.
                $totalCogs = SaleMoney::sum($cogs);
                foreach (
                    ["CASH" => $cash, "TRANSFER" => $transfer]
                    as $channel => $amount
                ) {
                    if ($amount === "0.00") {
                        continue;
                    }
                    $this->database->table("sale_payments")->insert([
                        "public_id" => Uuid::v4(),
                        "tenant_id" => $context->tenantId,
                        "shop_id" => $context->shopId,
                        "sale_id" => $sale["id"],
                        "direction" => "RECEIPT",
                        "channel" => $channel,
                        "amount" => $amount,
                        "occurred_at" => $sale["occurred_at"],
                        "posted_at" => $now,
                        "created_by_user_id" => $context->userId,
                        "correlation_id" => $correlation,
                        "created_at" => $now,
                    ]);
                }
                if ($credit !== "0.00") {
                    $receivable = Uuid::v4();
                    $this->database->table("receivables")->insert([
                        "public_id" => $receivable,
                        "tenant_id" => $context->tenantId,
                        "shop_id" => $context->shopId,
                        "customer_id" => $sale["customer_id"],
                        "sale_id" => $sale["id"],
                        "original_amount" => $credit,
                        "due_date" => $due,
                        "occurred_at" => $sale["occurred_at"],
                        "posted_at" => $now,
                        "created_by_user_id" => $context->userId,
                        "created_at" => $now,
                    ]);
                    $changes[] = new ChangeCommand(
                        $context->tenantId,
                        $context->shopId,
                        "receivable",
                        $receivable,
                    );
                    // Customer has no stored balance; notify consumers that its debt context must be refreshed.
                    $customerPublic = $this->database
                        ->table("customers")
                        ->select("public_id")
                        ->where("id", $sale["customer_id"])
                        ->get()
                        ->getRowArray()["public_id"];
                    $changes[] = new ChangeCommand(
                        $context->tenantId,
                        null,
                        "customer",
                        $customerPublic,
                    );
                }
                $this->database
                    ->table("sales")
                    ->where("id", $sale["id"])
                    ->update([
                        "subtotal_amount" => $total,
                        "total_amount" => $total,
                        "received_amount_snapshot" =>
                            $composition["paid_amount"],
                        "credit_created_amount_snapshot" => $credit,
                        "status" => "POSTED",
                        "posted_at" => $now,
                        "posted_by_user_id" => $context->userId,
                        "correlation_id" => $correlation,
                        "updated_at" => $now,
                    ]);
                $this->audit->append(
                    new AuditAppendCommand(
                        AuditActor::tenantUser(
                            $context->userId,
                            $context->membershipId,
                            $context->tenantId,
                            $context->shopId,
                        ),
                        "sale.posted",
                        AuditCategory::SALE,
                        "sale",
                        $id,
                        "Sale posted.",
                        [
                            "total_amount" => $total,
                            "paid_amount" => $composition["paid_amount"],
                            "credit_amount" => $credit,
                            "line_count" => count($items),
                            "total_cogs" => $totalCogs,
                            "unestablished_cost_lines" => $unknownCostLines,
                        ],
                    ),
                );
                foreach ($products as $product) {
                    $changes[] = new ChangeCommand(
                        $context->tenantId,
                        $context->shopId,
                        "stock_position",
                        $product["public_id"],
                    );
                }
                $changes[] = new ChangeCommand(
                    $context->tenantId,
                    $context->shopId,
                    "sale",
                    $id,
                );
                if ($sale["replacement_for_sale_id"] !== null) {
                    $originalPublic = $this->database
                        ->table("sales")
                        ->select("public_id")
                        ->where([
                            "tenant_id" => $context->tenantId,
                            "shop_id" => $context->shopId,
                            "id" => $sale["replacement_for_sale_id"],
                        ])
                        ->get()
                        ->getRowArray()["public_id"];
                    $changes[] = new ChangeCommand(
                        $context->tenantId,
                        $context->shopId,
                        "sale",
                        $originalPublic,
                    );
                }
                $this->changes->publish($changes); // Last lock boundary; caller commits everything together.
            },
        );
    }
}
