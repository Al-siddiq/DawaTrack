SET time_zone = '+00:00';

CREATE TABLE stock_intakes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 tenant_id BIGINT UNSIGNED NOT NULL, shop_id BIGINT UNSIGNED NOT NULL, intake_kind VARCHAR(16) NOT NULL, status VARCHAR(16) NOT NULL DEFAULT 'DRAFT',
 reference VARCHAR(100) NULL, notes VARCHAR(1000) NULL, occurred_at DATETIME(6) NOT NULL, device_local_offset_minutes SMALLINT NULL,
 posted_at DATETIME(6) NULL, posted_by_user_id INT UNSIGNED NULL, reversal_of_intake_id BIGINT UNSIGNED NULL, replacement_for_intake_id BIGINT UNSIGNED NULL,
 correlation_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL, created_by_user_id INT UNSIGNED NOT NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 CONSTRAINT uq_stock_intakes_public UNIQUE (public_id), CONSTRAINT uq_stock_intakes_scope_id UNIQUE (tenant_id,shop_id,id),
 CONSTRAINT uq_stock_intakes_reversal UNIQUE (reversal_of_intake_id), CONSTRAINT uq_stock_intakes_replacement UNIQUE (replacement_for_intake_id),
 INDEX ix_stock_intakes_scope_status_occurred (tenant_id,shop_id,status,occurred_at), INDEX ix_stock_intakes_scope_posted (tenant_id,shop_id,posted_at),
 CONSTRAINT ck_stock_intakes_public_uuid CHECK (public_id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 CONSTRAINT ck_stock_intakes_kind CHECK (intake_kind IN ('OPENING','RECEIPT')), CONSTRAINT ck_stock_intakes_status CHECK (status IN ('DRAFT','POSTED')),
 CONSTRAINT ck_stock_intakes_posting CHECK ((status='DRAFT' AND posted_at IS NULL AND posted_by_user_id IS NULL) OR (status='POSTED' AND posted_at IS NOT NULL AND posted_by_user_id IS NOT NULL)),
 CONSTRAINT fk_stock_intakes_shop FOREIGN KEY (tenant_id,shop_id) REFERENCES shops(tenant_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_stock_intakes_poster FOREIGN KEY (posted_by_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_stock_intakes_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_stock_intakes_reversal FOREIGN KEY (tenant_id,shop_id,reversal_of_intake_id) REFERENCES stock_intakes(tenant_id,shop_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_stock_intakes_replacement FOREIGN KEY (tenant_id,shop_id,replacement_for_intake_id) REFERENCES stock_intakes(tenant_id,shop_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rollback only after movements/items: DROP TABLE stock_intakes.
