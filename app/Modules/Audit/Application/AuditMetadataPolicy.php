<?php
declare(strict_types=1);
namespace App\Modules\Audit\Application;
use App\Exceptions\ApiException;
final class AuditMetadataPolicy
{
    private const MAX_DEPTH = 4;
    private const MAX_ENTRIES = 40;
    private const MAX_STRING_LENGTH = 500;
    private const MAX_SERIALIZED_BYTES = 16_384;
    private const FORBIDDEN = '/password|passwd|credential|mfa.*(code|secret)|recovery.*(code|secret|token)|magic.*link|verification.*(code|secret|token)|tls.*(key|secret)|private.*key|cookie|session|auth.*token|access.*token|refresh.*token|bearer|api.*(key|secret)|request.*body|raw.*(bytes|binary)|image.*bytes/i';

    public function sanitize(mixed $value): mixed
    {
        $safe = $this->sanitizeNode($value, 0);
        $encoded = json_encode($safe, JSON_THROW_ON_ERROR);
        if (strlen($encoded) > self::MAX_SERIALIZED_BYTES) {
            throw new ApiException('AUDIT_METADATA_REJECTED', 'Audit metadata exceeds the total safe size.', 422);
        }

        return $safe;
    }

    private function sanitizeNode(mixed $value, int $depth): mixed
    {
        if ($depth > self::MAX_DEPTH) {
            throw new ApiException('AUDIT_METADATA_REJECTED', 'Audit metadata nesting exceeds the safe limit.', 422);
        }
        if (is_string($value)) {
            if (mb_strlen($value) > self::MAX_STRING_LENGTH) {
                throw new ApiException('AUDIT_METADATA_REJECTED', 'Audit metadata string exceeds the safe limit.', 422);
            }

            return $value;
        }
        if (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
            return $value;
        }
        if (! is_array($value)) {
            throw new ApiException('AUDIT_METADATA_REJECTED', 'Audit metadata must contain only scalar values and arrays.', 422);
        }
        if (count($value) > self::MAX_ENTRIES) {
            throw new ApiException('AUDIT_METADATA_REJECTED', 'Audit metadata has too many entries.', 422);
        }

        $safe = [];
        foreach ($value as $key => $item) {
            $name = (string) $key;
            if (preg_match(self::FORBIDDEN, $name) === 1) {
                throw new ApiException('AUDIT_METADATA_REJECTED', 'Audit metadata contains a forbidden sensitive key.', 422);
            }
            $safe[$key] = $this->sanitizeNode($item, $depth + 1);
        }

        return $safe;
    }
}
