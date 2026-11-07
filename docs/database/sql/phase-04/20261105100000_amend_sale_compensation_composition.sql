-- Owner-approved WP4 compensation header amendment; MySQL 8.4
-- Run only after preflight returns zero. Stop and investigate otherwise.
SELECT COUNT(*) AS preflight_blocking_rows FROM sales WHERE reversal_of_sale_id IS NULL AND kind='SALE' AND original_sale_id IS NOT NULL;
ALTER TABLE sales DROP CHECK ck_sales_composition, ADD CONSTRAINT ck_sales_composition CHECK (
 (reversal_of_sale_id IS NOT NULL AND status='POSTED' AND original_sale_id IS NOT NULL AND received_amount_snapshot IS NULL AND credit_created_amount_snapshot IS NULL)
 OR
 (reversal_of_sale_id IS NULL AND ((kind='RETURN' AND original_sale_id IS NOT NULL AND received_amount_snapshot IS NULL AND credit_created_amount_snapshot IS NULL)
 OR (kind='SALE' AND original_sale_id IS NULL AND ((status='DRAFT' AND received_amount_snapshot IS NULL AND credit_created_amount_snapshot IS NULL)
 OR (status='POSTED' AND received_amount_snapshot IS NOT NULL AND credit_created_amount_snapshot IS NOT NULL AND received_amount_snapshot>=0 AND credit_created_amount_snapshot>=0 AND received_amount_snapshot+credit_created_amount_snapshot=total_amount AND (credit_created_amount_snapshot=0 OR customer_id IS NOT NULL))))))
);
-- Rollback preflight (must be zero; never delete evidence):
-- SELECT COUNT(*) FROM sales WHERE reversal_of_sale_id IS NOT NULL AND status='POSTED' AND kind='SALE';
