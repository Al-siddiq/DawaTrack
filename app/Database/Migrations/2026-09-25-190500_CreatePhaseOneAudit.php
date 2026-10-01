<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreatePhaseOneAudit extends Migration
{
    public function up(): void
    {
        $this->db->query("SET time_zone = '+00:00'");

        $this->db->query(<<<'SQL'
CREATE TABLE audit_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, tenant_id BIGINT UNSIGNED NULL, shop_id BIGINT UNSIGNED NULL,
 actor_type VARCHAR(12) NOT NULL, actor_user_id INT UNSIGNED NULL, actor_membership_id BIGINT UNSIGNED NULL, actor_device_id BIGINT UNSIGNED NULL,
 action_code VARCHAR(80) NOT NULL, category VARCHAR(24) NOT NULL, resource_type VARCHAR(48) NOT NULL, resource_public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
 reason VARCHAR(500) NULL, change_metadata JSON NULL, caused_by_audit_event_id BIGINT UNSIGNED NULL, correlation_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
 sync_operation_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL, ip_address VARBINARY(16) NULL, user_agent_hash BINARY(32) NULL, occurred_at DATETIME(6) NOT NULL, created_at DATETIME(6) NOT NULL,
 CONSTRAINT uq_audit_events_public UNIQUE (public_id), INDEX ix_audit_events_scope_time (tenant_id,shop_id,occurred_at,id), INDEX ix_audit_events_resource (tenant_id,resource_type,resource_public_id,occurred_at),
 INDEX ix_audit_events_actor (actor_user_id,occurred_at), INDEX ix_audit_events_correlation (correlation_id), INDEX ix_audit_events_sync_operation (sync_operation_id), INDEX ix_audit_events_category (category,occurred_at),
 CONSTRAINT ck_audit_events_actor CHECK ((actor_type='USER' AND actor_user_id IS NOT NULL) OR (actor_type='SYSTEM' AND actor_user_id IS NULL)),
 CONSTRAINT ck_audit_events_category CHECK (category IN ('AUTHORIZATION','TENANT','SUBSCRIPTION','INVENTORY','SALE','DEBT','FINANCIAL','RECONCILIATION','SYNC','SUPPORT','SECURITY')),
 CONSTRAINT fk_audit_events_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_audit_events_shop FOREIGN KEY (tenant_id,shop_id) REFERENCES shops(tenant_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_audit_events_actor_user FOREIGN KEY (actor_user_id) REFERENCES users(id),
 CONSTRAINT fk_audit_events_membership FOREIGN KEY (actor_membership_id) REFERENCES tenant_memberships(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_audit_events_cause FOREIGN KEY (caused_by_audit_event_id) REFERENCES audit_events(id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        $this->db->query('DROP TABLE `audit_events`');
    }
}
