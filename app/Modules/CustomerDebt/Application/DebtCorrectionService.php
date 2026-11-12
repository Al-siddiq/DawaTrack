<?php

declare(strict_types=1);
namespace App\Modules\CustomerDebt\Application;

use App\Exceptions\ApiException;
use App\Libraries\Http\RequestContext;
use App\Modules\Audit\Application\AuditService;
use App\Modules\Audit\Domain\{AuditActor, AuditAppendCommand, AuditCategory, AuditOccurrence};
use App\Modules\CustomerDebt\Domain\{DebtAllocationPlanner, DebtAmount};
use App\Modules\CustomerDebt\Infrastructure\{DebtPaymentRepository, DebtPaymentQuery, DebtReadSnapshot, DebtSchema, DebtSettlementQuery};
use App\Modules\Reconciliation\Application\CashControlGuard;
use App\Modules\Sales\Application\{CommercialAccess, DraftInput};
use App\Modules\Sync\Application\ChangeSequenceService;
use App\Modules\Sync\Domain\ChangeCommand;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use App\Support\UtcTimestamp;
use CodeIgniter\Database\BaseConnection;

/** One owner for reviewed corrections. No source evidence is updated or deleted. */
final class DebtCorrectionService
{
    private readonly DebtPaymentRepository $repository;
    public function __construct(private readonly BaseConnection $database, private readonly CommercialAccess $access,
        private readonly AuditService $audit, private readonly ChangeSequenceService $changes, private readonly RequestContext $request)
    {
        $this->repository = new DebtPaymentRepository($database);
    }

    public function execute(ShopContext $c, string $source, string $kind, array $input, bool $post = false): array
    {
        if (!in_array($kind, ['reverse', 'correct', 'adjust', 'adjust-reverse'], true)) throw new \LogicException('Unknown correction.');
        $command = DebtCorrectionInput::normalize($input, $kind, $source, $post);
        $permissions = ['customer.view', 'debt.view', in_array($kind, ['reverse', 'correct'], true) ? 'debt.payment.reverse' : 'debt.adjust'];
        if ($kind === 'correct') {
            $permissions[] = 'debt.payment.record';
            if ($command['replacement']['allocation_mode'] === 'EXPLICIT') $permissions[] = 'debt.payment.allocate';
        }
        return $this->access->mutate($c, $permissions, function () use ($c, $command, $input, $post, $kind): array {
            (new DebtSchema($this->database))->requireReady();
            if ($this->replay($c, $command)) return ['already_saved' => true, 'public_id' => $command['public_id']];
            AuditOccurrence::supplied($command['occurred_at']);
            $review = in_array($kind, ['reverse', 'correct'], true) ? $this->paymentReview($c, $command) : $this->adjustmentReview($c, $command);
            $review['review_fingerprint'] = DebtPaymentInput::fingerprint([$command, $review]);
            if (!$post) { unset($review['_source'], $review['_customer'], $review['_bill']); return $review; }
            if (!hash_equals($review['review_fingerprint'], $input['review_fingerprint'])) {
                throw new ApiException('DEBT_REVIEW_STALE', 'The linked records changed. Review this change again before saving it.', 409);
            }
            $commands = in_array($kind, ['reverse', 'correct'], true) ? $this->postPayment($c, $command, $review) : $this->postAdjustment($c, $command, $review);
            // Generation is acquired last, once, after all effects and Audit for both halves.
            $this->changes->publish($commands);
            return ['already_saved' => false, 'public_id' => $command['public_id']];
        });
    }

    private function replay(ShopContext $c, array $command): bool
    {
        $isPayment = in_array($command['kind'], ['reverse', 'correct'], true);
        $row = $isPayment ? $this->repository->payment($c, $command['public_id']) : $this->adjustment($c, $command['public_id']);
        if (!$row) {
            if ($command['kind'] === 'correct' && $this->repository->payment($c, $command['replacement']['public_id'])) $this->identityConflict();
            return false;
        }
        $action = $isPayment ? 'debt_payment.reversed' : ($command['kind'] === 'adjust' ? 'receivable.adjusted' : 'receivable.adjustment_reversed');
        $evidence = $this->evidence($c, $command['public_id'], $isPayment ? 'debt_payment' : 'receivable_adjustment', $action);
        if (!hash_equals((string) ($evidence['command_fingerprint'] ?? ''), DebtPaymentInput::fingerprint($command))) $this->identityConflict();
        if ($isPayment) {
            $source = $this->repository->payment($c, $command['source_public_id']);
            if (!$source || (int) $row['reversal_of_debt_payment_id'] !== (int) $source['id']
                || $row['customer_id'] != $source['customer_id'] || $row['channel'] !== $source['channel'] || $row['amount'] !== $source['amount']) $this->invalid();
            // Validate persisted immutable allocation evidence as well as the command hash.
            (new DebtPaymentQuery($this->database, $this->access))->show($c, $row['public_id']);
            if ($command['kind'] === 'correct') {
                $replacement = $this->repository->payment($c, $command['replacement']['public_id']);
                if (!$replacement || $replacement['correlation_id'] !== $row['correlation_id'] || $replacement['reversal_of_debt_payment_id'] !== null) $this->invalid();
                $normalEvidence = $this->repository->recordedEvidence($c, $replacement['public_id']);
                if (!hash_equals((string) $normalEvidence['metadata']['command_fingerprint'], DebtPaymentInput::fingerprint($command['replacement']))) $this->invalid();
                (new DebtPaymentQuery($this->database, $this->access))->show($c, $replacement['public_id']);
            }
        } else {
            if ($row['receivable_public_id'] !== ($command['kind'] === 'adjust' ? $command['source_public_id'] : ($evidence['receivable_public_id'] ?? null))
                || $row['amount_delta'] !== ($evidence['amount_delta'] ?? null)) $this->invalid();
        }
        return true;
    }

    private function paymentReview(ShopContext $c, array $command): array
    {
        $source = $this->repository->payment($c, $command['source_public_id']);
        if (!$source) throw new ApiException('RESOURCE_NOT_FOUND', 'The original payment was not found.', 404);
        if ($source['reversal_of_debt_payment_id'] !== null || $source['reversed_by_public_id'] !== null) {
            throw new ApiException('DEBT_ALREADY_REVERSED', 'This payment has already been undone, or is itself an undo. Open the original payment and its linked records.', 409);
        }
        if ($source['channel'] === 'CASH' || ($command['replacement']['channel'] ?? null) === 'CASH') {
            (new CashControlGuard($this->database))->assertCanAffectCash($c);
        }
        $originalAllocations = $this->repository->allocations($c, (int) $source['id']);
        // Original commercial roots before Customer and ordered Receivables; the outer Shop lock serializes all writers.
        foreach ($originalAllocations as $allocation) $this->lockSale($c, $allocation['sale_public_id']);
        $customer = $this->repository->customer($c, $source['customer_public_id'], true);
        $bills = $this->repository->balances($c, (int) $customer['id'], true);
        (new DebtPaymentQuery($this->database, $this->access))->show($c, $source['public_id']);
        $before = DebtAmount::sum(array_column($bills, 'outstanding_amount'));
        $indexed = array_column($bills, null, 'public_id');
        $restored = [];
        $capacityState = [];
        foreach ($originalAllocations as $allocation) {
            $bill = $indexed[$allocation['receivable_public_id']] ?? null;
            if (!$bill || DebtAmount::compare($bill['collected_amount'], (string) $allocation['amount']) < 0) $this->invalid();
            $capacity = (new DebtSettlementQuery($this->database))->capacity($c, (int) $bill['sale_id']);
            if (DebtAmount::compare($capacity[$source['channel']], (string) $allocation['amount']) < 0) {
                throw new ApiException('DEBT_REFUND_DEPENDENCY', 'Part of this payment has already been refunded for returned goods. Open the linked sale and undo the eligible return first, then review this payment again.', 409,
                    ['dependencies' => ['sale_public_id' => $allocation['sale_public_id'], 'receivable_public_id' => $allocation['receivable_public_id']]]);
            }
            $capacityState[] = [$bill['public_id'], $capacity];
            $restored[] = ['receivable_public_id' => $bill['public_id'], 'amount' => (string) $allocation['amount'],
                'balance_before' => $bill['outstanding_amount'], 'balance_after' => DebtAmount::sum([$bill['outstanding_amount'], (string) $allocation['amount']])];
            $indexed[$bill['public_id']]['outstanding_amount'] = DebtAmount::sum([$bill['outstanding_amount'], (string) $allocation['amount']]);
            $indexed[$bill['public_id']]['collected_amount'] = DebtAmount::subtract($bill['collected_amount'], (string) $allocation['amount']);
        }
        $afterUndo = DebtAmount::sum([$before, (string) $source['amount']]);
        $review = ['public_id' => $command['public_id'], 'kind' => $command['kind'], 'customer_public_id' => $customer['public_id'], 'customer_name' => $customer['display_name'],
            'source_public_id' => $source['public_id'], 'amount' => (string) $source['amount'], 'channel' => $source['channel'],
            'customer_balance_before' => $before, 'customer_balance_after_undo' => $afterUndo, 'customer_balance_after' => $afterUndo,
            'allocations' => $restored, 'capacity_state' => $capacityState,
            'bill_state' => $this->billState($bills), '_source' => $source, '_customer' => $customer];
        if ($command['kind'] === 'correct') {
            $replacement = $command['replacement'];
            AuditOccurrence::supplied($replacement['occurred_at']);
            if ($replacement['customer_public_id'] !== $source['customer_public_id']) {
                throw new ApiException('VALIDATION_FAILED', 'A corrected payment must belong to the same customer. Undo it separately before recording money for another customer.', 422);
            }
            $plan = (new DebtAllocationPlanner())->plan(array_values($indexed), $replacement['amount'], $replacement['allocation_mode'], $replacement['allocations']);
            $review['replacement_allocations'] = array_map(static function (array $a): array { unset($a['receivable_id']); return $a; }, $plan['allocations']);
            $review['replacement_public_id'] = $replacement['public_id'];
            $review['replacement_amount'] = $replacement['amount'];
            $review['replacement_channel'] = $replacement['channel'];
            $review['customer_balance_after'] = $plan['customer_balance_after'];
        }
        return $review;
    }

    private function postPayment(ShopContext $c, array $command, array $review): array
    {
        $source = $review['_source'];
        $inverse = ['public_id' => $command['public_id'], 'customer_public_id' => $source['customer_public_id'],
            'channel' => $source['channel'], 'amount' => (string) $source['amount'], 'external_reference' => $source['external_reference'],
            'occurred_at' => $command['occurred_at'], 'reason' => $command['reason'], 'allocation_mode' => 'EXPLICIT'];
        $participant = new DebtPaymentPostingParticipant($this->database, $this->audit, $this->request);
        $commands = $participant->append($c, $review['_customer'], $inverse,
            ['allocations' => $review['allocations'], 'customer_balance_before' => $review['customer_balance_before'], 'customer_balance_after' => $review['customer_balance_after_undo']],
            (int) $source['id'], 'debt_payment.reversed', DebtPaymentInput::fingerprint($command),
            ['source_public_id' => $source['public_id'], 'replacement_public_id' => $review['replacement_public_id'] ?? null]);
        if ($command['kind'] === 'correct') {
            $commands = [...$commands, ...$participant->append($c, $review['_customer'], $command['replacement'],
                ['allocations' => $review['replacement_allocations'], 'customer_balance_before' => $review['customer_balance_after_undo'], 'customer_balance_after' => $review['customer_balance_after']],
                links: ['corrects_public_id' => $source['public_id'], 'inverse_public_id' => $command['public_id']])];
            $this->audit->append(new AuditAppendCommand($this->actor($c), 'debt_payment.corrected', AuditCategory::DEBT, 'debt_payment', $command['public_id'], $command['reason'],
                ['source_public_id' => $source['public_id'], 'replacement_public_id' => $command['replacement']['public_id']], occurrence: AuditOccurrence::supplied($command['occurred_at'])));
        }
        // A negative or attribution-invalid aggregate fails the entire pair before publication.
        $this->repository->balances($c, (int) $source['customer_id']);
        return $commands;
    }

    private function adjustmentReview(ShopContext $c, array $command): array
    {
        $source = $command['kind'] === 'adjust' ? null : $this->adjustment($c, $command['source_public_id']);
        if ($command['kind'] !== 'adjust' && !$source) throw new ApiException('RESOURCE_NOT_FOUND', 'This bill change was not found.', 404);
        if ($source && ($source['sale_id'] !== null || $source['reversal_of_adjustment_id'] !== null || $source['reversed_by_public_id'] !== null
            || !in_array($source['adjustment_type'], ['CORRECTION_INCREASE', 'CORRECTION_DECREASE'], true))) {
            throw new ApiException('DEBT_ADJUSTMENT_DEPENDENCY', 'This change cannot be undone here. For a return or cancelled sale, open the original sale and use its own correction action.', 409);
        }
        $billId = $source['receivable_public_id'] ?? $command['source_public_id'];
        $bill = $this->database->query('SELECT b.*,s.public_id sale_public_id,c.public_id customer_public_id FROM receivables b
            JOIN sales s ON s.tenant_id=b.tenant_id AND s.shop_id=b.shop_id AND s.id=b.sale_id
            JOIN customers c ON c.tenant_id=b.tenant_id AND c.id=b.customer_id
            WHERE b.tenant_id=? AND b.shop_id=? AND b.public_id=?', [$c->tenantId, $c->shopId, $billId])->getRowArray();
        if (!$bill) throw new ApiException('RESOURCE_NOT_FOUND', 'This bill was not found.', 404);
        $this->lockSale($c, $bill['sale_public_id']);
        if ($this->database->query("SELECT id FROM sales WHERE tenant_id=? AND shop_id=? AND reversal_of_sale_id=? AND status='POSTED'", [$c->tenantId, $c->shopId, $bill['sale_id']])->getRowArray()) {
            throw new ApiException('DEBT_ADJUSTMENT_DEPENDENCY', 'This sale was cancelled. Its bill cannot be changed here.', 409);
        }
        $customer = $this->repository->customer($c, $bill['customer_public_id'], true);
        $bills = $this->repository->balances($c, (int) $customer['id'], true);
        $current = array_column($bills, null, 'public_id')[$billId] ?? null;
        if (!$current) $this->invalid();
        $delta = $source ? (str_starts_with($source['amount_delta'], '-') ? substr($source['amount_delta'], 1) : '-' . $source['amount_delta']) : $command['amount_delta'];
        $negative = str_starts_with($delta, '-');
        $amount = $negative ? substr($delta, 1) : $delta;
        if ($negative && DebtAmount::compare($current['outstanding_amount'], $amount) < 0) {
            throw new ApiException('DEBT_ADJUSTMENT_DEPENDENCY', 'This decrease is more than the bill still needs. Check later payments and returns first. A bill cannot be changed below zero.', 409);
        }
        $before = DebtAmount::sum(array_column($bills, 'outstanding_amount'));
        return ['public_id' => $command['public_id'], 'kind' => $command['kind'], 'customer_public_id' => $customer['public_id'], 'customer_name' => $customer['display_name'],
            'source_public_id' => $command['source_public_id'], 'receivable_public_id' => $billId, 'sale_public_id' => $bill['sale_public_id'], 'amount_delta' => $delta,
            'bill_balance_before' => $current['outstanding_amount'],
            'bill_balance_after' => $negative ? DebtAmount::subtract($current['outstanding_amount'], $amount) : DebtAmount::sum([$current['outstanding_amount'], $amount]),
            'customer_balance_before' => $before, 'customer_balance_after' => $negative ? DebtAmount::subtract($before, $amount) : DebtAmount::sum([$before, $amount]),
            'bill_state' => $this->billState($bills), '_source' => $source, '_bill' => $bill, '_customer' => $customer];
    }

    private function postAdjustment(ShopContext $c, array $command, array $review): array
    {
        $source = $review['_source'];
        (new ReceivableAdjustmentParticipant($this->database))->append($c, $review['_bill'], null,
            $source ? 'REVERSAL' : $command['adjustment_type'], $review['amount_delta'], $command['reason'], $command['occurred_at'],
            UtcTimestamp::now(), $this->request->requestId(), $source ? (int) $source['id'] : null, $command['public_id']);
        $this->audit->append(new AuditAppendCommand($this->actor($c), $source ? 'receivable.adjustment_reversed' : 'receivable.adjusted', AuditCategory::DEBT,
            'receivable_adjustment', $command['public_id'], $command['reason'],
            ['command_fingerprint' => DebtPaymentInput::fingerprint($command), 'source_public_id' => $command['source_public_id'],
                'receivable_public_id' => $review['receivable_public_id'], 'amount_delta' => $review['amount_delta'],
                'bill_balance_before' => $review['bill_balance_before'], 'bill_balance_after' => $review['bill_balance_after'],
                'customer_balance_before' => $review['customer_balance_before'], 'customer_balance_after' => $review['customer_balance_after']],
            occurrence: AuditOccurrence::supplied($command['occurred_at'])));
        $this->repository->balances($c, (int) $review['_customer']['id']);
        return [new ChangeCommand($c->tenantId, $c->shopId, 'receivable_adjustment', $command['public_id']),
            new ChangeCommand($c->tenantId, $c->shopId, 'receivable', $review['receivable_public_id']),
            new ChangeCommand($c->tenantId, null, 'customer', $review['customer_public_id'])];
    }

    public function showAdjustment(ShopContext $c, string $id): array
    {
        $this->access->read($c, 'customer.view'); $this->access->read($c, 'debt.view');
        (new DebtSchema($this->database))->requireReady();
        return (new DebtReadSnapshot($this->database))->run(function () use ($c, $id): array {
            $row = $this->adjustment($c, DraftInput::uuid($id));
            if (!$row) throw new ApiException('RESOURCE_NOT_FOUND', 'This bill change is not saved yet. Check again before starting another.', 404);
            $public = array_intersect_key($row, array_flip(['public_id', 'receivable_public_id', 'customer_public_id', 'customer_name', 'sale_public_id', 'adjustment_type', 'amount_delta', 'occurred_at', 'posted_at', 'reversal_of_public_id', 'reversed_by_public_id']));
            // Reasons are authorized correction context, never part of a public/printed payment receipt.
            $public['reason'] = $row['reason'];
            $public['sales_owned'] = $row['sale_id'] !== null;
            if (!$public['sales_owned']) {
                $e = $this->evidence($c, $id, 'receivable_adjustment', $row['reversal_of_adjustment_id'] === null ? 'receivable.adjusted' : 'receivable.adjustment_reversed');
                if (($e['amount_delta'] ?? null) !== $row['amount_delta'] || ($e['receivable_public_id'] ?? null) !== $row['receivable_public_id']) $this->invalid();
                foreach (['bill_balance_before', 'bill_balance_after', 'customer_balance_before', 'customer_balance_after'] as $key) $public[$key] = (string) $e[$key];
            }
            return $public;
        });
    }

    public function adjustmentHistory(ShopContext $c, string $bill, array $input): array
    {
        $this->access->read($c, 'customer.view'); $this->access->read($c, 'debt.view');
        DraftInput::only($input, ['page']);
        $page = filter_var($input['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]);
        if ($page === false) throw new ApiException('VALIDATION_FAILED', 'Choose a valid page.', 422);
        (new DebtSchema($this->database))->requireReady();
        return (new DebtReadSnapshot($this->database))->run(function () use ($c, $bill, $page): array {
            $row = $this->database->query('SELECT id FROM receivables WHERE tenant_id=? AND shop_id=? AND public_id=?', [$c->tenantId, $c->shopId, DraftInput::uuid($bill)])->getRowArray();
            if (!$row) throw new ApiException('RESOURCE_NOT_FOUND', 'This bill was not found.', 404);
            $bind = [$c->tenantId, $c->shopId, $row['id']];
            $total = (int) $this->database->query('SELECT COUNT(*) n FROM receivable_adjustments WHERE tenant_id=? AND shop_id=? AND receivable_id=?', $bind)->getRowArray()['n'];
            $rows = $this->database->query('SELECT public_id FROM receivable_adjustments WHERE tenant_id=? AND shop_id=? AND receivable_id=? ORDER BY posted_at DESC,id DESC LIMIT 20 OFFSET ?', [...$bind, ($page - 1) * 20])->getResultArray();
            return ['items' => array_map(fn(array $r): array => $this->showAdjustment($c, $r['public_id']), $rows),
                'pagination' => ['page' => $page, 'per_page' => 20, 'total' => $total, 'total_pages' => (int) ceil($total / 20)]];
        });
    }

    private function adjustment(ShopContext $c, string $id): ?array
    {
        return $this->database->query('SELECT a.*,b.public_id receivable_public_id,c.public_id customer_public_id,c.display_name customer_name,s.public_id sale_public_id,
            original.public_id reversal_of_public_id,inverse.public_id reversed_by_public_id FROM receivable_adjustments a
            JOIN receivables b ON b.tenant_id=a.tenant_id AND b.shop_id=a.shop_id AND b.id=a.receivable_id
            JOIN customers c ON c.tenant_id=b.tenant_id AND c.id=b.customer_id
            JOIN sales s ON s.tenant_id=b.tenant_id AND s.shop_id=b.shop_id AND s.id=b.sale_id
            LEFT JOIN receivable_adjustments original ON original.tenant_id=a.tenant_id AND original.shop_id=a.shop_id AND original.id=a.reversal_of_adjustment_id
            LEFT JOIN receivable_adjustments inverse ON inverse.tenant_id=a.tenant_id AND inverse.shop_id=a.shop_id AND inverse.reversal_of_adjustment_id=a.id
            WHERE a.tenant_id=? AND a.shop_id=? AND a.public_id=?', [$c->tenantId, $c->shopId, $id])->getRowArray();
    }

    private function evidence(ShopContext $c, string $id, string $resource, string $action): array
    {
        $row = $this->database->table('audit_events')->select('change_metadata')->where(['tenant_id' => $c->tenantId, 'shop_id' => $c->shopId,
            'resource_type' => $resource, 'resource_public_id' => $id, 'action_code' => $action])->get(1)->getRowArray();
        $metadata = $row ? json_decode($row['change_metadata'] ?? '', true, 512, JSON_THROW_ON_ERROR) : null;
        if (!is_array($metadata)) $this->identityConflict();
        return $metadata;
    }
    private function lockSale(ShopContext $c, string $id): void
    {
        if (!$this->database->query('SELECT id FROM sales WHERE tenant_id=? AND shop_id=? AND public_id=? FOR UPDATE', [$c->tenantId, $c->shopId, $id])->getRowArray()) $this->invalid();
    }
    private function billState(array $bills): array
    {
        return array_map(static fn(array $b): array => [$b['public_id'], $b['adjustment_delta'], $b['collected_amount'], $b['outstanding_amount'], $b['due_date']], $bills);
    }
    private function actor(ShopContext $c): AuditActor { return AuditActor::tenantUser($c->userId, $c->membershipId, $c->tenantId, $c->shopId); }
    private function identityConflict(): never { throw new ApiException('DEBT_IDENTITY_CONFLICT', 'This reference belongs to different saved details. Open the saved record before making another change.', 409); }
    private function invalid(): never { throw new ApiException('DEBT_EVIDENCE_INVALID', 'These linked records need checking. Ask the shop owner for help.', 409); }
}
