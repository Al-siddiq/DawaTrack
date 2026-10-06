<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application;

use App\Exceptions\ApiException;
use App\Support\Uuid;
use DateTimeImmutable;

final readonly class AuditQuery
{
    public function __construct(
        public int $pageSize,
        public ?string $from,
        public ?string $to,
        public ?string $category,
        public ?string $action,
        public ?string $actor,
        public ?string $resourceType,
        public ?string $resourcePublicId,
        public ?string $correlationId,
        public ?AuditCursor $cursor,
    ) {
    }

    /** @param array<string,mixed> $input */
    public static function from(array $input): self
    {
        $size = (int) ($input['page_size'] ?? 20);
        if ($size < 1 || $size > 50) {
            throw new ApiException('AUDIT_PAGE_INVALID', 'Choose an Audit page size from 1 to 50.', 422);
        }

        $from = self::date($input['from'] ?? null, false);
        $to = self::date($input['to'] ?? null, true);
        if ($from !== null && $to !== null && $from > $to) {
            throw new ApiException('AUDIT_FILTER_INVALID', 'The Audit date range is invalid.', 422);
        }

        if ($from !== null && $to !== null && strtotime($to) - strtotime($from) > 366 * 86400) {
            throw new ApiException('AUDIT_FILTER_INVALID', 'Audit date ranges are limited to 366 days.', 422);
        }

        $category = self::bounded($input['category'] ?? null, 24, '/^[A-Z_]+$/');
        $action = self::bounded($input['action'] ?? null, 80, '/^[a-z0-9_.]+$/');
        $actor = self::bounded($input['actor'] ?? null, 100, '/^[\pL\pN@._ +\-]+$/u');
        $resourceType = self::bounded($input['resource_type'] ?? null, 48, '/^[a-z0-9_]+$/');
        $resourceId = self::uuid($input['resource_public_id'] ?? null);
        $correlation = self::uuid($input['correlation_id'] ?? null);
        $cursor = AuditCursor::decode(isset($input['cursor']) ? (string) $input['cursor'] : null);

        return new self(
            $size,
            $from,
            $to,
            $category,
            $action,
            $actor,
            $resourceType,
            $resourceId,
            $correlation,
            $cursor,
        );
    }

    private static function date(mixed $value, bool $end): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $input = trim((string) $value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $input) !== 1) {
            throw new ApiException('AUDIT_FILTER_INVALID', 'Use valid Audit dates.', 422);
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $input);
        if ($date === false || $date->format('Y-m-d') !== $input) {
            throw new ApiException('AUDIT_FILTER_INVALID', 'Use valid Audit dates.', 422);
        }

        return $date->format($end ? 'Y-m-d 23:59:59.999999' : 'Y-m-d 00:00:00.000000');
    }

    private static function bounded(mixed $value, int $max, string $pattern): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > $max || preg_match($pattern, $value) !== 1) {
            throw new ApiException('AUDIT_FILTER_INVALID', 'An Audit filter is invalid.', 422);
        }

        return $value;
    }

    private static function uuid(mixed $value): ?string
    {
        $value = strtolower(trim((string) ($value ?? '')));
        if ($value === '') {
            return null;
        }

        if (! Uuid::isV4($value)) {
            throw new ApiException('AUDIT_FILTER_INVALID', 'An Audit identifier filter is invalid.', 422);
        }

        return $value;
    }
}
