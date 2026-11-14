<?php

declare(strict_types=1);

namespace App\Modules\FinancialMovement\Application;

use App\Exceptions\ApiException;
use App\Modules\FinancialMovement\Domain\MovementAmount;
use App\Modules\Sales\Application\DraftInput;

final class ExpenseInput
{
    public static function normalize(array $input, bool $confirmation = false): array
    {
        $allowed = ['public_id', 'channel', 'amount', 'description', 'external_reference', 'occurred_at'];
        DraftInput::only($input, $confirmation ? [...$allowed, 'review_fingerprint'] : $allowed);
        if (!in_array($input['channel'] ?? null, ['CASH', 'TRANSFER'], true)) {
            throw new ApiException('VALIDATION_FAILED', 'Choose Cash or Transfer.', 422, ['channel' => ['Choose Cash or Transfer for this money record.']]);
        }
        try {
            $description = DraftInput::text($input['description'] ?? null, 'description', 300, true);
        } catch (ApiException) {
            throw new ApiException('VALIDATION_FAILED', 'Explain what the money was for, using up to 300 characters.', 422,
                ['description' => ['Enter a short explanation, such as Transport for stock delivery.']]);
        }
        $reference = DraftInput::text($input['external_reference'] ?? null, 'external_reference', 120);
        $occurred = $input['occurred_at'] ?? null;
        if ($occurred !== null) {
            $occurred = DraftInput::occurred($occurred);
        }
        if ($confirmation && (!is_string($input['review_fingerprint'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $input['review_fingerprint']))) {
            throw new ApiException('FINANCIAL_REVIEW_REQUIRED', 'Review the money record before saving it.', 422);
        }
        return ['public_id' => DraftInput::uuid($input['public_id'] ?? null), 'channel' => $input['channel'],
            'amount' => MovementAmount::fromInput($input['amount'] ?? null)->magnitude,
            'description' => $description, 'external_reference' => $reference, 'occurred_at' => $occurred];
    }

    public static function fingerprint(array $command, string $type = 'SHOP_EXPENSE'): string
    {
        return hash('sha256', json_encode([$type === 'SHOP_EXPENSE' ? 'expense-v1' : $type . '-v1', $command], JSON_THROW_ON_ERROR));
    }
}
