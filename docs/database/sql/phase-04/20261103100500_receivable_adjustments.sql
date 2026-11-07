SET time_zone = '+00:00';

CREATE TABLE receivable_adjustments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 tenant_id BIGINT UNSIGNED NOT NULL, shop_id BIGINT UNSIGNED NOT NULL, receivable_id BIGINT UNSIGNED NOT NULL,
 adjustment_type VARCHAR(24) NOT NULL, amount_delta DECIMAL(19,2) NOT NULL, sale_id BIGINT UNSIGNED NULL,
 reason VARCHAR(500) NOT NULL, reversal_of_adjustment_id BIGINT UNSIGNED NULL,
 occurred_at DATETIME(6) NOT NULL, posted_at DATETIME(6) NOT NULL, created_by_user_id INT UNSIGNED NOT NULL,
 correlation_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL, created_at DATETIME(6) NOT NULL,
 CONSTRAINT uq_receivable_adjustments_public UNIQUE (public_id), CONSTRAINT uq_receivable_adjustments_scope UNIQUE (tenant_id,shop_id,id),
 CONSTRAINT ck_receivable_adjustments_uuid CHECK (public_id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'), CONSTRAINT uq_receivable_adjustments_reversal UNIQUE (reversal_of_adjustment_id),
 INDEX ix_receivable_adjustments_receivable (tenant_id,shop_id,receivable_id,posted_at), INDEX ix_receivable_adjustments_sale (sale_id),
 CONSTRAINT ck_receivable_adjustments_type CHECK (adjustment_type IN ('RETURN_REDUCTION','CORRECTION_INCREASE','CORRECTION_DECREASE','REVERSAL')),
 CONSTRAINT ck_receivable_adjustments_amount CHECK (amount_delta<>0 AND CHAR_LENGTH(TRIM(reason))>0 AND (adjustment_type NOT IN ('RETURN_REDUCTION','CORRECTION_DECREASE') OR amount_delta<0) AND (adjustment_type<>'CORRECTION_INCREASE' OR amount_delta>0)),
 CONSTRAINT ck_receivable_adjustments_source CHECK ((adjustment_type<>'RETURN_REDUCTION' OR sale_id IS NOT NULL) AND ((adjustment_type='REVERSAL' AND reversal_of_adjustment_id IS NOT NULL) OR (adjustment_type<>'REVERSAL' AND reversal_of_adjustment_id IS NULL))),
 CONSTRAINT fk_receivable_adjustments_receivable FOREIGN KEY (tenant_id,shop_id,receivable_id) REFERENCES receivables(tenant_id,shop_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_receivable_adjustments_sale FOREIGN KEY (tenant_id,shop_id,sale_id) REFERENCES sales(tenant_id,shop_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT, CONSTRAINT fk_receivable_adjustments_created_by_user_id FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_receivable_adjustments_reversal FOREIGN KEY (tenant_id,shop_id,reversal_of_adjustment_id) REFERENCES receivable_adjustments(tenant_id,shop_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
