<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreatePhaseOneBrandingDomains extends Migration
{
    public function up(): void
    {
        $this->db->query("SET time_zone = '+00:00'");

        $this->db->query(<<<'SQL'
CREATE TABLE shop_branding_profiles (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, tenant_id BIGINT UNSIGNED NOT NULL, shop_id BIGINT UNSIGNED NOT NULL,
 brand_display_name VARCHAR(160) NOT NULL, tagline VARCHAR(180) NULL, public_description VARCHAR(500) NULL,
 primary_colour CHAR(7) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, secondary_colour CHAR(7) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, accent_colour CHAR(7) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 theme_colour CHAR(7) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, background_colour CHAR(7) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 pwa_full_name VARCHAR(80) NOT NULL, pwa_short_name VARCHAR(24) NOT NULL, application_title VARCHAR(100) NOT NULL,
 configuration_version BIGINT UNSIGNED NOT NULL DEFAULT 1, updated_by_user_id INT UNSIGNED NOT NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 CONSTRAINT uq_brand_profiles_public UNIQUE (public_id), CONSTRAINT uq_brand_profiles_shop UNIQUE (tenant_id,shop_id), CONSTRAINT uq_brand_profiles_scope_id UNIQUE (tenant_id,shop_id,id),
 CONSTRAINT fk_brand_profiles_shop FOREIGN KEY (tenant_id,shop_id) REFERENCES shops(tenant_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_brand_profiles_updater FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT ck_brand_profiles_colours CHECK (primary_colour REGEXP '^#[0-9A-Fa-f]{6}$' AND secondary_colour REGEXP '^#[0-9A-Fa-f]{6}$' AND accent_colour REGEXP '^#[0-9A-Fa-f]{6}$' AND theme_colour REGEXP '^#[0-9A-Fa-f]{6}$' AND background_colour REGEXP '^#[0-9A-Fa-f]{6}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE shop_brand_assets (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, tenant_id BIGINT UNSIGNED NOT NULL, shop_id BIGINT UNSIGNED NOT NULL,
 asset_type VARCHAR(24) NOT NULL, storage_key VARCHAR(500) NOT NULL, mime_type VARCHAR(80) NOT NULL, width_px SMALLINT UNSIGNED NOT NULL, height_px SMALLINT UNSIGNED NOT NULL,
 file_size_bytes BIGINT UNSIGNED NOT NULL, content_sha256 BINARY(32) NOT NULL, is_active BOOLEAN NOT NULL DEFAULT TRUE, uploaded_by_user_id INT UNSIGNED NOT NULL,
 activated_at DATETIME(6) NOT NULL, deactivated_at DATETIME(6) NULL, created_at DATETIME(6) NOT NULL,
 active_type_guard VARCHAR(24) GENERATED ALWAYS AS (CASE WHEN is_active=1 THEN asset_type ELSE NULL END) STORED,
 CONSTRAINT uq_brand_assets_public UNIQUE (public_id), CONSTRAINT uq_brand_assets_storage UNIQUE (storage_key), CONSTRAINT uq_brand_assets_scope_id UNIQUE (tenant_id,shop_id,id),
 CONSTRAINT uq_brand_assets_active UNIQUE (tenant_id,shop_id,active_type_guard), INDEX ix_brand_assets_lookup (tenant_id,shop_id,asset_type,is_active),
 CONSTRAINT ck_brand_assets_type CHECK (asset_type IN ('LOGO','FAVICON','PWA_ICON_192','PWA_ICON_512','LOGIN_BACKGROUND')),
 CONSTRAINT fk_brand_assets_shop FOREIGN KEY (tenant_id,shop_id) REFERENCES shops(tenant_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_brand_assets_uploader FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE shop_domains (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, tenant_id BIGINT UNSIGNED NOT NULL, shop_id BIGINT UNSIGNED NOT NULL,
 hostname VARCHAR(253) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, domain_type VARCHAR(24) NOT NULL, verification_status VARCHAR(16) NOT NULL DEFAULT 'PENDING',
 is_active BOOLEAN NOT NULL DEFAULT FALSE, is_primary BOOLEAN NOT NULL DEFAULT FALSE, verification_method VARCHAR(16) NULL, verification_token_hash BINARY(32) NULL,
 verification_reference VARCHAR(200) NULL, certificate_status VARCHAR(16) NULL, verified_at DATETIME(6) NULL, activated_at DATETIME(6) NULL, deactivated_at DATETIME(6) NULL,
 created_by_admin_user_id INT UNSIGNED NULL, verified_by_admin_user_id INT UNSIGNED NULL, updated_by_user_id INT UNSIGNED NOT NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 primary_guard BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN is_active=1 AND is_primary=1 THEN shop_id ELSE NULL END) STORED,
 CONSTRAINT uq_shop_domains_hostname UNIQUE (hostname), CONSTRAINT uq_shop_domains_public UNIQUE (public_id), CONSTRAINT uq_shop_domains_scope_id UNIQUE (tenant_id,shop_id,id), CONSTRAINT uq_shop_domains_primary UNIQUE (tenant_id,primary_guard),
 INDEX ix_shop_domains_scope_state (tenant_id,shop_id,is_active,is_primary),
 CONSTRAINT ck_shop_domains_type CHECK (domain_type IN ('PLATFORM_SUBDOMAIN','CUSTOM')), CONSTRAINT ck_shop_domains_verification CHECK (verification_status IN ('PENDING','VERIFIED','FAILED')),
 CONSTRAINT ck_shop_domains_method CHECK (verification_method IS NULL OR verification_method IN ('DNS_TXT','DNS_CNAME','PLATFORM')),
 CONSTRAINT ck_shop_domains_certificate CHECK (certificate_status IS NULL OR certificate_status IN ('PENDING','ACTIVE','FAILED')),
 CONSTRAINT ck_shop_domains_activation CHECK (is_active=0 OR (verification_status='VERIFIED' AND certificate_status='ACTIVE')),
 CONSTRAINT fk_shop_domains_shop FOREIGN KEY (tenant_id,shop_id) REFERENCES shops(tenant_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_shop_domains_creator FOREIGN KEY (created_by_admin_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_shop_domains_verifier FOREIGN KEY (verified_by_admin_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_shop_domains_updater FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        $this->db->query('DROP TABLE `shop_domains`');
        $this->db->query('DROP TABLE `shop_brand_assets`');
        $this->db->query('DROP TABLE `shop_branding_profiles`');
    }
}
