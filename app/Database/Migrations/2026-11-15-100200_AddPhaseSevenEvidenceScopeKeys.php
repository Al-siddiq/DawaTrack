<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddPhaseSevenEvidenceScopeKeys extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
ALTER TABLE reconciliation_financial_counts ADD CONSTRAINT uq_financial_counts_control_source UNIQUE (tenant_id,shop_id,reconciliation_session_id,id)
SQL);
        $this->db->query(<<<'SQL'
ALTER TABLE registered_devices ADD CONSTRAINT uq_registered_devices_scope_id UNIQUE (tenant_id,shop_id,id)
SQL);
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE registered_devices DROP INDEX uq_registered_devices_scope_id');
        $this->db->query('ALTER TABLE reconciliation_financial_counts DROP INDEX uq_financial_counts_control_source');
    }
}
