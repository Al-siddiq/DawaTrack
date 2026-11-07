<?php
// Pure Return-era provenance check; no database/HTTP execution.
declare(strict_types=1);
require __DIR__ .
    "/../../app/Modules/Inventory/Application/CostStateReconstructor.php";
$checks = 0;
$same = static function ($a, $b) use (&$checks) {
    if ($a !== $b) {
        throw new RuntimeException("Return provenance contract mismatch");
    }
    $checks++;
};
$chain = [
    [
        "id" => 1,
        "source_kind" => "SALE_ITEM",
        "movement_type" => "SALE",
        "quantity_before" => "0.0000",
        "quantity_after" => "-1.0000",
    ],
    [
        "id" => 2,
        "source_kind" => "INTAKE_ITEM",
        "quantity_before" => "-1.0000",
        "quantity_after" => "5.0000",
    ],
    [
        "id" => 3,
        "source_kind" => "SALE_ITEM",
        "movement_type" => "CUSTOMER_RETURN",
        "quantity_before" => "5.0000",
        "quantity_after" => "6.0000",
        "original_sale_movement_id" => 1,
    ],
    [
        "id" => 4,
        "source_kind" => "INTAKE_ITEM",
        "quantity_before" => "6.0000",
        "quantity_after" => "7.0000",
    ],
    [
        "id" => 5,
        "source_kind" => "SALE_ITEM",
        "movement_type" => "SALE",
        "quantity_before" => "7.0000",
        "quantity_after" => "0.0000",
    ],
    [
        "id" => 6,
        "source_kind" => "INTAKE_ITEM",
        "quantity_before" => "0.0000",
        "quantity_after" => "2.0000",
    ],
    [
        "id" => 7,
        "source_kind" => "SALE_ITEM",
        "movement_type" => "SALE",
        "quantity_before" => "2.0000",
        "quantity_after" => "1.0000",
    ],
];
$history = new \App\Modules\Inventory\Application\CostStateReconstructor();
$before = $history->beforeStates($chain);
$after = $history->afterStates($chain);
$same($before[1], "UNESTABLISHED");
$same($before[3], "VALID");
$same($before[4], "UNESTABLISHED");
$same($before[6], "UNESTABLISHED");
$same($before[7], "VALID");
$same($after[3], "UNESTABLISHED");
$same($after[4], "UNESTABLISHED");
$same($after[6], "VALID");

$legacy = [
    ["id" => 1, "source_kind" => "MANUAL", "quantity_after" => "2.0000"],
    [
        "id" => 2,
        "source_kind" => "INTAKE_ITEM",
        "quantity_before" => "2.0000",
        "quantity_after" => "3.0000",
    ],
];
$same($history->afterStates($legacy)[2], "VALID");
$intakeReversal = [
    ...$chain,
    [
        "id" => 8,
        "source_kind" => "REVERSAL",
        "original_source_kind" => "INTAKE_ITEM",
        "reversal_of_movement_id" => 6,
        "quantity_before" => "1.0000",
        "quantity_after" => "0.0000",
    ],
];
$same($history->afterStates($intakeReversal)[8], "UNESTABLISHED");
echo "PASS: {$checks} WP4-B Return-era provenance assertions. No SQL/HTTP execution.\n";
