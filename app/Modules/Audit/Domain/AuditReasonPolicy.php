<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain;

use App\Exceptions\ApiException;

final class AuditReasonPolicy
{
    private const OPTIONAL = [
        'branding.asset_replaced',
        'branding.updated',
        'shop.profile_updated',
        'product.created',
        'product.updated',
        'stock.threshold_updated',
        'stock_intake.posted',
    ];

    public function validate(string $actionCode, ?string $reason): ?string
    {
        $reason = $reason === null ? null : trim($reason);

        if ($reason !== null && mb_strlen($reason) > 500) {
            throw new ApiException('VALIDATION_FAILED', 'Audit reason exceeds 500 characters.', 422, ['reason' => ['Use 500 characters or fewer.']]);
        }

        if (! in_array($actionCode, self::OPTIONAL, true) && ($reason === null || $reason === '')) {
            throw new ApiException('VALIDATION_FAILED', 'This action requires a reason.', 422, ['reason' => ['Enter a reason before continuing.']]);
        }

        return $reason === '' ? null : $reason;
    }
}
