<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application;

use App\Exceptions\ApiException;
use App\Support\Uuid;

final readonly class AuditCursor
{
    private function __construct(public string $occurredAt, public string $publicId)
    {
    }

    public static function decode(?string $cursor): ?self
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }

        if (strlen($cursor) > 512 || preg_match('/^[A-Za-z0-9_-]+$/', $cursor) !== 1) {
            throw new ApiException('AUDIT_PAGE_INVALID', 'The Audit page cursor is invalid or expired.', 422);
        }

        $encoded = strtr($cursor, '-_', '+/');
        $encoded .= str_repeat('=', (4 - strlen($encoded) % 4) % 4);
        $decoded = base64_decode($encoded, true);
        $data = $decoded === false ? null : json_decode($decoded, true);
        if (! is_array($data) || ! isset($data['occurred_at'], $data['public_id']) || ! Uuid::isV4((string) $data['public_id'])) {
            throw new ApiException('AUDIT_PAGE_INVALID', 'The Audit page cursor is invalid or expired.', 422);
        }

        return new self((string) $data['occurred_at'], (string) $data['public_id']);
    }

    public static function encode(string $occurredAt, string $publicId): string
    {
        $json = json_encode([
            'occurred_at' => $occurredAt,
            'public_id' => $publicId,
        ], JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }
}
