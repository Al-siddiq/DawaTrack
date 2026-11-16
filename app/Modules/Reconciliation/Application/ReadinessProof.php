<?php

declare(strict_types=1);

namespace App\Modules\Reconciliation\Application;

use App\Exceptions\ApiException;
use Throwable;

/** Five-minute opaque online receipt bound to user/Device/control/epoch/target, never a sync cursor. */
final class ReadinessProof
{
    private const PURPOSE = 'DawaTrack:CashReadiness:v1';
    private readonly string $key;

    public function __construct(string $secret)
    {
        if (strlen($secret) < 32 || !function_exists('openssl_encrypt') || !function_exists('openssl_decrypt')) {
            throw new ApiException('READINESS_SETUP_REQUIRED', 'Online readiness is not configured. Ask the administrator to check the existing private encryption key.', 503);
        }
        $this->key = hash_hmac('sha256', self::PURPOSE, $secret, true);
    }

    public function encode(array $payload): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt(json_encode([...$payload, 'expires' => time() + 300], JSON_THROW_ON_ERROR),
            'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag, self::PURPOSE, 16);
        if ($cipher === false) {
            throw new \RuntimeException('Readiness proof could not be issued.');
        }
        return 'cr1.' . rtrim(strtr(base64_encode($iv . $tag . $cipher), '+/', '-_'), '=');
    }

    public function decode(string $proof): array
    {
        try {
            if (strlen($proof) > 4096 || !preg_match('/^cr1\.([A-Za-z0-9_-]+)$/D', $proof, $parts)) {
                throw new \UnexpectedValueException();
            }
            $raw = base64_decode(strtr($parts[1], '-_', '+/'), true);
            if ($raw === false || strlen($raw) < 29) {
                throw new \UnexpectedValueException();
            }
            $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA,
                substr($raw, 0, 12), substr($raw, 12, 16), self::PURPOSE);
            if ($plain === false) {
                throw new \UnexpectedValueException();
            }
            $data = json_decode($plain, true, 16, JSON_THROW_ON_ERROR);
            if (!is_array($data) || !is_int($data['expires'] ?? null) || $data['expires'] < time() || $data['expires'] > time() + 300) {
                throw new \UnexpectedValueException();
            }
            return $data;
        } catch (Throwable) {
            throw new ApiException('READINESS_REFRESH_REQUIRED', 'Reload this check on the same phone or computer, then tap the ready button again.', 409);
        }
    }
}
