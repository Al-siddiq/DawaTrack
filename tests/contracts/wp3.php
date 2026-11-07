<?php
// Pure exact-money and historical cost-state checks. No framework, database, or HTTP acceptance.
declare(strict_types=1);
require __DIR__ . "/../../app/Exceptions/ApiException.php";
require __DIR__ .
    "/../../app/Modules/Reconciliation/Application/CashAmount.php";
require __DIR__ . "/../../app/Modules/Sales/Application/SaleMoney.php";
require __DIR__ .
    "/../../app/Modules/Inventory/Application/CostStateReconstructor.php";
require __DIR__ .
    "/../../app/Modules/Inventory/Application/InventoryProjection.php";
use App\Modules\Sales\Application\SaleMoney;
use App\Modules\Inventory\Application\CostStateReconstructor;
use App\Exceptions\ApiException;
$checks = 0;
$same = static function ($actual, $expected) use (&$checks) {
    if ($actual !== $expected) {
        throw new RuntimeException(
            "Mismatch: " . var_export([$actual, $expected], true),
        );
    }
    $checks++;
};
$reject = static function (callable $action) use (&$checks) {
    try {
        $action();
    } catch (ApiException) {
        $checks++;
        return;
    }
    throw new RuntimeException("Invalid amount accepted.");
};
$same(SaleMoney::sum([]), "0.00");
$same(SaleMoney::sum(["0.01", "0.09", "9.90"]), "10.00");
$same(SaleMoney::sum(["90071992547409920.00", "0.01"]), "90071992547409920.01");
$same(
    SaleMoney::difference("99999999999999999.99", "0.01"),
    "99999999999999999.98",
);
$same(
    SaleMoney::difference("0.00", "99999999999999999.99"),
    "-99999999999999999.99",
);
$same(SaleMoney::difference("10.00", "10.00"), "0.00");
$same(SaleMoney::difference("1000.00", "0.01"), "999.99");
foreach (
    [
        ["20000.00", "0.00", "0.00", "0.00"],
        ["0.00", "20000.00", "0.00", "0.00"],
        ["5000.00", "15000.00", "0.00", "0.00"],
        ["0.00", "0.00", "0.00", "20000.00"],
        ["5000.00", "0.00", "0.00", "15000.00"],
        ["0.00", "5000.00", "0.00", "15000.00"],
        ["5000.00", "5000.00", "0.00", "10000.00"],
    ]
    as [$cash, $transfer, $unused, $credit]
) {
    $actual = SaleMoney::composition("20000.00", $cash, $transfer);
    $same($actual["credit_amount"], $credit);
    $same(SaleMoney::sum([$actual["paid_amount"], $credit]), "20000.00");
}
$reject(fn() => SaleMoney::sum(["99999999999999999.99", "0.01"]));
$reject(fn() => SaleMoney::composition("100.00", "100.00", "0.01"));
foreach (["-1", "1e2", "1.001", 1.5, [], null] as $bad) {
    $reject(fn() => SaleMoney::composition("100.00", $bad, "0"));
}
$format = static fn(int $cents) => intdiv($cents, 100) .
    "." .
    str_pad((string) ($cents % 100), 2, "0", STR_PAD_LEFT);
mt_srand(4403);
for ($i = 0; $i < 1000; $i++) {
    $a = mt_rand(0, 100000000);
    $b = mt_rand(0, 100000000);
    $same(SaleMoney::sum([$format($a), $format($b)]), $format($a + $b));
    $same(
        SaleMoney::difference($format($a), $format($b)),
        ($a < $b ? "-" : "") . $format(abs($a - $b)),
    );
}
$chain = [
    [
        "id" => 1,
        "source_kind" => "SALE_ITEM",
        "quantity_after" => "-2.0000",
        "reversal_of_movement_id" => null,
    ],
    [
        "id" => 2,
        "source_kind" => "INTAKE_ITEM",
        "quantity_after" => "3.0000",
        "reversal_of_movement_id" => null,
    ],
    [
        "id" => 3,
        "source_kind" => "SALE_ITEM",
        "quantity_after" => "0.0000",
        "reversal_of_movement_id" => null,
    ],
    [
        "id" => 4,
        "source_kind" => "SALE_ITEM",
        "quantity_after" => "-1.0000",
        "reversal_of_movement_id" => null,
    ],
    [
        "id" => 5,
        "source_kind" => "INTAKE_ITEM",
        "quantity_after" => "5.0000",
        "reversal_of_movement_id" => null,
    ],
    [
        "id" => 6,
        "source_kind" => "SALE_ITEM",
        "quantity_after" => "4.0000",
        "reversal_of_movement_id" => null,
    ],
];
$same((new CostStateReconstructor())->beforeStates($chain), [
    1 => "UNESTABLISHED",
    2 => "UNESTABLISHED",
    3 => "VALID",
    4 => "RETAINED",
    5 => "RETAINED",
    6 => "VALID",
]);
// Later acquisitions cannot retroactively establish the basis of movement 1.
$same(
    (new CostStateReconstructor())->beforeStates(array_slice($chain, 0, 1))[1],
    "UNESTABLISHED",
);
echo "PASS: {$checks} exact-money/composition/historical-state checks. No MySQL/HTTP execution.\n";
