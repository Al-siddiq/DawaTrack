-- Apply once in order after WP1, using migration OR SQL on each database, never both.
-- Back up and rehearse native fresh/upgrade/partial-DDL recovery. Never drop evidence-bearing tables.
CREATE TABLE reconciliation_stock_counts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 tenant_id BIGINT UNSIGNED NOT NULL, shop_id BIGINT UNSIGNED NOT NULL,
 reconciliation_session_id BIGINT UNSIGNED NOT NULL, product_id BIGINT UNSIGNED NOT NULL,
 expected_quantity DECIMAL(19,4) NOT NULL, expected_average_unit_cost DECIMAL(19,6) NOT NULL,
 actual_quantity DECIMAL(19,4) NULL, variance_quantity DECIMAL(19,4) NULL,
 counted_by_user_id INT UNSIGNED NULL, counted_at DATETIME(6) NULL, committed_at DATETIME(6) NULL,
 explanation VARCHAR(500) NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 CONSTRAINT uq_stock_counts_product UNIQUE (reconciliation_session_id,product_id),
 CONSTRAINT uq_stock_counts_control_source UNIQUE (tenant_id,shop_id,reconciliation_session_id,id),
 INDEX ix_stock_counts_scope (tenant_id,shop_id,product_id,reconciliation_session_id),
 CONSTRAINT ck_stock_counts_cost CHECK (expected_average_unit_cost>=0),
 CONSTRAINT ck_stock_counts_actual CHECK (actual_quantity IS NULL OR actual_quantity>=0),
 CONSTRAINT ck_stock_counts_variance CHECK ((actual_quantity IS NULL AND variance_quantity IS NULL) OR (actual_quantity IS NOT NULL AND variance_quantity IS NOT NULL AND variance_quantity=actual_quantity-expected_quantity)),
 CONSTRAINT fk_stock_counts_control FOREIGN KEY (tenant_id,shop_id,reconciliation_session_id) REFERENCES reconciliation_sessions(tenant_id,shop_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_stock_counts_product FOREIGN KEY (tenant_id,product_id) REFERENCES products(tenant_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_stock_counts_counter FOREIGN KEY (counted_by_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
