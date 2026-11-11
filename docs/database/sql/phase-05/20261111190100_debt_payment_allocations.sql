SET time_zone = '+00:00';

CREATE TABLE debt_payment_allocations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    shop_id BIGINT UNSIGNED NOT NULL,
    debt_payment_id BIGINT UNSIGNED NOT NULL,
    receivable_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(19,2) NOT NULL,
    allocation_order SMALLINT UNSIGNED NOT NULL,
    created_at DATETIME(6) NOT NULL,
    CONSTRAINT uq_debt_allocations_bill UNIQUE (debt_payment_id,receivable_id),
    INDEX ix_debt_allocations_receivable (tenant_id,shop_id,receivable_id),
    INDEX ix_debt_allocations_order (tenant_id,debt_payment_id,allocation_order),
    CONSTRAINT ck_debt_allocations_amount CHECK (amount>0),
    CONSTRAINT ck_debt_allocations_order CHECK (allocation_order>0),
    CONSTRAINT fk_debt_allocations_payment FOREIGN KEY (tenant_id,shop_id,debt_payment_id) REFERENCES debt_payments(tenant_id,shop_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_debt_allocations_receivable FOREIGN KEY (tenant_id,shop_id,receivable_id) REFERENCES receivables(tenant_id,shop_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
