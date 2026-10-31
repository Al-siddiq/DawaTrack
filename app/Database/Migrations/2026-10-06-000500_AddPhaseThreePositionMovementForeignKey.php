<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddPhaseThreePositionMovementForeignKey extends Migration
{
    public function up(): void
    {
        $this->db->query("SET time_zone = '+00:00'");
        $this->db->query('ALTER TABLE stock_positions ADD CONSTRAINT fk_stock_positions_last_movement FOREIGN KEY (tenant_id,shop_id,last_movement_id) REFERENCES stock_movements(tenant_id,shop_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE stock_positions DROP FOREIGN KEY fk_stock_positions_last_movement');
    }
}
