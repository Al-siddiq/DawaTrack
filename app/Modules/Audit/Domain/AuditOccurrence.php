<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain;

use DateTimeImmutable;
use DateTimeZone;
use App\Exceptions\ApiException;

final readonly class AuditOccurrence
{
    private const MAX_PAST_SECONDS = 31_622_400;
    private const MAX_FUTURE_SECONDS = 300;

    private function __construct(public ?DateTimeImmutable $occurredAt, public ?string $operationId)
    {
    }

    public static function serverNow(): self
    {
        return new self(null, null);
    }

    public static function supplied(string $occurredAt, ?string $operationId = null): self
    {
        $utc = new DateTimeZone('UTC');
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s.u', $occurredAt, $utc);
        $errors = DateTimeImmutable::getLastErrors();
        if ($parsed === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new ApiException('AUDIT_CONTEXT_INVALID', 'Audit occurrence time must be a UTC DATETIME(6) value.', 422);
        }

        $now = new DateTimeImmutable('now', $utc);
        $difference = $parsed->getTimestamp() - $now->getTimestamp();
        if ($difference > self::MAX_FUTURE_SECONDS || $difference < -self::MAX_PAST_SECONDS) {
            throw new ApiException('AUDIT_CONTEXT_INVALID', 'Audit occurrence time is outside the accepted bound.', 422);
        }

        if ($operationId !== null && ! \App\Support\Uuid::isV4(strtolower($operationId))) {
            throw new ApiException('AUDIT_CONTEXT_INVALID', 'Audit operation identifier must be a UUIDv4.', 422);
        }

        return new self($parsed, $operationId === null ? null : strtolower($operationId));
    }

    public function formatOr(string $serverNow): string
    {
        return $this->occurredAt?->format('Y-m-d H:i:s.u') ?? $serverNow;
    }
}
