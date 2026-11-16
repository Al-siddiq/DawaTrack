<?php

declare(strict_types=1);

namespace App\Modules\Reconciliation\Infrastructure;

use App\Exceptions\ApiException;
use CodeIgniter\Database\BaseConnection;

final class StockControlSchema
{
    public function __construct(private readonly BaseConnection $database) {}

    public function requireReady(): void
    {
        (new CashControlSchema($this->database))->requireReady();
        $row = $this->database->query("SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='stock_movements' AND CONSTRAINT_NAME='fk_stock_movements_variance' AND REFERENCED_TABLE_NAME='reconciliation_variances'")->getRowArray();
        if (!$row) {
            throw new ApiException('STOCK_CONTROL_SETUP_REQUIRED', 'Stock control setup is incomplete. Ask the administrator to check the deferred variance FK migration.', 503);
        }
    }
}
