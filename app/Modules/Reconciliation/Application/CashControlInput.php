<?php

declare(strict_types=1);

namespace App\Modules\Reconciliation\Application;

use App\Exceptions\ApiException;
use App\Support\Uuid;
use DateTimeImmutable;
use DateTimeZone;

final class CashControlInput
{
    public static function only(array $input, array $fields): void
    {
        if (array_diff(array_keys($input), $fields)) {
            throw new ApiException('VALIDATION_FAILED', 'Something on this page has changed. Reload the page and try again.', 422);
        }
    }

    public static function uuid(mixed $value): string
    {
        if (!is_string($value) || !Uuid::isV4($value) || strtolower($value) !== $value) {
            throw new ApiException('VALIDATION_FAILED', 'This check number is not valid. Go back and open the check again.', 422);
        }
        return $value;
    }

    public static function text(mixed $value, int $max, string $field = 'reason'): string
    {
        if (!is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > $max) {
            throw new ApiException('VALIDATION_FAILED', 'Enter valid text for this field.', 422,
                [$field => ['Enter text of at most ' . $max . ' characters.']]);
        }
        return trim($value);
    }

    public static function utc(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new ApiException('VALIDATION_FAILED', 'Enter a valid date and time, or leave it empty if you do not know it.', 422);
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s.u', $value, new DateTimeZone('UTC'));
        if (!$date || $date->format('Y-m-d H:i:s.u') !== $value) {
            throw new ApiException('VALIDATION_FAILED', 'Enter a valid date and time, or leave it empty if you do not know it.', 422);
        }
        return $value;
    }

    public static function page(mixed $value, int $max = 999999): int
    {
        if (!is_scalar($value) || !preg_match('/^[1-9][0-9]{0,5}$/D', (string) $value) || (int) $value > $max) {
            throw new ApiException('VALIDATION_FAILED', 'Choose a valid page or page size.', 422);
        }
        return (int) $value;
    }
}
