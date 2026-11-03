<?php

declare(strict_types=1);
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
final class CreatePhaseFourSalePayments extends Migration
{
    public function up(): void
    {
        $this->db->query("SET time_zone = '+00:00'");
        $this->db->query(
            <<<'SQL'
            CREATE TABLE sale_payments (
             id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
             tenant_id BIGINT UNSIGNED NOT NULL, shop_id BIGINT UNSIGNED NOT NULL, sale_id BIGINT UNSIGNED NOT NULL,
             direction VARCHAR(12) NOT NULL DEFAULT 'RECEIPT', channel VARCHAR(12) NOT NULL, amount DECIMAL(19,2) NOT NULL,
             external_reference VARCHAR(120) NULL, occurred_at DATETIME(6) NOT NULL, posted_at DATETIME(6) NOT NULL,
             created_by_user_id INT UNSIGNED NOT NULL, reversal_of_payment_id BIGINT UNSIGNED NULL,
             correlation_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL, created_at DATETIME(6) NOT NULL,
             CONSTRAINT uq_sale_payments_public UNIQUE (public_id), CONSTRAINT uq_sale_payments_scope UNIQUE (tenant_id,shop_id,id),
             CONSTRAINT ck_sale_payments_uuid CHECK (public_id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'), CONSTRAINT uq_sale_payments_reversal UNIQUE (reversal_of_payment_id),
             INDEX ix_sale_payments_channel (tenant_id,shop_id,channel,posted_at), INDEX ix_sale_payments_sale (tenant_id,sale_id),
             CONSTRAINT ck_sale_payments_channel CHECK (channel IN ('CASH','TRANSFER')), CONSTRAINT ck_sale_payments_direction CHECK (direction IN ('RECEIPT','REFUND')), CONSTRAINT ck_sale_payments_amount CHECK (amount>0),
             CONSTRAINT fk_sale_payments_sale FOREIGN KEY (tenant_id,shop_id,sale_id) REFERENCES sales(tenant_id,shop_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT, CONSTRAINT fk_sale_payments_created_by_user_id FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
             CONSTRAINT fk_sale_payments_reversal FOREIGN KEY (tenant_id,shop_id,reversal_of_payment_id) REFERENCES sale_payments(tenant_id,shop_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL
            ,
        );
    }
    public function down(): void
    {
        $this->db->query("DROP TABLE sale_payments");
    }
}
