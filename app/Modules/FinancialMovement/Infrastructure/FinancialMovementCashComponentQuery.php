<?php

declare(strict_types=1);

namespace App\Modules\FinancialMovement\Infrastructure;

use App\Exceptions\ApiException;
use App\Modules\FinancialMovement\Domain\MovementAmount;
use App\Modules\Reconciliation\Application\CashAmount;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use CodeIgniter\Database\BaseConnection;

/** Signed CASH effects are summed from immutable rows, never from mutable balance state. */
final class FinancialMovementCashComponentQuery
{
    public function __construct(private readonly BaseConnection $database) {}

    public function forPeriod(ShopContext $context, string $periodStartedAt, string $cutoffAt): array
    {
        (new FinancialMovementSchema($this->database))->requireReady();
        $scopePeriod = 'm.tenant_id=? AND m.shop_id=? AND m.posted_at>? AND m.posted_at<=?';
        $where = $scopePeriod . ' AND m.channel=\'CASH\'';
        $binds = [$context->tenantId, $context->shopId, $periodStartedAt, $cutoffAt];
        $aggregate = $this->database->query(
            "SELECT
                COALESCE(SUM(CASE WHEN m.movement_type='OWNER_INTRODUCTION' THEN m.amount_delta ELSE 0.00 END),0.00) introductions,
                COALESCE(SUM(CASE WHEN m.movement_type='SHOP_EXPENSE' THEN -m.amount_delta ELSE 0.00 END),0.00) expenses,
                COALESCE(SUM(CASE WHEN m.movement_type='OWNER_WITHDRAWAL' THEN -m.amount_delta ELSE 0.00 END),0.00) withdrawals,
                COUNT(*) source_count,
                COALESCE(SUM(CASE WHEN m.movement_type='OWNER_INTRODUCTION' THEN 1 ELSE 0 END),0) introduction_count,
                COALESCE(SUM(CASE WHEN m.movement_type='SHOP_EXPENSE' THEN 1 ELSE 0 END),0) expense_count,
                COALESCE(SUM(CASE WHEN m.movement_type='OWNER_WITHDRAWAL' THEN 1 ELSE 0 END),0) withdrawal_count,
                COALESCE(SUM(CASE WHEN m.movement_type='FINANCIAL_CORRECTION' THEN 1 ELSE 0 END),0) correction_count,
                COALESCE(SUM(CASE WHEN m.movement_type='REVERSAL' THEN 1 ELSE 0 END),0) reversal_count,
                COALESCE(SUM(CASE WHEN m.movement_type NOT IN ('OWNER_INTRODUCTION','SHOP_EXPENSE','OWNER_WITHDRAWAL','FINANCIAL_CORRECTION','REVERSAL')
                    OR (m.movement_type='OWNER_INTRODUCTION' AND (m.amount_delta<=0 OR m.reversal_of_movement_id IS NOT NULL OR m.replacement_for_movement_id IS NOT NULL))
                    OR (m.movement_type IN ('SHOP_EXPENSE','OWNER_WITHDRAWAL') AND (m.amount_delta>=0 OR m.reversal_of_movement_id IS NOT NULL OR m.replacement_for_movement_id IS NOT NULL))
                    OR (m.movement_type='REVERSAL' AND EXISTS (SELECT 1 FROM financial_movements child_reverse
                        WHERE child_reverse.tenant_id=m.tenant_id AND child_reverse.shop_id=m.shop_id AND child_reverse.reversal_of_movement_id=m.id))
                    OR EXISTS (SELECT 1 FROM financial_movements child WHERE child.tenant_id=m.tenant_id AND child.shop_id=m.shop_id
                        AND child.reversal_of_movement_id=m.id AND (child.movement_type<>'REVERSAL'
                            OR child.channel<>m.channel OR child.amount_delta<>-m.amount_delta))
                    OR (m.movement_type IN ('OWNER_INTRODUCTION','SHOP_EXPENSE','OWNER_WITHDRAWAL') AND (
                        m.correlation_id IS NULL OR
                        (SELECT COUNT(*) FROM audit_events a WHERE a.tenant_id=m.tenant_id AND a.shop_id=m.shop_id
                            AND a.resource_type='financial_movement' AND a.resource_public_id=m.public_id
                            AND a.action_code='financial_movement.recorded' AND a.correlation_id=m.correlation_id
                            AND a.reason=m.description
                            AND JSON_UNQUOTE(JSON_EXTRACT(a.change_metadata,'$.movement_type'))=m.movement_type
                            AND JSON_UNQUOTE(JSON_EXTRACT(a.change_metadata,'$.channel'))=m.channel
                            AND JSON_UNQUOTE(JSON_EXTRACT(a.change_metadata,'$.amount_delta'))=m.amount_delta
                            AND JSON_UNQUOTE(JSON_EXTRACT(a.change_metadata,'$.command_fingerprint')) REGEXP '^[a-f0-9]{64}
                    )) THEN 1 ELSE 0 END),0) invalid_count
             FROM financial_movements m WHERE $where",
            $binds,
        )->getRowArray();

        if ((int) $aggregate['invalid_count'] !== 0) $this->invalid();

        $repository = new FinancialMovementRepository($this->database);
        $rows = $this->database->query(
            "SELECT m.* FROM financial_movements m
             LEFT JOIN financial_movements source ON source.tenant_id=m.tenant_id AND source.shop_id=m.shop_id
                AND source.id=COALESCE(m.reversal_of_movement_id,m.replacement_for_movement_id)
             WHERE $scopePeriod AND m.movement_type IN ('FINANCIAL_CORRECTION','REVERSAL')
               AND (m.channel='CASH' OR source.channel='CASH')
             ORDER BY m.posted_at,m.id",
            $binds,
        )->getResultArray();

        $corrections = [];
        $reversals = [];
        $reversalsByType = [];
        foreach ($rows as $row) {
            $type = $row['movement_type'];
            $delta = (string) $row['amount_delta'];
            MovementAmount::fromDelta($delta);
            $evidence = $repository->recordedEvidence($context, $row);

            if ($type === 'REVERSAL') {
                $source = $repository->movementById($context, (int) $row['reversal_of_movement_id']);
                if (!$source || !in_array($source['movement_type'], ['SHOP_EXPENSE', 'OWNER_WITHDRAWAL', 'OWNER_INTRODUCTION', 'FINANCIAL_CORRECTION'], true)
                    || $source['channel'] !== $row['channel']
                    || $delta !== $this->inverse((string) $source['amount_delta'])) {
                    $this->invalid();
                }
                $repository->recordedEvidence($context, $source);
                $inverse = $repository->reversalChild($context, (int) $source['id']);
                $children = $repository->replacementChildren($context, (int) $source['id']);
                if (!$inverse || $inverse['public_id'] !== $row['public_id']) $this->invalid();
                if (($evidence['counterpart_public_id'] ?? null) === null) {
                    if ($children) $this->invalid();
                } else {
                    if (count($children) !== 1 || $children[0]['public_id'] !== $evidence['counterpart_public_id']) $this->invalid();
                    $replacement = $children[0];
                    if ($replacement['movement_type'] !== 'FINANCIAL_CORRECTION'
                        || $replacement['correlation_id'] !== $row['correlation_id']
                        || $replacement['posted_at'] !== $row['posted_at']) $this->invalid();
                    $replacementEvidence = $repository->recordedEvidence($context, $replacement);
                    if (($replacementEvidence['counterpart_public_id'] ?? null) !== $row['public_id']
                        || ($replacementEvidence['command_fingerprint'] ?? null) !== ($evidence['command_fingerprint'] ?? null)
                        || ($replacementEvidence['review_fingerprint'] ?? null) !== ($evidence['review_fingerprint'] ?? null)) $this->invalid();
                }
                $this->validateAncestors($context, $repository, $source);
                if ($row['channel'] === 'CASH') {
                    $reversals[] = $delta;
                    $sourceType = $source['movement_type'];
                    $reversalsByType[$sourceType] ??= [];
                    $reversalsByType[$sourceType][] = $delta;
                }
                continue;
            }

            $source = $repository->movementById($context, (int) $row['replacement_for_movement_id']);
            if (!$source || $source['movement_type'] === 'REVERSAL') $this->invalid();
            $inverse = $repository->reversalChild($context, (int) $source['id']);
            $children = $repository->replacementChildren($context, (int) $source['id']);
            if (!$inverse || $inverse['movement_type'] !== 'REVERSAL'
                || $inverse['channel'] !== $source['channel']
                || (string) $inverse['amount_delta'] !== $this->inverse((string) $source['amount_delta'])
                || count($children) !== 1 || $children[0]['public_id'] !== $row['public_id']
                || $inverse['correlation_id'] !== $row['correlation_id']
                || $inverse['posted_at'] !== $row['posted_at']) $this->invalid();
            $repository->recordedEvidence($context, $source);
            $inverseEvidence = $repository->recordedEvidence($context, $inverse);
            if (($inverseEvidence['counterpart_public_id'] ?? null) !== $row['public_id']
                || ($evidence['counterpart_public_id'] ?? null) !== $inverse['public_id']
                || ($inverseEvidence['command_fingerprint'] ?? null) !== ($evidence['command_fingerprint'] ?? null)
                || ($inverseEvidence['review_fingerprint'] ?? null) !== ($evidence['review_fingerprint'] ?? null)) $this->invalid();
            $this->validateAncestors($context, $repository, $source);
            if ($row['channel'] === 'CASH') $corrections[] = $delta;
        }

        $reversalAttribution = [];
        foreach ($reversalsByType as $sourceType => $values) {
            $reversalAttribution[] = ['source_movement_type' => $sourceType, 'amount_delta' => $this->sumSigned($values), 'count' => count($values)];
        }

        return [
            'cash_introductions' => CashAmount::parse((string) $aggregate['introductions']),
            'cash_expenses' => CashAmount::parse((string) $aggregate['expenses']),
            'cash_withdrawals' => CashAmount::parse((string) $aggregate['withdrawals']),
            'cash_financial_correction_replacements_net' => $this->sumSigned($corrections),
            'cash_financial_movement_reversals_net' => $this->sumSigned($reversals),
            'cash_financial_movement_corrections_net' => $this->sumSigned([...$corrections, ...$reversals]),
            'reversal_attribution' => $reversalAttribution,
            'source_count' => (int) $aggregate['source_count'],
            'type_counts' => [
                'OWNER_INTRODUCTION' => (int) $aggregate['introduction_count'],
                'SHOP_EXPENSE' => (int) $aggregate['expense_count'],
                'OWNER_WITHDRAWAL' => (int) $aggregate['withdrawal_count'],
                'FINANCIAL_CORRECTION' => (int) $aggregate['correction_count'],
                'REVERSAL' => (int) $aggregate['reversal_count'],
            ],
        ];
    }

    private function validateAncestors(ShopContext $context, FinancialMovementRepository $repository, array $cursor): void
    {
        $seen = [(int) $cursor['id'] => true];
        $depth = 0;
        while ($cursor['replacement_for_movement_id'] !== null) {
            if (++$depth > 100) $this->invalid();
            $parentId = (int) $cursor['replacement_for_movement_id'];
            if (isset($seen[$parentId])) $this->invalid();
            $seen[$parentId] = true;
            $parent = $repository->movementById($context, $parentId);
            if (!$parent || $parent['movement_type'] === 'REVERSAL') $this->invalid();
            $parentInverse = $repository->reversalChild($context, $parentId);
            $children = $repository->replacementChildren($context, $parentId);
            if (!$parentInverse || $parentInverse['movement_type'] !== 'REVERSAL'
                || $parentInverse['amount_delta'] !== $this->inverse((string) $parent['amount_delta'])
                || $parentInverse['channel'] !== $parent['channel']
                || $parentInverse['correlation_id'] !== $cursor['correlation_id']
                || $parentInverse['posted_at'] !== $cursor['posted_at']
                || count($children) !== 1 || (int) $children[0]['id'] !== (int) $cursor['id']) $this->invalid();
            $repository->recordedEvidence($context, $parent);
            $inverseEvidence = $repository->recordedEvidence($context, $parentInverse);
            $cursorEvidence = $repository->recordedEvidence($context, $cursor);
            if (($inverseEvidence['counterpart_public_id'] ?? null) !== $cursor['public_id']
                || ($cursorEvidence['counterpart_public_id'] ?? null) !== $parentInverse['public_id']
                || ($inverseEvidence['command_fingerprint'] ?? null) !== ($cursorEvidence['command_fingerprint'] ?? null)
                || ($inverseEvidence['review_fingerprint'] ?? null) !== ($cursorEvidence['review_fingerprint'] ?? null)) $this->invalid();
            $cursor = $parent;
        }
    }

    private function sumSigned(array $values): string
    {
        $positive = [];
        $negative = [];
        foreach ($values as $value) {
            $value = $this->signed((string) $value);
            if (str_starts_with($value, '-')) $negative[] = substr($value, 1);
            else $positive[] = $value;
        }
        $positiveTotal = \App\Modules\CustomerDebt\Domain\DebtAmount::sum($positive);
        $negativeTotal = \App\Modules\CustomerDebt\Domain\DebtAmount::sum($negative);
        $compare = \App\Modules\CustomerDebt\Domain\DebtAmount::compare($positiveTotal, $negativeTotal);
        if ($compare >= 0) return \App\Modules\CustomerDebt\Domain\DebtAmount::subtract($positiveTotal, $negativeTotal);
        return '-' . \App\Modules\CustomerDebt\Domain\DebtAmount::subtract($negativeTotal, $positiveTotal);
    }

    private function signed(string $value): string
    {
        $negative = str_starts_with($value, '-');
        $magnitude = CashAmount::parse($negative ? substr($value, 1) : $value);
        return $negative && $magnitude !== '0.00' ? '-' . $magnitude : $magnitude;
    }

    private function inverse(string $delta): string
    {
        return str_starts_with($delta, '-') ? substr($delta, 1) : '-' . $delta;
    }

    private function invalid(): never
    {
        throw new ApiException('EXPECTED_CASH_EVIDENCE_INVALID', 'A Cash financial movement or its correction history has inconsistent evidence. Review the linked money record before continuing.', 409);
    }
}

                        )<>1
                    )) THEN 1 ELSE 0 END),0) invalid_count
             FROM financial_movements m WHERE $where",
            $binds,
        )->getRowArray();

        if ((int) $aggregate['invalid_count'] !== 0) $this->invalid();

        $repository = new FinancialMovementRepository($this->database);
        $rows = $this->database->query(
            "SELECT m.* FROM financial_movements m WHERE $where
             AND m.movement_type IN ('FINANCIAL_CORRECTION','REVERSAL')
             ORDER BY m.posted_at,m.id",
            $binds,
        )->getResultArray();

        $corrections = [];
        $reversals = [];
        $reversalsByType = [];
        foreach ($rows as $row) {
            $type = $row['movement_type'];
            $delta = (string) $row['amount_delta'];
            MovementAmount::fromDelta($delta);
            $evidence = $repository->recordedEvidence($context, $row);

            if ($type === 'REVERSAL') {
                $source = $repository->movementById($context, (int) $row['reversal_of_movement_id']);
                if (!$source || !in_array($source['movement_type'], ['SHOP_EXPENSE', 'OWNER_WITHDRAWAL', 'OWNER_INTRODUCTION', 'FINANCIAL_CORRECTION'], true)
                    || $source['channel'] !== $row['channel']
                    || $delta !== $this->inverse((string) $source['amount_delta'])) {
                    $this->invalid();
                }
                $repository->recordedEvidence($context, $source);
                $inverse = $repository->reversalChild($context, (int) $source['id']);
                $children = $repository->replacementChildren($context, (int) $source['id']);
                if (!$inverse || $inverse['public_id'] !== $row['public_id']) $this->invalid();
                if (($evidence['counterpart_public_id'] ?? null) === null) {
                    if ($children) $this->invalid();
                } else {
                    if (count($children) !== 1 || $children[0]['public_id'] !== $evidence['counterpart_public_id']) $this->invalid();
                    $replacement = $children[0];
                    if ($replacement['movement_type'] !== 'FINANCIAL_CORRECTION'
                        || $replacement['correlation_id'] !== $row['correlation_id']
                        || $replacement['posted_at'] !== $row['posted_at']) $this->invalid();
                    $replacementEvidence = $repository->recordedEvidence($context, $replacement);
                    if (($replacementEvidence['counterpart_public_id'] ?? null) !== $row['public_id']
                        || ($replacementEvidence['command_fingerprint'] ?? null) !== ($evidence['command_fingerprint'] ?? null)
                        || ($replacementEvidence['review_fingerprint'] ?? null) !== ($evidence['review_fingerprint'] ?? null)) $this->invalid();
                }
                $this->validateAncestors($context, $repository, $source);
                $reversals[] = $delta;
                $sourceType = $source['movement_type'];
                $reversalsByType[$sourceType] ??= [];
                $reversalsByType[$sourceType][] = $delta;
                continue;
            }

            $source = $repository->movementById($context, (int) $row['replacement_for_movement_id']);
            if (!$source || $source['movement_type'] === 'REVERSAL') $this->invalid();
            $inverse = $repository->reversalChild($context, (int) $source['id']);
            $children = $repository->replacementChildren($context, (int) $source['id']);
            if (!$inverse || $inverse['movement_type'] !== 'REVERSAL'
                || $inverse['channel'] !== $source['channel']
                || (string) $inverse['amount_delta'] !== $this->inverse((string) $source['amount_delta'])
                || count($children) !== 1 || $children[0]['public_id'] !== $row['public_id']
                || $inverse['correlation_id'] !== $row['correlation_id']
                || $inverse['posted_at'] !== $row['posted_at']) $this->invalid();
            $repository->recordedEvidence($context, $source);
            $inverseEvidence = $repository->recordedEvidence($context, $inverse);
            if (($inverseEvidence['counterpart_public_id'] ?? null) !== $row['public_id']
                || ($evidence['counterpart_public_id'] ?? null) !== $inverse['public_id']
                || ($inverseEvidence['command_fingerprint'] ?? null) !== ($evidence['command_fingerprint'] ?? null)
                || ($inverseEvidence['review_fingerprint'] ?? null) !== ($evidence['review_fingerprint'] ?? null)) $this->invalid();
            $this->validateAncestors($context, $repository, $source);
            $corrections[] = $delta;
        }

        $reversalAttribution = [];
        foreach ($reversalsByType as $sourceType => $values) {
            $reversalAttribution[] = ['source_movement_type' => $sourceType, 'amount_delta' => $this->sumSigned($values), 'count' => count($values)];
        }

        return [
            'cash_introductions' => CashAmount::parse((string) $aggregate['introductions']),
            'cash_expenses' => CashAmount::parse((string) $aggregate['expenses']),
            'cash_withdrawals' => CashAmount::parse((string) $aggregate['withdrawals']),
            'cash_financial_correction_replacements_net' => $this->sumSigned($corrections),
            'cash_financial_movement_reversals_net' => $this->sumSigned($reversals),
            'cash_financial_movement_corrections_net' => $this->sumSigned([...$corrections, ...$reversals]),
            'reversal_attribution' => $reversalAttribution,
            'source_count' => (int) $aggregate['source_count'],
            'type_counts' => [
                'OWNER_INTRODUCTION' => (int) $aggregate['introduction_count'],
                'SHOP_EXPENSE' => (int) $aggregate['expense_count'],
                'OWNER_WITHDRAWAL' => (int) $aggregate['withdrawal_count'],
                'FINANCIAL_CORRECTION' => (int) $aggregate['correction_count'],
                'REVERSAL' => (int) $aggregate['reversal_count'],
            ],
        ];
    }

    private function validateAncestors(ShopContext $context, FinancialMovementRepository $repository, array $cursor): void
    {
        $seen = [(int) $cursor['id'] => true];
        $depth = 0;
        while ($cursor['replacement_for_movement_id'] !== null) {
            if (++$depth > 100) $this->invalid();
            $parentId = (int) $cursor['replacement_for_movement_id'];
            if (isset($seen[$parentId])) $this->invalid();
            $seen[$parentId] = true;
            $parent = $repository->movementById($context, $parentId);
            if (!$parent || $parent['movement_type'] === 'REVERSAL') $this->invalid();
            $parentInverse = $repository->reversalChild($context, $parentId);
            $children = $repository->replacementChildren($context, $parentId);
            if (!$parentInverse || $parentInverse['movement_type'] !== 'REVERSAL'
                || $parentInverse['amount_delta'] !== $this->inverse((string) $parent['amount_delta'])
                || $parentInverse['channel'] !== $parent['channel']
                || $parentInverse['correlation_id'] !== $cursor['correlation_id']
                || $parentInverse['posted_at'] !== $cursor['posted_at']
                || count($children) !== 1 || (int) $children[0]['id'] !== (int) $cursor['id']) $this->invalid();
            $repository->recordedEvidence($context, $parent);
            $inverseEvidence = $repository->recordedEvidence($context, $parentInverse);
            $cursorEvidence = $repository->recordedEvidence($context, $cursor);
            if (($inverseEvidence['counterpart_public_id'] ?? null) !== $cursor['public_id']
                || ($cursorEvidence['counterpart_public_id'] ?? null) !== $parentInverse['public_id']
                || ($inverseEvidence['command_fingerprint'] ?? null) !== ($cursorEvidence['command_fingerprint'] ?? null)
                || ($inverseEvidence['review_fingerprint'] ?? null) !== ($cursorEvidence['review_fingerprint'] ?? null)) $this->invalid();
            $cursor = $parent;
        }
    }

    private function sumSigned(array $values): string
    {
        $positive = [];
        $negative = [];
        foreach ($values as $value) {
            $value = $this->signed((string) $value);
            if (str_starts_with($value, '-')) $negative[] = substr($value, 1);
            else $positive[] = $value;
        }
        $positiveTotal = \App\Modules\CustomerDebt\Domain\DebtAmount::sum($positive);
        $negativeTotal = \App\Modules\CustomerDebt\Domain\DebtAmount::sum($negative);
        $compare = \App\Modules\CustomerDebt\Domain\DebtAmount::compare($positiveTotal, $negativeTotal);
        if ($compare >= 0) return \App\Modules\CustomerDebt\Domain\DebtAmount::subtract($positiveTotal, $negativeTotal);
        return '-' . \App\Modules\CustomerDebt\Domain\DebtAmount::subtract($negativeTotal, $positiveTotal);
    }

    private function signed(string $value): string
    {
        $negative = str_starts_with($value, '-');
        $magnitude = CashAmount::parse($negative ? substr($value, 1) : $value);
        return $negative && $magnitude !== '0.00' ? '-' . $magnitude : $magnitude;
    }

    private function inverse(string $delta): string
    {
        return str_starts_with($delta, '-') ? substr($delta, 1) : '-' . $delta;
    }

    private function invalid(): never
    {
        throw new ApiException('EXPECTED_CASH_EVIDENCE_INVALID', 'A Cash financial movement or its correction history has inconsistent evidence. Review the linked money record before continuing.', 409);
    }
}
