<?php

declare(strict_types=1);

namespace App\Modules\CustomerDebt\Domain;

use App\Exceptions\ApiException;

/** Pure allocation; caller supplies freshly locked, canonical-order bill balances. */
final class DebtAllocationPlanner
{
    public function plan(array $bills, string $amount, string $mode, array $selected): array
    {
        $total = DebtAmount::sum(array_column($bills, 'outstanding_amount'));
        if (DebtAmount::compare($amount, $total) > 0) {
            throw new ApiException('DEBT_AMOUNT_EXCEEDS_BALANCE', 'This payment is above the amount this customer still owes. Refresh the bills and enter a lower amount.', 409);
        }
        $byId = array_column($bills, null, 'public_id');
        $remaining = $amount;
        $allocations = [];
        if ($mode === 'EXPLICIT') {
            if (DebtAmount::compare(DebtAmount::sum(array_column($selected, 'amount')), $amount) !== 0) {
                throw new ApiException('VALIDATION_FAILED', 'The amounts assigned to bills must add up to the payment.', 422, ['allocations' => ['Assign the full payment, with nothing left over.']]);
            }
            foreach ($selected as $choice) {
                $bill = $byId[$choice['receivable_public_id']] ?? null;
                if (!$bill || DebtAmount::compare($choice['amount'], $bill['outstanding_amount']) > 0) {
                    throw new ApiException('DEBT_REVIEW_STALE', 'A selected bill is unavailable or its amount has changed. Refresh and review the bills.', 409);
                }
                $allocations[] = $this->allocation($bill, $choice['amount']);
            }
        } else {
            foreach ($bills as $bill) {
                if ($remaining === '0.00') break;
                if ($bill['outstanding_amount'] === '0.00') continue;
                if (count($allocations) === 200) {
                    throw new ApiException('DEBT_ALLOCATION_LIMIT', 'This payment covers too many bills at once. Enter a smaller payment or choose up to 200 bills.', 422);
                }
                $part = DebtAmount::compare($remaining, $bill['outstanding_amount']) < 0 ? $remaining : $bill['outstanding_amount'];
                $allocations[] = $this->allocation($bill, $part);
                $remaining = DebtAmount::subtract($remaining, $part);
            }
        }
        if (DebtAmount::compare(DebtAmount::sum(array_column($allocations, 'amount')), $amount) !== 0) {
            throw new ApiException('DEBT_EVIDENCE_INVALID', 'The payment could not be assigned safely. Refresh the bills.', 409);
        }
        return ['customer_balance_before' => $total, 'customer_balance_after' => DebtAmount::subtract($total, $amount), 'allocations' => $allocations];
    }

    private function allocation(array $bill, string $amount): array
    {
        return ['receivable_id' => $bill['id'], 'receivable_public_id' => $bill['public_id'],
            'amount' => $amount, 'before_amount' => $bill['outstanding_amount'],
            'after_amount' => DebtAmount::subtract($bill['outstanding_amount'], $amount), 'due_date' => $bill['due_date']];
    }
}
