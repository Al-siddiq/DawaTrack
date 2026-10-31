<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreatePhaseThreeStockPositions extends Migration
{
    public function up(): void
    {
        $this->db->query("SET time_zone = '+00:00'");
        $this->db->query(<<<'SQL'
CREATE TABLE stock_positions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 tenant_id BIGINT UNSIGNED NOT NULL,
 shop_id BIGINT UNSIGNED NOT NULL,
 product_id BIGINT UNSIGNED NOT NULL,
 expected_quantity DECIMAL(19,4) NOT NULL DEFAULT 0,
 low_stock_threshold DECIMAL(19,4) NOT NULL DEFAULT 0,
 average_unit_cost DECIMAL(19,6) NOT NULL DEFAULT 0,
 cost_basis_state VARCHAR(16) NOT NULL DEFAULT 'UNESTABLISHED',
 last_movement_id BIGINT UNSIGNED NULL,
 version BIGINT UNSIGNED NOT NULL DEFAULT 0,
 created_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 CONSTRAINT uq_stock_positions_scope_product UNIQUE (tenant_id,shop_id,product_id),
 CONSTRAINT uq_stock_positions_scope_id UNIQUE (tenant_id,shop_id,id),
 INDEX ix_stock_positions_scope_quantity (tenant_id,shop_id,expected_quantity),
 CONSTRAINT ck_stock_positions_threshold CHECK (low_stock_threshold >= 0),
 CONSTRAINT ck_stock_positions_average_cost CHECK (average_unit_cost >= 0),
 CONSTRAINT ck_stock_positions_cost_state CHECK (cost_basis_state IN ('UNESTABLISHED','VALID','RETAINED')),
 CONSTRAINT fk_stock_positions_shop FOREIGN KEY (tenant_id,shop_id) REFERENCES shops(tenant_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_stock_positions_product FOREIGN KEY (tenant_id,product_id) REFERENCES products(tenant_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        $this->db->query('DROP TABLE `stock_positions`');
    }
}
