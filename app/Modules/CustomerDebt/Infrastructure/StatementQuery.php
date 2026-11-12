<?php

declare(strict_types=1);

namespace App\Modules\CustomerDebt\Infrastructure;

use App\Exceptions\ApiException;
use App\Modules\CustomerDebt\Application\{DebtPaymentInput, StatementCursor, StatementInput};
use App\Modules\CustomerDebt\Domain\DebtAmount;
use App\Modules\Sales\Application\{CommercialAccess, DraftInput};
use App\Modules\TenantShop\Infrastructure\ShopContext;
use App\Support\UtcTimestamp;
use CodeIgniter\Database\BaseConnection;

/** Authorized snapshot query; no balances, drafts, cursors or reports are persisted. */
final class StatementQuery
{
    public function __construct(
        private readonly BaseConnection $database,
        private readonly CommercialAccess $access,
    ) {}

    public function show(ShopContext $context, string $id, array $input): array
    {
        $this->access->read($context, 'customer.view');
        $this->access->read($context, 'debt.view');
        (new DebtSchema($this->database))->requireReady();
        $codec = new StatementCursor(config(\Config\Encryption::class)->key);
        return (new DebtReadSnapshot($this->database))->run(function () use ($context, $id, $input, $codec): array {
            $repository = new DebtPaymentRepository($this->database);
            $customer = $repository->customer($context, DraftInput::uuid($id));
            $shop = $this->database->table('shops')->select('public_id,name,timezone_name')
                ->where(['tenant_id' => $context->tenantId, 'id' => $context->shopId])->get()->getRowArray();
            if (!$shop) {
                throw new ApiException('RESOURCE_NOT_FOUND', 'This shop was not found.', 404);
            }
            $filters = StatementInput::normalize($input, $shop['timezone_name']);
            $version = $this->version($context);
            $binding = DebtPaymentInput::fingerprint([
                $context->tenantId, $context->shopId, $context->membershipId, $context->userId, $customer['public_id'],
                $filters['from_date'], $filters['to_date'], $filters['per_page'], $shop['timezone_name'],
            ]);
            $anchor = $filters['cursor'] === null ? null : $codec->decode($filters['cursor']);
            if ($anchor && (($anchor['binding'] ?? null) !== $binding || ($anchor['version'] ?? null) !== $version)) {
                $this->refresh();
            }
            [$events, $binds] = (new StatementEventQuery())->projection($context, (int) $customer['id']);
            [$range, $rangeBinds] = $this->range($filters);
            $totals = $this->database->query(
                "SELECT COUNT(*) event_count,
                 COALESCE(SUM(CASE WHEN e.amount_delta>0 THEN e.amount_delta ELSE 0.00 END),0.00) increases,
                 COALESCE(SUM(CASE WHEN e.amount_delta<0 THEN -e.amount_delta ELSE 0.00 END),0.00) decreases
                 FROM ($events) e WHERE $range", [...$binds, ...$rangeBinds],
            )->getRowArray();
            $opening = '0.00';
            if ($filters['from_utc'] !== null) {
                $opening = $this->sum($events, $binds, 'e.posted_at<?', [$filters['from_utc']]);
            }
            $increases = DebtAmount::sum([(string) $totals['increases']]);
            $decreases = DebtAmount::sum([(string) $totals['decreases']]);
            $closing = DebtAmount::subtract(DebtAmount::sum([$opening, $increases]), $decreases);
            $bills = $repository->balances($context, (int) $customer['id']);
            $current = DebtAmount::sum(array_column($bills, 'outstanding_amount'));
            if (DebtAmount::compare($this->sum($events, $binds, '1=1', []), $current) !== 0) {
                throw new ApiException('DEBT_EVIDENCE_INVALID', 'The statement does not match the bills. Ask the shop owner to check the linked records.', 409);
            }
            $running = $anchor['running'] ?? $opening;
            $seen = $anchor['seen'] ?? 0;
            if ($anchor && (($anchor['opening'] ?? null) !== $opening || ($anchor['closing'] ?? null) !== $closing
                || !is_string($running) || !is_int($seen) || $seen < 0
                || ($seen > 0 && $seen >= (int) $totals['event_count'])
                || ($seen === 0 && (($anchor['last'] ?? null) !== null || $running !== $opening))
                || ($seen > 0 && (!is_array($anchor['last'] ?? null) || count($anchor['last']) !== 3)))) {
                $this->refresh();
            }
            $pageStart = DebtAmount::sum([$running]);
            $where = $range;
            $pageBinds = [...$binds, ...$rangeBinds];
            if ($anchor && $seen > 0) {
                [$time, $rank, $eventId] = $anchor['last'];
                if (!is_string($time) || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}\.[0-9]{6}$/D', $time)
                    || !in_array($rank, [1, 2, 3], true) || !is_string($eventId) || !preg_match('/^[1-9][0-9]{0,20}$/D', $eventId)) {
                    $this->refresh();
                }
                $where .= ' AND (e.posted_at,e.event_rank,e.event_id)>(?,?,CAST(? AS DECIMAL(21,0)))';
                array_push($pageBinds, $time, $rank, $eventId);
            }
            $rows = $this->database->query(
                "SELECT e.* FROM ($events) e WHERE $where ORDER BY e.posted_at,e.event_rank,e.event_id LIMIT ?",
                [...$pageBinds, $filters['per_page'] + 1],
            )->getResultArray();
            $more = count($rows) > $filters['per_page'];
            if ($more) {
                array_pop($rows);
            }
            $items = [];
            foreach ($rows as $row) {
                $negative = str_starts_with((string) $row['amount_delta'], '-');
                $amount = DebtAmount::sum([$negative ? substr((string) $row['amount_delta'], 1) : (string) $row['amount_delta']]);
                $running = $negative ? DebtAmount::subtract($running, $amount) : DebtAmount::sum([$running, $amount]);
                $items[] = $this->event($context, $row, $repository) + [
                    'amount_delta' => ($negative && $amount !== '0.00' ? '-' : '') . $amount,
                    'direction' => $amount === '0.00' ? 'NONE' : ($negative ? 'DECREASE' : 'INCREASE'),
                    'running_amount' => $running,
                ];
            }
            if (!$more && $running !== $closing) {
                throw new ApiException('DEBT_EVIDENCE_INVALID', 'The statement totals need checking. Ask the shop owner for help.', 409);
            }
            $asOf = $anchor['as_of'] ?? UtcTimestamp::now();
            $expires = $anchor['expires'] ?? time() + 1800;
            $pageCursor = $filters['cursor'] ?? $codec->encode(['v' => 1, 'binding' => $binding, 'version' => $version,
                'last' => null, 'opening' => $opening, 'closing' => $closing, 'running' => $opening, 'seen' => 0,
                'as_of' => $asOf, 'expires' => $expires]);
            $next = null;
            if ($more) {
                $last = $rows[array_key_last($rows)];
                $next = $codec->encode(['v' => 1, 'binding' => $binding, 'version' => $version,
                    'last' => [$last['posted_at'], (int) $last['event_rank'], (string) $last['event_id']],
                    'opening' => $opening, 'closing' => $closing, 'running' => $running, 'seen' => $seen + count($items),
                    'as_of' => $asOf, 'expires' => $expires]);
            }
            return [
                'customer' => ['public_id' => $customer['public_id'], 'display_name' => $customer['display_name'], 'status' => $customer['status']],
                'shop' => ['public_id' => $shop['public_id'], 'name' => $shop['name'], 'timezone' => $shop['timezone_name']],
                'filters' => ['from_date' => $filters['from_date'], 'to_date' => $filters['to_date']],
                'summary' => ['opening_amount' => $opening, 'increase_amount' => $increases, 'decrease_amount' => $decreases,
                    'closing_amount' => $closing, 'current_amount' => $current],
                'items' => $items, 'as_of' => $asOf,
                'pagination' => ['per_page' => $filters['per_page'], 'total' => (int) $totals['event_count'],
                    'from_record' => count($items) ? $seen + 1 : 0, 'to_record' => $seen + count($items),
                    'page_start_amount' => $pageStart, 'page_end_amount' => $running, 'page_cursor' => $pageCursor, 'next_cursor' => $next],
            ];
        });
    }

    private function version(ShopContext $context): array
    {
        $generations = $this->database->query("SELECT id,generation_uuid FROM sync_generations WHERE status='ACTIVE'")->getResultArray();
        if (count($generations) !== 1) {
            throw new ApiException('SYNC_GENERATION_UNAVAILABLE', 'Statement pages are temporarily unavailable. Try again shortly.', 503);
        }
        $generation = $generations[0];
        $latest = $this->database->query(
            'SELECT COALESCE(MAX(change_sequence),0) value FROM sync_changes WHERE sync_generation_id=? AND tenant_id=? AND (shop_id=? OR shop_id IS NULL)',
            [$generation['id'], $context->tenantId, $context->shopId],
        )->getRowArray();
        return ['generation' => $generation['generation_uuid'], 'latest' => (string) $latest['value']];
    }

    private function range(array $filters): array
    {
        $parts = ['1=1'];
        $binds = [];
        if ($filters['from_utc'] !== null) {
            $parts[] = 'e.posted_at>=?';
            $binds[] = $filters['from_utc'];
        }
        if ($filters['until_utc'] !== null) {
            $parts[] = 'e.posted_at<?';
            $binds[] = $filters['until_utc'];
        }
        return [implode(' AND ', $parts), $binds];
    }

    private function sum(string $events, array $binds, string $where, array $extra): string
    {
        $value = (string) $this->database->query("SELECT COALESCE(SUM(e.amount_delta),0.00) value FROM ($events) e WHERE $where", [...$binds, ...$extra])->getRowArray()['value'];
        return DebtAmount::sum([$value]);
    }

    private function event(ShopContext $context, array $row, DebtPaymentRepository $repository): array
    {
        $out = ['public_id' => $row['public_id'], 'event_type' => $row['event_type'], 'source_type' => $row['source_type'],
            'sale_public_id' => $row['sale_public_id'], 'receivable_public_id' => $row['bill_public_id'],
            'original_public_id' => $row['original_public_id'], 'channel' => $row['channel'],
            'occurred_at' => $row['occurred_at'], 'posted_at' => $row['posted_at']];
        if ($row['source_type'] === 'SALE_CHANGE') {
            $payments = $this->database->query('SELECT direction,channel,amount FROM sale_payments WHERE tenant_id=? AND shop_id=? AND sale_id=? ORDER BY id',
                [$context->tenantId, $context->shopId, $row['source_id']])->getResultArray();
            $out['money_payments'] = array_map(static fn(array $p): array => [
                'direction' => $p['direction'], 'channel' => $p['channel'], 'amount' => (string) $p['amount'],
            ], $payments);
        }
        if ($row['source_type'] === 'PAYMENT') {
            $payment = $repository->payment($context, $row['public_id']);
            if (!$payment) {
                throw new ApiException('DEBT_EVIDENCE_INVALID', 'This payment needs checking. Ask the shop owner for help.', 409);
            }
            $evidence = $repository->recordedEvidence($context, $row['public_id']);
            $out['allocations'] = $repository->verifiedAllocations($context, $payment, $evidence['metadata']);
            $out['reversed_by_public_id'] = $payment['reversed_by_public_id'];
            $out['replacement_public_id'] = $evidence['metadata']['replacement_public_id'] ?? null;
            $out['corrects_public_id'] = $evidence['metadata']['corrects_public_id'] ?? null;
        }
        return $out;
    }

    private function refresh(): never
    {
        throw new ApiException('STATEMENT_REFRESH_REQUIRED', 'The records changed while you were reading. Refresh the statement from the beginning.', 409);
    }
}
