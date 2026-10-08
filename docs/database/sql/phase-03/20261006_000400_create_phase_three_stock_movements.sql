SET time_zone = '+00:00';

CREATE TABLE stock_movements (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 tenant_id BIGINT UNSIGNED NOT NULL, shop_id BIGINT UNSIGNED NOT NULL, product_id BIGINT UNSIGNED NOT NULL, stock_position_id BIGINT UNSIGNED NOT NULL,
 movement_type VARCHAR(32) NOT NULL, quantity_delta DECIMAL(19,4) NOT NULL, unit_cost_basis DECIMAL(19,6) NOT NULL,
 quantity_before DECIMAL(19,4) NOT NULL, quantity_after DECIMAL(19,4) NOT NULL, average_cost_before DECIMAL(19,6) NOT NULL, average_cost_after DECIMAL(19,6) NOT NULL,
 source_kind VARCHAR(32) NOT NULL, stock_intake_item_id BIGINT UNSIGNED NULL, sale_item_id BIGINT UNSIGNED NULL, reconciliation_variance_id BIGINT UNSIGNED NULL,
 reversal_of_movement_id BIGINT UNSIGNED NULL, reason_code VARCHAR(40) NULL, reason_text VARCHAR(500) NULL,
 occurred_at DATETIME(6) NOT NULL, posted_at DATETIME(6) NOT NULL, created_by_user_id INT UNSIGNED NOT NULL,
 correlation_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL, created_at DATETIME(6) NOT NULL,
 CONSTRAINT uq_stock_movements_public UNIQUE (public_id), CONSTRAINT uq_stock_movements_scope_id UNIQUE (tenant_id,shop_id,id), CONSTRAINT uq_stock_movements_reversal UNIQUE (reversal_of_movement_id),
 INDEX ix_stock_movements_product_posted (tenant_id,shop_id,product_id,posted_at,id), INDEX ix_stock_movements_type_posted (tenant_id,shop_id,movement_type,posted_at),
 INDEX ix_stock_movements_intake_item (stock_intake_item_id), INDEX ix_stock_movements_sale_item (sale_item_id), INDEX ix_stock_movements_recon_variance (reconciliation_variance_id),
 CONSTRAINT ck_stock_movements_public_uuid CHECK (public_id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 CONSTRAINT ck_stock_movements_type CHECK (movement_type IN ('OPENING','STOCK_INTAKE','SALE','CUSTOMER_RETURN','DAMAGED','EXPIRED','OWNER_WITHDRAWAL','MISSING_LOSS','CORRECTION','REVERSAL','RECONCILIATION_ADJUSTMENT')),
 CONSTRAINT ck_stock_movements_source CHECK (source_kind IN ('INTAKE_ITEM','SALE_ITEM','RECON_VARIANCE','MANUAL','REVERSAL')),
 CONSTRAINT ck_stock_movements_delta CHECK (quantity_delta <> 0 AND quantity_after = quantity_before + quantity_delta), CONSTRAINT ck_stock_movements_cost CHECK (unit_cost_basis >= 0 AND average_cost_before >= 0 AND average_cost_after >= 0),
 CONSTRAINT ck_stock_movements_source_shape CHECK ((source_kind='INTAKE_ITEM' AND stock_intake_item_id IS NOT NULL AND sale_item_id IS NULL AND reconciliation_variance_id IS NULL AND reversal_of_movement_id IS NULL) OR (source_kind='SALE_ITEM' AND stock_intake_item_id IS NULL AND sale_item_id IS NOT NULL AND reconciliation_variance_id IS NULL AND reversal_of_movement_id IS NULL) OR (source_kind='RECON_VARIANCE' AND stock_intake_item_id IS NULL AND sale_item_id IS NULL AND reconciliation_variance_id IS NOT NULL AND reversal_of_movement_id IS NULL) OR (source_kind='MANUAL' AND stock_intake_item_id IS NULL AND sale_item_id IS NULL AND reconciliation_variance_id IS NULL AND reversal_of_movement_id IS NULL) OR (source_kind='REVERSAL' AND stock_intake_item_id IS NULL AND sale_item_id IS NULL AND reconciliation_variance_id IS NULL AND reversal_of_movement_id IS NOT NULL)),
 CONSTRAINT ck_stock_movements_intake_sign CHECK (movement_type NOT IN ('OPENING','STOCK_INTAKE') OR (source_kind='INTAKE_ITEM' AND quantity_delta > 0)),
 CONSTRAINT fk_stock_movements_product FOREIGN KEY (tenant_id,product_id) REFERENCES products(tenant_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_stock_movements_position FOREIGN KEY (tenant_id,shop_id,stock_position_id) REFERENCES stock_positions(tenant_id,shop_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_stock_movements_intake_item FOREIGN KEY (tenant_id,shop_id,stock_intake_item_id) REFERENCES stock_intake_items(tenant_id,shop_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_stock_movements_reversal FOREIGN KEY (tenant_id,shop_id,reversal_of_movement_id) REFERENCES stock_movements(tenant_id,shop_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_stock_movements_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- sale_item_id FK is owned by Phase 4; reconciliation_variance_id FK by Phase 7.
-- Rollback after dropping stock_positions pointer FK: DROP TABLE stock_movements.
