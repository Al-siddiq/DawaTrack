<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddPhaseSevenStockVarianceForeignKey extends Migration
{
    public function up(): void
    {
        $bad = $this->db->query("SELECT m.id FROM stock_movements m LEFT JOIN reconciliation_variances v ON v.tenant_id=m.tenant_id AND v.shop_id=m.shop_id AND v.id=m.reconciliation_variance_id WHERE m.reconciliation_variance_id IS NOT NULL AND (v.id IS NULL OR v.variance_kind<>'STOCK' OR m.source_kind<>'RECON_VARIANCE' OR m.movement_type<>'RECONCILIATION_ADJUSTMENT') LIMIT 1")->getRowArray();
        if ($bad) {
            throw new \RuntimeException('Stock variance links need investigation before FK installation; do not rewrite protected movements.');
        }
        $this->db->query('ALTER TABLE reconciliation_variances ADD CONSTRAINT uq_variances_scope_id UNIQUE (tenant_id,shop_id,id)');
        $this->db->query('ALTER TABLE stock_movements ADD CONSTRAINT fk_stock_movements_variance FOREIGN KEY (tenant_id,shop_id,reconciliation_variance_id) REFERENCES reconciliation_variances(tenant_id,shop_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT');
    }

    public function down(): void
    {
        if ($this->db->table('stock_movements')->where('reconciliation_variance_id IS NOT NULL', null, false)->countAllResults() > 0) {
            throw new \RuntimeException('Preserve Stock variance movement integrity; use a compatible code rollback.');
        }
        $this->db->query('ALTER TABLE stock_movements DROP FOREIGN KEY fk_stock_movements_variance');
        $this->db->query('ALTER TABLE reconciliation_variances DROP INDEX uq_variances_scope_id');
    }
}
