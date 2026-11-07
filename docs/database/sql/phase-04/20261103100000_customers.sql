SET time_zone = '+00:00';

CREATE TABLE customers (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 tenant_id BIGINT UNSIGNED NOT NULL, display_name VARCHAR(160) NOT NULL, phone VARCHAR(32) NULL, notes VARCHAR(500) NULL,
 status VARCHAR(16) NOT NULL DEFAULT 'ACTIVE', created_by_user_id INT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, deactivated_at DATETIME(6) NULL,
 CONSTRAINT uq_customers_public UNIQUE (public_id), CONSTRAINT uq_customers_tenant_id UNIQUE (tenant_id,id),
 INDEX ix_customers_name (tenant_id,status,display_name), INDEX ix_customers_phone (tenant_id,phone),
 CONSTRAINT ck_customers_uuid CHECK (public_id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'), CONSTRAINT ck_customers_status CHECK (status IN ('ACTIVE','INACTIVE')),
 CONSTRAINT ck_customers_name CHECK (CHAR_LENGTH(TRIM(display_name))>0),
 CONSTRAINT ck_customers_deactivation CHECK ((status='ACTIVE' AND deactivated_at IS NULL) OR (status='INACTIVE' AND deactivated_at IS NOT NULL)),
 CONSTRAINT fk_customers_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_customers_created_by_user_id FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
