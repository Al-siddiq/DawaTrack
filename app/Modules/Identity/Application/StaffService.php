<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Exceptions\ApiException;
use App\Modules\Audit\Application\AuditService;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use App\Security\UserSessionRevocationService;
use App\Support\UtcTimestamp;
use App\Support\SensitiveReason;
use App\Support\Uuid;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use Throwable;

final class StaffService
{
    private const MEMBERSHIP_LOCK_NAME = 'dawatrack:identity-membership';

    public function __construct(
        private readonly BaseConnection $database,
        private readonly AuditService $audit,
        private readonly UserSessionRevocationService $sessionRevocation,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function listing(ShopContext $context): array
    {
        $context->requireOwner();

        return $this->database->table('tenant_memberships m')
            ->select('m.id,m.public_id,m.user_id,m.role,m.status,m.activated_at,m.deactivated_at,u.username,i.secret AS email')
            ->join('users u', 'u.id=m.user_id')
            ->join('auth_identities i', "i.user_id=m.user_id AND i.type='email_password'", 'left')
            ->where('m.tenant_id', $context->tenantId)
            ->orderBy('m.role')
            ->orderBy('m.created_at')
            ->get()
            ->getResultArray();
    }

    /** @param array<string, mixed> $input */
    public function create(ShopContext $context, array $input): array
    {
        $context->requireOwner();
        $data = $this->validateIdentityInput($input);
        $this->acquireMembershipLock();

        try {
            $this->database->transException(true)->transBegin();
            $this->assertCapacity($context->tenantId);
            $provider = auth()->getProvider();
            if ($provider->findByCredentials(['email' => $data['email']]) !== null) {
                throw new ApiException('STAFF_IDENTITY_UNAVAILABLE', 'That Staff identity cannot be used.', 409, ['email' => ['Use an email not assigned to another account.']]);
            }

            $user = new User([
                'username' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'active' => 1,
            ]);
            if (! $provider->save($user)) {
                throw new ApiException('VALIDATION_FAILED', 'The Staff identity is invalid.', 422, ['email' => ['The Staff identity could not be created.']]);
            }
            $userId = (int) $provider->getInsertID();
            $savedUser = $provider->findById($userId);
            if ($savedUser !== null && method_exists($savedUser, 'forcePasswordReset')) {
                $savedUser->forcePasswordReset();
                $provider->save($savedUser);
            }
            (new MembershipInvariantService($this->database))->assertUserMayActivate($userId);

            $now = UtcTimestamp::now();
            $publicId = Uuid::v4();
            $this->database->table('tenant_memberships')->insert([
                'public_id' => $publicId,
                'tenant_id' => $context->tenantId,
                'user_id' => $userId,
                'role' => 'STAFF',
                'status' => 'ACTIVE',
                'activated_at' => $now,
                'created_by_user_id' => $context->userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->audit->record([
                'tenant_id' => $context->tenantId,
                'shop_id' => $context->shopId,
                'actor_type' => 'USER',
                'actor_user_id' => $context->userId,
                'actor_membership_id' => $context->membershipId,
                'action_code' => 'staff.created',
                'category' => 'AUTHORIZATION',
                'resource_type' => 'tenant_membership',
                'resource_public_id' => $publicId,
                'reason' => $data['reason'],
                'change_metadata' => ['role' => 'STAFF', 'status' => 'ACTIVE'],
            ]);
            $this->database->transCommit();

            return ['public_id' => $publicId, 'email' => $data['email'], 'status' => 'ACTIVE'];
        } catch (Throwable $exception) {
            $this->database->transRollback();
            throw $exception;
        } finally {
            $this->releaseMembershipLock();
        }
    }

    public function setActive(ShopContext $context, int $membershipId, bool $active, string $reason): void
    {
        $context->requireOwner();
        $reason = SensitiveReason::require($reason);
        $this->acquireMembershipLock();

        try {
            $this->database->transException(true)->transBegin();
            $membership = $this->staffMembership($context->tenantId, $membershipId);
            $targetStatus = $active ? 'ACTIVE' : 'DEACTIVATED';
            if ($membership['status'] === $targetStatus) {
                throw new ApiException('STAFF_STATUS_UNCHANGED', 'The Staff status is already current.', 409);
            }
            if ($active) {
                (new MembershipInvariantService($this->database))->assertUserMayActivate((int) $membership['user_id'], $membershipId);
                $this->assertCapacity($context->tenantId);
            }

            $now = UtcTimestamp::now();
            $this->database->table('tenant_memberships')->where('id', $membershipId)->update([
                'status' => $targetStatus,
                'activated_at' => $active ? $now : $membership['activated_at'],
                'deactivated_at' => $active ? null : $now,
                'updated_at' => $now,
            ]);
            $this->database->table('users')->where('id', $membership['user_id'])->update(['active' => $active ? 1 : 0]);
            if (! $active) {
                $this->invalidateIdentityAccess((int) $membership['user_id']);
            }
            $this->audit->record([
                'tenant_id' => $context->tenantId,
                'shop_id' => $context->shopId,
                'actor_type' => 'USER',
                'actor_user_id' => $context->userId,
                'actor_membership_id' => $context->membershipId,
                'action_code' => $active ? 'staff.activated' : 'staff.deactivated',
                'category' => 'AUTHORIZATION',
                'resource_type' => 'tenant_membership',
                'resource_public_id' => $membership['public_id'],
                'reason' => $reason,
                'change_metadata' => ['before' => ['status' => $membership['status']], 'after' => ['status' => $targetStatus]],
            ]);
            $this->database->transCommit();
        } catch (Throwable $exception) {
            $this->database->transRollback();
            throw $exception;
        } finally {
            $this->releaseMembershipLock();
        }
    }

    /** @return array{temporary_password: string} */
    public function resetPassword(ShopContext $context, int $membershipId, string $reason): array
    {
        $context->requireOwner();
        $reason = SensitiveReason::require($reason);
        $this->acquireMembershipLock();
        try {
            $membership = $this->staffMembership($context->tenantId, $membershipId);
            $provider = auth()->getProvider();
            $user = $provider->findById($membership['user_id']);
            if ($user === null || $user->inGroup('platform_admin')) {
                throw new ApiException('RESOURCE_NOT_FOUND', 'The Staff member was not found.', 404);
            }

            $temporaryPassword = $this->temporaryPassword();
            $this->database->transException(true)->transBegin();
            try {
                $user->password = $temporaryPassword;
                if (method_exists($user, 'forcePasswordReset')) {
                    $user->forcePasswordReset();
                }
                if (! $provider->save($user)) {
                    throw new ApiException('CREDENTIAL_RESET_FAILED', 'Staff credentials could not be reset.', 500);
                }
                $this->invalidateIdentityAccess((int) $membership['user_id']);
                $this->audit->record([
                    'tenant_id' => $context->tenantId,
                    'shop_id' => $context->shopId,
                    'actor_type' => 'USER',
                    'actor_user_id' => $context->userId,
                    'actor_membership_id' => $context->membershipId,
                    'action_code' => 'staff.credentials_reset',
                    'category' => 'SECURITY',
                    'resource_type' => 'tenant_membership',
                    'resource_public_id' => $membership['public_id'],
                    'reason' => $reason,
                    'change_metadata' => ['sessions_invalidated' => true, 'force_reset_requested' => method_exists($user, 'forcePasswordReset')],
                ]);
                $this->database->transCommit();
            } catch (Throwable $exception) {
                $this->database->transRollback();
                throw $exception;
            }

            return ['temporary_password' => $temporaryPassword];
        } finally {
            $this->releaseMembershipLock();
        }
    }

    /** @return array<string, mixed> */
    public function permissions(ShopContext $context, int $membershipId): array
    {
        $context->requireOwner();
        $membership = $this->staffMembership($context->tenantId, $membershipId);
        $history = $this->database->table('membership_permission_grants')
            ->select('id,permission_code,granted_at,revoked_at')
            ->where(['tenant_id' => $context->tenantId, 'membership_id' => $membershipId])
            ->orderBy('granted_at', 'DESC')
            ->get()
            ->getResultArray();
        $active = array_values(array_unique(array_column(array_filter(
            $history,
            static fn (array $row): bool => $row['revoked_at'] === null,
        ), 'permission_code')));

        return [
            'membership' => ['id' => $membership['id'], 'public_id' => $membership['public_id'], 'status' => $membership['status']],
            'catalogue' => PermissionService::catalogue(),
            'active' => $active,
            'history' => $history,
        ];
    }

    /** @return array<string, string> */
    private function validateIdentityInput(array $input): array
    {
        $data = [
            'name' => trim((string) ($input['name'] ?? '')),
            'email' => strtolower(trim((string) ($input['email'] ?? ''))),
            'password' => (string) ($input['password'] ?? ''),
            'reason' => trim((string) ($input['reason'] ?? '')),
        ];
        $errors = [];
        if ($data['name'] === '' || mb_strlen($data['name']) > 100) {
            $errors['name'] = ['Enter a name of no more than 100 characters.'];
        }
        if (! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = ['Enter a valid email address.'];
        }
        if (strlen($data['password']) < 12) {
            $errors['password'] = ['Use a temporary password of at least 12 characters.'];
        }
        if ($data['reason'] === '') {
            $errors['reason'] = ['Enter a creation reason.'];
        }
        if ($errors !== []) {
            throw new ApiException('VALIDATION_FAILED', 'Correct the Staff fields.', 422, $errors);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function staffMembership(int $tenantId, int $membershipId): array
    {
        $membership = $this->database->table('tenant_memberships')
            ->where(['id' => $membershipId, 'tenant_id' => $tenantId, 'role' => 'STAFF'])
            ->get()
            ->getRowArray();
        if ($membership === null) {
            throw new ApiException('RESOURCE_NOT_FOUND', 'The Staff member was not found.', 404);
        }

        return $membership;
    }

    private function assertCapacity(int $tenantId): void
    {
        $now = UtcTimestamp::now();
        $subscription = $this->database->table('subscriptions')
            ->where('tenant_id', $tenantId)
            ->where('starts_at <=', $now)
            ->where('ends_at >', $now)
            ->orderBy('starts_at', 'DESC')
            ->get(1)
            ->getRowArray();
        if ($subscription === null) {
            throw new ApiException('READ_ONLY', 'An active entitlement is required to activate Staff.', 403);
        }
        $active = $this->database->table('tenant_memberships')
            ->where(['tenant_id' => $tenantId, 'role' => 'STAFF', 'status' => 'ACTIVE'])
            ->countAllResults();
        if ($active >= (int) $subscription['max_active_staff_snapshot']) {
            throw new ApiException('STAFF_CAP_REACHED', 'The current plan Staff allowance has been reached.', 409);
        }
    }

    private function acquireMembershipLock(): void
    {
        $row = $this->database->query('SELECT GET_LOCK(?, 10) AS lock_acquired', [self::MEMBERSHIP_LOCK_NAME])->getRowArray();
        if ((int) ($row['lock_acquired'] ?? 0) !== 1) {
            throw new ApiException('STAFF_BUSY', 'Another Staff change is in progress. Please retry.', 409);
        }
    }

    private function releaseMembershipLock(): void
    {
        $this->database->query('SELECT RELEASE_LOCK(?)', [self::MEMBERSHIP_LOCK_NAME]);
    }

    private function invalidateIdentityAccess(int $userId): void
    {
        $this->database->table('auth_remember_tokens')->where('user_id', $userId)->delete();
        $this->database->table('auth_identities')->where('user_id', $userId)->where('type', 'magic-link')->delete();
        $this->sessionRevocation->revokeAll($userId);
    }

    private function temporaryPassword(): string
    {
        return 'Dt!' . bin2hex(random_bytes(9)) . '9a';
    }
}
