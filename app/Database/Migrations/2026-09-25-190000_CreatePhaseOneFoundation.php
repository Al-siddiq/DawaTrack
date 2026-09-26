<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

final class CreatePhaseOneFoundation extends Migration
{
    public function up(): void
    {
        $path = ROOTPATH . 'docs/database/sql/phase-01/001_phase_01_foundation.sql';
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException('Phase 1 SQL source could not be read.');
        }
        foreach (array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [])) as $statement) {
            $this->db->query($statement);
        }
    }

    public function down(): void
    {
        foreach (['audit_events','subscription_events','subscriptions','shop_domains','shop_brand_assets','shop_branding_profiles','membership_permission_grants','tenant_memberships','shops','tenants','subscription_plans','sync_generations'] as $table) {
            $this->db->query('DROP TABLE IF EXISTS `' . $table . '`');
        }
    }
}
