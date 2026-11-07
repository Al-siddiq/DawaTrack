<?php
// Pure effective-suffix and source-state proof; no native database/HTTP execution.
declare(strict_types=1);
namespace CodeIgniter\Database {
    class BaseConnection {}
}
namespace {
    require __DIR__ . "/../../app/Exceptions/ApiException.php";
    require __DIR__ .
        "/../../app/Modules/Inventory/Application/CostStateReconstructor.php";
    require __DIR__ .
        "/../../app/Modules/Inventory/Application/CommercialStockParticipant.php";
    $checks = 0;
    $same = static function ($a, $b) use (&$checks) {
        if ($a !== $b) {
            throw new \RuntimeException("Effective suffix mismatch");
        }
        $checks++;
    };
    $pair = [
        [
            "id" => 1,
            "source_kind" => "INTAKE_ITEM",
            "quantity_before" => "0.0000",
            "quantity_after" => "5.0000",
            "average_cost_before" => "0.000000",
            "average_cost_after" => "60.000000",
        ],
        [
            "id" => 2,
            "source_kind" => "SALE_ITEM",
            "movement_type" => "SALE",
            "quantity_before" => "5.0000",
            "quantity_after" => "3.0000",
            "average_cost_before" => "60.000000",
            "average_cost_after" => "60.000000",
        ],
        [
            "id" => 3,
            "source_kind" => "SALE_ITEM",
            "movement_type" => "CUSTOMER_RETURN",
            "original_sale_movement_id" => 2,
            "quantity_before" => "3.0000",
            "quantity_after" => "4.0000",
            "average_cost_before" => "60.000000",
            "average_cost_after" => "60.000000",
        ],
        [
            "id" => 4,
            "source_kind" => "REVERSAL",
            "original_source_kind" => "SALE_ITEM",
            "reversal_of_movement_id" => 3,
            "quantity_before" => "4.0000",
            "quantity_after" => "3.0000",
            "average_cost_before" => "60.000000",
            "average_cost_after" => "60.000000",
        ],
    ];
    $stock = new \App\Modules\Inventory\Application\CommercialStockParticipant(
        new \CodeIgniter\Database\BaseConnection(),
    );
    $position = [
        "expected_quantity" => "3.0000",
        "average_unit_cost" => "60.000000",
        "cost_basis_state" => "VALID",
        "last_movement_id" => 4,
    ];
    $stock->assertLatest($pair, [$pair[1]], $position);
    $checks++;
    foreach (["average", "quantity", "state", "pointer", "later"] as $case) {
        $bad = $pair;
        $p = $position;
        if ($case === "average") {
            $bad[3]["average_cost_after"] = "59.000000";
        }
        if ($case === "quantity") {
            $bad[3]["quantity_after"] = "4.0000";
        }
        if ($case === "state") {
            $p["cost_basis_state"] = "UNESTABLISHED";
        }
        if ($case === "pointer") {
            $p["last_movement_id"] = 3;
        }
        if ($case === "later") {
            $bad[] = [
                "id" => 5,
                "source_kind" => "MANUAL",
                "movement_type" => "CORRECTION",
                "quantity_before" => "3.0000",
                "quantity_after" => "4.0000",
                "average_cost_before" => "60.000000",
                "average_cost_after" => "60.000000",
            ];
        }
        try {
            $stock->assertLatest($bad, [$bad[1]], $p);
            throw new \LogicException("Unsafe suffix accepted: " . $case);
        } catch (\App\Exceptions\ApiException) {
            $checks++;
        }
    }
    $same(
        (new \App\Modules\Inventory\Application\CostStateReconstructor())->afterStates(
            $pair,
        )[4],
        "VALID",
    );
    echo "PASS: {$checks} WP4-C effective-suffix assertions. No SQL/HTTP execution.\n";
}
