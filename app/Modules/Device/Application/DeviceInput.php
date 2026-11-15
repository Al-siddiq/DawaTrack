<?php

declare(strict_types=1);

namespace App\Modules\Device\Application;

use App\Exceptions\ApiException;
use App\Support\Uuid;

final class DeviceInput
{
    public static function only(array $input, array $fields): void
    {
        if (array_diff(array_keys($input), $fields)) {
            throw new ApiException('VALIDATION_FAILED', 'This Device action contains unsupported fields.', 422);
        }
    }

    public static function uuid(mixed $value): string
    {
        if (!is_string($value) || !Uuid::isV4($value) || strtolower($value) !== $value) {
            throw new ApiException('VALIDATION_FAILED', 'Use a permanent lowercase UUIDv4 for this installation.', 422);
        }
        return $value;
    }

    public static function name(mixed $value): string
    {
        if (!is_string($value) || mb_strlen(trim($value)) < 1 || mb_strlen(trim($value)) > 100
            || preg_match('/[\x00-\x1f\x7f]/u', $value)) {
            throw new ApiException('VALIDATION_FAILED', 'Give this Device a recognizable name of up to 100 characters.', 422,
                ['display_name' => ['Enter a recognizable Device name.']]);
        }
        return trim($value);
    }
}
