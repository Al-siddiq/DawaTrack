<?php
// Integrated read projection and diagnostic-source checks against explicit B/C doubles; no native SQL.
declare(strict_types=1);
require __DIR__ . "/wp4-c-posting.php";
require __DIR__ . "/../../app/Modules/Sales/Infrastructure/SaleQuery.php";
require __DIR__ .
    "/../../app/Modules/Sales/Infrastructure/CommercialDiagnostics.php";
class DReadBuilder extends W4Builder
{
    private array $f = [];
    private string $fields = "*";
    public function __construct(private object $db, private string $name)
    {
        parent::__construct($db, $name);
    }
    public function select(string $fields): self
    {
        $this->fields = $fields;
        return $this;
    }
    public function where(string|array $key, mixed $value = null): self
    {
        parent::where($key, $value);
        $this->f += is_array($key) ? $key : [$key => $value];
        return $this;
    }
    public function whereIn(string $key, array $values): self
    {
        $this->f[$key] = ["in" => $values];
        return $this;
    }
    public function get(): FixtureResult
    {
        $table = explode(" ", $this->name)[0];
        $rows = $this->db->state[$table] ?? [];
        $rows = array_values(
            array_filter($rows, function ($row) {
                foreach ($this->f as $k => $v) {
                    $key = substr($k, strrpos("." . $k, "."));
                    if (is_array($v) && isset($v["in"])) {
                        if (!in_array($row[$key] ?? null, $v["in"], true)) {
                            return false;
                        }
                    } elseif (($row[$key] ?? null) != $v) {
                        return false;
                    }
                }
                return true;
            }),
        );
        if ($this->fields !== "*") {
            foreach ($rows as &$row) {
                $out = [];
                foreach (explode(",", $this->fields) as $expr) {
                    $bits = explode(" ", trim($expr));
                    $name = explode(".", $bits[0]);
                    $key = end($name);
                    $out[$bits[1] ?? $key] = $row[$key] ?? null;
                }
                $row = $out;
            };
        }
        return new FixtureResult($rows);
    }
}
class DReadDB extends W4DB
{
    public function table(string $name): FixtureBuilder
    {
        return new DReadBuilder($this, $name);
    }
    public function query(string $sql, array $args = []): FixtureResult
    {
        if (str_contains($sql, "SELECT r.public_id,r.status")) {
            return new FixtureResult([]);
        }
        if (
            str_contains($sql, "DECIMAL(38,4))-CAST") &&
            str_ends_with($sql, " value")
        ) {
            return new FixtureResult([
                [
                    "value" => fixtureFormat(
                        fixtureScaled($args[0], 4) - fixtureScaled($args[1], 4),
                        4,
                    ),
                ],
            ]);
        }
        return parent::query($sql, $args);
    }
}
$checks = 0;
[$source] = w4fixture();
$db = new DReadDB();
$db->state = $source->state;
$db->state["products"][0]["tenant_id"] = 1;
$audit = new \App\Modules\Audit\Application\AuditService($db);
$access = new \App\Modules\Sales\Application\CommercialAccess($db, $audit);
$events = new \App\Modules\Sales\Application\CommercialEvents(
    $db,
    $audit,
    new \App\Modules\Sync\Application\ChangeSequenceService($db),
);
$request = new \App\Libraries\Http\RequestContext();
$sales = new \App\Modules\Sales\Application\SalePostingService(
    $db,
    $access,
    $audit,
    new \App\Modules\Sync\Application\ChangeSequenceService($db),
    $request,
);
$returns = new \App\Modules\Sales\Application\ReturnService(
    $db,
    $access,
    $events,
    $request,
);
$reverse = new \App\Modules\Sales\Application\CommercialReversalService(
    $db,
    $access,
    $events,
    $request,
);
$query = new \App\Modules\Sales\Infrastructure\SaleQuery($db, $access);
$sales->post($context, $uuid, $input);
$id = \App\Support\Uuid::v4();
$returns->create($context, $uuid, ["public_id" => $id]);
w4save($db, $returns, $id, [0 => "2"]);
$draft = $query->state($context, $id);
$same(count($draft["original_items"]), 2);
$same(isset($draft["original_items"][0]["unit_cost_snapshot"]), false);
$db->deniedPermissions = ["sale.return"];
$view = $query->state($context, $id);
$same(isset($view["original_items"]), false);
$db->deniedPermissions = [];
w4post($db, $returns, $id);
$returned = $query->state($context, $id);
$same($returned["operation_type"], "RETURN");
$same($returned["gross_profit_effect"], "-80.00");
$rev = creverse($reverse, $id);
$comp = $query->state($context, $rev);
$same($comp["operation_type"], "COMPENSATION");
$same($comp["reversal_of_public_id"], $id);
$same($comp["original_sale_public_id"], $uuid);
$same($comp["received_amount_snapshot"], null);
$same($comp["credit_created_amount_snapshot"], null);
$same($comp["gross_profit_effect"], "80.00");
$same($comp["items"][0]["cost_basis_state"], "VALID");
$same($query->state($context, $id)["reversal_public_id"], $rev);
$db->deniedPermissions = ["sale.profit.view"];
$masked = $query->state($context, $rev);
$same($masked["profit_visible"], false);
$same(isset($masked["items"][0]["unit_cost_snapshot"]), false);
$same(isset($masked["items"][0]["line_cogs_amount"]), false);
$same(isset($masked["gross_profit_effect"]), false);
$diagnostics = new \App\Modules\Sales\Infrastructure\CommercialDiagnostics($db);
$method = new ReflectionMethod($diagnostics, "checks");
$sqls = $method->invoke($diagnostics);
$same(count($sqls), 18);
foreach ($sqls as $name => $sql) {
    $same(substr_count($sql, "?"), 2);
    $same(str_contains($sql, "LIMIT 100"), true);
    $same(
        preg_match("/\b(INSERT|UPDATE|DELETE|ALTER|DROP|TRUNCATE)\b/i", $sql),
        0,
    );
}
$same(isset($sqls["missing_compensation_line"]), true);
$same(isset($sqls["adjustment_source_graph"]), true);
$same(isset($sqls["replacement_graph"]), true);
class DiagnosticReadDB extends \CodeIgniter\Database\BaseConnection
{
    public function query(string $sql, array $args = []): FixtureResult
    {
        $this->queries[] = $sql;
        return new FixtureResult([]);
    }
}
$d = new DiagnosticReadDB();
$d->state = ["sentinel" => ["unchanged"]];
$before = $d->state;
$result = (new \App\Modules\Sales\Infrastructure\CommercialDiagnostics(
    $d,
))->inspect(1, 1);
$same(count($result), 19);
$same($d->state, $before);
$same($d->commits, 1);
$same(str_contains($d->queries[0], "READ ONLY"), true);
$d->fail = "commit";
try {
    (new \App\Modules\Sales\Infrastructure\CommercialDiagnostics($d))->inspect(
        1,
        1,
    );
    throw new LogicException("Expected diagnostic commit failure");
} catch (RuntimeException $e) {
    if ($e instanceof LogicException) {
        throw $e;
    }
    $same($d->state, $before);
    $same($d->rollbacks, 1);
}
echo "PASS: {$checks} WP4-D integrated read/privacy/diagnostic-source assertions. No native SQL/HTTP execution.\n";
