-- Apply once after WP2 evidence tables. Use migration OR SQL on each database, never both.
-- Before DDL, run this preflight and stop if any row is returned; never rewrite protected movements:
-- SELECT m.id FROM stock_movements m LEFT JOIN reconciliation_variances v ON v.tenant_id=m.tenant_id AND v.shop_id=m.shop_id AND v.id=m.reconciliation_variance_id WHERE m.reconciliation_variance_id IS NOT NULL AND (v.id IS NULL OR v.variance_kind<>'STOCK' OR m.source_kind<>'RECON_VARIANCE' OR m.movement_type<>'RECONCILIATION_ADJUSTMENT') LIMIT 1;
-- Candidate key adds no business restriction beyond the existing globally unique id. DDL may partially commit.
ALTER TABLE reconciliation_variances ADD CONSTRAINT uq_variances_scope_id UNIQUE (tenant_id,shop_id,id);
ALTER TABLE stock_movements ADD CONSTRAINT fk_stock_movements_variance FOREIGN KEY (tenant_id,shop_id,reconciliation_variance_id) REFERENCES reconciliation_variances(tenant_id,shop_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT;
