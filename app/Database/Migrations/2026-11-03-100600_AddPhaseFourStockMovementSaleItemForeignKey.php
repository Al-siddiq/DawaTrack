<?php

declare(strict_types=1);
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
final class AddPhaseFourStockMovementSaleItemForeignKey extends Migration
{
    public function up(): void
    {
        $this->db->query(
            <<<'SQL'
            ALTER TABLE stock_movements ADD CONSTRAINT fk_stock_movements_sale_item FOREIGN KEY (tenant_id,shop_id,sale_item_id) REFERENCES sale_items(tenant_id,shop_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT
            SQL
            ,
        );
    }
    public function down(): void
    {
        $this->db->query(
            "ALTER TABLE stock_movements DROP FOREIGN KEY fk_stock_movements_sale_item",
        );
    }
}
