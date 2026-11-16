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
final class CashControlQuery
{
    private readonly CashControlRepository $repository;

    public function __construct(private readonly BaseConnection $database, private readonly CommercialAccess $access)
    {
        $this->repository = new CashControlRepository($database);
    }

    private function read(ShopContext $context, string $permission = 'reconciliation.view'): void
    {
        $this->access->read($context, 'reconciliation.view');
        $this->access->read($context, $permission);
        (new CashControlSchema($this->database))->requireReady();
    }

    public function history(ShopContext $context, int $page, int $perPage): array
    {
        $this->read($context);
        return (new DebtReadSnapshot($this->database))->run(function () use ($context, $page, $perPage): array {
            $where = ['tenant_id' => $context->tenantId, 'shop_id' => $context->shopId, 'scope' => 'CASH', 'control_kind' => 'STANDARD'];
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
            $count = $this->repository->count($context, (int) $control['id']);
            $readiness = $this->repository->readiness($context, $control);
            $reviewer = $this->access->allows($context, 'reconciliation.review');
            $counter = $this->access->allows($context, 'reconciliation.count');
            $safe = array_intersect_key($control, array_flip(['public_id', 'scope', 'status', 'cash_period_starts_at', 'cutoff_at',
                'generation_uuid', 'started_at', 'counts_committed_at', 'finalized_at', 'aborted_at', 'abort_reason']));
            $safe['cutoff_change_sequence'] = (string) $control['cutoff_change_sequence'];
            $safe['readiness'] = ['required' => $readiness['required'], 'resolved' => $readiness['resolved'], 'complete' => $readiness['complete']];
            $blind = (new \App\Modules\Reconciliation\Application\BlindReadPolicy($this->database))->blocksScope($context, 'CASH');
            $safe['can_review_values'] = $reviewer && $control['counts_committed_at'] !== null && !$blind;
            if (($counter || $reviewer) && (!$blind || $control['status'] === 'COUNTING')) {
                $safe['count'] = ['actual_amount' => $count['actual_amount'], 'revision' => $count['updated_at'], 'saved_at' => $count['counted_at'], 'committed_at' => $count['committed_at']];
            }
            if ($safe['can_review_values']) {
                $safe['snapshot'] = array_intersect_key($count, array_flip(['opening_baseline_amount', 'cash_sale_receipts', 'cash_sale_refunds',
                    'cash_debt_receipts', 'cash_introductions', 'cash_expenses', 'cash_withdrawals', 'cash_corrections_net', 'expected_amount',
                    'actual_amount', 'variance_amount', 'explanation']));
                $variance = $this->repository->variance($context, (int) $control['id']);
                $safe['variance_public_id'] = $variance['public_id'] ?? null;
            }
            $prior = $this->database->table('reconciliation_sessions')->select('public_id,control_kind')->where([
                'tenant_id' => $context->tenantId, 'shop_id' => $context->shopId, 'id' => $control['previous_cash_session_id'],
            ])->get()->getRowArray();
            $safe['previous_cash_public_id'] = $prior['public_id'] ?? null;
            $safe['previous_cash_kind'] = $prior['control_kind'] ?? null;
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
            foreach ($devices as &$device) {
                unset($device['_id']);
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
            throw new ApiException('CASH_CONTROL_STATE_CONFLICT', 'This control is no longer accepting readiness decisions.', 409);
        }
        $device = (new DeviceQuery($this->database))->authenticated($context, $cookie);
        if (!$device || $device['status'] !== 'ACTIVE') {
            throw new ApiException('DEVICE_PROOF_REQUIRED', 'Use an active browser installation registered by the Owner.', 409);
        }
        $state = $this->repository->readiness($context, $control);
        if (!in_array($device['public_id'], array_column($state['devices'], 'public_id'), true)) {
            throw new ApiException('DEVICE_NOT_RELEVANT', 'This Device was not active in the pinned start set.', 409);
        }
        $active = $this->database->table('sync_generations')->select('generation_uuid')->where('status', 'ACTIVE')->get()->getResultArray();
        if (count($active) !== 1 || $active[0]['generation_uuid'] !== $control['generation_uuid']) {
            throw new ApiException('CASH_CONTROL_GENERATION_CHANGED', 'The server generation changed. Abort safely and start a new control.', 409);
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
        if ($control['counts_committed_at'] !== null) {
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
            throw new ApiException('VALIDATION_FAILED', 'Use a shorter member search.', 422);
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
        if ((new \App\Modules\Reconciliation\Application\BlindReadPolicy($this->database))->blocksScope($context, 'CASH')) {
            throw new ApiException('BLIND_COUNT_ACTIVE', 'Commit the active physical count before reading historical variance notes.', 409);
        }
        $control = $this->repository->control($context, $id);
        if ($control['status'] !== 'FINALIZED') {
            throw new ApiException('CASH_CONTROL_STATE_CONFLICT', 'Later notes belong to finalized variance evidence.', 409);
        }
        $where = ['n.tenant_id' => $context->tenantId, 'n.shop_id' => $context->shopId, 'n.reconciliation_session_id' => $control['id']];
        $total = $this->database->table('reconciliation_variance_notes n')->where($where)->countAllResults();
        $rows = $this->database->table('reconciliation_variance_notes n')->select('n.public_id,n.note_type,n.note_text,n.created_at,u.username author_name')
            ->join('users u', 'u.id=n.created_by_user_id')->where($where)->orderBy('n.created_at', 'DESC')->orderBy('n.id', 'DESC')
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
            throw new ApiException('VALIDATION_FAILED', 'Choose a Sale, Debt payment or Money movement reference.', 422);
        }
        [$table, $permissions] = $map[$kind];
        foreach ($permissions as $permission) {
            $this->access->read($context, $permission);
        }
        $row = $this->database->table($table)->select($kind === 'sale' ? 'public_id,status' : 'public_id')->where([
            'tenant_id' => $context->tenantId, 'shop_id' => $context->shopId, 'public_id' => $id,
        ])->get()->getRowArray();
        return ['public_id' => $id, 'found' => $row !== null, 'state' => $row ? ($row['status'] ?? 'POSTED') : 'NOT_FOUND_AT_READ',
            'guidance' => 'A missing record is inconclusive while a request may still finish. Keep the same reference; inspect full details after count commitment before resolving a changed-command conflict.'];
    }
}
