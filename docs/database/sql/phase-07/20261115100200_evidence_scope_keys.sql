-- Apply once in order after WP1, using migration OR SQL on each database, never both.
-- Back up and rehearse native fresh/upgrade/partial-DDL recovery. Never drop evidence-bearing tables.
ALTER TABLE reconciliation_financial_counts ADD CONSTRAINT uq_financial_counts_control_source UNIQUE (tenant_id,shop_id,reconciliation_session_id,id);

ALTER TABLE registered_devices ADD CONSTRAINT uq_registered_devices_scope_id UNIQUE (tenant_id,shop_id,id);
