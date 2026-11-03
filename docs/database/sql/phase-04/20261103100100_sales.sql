SET time_zone = '+00:00';

CREATE TABLE sales (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 tenant_id BIGINT UNSIGNED NOT NULL, shop_id BIGINT UNSIGNED NOT NULL, kind VARCHAR(16) NOT NULL DEFAULT 'SALE', status VARCHAR(16) NOT NULL DEFAULT 'DRAFT',
 customer_id BIGINT UNSIGNED NULL, original_sale_id BIGINT UNSIGNED NULL,
 subtotal_amount DECIMAL(19,2) NOT NULL DEFAULT 0, total_amount DECIMAL(19,2) NOT NULL DEFAULT 0,
 received_amount_snapshot DECIMAL(19,2) NULL, credit_created_amount_snapshot DECIMAL(19,2) NULL,
 currency_code CHAR(3) NOT NULL DEFAULT 'NGN', occurred_at DATETIME(6) NOT NULL, device_local_offset_minutes SMALLINT NULL,
 posted_at DATETIME(6) NULL, posted_by_user_id INT UNSIGNED NULL,
 reversal_of_sale_id BIGINT UNSIGNED NULL, replacement_for_sale_id BIGINT UNSIGNED NULL,
 correlation_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
 created_by_user_id INT UNSIGNED NOT NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 CONSTRAINT uq_sales_public UNIQUE (public_id), CONSTRAINT uq_sales_scope UNIQUE (tenant_id,shop_id,id),
 CONSTRAINT ck_sales_uuid CHECK (public_id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'), CONSTRAINT uq_sales_reversal UNIQUE (reversal_of_sale_id), CONSTRAINT uq_sales_replacement UNIQUE (replacement_for_sale_id),
 INDEX ix_sales_posted (tenant_id,shop_id,posted_at,id), INDEX ix_sales_actor (tenant_id,shop_id,posted_by_user_id,posted_at),
 INDEX ix_sales_customer (tenant_id,customer_id,posted_at), INDEX ix_sales_draft (tenant_id,shop_id,status,updated_at,id),
 CONSTRAINT ck_sales_kind CHECK (kind IN ('SALE','RETURN')), CONSTRAINT ck_sales_status CHECK (status IN ('DRAFT','POSTED')),
 CONSTRAINT ck_sales_amount CHECK (subtotal_amount>=0 AND total_amount>=0 AND currency_code='NGN'),
 CONSTRAINT ck_sales_posting CHECK ((status='DRAFT' AND posted_at IS NULL AND posted_by_user_id IS NULL) OR (status='POSTED' AND posted_at IS NOT NULL AND posted_by_user_id IS NOT NULL)),
 CONSTRAINT ck_sales_composition CHECK ((kind='RETURN' AND original_sale_id IS NOT NULL AND received_amount_snapshot IS NULL AND credit_created_amount_snapshot IS NULL) OR (kind='SALE' AND ((status='DRAFT' AND received_amount_snapshot IS NULL AND credit_created_amount_snapshot IS NULL) OR (status='POSTED' AND received_amount_snapshot IS NOT NULL AND credit_created_amount_snapshot IS NOT NULL AND received_amount_snapshot>=0 AND credit_created_amount_snapshot>=0 AND received_amount_snapshot+credit_created_amount_snapshot=total_amount AND (credit_created_amount_snapshot=0 OR customer_id IS NOT NULL))))),
 CONSTRAINT ck_sales_offset CHECK (device_local_offset_minutes IS NULL OR device_local_offset_minutes BETWEEN -840 AND 840),
 CONSTRAINT fk_sales_shop FOREIGN KEY (tenant_id,shop_id) REFERENCES shops(tenant_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT, CONSTRAINT fk_sales_customer FOREIGN KEY (tenant_id,customer_id) REFERENCES customers(tenant_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT, CONSTRAINT fk_sales_created_by_user_id FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT, CONSTRAINT fk_sales_posted_by_user_id FOREIGN KEY (posted_by_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_sales_original FOREIGN KEY (tenant_id,shop_id,original_sale_id) REFERENCES sales(tenant_id,shop_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_sales_reversal FOREIGN KEY (tenant_id,shop_id,reversal_of_sale_id) REFERENCES sales(tenant_id,shop_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_sales_replacement FOREIGN KEY (tenant_id,shop_id,replacement_for_sale_id) REFERENCES sales(tenant_id,shop_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
