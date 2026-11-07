<?php
// Real Return/Inventory services against explicit in-memory SQL/authority doubles.
// Does not execute MySQL, HTTP or concurrent transactions.
declare(strict_types=1);
require __DIR__ . "/wp3-posting.php";
require __DIR__ . "/../../app/Support/SensitiveReason.php";
require __DIR__ .
    "/../../app/Modules/Inventory/Application/CostStateReconstructor.php";
foreach (["CommercialEvidence", "CommercialEvents", "ReturnService"] as $name) {
    require __DIR__ . "/../../app/Modules/Sales/Application/" . $name . ".php";
}
require __DIR__ .
    "/../../app/Modules/Inventory/Application/CommercialCostHistory.php";
require __DIR__ .
    "/../../app/Modules/Inventory/Application/InventoryDecimal.php";
require __DIR__ .
    "/../../app/Modules/Inventory/Application/CommercialStockParticipant.php";
class W4Builder extends FixtureBuilder
{
    private array $filters = [];
    public function __construct(
        private object $database,
        private string $name,
    ) {}
    public function select(string $fields): self
    {
        return $this;
    }
    public function join(string $table, string $on, string $type = ""): self
    {
        return $this;
    }
    public function where(string|array $key, mixed $value = null): self
    {
        $this->filters += is_array($key) ? $key : [$key => $value];
        return $this;
    }
    public function orderBy(string $field, string $direction = "ASC"): self
    {
        return $this;
    }
    private function match(array $r): bool
    {
        foreach ($this->filters as $k => $v) {
            if (($r[$k] ?? null) != $v) {
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
                    $this->database->state[$this->name] ?? [],
                    fn($r) => $this->match($r),
                ),
            ),
        );
    }
    public function countAllResults(): int
    {
        return count($this->get()->getResultArray());
    }
    public function delete(): bool
    {
        $this->database->state[$this->name] = array_values(
            array_filter(
                $this->database->state[$this->name],
                fn($r) => !$this->match($r),
            ),
        );
        return true;
    }
    public function insert(array $r): bool
    {
        if ($this->database->fail === $this->name) {
            throw new RuntimeException("Injected " . $this->name);
        }
        if ($this->name === "sales") {
            $r += [
                "reversal_of_sale_id" => null,
                "replacement_for_sale_id" => null,
                "original_sale_id" => null,
                "received_amount_snapshot" => null,
                "credit_created_amount_snapshot" => null,
                "posted_by_user_id" => null,
                "posted_at" => null,
                "subtotal_amount" => "0.00",
                "total_amount" => "0.00",
            ];
        }
        $r["id"] = ++$this->database->lastId;
        $this->database->state[$this->name][] = $r;
        return true;
    }
    public function update(array $values): bool
    {
        if ($this->database->fail === $this->name) {
            throw new RuntimeException("Injected " . $this->name);
        }
        foreach ($this->database->state[$this->name] as &$r) {
            if ($this->match($r)) {
                foreach ($values as $key => $value) {
                    $r[$key] =
                        $value instanceof \CodeIgniter\Database\RawSql
                            ? $r[$key] + 1
                            : $value;
                }
            }
        }
        return true;
    }
}
class W4DB extends \CodeIgniter\Database\BaseConnection
{
    public array $deniedPermissions = [];
    public function table(string $name): FixtureBuilder
    {
        return new W4Builder($this, $name);
    }
    public function tableExists(string $name): bool
    {
        return array_key_exists($name, $this->state);
    }
    private function active(array $r): bool
    {
        foreach ($this->state["sales"] as $s) {
            if (
                ($s["reversal_of_sale_id"] ?? null) == $r["id"] &&
                $s["status"] === "POSTED"
            ) {
                return false;
            }
        }
        return $r["kind"] === "RETURN" &&
            $r["status"] === "POSTED" &&
            ($r["reversal_of_sale_id"] ?? null) === null;
    }
    private function row(string $table, int $id): ?array
    {
        foreach ($this->state[$table] as $r) {
            if ($r["id"] === $id) {
                return $r;
            }
        }
        return null;
    }
    public function query(string $sql, array $args = []): FixtureResult
    {
        $this->queries[] = $sql;
        if (
            str_contains($sql, "FROM sales WHERE") &&
            !str_contains($sql, "SELECT COALESCE")
        ) {
            $rows = array_values(
                array_filter(
                    $this->state["sales"],
                    fn($r) => $r["tenant_id"] == $args[0] &&
                        $r["shop_id"] == $args[1] &&
                        (str_contains($sql, "public_id=?")
                            ? $r["public_id"] == $args[2]
                            : $r["id"] == $args[2]),
                ),
            );
            return new FixtureResult($rows);
        }
        if (
            str_contains($sql, "FROM sale_items WHERE") &&
            !str_contains($sql, "SUM(")
        ) {
            return new FixtureResult(
                array_values(
                    array_filter(
                        $this->state["sale_items"],
                        fn($i) => $i["sale_id"] == $args[2],
                    ),
                ),
            );
        }
        if (str_contains($sql, "FROM sale_items i JOIN products")) {
            $items = array_values(
                array_filter(
                    $this->state["sale_items"],
                    fn($i) => $i["sale_id"] == $args[2],
                ),
            );
            foreach ($items as &$i) {
                $p = $this->row("products", (int) $i["product_id"]);
                $i += [
                    "product_public_id" => $p["public_id"],
                    "product_name" => $p["name"],
                    "unit_name" => $p["unit_name"],
                ];
            }
            return new FixtureResult($items);
        }
        if (str_contains($sql, "COALESCE(SUM(i.quantity)")) {
            $q = 0;
            $money = 0;
            $cogs = 0;
            foreach ($this->state["sale_items"] as $i) {
                if (
                    ($i["original_sale_item_id"] ?? null) == $args[2] &&
                    $this->active($this->row("sales", (int) $i["sale_id"]))
                ) {
                    $q += fixtureScaled($i["quantity"], 4);
                    $money += fixtureScaled($i["line_sales_amount"], 2);
                    $cogs += fixtureScaled($i["line_cogs_amount"], 2);
                }
            }
            return new FixtureResult([
                [
                    "quantity" => fixtureFormat($q, 4),
                    "amount" => fixtureFormat($money, 2),
                    "cogs" => fixtureFormat($cogs, 2),
                ],
            ]);
        }
        if (str_contains($sql, "GREATEST(0.00,LEAST")) {
            $amount = fixtureScaled($args[0], 2);
            $old = fixtureScaled($args[1], 2);
            $qty = fixtureScaled($args[3], 4);
            $den = fixtureScaled($args[4], 4);
            $target = intdiv(
                fixtureScaled($args[2], 2) * $qty + intdiv($den, 2),
                $den,
            );
            return new FixtureResult([
                [
                    "value" => fixtureFormat(
                        max(
                            0,
                            min(
                                $amount - $old,
                                $target - fixtureScaled($args[5], 2),
                            ),
                        ),
                        2,
                    ),
                ],
            ]);
        }
        if (str_contains($sql, "DECIMAL(38,4))>CAST")) {
            return new FixtureResult([
                [
                    "value" =>
                        fixtureScaled($args[0], 4) > fixtureScaled($args[1], 4)
                            ? 1
                            : 0,
                ],
            ]);
        }
        if (str_contains($sql, "DECIMAL(38,4))+CAST")) {
            return new FixtureResult([
                [
                    "value" => fixtureFormat(
                        fixtureScaled($args[0], 4) + fixtureScaled($args[1], 4),
                        4,
                    ),
                ],
            ]);
        }
        if (str_contains($sql, "FROM receivables WHERE")) {
            return new FixtureResult(
                array_values(
                    array_filter(
                        $this->state["receivables"],
                        fn($r) => $r["sale_id"] == $args[2],
                    ),
                ),
            );
        }
        if (str_contains($sql, "SUM(amount_delta)")) {
            $v = fixtureScaled($args[0], 2);
            foreach ($this->state["receivable_adjustments"] as $a) {
                if ($a["receivable_id"] == $args[3]) {
                    $v += fixtureScaled($a["amount_delta"], 2);
                }
            }
            return new FixtureResult([["value" => fixtureFormat($v, 2)]]);
        }
        if (str_contains($sql, "SELECT (SELECT COALESCE(SUM(amount)")) {
            $v = 0;
            foreach ($this->state["sale_payments"] as $p) {
                if (
                    $p["sale_id"] == $args[2] &&
                    $p["direction"] === "RECEIPT" &&
                    $p["channel"] === $args[3]
                ) {
                    $v += fixtureScaled($p["amount"], 2);
                }
                $r = $this->row("sales", (int) $p["sale_id"]);
                if (
                    ($r["original_sale_id"] ?? null) == $args[6] &&
                    $this->active($r) &&
                    $p["direction"] === "REFUND" &&
                    $p["channel"] === $args[7]
                ) {
                    $v -= fixtureScaled($p["amount"], 2);
                }
            }
            return new FixtureResult([["value" => fixtureFormat($v, 2)]]);
        }
        if (str_contains($sql, "SELECT r.public_id FROM sales r")) {
            foreach ($this->state["sales"] as $r) {
                if (
                    ($r["original_sale_id"] ?? null) == $args[2] &&
                    $this->active($r)
                ) {
                    return new FixtureResult([
                        ["public_id" => $r["public_id"]],
                    ]);
                }
            }
            return new FixtureResult([]);
        }
        if (
            str_contains($sql, "FROM stock_movements WHERE") &&
            str_contains($sql, "sale_item_id=?")
        ) {
            return new FixtureResult(
                array_values(
                    array_filter(
                        $this->state["stock_movements"],
                        fn($r) => ($r["sale_item_id"] ?? null) == $args[2] &&
                            $r["source_kind"] === "SALE_ITEM",
                    ),
                ),
            );
        }
        if (str_contains($sql, "FROM stock_movements m")) {
            $rows = array_values(
                array_filter(
                    $this->state["stock_movements"],
                    fn($m) => $m["product_id"] == $args[2] &&
                        (!isset($args[3]) || $m["id"] <= $args[3]),
                ),
            );
            usort($rows, fn($a, $b) => $a["id"] <=> $b["id"]);
            foreach ($rows as &$m) {
                $original = $this->row(
                    "stock_movements",
                    (int) ($m["reversal_of_movement_id"] ?? 0),
                );
                $m["original_source_kind"] = $original["source_kind"] ?? null;
                $m["original_sale_movement_id"] = null;
                if ($m["movement_type"] === "CUSTOMER_RETURN") {
                    $i = $this->row("sale_items", (int) $m["sale_item_id"]);
                    foreach ($this->state["stock_movements"] as $o) {
                        if (
                            ($o["sale_item_id"] ?? null) ==
                                $i["original_sale_item_id"] &&
                            $o["source_kind"] === "SALE_ITEM"
                        ) {
                            $m["original_sale_movement_id"] = $o["id"];
                        }
                    }
                }
            }
            return new FixtureResult($rows);
        }
        if (str_contains($sql, "movement_type='CUSTOMER_RETURN'")) {
            return new FixtureResult(
                array_values(
                    array_filter(
                        $this->state["stock_movements"],
                        fn($m) => $m["movement_type"] === "CUSTOMER_RETURN" &&
                            $m["product_id"] == $args[2],
                    ),
                ),
            );
        }
        if (str_contains($sql, "new_quantity")) {
            $q = fixtureScaled($args[0], 4);
            $inc = fixtureScaled($args[1], 4);
            $avg = fixtureScaled($args[4], 6);
            $cost = fixtureScaled($args[6], 6);
            $new = $q + $inc;
            $basis =
                $q > 0
                    ? intdiv($q * $avg + $inc * $cost + intdiv($new, 2), $new)
                    : ($new > 0
                        ? fixtureScaled($args[11], 6)
                        : fixtureScaled($args[12], 6));
            return new FixtureResult([
                [
                    "new_quantity" => fixtureFormat($new, 4),
                    "new_average" => fixtureFormat($basis, 6),
                ],
            ]);
        }
        if (str_contains($sql, "equal_value")) {
            $a = fixtureScaled($args[0], 4);
            $b = fixtureScaled($args[1], 4);
            return new FixtureResult([
                [
                    "equal_value" => $a === $b ? 1 : 0,
                    "less_value" => $a < $b ? 1 : 0,
                ],
            ]);
        }
        if (str_contains($sql, "less_value")) {
            return new FixtureResult([
                [
                    "less_value" =>
                        fixtureScaled($args[0], 4) < fixtureScaled($args[1], 4)
                            ? 1
                            : 0,
                ],
            ]);
        }
        if (str_contains($sql, "SUM(line_sales_amount)")) {
            $v = 0;
            foreach ($this->state["sale_items"] as $i) {
                if ($i["sale_id"] == $args[0]) {
                    $v += fixtureScaled($i["line_sales_amount"], 2);
                }
            }
            return new FixtureResult([["value" => fixtureFormat($v, 2)]]);
        }
        return parent::query($sql, $args);
    }
}
function w4fixture(): array
{
    [$old, $unused] = fixture();
    $db = new W4DB();
    $db->state = $old->state;
    $db->state["receivable_adjustments"] = [];
    $db->state["products"][0] += [
        "name" => "Test medicine",
        "unit_name" => "pack",
    ];
    $db->state["sales"][0] += [
        "total_amount" => "0.00",
        "subtotal_amount" => "0.00",
    ];
    $db->state["stock_movements"][] = [
        "id" => 90,
        "public_id" => \App\Support\Uuid::v4(),
        "tenant_id" => 1,
        "shop_id" => 1,
        "product_id" => 1,
        "source_kind" => "INTAKE_ITEM",
        "movement_type" => "OPENING",
        "quantity_delta" => "5.0000",
        "quantity_before" => "0.0000",
        "quantity_after" => "5.0000",
        "average_cost_before" => "0.000000",
        "average_cost_after" => "60.000000",
        "unit_cost_basis" => "60.000000",
        "reversal_of_movement_id" => null,
    ];
    $db->state["stock_positions"][0]["last_movement_id"] = 90;
    $db->state["stock_positions"][0]["version"] = 1;
    $audit = new \App\Modules\Audit\Application\AuditService($db);
    $access = new \App\Modules\Sales\Application\CommercialAccess($db, $audit);
    $changes = new \App\Modules\Sync\Application\ChangeSequenceService($db);
    $request = new \App\Libraries\Http\RequestContext();
    $events = new \App\Modules\Sales\Application\CommercialEvents(
        $db,
        $audit,
        $changes,
    );
    return [
        $db,
        new \App\Modules\Sales\Application\SalePostingService(
            $db,
            $access,
            $audit,
            $changes,
            $request,
        ),
        new \App\Modules\Sales\Application\ReturnService(
            $db,
            $access,
            $events,
            $request,
        ),
    ];
}
function w4row(W4DB $db, string $id): array
{
    foreach ($db->state["sales"] as $r) {
        if ($r["public_id"] === $id) {
            return $r;
        }
    }
    throw new RuntimeException("Missing fixture header");
}
function w4save(W4DB $db, object $returns, string $id, array $quantities): void
{
    global $context;
    $r = w4row($db, $id);
    $lines = array_values(
        array_filter($db->state["sale_items"], fn($i) => $i["sale_id"] === 1),
    );
    foreach ($lines as &$line) {
        if (!isset($line["public_id"])) {
            $public = \App\Support\Uuid::v4();
            foreach ($db->state["sale_items"] as &$stored) {
                if ($stored["id"] === $line["id"]) {
                    $stored["public_id"] = $public;
                }
            }
            unset($stored);
            $line["public_id"] = $public;
        }
    }
    unset($line);
    $items = [];
    foreach ($quantities as $index => $q) {
        $items[] = [
            "public_id" => \App\Support\Uuid::v4(),
            "original_sale_item_public_id" => $lines[$index]["public_id"],
            "quantity" => $q,
        ];
    }
    $returns->update($context, $id, [
        "expected_updated_at" => $r["updated_at"],
        "items" => $items,
    ]);
}
function w4post(
    W4DB $db,
    object $returns,
    string $id,
    string $cash = "0.00",
    string $transfer = "0.00",
): void {
    global $context;
    $input = [
        "expected_updated_at" => w4row($db, $id)["updated_at"],
        "cash_amount" => $cash,
        "transfer_amount" => $transfer,
    ];
    $p = $returns->preview($context, $id, $input);
    $returns->post(
        $context,
        $id,
        $input + [
            "review_fingerprint" => $p["review_fingerprint"],
            "reason" => "Owner test Return.",
        ],
    );
}
$checks = 0;
$same = static function ($a, $b) use (&$checks) {
    if (is_array($a) ? serialize($a) !== serialize($b) : $a !== $b) {
        throw new RuntimeException(
            "WP4 assertion failed at check " . ($checks + 1),
        );
    }
    $checks++;
};
[$db, $sales, $returns] = w4fixture();
$sales->post($context, $uuid, $input);
$id = \App\Support\Uuid::v4();
$returns->create($context, $uuid, ["public_id" => $id]);
w4save($db, $returns, $id, [0 => "2"]);
w4post($db, $returns, $id);
$same(w4row($db, $id)["total_amount"], "200.00");
$same($db->state["receivable_adjustments"][0]["amount_delta"], "-200.00");
$same($db->state["stock_positions"][0]["expected_quantity"], "1.0000");
foreach (
    [
        "begin",
        "stock_movements",
        "stock_positions",
        "sale_items",
        "sale_payments",
        "receivable_adjustments",
        "sales",
        "audit",
        "changes",
        "commit",
    ]
    as $stage
) {
    [$db, $sales, $returns] = w4fixture();
    $sales->post($context, $uuid, $input);
    $id = \App\Support\Uuid::v4();
    $returns->create($context, $uuid, ["public_id" => $id]);
    w4save($db, $returns, $id, [0 => "2", 1 => "4"]);
    $body = [
        "expected_updated_at" => w4row($db, $id)["updated_at"],
        "cash_amount" => "100.00",
        "transfer_amount" => "200.00",
    ];
    $review = $returns->preview($context, $id, $body);
    $before = $db->state;
    $db->fail = $stage;
    try {
        $returns->post(
            $context,
            $id,
            $body + [
                "review_fingerprint" => $review["review_fingerprint"],
                "reason" => "Injected Return failure.",
            ],
        );
        throw new LogicException("Return failure not injected");
    } catch (RuntimeException $e) {
        if ($e instanceof LogicException) {
            throw $e;
        }
    }
    $same($db->state, $before);
}
foreach (
    [
        ["600.00", "0.00"],
        ["0.00", "600.00"],
        ["100.00", "500.00"],
        ["0.00", "0.00"],
        ["100.00", "0.00"],
        ["0.00", "200.00"],
        ["100.00", "200.00"],
    ]
    as [$cash, $transfer]
) {
    [$db, $sales, $returns] = w4fixture();
    $sales->post($context, $uuid, [
        "expected_updated_at" => $version,
        "cash_amount" => $cash,
        "transfer_amount" => $transfer,
    ]);
    $id = \App\Support\Uuid::v4();
    $returns->create($context, $uuid, ["public_id" => $id]);
    w4save($db, $returns, $id, [0 => "2", 1 => "4"]);
    $estimate = $returns->estimate($context, $id);
    $same(
        $estimate["refund_amount"],
        \App\Modules\Sales\Application\SaleMoney::sum([$cash, $transfer]),
    );
    w4post($db, $returns, $id, $cash, $transfer);
    $same(w4row($db, $id)["total_amount"], "600.00");
}
[$db, $sales, $returns] = w4fixture();
$sales->post($context, $uuid, $input);
$id = \App\Support\Uuid::v4();
$returns->create($context, $uuid, ["public_id" => $id]);
w4save($db, $returns, $id, [0 => "2", 1 => "4"]);
$body = [
    "expected_updated_at" => w4row($db, $id)["updated_at"],
    "cash_amount" => "100.00",
    "transfer_amount" => "200.00",
];
$preview = $returns->preview($context, $id, $body);
$before = $db->state;
foreach (
    [
        ["review_fingerprint" => "wrong"],
        ["cash_amount" => "300.00", "transfer_amount" => "0.00"],
    ]
    as $bad
) {
    try {
        $returns->post(
            $context,
            $id,
            array_replace(
                $body,
                [
                    "review_fingerprint" => $preview["review_fingerprint"],
                    "reason" => "Validate stale or channel cap.",
                ],
                $bad,
            ),
        );
        throw new LogicException("Invalid review accepted");
    } catch (\App\Exceptions\ApiException) {
    }
    $same($db->state, $before);
}
$db->baseline = false;
try {
    w4post($db, $returns, $id, "100.00", "200.00");
    throw new LogicException("Cash without baseline");
} catch (\App\Exceptions\ApiException $e) {
    $same($e->errorCode, "OPENING_CASH_REQUIRED");
}
$same($db->state, $before);
$e = new \App\Modules\Sales\Application\CommercialEvidence($db);
$o = [
    "quantity" => "0.0300",
    "line_sales_amount" => "0.01",
    "line_cogs_amount" => "0.02",
];
$a = $e->allocation(
    $o,
    ["quantity" => "0.0000", "amount" => "0.00", "cogs" => "0.00"],
    "0.01",
);
$b = $e->allocation(
    $o,
    [
        "quantity" => "0.0100",
        "amount" => $a["line_sales_amount"],
        "cogs" => $a["line_cogs_amount"],
    ],
    "0.01",
);
$d = $e->allocation(
    $o,
    [
        "quantity" => "0.0200",
        "amount" => \App\Modules\Sales\Application\SaleMoney::sum([
            $a["line_sales_amount"],
            $b["line_sales_amount"],
        ]),
        "cogs" => \App\Modules\Sales\Application\SaleMoney::sum([
            $a["line_cogs_amount"],
            $b["line_cogs_amount"],
        ]),
    ],
    "0.01",
);
$same(
    \App\Modules\Sales\Application\SaleMoney::sum([
        $a["line_sales_amount"],
        $b["line_sales_amount"],
        $d["line_sales_amount"],
    ]),
    "0.01",
);
$same(
    \App\Modules\Sales\Application\SaleMoney::sum([
        $a["line_cogs_amount"],
        $b["line_cogs_amount"],
        $d["line_cogs_amount"],
    ]),
    "0.02",
);

// Permanent draft identity, preview side effects, cumulative eligibility and committed retry.
[$db, $sales, $returns] = w4fixture();
$sales->post($context, $uuid, $input);
$id = \App\Support\Uuid::v4();
$same($returns->create($context, $uuid, ["public_id" => $id]), $id);
$before = $db->state;
$same($returns->create($context, $uuid, ["public_id" => $id]), $id);
$same($db->state, $before);
w4save($db, $returns, $id, [0 => "2"]);
$before = $db->state;
$estimate = $returns->estimate($context, $id);
$body = [
    "expected_updated_at" => w4row($db, $id)["updated_at"],
    "cash_amount" => "0.00",
    "transfer_amount" => "0.00",
];
$p = $returns->preview($context, $id, $body);
$same($db->state, $before);
$same($estimate["debt_reduction"], "200.00");
$same($estimate["refund_amount"], "0.00");
w4post($db, $returns, $id);
$same(count($db->state["receivables"]), 1);
$same(count($db->state["sale_payments"]), 2);
$saved = $db->state;
$returns->post(
    $context,
    $id,
    $body + [
        "review_fingerprint" => $p["review_fingerprint"],
        "reason" => "Retry the same Return after response loss.",
    ],
);
$same($db->state, $saved);
$same(w4row($db, $id)["received_amount_snapshot"], null);
$same(w4row($db, $id)["credit_created_amount_snapshot"], null);
try {
    $returns->estimate($context, $id);
    throw new LogicException("Posted estimate allowed");
} catch (\App\Exceptions\ApiException $e) {
    $same($e->errorCode, "RETURN_STATE_CONFLICT");
}
$id2 = \App\Support\Uuid::v4();
$returns->create($context, $uuid, ["public_id" => $id2]);
try {
    w4save($db, $returns, $id2, [0 => "0.0001"]);
    throw new LogicException("Over-return allowed");
} catch (\App\Exceptions\ApiException $e) {
    $same($e->errorCode, "RETURN_QUANTITY_EXCEEDED");
}
w4save($db, $returns, $id2, [1 => "4"]);
$q = $returns->estimate($context, $id2);
$same($q["debt_reduction"], "100.00");
$same($q["refund_amount"], "300.00");
w4post($db, $returns, $id2, "100.00", "200.00");
$same($db->state["stock_positions"][0]["expected_quantity"], "5.0000");
// Competing valid draft makes an already-reviewed settlement stale without partial effects.
[$db, $sales, $returns] = w4fixture();
$sales->post($context, $uuid, $input);
$first = \App\Support\Uuid::v4();
$second = \App\Support\Uuid::v4();
foreach ([$first, $second] as $id) {
    $returns->create($context, $uuid, ["public_id" => $id]);
    w4save($db, $returns, $id, [1 => "2"]);
}
$body = [
    "expected_updated_at" => w4row($db, $second)["updated_at"],
    "cash_amount" => "0.00",
    "transfer_amount" => "0.00",
];
$p = $returns->preview($context, $second, $body);
w4post($db, $returns, $first);
$before = $db->state;
try {
    $returns->post(
        $context,
        $second,
        $body + [
            "review_fingerprint" => $p["review_fingerprint"],
            "reason" => "Stale reviewed settlement.",
        ],
    );
    throw new LogicException("Stale settlement allowed");
} catch (\App\Exceptions\ApiException $e) {
    $same($e->errorCode, "RETURN_REFUND_MISMATCH");
}
$same($db->state, $before);
// Draft validation/discard and current authority are enforced by real service/access code.
foreach (["READ_ONLY", "BLOCKED", "CLOSED"] as $mode) {
    $db->mode = $mode;
    try {
        $returns->create($context, $uuid, [
            "public_id" => \App\Support\Uuid::v4(),
        ]);
        throw new LogicException("Access mode bypass");
    } catch (\App\Exceptions\ApiException $e) {
        $same($e->errorCode, $mode);
    }
    $same($db->state, $before);
}
$db->mode = "FULL";
$db->deniedPermissions = ["sale.return"];
try {
    $returns->preview($context, $second, $body);
    throw new LogicException("Return grant bypass");
} catch (\App\Exceptions\ApiException $e) {
    $same($e->errorCode, "PERMISSION_DENIED");
}
$same($db->state, $before);
$db->deniedPermissions = [];
$discardVersion = w4row($db, $second)["updated_at"];
try {
    $returns->update($context, $second, [
        "expected_updated_at" => "2000-01-01 00:00:00.000000",
        "items" => [],
    ]);
    throw new LogicException("Stale draft overwrite");
} catch (\App\Exceptions\ApiException $e) {
    $same($e->status, 409);
}
$same($db->state, $before);
foreach (["0", "-1", "1.00001", "bad"] as $quantity) {
    try {
        w4save($db, $returns, $second, [1 => $quantity]);
        throw new LogicException("Invalid quantity");
    } catch (\App\Exceptions\ApiException) {
    }
    $same($db->state, $before);
}
$returns->discard($context, $second, [
    "expected_updated_at" => $discardVersion,
    "reason" => "Discard an unused Return draft.",
]);
$same(
    count(
        array_filter(
            $db->state["sales"],
            fn($r) => $r["public_id"] === $second,
        ),
    ),
    0,
);
$same(count($db->state["stock_movements"]), count($before["stock_movements"]));
$same(count($db->state["sale_payments"]), count($before["sale_payments"]));
// Historical identity and zero-value lines; no fake payment/adjustment rows.
[$db, $sales, $returns] = w4fixture();
$sales->post($context, $uuid, $input);
$db->state["products"][0]["current_selling_price"] = "999.00";
$db->state["products"][0]["status"] = "INACTIVE";
$db->state["customers"][0]["status"] = "INACTIVE";
$id = \App\Support\Uuid::v4();
$returns->create($context, $uuid, ["public_id" => $id]);
w4save($db, $returns, $id, [0 => "1"]);
w4post($db, $returns, $id);
$same(w4row($db, $id)["total_amount"], "100.00");
[$db, $sales, $returns] = w4fixture();
foreach ($db->state["sale_items"] as &$i) {
    $i["unit_selling_price"] = "0.00";
}
unset($i);
$db->override = true;
$sales->post($context, $uuid, [
    "expected_updated_at" => w4row($db, $uuid)["updated_at"],
    "cash_amount" => "0.00",
    "transfer_amount" => "0.00",
]);
$id = \App\Support\Uuid::v4();
$returns->create($context, $uuid, ["public_id" => $id]);
w4save($db, $returns, $id, [0 => "2"]);
w4post($db, $returns, $id);
$same(w4row($db, $id)["total_amount"], "0.00");
$same(count($db->state["sale_payments"]), 0);
$same(count($db->state["receivable_adjustments"]), 0);
// Read-only representation excludes costs without the independent profit grant.
require __DIR__ . "/../../app/Modules/Sales/Infrastructure/ReturnQuery.php";
$audit = new \App\Modules\Audit\Application\AuditService($db);
$access = new \App\Modules\Sales\Application\CommercialAccess($db, $audit);
$db->deniedPermissions = ["sale.profit.view"];
$reader = new \App\Modules\Sales\Infrastructure\ReturnQuery($db, $access);
$detail = $reader->detail($context, $id);
$same($detail["profit_visible"], false);
$same(isset($detail["items"][0]["unit_cost_snapshot"]), false);
$same(isset($detail["items"][0]["line_cogs_amount"]), false);
$same(isset($detail["total_cogs_amount"]), false);
$same($detail["original_sale_public_id"], $uuid);

foreach ([["0.00", "600.00"], ["0.00", "0.00"]] as [$cash, $transfer]) {
    [$db, $sales, $returns] = w4fixture();
    $db->baseline = false;
    $sales->post($context, $uuid, [
        "expected_updated_at" => $version,
        "cash_amount" => $cash,
        "transfer_amount" => $transfer,
    ]);
    $id = \App\Support\Uuid::v4();
    $returns->create($context, $uuid, ["public_id" => $id]);
    w4save($db, $returns, $id, [0 => "2", 1 => "4"]);
    w4post($db, $returns, $id, $cash, $transfer);
    $same(w4row($db, $id)["status"], "POSTED");
}
$decimal = new \App\Modules\Inventory\Application\InventoryDecimal($db);
$next = $decimal->incoming(
    "10.0000",
    "80.000000",
    "VALID",
    "2.0000",
    "60.000000",
    true,
);
$same($next["average"], "76.666667");
$same($next["quantity"], "12.0000");
$same(
    $decimal->incoming(
        "10.0000",
        "80.000000",
        "UNESTABLISHED",
        "2.0000",
        "60.000000",
        true,
    )["state"],
    "UNESTABLISHED",
);
$same(
    $decimal->incoming(
        "0.0000",
        "80.000000",
        "UNESTABLISHED",
        "2.0000",
        "60.000000",
        true,
    )["state"],
    "VALID",
);
echo "PASS: {$checks} WP4-B Return draft/settlement/posting/authority/privacy/rollback assertions with doubles. Not native MySQL/HTTP/concurrency acceptance.\n";
