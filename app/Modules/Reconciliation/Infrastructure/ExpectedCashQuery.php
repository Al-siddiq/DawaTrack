<?php

declare(strict_types=1);

namespace App\Modules\Reconciliation\Infrastructure;

use App\Exceptions\ApiException;
use App\Modules\CustomerDebt\Domain\DebtAmount;
use App\Modules\CustomerDebt\Infrastructure\DebtCashComponentQuery;
use App\Modules\CustomerDebt\Infrastructure\DebtReadSnapshot;
use App\Modules\FinancialMovement\Infrastructure\FinancialMovementCashComponentQuery;
use App\Modules\Reconciliation\Application\CashAmount;
use App\Modules\Sales\Infrastructure\SaleCashComponentQuery;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Internal, read-only expected physical Cash composition for a Reconciliation caller.
 * The caller supplies a server-selected baseline and authoritative UTC period boundaries.
 */
final class ExpectedCashQuery
{
    public function __construct(private readonly BaseConnection $database) {}

    public function calculate(ShopContext $context, string $periodStartedAt, string $cutoffAt, string $openingBaselineAmount): array
    {
        $start = $this->utc($periodStartedAt, 'period_started_at');
        $cutoff = $this->utc($cutoffAt, 'cutoff_at');
        if ($start >= $cutoff) {
            throw new ApiException('VALIDATION_FAILED', 'The Cash period start must be earlier than its cutoff.', 422,
                ['period_started_at' => ['Choose an earlier UTC period start.']]);
        }
        $baseline = CashAmount::parse($openingBaselineAmount);

        return (new DebtReadSnapshot($this->database))->run(function () use ($context, $start, $cutoff, $baseline): array {
            $sales = (new SaleCashComponentQuery($this->database))->forPeriod($context, $start, $cutoff);
            $debt = (new DebtCashComponentQuery($this->database))->forPeriod($context, $start, $cutoff);
            $movements = (new FinancialMovementCashComponentQuery($this->database))->forPeriod($context, $start, $cutoff);

            // The non-negative reconciliation snapshot fields hold source magnitudes.
            // Signed debt-payment reversals and all financial movement inverses/replacements
            // are kept in the signed correction component so a reversal in a later period
            // can offset the prior-period source without rewriting the frozen baseline.
            $correctionsNet = $this->sumSigned([
                $debt['cash_debt_reversal_net'],
                $movements['cash_financial_movement_corrections_net'],
            ]);

            $expected = $this->sumSigned([
                $baseline,
                $sales['cash_sale_receipts'],
                '-' . $sales['cash_sale_refunds'],
                $debt['cash_debt_receipts'],
                $movements['cash_introductions'],
                '-' . $movements['cash_expenses'],
                '-' . $movements['cash_withdrawals'],
                $correctionsNet,
            ]);

            return [
                'period' => ['period_started_at' => $start, 'cutoff_at' => $cutoff, 'boundary' => '(period_started_at, cutoff_at]'],
                'opening_baseline_amount' => $baseline,
                'cash_sale_receipts' => $sales['cash_sale_receipts'],
                'cash_sale_refunds' => $sales['cash_sale_refunds'],
                'cash_debt_receipts' => $debt['cash_debt_receipts'],
                'cash_introductions' => $movements['cash_introductions'],
                'cash_expenses' => $movements['cash_expenses'],
                'cash_withdrawals' => $movements['cash_withdrawals'],
                'cash_corrections_net' => $this->signedField($correctionsNet),
                'expected_amount' => $this->signedField($expected),
                'source_counts' => [
                    'sale_payments' => $sales['source_count'],
                    'sale_receipts' => $sales['receipt_count'],
                    'sale_refunds' => $sales['refund_count'],
                    'debt_payments' => $debt['source_count'],
                    'debt_receipts' => $debt['receipt_count'],
                    'debt_payment_reversals' => $debt['reversal_count'],
                    'financial_movements' => $movements['source_count'],
                    'financial_movement_types' => $movements['type_counts'],
                ],
                'attribution' => [
                    'debt_payment_reversal_net' => $debt['cash_debt_reversal_net'],
                    'financial_movement_correction_replacements_net' => $movements['cash_financial_correction_replacements_net'],
                    'financial_movement_reversals_net' => $movements['cash_financial_movement_reversals_net'],
                    'financial_movement_reversals_by_original_type' => $movements['reversal_attribution'],
                ],
            ];
        });
    }

    private function utc(string $value, string $field): string
    {
        if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}\.[0-9]{6}$/D', $value)) {
            throw new ApiException('VALIDATION_FAILED', 'Supply an exact UTC timestamp for the Cash period.', 422,
                [$field => ['Use YYYY-MM-DD HH:MM:SS.ffffff UTC.']]);
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s.u', $value, new DateTimeZone('UTC'));
        if (!$date || $date->format('Y-m-d H:i:s.u') !== $value) {
            throw new ApiException('VALIDATION_FAILED', 'Supply a valid UTC timestamp for the Cash period.', 422,
                [$field => ['Check the UTC date and time.']]);
        }
        return $value;
    }

    private function sumSigned(array $values): string
    {
        $positive = [];
        $negative = [];
        foreach ($values as $value) {
            $value = $this->signedWide((string) $value);
            if (str_starts_with($value, '-')) $negative[] = substr($value, 1);
            else $positive[] = $value;
        }
        $positiveTotal = DebtAmount::sum($positive);
        $negativeTotal = DebtAmount::sum($negative);
        $compare = DebtAmount::compare($positiveTotal, $negativeTotal);
        if ($compare >= 0) return DebtAmount::subtract($positiveTotal, $negativeTotal);
        return '-' . DebtAmount::subtract($negativeTotal, $positiveTotal);
    }

    private function signedWide(string $value): string
    {
        $negative = str_starts_with($value, '-');
        $raw = $negative ? substr($value, 1) : $value;
        if (!preg_match('/^(?:0|[1-9][0-9]{0,35})(?:\\.[0-9]{1,2})?$/D', $raw)) {
            throw new ApiException('EXPECTED_CASH_RANGE_EXCEEDED', 'The Cash component total exceeds the supported exact decimal range.', 409);
        }
        $magnitude = DebtAmount::sum([$raw]);
        return $negative && $magnitude !== '0.00' ? '-' . $magnitude : $magnitude;
    }

    private function signedField(string $value): string
    {
        $negative = str_starts_with($value, '-');
        $raw = $negative ? substr($value, 1) : $value;
        if (!preg_match('/^(?:0|[1-9][0-9]{0,16})(?:\\.[0-9]{1,2})?$/D', $raw)) {
            throw new ApiException('EXPECTED_CASH_RANGE_EXCEEDED', 'The expected Cash total does not fit the existing reconciliation DECIMAL(19,2) field.', 409);
        }
        $magnitude = CashAmount::parse($raw);
        return $negative && $magnitude !== '0.00' ? '-' . $magnitude : $magnitude;
    }
}
