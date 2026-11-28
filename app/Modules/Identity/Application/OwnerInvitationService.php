<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Exceptions\ApiException;
use App\Modules\Audit\Application\AuditService;
use App\Modules\Audit\Domain\AuditActor;
use App\Modules\Audit\Domain\AuditAppendCommand;
use App\Modules\Audit\Domain\AuditCategory;
use App\Support\SensitiveReason;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Models\UserIdentityModel;
use Throwable;

final class OwnerInvitationService
{
    private const MEMBERSHIP_LOCK = 'dawatrack:identity-membership';

    public function __construct(
        private readonly BaseConnection $db,
        private readonly AuditService $audit,
    ) {
    }

    /** @return array{delivery_status: string, expires_at: string} */
    public function issueForMembership(
        string $membershipPublicId,
        int $adminUserId,
        string $reason,
        bool $recovery = false,
    ): array {
        $reason = SensitiveReason::require($reason);
        $this->lock();

        try {
            $this->db->transException(true)->transBegin();
            try {
                $membership = $this->db->query(<<<'SQL'
SELECT m.id AS membership_id,
       m.public_id AS membership_public_id,
       m.status AS membership_status,
       m.user_id,
       m.tenant_id,
       t.public_id AS tenant_public_id,
       t.status AS tenant_status,
       s.id AS shop_id,
       i.secret AS owner_email,
       u.active AS user_active
FROM tenant_memberships m
INNER JOIN tenants t ON t.id = m.tenant_id
INNER JOIN shops s ON s.tenant_id = t.id
INNER JOIN users u ON u.id = m.user_id
INNER JOIN auth_identities i ON i.user_id = u.id AND i.type = 'email_password'
WHERE m.public_id = ?
  AND m.role = 'PRIMARY_OWNER'
LIMIT 1
FOR UPDATE
SQL, [$membershipPublicId])->getRowArray();

                if ($membership === null || (int) $membership['user_active'] !== 1) {
                    throw new ApiException('RESOURCE_NOT_FOUND', 'Primary Owner not found.', 404);
                }
                $expectedMembership = $recovery ? 'ACTIVE' : 'INVITED';
                $expectedTenant = $recovery ? 'ACTIVE' : 'ONBOARDING';
                if ($membership['membership_status'] !== $expectedMembership || $membership['tenant_status'] !== $expectedTenant) {
                    throw new ApiException('OWNER_STATE_CHANGED', 'The Primary Owner state changed. Refresh the Tenant details and try again.', 409);
                }

                $user = auth()->getProvider()->findById((int) $membership['user_id']);
                if ($user === null || $user->inGroup('platform_admin')) {
                    throw new ApiException('RESOURCE_NOT_FOUND', 'Primary Owner not found.', 404);
                }

                $token = random_string('crypto', 40);
                $expires = Time::now()->addSeconds(setting('Auth.magicLinkLifetime'));
                $identityModel = model(UserIdentityModel::class);
                $identityModel->deleteIdentitiesByType($user, Session::ID_TYPE_MAGIC_LINK);
                $identityModel->insert([
                    'user_id' => (int) $membership['user_id'],
                    'type' => Session::ID_TYPE_MAGIC_LINK,
                    'secret' => $token,
                    'expires' => $expires,
                ]);

                $this->audit->append(new AuditAppendCommand(
                    actor: AuditActor::platformUser($adminUserId, (int) $membership['tenant_id'], (int) $membership['shop_id']),
                    actionCode: $recovery ? 'owner.recovery_requested' : 'owner.invitation_issued',
                    category: AuditCategory::SECURITY,
                    resourceType: 'tenant_membership',
                    resourcePublicId: $membership['membership_public_id'],
                    reason: $reason,
                    metadata: [
                        'membership_status' => $membership['membership_status'],
                        'delivery_channel' => 'EMAIL',
                    ],
                ));

                if (! $this->db->transCommit()) {
                    throw new ApiException('OWNER_INVITATION_FAILED', 'The Owner access link could not be prepared.', 500);
                }
            } catch (Throwable $exception) {
                $this->db->transRollback();
                throw $exception;
            }
        } finally {
            $this->releaseLock();
        }

        $delivered = $this->sendMagicLink($membership['owner_email'], $user, $token);
        return [
            'delivery_status' => $delivered ? 'SENT' : 'FAILED',
            'expires_at' => $expires->toDateTimeString(),
        ];
    }

    private function sendMagicLink(string $recipient, object $user, string $token): bool
    {
        try {
            helper('email');
            $request = service('request');
            $message = view(setting('Auth.views')['magic-link-email'], [
                'token' => $token,
                'user' => $user,
                'ipAddress' => $request->getIPAddress(),
                'userAgent' => (string) $request->getUserAgent(),
                'date' => Time::now()->toDateTimeString(),
            ], ['debug' => false]);

            $email = emailer(['mailType' => 'html'])
                ->setFrom(setting('Email.fromEmail'), setting('Email.fromName') ?? '')
                ->setTo($recipient)
                ->setSubject(lang('Auth.magicLinkSubject'))
                ->setMessage($message);

            $sent = $email->send(false);
            $email->clear();
            if ($sent === false) {
                log_message('error', 'owner_access_email_failed {request_id}', [
                    'request_id' => service('requestContext')->requestId(),
                ]);
                return false;
            }
            return true;
        } catch (Throwable $exception) {
            log_message('error', 'owner_access_email_failed {request_id} {exception_class}', [
                'request_id' => service('requestContext')->requestId(),
                'exception_class' => $exception::class,
            ]);
            return false;
        }
    }

    private function lock(): void
    {
        $row = $this->db->query('SELECT GET_LOCK(?, 10) AS acquired', [self::MEMBERSHIP_LOCK])->getRowArray();
        if ((int) ($row['acquired'] ?? 0) !== 1) {
            throw new ApiException('OWNER_BUSY', 'Another Owner operation is in progress. Please retry.', 409);
        }
    }

    private function releaseLock(): void
    {
        $this->db->query('SELECT RELEASE_LOCK(?)', [self::MEMBERSHIP_LOCK]);
    }
}
