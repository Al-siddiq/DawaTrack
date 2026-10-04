<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Enums\PermissionCode;
use App\Exceptions\ApiException;
use App\Modules\Audit\Application\AuditService;
use App\Modules\Audit\Domain\AuditActor;
use App\Modules\Audit\Domain\AuditAppendCommand;
use App\Modules\Audit\Domain\AuditCategory;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use App\Support\UtcTimestamp;
use App\Support\SensitiveReason;
use CodeIgniter\Database\BaseConnection;
use Throwable;

final class PermissionService
{
    public function __construct(
        private readonly BaseConnection $database,
        private readonly AuditService $audit,
    ) {
    }

    /** @return list<array{code: string, group: string, label: string}> */
    public static function catalogue(): array
    {
        return array_map(static function (PermissionCode $permission): array {
            [$group, $action] = array_pad(explode('.', $permission->value, 2), 2, 'access');

            return [
                'code' => $permission->value,
                'group' => ucwords(str_replace('_', ' ', $group)),
                'label' => ucwords(str_replace(['.', '_'], ' ', $group . ' ' . $action)),
            ];
        }, PermissionCode::cases());
    }

    public function grant(ShopContext $context, int $membershipId, string $permissionCode, string $reason): void
    {
        $context->requireOwner();
        $this->acquireLock($context->tenantId, $membershipId);
        try {
            $this->grantLocked($context, $membershipId, $permissionCode, $reason);
        } finally {
            $this->releaseLock($context->tenantId, $membershipId);
        }
    }

    private function grantLocked(ShopContext $context, int $membershipId, string $permissionCode, string $reason): void
    {
        $this->assertKnown($permissionCode);
        $reason = SensitiveReason::require($reason);
        $membership = $this->staffMembership($context->tenantId, $membershipId, true);
        $existing = $this->database->table('membership_permission_grants')->where([
            'tenant_id' => $context->tenantId,
            'membership_id' => $membershipId,
            'permission_code' => $permissionCode,
            'revoked_at' => null,
        ])->countAllResults();
        if ($existing > 0) {
            throw new ApiException('PERMISSION_UNCHANGED', 'That permission is already granted.', 409);
        }

        $now = UtcTimestamp::now();
        $this->database->transException(true)->transBegin();
        try {
            $this->database->table('membership_permission_grants')->insert([
                'tenant_id' => $context->tenantId,
                'membership_id' => $membershipId,
                'permission_code' => $permissionCode,
                'granted_by_user_id' => $context->userId,
                'granted_at' => $now,
                'created_at' => $now,
            ]);
            $this->audit->append(new AuditAppendCommand(
                actor: AuditActor::tenantUser($context->userId, $context->membershipId, $context->tenantId, $context->shopId),
                actionCode: 'permission.granted',
                category: AuditCategory::AUTHORIZATION,
                resourceType: 'tenant_membership',
                resourcePublicId: $membership['public_id'],
                reason: $reason,
                metadata: ['permission_code' => $permissionCode, 'before' => false, 'after' => true],
            ));
            $this->database->transCommit();
        } catch (Throwable $exception) {
            $this->database->transRollback();
            throw $exception;
        }
    }

    public function revoke(ShopContext $context, int $membershipId, string $permissionCode, string $reason): void
    {
        $context->requireOwner();
        $this->acquireLock($context->tenantId, $membershipId);
        try {
            $this->revokeLocked($context, $membershipId, $permissionCode, $reason);
        } finally {
            $this->releaseLock($context->tenantId, $membershipId);
        }
    }

    private function revokeLocked(ShopContext $context, int $membershipId, string $permissionCode, string $reason): void
    {
        $this->assertKnown($permissionCode);
        $reason = SensitiveReason::require($reason);
        $membership = $this->staffMembership($context->tenantId, $membershipId, false);
        $grant = $this->database->table('membership_permission_grants')->where([
            'tenant_id' => $context->tenantId,
            'membership_id' => $membershipId,
            'permission_code' => $permissionCode,
            'revoked_at' => null,
        ])->orderBy('granted_at', 'DESC')->get(1)->getRowArray();
        if ($grant === null) {
            throw new ApiException('PERMISSION_UNCHANGED', 'That permission is not currently granted.', 409);
        }

        $now = UtcTimestamp::now();
        $this->database->transException(true)->transBegin();
        try {
            $this->database->table('membership_permission_grants')->where('id', $grant['id'])->update([
                'revoked_by_user_id' => $context->userId,
                'revoked_at' => $now,
            ]);
            $this->audit->append(new AuditAppendCommand(
                actor: AuditActor::tenantUser($context->userId, $context->membershipId, $context->tenantId, $context->shopId),
                actionCode: 'permission.revoked',
                category: AuditCategory::AUTHORIZATION,
                resourceType: 'tenant_membership',
                resourcePublicId: $membership['public_id'],
                reason: $reason,
                metadata: ['permission_code' => $permissionCode, 'before' => true, 'after' => false],
            ));
            $this->database->transCommit();
        } catch (Throwable $exception) {
            $this->database->transRollback();
            throw $exception;
        }
    }

    public function allows(int $tenantId, int $membershipId, string $permissionCode): bool
    {
        if (! PermissionCode::accepts($permissionCode)) {
            return false;
        }
        $membership = $this->database->table('tenant_memberships')->where([
            'tenant_id' => $tenantId,
            'id' => $membershipId,
            'status' => 'ACTIVE',
        ])->get()->getRowArray();
        if ($membership === null) {
            return false;
        }
        if ($membership['role'] === 'PRIMARY_OWNER') {
            return true;
        }

        return $this->database->table('membership_permission_grants')->where([
            'tenant_id' => $tenantId,
            'membership_id' => $membershipId,
            'permission_code' => $permissionCode,
            'revoked_at' => null,
        ])->countAllResults() > 0;
    }

    private function assertKnown(string $permissionCode): void
    {
        if (! PermissionCode::accepts($permissionCode)) {
            throw new ApiException('UNKNOWN_PERMISSION', 'The permission is not recognized.', 422, ['permission_code' => ['Choose an approved permission.']]);
        }
    }

    private function acquireLock(int $tenantId, int $membershipId): void
    {
        $name = "dawatrack:permission:{$tenantId}:{$membershipId}";
        $row = $this->database->query('SELECT GET_LOCK(?, 10) AS lock_acquired', [$name])->getRowArray();
        if ((int) ($row['lock_acquired'] ?? 0) !== 1) {
            throw new ApiException('PERMISSION_BUSY', 'Another permission change is in progress. Please retry.', 409);
        }
    }

    private function releaseLock(int $tenantId, int $membershipId): void
    {
        $this->database->query('SELECT RELEASE_LOCK(?)', ["dawatrack:permission:{$tenantId}:{$membershipId}"]);
    }

    /** @return array<string, mixed> */
    private function staffMembership(int $tenantId, int $membershipId, bool $mustBeActive): array
    {
        $builder = $this->database->table('tenant_memberships')->where([
            'tenant_id' => $tenantId,
            'id' => $membershipId,
            'role' => 'STAFF',
        ]);
        if ($mustBeActive) {
            $builder->where('status', 'ACTIVE');
        }
        $membership = $builder->get()->getRowArray();
        if ($membership === null) {
            throw new ApiException('RESOURCE_NOT_FOUND', 'The Staff member was not found.', 404);
        }

        return $membership;
    }
}
