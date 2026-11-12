<?php

declare(strict_types=1);

namespace App\Modules\CustomerDebt\Application;

use App\Exceptions\ApiException;
use App\Modules\Reconciliation\Application\CashAmount;
use App\Modules\Sales\Application\DraftInput;

final class DebtPaymentInput
{
    public static function normalize(array $input, bool $confirmation = false): array
    {
        $allowed = ['public_id', 'customer_public_id', 'channel', 'amount', 'external_reference', 'occurred_at', 'allocation_mode', 'allocations', 'reason'];
        DraftInput::only($input, $confirmation ? [...$allowed, 'review_fingerprint'] : $allowed);
        $channel = $input['channel'] ?? null;
        $mode = $input['allocation_mode'] ?? null;
        if (!in_array($channel, ['CASH', 'TRANSFER'], true) || !in_array($mode, ['OLDEST_FIRST', 'EXPLICIT'], true)) {
            throw new ApiException('VALIDATION_FAILED', 'Choose Cash or bank transfer and how to cover the bills.', 422);
        }
        $amount = self::positive($input['amount'] ?? null);
        $reference = DraftInput::text($input['external_reference'] ?? null, 'external_reference', 120);
        if ($channel === 'CASH' && $reference !== null) {
            throw new ApiException('VALIDATION_FAILED', 'A bank transfer reference is only for a bank transfer.', 422);
        }
        $rows = $input['allocations'] ?? [];
        if (!is_array($rows) || !array_is_list($rows) || count($rows) > 200
            || ($mode === 'EXPLICIT' && $rows === []) || ($mode === 'OLDEST_FIRST' && $rows !== [])) {
            throw new ApiException('VALIDATION_FAILED', 'Choose up to 200 different bills, or let the payment cover the oldest bills.', 422, ['allocations' => ['Check the selected bills and amounts.']]);
        }
        $allocations = [];
        $seen = [];
        foreach ($rows as $row) {
            if ($row instanceof \stdClass) $row = (array) $row;
            if (!is_array($row)) throw new ApiException('VALIDATION_FAILED', 'Check each selected bill.', 422);
            DraftInput::only($row, ['receivable_public_id', 'amount']);
            $id = DraftInput::uuid($row['receivable_public_id'] ?? null, 'receivable_public_id');
            if (isset($seen[$id])) throw new ApiException('VALIDATION_FAILED', 'Choose each bill only once.', 422);
            $seen[$id] = true;
            $allocations[] = ['receivable_public_id' => $id, 'amount' => self::positive($row['amount'] ?? null, 'allocations')];
        }
        if ($confirmation && (!is_string($input['review_fingerprint'] ?? null)
            || !preg_match('/^[a-f0-9]{64}$/D', $input['review_fingerprint']))) {
            throw new ApiException('DEBT_REVIEW_REQUIRED', 'Review this payment before saving it.', 422);
        }
        return [
            'public_id' => DraftInput::uuid($input['public_id'] ?? null),
            'customer_public_id' => DraftInput::uuid($input['customer_public_id'] ?? null, 'customer_public_id'),
            'channel' => $channel, 'amount' => $amount, 'external_reference' => $reference,
            'occurred_at' => DraftInput::occurred($input['occurred_at'] ?? null),
            'allocation_mode' => $mode, 'allocations' => $allocations,
            'reason' => DraftInput::text($input['reason'] ?? null, 'reason', 500),
        ];
    }

    private static function positive(mixed $value, string $field = 'amount'): string
    {
        try {
            $amount = CashAmount::parse($value);
        } catch (ApiException) {
            throw new ApiException('VALIDATION_FAILED', 'Enter an amount with up to two decimal places.', 422, [$field => ['Use a number such as 100.00; do not include commas or a currency sign.']]);
        }
        if ($amount === '0.00') throw new ApiException('VALIDATION_FAILED', 'Enter an amount greater than zero.', 422, [$field => ['Enter an amount such as 100.00.']]);
        return $amount;
    }

    public static function fingerprint(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
