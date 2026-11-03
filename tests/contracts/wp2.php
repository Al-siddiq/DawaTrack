<?php
// Pure validation and migration/SQL parity checks. No framework boot, SQL execution or database acceptance.
declare(strict_types=1);
namespace CodeIgniter\Database {
    class Migration
    {
        public function __construct(public object $db) {}
    }
}
namespace {
    require __DIR__ . "/../../app/Exceptions/ApiException.php";
    require __DIR__ . "/../../app/Support/Uuid.php";
    require __DIR__ . "/../../app/Support/UtcTimestamp.php";
    require __DIR__ . "/../../app/Modules/Sales/Application/DraftInput.php";
    require __DIR__ .
        "/../../app/Modules/Reconciliation/Application/CashAmount.php";
    use App\Modules\Sales\Application\DraftInput;
    use App\Modules\Reconciliation\Application\CashAmount;
    use App\Exceptions\ApiException;
    $checks = 0;
    $same = static function ($actual, $expected) use (&$checks) {
        if ($actual !== $expected) {
            throw new \RuntimeException(
                "Contract mismatch: " . var_export($actual, true),
            );
        }
        $checks++;
    };
    $reject = static function (callable $action, int $status = 422) use (
        &$checks,
    ) {
        try {
            $action();
        } catch (ApiException $e) {
            if ($e->status !== $status) {
                throw $e;
            }
            $checks++;
            return;
        }
        throw new \RuntimeException("Invalid input accepted.");
    };
    $same(DraftInput::phone(" +234 (801) 234-5678 "), "+2348012345678");
    $same(DraftInput::phone(""), null);
    $same(DraftInput::phone(null), null);
    foreach (
        ["123", "+1234567890123456", "abc", "++2348012345678", [], 12345678]
        as $bad
    ) {
        $reject(fn() => DraftInput::phone($bad));
    }
    $same(DraftInput::text(" Alice ", "display_name", 160, true), "Alice");
    foreach (["", null, [], 42, str_repeat("a", 161)] as $bad) {
        $reject(fn() => DraftInput::text($bad, "display_name", 160, true));
    }
    $same(DraftInput::dueDate("2028-02-29"), "2028-02-29");
    $same(DraftInput::dueDate(""), null);
    foreach (
        [
            "0000-01-01",
            "0999-12-31",
            "2026-02-29",
            "2026-13-01",
            "2026-01-32",
            "2026-1-1",
            123,
        ]
        as $bad
    ) {
        $reject(fn() => DraftInput::dueDate($bad));
    }
    $same(
        DraftInput::occurred("2026-11-03 12:30:45.123456"),
        "2026-11-03 12:30:45.123456",
    );
    foreach (
        [
            "0000-01-01 00:00:00.000000",
            "2026-02-30 00:00:00.000000",
            "2026-11-03 12:30:45",
            "2026-11-03T12:30:45.123456Z",
        ]
        as $bad
    ) {
        $reject(fn() => DraftInput::occurred($bad));
    }
    $same(DraftInput::offset(60), 60);
    $same(DraftInput::offset(null), null);
    foreach ([841, -841, "60", 60.5] as $bad) {
        $reject(fn() => DraftInput::offset($bad));
    }
    $uuid = "01234567-89ab-4cde-8fab-0123456789ab";
    $same(DraftInput::uuid(strtoupper($uuid)), $uuid);
    $reject(fn() => DraftInput::uuid("01234567-89ab-1cde-8fab-0123456789ab"));
    $reject(fn() => DraftInput::only(["tenant_id" => 1], ["public_id"]));
    $reject(fn() => DraftInput::only(["total_amount" => "1.00"], ["quantity"]));
    $reject(fn() => DraftInput::version(["updated_at" => "a"], "b"), 409);
    $same(
        DraftInput::nextTime("9998-01-01 00:00:00.000000"),
        "9998-01-01 00:00:00.000001",
    );
    foreach (["-1", "1e2", "1.001", "999999999999999999.99", 1.5] as $bad) {
        $reject(fn() => CashAmount::parse($bad));
    }
    $same(CashAmount::parse("0.5"), "0.50");
    $pairs = [
        "100000_CreatePhaseFourCustomers" => "100000_customers",
        "100100_CreatePhaseFourSales" => "100100_sales",
        "100200_CreatePhaseFourSaleItems" => "100200_sale_items",
        "100300_CreatePhaseFourSalePayments" => "100300_sale_payments",
        "100400_CreatePhaseFourReceivables" => "100400_receivables",
        "100500_CreatePhaseFourReceivableAdjustments" =>
            "100500_receivable_adjustments",
        "100600_AddPhaseFourStockMovementSaleItemForeignKey" =>
            "100600_stock_movements_sale_item_fk",
    ];
    $normalize = static fn(string $sql) => trim(
        preg_replace("/\s+/", " ", trim($sql)),
        " ;\n\r\t",
    );
    foreach ($pairs as $migration => $sql) {
        require __DIR__ .
            "/../../app/Database/Migrations/2026-11-03-" .
            $migration .
            ".php";
        $capture = new class {
            public array $statements = [];
            public function query(string $sql): void
            {
                $this->statements[] = $sql;
            }
        };
        $class = "App\\Database\\Migrations\\" . substr($migration, 7);
        $instance = new $class($capture);
        $instance->up();
        $same(
            $normalize(implode("; ", $capture->statements)),
            $normalize(
                file_get_contents(
                    __DIR__ .
                        "/../../docs/database/sql/phase-04/20261103" .
                        $sql .
                        ".sql",
                ),
            ),
        );
        $instance->down();
        $same(
            count($capture->statements),
            str_starts_with($migration, "100600") ? 2 : 3,
        );
    }
    echo "WP2 validation and SQL-source parity: {$checks} checks passed. No MySQL statements executed.\n";
}
