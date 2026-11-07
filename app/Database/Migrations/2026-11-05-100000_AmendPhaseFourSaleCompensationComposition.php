<?php

declare(strict_types=1);
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/** Owner-approved WP4 compensation header amendment; no evidence rows are rewritten. */
final class AmendPhaseFourSaleCompensationComposition extends Migration
{
    private const NEW_CHECK = <<<'SQL'
    (
      (reversal_of_sale_id IS NOT NULL AND status='POSTED' AND original_sale_id IS NOT NULL AND received_amount_snapshot IS NULL AND credit_created_amount_snapshot IS NULL)
      OR
      (reversal_of_sale_id IS NULL AND (
        (kind='RETURN' AND original_sale_id IS NOT NULL AND received_amount_snapshot IS NULL AND credit_created_amount_snapshot IS NULL)
        OR
        (kind='SALE' AND original_sale_id IS NULL AND (
          (status='DRAFT' AND received_amount_snapshot IS NULL AND credit_created_amount_snapshot IS NULL)
          OR
          (status='POSTED' AND received_amount_snapshot IS NOT NULL AND credit_created_amount_snapshot IS NOT NULL AND received_amount_snapshot>=0 AND credit_created_amount_snapshot>=0 AND received_amount_snapshot+credit_created_amount_snapshot=total_amount AND (credit_created_amount_snapshot=0 OR customer_id IS NOT NULL))
        ))
      ))
    )
    SQL;

    private const OLD_CHECK = <<<'SQL'
    ((kind='RETURN' AND original_sale_id IS NOT NULL AND received_amount_snapshot IS NULL AND credit_created_amount_snapshot IS NULL) OR (kind='SALE' AND ((status='DRAFT' AND received_amount_snapshot IS NULL AND credit_created_amount_snapshot IS NULL) OR (status='POSTED' AND received_amount_snapshot IS NOT NULL AND credit_created_amount_snapshot IS NOT NULL AND received_amount_snapshot>=0 AND credit_created_amount_snapshot>=0 AND received_amount_snapshot+credit_created_amount_snapshot=total_amount AND (credit_created_amount_snapshot=0 OR customer_id IS NOT NULL)))))
    SQL;

    public function up(): void
    {
        $invalid = (int) $this->db->query("SELECT COUNT(*) AS n FROM sales WHERE reversal_of_sale_id IS NULL AND kind='SALE' AND original_sale_id IS NOT NULL")->getRowArray()['n'];
        if ($invalid !== 0) {
            throw new RuntimeException('WP4 CHECK preflight blocked: ordinary SALE rows unexpectedly reference original_sale_id. Investigate; no rows were changed.');
        }
        $this->db->query('ALTER TABLE sales DROP CHECK ck_sales_composition, ADD CONSTRAINT ck_sales_composition CHECK ' . self::NEW_CHECK);
    }

    public function down(): void
    {
        $count = (int) $this->db->query("SELECT COUNT(*) AS n FROM sales WHERE reversal_of_sale_id IS NOT NULL AND status='POSTED' AND kind='SALE'")->getRowArray()['n'];
        if ($count !== 0) {
            throw new RuntimeException('Cannot restore the old CHECK while posted SALE-shaped Return compensations exist. Preserve evidence and roll forward.');
        }
        $this->db->query('ALTER TABLE sales DROP CHECK ck_sales_composition, ADD CONSTRAINT ck_sales_composition CHECK ' . self::OLD_CHECK);
    }
}
