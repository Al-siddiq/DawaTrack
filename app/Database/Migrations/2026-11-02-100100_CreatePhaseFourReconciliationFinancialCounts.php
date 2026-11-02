<?php

declare(strict_types=1);
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
final class CreatePhaseFourReconciliationFinancialCounts extends Migration
{
    public function up(): void
    {
        $this->db->query("SET time_zone = '+00:00'");
        $this->db->query(
            <<<'SQL'
            CREATE TABLE reconciliation_financial_counts (
             id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
             tenant_id BIGINT UNSIGNED NOT NULL, shop_id BIGINT UNSIGNED NOT NULL, reconciliation_session_id BIGINT UNSIGNED NOT NULL,
             channel VARCHAR(12) NOT NULL DEFAULT 'CASH',
             opening_baseline_amount DECIMAL(19,2) NOT NULL,
             cash_sale_receipts DECIMAL(19,2) NOT NULL DEFAULT 0, cash_sale_refunds DECIMAL(19,2) NOT NULL DEFAULT 0,
             cash_debt_receipts DECIMAL(19,2) NOT NULL DEFAULT 0, cash_introductions DECIMAL(19,2) NOT NULL DEFAULT 0,
             cash_expenses DECIMAL(19,2) NOT NULL DEFAULT 0, cash_withdrawals DECIMAL(19,2) NOT NULL DEFAULT 0,
             cash_corrections_net DECIMAL(19,2) NOT NULL DEFAULT 0,
             expected_amount DECIMAL(19,2) NOT NULL, actual_amount DECIMAL(19,2) NULL, variance_amount DECIMAL(19,2) NULL,
             counted_by_user_id INT UNSIGNED NULL, counted_at DATETIME(6) NULL, committed_at DATETIME(6) NULL,
             explanation VARCHAR(500) NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
             CONSTRAINT uq_reconciliation_financial_counts_channel UNIQUE (reconciliation_session_id,channel),
             INDEX ix_reconciliation_financial_counts_scope (tenant_id,shop_id,reconciliation_session_id),
             CONSTRAINT ck_reconciliation_financial_counts_channel CHECK (channel='CASH'),
             CONSTRAINT ck_reconciliation_financial_counts_amounts CHECK (opening_baseline_amount>=0 AND cash_sale_receipts>=0 AND cash_sale_refunds>=0 AND cash_debt_receipts>=0 AND cash_introductions>=0 AND cash_expenses>=0 AND cash_withdrawals>=0 AND (actual_amount IS NULL OR actual_amount>=0)),
             CONSTRAINT ck_reconciliation_financial_counts_expected CHECK (expected_amount=opening_baseline_amount+cash_sale_receipts-cash_sale_refunds+cash_debt_receipts+cash_introductions-cash_expenses-cash_withdrawals+cash_corrections_net),
             CONSTRAINT ck_reconciliation_financial_counts_variance CHECK ((actual_amount IS NULL AND variance_amount IS NULL) OR (actual_amount IS NOT NULL AND variance_amount IS NOT NULL AND variance_amount=actual_amount-expected_amount)),
             CONSTRAINT fk_reconciliation_financial_counts_parent FOREIGN KEY (tenant_id,shop_id,reconciliation_session_id) REFERENCES reconciliation_sessions(tenant_id,shop_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
             CONSTRAINT fk_reconciliation_financial_counts_counter FOREIGN KEY (counted_by_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL
            ,
        );
    }
    public function down(): void
    {
        $this->db->query("DROP TABLE reconciliation_financial_counts");
    }
}
