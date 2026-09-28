<?php

declare(strict_types=1);

namespace App\Security;

use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\Session\Session;

final class UserSessionRevocationService
{
    private const AUTHENTICATED_AT_KEY = 'security_authenticated_at';
    private const CACHE_PREFIX = 'dawatrack_user_revoked_';

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly Session $session,
    ) {
    }

    public function revokeAll(int $userId): void
    {
        $this->cache->save(self::CACHE_PREFIX . $userId, microtime(true), 2_592_000);
    }

    public function currentSessionIsRevoked(int $userId): bool
    {
        $authenticatedAt = (float) $this->session->get(self::AUTHENTICATED_AT_KEY);
        $revokedAt = (float) ($this->cache->get(self::CACHE_PREFIX . $userId) ?: 0.0);
        if ($authenticatedAt === 0.0) {
            return $revokedAt > 0.0;
        }

        return $revokedAt > 0.0 && $authenticatedAt <= $revokedAt;
    }

    /** Called after a successful authentication response to bind this session to its login time. */
    public function markCurrentSession(): void
    {
        if ((float) $this->session->get(self::AUTHENTICATED_AT_KEY) === 0.0) {
            $this->session->set(self::AUTHENTICATED_AT_KEY, microtime(true));
        }
    }

    public function clearSessionMarker(): void
    {
        $this->session->remove(self::AUTHENTICATED_AT_KEY);
    }
}
