<?php

declare(strict_types=1);
namespace App\Modules\CustomerDebt\Application;

use App\Exceptions\ApiException;
use App\Modules\Reconciliation\Application\CashAmount;
use App\Modules\Sales\Application\DraftInput;
use App\Support\SensitiveReason;

final class DebtCorrectionInput
{
    public static function normalize(array $input, string $kind, string $source, bool $confirm): array
    {
        $allowed = ['public_id', 'reason', 'occurred_at'];
        if ($kind === 'correct') $allowed = ['reversal_public_id', 'replacement_public_id', 'reason', 'occurred_at', 'replacement'];
        if ($kind === 'adjust') $allowed = [...$allowed, 'adjustment_type', 'amount_delta'];
        DraftInput::only($input, $confirm ? [...$allowed, 'review_fingerprint'] : $allowed);
        if ($confirm && (!is_string($input['review_fingerprint'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $input['review_fingerprint']))) {
            throw new ApiException('DEBT_REVIEW_REQUIRED', 'Review this change before saving it.', 422);
        }
        $command = ['kind' => $kind, 'source_public_id' => DraftInput::uuid($source),
            'public_id' => DraftInput::uuid($input[$kind === 'correct' ? 'reversal_public_id' : 'public_id'] ?? null),
            'reason' => SensitiveReason::require(DraftInput::text($input['reason'] ?? null, 'reason', 500) ?? ''),
            'occurred_at' => DraftInput::occurred($input['occurred_at'] ?? null)];
        if ($command['public_id'] === $command['source_public_id']) throw new ApiException('DEBT_IDENTITY_CONFLICT', 'Use a new reference for this change.', 409);
        if ($kind === 'correct') {
            $replacement = $input['replacement'] ?? null;
            if ($replacement instanceof \stdClass) $replacement = (array) $replacement;
            if (!is_array($replacement)) throw new ApiException('VALIDATION_FAILED', 'Enter the replacement payment details.', 422);
            // Client cannot inject a different UUID or reason into the replacement.
            DraftInput::only($replacement, ['customer_public_id', 'channel', 'amount', 'external_reference', 'occurred_at', 'allocation_mode', 'allocations']);
            $command['replacement'] = DebtPaymentInput::normalize($replacement + [
                'public_id' => DraftInput::uuid($input['replacement_public_id'] ?? null), 'reason' => $command['reason']]);
            if (in_array($command['replacement']['public_id'], [$command['public_id'], $command['source_public_id']], true)) {
                throw new ApiException('DEBT_IDENTITY_CONFLICT', 'The undo and replacement need different new references.', 409);
            }
        }
        if ($kind === 'adjust') {
            $type = $input['adjustment_type'] ?? null;
            $delta = $input['amount_delta'] ?? null;
            if (!in_array($type, ['CORRECTION_INCREASE', 'CORRECTION_DECREASE'], true) || !is_string($delta)) {
                throw new ApiException('VALIDATION_FAILED', 'Choose a factual increase or decrease and enter its amount.', 422);
            }
            $negative = str_starts_with($delta, '-');
            $amount = CashAmount::parse($negative ? substr($delta, 1) : $delta);
            if ($amount === '0.00' || $negative !== ($type === 'CORRECTION_DECREASE')) {
                throw new ApiException('VALIDATION_FAILED', 'Enter a non-zero amount with the correct increase or decrease sign.', 422);
            }
            $command += ['adjustment_type' => $type, 'amount_delta' => ($negative ? '-' : '') . $amount];
        }
        return $command;
    }
}
