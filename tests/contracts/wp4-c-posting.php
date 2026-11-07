<?php
// Real C services with explicit B authority/database doubles; NOT MySQL/HTTP/concurrency acceptance.
declare(strict_types=1);
require __DIR__ . "/wp4-b-posting.php";
require __DIR__ .
    "/../../app/Modules/Sales/Application/CommercialReversalService.php";
require __DIR__ .
    "/../../app/Modules/Sales/Infrastructure/CommercialCorrectionQuery.php";
function cfixture(): array
{
    [$db, $sales, $returns] = w4fixture();
    $db->state["products"][0]["tenant_id"] = 1;
    $audit = new \App\Modules\Audit\Application\AuditService($db);
    $access = new \App\Modules\Sales\Application\CommercialAccess($db, $audit);
    $events = new \App\Modules\Sales\Application\CommercialEvents(
        $db,
        $audit,
        new \App\Modules\Sync\Application\ChangeSequenceService($db),
    );
    return [
        $db,
        $sales,
        $returns,
        new \App\Modules\Sales\Application\CommercialReversalService(
            $db,
            $access,
            $events,
            new \App\Libraries\Http\RequestContext(),
        ),
        new \App\Modules\Sales\Infrastructure\CommercialCorrectionQuery(
            $db,
            $access,
        ),
    ];
}
function creverse(object $reverse, string $id): string
{
    global $context;
    $p = $reverse->preview($context, $id, []);
    $uuid = \App\Support\Uuid::v4();
    return $reverse->reverse($context, $id, [
        "public_id" => $uuid,
        "review_fingerprint" => $p["review_fingerprint"],
        "reason" => "Development compensation verification.",
    ]);
}
function cexpect(callable $op, string $code): void
{
    global $same;
    try {
        $op();
        throw new LogicException("Expected " . $code);
    } catch (\App\Exceptions\ApiException $e) {
        $same($e->errorCode, $code);
    }
}
$checks = 0;
[$db, $sales, $returns, $reverse, $query] = cfixture();
$sales->post($context, $uuid, $input);
$id = \App\Support\Uuid::v4();
$returns->create($context, $uuid, ["public_id" => $id]);
w4save($db, $returns, $id, [0 => "2"]);
w4post($db, $returns, $id);
cexpect(
    fn() => $reverse->preview($context, $uuid, []),
    "SALE_HAS_EFFECTIVE_RETURNS",
);
$before = $db->state;
$preview = $reverse->preview($context, $id, []);
$same($db->state, $before);
$same($preview["debt_direction"], "RESTORE");
$same($preview["debt_amount"], "200.00");
$same($preview["cash_amount"], "0.00");
$rev = creverse($reverse, $id);
$same(count($db->state["receivables"]), 1);
$same($db->state["receivable_adjustments"][1]["amount_delta"], "200.00");
$same(w4row($db, $rev)["received_amount_snapshot"], null);
$same(w4row($db, $rev)["credit_created_amount_snapshot"], null);
$same($db->state["stock_positions"][0]["expected_quantity"], "-1.0000");
$same($db->state["stock_positions"][0]["cost_basis_state"], "RETAINED");
$before = $db->state;
$reverse->reverse($context, $id, [
    "public_id" => $rev,
    "reason" => "Resolve retry after committed compensation.",
]);
$same($db->state, $before);
cexpect(fn() => creverse($reverse, $id), "SALE_ALREADY_REVERSED");
cexpect(
    fn() => $reverse->preview($context, $rev, []),
    "REVERSAL_OF_REVERSAL_NOT_ALLOWED",
);
$receipt = $query->detail($context, $rev);
$same($receipt["operation_type"], "COMPENSATION");
$same($receipt["reversal_of_public_id"], $id);
$same(count($receipt["items"]), 1);
$same(
    $receipt["credit_adjustments"][0]["receivable_public_id"],
    $db->state["receivables"][0]["public_id"],
);
$same(
    $receipt["credit_adjustments"][0]["reversal_of_adjustment_public_id"],
    $db->state["receivable_adjustments"][0]["public_id"],
);
$db->deniedPermissions = ["sale.profit.view"];
$masked = $query->detail($context, $rev);
$same($masked["profit_visible"], false);
$same(isset($masked["items"][0]["unit_cost_snapshot"]), false);
$same(isset($masked["items"][0]["line_cogs_amount"]), false);
$db->deniedPermissions = [];
$state = $query->state($context, $id);
$same($state["reversal_public_id"], $rev);
$repl = \App\Support\Uuid::v4();
$before = $db->state;
$reverse->replacement($context, $id, [
    "public_id" => $repl,
    "reason" => "Prepare a corrected Return.",
]);
$same(w4row($db, $repl)["status"], "DRAFT");
$same(w4row($db, $repl)["original_sale_id"], 1);
$same(count($db->state["stock_movements"]), count($before["stock_movements"]));
$same(count($db->state["sale_payments"]), count($before["sale_payments"]));
$same(
    count($db->state["receivable_adjustments"]),
    count($before["receivable_adjustments"]),
);
$before = $db->state;
$reverse->replacement($context, $id, [
    "public_id" => $repl,
    "reason" => "Retrieve existing replacement.",
]);
$same($db->state, $before);
cexpect(
    fn() => $reverse->replacement($context, $id, [
        "public_id" => \App\Support\Uuid::v4(),
        "reason" => "Another replacement denied.",
    ]),
    "REPLACEMENT_EXISTS",
);
w4post($db, $returns, $repl);
$same(w4row($db, $repl)["status"], "POSTED");
creverse($reverse, $repl);
// Full paid/partial credit compensation across all seven original compositions.
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
    [$db, $sales, $returns, $reverse, $query] = cfixture();
    $sales->post($context, $uuid, [
        "expected_updated_at" => $version,
        "cash_amount" => $cash,
        "transfer_amount" => $transfer,
    ]);
    $id = \App\Support\Uuid::v4();
    $returns->create($context, $uuid, ["public_id" => $id]);
    w4save($db, $returns, $id, [0 => "2", 1 => "4"]);
    w4post($db, $returns, $id, $cash, $transfer);
    $rev = creverse($reverse, $id);
    $same(w4row($db, $rev)["kind"], "SALE");
    $same(w4row($db, $rev)["received_amount_snapshot"], null);
    $same($db->state["stock_positions"][0]["expected_quantity"], "-1.0000");
    $receipt = $query->detail($context, $rev);
    $same(
        \App\Modules\Sales\Application\SaleMoney::sum(
            array_column($receipt["payments"], "amount"),
        ),
        \App\Modules\Sales\Application\SaleMoney::sum([$cash, $transfer]),
    );
    foreach ($receipt["payments"] as $p) {
        $same($p["direction"], "RECEIPT");
        $same(is_string($p["reversal_of_payment_public_id"]), true);
    }
    $original = creverse($reverse, $uuid);
    $same(w4row($db, $original)["kind"], "RETURN");
    $same($db->state["stock_positions"][0]["expected_quantity"], "5.0000");
    $same($db->state["stock_positions"][0]["cost_basis_state"], "VALID");
    $replacement = $reverse->replacement($context, $uuid, [
        "public_id" => \App\Support\Uuid::v4(),
        "reason" => "Prepare forward Sale replacement.",
    ]);
    $same(w4row($db, $replacement)["status"], "DRAFT");
    $same(w4row($db, $replacement)["replacement_for_sale_id"], 1);
    $sales->post($context, $replacement, [
        "expected_updated_at" => w4row($db, $replacement)["updated_at"],
        "transfer_amount" => "600.00",
    ]);
    $same(w4row($db, $replacement)["status"], "POSTED");
}
// Every compensation write and transaction boundary is atomic on injected failure.
foreach (
    [
        "begin",
        "sales",
        "sale_items",
        "stock_movements",
        "stock_positions",
        "sale_payments",
        "receivable_adjustments",
        "audit",
        "changes",
        "commit",
    ]
    as $stage
) {
    [$db, $sales, $returns, $reverse, $query] = cfixture();
    $sales->post($context, $uuid, $input);
    $p = $reverse->preview($context, $uuid, []);
    $before = $db->state;
    $db->fail = $stage;
    try {
        $reverse->reverse($context, $uuid, [
            "public_id" => \App\Support\Uuid::v4(),
            "review_fingerprint" => $p["review_fingerprint"],
            "reason" => "Injected correction failure.",
        ]);
        throw new LogicException("Missing failure " . $stage);
    } catch (RuntimeException $e) {
        if ($e instanceof LogicException) {
            throw $e;
        }
    }
    $same($db->state, $before);
}

// Eligibility/stale review, Cash, authority and source-integrity denials leave no effects.
[$db, $sales, $returns, $reverse, $query] = cfixture();
$sales->post($context, $uuid, $input);
$p = $reverse->preview($context, $uuid, []);
$before = $db->state;
cexpect(
    fn() => $reverse->reverse($context, $uuid, [
        "public_id" => \App\Support\Uuid::v4(),
        "reason" => "Reject stale review.",
        "review_fingerprint" => "wrong",
    ]),
    "COMMERCIAL_REVIEW_STALE",
);
$same($db->state, $before);
$db->baseline = false;
cexpect(fn() => creverse($reverse, $uuid), "OPENING_CASH_REQUIRED");
$same($db->state, $before);
$db->baseline = true;
$db->deniedPermissions = ["sale.reverse"];
cexpect(fn() => $reverse->preview($context, $uuid, []), "PERMISSION_DENIED");
$same($db->state, $before);
$db->deniedPermissions = [];
foreach (["READ_ONLY", "BLOCKED", "CLOSED"] as $mode) {
    $db->mode = $mode;
    cexpect(fn() => $reverse->preview($context, $uuid, []), $mode);
    $same($db->state, $before);
}
$db->mode = "FULL";
$db->state["stock_movements"][] = [
    "id" => 1000,
    "public_id" => \App\Support\Uuid::v4(),
    "tenant_id" => 1,
    "shop_id" => 1,
    "product_id" => 1,
    "source_kind" => "MANUAL",
    "movement_type" => "CORRECTION",
    "quantity_before" => "-1.0000",
    "quantity_after" => "-1.0000",
    "average_cost_before" => "60.000000",
    "average_cost_after" => "60.000000",
];
$db->state["stock_positions"][0]["last_movement_id"] = 1000;
$before = $db->state;
cexpect(
    fn() => $reverse->preview($context, $uuid, []),
    "REVERSAL_STOCK_DEPENDENCY",
);
$same($db->state, $before);
// Replacement preparation is atomic too, creates no financial effects, and uses current price.
foreach (["begin", "sales", "sale_items", "audit", "commit"] as $stage) {
    [$db, $sales, $returns, $reverse, $query] = cfixture();
    $sales->post($context, $uuid, $input);
    creverse($reverse, $uuid);
    $db->state["products"][0]["current_selling_price"] = "125.00";
    $before = $db->state;
    $db->fail = $stage;
    try {
        $reverse->replacement($context, $uuid, [
            "public_id" => \App\Support\Uuid::v4(),
            "reason" => "Injected replacement failure.",
        ]);
        throw new LogicException("Replacement failure missing");
    } catch (RuntimeException $e) {
        if ($e instanceof LogicException) {
            throw $e;
        }
    }
    $same($db->state, $before);
}
$db->fail = null;
$replacement = $reverse->replacement($context, $uuid, [
    "public_id" => \App\Support\Uuid::v4(),
    "reason" => "Prepare current-price replacement.",
]);
$same(w4row($db, $replacement)["total_amount"], "750.00");
$same(count($db->state["stock_movements"]), count($before["stock_movements"]));
// Out-of-order partial Returns cannot be reversed past later active stock evidence.
[$db, $sales, $returns, $reverse, $query] = cfixture();
$sales->post($context, $uuid, $input);
$ids = [];
foreach (["1", "1"] as $q) {
    $id = \App\Support\Uuid::v4();
    $returns->create($context, $uuid, ["public_id" => $id]);
    w4save($db, $returns, $id, [1 => $q]);
    w4post($db, $returns, $id);
    $ids[] = $id;
}
cexpect(
    fn() => $reverse->preview($context, $ids[0], []),
    "REVERSAL_STOCK_DEPENDENCY",
);
creverse($reverse, $ids[1]);
creverse($reverse, $ids[0]);
$same($db->state["stock_positions"][0]["expected_quantity"], "-1.0000");
// After the root Sale is compensated no new Return/replacement can bypass it.
creverse($reverse, $uuid);
cexpect(
    fn() => $reverse->replacement($context, $ids[0], [
        "public_id" => \App\Support\Uuid::v4(),
        "reason" => "Reject Return replacement of reversed root.",
    ]),
    "ORIGINAL_SALE_REVERSED",
);

// Same-original credit restoration also rolls back on every Return compensation failure.
foreach (
    [
        "sales",
        "sale_items",
        "stock_movements",
        "stock_positions",
        "sale_payments",
        "receivable_adjustments",
        "audit",
        "changes",
        "commit",
    ]
    as $stage
) {
    [$db, $sales, $returns, $reverse, $query] = cfixture();
    $sales->post($context, $uuid, $input);
    $id = \App\Support\Uuid::v4();
    $returns->create($context, $uuid, ["public_id" => $id]);
    w4save($db, $returns, $id, [0 => "2", 1 => "4"]);
    w4post($db, $returns, $id, "100.00", "200.00");
    $p = $reverse->preview($context, $id, []);
    $before = $db->state;
    $db->fail = $stage;
    try {
        $reverse->reverse($context, $id, [
            "public_id" => \App\Support\Uuid::v4(),
            "review_fingerprint" => $p["review_fingerprint"],
            "reason" => "Injected debt restoration failure.",
        ]);
        throw new LogicException("Missing restoration failure");
    } catch (RuntimeException $e) {
        if ($e instanceof LogicException) {
            throw $e;
        }
    }
    $same($db->state, $before);
}
// Safe non-cash compensations do not depend on Opening Cash.
foreach ([["0.00", "600.00"], ["0.00", "0.00"]] as [$cash, $transfer]) {
    [$db, $sales, $returns, $reverse, $query] = cfixture();
    $db->baseline = false;
    $sales->post($context, $uuid, [
        "expected_updated_at" => $version,
        "cash_amount" => $cash,
        "transfer_amount" => $transfer,
    ]);
    $rev = creverse($reverse, $uuid);
    $same(w4row($db, $rev)["status"], "POSTED");
    $same(w4row($db, $rev)["received_amount_snapshot"], null);
    $same(w4row($db, $rev)["credit_created_amount_snapshot"], null);
}

[$db, $sales, $returns, $reverse, $query] = cfixture();
$db->state["stock_movements"] = [];
$db->state["stock_positions"][0]["expected_quantity"] = "0.0000";
$db->state["stock_positions"][0]["average_unit_cost"] = "0.000000";
$db->state["stock_positions"][0]["cost_basis_state"] = "UNESTABLISHED";
$db->state["stock_positions"][0]["last_movement_id"] = null;
$sales->post($context, $uuid, $input);
$rev = creverse($reverse, $uuid);
$same($db->state["stock_positions"][0]["expected_quantity"], "0.0000");
$same($db->state["stock_positions"][0]["average_unit_cost"], "0.000000");
$same($db->state["stock_positions"][0]["cost_basis_state"], "UNESTABLISHED");
[$db, $sales, $returns, $reverse, $query] = cfixture();
$sales->post($context, $uuid, $input);
$id = \App\Support\Uuid::v4();
$returns->create($context, $uuid, ["public_id" => $id]);
w4save($db, $returns, $id, [0 => "1"]);
w4post($db, $returns, $id);
$rev = creverse($reverse, $id);
cexpect(
    fn() => $sales->post($context, $rev, [
        "expected_updated_at" => w4row($db, $rev)["updated_at"],
    ]),
    "SALE_NOT_FOUND",
);
[$db, $sales, $returns, $reverse, $query] = cfixture();
$sales->post($context, $uuid, $input);
$db->state["receivables"][0]["customer_id"] = 999;
cexpect(
    fn() => $reverse->preview($context, $uuid, []),
    "COMMERCIAL_EVIDENCE_INVALID",
);
echo "PASS: {$checks} WP4-C compensation/replacement orchestration assertions with doubles. Not native MySQL/HTTP/concurrency acceptance.\n";
