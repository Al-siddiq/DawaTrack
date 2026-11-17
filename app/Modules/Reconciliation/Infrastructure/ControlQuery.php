<?php

declare(strict_types=1);

namespace App\Modules\Reconciliation\Infrastructure;

use App\Exceptions\ApiException;
use App\Modules\CustomerDebt\Infrastructure\DebtReadSnapshot;
use App\Modules\Device\Infrastructure\DeviceQuery;
use App\Modules\Reconciliation\Application\ReadinessProof;
use App\Modules\Sales\Application\CommercialAccess;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use CodeIgniter\Database\BaseConnection;

/** Blind and review DTOs are distinct allowlists, never serialized database rows. */
class ControlQuery
{
    private readonly ControlRepository $repository;

    public function __construct(private readonly BaseConnection $database, private readonly CommercialAccess $access, private readonly string $scope = 'CASH')
    {
        $this->repository = new ControlRepository($database, $scope);
    }

    private function read(ShopContext $context, string $permission = 'reconciliation.view'): void
    {
        $this->access->read($context, 'reconciliation.view');
        $this->access->read($context, $permission);
        (new CashControlSchema($this->database))->requireReady();
        if ($this->hasStock()) {
            (new StockControlSchema($this->database))->requireReady();
        }
    }

    public function history(ShopContext $context, int $page, int $perPage): array
    {
        $this->read($context);
        return (new DebtReadSnapshot($this->database))->run(function () use ($context, $page, $perPage): array {
            $where = ['tenant_id' => $context->tenantId, 'shop_id' => $context->shopId, 'scope' => $this->scope, 'control_kind' => 'STANDARD'];
            $total = $this->database->table('reconciliation_sessions')->where($where)->countAllResults();
            $rows = $this->database->table('reconciliation_sessions')->select('public_id,status,started_at,cutoff_at,finalized_at,aborted_at')
                ->where($where)->orderBy('started_at', 'DESC')->orderBy('id', 'DESC')->get($perPage, ($page - 1) * $perPage)->getResultArray();
            return ['controls' => $rows, 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => (int) ceil($total / $perPage)]];
        });
    }

    public function detail(ShopContext $context, string $id, ?string $cookie): array
    {
        $this->read($context);
        return (new DebtReadSnapshot($this->database))->run(function () use ($context, $id, $cookie): array {
            $control = $this->repository->control($context, $id);
            $count = $this->hasCash() ? $this->repository->count($context, (int) $control['id']) : null;
            $stock = $this->hasStock() ? (new StockCountEvidence($this->database))->progress($context, $control) : null;
            $readiness = $this->repository->readiness($context, $control);
            $reviewer = $this->access->allows($context, 'reconciliation.review');
            $counter = $this->access->allows($context, 'reconciliation.count');
            $safe = array_intersect_key($control, array_flip(['public_id', 'scope', 'status', 'cash_period_starts_at', 'stock_period_starts_at', 'cutoff_at',
                'generation_uuid', 'started_at', 'counts_committed_at', 'finalized_at', 'aborted_at', 'abort_reason']));
            $safe['cutoff_change_sequence'] = (string) $control['cutoff_change_sequence'];
            $safe['readiness'] = ['required' => $readiness['required'], 'resolved' => $readiness['resolved'], 'complete' => $readiness['complete']];
            $blind = $this->blind($context);
            if ($blind || !$reviewer) {
                unset($safe['abort_reason']);
            }
            $safe['can_review_values'] = $reviewer && $control['counts_committed_at'] !== null && !$blind;
            if ($count !== null && ($counter || $reviewer) && (!$blind || $control['status'] === 'COUNTING')) {
                $safe['count'] = ['actual_amount' => $count['actual_amount'], 'revision' => $count['updated_at'], 'saved_at' => $count['counted_at'], 'committed_at' => $count['committed_at']];
            }
            if ($count !== null && $safe['can_review_values']) {
                $safe['snapshot'] = array_intersect_key($count, array_flip(['opening_baseline_amount', 'cash_sale_receipts', 'cash_sale_refunds',
                    'cash_debt_receipts', 'cash_introductions', 'cash_expenses', 'cash_withdrawals', 'cash_corrections_net', 'expected_amount',
                    'actual_amount', 'variance_amount', 'explanation']));
                $variance = $this->repository->variance($context, (int) $control['id']);
                $safe['variance_public_id'] = $variance['public_id'] ?? null;
            }
            $prior = $this->database->table('reconciliation_sessions')->select('public_id,control_kind,scope')->where([
                'tenant_id' => $context->tenantId, 'shop_id' => $context->shopId, 'id' => $control['previous_cash_session_id'],
            ])->get()->getRowArray();
            $safe['previous_cash_public_id'] = $prior['public_id'] ?? null;
            $safe['previous_cash_kind'] = $prior['control_kind'] ?? null;
            $safe['previous_cash_scope'] = $prior['scope'] ?? null;
            $priorStock = $this->database->table('reconciliation_sessions')->select('public_id,scope')->where([
                'tenant_id' => $context->tenantId, 'shop_id' => $context->shopId, 'id' => $control['previous_stock_session_id'],
            ])->get()->getRowArray();
            $safe['previous_stock_public_id'] = $priorStock['public_id'] ?? null;
            $safe['previous_stock_scope'] = $priorStock['scope'] ?? null;
            $safe['has_cash'] = $this->hasCash();
            $safe['has_stock'] = $this->hasStock();
            if ($this->hasStock()) {
                $safe['stock_progress'] = $stock;
                if ($counter || $reviewer) {
                    $safe['commit_revision'] = $control['status'] === 'COUNTING'
                        ? (new StockCountEvidence($this->database))->commitRevision($context, $control, $count, $stock) : null;
                }
                $latest = $this->database->query("SELECT id FROM reconciliation_sessions WHERE tenant_id=? AND shop_id=? AND scope IN ('STOCK','FULL') AND status='FINALIZED' ORDER BY cutoff_at DESC,id DESC LIMIT 1", [$context->tenantId, $context->shopId])->getRowArray();
                $activeStock = $this->database->query("SELECT id FROM reconciliation_sessions WHERE tenant_id=? AND shop_id=? AND scope IN ('STOCK','FULL') AND status IN ('COUNTING','COUNTS_COMMITTED','REVIEW') LIMIT 1", [$context->tenantId, $context->shopId])->getRowArray();
                $safe['stock_adjustment_eligible'] = $safe['can_review_values'] && $control['status'] === 'FINALIZED' && $latest && (int) $latest['id'] === (int) $control['id'] && !$activeStock;
            }
            $active = $this->database->table('sync_generations')->select('generation_uuid')->where('status', 'ACTIVE')->get()->getResultArray();
            $safe['generation_changed'] = count($active) !== 1 || $active[0]['generation_uuid'] !== $control['generation_uuid'];
            $current = (new DeviceQuery($this->database))->authenticated($context, $cookie);
            $safe['current_device_public_id'] = $current && $current['status'] === 'ACTIVE' ? $current['public_id'] : null;
            $safe['current_device_required'] = $safe['current_device_public_id'] !== null && in_array($safe['current_device_public_id'], array_column($readiness['devices'], 'public_id'), true);
            return $safe;
        });
    }

    public function readiness(ShopContext $context, string $id, int $page, int $perPage): array
    {
        $this->read($context);
        return (new DebtReadSnapshot($this->database))->run(function () use ($context, $id, $page, $perPage): array {
            $control = $this->repository->control($context, $id);
            $state = $this->repository->readiness($context, $control);
            $devices = array_slice($state['devices'], ($page - 1) * $perPage, $perPage);
            $showReasons = $this->access->allows($context, 'reconciliation.review') && !$this->blind($context)
                && ($control['counts_committed_at'] !== null || $control['status'] === 'ABORTED');
            foreach ($devices as &$device) {
                unset($device['_id']);
                if (!$showReasons) {
                    unset($device['reason']);
                }
            }
            unset($device);
            return ['devices' => $devices, 'required' => $state['required'], 'resolved' => $state['resolved'], 'complete' => $state['complete'],
                'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $state['required'], 'total_pages' => (int) ceil($state['required'] / $perPage)]];
        });
    }

    public function challenge(ShopContext $context, string $id, ?string $cookie, ReadinessProof $proof): array
    {
        $this->read($context);
        $control = $this->repository->control($context, $id);
        if (!in_array($control['status'], ['COUNTING', 'COUNTS_COMMITTED', 'REVIEW'], true)) {
            throw new ApiException('CASH_CONTROL_STATE_CONFLICT', 'You can no longer mark a phone or computer ready for this check.', 409);
        }
        $device = (new DeviceQuery($this->database))->authenticated($context, $cookie);
        if (!$device || $device['status'] !== 'ACTIVE') {
            throw new ApiException('DEVICE_PROOF_REQUIRED', 'Ask the owner to add this phone or computer under Shop devices first.', 409);
        }
        $state = $this->repository->readiness($context, $control);
        if (!in_array($device['public_id'], array_column($state['devices'], 'public_id'), true)) {
            throw new ApiException('DEVICE_NOT_RELEVANT', 'This phone or computer was not on the list when the check started.', 409);
        }
        $active = $this->database->table('sync_generations')->select('generation_uuid')->where('status', 'ACTIVE')->get()->getResultArray();
        if (count($active) !== 1 || $active[0]['generation_uuid'] !== $control['generation_uuid']) {
            throw new ApiException('CASH_CONTROL_GENERATION_CHANGED', 'DawaTrack has changed since this check started. Cancel this check and start again.', 409);
        }
        $target = ['control_public_id' => $id, 'device_public_id' => $device['public_id'], 'generation_uuid' => $control['generation_uuid'],
            'cutoff_at' => $control['cutoff_at'], 'sequence' => (string) $control['cutoff_change_sequence']];
        return ['target' => $target, 'proof' => $proof->encode([...$target, 'user_id' => $context->userId, 'membership_id' => $context->membershipId])];
    }

    public function staff(ShopContext $context, string $id, int $page, int $perPage): array
    {
        $this->read($context, 'reconciliation.review');
        $control = $this->repository->control($context, $id);
        $where = ['c.tenant_id' => $context->tenantId, 'c.shop_id' => $context->shopId, 'c.reconciliation_session_id' => $control['id']];
        $total = $this->database->table('reconciliation_staff_contexts c')->where($where)->countAllResults();
        $selection = 'm.public_id membership_public_id,u.username display_name,c.context_role,c.context_starts_at,c.context_ends_at';
        if ($control['counts_committed_at'] !== null && !$this->blind($context)) {
            $selection .= ',c.notes';
        }
        $rows = $this->database->table('reconciliation_staff_contexts c')->select($selection)
            ->join('tenant_memberships m', 'm.tenant_id=c.tenant_id AND m.id=c.membership_id AND m.user_id=c.user_id')
            ->join('users u', 'u.id=c.user_id')->where($where)->orderBy('c.id')->get($perPage, ($page - 1) * $perPage)->getResultArray();
        return ['contexts' => $rows, 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => (int) ceil($total / $perPage)]];
    }

    public function members(ShopContext $context, string $search, int $page, int $perPage): array
    {
        $this->read($context, 'reconciliation.start');
        if (mb_strlen($search) > 100) {
            throw new ApiException('VALIDATION_FAILED', 'Use fewer words to find a person.', 422);
        }
        $builder = $this->database->table('tenant_memberships m')->join('users u', 'u.id=m.user_id')
            ->where(['m.tenant_id' => $context->tenantId, 'm.status' => 'ACTIVE']);
        if ($search !== '') {
            $builder->like('u.username', $search);
        }
        $total = (clone $builder)->countAllResults();
        $rows = $builder->select('m.public_id,u.username display_name')->orderBy('u.username')->orderBy('m.id')->get($perPage, ($page - 1) * $perPage)->getResultArray();
        return ['members' => $rows, 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => (int) ceil($total / $perPage)]];
    }

    public function notes(ShopContext $context, string $id, int $page, int $perPage): array
    {
        $this->read($context, 'reconciliation.review');
        if ($this->blind($context)) {
            throw new ApiException('BLIND_COUNT_ACTIVE', 'Confirm the current count before opening notes from past checks.', 409);
        }
        $control = $this->repository->control($context, $id);
        if ($control['status'] !== 'FINALIZED') {
            throw new ApiException('CASH_CONTROL_STATE_CONFLICT', 'Finish the check before adding a later note.', 409);
        }
        $where = ['n.tenant_id' => $context->tenantId, 'n.shop_id' => $context->shopId, 'n.reconciliation_session_id' => $control['id']];
        $total = $this->database->table('reconciliation_variance_notes n')->where($where)->countAllResults();
        $rows = $this->database->table('reconciliation_variance_notes n')->select('n.public_id,n.note_type,n.note_text,n.created_at,u.username author_name,v.public_id variance_public_id,v.variance_kind')
            ->join('users u', 'u.id=n.created_by_user_id')->join('reconciliation_variances v', 'v.tenant_id=n.tenant_id AND v.shop_id=n.shop_id AND v.reconciliation_session_id=n.reconciliation_session_id AND v.id=n.reconciliation_variance_id')->where($where)->orderBy('n.created_at', 'DESC')->orderBy('n.id', 'DESC')
            ->get($perPage, ($page - 1) * $perPage)->getResultArray();
        return ['notes' => $rows, 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => (int) ceil($total / $perPage)]];
    }

    /** Blind-safe existence/state proof only. Never certifies that a changed command body was accepted. */
    public function sourceStatus(ShopContext $context, string $controlId, string $kind, string $id): array
    {
        $this->read($context);
        $this->repository->control($context, $controlId);
        $map = ['sale' => ['sales', ['sale.view']], 'debt_payment' => ['debt_payments', ['customer.view', 'debt.view']],
            'financial_movement' => ['financial_movements', ['financial.view']]];
        if (!isset($map[$kind])) {
            throw new ApiException('VALIDATION_FAILED', 'Choose a sale, customer payment or money record.', 422);
        }
        [$table, $permissions] = $map[$kind];
        foreach ($permissions as $permission) {
            $this->access->read($context, $permission);
        }
        $row = $this->database->table($table)->select($kind === 'sale' ? 'public_id,status' : 'public_id')->where([
            'tenant_id' => $context->tenantId, 'shop_id' => $context->shopId, 'public_id' => $id,
        ])->get()->getRowArray();
        return ['public_id' => $id, 'found' => $row !== null, 'state' => $row ? ($row['status'] ?? 'POSTED') : 'NOT_FOUND_AT_READ',
            'guidance' => 'Your earlier save may still finish. Keep the same record number. Confirm the count before opening the full record to check its details.'];
    }
    public function stockCounts(ShopContext $context, string $id, string $search, int $page, int $perPage): array
    {
        $this->read($context);
        if (!$this->hasStock() || mb_strlen($search) > 100) {
            throw new ApiException('VALIDATION_FAILED', 'Open a stock check and use a short item name to search.', 422);
        }
        return (new DebtReadSnapshot($this->database))->run(function () use ($context, $id, $search, $page, $perPage): array {
            $control = $this->repository->control($context, $id);
            $slice = (new StockCountEvidence($this->database))->page($context, $control, $search, $page, $perPage);
            $rows = $slice['rows'];
            $blind = $this->blind($context);
            $review = $this->access->allows($context, 'reconciliation.review') && $control['counts_committed_at'] !== null && !$blind;
            $observe = ($this->access->allows($context, 'reconciliation.count') || $this->access->allows($context, 'reconciliation.review'))
                && (!$blind || $control['status'] === 'COUNTING');
            $variances = [];
            if ($review && $control['status'] === 'FINALIZED' && $rows !== []) {
                $marks = implode(',', array_fill(0, count($rows), '?'));
                $links = $this->database->query("SELECT v.stock_count_id,v.public_id variance_public_id,v.quantity_variance,m.public_id adjustment_public_id,m.posted_at adjusted_at FROM reconciliation_variances v LEFT JOIN stock_movements m ON m.tenant_id=v.tenant_id AND m.shop_id=v.shop_id AND m.reconciliation_variance_id=v.id AND m.source_kind='RECON_VARIANCE' AND m.movement_type='RECONCILIATION_ADJUSTMENT' WHERE v.tenant_id=? AND v.shop_id=? AND v.reconciliation_session_id=? AND v.variance_kind='STOCK' AND v.stock_count_id IN ($marks) LIMIT ?", [$context->tenantId, $context->shopId, $control['id'], ...array_column($rows, 'id'), count($rows) + 1])->getResultArray();
                foreach ($links as $link) {
                    if (isset($variances[$link['stock_count_id']])) {
                        throw new ApiException('STOCK_CONTROL_EVIDENCE_INVALID', 'More than one stock change was found for this difference. Keep the records and ask the shop owner for help.', 409);
                    }
                    $variances[$link['stock_count_id']] = $link;
                }
            }
            $total = $slice['total'];
            $out = [];
            foreach ($rows as $row) {
                $safe = ['product_public_id' => $row['product_public_id'], 'name' => $row['name'], 'unit_name' => $row['unit_name']];
                if ($observe) {
                    $safe += ['actual_quantity' => $row['actual_quantity'], 'revision' => $row['updated_at'], 'saved_at' => $row['counted_at'], 'committed_at' => $row['committed_at']];
                }
                if ($review) {
                    $safe += array_intersect_key($row, array_flip(['expected_quantity', 'expected_average_unit_cost', 'cost_basis_state', 'variance_quantity', 'explanation']));
                    $link = $variances[$row['id']] ?? null;
                    if ($control['status'] === 'FINALIZED' && $row['variance_quantity'] !== '0.0000'
                        && (!$link || $link['quantity_variance'] !== $row['variance_quantity'])) {
                        throw new ApiException('STOCK_CONTROL_EVIDENCE_INVALID', 'The saved stock numbers do not match. Keep them as they are and ask the shop owner for help.', 409);
                    }
                    $safe['variance_public_id'] = $link['variance_public_id'] ?? null;
                    $safe['adjustment'] = $link && $link['adjustment_public_id'] ? ['public_id' => $link['adjustment_public_id'], 'posted_at' => $link['adjusted_at']] : null;
                }
                $out[] = $safe;
            }
            return ['counts' => $out, 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => (int) ceil($total / $perPage)]];
        });
    }

    public function adjustmentStatus(ShopContext $context, string $id, string $movement): array
    {
        $this->read($context, 'reconciliation.review');
        $this->access->read($context, 'reconciliation.adjust');
        $control = $this->repository->control($context, $id);
        $row = $this->database->query("SELECT m.public_id,v.public_id variance_public_id,m.posted_at FROM stock_movements m JOIN reconciliation_variances v ON v.tenant_id=m.tenant_id AND v.shop_id=m.shop_id AND v.id=m.reconciliation_variance_id WHERE m.tenant_id=? AND m.shop_id=? AND v.reconciliation_session_id=? AND m.public_id=? AND m.source_kind='RECON_VARIANCE' AND m.movement_type='RECONCILIATION_ADJUSTMENT'",
            [$context->tenantId, $context->shopId, $control['id'], $movement])->getRowArray();
        return ['found' => $row !== null, 'movement' => $row, 'control_public_id' => $id,
            'guidance' => 'Your earlier save may still finish. Keep the same stock change and difference numbers. Do not start another change.'];
    }

    public function lateOperations(ShopContext $context, string $id, int $page, int $perPage): array
    {
        $this->read($context, 'reconciliation.review');
        $control = $this->repository->control($context, $id);
        if ($control['counts_committed_at'] === null || $this->blind($context)) {
            throw new ApiException('BLIND_COUNT_ACTIVE', 'Confirm the count before opening the related shop records.', 409);
        }
        $where = ['tenant_id' => $context->tenantId, 'shop_id' => $context->shopId, 'resource_type' => 'reconciliation_session',
            'resource_public_id' => $id, 'action_code' => 'reconciliation.late_operation_associated'];
        $total = $this->database->table('audit_events')->where($where)->countAllResults();
        $rows = $this->database->table('audit_events')->select('public_id,change_metadata,created_at')->where($where)->orderBy('id', 'DESC')->get($perPage, ($page - 1) * $perPage)->getResultArray();
        $out = [];
        foreach ($rows as $row) {
            $metadata = json_decode((string) $row['change_metadata'], true);
            if (!is_array($metadata)) {
                throw new ApiException('CASH_CONTROL_EVIDENCE_INVALID', 'A shop record added later needs checking. Keep it as it is and ask the shop owner for help.', 409);
            }
            $out[] = ['public_id' => $row['public_id'], 'created_at' => $row['created_at'], ...array_intersect_key($metadata, array_flip([
                'source_resource_type', 'source_public_id', 'affected_scope', 'occurred_at', 'posted_at', 'cutoff_at']))];
        }
        return ['associations' => $out, 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => (int) ceil($total / $perPage)]];
    }

    private function hasCash(): bool { return in_array($this->scope, ['CASH', 'FULL'], true); }
    private function hasStock(): bool { return in_array($this->scope, ['STOCK', 'FULL'], true); }
    private function blind(ShopContext $context): bool
    {
        $policy = new \App\Modules\Reconciliation\Application\BlindReadPolicy($this->database);
        return ($this->hasCash() && $policy->blocksScope($context, 'CASH')) || ($this->hasStock() && $policy->blocksScope($context, 'STOCK'));
    }

}
