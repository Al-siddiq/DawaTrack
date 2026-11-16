<?php

declare(strict_types=1);

namespace App\Modules\Reconciliation\Infrastructure;

use App\Exceptions\ApiException;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use App\Support\Uuid;
use CodeIgniter\Database\BaseConnection;

/** Internal scoped evidence only. Caller supplies established authority and transaction. */
class ControlRepository
{
    public function __construct(private readonly BaseConnection $database, private readonly string $scope = 'CASH')
    {
        if (!in_array($scope, ['CASH', 'STOCK', 'FULL'], true)) {
            throw new \InvalidArgumentException('Use a server-selected reconciliation scope.');
        }
    }

    public function startAction(): string
    {
        return $this->scope === 'CASH' ? 'reconciliation.cash_started' : 'reconciliation.control_started';
    }

    public function control(ShopContext $context, string $id, bool $lock = false, bool $required = true): ?array
    {
        $row = $this->database->query(
            "SELECT s.* FROM reconciliation_sessions s WHERE s.tenant_id=? AND s.shop_id=? AND s.public_id=? AND s.control_kind='STANDARD' AND s.scope=?" . ($lock ? ' FOR UPDATE' : ''),
            [$context->tenantId, $context->shopId, $id, $this->scope],
        )->getRowArray();
        if (!$row && $required) {
            throw new ApiException('CASH_CONTROL_NOT_FOUND', 'The Cash control is unavailable.', 404);
        }
        if ($row) {
            $generation = $this->database->table('sync_generations')->select('generation_uuid')->where('id', $row['sync_generation_id'])->get()->getRowArray();
            if (!$generation) {
                $this->invalid();
            }
            $row['generation_uuid'] = $generation['generation_uuid'];
        }
        return $row;
    }

    public function count(ShopContext $context, int $control, bool $lock = false): array
    {
        $rows = $this->database->query(
            "SELECT * FROM reconciliation_financial_counts WHERE tenant_id=? AND shop_id=? AND reconciliation_session_id=? AND channel='CASH'" . ($lock ? ' FOR UPDATE' : ''),
            [$context->tenantId, $context->shopId, $control],
        )->getResultArray();
        if (count($rows) !== 1) {
            $this->invalid();
        }
        return $rows[0];
    }

    /** Verify complete immutable membership against its parent start Audit and count/digest. */
    public function readiness(ShopContext $context, array $control): array
    {
        $roots = $this->database->query(
            "SELECT id,change_metadata FROM audit_events WHERE tenant_id=? AND shop_id=? AND resource_type='reconciliation_session' AND resource_public_id=? AND action_code=? AND category='RECONCILIATION'",
            [$context->tenantId, $context->shopId, $control['public_id'], $this->startAction()],
        )->getResultArray();
        if (count($roots) !== 1) {
            $this->invalid();
        }
        $root = json_decode((string) $roots[0]['change_metadata'], true);
        if (!is_array($root) || !is_int($root['device_count'] ?? null) || !is_string($root['device_digest'] ?? null)
            || ($root['scope'] ?? null) !== $control['scope'] || ($root['cutoff_at'] ?? null) !== $control['cutoff_at']
            || ($root['generation_uuid'] ?? null) !== $control['generation_uuid']) {
            $this->invalid();
        }
        $rows = $this->database->query(
            "SELECT a.change_metadata,d.id device_id,d.public_id,d.status device_status,k.readiness_status,k.acknowledged_change_sequence,k.reason,k.decided_at,u.username decided_by
             FROM audit_events a
             LEFT JOIN registered_devices d ON d.tenant_id=a.tenant_id AND d.shop_id=a.shop_id AND d.public_id=JSON_UNQUOTE(JSON_EXTRACT(a.change_metadata,'$.device_public_id'))
             LEFT JOIN reconciliation_device_acknowledgements k ON k.tenant_id=a.tenant_id AND k.shop_id=a.shop_id AND k.reconciliation_session_id=? AND k.registered_device_id=d.id
             LEFT JOIN users u ON u.id=k.decided_by_user_id
             WHERE a.tenant_id=? AND a.shop_id=? AND a.resource_type='reconciliation_session' AND a.resource_public_id=? AND a.action_code='reconciliation.device_required' AND a.category='RECONCILIATION' AND a.caused_by_audit_event_id=? ORDER BY d.public_id",
            [$control['id'], $context->tenantId, $context->shopId, $control['public_id'], $roots[0]['id']],
        )->getResultArray();
        $ids = [];
        $out = [];
        $decided = 0;
        foreach ($rows as $row) {
            $metadata = json_decode((string) $row['change_metadata'], true);
            if (!$row['device_id'] || !Uuid::isV4((string) $row['public_id']) || !is_array($metadata)
                || ($metadata['device_public_id'] ?? null) !== $row['public_id']
                || !is_string($metadata['display_name'] ?? null) || mb_strlen($metadata['display_name']) > 100) {
                $this->invalid();
            }
            if ($row['readiness_status'] === 'ACKNOWLEDGED' && (string) $row['acknowledged_change_sequence'] !== (string) $control['cutoff_change_sequence']) {
                $this->invalid();
            }
            if ($row['readiness_status'] !== null) {
                if (!in_array($row['readiness_status'], ['ACKNOWLEDGED', 'EXCLUDED'], true)) {
                    $this->invalid();
                }
                $decided++;
            }
            $ids[] = $row['public_id'];
            $out[] = ['public_id' => $row['public_id'], 'display_name' => $metadata['display_name'],
                'device_status' => $row['device_status'], 'decision' => $row['readiness_status'],
                'reason' => $row['reason'], 'decided_at' => $row['decided_at'], 'decided_by' => $row['decided_by'],
                '_id' => (int) $row['device_id']];
        }
        sort($ids, SORT_STRING);
        if (count(array_unique($ids)) !== count($ids) || count($ids) !== $root['device_count']
            || !hash_equals($root['device_digest'], hash('sha256', implode("\n", $ids)))) {
            $this->invalid();
        }
        $count = $this->database->table('reconciliation_device_acknowledgements')->where([
            'tenant_id' => $context->tenantId, 'shop_id' => $context->shopId, 'reconciliation_session_id' => $control['id'],
        ])->countAllResults();
        if ($count !== $decided) {
            $this->invalid();
        }
        return ['required' => count($ids), 'resolved' => $decided, 'complete' => $decided === count($ids), 'devices' => $out];
    }

    public function variance(ShopContext $context, int $control, ?string $publicId = null): ?array
    {
        $where = ['tenant_id' => $context->tenantId, 'shop_id' => $context->shopId, 'reconciliation_session_id' => $control];
        if ($publicId !== null) {
            $where['public_id'] = $publicId;
        } else {
            $where['variance_kind'] = 'CASH';
        }
        return $this->database->table('reconciliation_variances')->where($where)->get()->getRowArray();
    }

    private function invalid(): never
    {
        throw new ApiException('CASH_CONTROL_EVIDENCE_INVALID', 'This control has inconsistent evidence. Stop and ask the Owner to investigate without editing saved records.', 409);
    }
}
