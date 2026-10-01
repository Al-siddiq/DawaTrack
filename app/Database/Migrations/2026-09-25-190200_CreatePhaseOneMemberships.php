<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreatePhaseOneMemberships extends Migration
{
    public function up(): void
    {
        $this->db->query("SET time_zone = '+00:00'");

        $this->db->query(<<<'SQL'
CREATE TABLE tenant_memberships (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, tenant_id BIGINT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL,
 role VARCHAR(24) NOT NULL, status VARCHAR(16) NOT NULL DEFAULT 'ACTIVE', activated_at DATETIME(6) NULL, deactivated_at DATETIME(6) NULL,
 created_by_user_id INT UNSIGNED NOT NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 primary_owner_guard BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN role='PRIMARY_OWNER' AND status='ACTIVE' THEN tenant_id ELSE NULL END) STORED,
 CONSTRAINT uq_memberships_public UNIQUE (public_id), CONSTRAINT uq_memberships_tenant_user UNIQUE (tenant_id,user_id), CONSTRAINT uq_memberships_tenant_id UNIQUE (tenant_id,id),
 CONSTRAINT uq_memberships_primary_owner UNIQUE (primary_owner_guard), INDEX ix_memberships_user_status (user_id,status), INDEX ix_memberships_tenant_status_role (tenant_id,status,role),
 CONSTRAINT ck_memberships_role CHECK (role IN ('PRIMARY_OWNER','STAFF')), CONSTRAINT ck_memberships_status CHECK (status IN ('INVITED','ACTIVE','DEACTIVATED')),
 CONSTRAINT fk_memberships_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_memberships_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_memberships_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE membership_permission_grants (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, membership_id BIGINT UNSIGNED NOT NULL, permission_code VARCHAR(80) NOT NULL,
 granted_by_user_id INT UNSIGNED NOT NULL, granted_at DATETIME(6) NOT NULL, revoked_by_user_id INT UNSIGNED NULL, revoked_at DATETIME(6) NULL, created_at DATETIME(6) NOT NULL,
 CONSTRAINT uq_permission_grants_event UNIQUE (membership_id,permission_code,granted_at), INDEX ix_permission_grants_scope (tenant_id,membership_id,revoked_at),
 CONSTRAINT fk_permission_grants_membership FOREIGN KEY (tenant_id,membership_id) REFERENCES tenant_memberships(tenant_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_permission_grants_granter FOREIGN KEY (granted_by_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_permission_grants_revoker FOREIGN KEY (revoked_by_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT ck_permission_grants_revocation CHECK ((revoked_at IS NULL AND revoked_by_user_id IS NULL) OR (revoked_at IS NOT NULL AND revoked_by_user_id IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        $this->db->query('DROP TABLE `membership_permission_grants`');
        $this->db->query('DROP TABLE `tenant_memberships`');
    }
}
