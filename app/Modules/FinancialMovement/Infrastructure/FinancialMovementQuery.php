<?php

declare(strict_types=1);

namespace App\Modules\FinancialMovement\Infrastructure;

use App\Exceptions\ApiException;
use App\Modules\CustomerDebt\Infrastructure\DebtReadSnapshot;
use App\Modules\FinancialMovement\Domain\MovementAmount;
use App\Modules\Sales\Application\{CommercialAccess, DraftInput};
use App\Modules\TenantShop\Infrastructure\ShopContext;
use DateTimeImmutable;
use DateTimeZone;
use CodeIgniter\Database\BaseConnection;

final class FinancialMovementQuery
{
    public function __construct(private readonly BaseConnection $database, private readonly CommercialAccess $access) {}

    public function show(ShopContext $context, string $id): array
    {
        $this->authorize($context);
        return (new DebtReadSnapshot($this->database))->run(function () use ($context, $id): array {
            $repository = new FinancialMovementRepository($this->database);
            $row = $repository->movement($context, DraftInput::uuid($id));
            if (!$row) {
                throw new ApiException('FINANCIAL_MOVEMENT_NOT_FOUND', 'This money record is not saved yet. Check again before starting another expense.', 404);
            }
            return $this->output($row, $repository->recordedEvidence($context, $row));
        });
    }

    public function correctionState(ShopContext $context, string $id): array
    {
        $this->authorize($context);
        return (new DebtReadSnapshot($this->database))->run(function () use ($context, $id): array {
            $repository = new FinancialMovementRepository($this->database);
            $requested = $repository->movement($context, DraftInput::uuid($id));
            if (!$requested) {
                throw new ApiException('FINANCIAL_MOVEMENT_NOT_FOUND', 'This money record was not found in this shop.', 404);
            }
            if ($requested['movement_type'] === 'REVERSAL') {
                $requested = $repository->movementById($context, (int) $requested['reversal_of_movement_id']);
                if (!$requested) {
                    throw new ApiException('FINANCIAL_EVIDENCE_INVALID', 'The original money record is missing. Ask the shop owner to investigate.', 409);
                }
            }

            $root = $requested;
            $seen = [(int) $root['id'] => true];
            $depth = 0;
            while ($root['replacement_for_movement_id'] !== null) {
                if (++$depth > 100) {
                    throw new ApiException('FINANCIAL_EVIDENCE_INVALID', 'The correction history is too long to display safely.', 409);
                }
                $parentId = (int) $root['replacement_for_movement_id'];
                if (isset($seen[$parentId])) {
                    throw new ApiException('FINANCIAL_EVIDENCE_INVALID', 'The correction history contains a cycle.', 409);
                }
                $seen[$parentId] = true;
                $parent = $repository->movementById($context, $parentId);
                if (!$parent || $parent['movement_type'] === 'REVERSAL') {
                    throw new ApiException('FINANCIAL_EVIDENCE_INVALID', 'The correction history has a missing or invalid original record.', 409);
                }
                $root = $parent;
            }

            $items = [];
            $currentEffective = $root['public_id'];
            $current = $root;
            $visited = [];
            $depth = 0;
            while ($current) {
                if (++$depth > 100 || isset($visited[(int) $current['id']])) {
                    throw new ApiException('FINANCIAL_EVIDENCE_INVALID', 'The correction history contains a cycle or is too long.', 409);
                }
                $visited[(int) $current['id']] = true;
                $currentEvidence = $repository->recordedEvidence($context, $current);
                $items[] = $this->output($current, $currentEvidence) + ['relationship' => $current['movement_type'] === 'FINANCIAL_CORRECTION' ? 'Corrected replacement' : 'Original record'];

                $inverse = $repository->reversalChild($context, (int) $current['id']);
                $children = $repository->replacementChildren($context, (int) $current['id']);
                $inverseEvidence = null;
                if (count($children) > 1) {
                    throw new ApiException('FINANCIAL_EVIDENCE_INVALID', 'This correction history has more than one replacement for the same record.', 409);
                }
                if ($inverse) {
                    if ($inverse['movement_type'] !== 'REVERSAL'
                        || $inverse['channel'] !== $current['channel']
                        || (string) $inverse['amount_delta'] !== $this->inverse((string) $current['amount_delta'])) {
                        throw new ApiException('FINANCIAL_EVIDENCE_INVALID', 'The undo record does not exactly match the original.', 409);
                    }
                    $inverseEvidence = $repository->recordedEvidence($context, $inverse);
                    $items[] = $this->output($inverse, $inverseEvidence) + ['relationship' => 'Undo record'];
                }
                if ($children) {
                    if (!$inverse || $children[0]['movement_type'] !== 'FINANCIAL_CORRECTION'
                        || (int) $children[0]['replacement_for_movement_id'] !== (int) $current['id']) {
                        throw new ApiException('FINANCIAL_EVIDENCE_INVALID', 'The corrected replacement is not linked to a complete undo.', 409);
                    }
                    $replacementEvidence = $repository->recordedEvidence($context, $children[0]);
                    if (($inverseEvidence['counterpart_public_id'] ?? null) !== $children[0]['public_id']
                        || ($replacementEvidence['counterpart_public_id'] ?? null) !== ($inverse['public_id'] ?? null)) {
                        throw new ApiException('FINANCIAL_EVIDENCE_INVALID', 'The undo and corrected replacement do not identify each other.', 409);
                    }
                    $current = $children[0];
                    $currentEffective = $current['public_id'];
                } else {
                    if ($inverse && ($inverseEvidence['counterpart_public_id'] ?? null) !== null) {
                        throw new ApiException('FINANCIAL_EVIDENCE_INVALID', 'The undo record points to a replacement that is missing.', 409);
                    }
                    if ($inverse) $currentEffective = null;
                    $current = null;
                }
            }
            return [
                'source_public_id' => $requested['public_id'],
                'current_public_id' => $currentEffective,
                'items' => $items,
            ];
        });
    }

    public function history(ShopContext $context, array $input): array
    {
        $this->authorize($context);
        DraftInput::only($input, ['page', 'per_page', 'channel', 'from_date', 'to_date']);
        $page = $this->integer($input['page'] ?? 1, 100000);
        $size = $this->integer($input['per_page'] ?? 20, 50);
        $channel = $input['channel'] ?? 'ALL';
        if (!in_array($channel, ['ALL', 'CASH', 'TRANSFER'], true)) {
            throw new ApiException('VALIDATION_FAILED', 'Choose Cash, Transfer or All.', 422);
        }
        return (new DebtReadSnapshot($this->database))->run(function () use ($context, $input, $page, $size, $channel): array {
            $shop = $this->database->table('shops')->select('name,timezone_name')
                ->where(['tenant_id' => $context->tenantId, 'id' => $context->shopId])->get()->getRowArray();
            if (!$shop) {
                throw new ApiException('RESOURCE_NOT_FOUND', 'This shop was not found.', 404);
            }
            $zone = new DateTimeZone($shop['timezone_name']);
            $from = $this->date($input['from_date'] ?? null, 'from_date', $zone);
            $to = $this->date($input['to_date'] ?? null, 'to_date', $zone);
            if ($from && $to && $from > $to) {
                throw new ApiException('VALIDATION_FAILED', 'The start date must be on or before the end date.', 422,
                    ['from_date' => ['Choose an earlier start date.']]);
            }
            $where = 'm.tenant_id=? AND m.shop_id=?';
            $binds = [$context->tenantId, $context->shopId];
            if ($channel !== 'ALL') {
                $where .= ' AND m.channel=?';
                $binds[] = $channel;
            }
            $utc = new DateTimeZone('UTC');
            if ($from) {
                $where .= ' AND m.posted_at>=?';
                $binds[] = $from->setTimezone($utc)->format('Y-m-d H:i:s.u');
            }
            if ($to) {
                $where .= ' AND m.posted_at<?';
                $binds[] = $to->modify('+1 day')->setTime(0, 0)->setTimezone($utc)->format('Y-m-d H:i:s.u');
            }
            $total = (int) $this->database->query("SELECT COUNT(*) total FROM financial_movements m WHERE $where", $binds)->getRowArray()['total'];
            $ids = $this->database->query("SELECT m.public_id FROM financial_movements m WHERE $where ORDER BY m.posted_at DESC,m.id DESC LIMIT ? OFFSET ?",
                [...$binds, $size, ($page - 1) * $size])->getResultArray();
            $repository = new FinancialMovementRepository($this->database);
            $items = [];
            foreach ($ids as $id) {
                $row = $repository->movement($context, $id['public_id']);
                if (!$row) {
                    throw new ApiException('FINANCIAL_EVIDENCE_INVALID', 'This money history needs checking. Ask the shop owner for help.', 409);
                }
                $items[] = $this->output($row, $repository->recordedEvidence($context, $row));
            }
            return ['items' => $items, 'shop' => ['name' => $shop['name'], 'timezone' => $shop['timezone_name']],
                'filters' => ['channel' => $channel, 'from_date' => $from?->format('Y-m-d'), 'to_date' => $to?->format('Y-m-d')],
                'pagination' => ['page' => $page, 'per_page' => $size, 'total' => $total, 'total_pages' => (int) ceil($total / $size)]];
        });
    }

    private function output(array $row, array $evidence): array
    {
        return ['public_id' => $row['public_id'], 'movement_type' => $row['movement_type'], 'channel' => $row['channel'],
            'amount_delta' => (string) $row['amount_delta'], 'amount' => MovementAmount::fromDelta((string) $row['amount_delta'])->magnitude,
            'physical_cash_effect' => $row['channel'] === 'CASH' ? (string) $row['amount_delta'] : '0.00',
            'description' => $row['description'], 'audit_reason' => $evidence['audit_reason'],
            'external_reference' => $row['external_reference'],
            'occurred_at' => $row['occurred_at'], 'posted_at' => $row['posted_at'],
            'shop_name' => $row['shop_name'], 'shop_timezone' => $row['timezone_name'],
            'recorded_by' => $row['recorded_by'] ?: 'Shop user',
            'reversal_of_public_id' => $row['reversal_of_public_id'], 'replacement_for_public_id' => $row['replacement_for_public_id'],
            'reversed_by_public_id' => $row['reversed_by_public_id'], 'replacement_public_id' => $row['replacement_public_id']];
    }

    private function inverse(string $delta): string
    {
        return str_starts_with($delta, '-') ? substr($delta, 1) : '-' . $delta;
    }

    private function authorize(ShopContext $context): void
    {
        $this->access->read($context, 'financial.view');
        (new FinancialMovementSchema($this->database))->requireReady();
    }

    private function integer(mixed $value, int $max): int
    {
        if ((!is_string($value) && !is_int($value)) || filter_var($value, FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => $max]]) === false) {
            throw new ApiException('VALIDATION_FAILED', 'Choose a valid page and number of records.', 422);
        }
        return (int) $value;
    }

    private function date(mixed $value, string $field, DateTimeZone $zone): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || !preg_match('/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$/D', $value) || $value === '9999-12-31') {
            throw new ApiException('VALIDATION_FAILED', 'Choose a valid calendar date.', 422, [$field => ['Check the day, month and year.']]);
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $zone);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new ApiException('VALIDATION_FAILED', 'Choose a valid calendar date.', 422, [$field => ['Check the day, month and year.']]);
        }
        return $date;
    }
}
