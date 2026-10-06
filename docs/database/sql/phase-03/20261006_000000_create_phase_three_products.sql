SET time_zone = '+00:00';

CREATE TABLE products (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 tenant_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(180) NOT NULL,
 normalized_name VARCHAR(180) NOT NULL,
 unit_name VARCHAR(40) NOT NULL,
 current_selling_price DECIMAL(19,2) NOT NULL,
 status VARCHAR(16) NOT NULL DEFAULT 'ACTIVE',
 created_by_user_id INT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 deactivated_at DATETIME(6) NULL,
 CONSTRAINT uq_products_public UNIQUE (public_id),
 CONSTRAINT uq_products_tenant_id UNIQUE (tenant_id,id),
 INDEX ix_products_tenant_normalized (tenant_id,normalized_name),
 INDEX ix_products_tenant_status_name (tenant_id,status,name),
 CONSTRAINT ck_products_public_uuid CHECK (public_id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 CONSTRAINT ck_products_price CHECK (current_selling_price >= 0),
 CONSTRAINT ck_products_status CHECK (status IN ('ACTIVE','INACTIVE')),
 CONSTRAINT fk_products_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_products_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rollback: DROP TABLE stock_positions first, then DROP TABLE products.
