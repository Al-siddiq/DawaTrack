<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddPhaseSevenAuditDeviceForeignKey extends Migration
{
    public function up(): void
    {
        $orphan = $this->db->query('SELECT a.id FROM audit_events a LEFT JOIN registered_devices d ON d.id=a.actor_device_id WHERE a.actor_device_id IS NOT NULL AND (d.id IS NULL OR a.tenant_id IS NULL OR a.shop_id IS NULL OR a.tenant_id<>d.tenant_id OR a.shop_id<>d.shop_id) LIMIT 1')->getRowArray();
        if ($orphan) {
            throw new \RuntimeException('Audit Device context needs investigation before the deferred FK can be applied. Do not rewrite Audit.');
        }
        $this->db->query('ALTER TABLE audit_events ADD CONSTRAINT fk_audit_events_device FOREIGN KEY (actor_device_id) REFERENCES registered_devices(id) ON UPDATE RESTRICT ON DELETE RESTRICT');
    }

    public function down(): void
    {
        if ($this->db->table('audit_events')->where('actor_device_id IS NOT NULL', null, false)->countAllResults() > 0) {
            throw new \RuntimeException('Do not remove the Device integrity boundary around existing Audit evidence.');
        }
        $this->db->query('ALTER TABLE audit_events DROP FOREIGN KEY fk_audit_events_device');
    }
}
