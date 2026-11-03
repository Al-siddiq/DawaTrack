<?php

declare(strict_types=1);
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
final class CreatePhaseFourReceivables extends Migration
{
    public function up(): void
    {
        $this->db->query("SET time_zone = '+00:00'");
        $this->db->query(
            <<<'SQL'
            CREATE TABLE receivables (
             id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
             tenant_id BIGINT UNSIGNED NOT NULL, shop_id BIGINT UNSIGNED NOT NULL, customer_id BIGINT UNSIGNED NOT NULL, sale_id BIGINT UNSIGNED NOT NULL,
             original_amount DECIMAL(19,2) NOT NULL, due_date DATE NULL, occurred_at DATETIME(6) NOT NULL, posted_at DATETIME(6) NOT NULL,
             created_by_user_id INT UNSIGNED NOT NULL, created_at DATETIME(6) NOT NULL,
             CONSTRAINT uq_receivables_public UNIQUE (public_id), CONSTRAINT uq_receivables_scope UNIQUE (tenant_id,shop_id,id),
             CONSTRAINT ck_receivables_uuid CHECK (public_id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'), CONSTRAINT uq_receivables_sale UNIQUE (sale_id),
             INDEX ix_receivables_customer (tenant_id,shop_id,customer_id,posted_at,id), INDEX ix_receivables_due (tenant_id,shop_id,due_date),
             CONSTRAINT ck_receivables_amount CHECK (original_amount>0), CONSTRAINT fk_receivables_sale FOREIGN KEY (tenant_id,shop_id,sale_id) REFERENCES sales(tenant_id,shop_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT, CONSTRAINT fk_receivables_customer FOREIGN KEY (tenant_id,customer_id) REFERENCES customers(tenant_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT, CONSTRAINT fk_receivables_created_by_user_id FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL
            ,
        );
    }
    public function down(): void
    {
        $this->db->query("DROP TABLE receivables");
    }
}
