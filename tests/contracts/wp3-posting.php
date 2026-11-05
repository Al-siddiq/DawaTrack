<?php
// Posting orchestration against in-memory database/authority/evidence doubles.
// Exercises the real posting, CommercialAccess, Cash guard and Inventory participant; NOT MySQL/HTTP/concurrency acceptance.
declare(strict_types=1);
namespace CodeIgniter\Database {
    class RawSql
    {
        public function __construct(public string $value) {}
    }
    class BaseConnection
    {
        public int $transDepth = 0,
            $lastId = 100,
            $commits = 0,
            $rollbacks = 0;
        public array $state = [],
            $saved = [],
            $queries = [];
        public ?string $fail = null;
        public bool $baseline = true,
            $activeCash = false,
            $override = false;
        public string $mode = "FULL";
        public function transException(bool $value): self
        {
            return $this;
        }
        public function transBegin(): bool
        {
            if ($this->fail === "begin") {
                return false;
            }
            $this->saved = unserialize(serialize($this->state));
            $this->transDepth = 1;
            return true;
        }
        public function transCommit(): bool
        {
            if ($this->fail === "commit") {
                return false;
            }
            $this->commits++;
            $this->transDepth = 0;
            return true;
        }
        public function transRollback(): bool
        {
            $this->state = $this->saved;
            $this->rollbacks++;
            $this->transDepth = 0;
            return true;
        }
        public function insertID(): int
        {
            return $this->lastId;
        }
        public function table(string $table): \FixtureBuilder
        {
            return new \FixtureBuilder($this, $table);
        }
        public function query(string $sql, array $args = []): \FixtureResult
        {
            $this->queries[] = $sql;
            if (
                str_contains($sql, "FROM tenants") ||
                str_contains($sql, "FROM shops") ||
                str_contains($sql, "FROM tenant_memberships")
            ) {
                return new \FixtureResult([["id" => 1]]);
            }
            if (str_contains($sql, "FROM reconciliation_sessions")) {
                return new \FixtureResult(
                    str_contains($sql, "status='FINALIZED'")
                        ? ($this->baseline
                            ? [["id" => 1]]
                            : [])
                        : ($this->activeCash
                            ? [["id" => 2]]
                            : []),
                );
            }
            if (str_contains($sql, "FROM customers")) {
                return new \FixtureResult(
                    array_values(
                        array_filter(
                            $this->state["customers"],
                            fn($r) => $r["status"] === "ACTIVE" &&
                                $r["id"] == $args[1],
                        ),
                    ),
                );
            }
            if (str_contains($sql, "FROM sales WHERE")) {
                return new \FixtureResult(
                    array_values(
                        array_filter(
                            $this->state["sales"],
                            fn($r) => $r["tenant_id"] == $args[0] &&
                                $r["shop_id"] == $args[1] &&
                                $r["public_id"] == $args[2],
                        ),
                    ),
                );
            }
            if (str_contains($sql, "FROM sale_items WHERE")) {
                return new \FixtureResult($this->state["sale_items"]);
            }
            if (str_contains($sql, "FROM products WHERE")) {
                return new \FixtureResult($this->state["products"]);
            }
            if (str_contains($sql, "FROM stock_positions WHERE")) {
                return new \FixtureResult($this->state["stock_positions"]);
            }
            if (str_contains($sql, "DECIMAL(38,4))-")) {
                return new \FixtureResult([
                    [
                        "quantity" => \fixtureFormat(
                            \fixtureScaled($args[0], 4) -
                                \fixtureScaled($args[1], 4),
                            4,
                        ),
                    ],
                ]);
            }
            if (str_contains($sql, "ROUND(CAST")) {
                return new \FixtureResult([
                    [
                        "amount" => \fixtureFormat(
                            intdiv(
                                \fixtureScaled($args[0], 4) *
                                    \fixtureScaled($args[1], 6) +
                                    50000000,
                                100000000,
                            ),
                            2,
                        ),
                    ],
                ]);
            }
            throw new \RuntimeException("Unmodeled fixture SQL: " . $sql);
        }
    }
}
namespace App\Modules\Identity\Application {
    class PermissionService
    {
        public function __construct(private object $db, object $audit) {}
        public function allows(int $tenant, int $member, string $code): bool
        {
            if (in_array($code, $this->db->deniedPermissions ?? [], true)) {
                return false;
            }
            return $code === "sale.price_override" ? $this->db->override : true;
        }
    }
}
namespace App\Modules\Access\Application {
    class AccessGate
    {
        public function __construct(private object $db) {}
        public function evaluate(int $tenant, int $shop): object
        {
            return new class ($this->db->mode) {
                public string $message = "Fixture access";
                public function __construct(public string $mode) {}
                public function permitsMutation(): bool
                {
                    return $this->mode === "FULL";
                }
            };
        }
    }
}
namespace App\Modules\Audit\Application {
    class AuditService
    {
        public function __construct(private object $db) {}
        public function append(object $command): void
        {
            if ($this->db->fail === "audit") {
                throw new \RuntimeException("Injected Audit failure");
            }
            $this->db->state["audit"][] = $command;
        }
    }
}
namespace App\Modules\Sync\Application {
    class ChangeSequenceService
    {
        public function __construct(private object $db) {}
        public function publish(array $commands): array
        {
            if ($this->db->fail === "changes") {
                throw new \RuntimeException("Injected change failure");
            }
            if ($this->db->transDepth !== 1) {
                throw new \RuntimeException(
                    "Publication outside owning transaction",
                );
            }
            $this->db->state["changes"] = [
                ...$this->db->state["changes"],
                ...$commands,
            ];
            return [];
        }
    }
}
namespace {
    function fixtureScaled(string $value, int $scale): int
    {
        $negative = str_starts_with($value, "-");
        [$w, $f] = array_pad(explode(".", ltrim($value, "-"), 2), 2, "");
        return ($negative ? -1 : 1) * (int) ($w . str_pad($f, $scale, "0"));
    }
    function fixtureFormat(int $value, int $scale): string
    {
        $negative = $value < 0;
        $text = str_pad((string) abs($value), $scale + 1, "0", STR_PAD_LEFT);
        return ($negative ? "-" : "") .
            substr($text, 0, -$scale) .
            "." .
            substr($text, -$scale);
    }
    class FixtureResult
    {
        public function __construct(private array $rows) {}
        public function getRowArray(): ?array
        {
            return $this->rows[0] ?? null;
        }
        public function getResultArray(): array
        {
            return $this->rows;
        }
    }
    class FixtureBuilder
    {
        private array $where = [];
        public function __construct(
            private object $db,
            private string $table,
        ) {}
        public function select(string $fields): self
        {
            return $this;
        }
        public function where(string|array $key, mixed $value = null): self
        {
            $this->where += is_array($key) ? $key : [$key => $value];
            return $this;
        }
        private function matches(array $row): bool
        {
            foreach ($this->where as $k => $v) {
                if (($row[$k] ?? null) != $v) {
                    return false;
                }
            }
            return true;
        }
        public function get(): FixtureResult
        {
            return new FixtureResult(
                array_values(
                    array_filter(
                        $this->db->state[$this->table],
                        fn($row) => $this->matches($row),
                    ),
                ),
            );
        }
        public function insert(array $values): bool
        {
            if ($this->db->fail === $this->table) {
                throw new RuntimeException(
                    "Injected " . $this->table . " failure",
                );
            }
            $values["id"] = ++$this->db->lastId;
            $this->db->state[$this->table][] = $values;
            return true;
        }
        public function update(array $values): bool
        {
            if ($this->db->fail === $this->table) {
                throw new RuntimeException(
                    "Injected " . $this->table . " failure",
                );
            }
            foreach ($this->db->state[$this->table] as &$row) {
                if ($this->matches($row)) {
                    foreach ($values as $key => $value) {
                        $row[$key] =
                            $value instanceof \CodeIgniter\Database\RawSql
                                ? $row[$key] + 1
                                : $value;
                    }
                }
            }
            return true;
        }
    }
    $root = __DIR__ . "/../../";
    foreach (
        [
            "app/Exceptions/ApiException.php",
            "app/Support/Uuid.php",
            "app/Support/UtcTimestamp.php",
            "app/Libraries/Http/RequestContext.php",
            "app/Modules/TenantShop/Infrastructure/ShopContext.php",
            "app/Modules/Audit/Domain/AuditAuthority.php",
            "app/Modules/Audit/Domain/AuditActor.php",
            "app/Modules/Audit/Domain/AuditCategory.php",
            "app/Modules/Audit/Domain/AuditAppendCommand.php",
            "app/Modules/Sync/Domain/ChangeKind.php",
            "app/Modules/Sync/Domain/ChangeCommand.php",
            "app/Modules/Reconciliation/Application/CashAmount.php",
            "app/Modules/Reconciliation/Application/CashControlGuard.php",
            "app/Modules/Sales/Application/DraftInput.php",
            "app/Modules/Sales/Application/CommercialAccess.php",
            "app/Modules/Sales/Application/CommercialDecimal.php",
            "app/Modules/Sales/Application/SaleMoney.php",
            "app/Modules/Inventory/Application/InventoryProjection.php",
            "app/Modules/Inventory/Application/SaleStockParticipant.php",
            "app/Modules/Sales/Application/SalePostingService.php",
        ]
        as $file
    ) {
        require $root . $file;
    }
    $uuid = "01234567-89ab-4cde-8fab-0123456789ab";
    $version = "2026-11-03 12:00:00.000001";
    $context = new \App\Modules\TenantShop\Infrastructure\ShopContext(
        1,
        1,
        1,
        1,
        "PRIMARY_OWNER",
    );
    function fixture(): array
    {
        global $uuid, $version;
        $db = new \CodeIgniter\Database\BaseConnection();
        $db->state = [
            "sales" => [
                [
                    "id" => 1,
                    "public_id" => $uuid,
                    "tenant_id" => 1,
                    "shop_id" => 1,
                    "kind" => "SALE",
                    "status" => "DRAFT",
                    "customer_id" => 1,
                    "original_sale_id" => null,
                    "reversal_of_sale_id" => null,
                    "replacement_for_sale_id" => null,
                    "occurred_at" => "2026-11-03 12:00:00.000000",
                    "updated_at" => $version,
                ],
            ],
            "customers" => [
                [
                    "id" => 1,
                    "public_id" => $uuid,
                    "tenant_id" => 1,
                    "status" => "ACTIVE",
                ],
            ],
            "products" => [
                [
                    "id" => 1,
                    "public_id" => $uuid,
                    "status" => "ACTIVE",
                    "current_selling_price" => "100.00",
                ],
            ],
            "stock_positions" => [
                [
                    "id" => 1,
                    "tenant_id" => 1,
                    "shop_id" => 1,
                    "product_id" => 1,
                    "expected_quantity" => "5.0000",
                    "average_unit_cost" => "60.000000",
                    "cost_basis_state" => "VALID",
                    "last_movement_id" => null,
                    "version" => 0,
                ],
            ],
            "sale_items" => [],
            "stock_movements" => [],
            "sale_payments" => [],
            "receivables" => [],
            "audit" => [],
            "changes" => [],
        ];
        foreach (["2.0000", "4.0000"] as $i => $qty) {
            $db->state["sale_items"][] = [
                "id" => $i + 1,
                "tenant_id" => 1,
                "shop_id" => 1,
                "sale_id" => 1,
                "product_id" => 1,
                "quantity" => $qty,
                "unit_selling_price" => "100.00",
                "line_sales_amount" => "0.00",
                "unit_cost_snapshot" => "0.000000",
                "line_cogs_amount" => "0.00",
                "original_sale_item_id" => null,
                "line_number" => $i + 1,
            ];
        }
        $audit = new \App\Modules\Audit\Application\AuditService($db);
        $access = new \App\Modules\Sales\Application\CommercialAccess(
            $db,
            $audit,
        );
        $changes = new \App\Modules\Sync\Application\ChangeSequenceService($db);
        return [
            $db,
            new \App\Modules\Sales\Application\SalePostingService(
                $db,
                $access,
                $audit,
                $changes,
                new \App\Libraries\Http\RequestContext(),
            ),
        ];
    }
    $checks = 0;
    $same = static function ($a, $b) use (&$checks) {
        if ($a !== $b) {
            throw new RuntimeException(
                "Posting fixture mismatch: " . var_export([$a, $b], true),
            );
        }
        $checks++;
    };
    $input = [
        "expected_updated_at" => $version,
        "cash_amount" => "100.00",
        "transfer_amount" => "200.00",
        "due_date" => "2026-12-01",
    ];
    [$db, $service] = fixture();
    $service->post($context, $uuid, $input);
    $same($db->state["sales"][0]["status"], "POSTED");
    $same($db->state["sales"][0]["total_amount"], "600.00");
    $same($db->state["sales"][0]["received_amount_snapshot"], "300.00");
    $same($db->state["sales"][0]["credit_created_amount_snapshot"], "300.00");
    $same(array_column($db->state["sale_items"], "line_cogs_amount"), [
        "120.00",
        "240.00",
    ]);
    $same(array_column($db->state["stock_movements"], "quantity_before"), [
        "5.0000",
        "3.0000",
    ]);
    $same(array_column($db->state["stock_movements"], "quantity_after"), [
        "3.0000",
        "-1.0000",
    ]);
    $same($db->state["stock_positions"][0]["cost_basis_state"], "RETAINED");
    $same($db->state["stock_positions"][0]["average_unit_cost"], "60.000000");
    $same($db->state["stock_positions"][0]["version"], 2);
    $same(count($db->state["sale_payments"]), 2);
    $same($db->state["receivables"][0]["original_amount"], "300.00");
    $same($db->state["receivables"][0]["due_date"], "2026-12-01");
    $same(count($db->state["audit"]), 1);
    $same(count($db->state["changes"]), 6);
    $same($db->transDepth, 0);
    $before = $db->state;
    $db->baseline = false;
    $db->activeCash = true;
    $service->post($context, $uuid, $input);
    $same($db->state, $before);
    foreach (
        [
            "stock_movements",
            "stock_positions",
            "sale_items",
            "sale_payments",
            "receivables",
            "sales",
            "audit",
            "changes",
            "commit",
        ]
        as $stage
    ) {
        [$db, $service] = fixture();
        $before = $db->state;
        $db->fail = $stage;
        try {
            $service->post($context, $uuid, $input);
            throw new LogicException("Failure was not injected");
        } catch (RuntimeException $e) {
            if ($e instanceof LogicException) {
                throw $e;
            }
        }
        $same($db->state, $before);
        $same($db->rollbacks, 1);
        $same($db->commits, 0);
    }
    foreach (
        [
            [
                "cash_amount" => "600.00",
                "transfer_amount" => "0.00",
                "credit" => "0.00",
                "payments" => 1,
            ],
            [
                "cash_amount" => "0.00",
                "transfer_amount" => "600.00",
                "credit" => "0.00",
                "payments" => 1,
            ],
            [
                "cash_amount" => "100.00",
                "transfer_amount" => "500.00",
                "credit" => "0.00",
                "payments" => 2,
            ],
            [
                "cash_amount" => "0.00",
                "transfer_amount" => "0.00",
                "credit" => "600.00",
                "payments" => 0,
            ],
            [
                "cash_amount" => "100.00",
                "transfer_amount" => "0.00",
                "credit" => "500.00",
                "payments" => 1,
            ],
            [
                "cash_amount" => "0.00",
                "transfer_amount" => "200.00",
                "credit" => "400.00",
                "payments" => 1,
            ],
            [
                "cash_amount" => "100.00",
                "transfer_amount" => "200.00",
                "credit" => "300.00",
                "payments" => 2,
            ],
        ]
        as $case
    ) {
        [$db, $service] = fixture();
        $db->baseline = $case["cash_amount"] !== "0.00";
        $body = [
            "expected_updated_at" => $version,
            "cash_amount" => $case["cash_amount"],
            "transfer_amount" => $case["transfer_amount"],
        ];
        $service->post($context, $uuid, $body);
        $same(
            $db->state["sales"][0]["credit_created_amount_snapshot"],
            $case["credit"],
        );
        $same(count($db->state["sale_payments"]), $case["payments"]);
        $same(
            count($db->state["receivables"]),
            $case["credit"] === "0.00" ? 0 : 1,
        );
    }
    foreach (
        [
            "baseline",
            "active_cash",
            "scope",
            "stale",
            "inactive_product",
            "inactive_customer",
            "missing_customer",
            "overpay",
            "price",
            "readonly",
            "blocked",
        ]
        as $case
    ) {
        [$db, $service] = fixture();
        $body = $input;
        if ($case === "baseline") {
            $db->baseline = false;
        }
        if ($case === "active_cash") {
            $db->activeCash = true;
        }
        if ($case === "scope") {
            $db->state["sales"][0]["tenant_id"] = 2;
        }
        if ($case === "stale") {
            $body["expected_updated_at"] = "stale";
        }
        if ($case === "inactive_product") {
            $db->state["products"][0]["status"] = "INACTIVE";
        }
        if ($case === "inactive_customer") {
            $db->state["customers"][0]["status"] = "INACTIVE";
        }
        if ($case === "missing_customer") {
            $db->state["sales"][0]["customer_id"] = null;
        }
        if ($case === "overpay") {
            $body["cash_amount"] = "999.00";
        }
        if ($case === "price") {
            $db->state["products"][0]["current_selling_price"] = "101.00";
        }
        if ($case === "readonly") {
            $db->mode = "READ_ONLY";
        }
        if ($case === "blocked") {
            $db->mode = "BLOCKED";
        }
        $before = $db->state;
        try {
            $service->post($context, $uuid, $body);
            throw new LogicException("Invalid posting accepted");
        } catch (\App\Exceptions\ApiException) {
        }
        $same($db->state, $before);
        $same($db->commits, 0);
    }
    [$db, $service] = fixture();
    $db->baseline = false;
    $db->state["sales"][0]["customer_id"] = null;
    $service->post($context, $uuid, [
        "expected_updated_at" => $version,
        "transfer_amount" => "600.00",
    ]);
    $same(count($db->state["receivables"]), 0);
    $same($db->state["sales"][0]["total_amount"], "600.00");
    [$db, $service] = fixture();
    $db->state["stock_positions"][0]["expected_quantity"] = "0.0000";
    $db->state["stock_positions"][0]["average_unit_cost"] = "0.000000";
    $db->state["stock_positions"][0]["cost_basis_state"] = "UNESTABLISHED";
    $service->post($context, $uuid, $input);
    $same($db->state["stock_positions"][0]["expected_quantity"], "-6.0000");
    $same(
        $db->state["stock_positions"][0]["cost_basis_state"],
        "UNESTABLISHED",
    );
    $same(array_column($db->state["sale_items"], "line_cogs_amount"), [
        "0.00",
        "0.00",
    ]);
    $same($db->state["audit"][0]->metadata["unestablished_cost_lines"], 2);
    foreach (
        [
            ["cash_amount" => null],
            ["cash_amount" => 1.5],
            ["total_amount" => "1.00"],
            ["status" => "POSTED"],
        ]
        as $bad
    ) {
        [$db, $service] = fixture();
        $before = $db->state;
        try {
            $service->post($context, $uuid, array_replace($input, $bad));
            throw new LogicException("Invalid input accepted");
        } catch (\App\Exceptions\ApiException) {
        }
        $same($db->state, $before);
        $same($db->commits, 0);
    }
    echo "PASS: {$checks} posting-orchestration checks with doubles, including all compositions, repeated lines, retry and injected rollback paths. Not MySQL/HTTP/concurrency acceptance.\n";
}
