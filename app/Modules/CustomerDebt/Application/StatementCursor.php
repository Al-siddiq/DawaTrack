<?php

declare(strict_types=1);

namespace App\Modules\CustomerDebt\Application;

use App\Exceptions\ApiException;
use Throwable;

/** Authenticated opaque page anchor. No browser state, DB writes or public internal IDs. */
final class StatementCursor
{
    private const AAD = 'DawaTrack:CustomerStatement:v1';
    private readonly string $key;

    public function __construct(string $secret)
    {
        // Reuse the deployment encryption seed with purpose separation. Never invent a default.
        if (strlen($secret) < 32 || !function_exists('openssl_encrypt') || !function_exists('openssl_decrypt')) {
            throw new ApiException('STATEMENT_SETUP_REQUIRED', 'Statement pages are not set up yet. Ask the shop owner to contact the system administrator.', 503);
        }
        $this->key = hash_hmac('sha256', self::AAD, $secret, true);
    }

    public function encode(array $payload): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt(json_encode($payload, JSON_THROW_ON_ERROR), 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag, self::AAD, 16);
        if ($cipher === false) {
            throw new \RuntimeException('Statement cursor encryption failed.');
        }
        return 'v1.' . rtrim(strtr(base64_encode($iv . $tag . $cipher), '+/', '-_'), '=');
    }

    public function decode(string $cursor): array
    {
        try {
            if (strlen($cursor) > 8192 || !preg_match('/^v1\.([A-Za-z0-9_-]+)$/D', $cursor, $match)) {
                throw new \UnexpectedValueException();
            }
            $bytes = base64_decode(strtr($match[1], '-_', '+/'), true);
            if ($bytes === false || strlen($bytes) < 29) {
                throw new \UnexpectedValueException();
            }
            $plain = openssl_decrypt(substr($bytes, 28), 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, substr($bytes, 0, 12), substr($bytes, 12, 16), self::AAD);
            if ($plain === false) {
                throw new \UnexpectedValueException();
            }
            $payload = json_decode($plain, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($payload) || ($payload['v'] ?? null) !== 1 || !is_int($payload['expires'] ?? null)) {
                throw new \UnexpectedValueException();
            }
            if ($payload['expires'] < time()) {
                throw new ApiException('STATEMENT_REFRESH_REQUIRED', 'This statement page has expired. Refresh the statement from the beginning.', 409);
            }
            return $payload;
        } catch (ApiException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new ApiException('VALIDATION_FAILED', 'This statement page link is not valid. Refresh the statement from the beginning.', 422);
        }
    }
}
