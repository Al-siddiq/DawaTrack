SET time_zone = '+00:00';

CREATE TABLE sync_generations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, generation_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 status VARCHAR(12) NOT NULL, reason VARCHAR(300) NOT NULL, activated_at DATETIME(6) NOT NULL, created_at DATETIME(6) NOT NULL,
 active_guard TINYINT GENERATED ALWAYS AS (CASE WHEN status='ACTIVE' THEN 1 ELSE NULL END) STORED,
 CONSTRAINT uq_sync_generations_uuid UNIQUE (generation_uuid), CONSTRAINT uq_sync_generations_active UNIQUE (active_guard),
 CONSTRAINT ck_sync_generations_status CHECK (status IN ('ACTIVE','RETIRED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE subscription_plans (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 code VARCHAR(24) NOT NULL, name VARCHAR(80) NOT NULL, max_active_staff SMALLINT UNSIGNED NOT NULL,
 monthly_price_amount DECIMAL(19,2) NULL, currency_code CHAR(3) NOT NULL DEFAULT 'NGN', is_active BOOLEAN NOT NULL DEFAULT TRUE,
 created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 CONSTRAINT uq_subscription_plans_public UNIQUE (public_id), CONSTRAINT uq_subscription_plans_code UNIQUE (code),
 INDEX ix_subscription_plans_active_code (is_active,code),
 CONSTRAINT ck_subscription_plans_code CHECK (code IN ('TRIAL','STARTER','GROWTH')),
 CONSTRAINT ck_subscription_plans_currency CHECK (currency_code='NGN'), CONSTRAINT ck_subscription_plans_price CHECK (monthly_price_amount IS NULL OR monthly_price_amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE tenants (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 legal_or_business_name VARCHAR(160) NOT NULL, status VARCHAR(16) NOT NULL DEFAULT 'ONBOARDING',
 activated_at DATETIME(6) NULL, suspended_at DATETIME(6) NULL, closed_at DATETIME(6) NULL, status_reason VARCHAR(500) NULL,
 retention_until DATE NULL, created_by_user_id BIGINT UNSIGNED NOT NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 CONSTRAINT uq_tenants_public UNIQUE (public_id), INDEX ix_tenants_status_created (status,created_at),
 CONSTRAINT ck_tenants_status CHECK (status IN ('ONBOARDING','ACTIVE','SUSPENDED','CLOSED')),
 CONSTRAINT fk_tenants_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE shops (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, tenant_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(160) NOT NULL, contact_phone VARCHAR(32) NULL, contact_email VARCHAR(254) NULL, address_line_1 VARCHAR(180) NOT NULL,
 address_line_2 VARCHAR(180) NULL, city_town VARCHAR(100) NOT NULL, state_region VARCHAR(100) NOT NULL, country_code CHAR(2) NOT NULL DEFAULT 'NG',
 timezone_name VARCHAR(64) NOT NULL DEFAULT 'Africa/Lagos', currency_code CHAR(3) NOT NULL DEFAULT 'NGN', status VARCHAR(16) NOT NULL DEFAULT 'ACTIVE',
 activated_at DATETIME(6) NULL, deactivated_at DATETIME(6) NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 CONSTRAINT uq_shops_public UNIQUE (public_id), CONSTRAINT uq_shops_tenant_id UNIQUE (tenant_id,id), INDEX ix_shops_tenant_status (tenant_id,status), INDEX ix_shops_tenant_name (tenant_id,name),
 CONSTRAINT ck_shops_status CHECK (status IN ('ACTIVE','INACTIVE')), CONSTRAINT ck_shops_country CHECK (country_code='NG'), CONSTRAINT ck_shops_currency CHECK (currency_code='NGN'),
 CONSTRAINT fk_shops_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE tenant_memberships (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, tenant_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL,
 role VARCHAR(24) NOT NULL, status VARCHAR(16) NOT NULL DEFAULT 'ACTIVE', activated_at DATETIME(6) NULL, deactivated_at DATETIME(6) NULL,
 created_by_user_id BIGINT UNSIGNED NOT NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 primary_owner_guard BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN role='PRIMARY_OWNER' AND status='ACTIVE' THEN tenant_id ELSE NULL END) STORED,
 CONSTRAINT uq_memberships_public UNIQUE (public_id), CONSTRAINT uq_memberships_tenant_user UNIQUE (tenant_id,user_id), CONSTRAINT uq_memberships_tenant_id UNIQUE (tenant_id,id),
 CONSTRAINT uq_memberships_primary_owner UNIQUE (primary_owner_guard), INDEX ix_memberships_user_status (user_id,status), INDEX ix_memberships_tenant_status_role (tenant_id,status,role),
 CONSTRAINT ck_memberships_role CHECK (role IN ('PRIMARY_OWNER','STAFF')), CONSTRAINT ck_memberships_status CHECK (status IN ('INVITED','ACTIVE','DEACTIVATED')),
 CONSTRAINT fk_memberships_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_memberships_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_memberships_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE membership_permission_grants (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, membership_id BIGINT UNSIGNED NOT NULL, permission_code VARCHAR(80) NOT NULL,
 granted_by_user_id BIGINT UNSIGNED NOT NULL, granted_at DATETIME(6) NOT NULL, revoked_by_user_id BIGINT UNSIGNED NULL, revoked_at DATETIME(6) NULL, created_at DATETIME(6) NOT NULL,
 CONSTRAINT uq_permission_grants_event UNIQUE (membership_id,permission_code,granted_at), INDEX ix_permission_grants_scope (tenant_id,membership_id,revoked_at),
 CONSTRAINT fk_permission_grants_membership FOREIGN KEY (tenant_id,membership_id) REFERENCES tenant_memberships(tenant_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_permission_grants_granter FOREIGN KEY (granted_by_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_permission_grants_revoker FOREIGN KEY (revoked_by_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT ck_permission_grants_revocation CHECK ((revoked_at IS NULL AND revoked_by_user_id IS NULL) OR (revoked_at IS NOT NULL AND revoked_by_user_id IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE shop_branding_profiles (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, tenant_id BIGINT UNSIGNED NOT NULL, shop_id BIGINT UNSIGNED NOT NULL,
 brand_display_name VARCHAR(160) NOT NULL, tagline VARCHAR(180) NULL, public_description VARCHAR(500) NULL,
 primary_colour CHAR(7) CHARACTER SET ascii NOT NULL, secondary_colour CHAR(7) CHARACTER SET ascii NOT NULL, accent_colour CHAR(7) CHARACTER SET ascii NOT NULL,
 theme_colour CHAR(7) CHARACTER SET ascii NOT NULL, background_colour CHAR(7) CHARACTER SET ascii NOT NULL,
 pwa_full_name VARCHAR(80) NOT NULL, pwa_short_name VARCHAR(24) NOT NULL, application_title VARCHAR(100) NOT NULL,
 configuration_version BIGINT UNSIGNED NOT NULL DEFAULT 1, updated_by_user_id BIGINT UNSIGNED NOT NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 CONSTRAINT uq_brand_profiles_public UNIQUE (public_id), CONSTRAINT uq_brand_profiles_shop UNIQUE (tenant_id,shop_id), CONSTRAINT uq_brand_profiles_scope_id UNIQUE (tenant_id,shop_id,id),
 CONSTRAINT fk_brand_profiles_shop FOREIGN KEY (tenant_id,shop_id) REFERENCES shops(tenant_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_brand_profiles_updater FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT ck_brand_profiles_colours CHECK (primary_colour REGEXP '^#[0-9A-Fa-f]{6}$' AND secondary_colour REGEXP '^#[0-9A-Fa-f]{6}$' AND accent_colour REGEXP '^#[0-9A-Fa-f]{6}$' AND theme_colour REGEXP '^#[0-9A-Fa-f]{6}$' AND background_colour REGEXP '^#[0-9A-Fa-f]{6}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE shop_brand_assets (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, tenant_id BIGINT UNSIGNED NOT NULL, shop_id BIGINT UNSIGNED NOT NULL,
 asset_type VARCHAR(24) NOT NULL, storage_key VARCHAR(500) NOT NULL, mime_type VARCHAR(80) NOT NULL, width_px SMALLINT UNSIGNED NOT NULL, height_px SMALLINT UNSIGNED NOT NULL,
 file_size_bytes BIGINT UNSIGNED NOT NULL, content_sha256 BINARY(32) NOT NULL, is_active BOOLEAN NOT NULL DEFAULT TRUE, uploaded_by_user_id BIGINT UNSIGNED NOT NULL,
 activated_at DATETIME(6) NOT NULL, deactivated_at DATETIME(6) NULL, created_at DATETIME(6) NOT NULL,
 active_type_guard VARCHAR(24) GENERATED ALWAYS AS (CASE WHEN is_active=1 THEN asset_type ELSE NULL END) STORED,
 CONSTRAINT uq_brand_assets_public UNIQUE (public_id), CONSTRAINT uq_brand_assets_storage UNIQUE (storage_key), CONSTRAINT uq_brand_assets_scope_id UNIQUE (tenant_id,shop_id,id),
 CONSTRAINT uq_brand_assets_active UNIQUE (tenant_id,shop_id,active_type_guard), INDEX ix_brand_assets_lookup (tenant_id,shop_id,asset_type,is_active),
 CONSTRAINT ck_brand_assets_type CHECK (asset_type IN ('LOGO','FAVICON','PWA_ICON_192','PWA_ICON_512','LOGIN_BACKGROUND')),
 CONSTRAINT fk_brand_assets_shop FOREIGN KEY (tenant_id,shop_id) REFERENCES shops(tenant_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_brand_assets_uploader FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE shop_domains (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, tenant_id BIGINT UNSIGNED NOT NULL, shop_id BIGINT UNSIGNED NOT NULL,
 hostname VARCHAR(253) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, domain_type VARCHAR(24) NOT NULL, verification_status VARCHAR(16) NOT NULL DEFAULT 'PENDING',
 is_active BOOLEAN NOT NULL DEFAULT FALSE, is_primary BOOLEAN NOT NULL DEFAULT FALSE, verification_method VARCHAR(16) NULL, verification_token_hash BINARY(32) NULL,
 verification_reference VARCHAR(200) NULL, certificate_status VARCHAR(16) NULL, verified_at DATETIME(6) NULL, activated_at DATETIME(6) NULL, deactivated_at DATETIME(6) NULL,
 created_by_admin_user_id BIGINT UNSIGNED NULL, verified_by_admin_user_id BIGINT UNSIGNED NULL, updated_by_user_id BIGINT UNSIGNED NOT NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE subscriptions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, tenant_id BIGINT UNSIGNED NOT NULL, subscription_plan_id BIGINT UNSIGNED NOT NULL,
 entitlement_type VARCHAR(12) NOT NULL, starts_at DATETIME(6) NOT NULL, ends_at DATETIME(6) NOT NULL, plan_code_snapshot VARCHAR(24) NOT NULL,
 max_active_staff_snapshot SMALLINT UNSIGNED NOT NULL, price_amount_snapshot DECIMAL(19,2) NOT NULL, currency_code CHAR(3) NOT NULL DEFAULT 'NGN', external_reference VARCHAR(120) NULL,
 supersedes_subscription_id BIGINT UNSIGNED NULL, created_by_user_id BIGINT UNSIGNED NOT NULL, reason VARCHAR(500) NULL, created_at DATETIME(6) NOT NULL,
 CONSTRAINT uq_subscriptions_public UNIQUE (public_id), CONSTRAINT uq_subscriptions_scope_id UNIQUE (tenant_id,id), INDEX ix_subscriptions_period (tenant_id,starts_at,ends_at), INDEX ix_subscriptions_type_end (entitlement_type,ends_at),
 CONSTRAINT ck_subscriptions_type CHECK (entitlement_type IN ('TRIAL','PAID')), CONSTRAINT ck_subscriptions_period CHECK (ends_at>starts_at), CONSTRAINT ck_subscriptions_currency CHECK (currency_code='NGN'), CONSTRAINT ck_subscriptions_price CHECK (price_amount_snapshot>=0),
 CONSTRAINT fk_subscriptions_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_subscriptions_plan FOREIGN KEY (subscription_plan_id) REFERENCES subscription_plans(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_subscriptions_previous FOREIGN KEY (supersedes_subscription_id) REFERENCES subscriptions(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_subscriptions_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE subscription_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, tenant_id BIGINT UNSIGNED NOT NULL, subscription_id BIGINT UNSIGNED NOT NULL,
 event_type VARCHAR(24) NOT NULL, previous_subscription_id BIGINT UNSIGNED NULL, effective_at DATETIME(6) NOT NULL, external_reference VARCHAR(120) NULL, reason VARCHAR(500) NULL,
 actor_user_id BIGINT UNSIGNED NOT NULL, created_at DATETIME(6) NOT NULL,
 CONSTRAINT uq_subscription_events_public UNIQUE (public_id), INDEX ix_subscription_events_tenant_time (tenant_id,effective_at,id), INDEX ix_subscription_events_type (subscription_id,event_type),
 CONSTRAINT ck_subscription_events_type CHECK (event_type IN ('CREATED','RENEWED','UPGRADED','DOWNGRADED','EXTENDED','EXPIRED')),
 CONSTRAINT fk_subscription_events_subscription FOREIGN KEY (tenant_id,subscription_id) REFERENCES subscriptions(tenant_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_subscription_events_previous FOREIGN KEY (previous_subscription_id) REFERENCES subscriptions(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_subscription_events_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE audit_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, tenant_id BIGINT UNSIGNED NULL, shop_id BIGINT UNSIGNED NULL,
 actor_type VARCHAR(12) NOT NULL, actor_user_id BIGINT UNSIGNED NULL, actor_membership_id BIGINT UNSIGNED NULL, actor_device_id BIGINT UNSIGNED NULL,
 action_code VARCHAR(80) NOT NULL, category VARCHAR(24) NOT NULL, resource_type VARCHAR(48) NOT NULL, resource_public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
 reason VARCHAR(500) NULL, change_metadata JSON NULL, caused_by_audit_event_id BIGINT UNSIGNED NULL, correlation_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
 sync_operation_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL, ip_address VARBINARY(16) NULL, user_agent_hash BINARY(32) NULL, occurred_at DATETIME(6) NOT NULL, created_at DATETIME(6) NOT NULL,
 CONSTRAINT uq_audit_events_public UNIQUE (public_id), INDEX ix_audit_events_scope_time (tenant_id,shop_id,occurred_at,id), INDEX ix_audit_events_resource (tenant_id,resource_type,resource_public_id,occurred_at),
 INDEX ix_audit_events_actor (actor_user_id,occurred_at), INDEX ix_audit_events_correlation (correlation_id), INDEX ix_audit_events_sync_operation (sync_operation_id), INDEX ix_audit_events_category (category,occurred_at),
 CONSTRAINT ck_audit_events_actor CHECK ((actor_type='USER' AND actor_user_id IS NOT NULL) OR (actor_type='SYSTEM' AND actor_user_id IS NULL)),
 CONSTRAINT ck_audit_events_category CHECK (category IN ('AUTHORIZATION','TENANT','SUBSCRIPTION','INVENTORY','SALE','DEBT','FINANCIAL','RECONCILIATION','SYNC','SUPPORT','SECURITY')),
 CONSTRAINT fk_audit_events_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_audit_events_shop FOREIGN KEY (tenant_id,shop_id) REFERENCES shops(tenant_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_audit_events_actor_user FOREIGN KEY (actor_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_audit_events_membership FOREIGN KEY (actor_membership_id) REFERENCES tenant_memberships(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_audit_events_cause FOREIGN KEY (caused_by_audit_event_id) REFERENCES audit_events(id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- actor_device_id intentionally receives fk_audit_events_device in Phase 7 after registered_devices exists.
