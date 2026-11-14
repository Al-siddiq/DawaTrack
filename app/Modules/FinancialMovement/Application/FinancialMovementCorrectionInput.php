<?php

declare(strict_types=1);

namespace App\Modules\FinancialMovement\Application;

use App\Exceptions\ApiException;
use App\Modules\FinancialMovement\Domain\MovementAmount;
use App\Modules\Sales\Application\DraftInput;
use App\Support\SensitiveReason;

final class FinancialMovementCorrectionInput
{
    public static function normalize(array $input, string $kind, string $source, bool $confirmation = false): array
    {
        if (!in_array($kind, ['reverse', 'correct'], true)) {
            throw new \LogicException('Unknown financial movement correction.');
        }
        $allowed = $kind === 'reverse'
            ? ['public_id', 'reason']
            : ['public_id', 'replacement_public_id', 'direction', 'amount', 'channel', 'description', 'external_reference', 'occurred_at', 'reason'];
        DraftInput::only($input, $confirmation ? [...$allowed, 'review_fingerprint'] : $allowed);

        $reason = SensitiveReason::require(DraftInput::text($input['reason'] ?? null, 'reason', 500, true));
        $publicId = DraftInput::uuid($input['public_id'] ?? null);
        if ($publicId === DraftInput::uuid($source, 'source_public_id')) {
            throw new ApiException('VALIDATION_FAILED', 'Use a new reference for the undo record.', 422);
        }
        $command = [
            'kind' => $kind,
            'source_public_id' => DraftInput::uuid($source, 'source_public_id'),
            'public_id' => $publicId,
            'reason' => $reason,
        ];

        if ($kind === 'correct') {
            $direction = $input['direction'] ?? null;
            if (!in_array($direction, ['MONEY_IN', 'MONEY_OUT'], true)) {
                throw new ApiException('VALIDATION_FAILED', 'Choose whether the corrected money came into or went out of the shop.', 422,
                    ['direction' => ['Choose Money in or Money out.']]);
            }
            $channel = $input['channel'] ?? null;
            if (!in_array($channel, ['CASH', 'TRANSFER'], true)) {
                throw new ApiException('VALIDATION_FAILED', 'Choose Cash or Transfer.', 422,
                    ['channel' => ['Choose Cash or Transfer for the corrected record.']]);
            }
            $description = DraftInput::text($input['description'] ?? null, 'description', 300, true);
            $reference = DraftInput::text($input['external_reference'] ?? null, 'external_reference', 120);
            $occurred = $input['occurred_at'] ?? null;
            if ($occurred !== null) {
                $occurred = DraftInput::occurred($occurred);
            }
            $amount = MovementAmount::fromInput($input['amount'] ?? null)->magnitude;
            $command += [
                'replacement_public_id' => DraftInput::uuid($input['replacement_public_id'] ?? null, 'replacement_public_id'),
                'direction' => $direction,
                'amount' => $amount,
                'amount_delta' => $direction === 'MONEY_IN' ? $amount : '-' . $amount,
                'channel' => $channel,
                'description' => $description,
                'external_reference' => $reference,
                'occurred_at' => $occurred,
            ];
            if ($command['public_id'] === $command['source_public_id']
            || $command['replacement_public_id'] === $command['source_public_id']
            || $command['replacement_public_id'] === $command['public_id']) {
                throw new ApiException('VALIDATION_FAILED', 'Use two different references for the undo and corrected record.', 422);
            }
        }
        if ($confirmation && (!is_string($input['review_fingerprint'] ?? null)
            || !preg_match('/^[a-f0-9]{64}$/D', $input['review_fingerprint']))) {
            throw new ApiException('FINANCIAL_REVIEW_REQUIRED', 'Review this change before saving it.', 422);
        }
        return $command;
    }

    public static function commandFingerprint(array $command): string
    {
        return hash('sha256', json_encode(['financial-movement-' . $command['kind'] . '-v1', $command], JSON_THROW_ON_ERROR));
    }

    public static function reviewFingerprint(array $command, array $sourceState): string
    {
        return hash('sha256', json_encode([self::commandFingerprint($command), $sourceState], JSON_THROW_ON_ERROR));
    }
}
