<?php

declare(strict_types=1);

namespace App\Modules\Reconciliation\Application;

use App\Modules\Audit\Application\AuditService;
use App\Modules\Audit\Domain\{AuditActor, AuditAppendCommand, AuditCategory};
use App\Modules\Sync\Domain\ChangeCommand;
use CodeIgniter\Database\BaseConnection;

/** Never mutates a source/control/snapshot. Caller already owns the domain roots and transaction. */
final class LateOperationAssociation
{
    public function __construct(private readonly BaseConnection $database, private readonly AuditService $audit) {}

    public function associate(ChangeCommand $command): ?string
    {
        $tables = ['stock_movement' => ['stock_movements', 'STOCK'], 'financial_movement' => ['financial_movements', 'CASH'],
            'debt_payment' => ['debt_payments', 'CASH'], 'sale' => ['sales', 'CASH']];
        if ($command->shopId === null || !isset($tables[$command->resourceType]) || !$this->database->tableExists('reconciliation_sessions')) {
            return null;
        }
        [$table, $scope] = $tables[$command->resourceType];
        $source = $this->database->table($table)->where(['tenant_id' => $command->tenantId, 'shop_id' => $command->shopId,
            'public_id' => $command->resourcePublicId])->get()->getRowArray();
        if (!$source || $source['posted_at'] === null || $source['occurred_at'] >= $source['posted_at']) {
            return null;
        }
        if (in_array($command->resourceType, ['financial_movement', 'debt_payment'], true) && $source['channel'] !== 'CASH') {
            return null;
        }
        if ($command->resourceType === 'sale') {
            if ($source['status'] !== 'POSTED' || $this->database->table('sale_payments')->where([
                'tenant_id' => $command->tenantId, 'shop_id' => $command->shopId, 'sale_id' => $source['id'], 'channel' => 'CASH',
            ])->countAllResults() === 0) {
                return null;
            }
        }
        $control = $this->database->query("SELECT * FROM reconciliation_sessions WHERE tenant_id=? AND shop_id=? AND status='FINALIZED' AND scope IN (?, 'FULL') AND cutoff_at>=? AND cutoff_at<? ORDER BY cutoff_at DESC,id DESC LIMIT 1",
            [$command->tenantId, $command->shopId, $scope, $source['occurred_at'], $source['posted_at']])->getRowArray();
        if (!$control) {
            return null;
        }
        $exists = $this->database->query("SELECT id FROM audit_events WHERE tenant_id=? AND shop_id=? AND resource_type='reconciliation_session' AND resource_public_id=? AND action_code='reconciliation.late_operation_associated' AND JSON_UNQUOTE(JSON_EXTRACT(change_metadata,'$.source_resource_type'))=? AND JSON_UNQUOTE(JSON_EXTRACT(change_metadata,'$.source_public_id'))=? AND JSON_UNQUOTE(JSON_EXTRACT(change_metadata,'$.affected_scope'))=? LIMIT 1",
            [$command->tenantId, $command->shopId, $control['public_id'], $command->resourceType, $command->resourcePublicId, $scope])->getRowArray();
        if ($exists) {
            return null;
        }
        $this->audit->append(new AuditAppendCommand(AuditActor::system($command->tenantId, $command->shopId),
            'reconciliation.late_operation_associated', AuditCategory::RECONCILIATION, 'reconciliation_session', $control['public_id'],
            'Late accepted source associated with a finalized cutoff; original evidence remains unchanged.',
            ['source_resource_type' => $command->resourceType, 'source_public_id' => $command->resourcePublicId,
                'affected_scope' => $scope, 'occurred_at' => $source['occurred_at'], 'posted_at' => $source['posted_at'],
                'cutoff_at' => $control['cutoff_at']]));
        return $control['public_id'];
    }
}
