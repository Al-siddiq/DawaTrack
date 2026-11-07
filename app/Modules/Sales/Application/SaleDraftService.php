<?php

declare(strict_types=1);
namespace App\Modules\Sales\Application;
use App\Exceptions\ApiException;
use App\Modules\Reconciliation\Application\CashAmount;
use App\Modules\Audit\Application\AuditService;
use App\Modules\Audit\Domain\{AuditActor, AuditAppendCommand, AuditCategory};
use App\Modules\TenantShop\Infrastructure\ShopContext;
use App\Support\{UtcTimestamp, SensitiveReason};
use CodeIgniter\Database\BaseConnection;

final class SaleDraftService
{
    private readonly CommercialDecimal $decimal;
    public function __construct(
        private readonly BaseConnection $database,
        private readonly CommercialAccess $access,
        private readonly AuditService $audit,
    ) {
        $this->decimal = new CommercialDecimal($database);
    }
    public function create(ShopContext $context, array $input): string
    {
        DraftInput::only($input, [
            "public_id",
            "customer_public_id",
            "occurred_at",
            "device_local_offset_minutes",
        ]);
        $id = DraftInput::uuid($input["public_id"] ?? null);
        $this->access->read($context, "sale.view");
        return $this->access->mutate(
            $context,
            ["sale.view", "sale.record"],
            function () use ($context, $input, $id) {
                $old = $this->locked($context, $id, false);
                if ($old) {
                    return $id;
                }
                $customer = $this->customer(
                    $context,
                    $input["customer_public_id"] ?? null,
                );
                $now = UtcTimestamp::now();
                $occurred = array_key_exists("occurred_at", $input)
                    ? DraftInput::occurred($input["occurred_at"])
                    : $now;
                $this->database->table("sales")->insert([
                    "public_id" => $id,
                    "tenant_id" => $context->tenantId,
                    "shop_id" => $context->shopId,
                    "kind" => "SALE",
                    "status" => "DRAFT",
                    "customer_id" => $customer["id"] ?? null,
                    "occurred_at" => $occurred,
                    "device_local_offset_minutes" => DraftInput::offset(
                        $input["device_local_offset_minutes"] ?? null,
                    ),
                    "created_by_user_id" => $context->userId,
                    "created_at" => $now,
                    "updated_at" => $now,
                ]);
                return $id;
            },
        );
    }
    public function update(ShopContext $context, string $id, array $input): void
    {
        DraftInput::only($input, [
            "customer_public_id",
            "occurred_at",
            "device_local_offset_minutes",
            "expected_updated_at",
        ]);
        $this->mutation($context, $id, $input, function ($row) use (
            $context,
            $input,
        ) {
            $update = [];
            if (array_key_exists("customer_public_id", $input)) {
                $update["customer_id"] =
                    $this->customer($context, $input["customer_public_id"])[
                        "id"
                    ] ?? null;
            }
            if (array_key_exists("occurred_at", $input)) {
                $update["occurred_at"] = DraftInput::occurred(
                    $input["occurred_at"],
                );
            }
            if (array_key_exists("device_local_offset_minutes", $input)) {
                $update["device_local_offset_minutes"] = DraftInput::offset(
                    $input["device_local_offset_minutes"],
                );
            }
            if ($update) {
                $this->database
                    ->table("sales")
                    ->where("id", $row["id"])
                    ->update($update);
            }
        });
    }
    public function addItem(
        ShopContext $context,
        string $id,
        array $input,
    ): void {
        DraftInput::only($input, [
            "public_id",
            "product_public_id",
            "quantity",
            "unit_selling_price",
            "line_number",
            "expected_updated_at",
        ]);
        $itemId = DraftInput::uuid($input["public_id"] ?? null);
        $this->mutation($context, $id, $input, function ($row) use (
            $context,
            $input,
            $itemId,
        ) {
            $values = $this->itemValues($context, $input, (int) $row["id"]);
            $now = UtcTimestamp::now();
            $this->database->table("sale_items")->insert(
                $values + [
                    "public_id" => $itemId,
                    "tenant_id" => $context->tenantId,
                    "shop_id" => $context->shopId,
                    "sale_id" => $row["id"],
                    "unit_cost_snapshot" => "0.000000",
                    "line_cogs_amount" => "0.00",
                    "created_at" => $now,
                    "updated_at" => $now,
                ],
            );
        });
    }
    public function updateItem(
        ShopContext $context,
        string $id,
        string $itemId,
        array $input,
    ): void {
        DraftInput::only($input, [
            "product_public_id",
            "quantity",
            "unit_selling_price",
            "line_number",
            "expected_updated_at",
        ]);
        $itemId = DraftInput::uuid($itemId);
        $this->mutation($context, $id, $input, function ($row) use (
            $context,
            $itemId,
            $input,
        ) {
            $item = $this->item($context, (int) $row["id"], $itemId);
            if (!array_key_exists("line_number", $input)) {
                $input["line_number"] = (int) $item["line_number"];
            }
            $this->database
                ->table("sale_items")
                ->where("id", $item["id"])
                ->update(
                    $this->itemValues($context, $input, (int) $row["id"]) + [
                        "updated_at" => UtcTimestamp::now(),
                    ],
                );
        });
    }
    public function removeItem(
        ShopContext $context,
        string $id,
        string $itemId,
        array $input,
    ): void {
        DraftInput::only($input, ["expected_updated_at"]);
        $itemId = DraftInput::uuid($itemId);
        $this->mutation($context, $id, $input, function ($row) use (
            $context,
            $itemId,
        ) {
            $item = $this->item($context, (int) $row["id"], $itemId);
            $this->database
                ->table("sale_items")
                ->where("id", $item["id"])
                ->delete();
        });
    }
    public function discard(
        ShopContext $context,
        string $id,
        array $input,
    ): void {
        DraftInput::only($input, ["expected_updated_at", "reason"]);
        $reason = SensitiveReason::require(
            DraftInput::text($input["reason"] ?? null, "reason", 500, true),
        );
        $this->access->read($context, "sale.view");
        $this->access->mutate(
            $context,
            ["sale.view", "sale.record"],
            function () use ($context, $id, $input, $reason) {
                $row = $this->locked($context, $id);
                DraftInput::version(
                    $row,
                    $input["expected_updated_at"] ?? null,
                );
                $items = $this->database
                    ->table("sale_items")
                    ->where("sale_id", $row["id"])
                    ->countAllResults();
                $this->audit->append(
                    new AuditAppendCommand(
                        AuditActor::tenantUser(
                            $context->userId,
                            $context->membershipId,
                            $context->tenantId,
                            $context->shopId,
                        ),
                        "sale.draft_discarded",
                        AuditCategory::SALE,
                        "sale",
                        $row["public_id"],
                        $reason,
                        [
                            "line_count" => $items,
                            "draft_total" => $row["total_amount"],
                        ],
                    ),
                );
                $this->database
                    ->table("sale_items")
                    ->where([
                        "tenant_id" => $context->tenantId,
                        "shop_id" => $context->shopId,
                        "sale_id" => $row["id"],
                    ])
                    ->delete();
                $this->database
                    ->table("sales")
                    ->where("id", $row["id"])
                    ->delete();
            },
        );
    }
    public function preview(
        ShopContext $context,
        string $id,
        array $input,
    ): array {
        DraftInput::only($input, [
            "cash_amount",
            "transfer_amount",
            "due_date",
            "expected_updated_at",
        ]);
        $cash = CashAmount::parse($input["cash_amount"] ?? "0.00");
        $transfer = CashAmount::parse($input["transfer_amount"] ?? "0.00");
        $due = DraftInput::dueDate($input["due_date"] ?? null);
        // Consistent read/locks only: never persists payment, credit or inventory evidence.
        $this->access->read($context, "sale.view");
        $this->access->read($context, "sale.record");
        $this->database->transException(true)->transBegin();
        try {
            $row = $this->locked($context, $id);
            DraftInput::version($row, $input["expected_updated_at"] ?? null);
            $lines = $this->database
                ->table("sale_items i")
                ->select(
                    "i.quantity,i.unit_selling_price,p.current_selling_price,p.status,p.public_id",
                )
                ->join(
                    "products p",
                    "p.tenant_id=i.tenant_id AND p.id=i.product_id",
                )
                ->where([
                    "i.tenant_id" => $context->tenantId,
                    "i.shop_id" => $context->shopId,
                    "i.sale_id" => $row["id"],
                ])
                ->orderBy("i.line_number")
                ->get()
                ->getResultArray();
            if (!$lines) {
                throw new ApiException(
                    "SALE_DRAFT_EMPTY",
                    "Add a Product before reviewing payment preparation.",
                    422,
                );
            }
            foreach ($lines as $line) {
                if ($line["status"] !== "ACTIVE") {
                    throw new ApiException(
                        "PRODUCT_INACTIVE",
                        "Remove or replace inactive Products before continuing.",
                        409,
                    );
                }
                $this->price(
                    $context,
                    $line["unit_selling_price"],
                    $line["current_selling_price"],
                );
            }
            $total = $this->total((int) $row["id"]);
            $amounts = $this->database
                ->query(
                    "SELECT CAST(? AS DECIMAL(38,2))+CAST(? AS DECIMAL(38,2)) paid, CAST(? AS DECIMAL(38,2))-CAST(? AS DECIMAL(38,2))-CAST(? AS DECIMAL(38,2)) credit",
                    [$cash, $transfer, $total, $cash, $transfer],
                )
                ->getRowArray();
            if (str_starts_with((string) $amounts["credit"], "-")) {
                throw new ApiException(
                    "PAYMENT_EXCEEDS_TOTAL",
                    "Prepared payments exceed the draft total. No wallet or advance balance is supported.",
                    422,
                );
            }
            $paid = CashAmount::parse((string) $amounts["paid"]);
            $credit = CashAmount::parse((string) $amounts["credit"]);
            if ($credit !== "0.00") {
                if ($row["customer_id"] === null) {
                    throw new ApiException(
                        "CUSTOMER_REQUIRED",
                        "Select an active Customer for credit.",
                        422,
                        [
                            "customer_public_id" => [
                                "Customer is required for credit.",
                            ],
                        ],
                    );
                }
                $customer = $this->database
                    ->table("customers")
                    ->where([
                        "tenant_id" => $context->tenantId,
                        "id" => $row["customer_id"],
                        "status" => "ACTIVE",
                    ])
                    ->get()
                    ->getRowArray();
                if (!$customer) {
                    throw new ApiException(
                        "CUSTOMER_INACTIVE",
                        "Select an active Customer for credit.",
                        409,
                    );
                }
            }
            if ($due !== null && $credit === "0.00") {
                throw new ApiException(
                    "VALIDATION_FAILED",
                    "A due date applies only to credit.",
                    422,
                    [
                        "due_date" => [
                            "Clear the due date for a fully paid draft.",
                        ],
                    ],
                );
            }
            if (!$this->database->transCommit()) {
                throw new \RuntimeException(
                    "Draft preview could not complete.",
                );
            }
            return [
                "estimate_only" => true,
                "posting_available" => false,
                "draft_public_id" => $row["public_id"],
                "draft_updated_at" => $row["updated_at"],
                "total_amount" => $total,
                "cash_amount" => $cash,
                "transfer_amount" => $transfer,
                "paid_amount" => $paid,
                "credit_amount" => $credit,
                "due_date" => $due,
                "preparation_persisted" => false,
            ];
        } catch (\Throwable $exception) {
            $this->database->transRollback();
            throw $exception;
        }
    }
    private function mutation(
        ShopContext $context,
        string $id,
        array $input,
        callable $operation,
    ): void {
        $this->access->read($context, "sale.view");
        $this->access->mutate(
            $context,
            ["sale.view", "sale.record"],
            function () use ($context, $id, $input, $operation) {
                $row = $this->locked($context, $id);
                DraftInput::version(
                    $row,
                    $input["expected_updated_at"] ?? null,
                );
                $operation($row);
                $total = $this->total((int) $row["id"]);
                $this->database
                    ->table("sales")
                    ->where("id", $row["id"])
                    ->update([
                        "subtotal_amount" => $total,
                        "total_amount" => $total,
                        "updated_at" => DraftInput::nextTime(
                            $row["updated_at"],
                        ),
                    ]);
            },
        );
    }
    private function locked(
        ShopContext $context,
        string $id,
        bool $required = true,
    ): ?array {
        $row = $this->database
            ->query(
                "SELECT * FROM sales WHERE tenant_id=? AND shop_id=? AND public_id=? AND kind='SALE' FOR UPDATE",
                [$context->tenantId, $context->shopId, DraftInput::uuid($id)],
            )
            ->getRowArray();
        if (!$row && $required) {
            throw new ApiException(
                "SALE_DRAFT_NOT_FOUND",
                "The Sale draft was not found.",
                404,
            );
        }
        if ($row && $row["status"] !== "DRAFT") {
            throw new ApiException(
                "SALE_ALREADY_POSTED",
                "Posted Sales cannot be edited or discarded.",
                409,
            );
        }
        return $row;
    }
    private function item(ShopContext $context, int $sale, string $id): array
    {
        $row = $this->database
            ->table("sale_items")
            ->where([
                "tenant_id" => $context->tenantId,
                "shop_id" => $context->shopId,
                "sale_id" => $sale,
                "public_id" => $id,
            ])
            ->get()
            ->getRowArray();
        if (!$row) {
            throw new ApiException(
                "SALE_ITEM_NOT_FOUND",
                "The draft item was not found.",
                404,
            );
        }
        return $row;
    }
    private function itemValues(
        ShopContext $context,
        array $input,
        int $sale,
    ): array {
        $product = $this->database
            ->table("products p")
            ->select("p.id,p.current_selling_price,p.status")
            ->join(
                "stock_positions sp",
                "sp.product_id=p.id AND sp.tenant_id=p.tenant_id",
            )
            ->where([
                "p.tenant_id" => $context->tenantId,
                "sp.shop_id" => $context->shopId,
                "p.public_id" => DraftInput::uuid(
                    $input["product_public_id"] ?? null,
                    "product_public_id",
                ),
            ])
            ->get()
            ->getRowArray();
        if (!$product) {
            throw new ApiException(
                "PRODUCT_NOT_FOUND",
                "The Product is unavailable in this Shop.",
                404,
            );
        }
        if ($product["status"] !== "ACTIVE") {
            throw new ApiException(
                "PRODUCT_INACTIVE",
                "Inactive Products cannot be added to Sale drafts.",
                409,
            );
        }
        $price = $this->price(
            $context,
            $input["unit_selling_price"] ?? $product["current_selling_price"],
            $product["current_selling_price"],
        );
        $quantity = DraftInput::text(
            $input["quantity"] ?? null,
            "quantity",
            20,
            true,
        );
        $quantity = $this->decimal->positiveQuantity($quantity);
        $line =
            $input["line_number"] ??
            (int) ($this->database
                ->table("sale_items")
                ->selectMax("line_number", "last_line")
                ->where("sale_id", $sale)
                ->get()
                ->getRowArray()["last_line"] ?? 0) +
                1;
        if (!is_int($line) || $line < 1 || $line > 65535) {
            throw new ApiException(
                "VALIDATION_FAILED",
                "Use a line number between 1 and 65535.",
                422,
            );
        }
        return [
            "product_id" => $product["id"],
            "quantity" => $quantity,
            "unit_selling_price" => $price,
            "line_sales_amount" => $this->decimal->lineAmount(
                $quantity,
                $price,
            ),
            "line_number" => $line,
        ];
    }
    private function price(
        ShopContext $context,
        mixed $requested,
        string $current,
    ): string {
        $price = CashAmount::parse($requested);
        if (
            $price !== $current &&
            !$this->access->allows($context, "sale.price_override")
        ) {
            throw new ApiException(
                "SALE_PRICE_PERMISSION_REQUIRED",
                "Use the current Product price, or obtain explicit price-override permission.",
                403,
                [
                    "unit_selling_price" => [
                        "Use the current Product price: " . $current,
                    ],
                ],
            );
        }
        return $price;
    }
    private function customer(ShopContext $context, mixed $id): ?array
    {
        if ($id === null || $id === "") {
            return null;
        }
        $row = $this->database
            ->query(
                "SELECT id FROM customers WHERE tenant_id=? AND public_id=? AND status='ACTIVE' FOR UPDATE",
                [
                    $context->tenantId,
                    DraftInput::uuid($id, "customer_public_id"),
                ],
            )
            ->getRowArray();
        if (!$row) {
            throw new ApiException(
                "CUSTOMER_NOT_FOUND",
                "Choose an active Customer in this Tenant.",
                404,
            );
        }
        return $row;
    }
    private function total(int $sale): string
    {
        $total = $this->database
            ->query(
                "SELECT COALESCE(SUM(line_sales_amount),0.00) total FROM sale_items WHERE sale_id=?",
                [$sale],
            )
            ->getRowArray()["total"];
        return CashAmount::parse((string) $total);
    }
}
