<?php

declare(strict_types=1);
namespace App\Commands;

use App\Modules\Sales\Infrastructure\CommercialDiagnostics;
use App\Support\Uuid;
use CodeIgniter\CLI\{BaseCommand, CLI};

final class DiagnoseSales extends BaseCommand
{
    protected $group = "DawaTrack";
    protected $name = "sales:diagnose";
    protected $description = "Read-only commercial evidence checks for one explicit Tenant and Shop.";
    protected $usage = "sales:diagnose <tenant-public-id> <shop-public-id>";

    public function run(array $params): void
    {
        $tenant = strtolower(trim((string) ($params[0] ?? "")));
        $shop = strtolower(trim((string) ($params[1] ?? "")));
        if (!Uuid::isV4($tenant) || !Uuid::isV4($shop)) {
            CLI::error(
                "Supply the Tenant and Shop public UUIDs. No all-Tenant default is allowed.",
            );
            return;
        }
        $database = db_connect();
        $scope = $database
            ->table("shops s")
            ->select("s.tenant_id,s.id shop_id,s.name shop_name")
            ->join("tenants t", "t.id=s.tenant_id")
            ->where(["t.public_id" => $tenant, "s.public_id" => $shop])
            ->get()
            ->getRowArray();
        if (!$scope) {
            CLI::error("The requested Tenant/Shop scope was not found.");
            return;
        }
        CLI::write(
            "Read-only commercial diagnostic: " . $scope["shop_name"],
            "yellow",
        );
        $problems = 0;
        foreach (
            (new CommercialDiagnostics($database))->inspect(
                (int) $scope["tenant_id"],
                (int) $scope["shop_id"],
            )
            as $name => $rows
        ) {
            $count = count($rows);
            $problems += $count;
            CLI::write(
                sprintf("%-34s %d", $name, $count),
                $count === 0 ? "green" : "red",
            );
            foreach ($rows as $row) {
                CLI::write(
                    "  " .
                        json_encode(
                            $row,
                            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                        ),
                );
            }
        }
        CLI::write(
            $problems === 0
                ? "No sampled drift detected."
                : $problems . " sampled anomaly row(s) require investigation.",
            $problems === 0 ? "green" : "red",
        );
        CLI::write(
            "Each category is limited to 100 anomalies. No data was repaired.",
        );
        CLI::write(
            "Checks sample normal and compensation totals, lineage, settlement, refund caps, replacement and historical cost-state evidence. Use inventory:diagnose for physical movement coverage. Zero findings do not certify acceptance.",
        );
        CLI::write(
            "Phase 5 payment allocations are not included in the adjusted-obligation check.",
        );
    }
}
