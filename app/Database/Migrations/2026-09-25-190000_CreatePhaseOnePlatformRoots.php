<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreatePhaseOnePlatformRoots extends Migration
{
    public function up(): void
    {
        $this->db->query("SET time_zone = '+00:00'");

        $this->db->query(<<<'SQL'
CREATE TABLE sync_generations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, generation_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 status VARCHAR(12) NOT NULL, reason VARCHAR(300) NOT NULL, activated_at DATETIME(6) NOT NULL, created_at DATETIME(6) NOT NULL,
 active_guard TINYINT GENERATED ALWAYS AS (CASE WHEN status='ACTIVE' THEN 1 ELSE NULL END) STORED,
 CONSTRAINT uq_sync_generations_uuid UNIQUE (generation_uuid), CONSTRAINT uq_sync_generations_active UNIQUE (active_guard),
 CONSTRAINT ck_sync_generations_status CHECK (status IN ('ACTIVE','RETIRED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE subscription_plans (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 code VARCHAR(24) NOT NULL, name VARCHAR(80) NOT NULL, max_active_staff SMALLINT UNSIGNED NOT NULL,
 monthly_price_amount DECIMAL(19,2) NULL, currency_code CHAR(3) NOT NULL DEFAULT 'NGN', is_active BOOLEAN NOT NULL DEFAULT TRUE,
 created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 CONSTRAINT uq_subscription_plans_public UNIQUE (public_id), CONSTRAINT uq_subscription_plans_code UNIQUE (code),
 INDEX ix_subscription_plans_active_code (is_active,code),
 CONSTRAINT ck_subscription_plans_code CHECK (code IN ('TRIAL','STARTER','GROWTH')),
 CONSTRAINT ck_subscription_plans_currency CHECK (currency_code='NGN'), CONSTRAINT ck_subscription_plans_price CHECK (monthly_price_amount IS NULL OR monthly_price_amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        $this->db->query('DROP TABLE `subscription_plans`');
        $this->db->query('DROP TABLE `sync_generations`');
    }
}
