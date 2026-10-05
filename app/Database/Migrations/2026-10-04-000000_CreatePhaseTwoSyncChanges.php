<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreatePhaseTwoSyncChanges extends Migration
{
    public function up(): void
    {
        $this->db->query("SET time_zone = '+00:00'");
        $this->db->query(<<<'SQL'
CREATE TABLE sync_changes (
 change_sequence BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 sync_generation_id BIGINT UNSIGNED NOT NULL, tenant_id BIGINT UNSIGNED NOT NULL, shop_id BIGINT UNSIGNED NULL,
 resource_type VARCHAR(48) NOT NULL, resource_public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 change_kind VARCHAR(16) NOT NULL, changed_at DATETIME(6) NOT NULL,
 caused_by_operation_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
 correlation_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
 INDEX ix_sync_changes_tenant_sequence (tenant_id,change_sequence),
 INDEX ix_sync_changes_shop_sequence (tenant_id,shop_id,change_sequence),
 INDEX ix_sync_changes_generation_tenant_sequence (sync_generation_id,tenant_id,change_sequence),
 INDEX ix_sync_changes_resource_sequence (resource_type,resource_public_id,change_sequence),
 CONSTRAINT ck_sync_changes_kind CHECK (change_kind IN ('UPSERT','DEACTIVATE')),
 CONSTRAINT fk_sync_changes_generation FOREIGN KEY (sync_generation_id) REFERENCES sync_generations(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_sync_changes_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_sync_changes_shop FOREIGN KEY (tenant_id,shop_id) REFERENCES shops(tenant_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        $this->db->query('DROP TABLE `sync_changes`');
    }
}
