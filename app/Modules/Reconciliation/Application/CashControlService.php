<?php

declare(strict_types=1);

namespace App\Modules\Reconciliation\Application;

use App\Exceptions\ApiException;
use App\Modules\Audit\Application\AuditService;
use App\Modules\Audit\Domain\{AuditActor, AuditAppendCommand, AuditCategory};
use App\Modules\CustomerDebt\Domain\DebtAmount;
use App\Modules\Device\Infrastructure\DeviceQuery;
use App\Modules\Reconciliation\Infrastructure\{CashControlRepository, CashControlSchema, ExpectedCashQuery};
use App\Modules\Sales\Application\CommercialAccess;
use App\Modules\Sync\Application\ChangeSequenceService;
use App\Modules\Sync\Domain\ChangeCommand;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use App\Support\{UtcTimestamp, Uuid};
use CodeIgniter\Database\BaseConnection;

/** Owns standard CASH commands only; Stock/Full workflows remain WP3. */
final class CashControlService
{
    private readonly CashControlRepository $repository;

    public function __construct(
        private readonly BaseConnection $database,
        private readonly CommercialAccess $access,
        private readonly AuditService $audit,
        private readonly ChangeSequenceService $changes,
        private readonly string $readinessSecret,
    ) {
        $this->repository = new CashControlRepository($database);
    }

    public function start(ShopContext $context, array $input): string
    {
        CashControlInput::only($input, ['public_id', 'on_duty']);
        $id = CashControlInput::uuid($input['public_id'] ?? null);
        $onDuty = $this->normalizeDuty($input['on_duty'] ?? []);
        return $this->command($context, ['reconciliation.start'], function () use ($context, $id, $onDuty): string {
            $fingerprint = hash('sha256', json_encode($onDuty, JSON_THROW_ON_ERROR));
            $existing = $this->repository->control($context, $id, true, false);
            if ($existing) {
                $record = $this->database->query(
                    "SELECT change_metadata FROM audit_events WHERE tenant_id=? AND shop_id=? AND resource_type='reconciliation_session' AND resource_public_id=? AND action_code='reconciliation.cash_started'",
                    [$context->tenantId, $context->shopId, $id],
                )->getResultArray();
                $metadata = count($record) === 1 ? json_decode((string) $record[0]['change_metadata'], true) : null;
                if (!is_array($metadata) || !hash_equals((string) ($metadata['duty_fingerprint'] ?? ''), $fingerprint)) {
                    throw new ApiException('CASH_CONTROL_IDENTITY_CONFLICT', 'This control reference was already started with different responsibility context.', 409);
                }
                return $id;
            }
            // New controls require working readiness setup; abort/recovery never depend on decrypting a receipt.
            new ReadinessProof($this->readinessSecret);
            // Every source writer uses the same roots; no new Cash effect can cross this barrier.
            (new ControlScopeGuard($this->database))->assertAvailable($context, 'CASH');
            $prior = $this->database->query(
                "SELECT s.*,c.actual_amount,c.committed_at FROM reconciliation_sessions s JOIN reconciliation_financial_counts c ON c.tenant_id=s.tenant_id AND c.shop_id=s.shop_id AND c.reconciliation_session_id=s.id AND c.channel='CASH' WHERE s.tenant_id=? AND s.shop_id=? AND s.scope IN ('CASH','FULL') AND s.status='FINALIZED' ORDER BY s.cutoff_at DESC,s.id DESC LIMIT 1 FOR UPDATE",
                [$context->tenantId, $context->shopId],
            )->getRowArray();
            if (!$prior || $prior['actual_amount'] === null || $prior['committed_at'] === null) {
                throw new ApiException('OPENING_CASH_REQUIRED', 'Finalize Starting cash before beginning a standard Cash control.', 409);
            }
            $generation = $this->activeGeneration();
            $now = UtcTimestamp::now();
            if ($now <= $prior['cutoff_at']) {
                throw new ApiException('CASH_CONTROL_CLOCK_CONFLICT', 'The server time is not later than the previous Cash cutoff. Ask the administrator to check the clock before starting.', 409);
            }
            $expected = (new ExpectedCashQuery($this->database))->calculate($context, $prior['cutoff_at'], $now, (string) $prior['actual_amount']);
            $devices = $this->database->query(
                "SELECT id,public_id,display_name FROM registered_devices WHERE tenant_id=? AND shop_id=? AND status='ACTIVE' ORDER BY public_id FOR UPDATE",
                [$context->tenantId, $context->shopId],
            )->getResultArray();
            $this->database->table('reconciliation_sessions')->insert([
                'public_id' => $id, 'tenant_id' => $context->tenantId, 'shop_id' => $context->shopId,
                'scope' => 'CASH', 'control_kind' => 'STANDARD', 'status' => 'COUNTING',
                'cash_period_starts_at' => $prior['cutoff_at'], 'cutoff_at' => $now, 'cutoff_change_sequence' => 0,
                'sync_generation_id' => $generation['id'], 'previous_cash_session_id' => $prior['id'],
                'started_by_user_id' => $context->userId, 'started_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $internal = (int) $this->database->insertID();
            $snapshot = array_intersect_key($expected, array_flip(['opening_baseline_amount', 'cash_sale_receipts', 'cash_sale_refunds',
                'cash_debt_receipts', 'cash_introductions', 'cash_expenses', 'cash_withdrawals', 'cash_corrections_net', 'expected_amount']));
            $this->database->table('reconciliation_financial_counts')->insert([
                'tenant_id' => $context->tenantId, 'shop_id' => $context->shopId, 'reconciliation_session_id' => $internal,
                'channel' => 'CASH', ...$snapshot, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $ids = array_column($devices, 'public_id');
            sort($ids, SORT_STRING);
            $root = $this->record($context, $id, 'reconciliation.cash_started', 'Standard blind Cash control started.', [
                'scope' => 'CASH', 'cutoff_at' => $now, 'generation_uuid' => $generation['generation_uuid'], 'previous_control_public_id' => $prior['public_id'],
                'device_count' => count($ids), 'device_digest' => hash('sha256', implode("\n", $ids)), 'duty_fingerprint' => $fingerprint,
            ]);
            foreach ($devices as $device) {
                $this->record($context, $id, 'reconciliation.device_required', 'Active Device pinned for this control.',
                    ['device_public_id' => $device['public_id'], 'display_name' => $device['display_name']], $root);
            }
            foreach ($onDuty as $duty) {
                $member = $this->database->query(
                    "SELECT id,user_id FROM tenant_memberships WHERE tenant_id=? AND public_id=? AND status='ACTIVE' FOR UPDATE",
                    [$context->tenantId, $duty['membership_public_id']],
                )->getRowArray();
                if (!$member || ($duty['ends_at'] !== null && $duty['ends_at'] > $now) || ($duty['starts_at'] !== null && $duty['starts_at'] > $now)) {
                    throw new ApiException('VALIDATION_FAILED', 'Choose active Shop members and duty times no later than the cutoff.', 422);
                }
                $this->staff($context, $internal, (int) $member['id'], (int) $member['user_id'], 'ON_DUTY', $duty['starts_at'], $duty['ends_at'], $duty['notes']);
            }
            // Final generation lock. All source/Device/member/count rows and Audit already exist.
            $watermark = $this->changes->acquireWatermark();
            if ($watermark->generationUuid !== $generation['generation_uuid']) {
                $this->generationChanged();
            }
            $this->database->table('reconciliation_sessions')->where('id', $internal)->update(['cutoff_change_sequence' => $watermark->changeSequence]);
            $this->changes->publish([new ChangeCommand($context->tenantId, $context->shopId, 'reconciliation_session', $id)]);
            return $id;
        });
    }

    public function count(ShopContext $context, string $id, array $input): string
    {
        CashControlInput::only($input, ['amount', 'expected_updated_at']);
        $amount = CashAmount::parse($input['amount'] ?? null);
        return $this->command($context, ['reconciliation.count'], function () use ($context, $id, $input, $amount): string {
            $control = $this->repository->control($context, $id, true);
            $count = $this->repository->count($context, (int) $control['id'], true);
            $this->status($control, ['COUNTING']);
            if ($count['actual_amount'] === $amount) {
                return $id;
            }
            $this->revision($count, $input);
            $now = UtcTimestamp::now();
            $this->database->table('reconciliation_financial_counts')->where('id', $count['id'])->update([
                'actual_amount' => $amount, 'variance_amount' => $this->difference($amount, (string) $count['expected_amount']),
                'counted_by_user_id' => $context->userId, 'counted_at' => $now, 'updated_at' => $now,
            ]);
            $this->record($context, $id, 'reconciliation.cash_counted', 'Physical Cash observation saved.');
            $this->finish($context, $control);
            return $id;
        });
    }

    public function commit(ShopContext $context, string $id, array $input): string
    {
        CashControlInput::only($input, ['expected_updated_at']);
        return $this->command($context, ['reconciliation.count', 'reconciliation.commit'], function () use ($context, $id, $input): string {
            $control = $this->repository->control($context, $id, true);
            $count = $this->repository->count($context, (int) $control['id'], true);
            if (in_array($control['status'], ['COUNTS_COMMITTED', 'REVIEW', 'FINALIZED'], true)) {
                return $id;
            }
            $this->status($control, ['COUNTING']);
            $this->revision($count, $input);
            $this->requireReady($context, $control);
            if ($count['actual_amount'] === null) {
                throw new ApiException('CASH_COUNT_REQUIRED', 'Save the physical Cash amount, including zero if none, before committing.', 422);
            }
            $now = UtcTimestamp::now();
            $this->database->table('reconciliation_financial_counts')->where('id', $count['id'])->update(['committed_at' => $now, 'updated_at' => $now]);
            $this->database->table('reconciliation_sessions')->where('id', $control['id'])->update([
                'status' => 'COUNTS_COMMITTED', 'counts_committed_by_user_id' => $context->userId, 'counts_committed_at' => $now, 'updated_at' => $now,
            ]);
            $observer = $this->database->query('SELECT id,user_id FROM tenant_memberships WHERE tenant_id=? AND user_id=? FOR UPDATE',
                [$context->tenantId, $count['counted_by_user_id']])->getRowArray();
            if (!$observer) {
                throw new ApiException('CASH_CONTROL_EVIDENCE_INVALID', 'The historical counter identity needs investigation.', 409);
            }
            $this->staff($context, (int) $control['id'], (int) $observer['id'], (int) $observer['user_id'], 'COUNTER');
            $this->record($context, $id, 'reconciliation.cash_committed', 'Physical Cash observation committed and locked.');
            $this->finish($context, $control);
            return $id;
        });
    }

    public function review(ShopContext $context, string $id): string
    {
        return $this->command($context, ['reconciliation.review'], function () use ($context, $id): string {
            $control = $this->repository->control($context, $id, true);
            if (in_array($control['status'], ['REVIEW', 'FINALIZED'], true)) {
                return $id;
            }
            $this->status($control, ['COUNTS_COMMITTED']);
            $this->database->table('reconciliation_sessions')->where('id', $control['id'])->update(['status' => 'REVIEW', 'updated_at' => UtcTimestamp::now()]);
            $this->staff($context, (int) $control['id'], $context->membershipId, $context->userId, 'REVIEWER');
            $this->record($context, $id, 'reconciliation.cash_reviewed', 'Frozen Cash evidence opened for review.');
            $this->finish($context, $control);
            return $id;
        });
    }

    public function explain(ShopContext $context, string $id, array $input): string
    {
        CashControlInput::only($input, ['explanation', 'expected_updated_at']);
        $explanation = CashControlInput::text($input['explanation'] ?? null, 500, 'explanation');
        return $this->command($context, ['reconciliation.review'], function () use ($context, $id, $input, $explanation): string {
            $control = $this->repository->control($context, $id, true);
            $this->status($control, ['REVIEW']);
            $count = $this->repository->count($context, (int) $control['id'], true);
            if ($count['explanation'] === $explanation) {
                return $id;
            }
            $this->revision($count, $input);
            $this->database->table('reconciliation_financial_counts')->where('id', $count['id'])->update([
                'explanation' => $explanation, 'updated_at' => UtcTimestamp::now(),
            ]);
            $this->staff($context, (int) $control['id'], $context->membershipId, $context->userId, 'REVIEWER');
            $this->record($context, $id, 'reconciliation.cash_explained', $explanation);
            $this->finish($context, $control);
            return $id;
        });
    }

    public function finalize(ShopContext $context, string $id): string
    {
        return $this->command($context, ['reconciliation.review', 'reconciliation.finalize'], function () use ($context, $id): string {
            $control = $this->repository->control($context, $id, true);
            if ($control['status'] === 'FINALIZED') {
                return $id;
            }
            $this->status($control, ['REVIEW']);
            $count = $this->repository->count($context, (int) $control['id'], true);
            $this->requireReady($context, $control);
            if ($count['actual_amount'] === null || $count['committed_at'] === null || $control['counts_committed_at'] === null) {
                throw new ApiException('CASH_COUNT_REQUIRED', 'Commit the physical Cash observation first.', 409);
            }
            $variance = $this->difference((string) $count['actual_amount'], (string) $count['expected_amount']);
            if ($variance !== (string) $count['variance_amount']) {
                throw new ApiException('CASH_CONTROL_EVIDENCE_INVALID', 'Saved Cash variance needs investigation.', 409);
            }
            if ($variance !== '0.00' && trim((string) $count['explanation']) === '') {
                throw new ApiException('VARIANCE_EXPLANATION_REQUIRED', 'Explain this shortage or surplus before finalizing. The explanation will not erase the variance.', 422);
            }
            $now = UtcTimestamp::now();
            if ($variance !== '0.00') {
                $this->database->table('reconciliation_variances')->insert([
                    'public_id' => Uuid::v4(), 'tenant_id' => $context->tenantId, 'shop_id' => $context->shopId,
                    'reconciliation_session_id' => $control['id'], 'variance_kind' => 'CASH', 'financial_count_id' => $count['id'],
                    'amount_variance' => $variance, 'explanation' => $count['explanation'], 'created_at' => $now,
                ]);
            }
            $this->staff($context, (int) $control['id'], $context->membershipId, $context->userId, 'FINALIZER');
            $this->database->table('reconciliation_sessions')->where('id', $control['id'])->update([
                'status' => 'FINALIZED', 'finalized_by_user_id' => $context->userId, 'finalized_at' => $now, 'updated_at' => $now,
            ]);
            $this->record($context, $id, 'reconciliation.cash_finalized', 'Cash control finalized with immutable observations and actual baseline rollover.',
                ['variance_amount' => $variance]);
            $this->finish($context, $control);
            return $id;
        });
    }

    public function abort(ShopContext $context, string $id, array $input): string
    {
        CashControlInput::only($input, ['reason']);
        $reason = CashControlInput::text($input['reason'] ?? null, 500);
        return $this->command($context, ['reconciliation.abort'], function () use ($context, $id, $reason): string {
            $control = $this->repository->control($context, $id, true);
            if ($control['status'] === 'ABORTED' && $control['abort_reason'] === $reason) {
                return $id;
            }
            $this->status($control, ['COUNTING', 'COUNTS_COMMITTED', 'REVIEW']);
            $now = UtcTimestamp::now();
            $this->database->table('reconciliation_sessions')->where('id', $control['id'])->update([
                'status' => 'ABORTED', 'aborted_by_user_id' => $context->userId, 'aborted_at' => $now, 'abort_reason' => $reason, 'updated_at' => $now,
            ]);
            $this->record($context, $id, 'reconciliation.cash_aborted', $reason);
            // A changed epoch must not trap a control forever. Abort never advances a baseline.
            $this->finish($context, $control, false);
            return $id;
        });
    }

    public function acknowledge(ShopContext $context, string $id, array $input, ?string $cookie): string
    {
        CashControlInput::only($input, ['proof']);
        $token = CashControlInput::text($input['proof'] ?? null, 4096, 'proof');
        return $this->command($context, [], function () use ($context, $id, $token, $cookie): string {
            $control = $this->repository->control($context, $id, true);
            $this->status($control, ['COUNTING', 'COUNTS_COMMITTED', 'REVIEW']);
            $device = (new DeviceQuery($this->database))->authenticated($context, $cookie, true);
            if (!$device || $device['status'] !== 'ACTIVE') {
                throw new ApiException('DEVICE_PROOF_REQUIRED', 'An active cookie-proven installation is required to acknowledge readiness.', 409);
            }
            $payload = (new ReadinessProof($this->readinessSecret))->decode($token);
            $target = ['control_public_id' => $id, 'device_public_id' => $device['public_id'], 'user_id' => $context->userId,
                'membership_id' => $context->membershipId, 'generation_uuid' => $control['generation_uuid'],
                'cutoff_at' => $control['cutoff_at'], 'sequence' => (string) $control['cutoff_change_sequence']];
            foreach ($target as $key => $value) {
                if (($payload[$key] ?? null) !== $value) {
                    throw new ApiException('READINESS_REFRESH_REQUIRED', 'This receipt belongs to another Device, user or control target. Check readiness again.', 409);
                }
            }
            $readiness = $this->repository->readiness($context, $control);
            $required = $this->required($readiness, $device['public_id']);
            if ($required['decision'] === 'ACKNOWLEDGED') {
                return $id;
            }
            if ($required['decision'] !== null) {
                throw new ApiException('READINESS_DECISION_CONFLICT', 'This Device already has an immutable readiness decision.', 409);
            }
            $now = UtcTimestamp::now();
            $this->database->table('reconciliation_device_acknowledgements')->insert([
                'tenant_id' => $context->tenantId, 'shop_id' => $context->shopId, 'reconciliation_session_id' => $control['id'],
                'registered_device_id' => $device['id'], 'readiness_status' => 'ACKNOWLEDGED',
                'acknowledged_change_sequence' => $control['cutoff_change_sequence'], 'decided_by_user_id' => $context->userId,
                'decided_at' => $now, 'created_at' => $now,
            ]);
            $this->database->table('registered_devices')->where('id', $device['id'])->update([
                'last_validated_at' => $now, 'last_seen_at' => $now, 'updated_at' => $now,
            ]);
            $actor = AuditActor::tenantDevice($context->userId, $context->membershipId, $context->tenantId, $context->shopId, (int) $device['id']);
            $this->audit->append(new AuditAppendCommand($actor, 'reconciliation.device_acknowledged', AuditCategory::RECONCILIATION,
                'reconciliation_session', $id, 'Pinned online readiness target received by this Device.', ['device_public_id' => $device['public_id']]));
            $this->finish($context, $control, true, [new ChangeCommand($context->tenantId, $context->shopId, 'registered_device', $device['public_id'])]);
            return $id;
        });
    }

    public function exclude(ShopContext $context, string $id, array $input): string
    {
        CashControlInput::only($input, ['device_public_id', 'reason']);
        $deviceId = CashControlInput::uuid($input['device_public_id'] ?? null);
        $reason = CashControlInput::text($input['reason'] ?? null, 500);
        return $this->command($context, ['reconciliation.exclude_device'], function () use ($context, $id, $deviceId, $reason): string {
            $member = $this->database->table('tenant_memberships')->select('role')->where('id', $context->membershipId)->get()->getRowArray();
            if (!$member || $member['role'] !== 'PRIMARY_OWNER') {
                throw new ApiException('PERMISSION_DENIED', 'Only the current Primary Owner may expressly exclude a Device.', 403);
            }
            $control = $this->repository->control($context, $id, true);
            $this->status($control, ['COUNTING', 'COUNTS_COMMITTED', 'REVIEW']);
            $required = $this->required($this->repository->readiness($context, $control), $deviceId);
            if ($required['decision'] === 'EXCLUDED' && $required['reason'] === $reason) {
                return $id;
            }
            if ($required['decision'] !== null) {
                throw new ApiException('READINESS_DECISION_CONFLICT', 'This Device already has an immutable readiness decision. Abort and start a new control if it needs reconsideration.', 409);
            }
            $now = UtcTimestamp::now();
            $this->database->table('reconciliation_device_acknowledgements')->insert([
                'tenant_id' => $context->tenantId, 'shop_id' => $context->shopId, 'reconciliation_session_id' => $control['id'],
                'registered_device_id' => $required['_id'], 'readiness_status' => 'EXCLUDED', 'reason' => $reason,
                'decided_by_user_id' => $context->userId, 'decided_at' => $now, 'created_at' => $now,
            ]);
            $this->record($context, $id, 'reconciliation.device_excluded', $reason, ['device_public_id' => $deviceId]);
            $this->finish($context, $control);
            return $id;
        });
    }

    public function note(ShopContext $context, string $id, array $input): string
    {
        CashControlInput::only($input, ['public_id', 'note_type', 'note_text']);
        $noteId = CashControlInput::uuid($input['public_id'] ?? null);
        $text = CashControlInput::text($input['note_text'] ?? null, 2000, 'note_text');
        $type = $input['note_type'] ?? null;
        if (!in_array($type, ['EXPLANATION', 'INVESTIGATION_FINDING', 'REVIEW_NOTE'], true)) {
            throw new ApiException('VALIDATION_FAILED', 'Choose an approved variance note type.', 422);
        }
        return $this->command($context, ['reconciliation.review', 'reconciliation.note'], function () use ($context, $id, $noteId, $text, $type): string {
            if ((new BlindReadPolicy($this->database))->blocksScope($context, 'CASH')) {
                throw new ApiException('BLIND_COUNT_ACTIVE', 'Commit the active physical count before working with historical variance notes.', 409);
            }
            $control = $this->repository->control($context, $id, true);
            $this->status($control, ['FINALIZED']);
            $variance = $this->repository->variance($context, (int) $control['id']);
            if (!$variance) {
                throw new ApiException('VARIANCE_NOT_FOUND', 'This balanced control has no Cash variance to annotate.', 409);
            }
            $existing = $this->database->query('SELECT * FROM reconciliation_variance_notes WHERE tenant_id=? AND shop_id=? AND public_id=? FOR UPDATE',
                [$context->tenantId, $context->shopId, $noteId])->getRowArray();
            if ($existing) {
                if ((int) $existing['reconciliation_variance_id'] !== (int) $variance['id'] || $existing['note_type'] !== $type || $existing['note_text'] !== $text) {
                    throw new ApiException('CASH_CONTROL_IDENTITY_CONFLICT', 'This note reference already belongs to different saved details.', 409);
                }
                return $id;
            }
            $this->database->table('reconciliation_variance_notes')->insert([
                'public_id' => $noteId, 'tenant_id' => $context->tenantId, 'shop_id' => $context->shopId,
                'reconciliation_session_id' => $control['id'], 'reconciliation_variance_id' => $variance['id'],
                'note_type' => $type, 'note_text' => $text, 'created_by_user_id' => $context->userId, 'created_at' => UtcTimestamp::now(),
            ]);
            $this->record($context, $id, 'reconciliation.variance_note_added', 'Later variance information appended without changing finalized evidence.',
                ['note_public_id' => $noteId, 'note_type' => $type]);
            $this->finish($context, $control, false);
            return $id;
        });
    }

    private function command(ShopContext $context, array $permissions, callable $operation): mixed
    {
        return $this->access->mutate($context, ['reconciliation.view', ...$permissions], function () use ($operation) {
            (new CashControlSchema($this->database))->requireReady();
            return $operation();
        });
    }

    private function finish(ShopContext $context, array $control, bool $sameGeneration = true, array $extra = []): void
    {
        $watermark = $this->changes->acquireWatermark();
        if ($sameGeneration && $watermark->generationUuid !== $control['generation_uuid']) {
            $this->generationChanged();
        }
        $this->changes->publish([new ChangeCommand($context->tenantId, $context->shopId, 'reconciliation_session', $control['public_id']), ...$extra]);
    }

    private function record(ShopContext $context, string $id, string $action, string $reason, array $metadata = [], ?int $cause = null): int
    {
        return $this->audit->append(new AuditAppendCommand(
            AuditActor::tenantUser($context->userId, $context->membershipId, $context->tenantId, $context->shopId),
            $action, AuditCategory::RECONCILIATION, 'reconciliation_session', $id, $reason, $metadata, $cause,
        ));
    }

    private function staff(ShopContext $context, int $control, int $membership, int $user, string $role, ?string $start = null, ?string $end = null, ?string $notes = null): void
    {
        $where = ['tenant_id' => $context->tenantId, 'shop_id' => $context->shopId, 'reconciliation_session_id' => $control,
            'membership_id' => $membership, 'context_role' => $role];
        if ($this->database->table('reconciliation_staff_contexts')->where($where)->countAllResults() === 0) {
            $this->database->table('reconciliation_staff_contexts')->insert([...$where, 'user_id' => $user,
                'context_starts_at' => $start, 'context_ends_at' => $end, 'notes' => $notes, 'created_at' => UtcTimestamp::now()]);
        }
    }

    private function normalizeDuty(mixed $input): array
    {
        if (!is_array($input) || !array_is_list($input) || count($input) > 50) {
            throw new ApiException('VALIDATION_FAILED', 'Choose up to 50 on-duty members.', 422);
        }
        $out = [];
        foreach ($input as $duty) {
            if ($duty instanceof \stdClass) {
                $duty = (array) $duty;
            }
            if (!is_array($duty)) {
                throw new ApiException('VALIDATION_FAILED', 'Use valid on-duty responsibility context.', 422);
            }
            CashControlInput::only($duty, ['membership_public_id', 'starts_at', 'ends_at', 'notes']);
            $key = CashControlInput::uuid($duty['membership_public_id'] ?? null);
            if (isset($out[$key])) {
                throw new ApiException('VALIDATION_FAILED', 'Choose each on-duty member only once.', 422);
            }
            $start = CashControlInput::utc($duty['starts_at'] ?? null);
            $end = CashControlInput::utc($duty['ends_at'] ?? null);
            if ($start !== null && $end !== null && $end < $start) {
                throw new ApiException('VALIDATION_FAILED', 'The duty end cannot precede its start.', 422);
            }
            $out[$key] = ['membership_public_id' => $key, 'starts_at' => $start, 'ends_at' => $end,
                'notes' => ($duty['notes'] ?? null) === null || $duty['notes'] === '' ? null : CashControlInput::text($duty['notes'], 500, 'notes')];
        }
        ksort($out, SORT_STRING);
        return array_values($out);
    }

    private function activeGeneration(): array
    {
        $rows = $this->database->table('sync_generations')->where('status', 'ACTIVE')->get()->getResultArray();
        if (count($rows) !== 1) {
            $this->generationChanged();
        }
        return $rows[0];
    }

    private function revision(array $count, array $input): void
    {
        if (!is_string($input['expected_updated_at'] ?? null) || $input['expected_updated_at'] !== $count['updated_at']) {
            throw new ApiException('CASH_CONTROL_STALE', 'This count changed in another session. Refresh and review the saved value before saving again.', 409);
        }
    }

    private function status(array $control, array $allowed): void
    {
        if (!in_array($control['status'], $allowed, true)) {
            throw new ApiException('CASH_CONTROL_STATE_CONFLICT', 'This action is unavailable at the current control stage. Check saved status.', 409);
        }
    }

    private function requireReady(ShopContext $context, array $control): void
    {
        if (!$this->repository->readiness($context, $control)['complete']) {
            throw new ApiException('DEVICE_READINESS_REQUIRED', 'Every pinned Device must acknowledge the online target or be explicitly excluded by the Owner.', 409);
        }
    }

    private function required(array $readiness, string $id): array
    {
        foreach ($readiness['devices'] as $row) {
            if ($row['public_id'] === $id) {
                return $row;
            }
        }
        throw new ApiException('DEVICE_NOT_RELEVANT', 'This Device is not in the frozen relevant set for this control.', 409);
    }

    private function difference(string $actual, string $expected): string
    {
        if (str_starts_with($expected, '-')) {
            $value = DebtAmount::sum([$actual, substr($expected, 1)]);
            CashAmount::parse($value);
            return $value;
        }
        $compare = DebtAmount::compare($actual, $expected);
        $value = $compare >= 0 ? DebtAmount::subtract($actual, $expected) : DebtAmount::subtract($expected, $actual);
        CashAmount::parse($value);
        return $compare < 0 && $value !== '0.00' ? '-' . $value : $value;
    }

    private function generationChanged(): never
    {
        throw new ApiException('CASH_CONTROL_GENERATION_CHANGED', 'The server generation changed. Abort this control safely and start a new one.', 409);
    }
}
