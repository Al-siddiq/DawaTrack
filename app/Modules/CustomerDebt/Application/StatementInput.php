<?php

declare(strict_types=1);

namespace App\Modules\CustomerDebt\Application;

use App\Exceptions\ApiException;
use App\Modules\Sales\Application\DraftInput;
use DateTimeImmutable;
use DateTimeZone;

final class StatementInput
{
    public static function normalize(array $input, string $timezone): array
    {
        DraftInput::only($input, ['from_date', 'to_date', 'per_page', 'cursor']);
        $size = $input['per_page'] ?? 20;
        if ((!is_string($size) && !is_int($size)) || filter_var($size, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 50]]) === false) {
            throw new ApiException('VALIDATION_FAILED', 'Choose between 1 and 50 records per page.', 422);
        }
        $from = self::date($input['from_date'] ?? null, 'from_date', $timezone);
        $to = self::date($input['to_date'] ?? null, 'to_date', $timezone);
        if ($from && $to && $from > $to) {
            throw new ApiException('VALIDATION_FAILED', 'The start date must be on or before the end date.', 422, ['from_date' => ['Choose a start date on or before the end date.']]);
        }
        $cursor = $input['cursor'] ?? null;
        if ($cursor !== null && (!is_string($cursor) || $cursor === '' || strlen($cursor) > 8192)) {
            throw new ApiException('VALIDATION_FAILED', 'Refresh the statement to get a valid page link.', 422);
        }
        $utc = new DateTimeZone('UTC');
        return ['from_date' => $from?->format('Y-m-d'), 'to_date' => $to?->format('Y-m-d'),
            'from_utc' => $from?->setTimezone($utc)->format('Y-m-d H:i:s.u'),
            // Calendar next midnight, not a fixed 86400-second offset; handles DST days.
            'until_utc' => $to?->modify('+1 day')->setTime(0, 0)->setTimezone($utc)->format('Y-m-d H:i:s.u'),
            'per_page' => (int) $size, 'cursor' => $cursor];
    }

    private static function date(mixed $value, string $field, string $timezone): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || !preg_match('/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$/D', $value)) {
            throw new ApiException('VALIDATION_FAILED', 'Choose a valid calendar date.', 422, [$field => ['Choose a date such as 2026-11-12.']]);
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone($timezone));
        if (!$date || $date->format('Y-m-d') !== $value || $value === '9999-12-31') {
            throw new ApiException('VALIDATION_FAILED', 'Choose a valid calendar date before 9999-12-31.', 422, [$field => ['Check the day, month and year.']]);
        }
        return $date;
    }
}
