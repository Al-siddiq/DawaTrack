<?php

declare(strict_types=1);

namespace App\Security;

use App\Modules\Audit\Application\AuditService;
use App\Modules\Audit\Domain\AuditActor;
use App\Modules\Audit\Domain\AuditAppendCommand;
use App\Modules\Audit\Domain\AuditCategory;
use CodeIgniter\Session\Session;

final class PlatformMfaService
{
    private const VERIFIED_AT_KEY = 'platform_mfa_verified_at';
    private const USER_ID_KEY = 'platform_mfa_user_id';
    private const LIFETIME_SECONDS = 900;

    public function __construct(
        private readonly Session $session,
        private readonly AuditService $audit,
    ) {
    }

    public function isRecent(int $userId): bool
    {
        $verifiedAt = (int) $this->session->get(self::VERIFIED_AT_KEY);
        $verifiedUserId = (int) $this->session->get(self::USER_ID_KEY);

        return $verifiedUserId === $userId
            && $verifiedAt > 0
            && $verifiedAt >= time() - self::LIFETIME_SECONDS;
    }

    /**
     * Records a native Shield Email2FA-completed login as the recent Admin factor.
     * This is called only after Shield has completed its configured login action.
     */
    public function establish(int $userId): void
    {
        $this->session->set([
            self::VERIFIED_AT_KEY => time(),
            self::USER_ID_KEY => $userId,
        ]);

        $this->auditSafely('admin.mfa_verified', $userId, 'Platform Administrator completed Shield email second-factor verification.');
    }

    public function clear(int $userId, string $reason): void
    {
        $this->session->remove([self::VERIFIED_AT_KEY, self::USER_ID_KEY]);
        $this->auditSafely('admin.mfa_cleared', $userId, $reason);
    }

    private function auditSafely(string $actionCode, int $userId, string $reason): void
    {
        if (! db_connect()->tableExists('audit_events')) {
            log_message('warning', 'security.audit_unavailable {request_id} {action_code} {reason}', [
                'request_id' => service('requestContext')->requestId(),
                'action_code' => $actionCode,
                'reason' => 'audit_schema_not_installed',
            ]);

            return;
        }

        $this->audit->append(new AuditAppendCommand(
            actor: AuditActor::platformUser($userId),
            actionCode: $actionCode,
            category: AuditCategory::SECURITY,
            resourceType: 'platform_admin_authentication',
            reason: $reason,
        ));
    }
}
