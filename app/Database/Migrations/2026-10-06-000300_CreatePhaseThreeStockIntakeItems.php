<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreatePhaseThreeStockIntakeItems extends Migration
{
    public function up(): void
    {
        $this->db->query("SET time_zone = '+00:00'");
        $this->db->query(<<<'SQL'
CREATE TABLE stock_intake_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 tenant_id BIGINT UNSIGNED NOT NULL, shop_id BIGINT UNSIGNED NOT NULL, stock_intake_id BIGINT UNSIGNED NOT NULL, product_id BIGINT UNSIGNED NOT NULL,
 quantity DECIMAL(19,4) NOT NULL, unit_cost DECIMAL(19,6) NOT NULL, line_cost_amount DECIMAL(19,2) NOT NULL, line_number SMALLINT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 CONSTRAINT uq_stock_intake_items_public UNIQUE (public_id), CONSTRAINT uq_stock_intake_items_line UNIQUE (stock_intake_id,line_number),
 CONSTRAINT uq_stock_intake_items_scope_id UNIQUE (tenant_id,shop_id,id), INDEX ix_stock_intake_items_product_intake (tenant_id,shop_id,product_id,stock_intake_id),
 CONSTRAINT ck_stock_intake_items_public_uuid CHECK (public_id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 CONSTRAINT ck_stock_intake_items_quantity CHECK (quantity > 0), CONSTRAINT ck_stock_intake_items_unit_cost CHECK (unit_cost >= 0), CONSTRAINT ck_stock_intake_items_line_number CHECK (line_number > 0),
 CONSTRAINT fk_stock_intake_items_intake FOREIGN KEY (tenant_id,shop_id,stock_intake_id) REFERENCES stock_intakes(tenant_id,shop_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_stock_intake_items_product FOREIGN KEY (tenant_id,product_id) REFERENCES products(tenant_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        $this->db->query('DROP TABLE `stock_intake_items`');
    }
}
