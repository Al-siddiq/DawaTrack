<?php

declare(strict_types=1);

namespace App\Modules\Reconciliation\Infrastructure;

use App\Exceptions\ApiException;
use App\Modules\Device\Infrastructure\DeviceSchema;
use CodeIgniter\Database\BaseConnection;

final class CashControlSchema
{
    public function __construct(private readonly BaseConnection $database) {}

    public function requireReady(): void
    {
        (new DeviceSchema($this->database))->requireReady();
        foreach (['reconciliation_stock_counts', 'reconciliation_variances', 'reconciliation_variance_notes',
            'reconciliation_staff_contexts', 'reconciliation_device_acknowledgements'] as $table) {
            if (!$this->database->tableExists($table)) {
                $this->missing();
            }
        }
        $rows = $this->database->query(
            "SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME IN ('fk_variances_cash_count','fk_variances_stock_count','fk_variance_notes_source','fk_staff_context_control','fk_staff_context_member','fk_device_ack_device','fk_device_ack_control','fk_stock_counts_control')",
        )->getResultArray();
        if (count($rows) !== 8) {
            $this->missing();
        }
    }

    private function missing(): never
    {
        throw new ApiException('CASH_CONTROL_SETUP_REQUIRED', 'Cash control setup is incomplete. Ask the administrator to check the approved migrations.', 503);
    }
}
