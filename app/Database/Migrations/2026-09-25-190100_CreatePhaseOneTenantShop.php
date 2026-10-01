<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

final class CreatePhaseOneTenantShop extends Migration
{
    public function up(): void
    {
        $this->db->query("SET time_zone = '+00:00'");
        if (! $this->db->tableExists('users')) {
            throw new RuntimeException('Shield migrations must be applied before DawaTrack Phase 1 migrations: table users is missing.');
        }

        $column = $this->db->query(
            'SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['users', 'id'],
        )->getRowArray();
        $engine = $this->db->query(
            'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            ['users'],
        )->getRowArray();

        if ($column === null || strtolower((string) $column['COLUMN_TYPE']) !== 'int unsigned' || $column['IS_NULLABLE'] !== 'NO' || $column['COLUMN_KEY'] !== 'PRI' || strcasecmp((string) ($engine['ENGINE'] ?? ''), 'InnoDB') !== 0) {
            throw new RuntimeException('Shield users.id is incompatible with DawaTrack Phase 1; expected an InnoDB PRIMARY KEY defined as INT UNSIGNED NOT NULL for Shield 1.4.1.');
        }

        $this->db->query(<<<'SQL'
CREATE TABLE tenants (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 legal_or_business_name VARCHAR(160) NOT NULL, status VARCHAR(16) NOT NULL DEFAULT 'ONBOARDING',
 activated_at DATETIME(6) NULL, suspended_at DATETIME(6) NULL, closed_at DATETIME(6) NULL, status_reason VARCHAR(500) NULL,
 retention_until DATE NULL, created_by_user_id INT UNSIGNED NOT NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 CONSTRAINT uq_tenants_public UNIQUE (public_id), INDEX ix_tenants_status_created (status,created_at),
 CONSTRAINT ck_tenants_status CHECK (status IN ('ONBOARDING','ACTIVE','SUSPENDED','CLOSED')),
 CONSTRAINT fk_tenants_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE shops (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, tenant_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(160) NOT NULL, contact_phone VARCHAR(32) NULL, contact_email VARCHAR(254) NULL, address_line_1 VARCHAR(180) NOT NULL,
 address_line_2 VARCHAR(180) NULL, city_town VARCHAR(100) NOT NULL, state_region VARCHAR(100) NOT NULL, country_code CHAR(2) NOT NULL DEFAULT 'NG',
 timezone_name VARCHAR(64) NOT NULL DEFAULT 'Africa/Lagos', currency_code CHAR(3) NOT NULL DEFAULT 'NGN', status VARCHAR(16) NOT NULL DEFAULT 'ACTIVE',
 activated_at DATETIME(6) NULL, deactivated_at DATETIME(6) NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 CONSTRAINT uq_shops_public UNIQUE (public_id), CONSTRAINT uq_shops_tenant_id UNIQUE (tenant_id,id), INDEX ix_shops_tenant_status (tenant_id,status), INDEX ix_shops_tenant_name (tenant_id,name),
 CONSTRAINT ck_shops_status CHECK (status IN ('ACTIVE','INACTIVE')), CONSTRAINT ck_shops_country CHECK (country_code='NG'), CONSTRAINT ck_shops_currency CHECK (currency_code='NGN'),
 CONSTRAINT fk_shops_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        $this->db->query('DROP TABLE `shops`');
        $this->db->query('DROP TABLE `tenants`');
    }
}
