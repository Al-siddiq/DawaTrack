-- Apply once in order after WP1, using migration OR SQL on each database, never both.
-- Back up and rehearse native fresh/upgrade/partial-DDL recovery. Never drop evidence-bearing tables.
CREATE TABLE reconciliation_variances (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 tenant_id BIGINT UNSIGNED NOT NULL, shop_id BIGINT UNSIGNED NOT NULL, reconciliation_session_id BIGINT UNSIGNED NOT NULL,
 variance_kind VARCHAR(12) NOT NULL, stock_count_id BIGINT UNSIGNED NULL, financial_count_id BIGINT UNSIGNED NULL,
 quantity_variance DECIMAL(19,4) NULL, amount_variance DECIMAL(19,2) NULL,
 explanation VARCHAR(500) NULL, created_at DATETIME(6) NOT NULL,
 CONSTRAINT uq_reconciliation_variances_public UNIQUE (public_id),
 CONSTRAINT uq_reconciliation_variances_stock UNIQUE (stock_count_id),
 CONSTRAINT uq_reconciliation_variances_cash UNIQUE (financial_count_id),
 CONSTRAINT uq_reconciliation_variances_control UNIQUE (tenant_id,shop_id,reconciliation_session_id,id),
 INDEX ix_reconciliation_variances_scope (tenant_id,shop_id,reconciliation_session_id,variance_kind),
 CONSTRAINT ck_reconciliation_variances_uuid CHECK (public_id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 CONSTRAINT ck_reconciliation_variances_source CHECK ((variance_kind='STOCK' AND stock_count_id IS NOT NULL AND financial_count_id IS NULL AND quantity_variance IS NOT NULL AND amount_variance IS NULL) OR (variance_kind='CASH' AND stock_count_id IS NULL AND financial_count_id IS NOT NULL AND quantity_variance IS NULL AND amount_variance IS NOT NULL)),
 CONSTRAINT fk_variances_control FOREIGN KEY (tenant_id,shop_id,reconciliation_session_id) REFERENCES reconciliation_sessions(tenant_id,shop_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_variances_stock_count FOREIGN KEY (tenant_id,shop_id,reconciliation_session_id,stock_count_id) REFERENCES reconciliation_stock_counts(tenant_id,shop_id,reconciliation_session_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_variances_cash_count FOREIGN KEY (tenant_id,shop_id,reconciliation_session_id,financial_count_id) REFERENCES reconciliation_financial_counts(tenant_id,shop_id,reconciliation_session_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
