<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreatePhaseSevenStaffContexts extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
CREATE TABLE reconciliation_staff_contexts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 tenant_id BIGINT UNSIGNED NOT NULL, shop_id BIGINT UNSIGNED NOT NULL, reconciliation_session_id BIGINT UNSIGNED NOT NULL,
 membership_id BIGINT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL,
 context_role VARCHAR(24) NOT NULL, context_starts_at DATETIME(6) NULL, context_ends_at DATETIME(6) NULL,
 notes VARCHAR(500) NULL, created_at DATETIME(6) NOT NULL,
 CONSTRAINT uq_staff_context_role UNIQUE (reconciliation_session_id,membership_id,context_role),
 INDEX ix_staff_context_user (tenant_id,shop_id,user_id,reconciliation_session_id),
 CONSTRAINT ck_staff_context_role CHECK (context_role IN ('ON_DUTY','COUNTER','REVIEWER','FINALIZER')),
 CONSTRAINT ck_staff_context_period CHECK (context_starts_at IS NULL OR context_ends_at IS NULL OR context_ends_at>=context_starts_at),
 CONSTRAINT fk_staff_context_control FOREIGN KEY (tenant_id,shop_id,reconciliation_session_id) REFERENCES reconciliation_sessions(tenant_id,shop_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_staff_context_member FOREIGN KEY (tenant_id,membership_id) REFERENCES tenant_memberships(tenant_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_staff_context_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        if ($this->db->table('reconciliation_staff_contexts')->countAllResults() > 0) {
            throw new \RuntimeException('Preserve reconciliation evidence; use a compatible code rollback.');
        }
        $this->db->query('DROP TABLE reconciliation_staff_contexts');
    }
}
