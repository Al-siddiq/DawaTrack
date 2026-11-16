<?php

declare(strict_types=1);

namespace App\Modules\Reconciliation\Application;

use App\Exceptions\ApiException;
use App\Modules\Audit\Application\AuditService;
use App\Modules\Audit\Domain\{AuditActor, AuditAppendCommand, AuditCategory};
use App\Modules\CustomerDebt\Domain\DebtAmount;
use App\Modules\Device\Infrastructure\DeviceQuery;
use App\Modules\Reconciliation\Infrastructure\{ControlRepository, CashControlSchema, ExpectedCashQuery, StockCountEvidence};
use App\Modules\Sales\Application\CommercialAccess;
use App\Modules\Sync\Application\ChangeSequenceService;
use App\Modules\Sync\Domain\ChangeCommand;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use App\Support\{UtcTimestamp, Uuid};
use CodeIgniter\Database\BaseConnection;

/** One owning lifecycle/readiness implementation; each controller binds its scope server-side. */
class ControlService
{
    private readonly ControlRepository $repository;

    public function __construct(
        private readonly BaseConnection $database,
        private readonly CommercialAccess $access,
        private readonly AuditService $audit,
        private readonly ChangeSequenceService $changes,
        private readonly string $readinessSecret,
        private readonly string $scope = 'CASH',
    ) {
        $this->repository = new ControlRepository($database, $scope);
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
                    "SELECT change_metadata FROM audit_events WHERE tenant_id=? AND shop_id=? AND resource_type='reconciliation_session' AND resource_public_id=? AND action_code=?",
                    [$context->tenantId, $context->shopId, $id, $this->repository->startAction()],
                )->getResultArray();
                $metadata = count($record) === 1 ? json_decode((string) $record[0]['change_metadata'], true) : null;
                if (!is_array($metadata) || !hash_equals((string) ($metadata['duty_fingerprint'] ?? ''), $fingerprint)) {
                    throw new ApiException('CASH_CONTROL_IDENTITY_CONFLICT', 'This check number is already saved with different staff details. Open that check before trying again.', 409);
                }
                return $id;
            }
            new ReadinessProof($this->readinessSecret);
            (new ControlScopeGuard($this->database))->assertAvailable($context, $this->scope);
            $cashPrior = null;
            $stockPrior = null;
            $stockStart = null;
            if ($this->hasCash()) {
                $cashPrior = $this->database->query(
                    "SELECT s.*,c.actual_amount,c.committed_at FROM reconciliation_sessions s JOIN reconciliation_financial_counts c ON c.tenant_id=s.tenant_id AND c.shop_id=s.shop_id AND c.reconciliation_session_id=s.id AND c.channel='CASH' WHERE s.tenant_id=? AND s.shop_id=? AND s.scope IN ('CASH','FULL') AND s.status='FINALIZED' ORDER BY s.cutoff_at DESC,s.id DESC LIMIT 1 FOR UPDATE",
                    [$context->tenantId, $context->shopId],
                )->getRowArray();
                if (!$cashPrior || $cashPrior['actual_amount'] === null || $cashPrior['committed_at'] === null) {
                    throw new ApiException('OPENING_CASH_REQUIRED', 'Finish the starting cash count before you start a cash check.', 409);
                }
            }
            if ($this->hasStock()) {
                $stockPrior = $this->database->query(
                    "SELECT * FROM reconciliation_sessions WHERE tenant_id=? AND shop_id=? AND scope IN ('STOCK','FULL') AND status='FINALIZED' ORDER BY cutoff_at DESC,id DESC LIMIT 1 FOR UPDATE",
                    [$context->tenantId, $context->shopId],
                )->getRowArray();
                $shop = $this->database->table('shops')->select('created_at')->where(['tenant_id' => $context->tenantId, 'id' => $context->shopId])->get()->getRowArray();
                $stockStart = $stockPrior['cutoff_at'] ?? ($shop['created_at'] ?? null);
                if ($stockStart === null) {
                    throw new ApiException('STOCK_CONTROL_EVIDENCE_INVALID', 'We could not find when stock records began. Ask the shop owner for help.', 409);
                }
            }
            $generation = $this->activeGeneration();
            $now = UtcTimestamp::now();
            if (($cashPrior && $now <= $cashPrior['cutoff_at']) || ($stockStart !== null && $now <= $stockStart)) {
                throw new ApiException('CASH_CONTROL_CLOCK_CONFLICT', 'The DawaTrack clock needs checking. Ask the person who sets it up for your shop to help.', 409);
            }
            $expected = $this->hasCash()
                ? (new ExpectedCashQuery($this->database))->calculate($context, $cashPrior['cutoff_at'], $now, (string) $cashPrior['actual_amount']) : null;
            $positions = $this->hasStock() ? (new StockCountEvidence($this->database))->positions($context) : [];
            $devices = $this->database->query(
                "SELECT id,public_id,display_name FROM registered_devices WHERE tenant_id=? AND shop_id=? AND status='ACTIVE' ORDER BY public_id FOR UPDATE",
                [$context->tenantId, $context->shopId],
            )->getResultArray();
            $this->database->table('reconciliation_sessions')->insert([
                'public_id' => $id, 'tenant_id' => $context->tenantId, 'shop_id' => $context->shopId,
                'scope' => $this->scope, 'control_kind' => 'STANDARD', 'status' => 'COUNTING',
                'cash_period_starts_at' => $cashPrior['cutoff_at'] ?? null, 'stock_period_starts_at' => $stockStart,
                'cutoff_at' => $now, 'cutoff_change_sequence' => 0, 'sync_generation_id' => $generation['id'],
                'previous_cash_session_id' => $cashPrior['id'] ?? null, 'previous_stock_session_id' => $stockPrior['id'] ?? null,
                'started_by_user_id' => $context->userId, 'started_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $internal = (int) $this->database->insertID();
            if ($expected !== null) {
                $snapshot = array_intersect_key($expected, array_flip(['opening_baseline_amount', 'cash_sale_receipts', 'cash_sale_refunds',
                    'cash_debt_receipts', 'cash_introductions', 'cash_expenses', 'cash_withdrawals', 'cash_corrections_net', 'expected_amount']));
                $this->database->table('reconciliation_financial_counts')->insert([
                    'tenant_id' => $context->tenantId, 'shop_id' => $context->shopId, 'reconciliation_session_id' => $internal,
                    'channel' => 'CASH', ...$snapshot, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            $stockExpected = [];
            foreach ($positions as $position) {
                $row = ['product_public_id' => $position['product_public_id'], 'name' => $position['name'], 'unit_name' => $position['unit_name'],
                    'expected_quantity' => (string) $position['expected_quantity'], 'expected_average_unit_cost' => (string) $position['average_unit_cost'],
                    'cost_basis_state' => $position['cost_basis_state'], 'position_version' => (string) $position['version']];
                $stockExpected[] = $row;
                $this->database->table('reconciliation_stock_counts')->insert([
                    'tenant_id' => $context->tenantId, 'shop_id' => $context->shopId, 'reconciliation_session_id' => $internal,
                    'product_id' => $position['product_id'], 'expected_quantity' => $row['expected_quantity'],
                    'expected_average_unit_cost' => $row['expected_average_unit_cost'], 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            $ids = array_column($devices, 'public_id');
            sort($ids, SORT_STRING);
            $metadata = ['scope' => $this->scope, 'cutoff_at' => $now, 'generation_uuid' => $generation['generation_uuid'],
                'previous_control_public_id' => $cashPrior['public_id'] ?? null, 'previous_stock_public_id' => $stockPrior['public_id'] ?? null,
                'device_count' => count($ids), 'device_digest' => hash('sha256', implode("\n", $ids)), 'duty_fingerprint' => $fingerprint];
            if ($this->hasStock()) {
                $metadata['stock_count'] = count($stockExpected);
                $metadata['stock_digest'] = StockCountEvidence::fingerprint($stockExpected);
            }
            $root = $this->record($context, $id, $this->repository->startAction(), 'Standard blind control started with frozen scope evidence.', $metadata);
            foreach ($devices as $device) {
                $this->record($context, $id, 'reconciliation.device_required', 'Active Device pinned for this control.',
                    ['device_public_id' => $device['public_id'], 'display_name' => $device['display_name']], $root);
            }
            foreach ($stockExpected as $row) {
                $this->record($context, $id, 'reconciliation.stock_expected', 'Stock membership and expected cost context frozen at cutoff.', $row, $root);
            }
            foreach ($onDuty as $duty) {
                $member = $this->database->query("SELECT id,user_id FROM tenant_memberships WHERE tenant_id=? AND public_id=? AND status='ACTIVE' FOR UPDATE",
                    [$context->tenantId, $duty['membership_public_id']])->getRowArray();
                if (!$member || ($duty['ends_at'] !== null && $duty['ends_at'] > $now) || ($duty['starts_at'] !== null && $duty['starts_at'] > $now)) {
                    throw new ApiException('VALIDATION_FAILED', 'Choose people who still work in the shop. Enter only times that have already passed. If someone is still working, leave their finish time empty.', 422);
                }
                $this->staff($context, $internal, (int) $member['id'], (int) $member['user_id'], 'ON_DUTY', $duty['starts_at'], $duty['ends_at'], $duty['notes']);
            }
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
        if ($this->hasStock() && array_key_exists('product_public_id', $input)) {
            return $this->stockObservation($context, $id, $input, false);
        }
        if (!$this->hasCash()) {
            throw new ApiException('VALIDATION_FAILED', 'Choose the item you counted.', 422);
        }
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
            $this->record($context, $id, $this->event('reconciliation.cash_counted'), 'Physical Cash observation saved.');
            $this->finish($context, $control);
            return $id;
        });
    }

    public function commit(ShopContext $context, string $id, array $input): string
    {
        CashControlInput::only($input, $this->hasStock() ? ['expected_commit_revision'] : ['expected_updated_at']);
        return $this->command($context, ['reconciliation.count', 'reconciliation.commit'], function () use ($context, $id, $input): string {
            $control = $this->repository->control($context, $id, true);
            $cash = $this->hasCash() ? $this->repository->count($context, (int) $control['id'], true) : null;
            $rows = $this->hasStock() ? (new StockCountEvidence($this->database))->rows($context, $control, true) : [];
            if (in_array($control['status'], ['COUNTS_COMMITTED', 'REVIEW', 'FINALIZED'], true)) {
                return $id;
            }
            $this->status($control, ['COUNTING']);
            if ($this->hasStock()) {
                $revision = StockCountEvidence::revision($id, $rows, $cash);
                if (!is_string($input['expected_commit_revision'] ?? null) || !hash_equals($revision, $input['expected_commit_revision'])) {
                    throw new ApiException('CASH_CONTROL_STALE', 'A saved count has changed. Reload and check all saved counts before confirming them.', 409);
                }
            } else {
                $this->revision($cash, $input);
            }
            $this->requireReady($context, $control);
            $observers = [];
            if ($cash !== null) {
                if ($cash['actual_amount'] === null || $cash['counted_by_user_id'] === null) {
                    throw new ApiException('CASH_COUNT_REQUIRED', 'Enter the cash you counted and save it first. Enter 0 if there is no cash.', 422);
                }
                $observers[] = (int) $cash['counted_by_user_id'];
            }
            foreach ($rows as $row) {
                if ($row['actual_quantity'] === null || $row['counted_by_user_id'] === null) {
                    throw new ApiException('STOCK_COUNT_REQUIRED', 'Enter and save a count for every item on this list. Enter 0 if you have none.', 422);
                }
                $observers[] = (int) $row['counted_by_user_id'];
            }
            if ($observers === []) {
                $observers[] = $context->userId; // Explicit confirmation of a genuinely empty frozen Stock set.
            }
            $now = UtcTimestamp::now();
            if ($cash !== null) {
                $this->database->table('reconciliation_financial_counts')->where('id', $cash['id'])->update(['committed_at' => $now, 'updated_at' => $now]);
            }
            if ($this->hasStock()) {
                $this->database->table('reconciliation_stock_counts')->where(['tenant_id' => $context->tenantId, 'shop_id' => $context->shopId,
                    'reconciliation_session_id' => $control['id']])->update(['committed_at' => $now, 'updated_at' => $now]);
            }
            foreach (array_unique($observers) as $user) {
                $member = $this->database->query('SELECT id,user_id FROM tenant_memberships WHERE tenant_id=? AND user_id=? FOR UPDATE', [$context->tenantId, $user])->getRowArray();
                if (!$member) {
                    throw new ApiException('CASH_CONTROL_EVIDENCE_INVALID', 'We could not find who counted. Ask the shop owner for help.', 409);
                }
                $this->staff($context, (int) $control['id'], (int) $member['id'], $user, 'COUNTER');
            }
            $this->database->table('reconciliation_sessions')->where('id', $control['id'])->update([
                'status' => 'COUNTS_COMMITTED', 'counts_committed_by_user_id' => $context->userId, 'counts_committed_at' => $now, 'updated_at' => $now,
            ]);
            $this->record($context, $id, $this->event('reconciliation.cash_committed'), 'All physical observations committed atomically and locked.');
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
            $this->record($context, $id, $this->event('reconciliation.cash_reviewed'), 'Frozen Cash evidence opened for review.');
            $this->finish($context, $control);
            return $id;
        });
    }

    public function explain(ShopContext $context, string $id, array $input): string
    {
        if ($this->hasStock() && array_key_exists('product_public_id', $input)) {
            return $this->stockObservation($context, $id, $input, true);
        }
        if (!$this->hasCash()) {
            throw new ApiException('VALIDATION_FAILED', 'Choose the item with a different count.', 422);
        }
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
            $this->record($context, $id, $this->event('reconciliation.cash_explained'), $explanation);
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
            $cash = $this->hasCash() ? $this->repository->count($context, (int) $control['id'], true) : null;
            $rows = $this->hasStock() ? (new StockCountEvidence($this->database))->rows($context, $control, true) : [];
            $this->requireReady($context, $control);
            if ($control['counts_committed_at'] === null) {
                throw new ApiException('CASH_COUNT_REQUIRED', 'Confirm all the counts first.', 409);
            }
            $now = UtcTimestamp::now();
            $cashVariance = null;
            if ($cash !== null) {
                if ($cash['actual_amount'] === null || $cash['committed_at'] === null) {
                    throw new ApiException('CASH_COUNT_REQUIRED', 'Confirm the cash count first.', 409);
                }
                $cashVariance = $this->difference((string) $cash['actual_amount'], (string) $cash['expected_amount']);
                if ($cashVariance !== (string) $cash['variance_amount']) {
                    throw new ApiException('CASH_CONTROL_EVIDENCE_INVALID', 'The saved cash numbers need checking. Keep them as they are and ask the shop owner for help.', 409);
                }
                $this->requireExplanation($cashVariance !== '0.00', $cash['explanation']);
                if ($cashVariance !== '0.00') {
                    $this->database->table('reconciliation_variances')->insert([
                        'public_id' => Uuid::v4(), 'tenant_id' => $context->tenantId, 'shop_id' => $context->shopId,
                        'reconciliation_session_id' => $control['id'], 'variance_kind' => 'CASH', 'financial_count_id' => $cash['id'],
                        'amount_variance' => $cashVariance, 'explanation' => $cash['explanation'], 'created_at' => $now,
                    ]);
                }
            }
            $stockVariances = 0;
            foreach ($rows as $row) {
                if ($row['actual_quantity'] === null || $row['committed_at'] === null) {
                    throw new ApiException('STOCK_COUNT_REQUIRED', 'Confirm every item count first.', 409);
                }
                $variance = \App\Modules\Inventory\Application\StockCountAmount::difference((string) $row['actual_quantity'], (string) $row['expected_quantity']);
                $this->requireExplanation($variance !== '0.0000', $row['explanation']);
                if ($variance !== '0.0000') {
                    $public = Uuid::v4();
                    $this->database->table('reconciliation_variances')->insert([
                        'public_id' => $public, 'tenant_id' => $context->tenantId, 'shop_id' => $context->shopId,
                        'reconciliation_session_id' => $control['id'], 'variance_kind' => 'STOCK', 'stock_count_id' => $row['id'],
                        'quantity_variance' => $variance, 'explanation' => $row['explanation'], 'created_at' => $now,
                    ]);
                    $this->record($context, $id, 'reconciliation.stock_variance_finalized', 'Immutable Stock variance finalized without changing operational Stock.',
                        ['variance_public_id' => $public, 'product_public_id' => $row['product_public_id'], 'quantity_variance' => $variance]);
                    $stockVariances++;
                }
            }
            $this->staff($context, (int) $control['id'], $context->membershipId, $context->userId, 'FINALIZER');
            $this->database->table('reconciliation_sessions')->where('id', $control['id'])->update([
                'status' => 'FINALIZED', 'finalized_by_user_id' => $context->userId, 'finalized_at' => $now, 'updated_at' => $now,
            ]);
            $this->record($context, $id, $this->event('reconciliation.cash_finalized'), 'Control finalized atomically with immutable observations and independent predecessors.',
                ['variance_amount' => $cashVariance, 'stock_variance_count' => $stockVariances]);
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
            $this->record($context, $id, $this->event('reconciliation.cash_aborted'), $reason);
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
                throw new ApiException('DEVICE_PROOF_REQUIRED', 'Ask the owner to add this phone or computer under Shop devices first.', 409);
            }
            $payload = (new ReadinessProof($this->readinessSecret))->decode($token);
            $target = ['control_public_id' => $id, 'device_public_id' => $device['public_id'], 'user_id' => $context->userId,
                'membership_id' => $context->membershipId, 'generation_uuid' => $control['generation_uuid'],
                'cutoff_at' => $control['cutoff_at'], 'sequence' => (string) $control['cutoff_change_sequence']];
            foreach ($target as $key => $value) {
                if (($payload[$key] ?? null) !== $value) {
                    throw new ApiException('READINESS_REFRESH_REQUIRED', 'Reload this check on the same phone or computer, then tap the ready button again.', 409);
                }
            }
            $readiness = $this->repository->readiness($context, $control);
            $required = $this->required($readiness, $device['public_id']);
            if ($required['decision'] === 'ACKNOWLEDGED') {
                return $id;
            }
            if ($required['decision'] !== null) {
                throw new ApiException('READINESS_DECISION_CONFLICT', 'This phone or computer has already been marked ready or skipped. That choice cannot be changed.', 409);
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
                throw new ApiException('PERMISSION_DENIED', 'Only the shop owner can skip a phone or computer.', 403);
            }
            $control = $this->repository->control($context, $id, true);
            $this->status($control, ['COUNTING', 'COUNTS_COMMITTED', 'REVIEW']);
            $required = $this->required($this->repository->readiness($context, $control), $deviceId);
            if ($required['decision'] === 'EXCLUDED' && $required['reason'] === $reason) {
                return $id;
            }
            if ($required['decision'] !== null) {
                throw new ApiException('READINESS_DECISION_CONFLICT', 'This phone or computer has already been marked ready or skipped. To change that choice, cancel this check and start again.', 409);
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
        CashControlInput::only($input, $this->hasStock() ? ['public_id', 'note_type', 'note_text', 'variance_public_id'] : ['public_id', 'note_type', 'note_text']);
        $varianceId = $this->hasStock() ? CashControlInput::uuid($input['variance_public_id'] ?? null) : null;
        $noteId = CashControlInput::uuid($input['public_id'] ?? null);
        $text = CashControlInput::text($input['note_text'] ?? null, 2000, 'note_text');
        $type = $input['note_type'] ?? null;
        if (!in_array($type, ['EXPLANATION', 'INVESTIGATION_FINDING', 'REVIEW_NOTE'], true)) {
            throw new ApiException('VALIDATION_FAILED', 'Choose what this note is about.', 422);
        }
        return $this->command($context, ['reconciliation.review', 'reconciliation.note'], function () use ($context, $id, $noteId, $text, $type, $varianceId): string {
            if (($this->hasCash() && (new BlindReadPolicy($this->database))->blocksScope($context, 'CASH')) || ($this->hasStock() && (new BlindReadPolicy($this->database))->blocksScope($context, 'STOCK'))) {
                throw new ApiException('BLIND_COUNT_ACTIVE', 'Confirm the current count before opening notes from past checks.', 409);
            }
            $control = $this->repository->control($context, $id, true);
            $this->status($control, ['FINALIZED']);
            $variance = $this->repository->variance($context, (int) $control['id'], $varianceId);
            if (!$variance) {
                throw new ApiException('VARIANCE_NOT_FOUND', 'Choose a difference from a finished check before adding a note.', 409);
            }
            $existing = $this->database->query('SELECT * FROM reconciliation_variance_notes WHERE tenant_id=? AND shop_id=? AND public_id=? FOR UPDATE',
                [$context->tenantId, $context->shopId, $noteId])->getRowArray();
            if ($existing) {
                if ((int) $existing['reconciliation_variance_id'] !== (int) $variance['id'] || $existing['note_type'] !== $type || $existing['note_text'] !== $text) {
                    throw new ApiException('CASH_CONTROL_IDENTITY_CONFLICT', 'This note number is already saved with different details. Open the saved note before trying again.', 409);
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

    private function stockObservation(ShopContext $context, string $id, array $input, bool $explain): string
    {
        CashControlInput::only($input, ['product_public_id', $explain ? 'explanation' : 'actual_quantity', 'expected_updated_at']);
        $product = CashControlInput::uuid($input['product_public_id'] ?? null);
        $value = $explain ? CashControlInput::text($input['explanation'] ?? null, 500, 'explanation')
            : \App\Modules\Inventory\Application\StockCountAmount::parse($input['actual_quantity'] ?? null);
        return $this->command($context, [$explain ? 'reconciliation.review' : 'reconciliation.count'], function () use ($context, $id, $input, $product, $value, $explain): string {
            $control = $this->repository->control($context, $id, true);
            $this->status($control, [$explain ? 'REVIEW' : 'COUNTING']);
            $rows = (new StockCountEvidence($this->database))->rows($context, $control, true);
            $row = null;
            foreach ($rows as $candidate) {
                if ($candidate['product_public_id'] === $product) {
                    $row = $candidate;
                    break;
                }
            }
            if ($row === null) {
                throw new ApiException('STOCK_COUNT_NOT_FOUND', 'This item is not on the list for this check.', 404);
            }
            if (($explain ? $row['explanation'] : $row['actual_quantity']) === $value) {
                return $id;
            }
            $this->revision($row, $input);
            $now = UtcTimestamp::now();
            $update = $explain ? ['explanation' => $value, 'updated_at' => $now] : [
                'actual_quantity' => $value,
                'variance_quantity' => \App\Modules\Inventory\Application\StockCountAmount::difference($value, (string) $row['expected_quantity']),
                'counted_by_user_id' => $context->userId, 'counted_at' => $now, 'updated_at' => $now,
            ];
            $this->database->table('reconciliation_stock_counts')->where('id', $row['id'])->update($update);
            if ($explain) {
                $this->staff($context, (int) $control['id'], $context->membershipId, $context->userId, 'REVIEWER');
            }
            $this->record($context, $id, $explain ? 'reconciliation.stock_explained' : 'reconciliation.stock_counted',
                $explain ? $value : 'Physical Stock observation saved.', ['product_public_id' => $product]);
            $this->finish($context, $control);
            return $id;
        });
    }

    private function hasCash(): bool { return in_array($this->scope, ['CASH', 'FULL'], true); }
    private function hasStock(): bool { return in_array($this->scope, ['STOCK', 'FULL'], true); }
    private function event(string $cashAction): string
    {
        return $this->scope === 'CASH' ? $cashAction : str_replace('reconciliation.cash_', 'reconciliation.control_', $cashAction);
    }

    private function requireExplanation(bool $nonzero, mixed $value): void
    {
        if ($nonzero && (!is_string($value) || trim($value) === '')) {
            throw new ApiException('VARIANCE_EXPLANATION_REQUIRED', 'Save a reason for each difference before you finish the check. The difference will still be kept.', 422);
        }
    }

    private function command(ShopContext $context, array $permissions, callable $operation): mixed
    {
        return $this->access->mutate($context, ['reconciliation.view', ...$permissions], function () use ($operation) {
            (new CashControlSchema($this->database))->requireReady();
            if ($this->hasStock()) {
                (new \App\Modules\Reconciliation\Infrastructure\StockControlSchema($this->database))->requireReady();
            }
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
            throw new ApiException('VALIDATION_FAILED', 'You can add up to 50 people who worked in the shop.', 422);
        }
        $out = [];
        foreach ($input as $duty) {
            if ($duty instanceof \stdClass) {
                $duty = (array) $duty;
            }
            if (!is_array($duty)) {
                throw new ApiException('VALIDATION_FAILED', 'Check the details of the people who worked in the shop.', 422);
            }
            CashControlInput::only($duty, ['membership_public_id', 'starts_at', 'ends_at', 'notes']);
            $key = CashControlInput::uuid($duty['membership_public_id'] ?? null);
            if (isset($out[$key])) {
                throw new ApiException('VALIDATION_FAILED', 'Add each person only once.', 422);
            }
            $start = CashControlInput::utc($duty['starts_at'] ?? null);
            $end = CashControlInput::utc($duty['ends_at'] ?? null);
            if ($start !== null && $end !== null && $end < $start) {
                throw new ApiException('VALIDATION_FAILED', 'The finish time must be after the start time.', 422);
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
            throw new ApiException('CASH_CONTROL_STALE', 'Someone has changed this saved count. Reload and check it before saving again.', 409);
        }
    }

    private function status(array $control, array $allowed): void
    {
        if (!in_array($control['status'], $allowed, true)) {
            throw new ApiException('CASH_CONTROL_STATE_CONFLICT', 'You cannot do this at this step. Check what was saved first.', 409);
        }
    }

    private function requireReady(ShopContext $context, array $control): void
    {
        if (!$this->repository->readiness($context, $control)['complete']) {
            throw new ApiException('DEVICE_READINESS_REQUIRED', 'Each shop phone or computer on this list must be marked ready or skipped by the owner before you can continue.', 409);
        }
    }

    private function required(array $readiness, string $id): array
    {
        foreach ($readiness['devices'] as $row) {
            if ($row['public_id'] === $id) {
                return $row;
            }
        }
        throw new ApiException('DEVICE_NOT_RELEVANT', 'This phone or computer is not on the list for this check.', 409);
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
        throw new ApiException('CASH_CONTROL_GENERATION_CHANGED', 'DawaTrack has changed since this check started. Cancel this check and start again.', 409);
    }
}
