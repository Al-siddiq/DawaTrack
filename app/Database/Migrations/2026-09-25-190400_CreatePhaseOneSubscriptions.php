<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreatePhaseOneSubscriptions extends Migration
{
    public function up(): void
    {
        $this->db->query("SET time_zone = '+00:00'");

        $this->db->query(<<<'SQL'
CREATE TABLE subscriptions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, tenant_id BIGINT UNSIGNED NOT NULL, subscription_plan_id BIGINT UNSIGNED NOT NULL,
 entitlement_type VARCHAR(12) NOT NULL, starts_at DATETIME(6) NOT NULL, ends_at DATETIME(6) NOT NULL, plan_code_snapshot VARCHAR(24) NOT NULL,
 max_active_staff_snapshot SMALLINT UNSIGNED NOT NULL, price_amount_snapshot DECIMAL(19,2) NOT NULL, currency_code CHAR(3) NOT NULL DEFAULT 'NGN', external_reference VARCHAR(120) NULL,
 supersedes_subscription_id BIGINT UNSIGNED NULL, created_by_user_id INT UNSIGNED NOT NULL, reason VARCHAR(500) NULL, created_at DATETIME(6) NOT NULL,
 CONSTRAINT uq_subscriptions_public UNIQUE (public_id), CONSTRAINT uq_subscriptions_scope_id UNIQUE (tenant_id,id), INDEX ix_subscriptions_period (tenant_id,starts_at,ends_at), INDEX ix_subscriptions_type_end (entitlement_type,ends_at),
 CONSTRAINT ck_subscriptions_type CHECK (entitlement_type IN ('TRIAL','PAID')), CONSTRAINT ck_subscriptions_period CHECK (ends_at>starts_at), CONSTRAINT ck_subscriptions_currency CHECK (currency_code='NGN'), CONSTRAINT ck_subscriptions_price CHECK (price_amount_snapshot>=0),
 CONSTRAINT fk_subscriptions_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_subscriptions_plan FOREIGN KEY (subscription_plan_id) REFERENCES subscription_plans(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_subscriptions_previous FOREIGN KEY (supersedes_subscription_id) REFERENCES subscriptions(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_subscriptions_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE subscription_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, tenant_id BIGINT UNSIGNED NOT NULL, subscription_id BIGINT UNSIGNED NOT NULL,
 event_type VARCHAR(24) NOT NULL, previous_subscription_id BIGINT UNSIGNED NULL, effective_at DATETIME(6) NOT NULL, external_reference VARCHAR(120) NULL, reason VARCHAR(500) NULL,
 actor_user_id INT UNSIGNED NOT NULL, created_at DATETIME(6) NOT NULL,
 CONSTRAINT uq_subscription_events_public UNIQUE (public_id), INDEX ix_subscription_events_tenant_time (tenant_id,effective_at,id), INDEX ix_subscription_events_type (subscription_id,event_type),
 CONSTRAINT ck_subscription_events_type CHECK (event_type IN ('CREATED','RENEWED','UPGRADED','DOWNGRADED','EXTENDED','EXPIRED')),
 CONSTRAINT fk_subscription_events_subscription FOREIGN KEY (tenant_id,subscription_id) REFERENCES subscriptions(tenant_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_subscription_events_previous FOREIGN KEY (previous_subscription_id) REFERENCES subscriptions(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_subscription_events_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        $this->db->query('DROP TABLE `subscription_events`');
        $this->db->query('DROP TABLE `subscriptions`');
    }
}
