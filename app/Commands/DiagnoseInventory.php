<?php

declare(strict_types=1);

namespace App\Commands;

use App\Modules\Inventory\Infrastructure\InventoryDiagnostics;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\CLI\Commands;
use Psr\Log\LoggerInterface;

final class DiagnoseInventory extends BaseCommand
{
    protected $group = 'DawaTrack';
    protected $name = 'inventory:diagnose';
    protected $description = 'Read-only Phase 3 inventory consistency diagnostics for one Tenant and Shop.';
    protected $usage = 'inventory:diagnose <tenant-public-id> <shop-public-id>';

    public function __construct(LoggerInterface $logger, Commands $commands)
    {
        parent::__construct($logger, $commands);
    }

    public function run(array $params): void
    {
        $tenantPublicId = strtolower(trim((string) ($params[0] ?? '')));
        $shopPublicId = strtolower(trim((string) ($params[1] ?? '')));
        $database = db_connect();
        $scope = $database->table('shops s')
            ->select('s.tenant_id,s.id shop_id,s.name shop_name')
            ->join('tenants t', 't.id=s.tenant_id')
            ->where(['t.public_id' => $tenantPublicId, 's.public_id' => $shopPublicId])
            ->get()
            ->getRowArray();

        if ($scope === null) {
            CLI::error('The requested Tenant/Shop scope was not found.');
            return;
        }

        CLI::write('Read-only inventory diagnostic: ' . $scope['shop_name'], 'yellow');
        $problems = 0;
        foreach ((new InventoryDiagnostics($database))->inspect((int) $scope['tenant_id'], (int) $scope['shop_id']) as $name => $rows) {
            $count = count($rows);
            $problems += $count;
            CLI::write(sprintf('%-24s %d', $name, $count), $count === 0 ? 'green' : 'red');
            foreach ($rows as $row) {
                CLI::write('  ' . json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            }
        }

        CLI::write($problems === 0 ? 'No sampled drift detected.' : "{$problems} sampled anomaly row(s) require investigation.", $problems === 0 ? 'green' : 'red');
        CLI::write('No data was changed. Do not repair projections directly.');
    }
}
