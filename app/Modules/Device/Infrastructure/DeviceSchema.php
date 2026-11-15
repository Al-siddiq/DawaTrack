<?php

declare(strict_types=1);

namespace App\Modules\Device\Infrastructure;

use App\Exceptions\ApiException;
use CodeIgniter\Database\BaseConnection;

final class DeviceSchema
{
    public function __construct(private readonly BaseConnection $database) {}

    public function requireReady(): void
    {
        if (!$this->database->tableExists('registered_devices')) {
            $this->missing();
        }
        $fields = $this->database->getFieldNames('registered_devices');
        foreach (['public_id', 'device_uuid', 'tenant_id', 'shop_id', 'display_name', 'status', 'credential_hash',
            'registered_by_user_id', 'registered_at', 'last_validated_at', 'offline_trust_expires_at',
            'last_sync_started_at', 'last_sync_completed_at', 'last_seen_at', 'revoked_at',
            'revoked_by_user_id', 'revocation_reason', 'created_at', 'updated_at'] as $field) {
            if (!in_array($field, $fields, true)) {
                $this->missing();
            }
        }
        $fk = $this->database->query(
            "SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='audit_events' AND CONSTRAINT_NAME='fk_audit_events_device' AND REFERENCED_TABLE_NAME='registered_devices'",
        )->getRowArray();
        if (!$fk) {
            $this->missing();
        }
    }

    private function missing(): never
    {
        throw new ApiException('DEVICE_SETUP_REQUIRED', 'Device setup is incomplete. Ask the administrator to check the approved migrations.', 503);
    }
}
