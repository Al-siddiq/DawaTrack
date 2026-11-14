<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreatePhaseSixFinancialMovements extends Migration
{
    public function up(): void
    {
        $this->db->query("SET time_zone = '+00:00'");
        $this->db->query(<<<'SQL'
CREATE TABLE financial_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    tenant_id BIGINT UNSIGNED NOT NULL,
    shop_id BIGINT UNSIGNED NOT NULL,
    movement_type VARCHAR(32) NOT NULL,
    channel VARCHAR(12) NOT NULL,
    amount_delta DECIMAL(19,2) NOT NULL,
    description VARCHAR(300) NOT NULL,
    external_reference VARCHAR(120) NULL,
    reversal_of_movement_id BIGINT UNSIGNED NULL,
    replacement_for_movement_id BIGINT UNSIGNED NULL,
    occurred_at DATETIME(6) NOT NULL,
    posted_at DATETIME(6) NOT NULL,
    created_by_user_id INT UNSIGNED NOT NULL,
    correlation_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    created_at DATETIME(6) NOT NULL,
    CONSTRAINT uq_financial_movements_public UNIQUE (public_id),
    CONSTRAINT uq_financial_movements_scope UNIQUE (tenant_id,shop_id,id),
    CONSTRAINT uq_financial_movements_reversal UNIQUE (reversal_of_movement_id),
    INDEX ix_financial_movements_channel (tenant_id,shop_id,channel,posted_at,id),
    INDEX ix_financial_movements_type (tenant_id,shop_id,movement_type,posted_at),
    CONSTRAINT ck_financial_movements_uuid CHECK (public_id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[a-f0-9]{12}$'),
    CONSTRAINT ck_financial_movements_channel CHECK (channel IN ('CASH','TRANSFER')),
    CONSTRAINT ck_financial_movements_type CHECK (movement_type IN ('SHOP_EXPENSE','OWNER_WITHDRAWAL','OWNER_INTRODUCTION','FINANCIAL_CORRECTION','REVERSAL')),
    CONSTRAINT ck_financial_movements_amount CHECK (amount_delta<>0),
    CONSTRAINT ck_financial_movements_sign CHECK ((movement_type IN ('SHOP_EXPENSE','OWNER_WITHDRAWAL') AND amount_delta<0) OR (movement_type='OWNER_INTRODUCTION' AND amount_delta>0) OR movement_type IN ('FINANCIAL_CORRECTION','REVERSAL')),
    CONSTRAINT ck_financial_movements_description CHECK (CHAR_LENGTH(TRIM(description)) BETWEEN 1 AND 300),
    CONSTRAINT ck_financial_movements_links CHECK ((movement_type='REVERSAL' AND reversal_of_movement_id IS NOT NULL AND replacement_for_movement_id IS NULL) OR (movement_type='FINANCIAL_CORRECTION' AND replacement_for_movement_id IS NOT NULL AND reversal_of_movement_id IS NULL) OR (movement_type IN ('SHOP_EXPENSE','OWNER_WITHDRAWAL','OWNER_INTRODUCTION') AND reversal_of_movement_id IS NULL AND replacement_for_movement_id IS NULL)),
    CONSTRAINT fk_financial_movements_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_financial_movements_shop FOREIGN KEY (tenant_id,shop_id) REFERENCES shops(tenant_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_financial_movements_actor FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_financial_movements_reversal FOREIGN KEY (tenant_id,shop_id,reversal_of_movement_id) REFERENCES financial_movements(tenant_id,shop_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_financial_movements_replacement FOREIGN KEY (tenant_id,shop_id,replacement_for_movement_id) REFERENCES financial_movements(tenant_id,shop_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        if ($this->db->table('financial_movements')->countAllResults() > 0) {
            throw new \RuntimeException('Refusing to remove protected financial movement evidence. Use a data-compatible code rollback.');
        }
        $this->db->query('DROP TABLE financial_movements');
    }
}
