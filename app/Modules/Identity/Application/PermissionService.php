<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Enums\PermissionCode;
use App\Modules\Audit\Application\AuditService;
use CodeIgniter\Database\BaseConnection;
use DomainException;

final class PermissionService
{
    public function __construct(private BaseConnection $database, private AuditService $audit)
    {
    }

    public function grant(int $tenantId, int $membershipId, string $permissionCode, int $ownerUserId, int $ownerMembershipId): void
    {
        if (! PermissionCode::accepts($permissionCode)) {
            throw new DomainException('Unknown permission code.');
        }
        $membership = $this->staffMembership($tenantId, $membershipId);
        $existing = $this->database->table('membership_permission_grants')->where([
            'tenant_id' => $tenantId, 'membership_id' => $membershipId, 'permission_code' => $permissionCode, 'revoked_at' => null,
        ])->countAllResults();
        if ($existing > 0) {
            return;
        }
        $now = gmdate('Y-m-d H:i:s.u');
        $this->database->transException(true)->transStart();
        $this->database->table('membership_permission_grants')->insert([
            'tenant_id' => $tenantId, 'membership_id' => $membershipId, 'permission_code' => $permissionCode,
            'granted_by_user_id' => $ownerUserId, 'granted_at' => $now, 'created_at' => $now,
        ]);
        $this->audit->record([
            'tenant_id' => $tenantId, 'actor_type' => 'USER', 'actor_user_id' => $ownerUserId,
            'actor_membership_id' => $ownerMembershipId, 'action_code' => 'permission.granted', 'category' => 'AUTHORIZATION',
            'resource_type' => 'membership', 'resource_public_id' => $membership['public_id'],
            'change_metadata' => ['permission_code' => $permissionCode],
        ]);
        $this->database->transComplete();
    }

    public function revoke(int $tenantId, int $membershipId, string $permissionCode, int $ownerUserId, int $ownerMembershipId, string $reason): void
    {
        if (trim($reason) === '') {
            throw new DomainException('A revocation reason is required.');
        }
        $membership = $this->staffMembership($tenantId, $membershipId);
        $now = gmdate('Y-m-d H:i:s.u');
        $this->database->transException(true)->transStart();
        $this->database->table('membership_permission_grants')->where([
            'tenant_id' => $tenantId, 'membership_id' => $membershipId, 'permission_code' => $permissionCode, 'revoked_at' => null,
        ])->update(['revoked_by_user_id' => $ownerUserId, 'revoked_at' => $now]);
        $this->audit->record([
            'tenant_id' => $tenantId, 'actor_type' => 'USER', 'actor_user_id' => $ownerUserId,
            'actor_membership_id' => $ownerMembershipId, 'action_code' => 'permission.revoked', 'category' => 'AUTHORIZATION',
            'resource_type' => 'membership', 'resource_public_id' => $membership['public_id'], 'reason' => trim($reason),
            'change_metadata' => ['permission_code' => $permissionCode],
        ]);
        $this->database->transComplete();
    }

    public function allows(int $tenantId, int $membershipId, string $permissionCode): bool
    {
        $membership = $this->database->table('tenant_memberships')->where(['tenant_id' => $tenantId, 'id' => $membershipId, 'status' => 'ACTIVE'])->get()->getRowArray();
        if (! $membership || ! PermissionCode::accepts($permissionCode)) {
            return false;
        }
        if ($membership['role'] === 'PRIMARY_OWNER') {
            return true;
        }

        return $this->database->table('membership_permission_grants')->where(['tenant_id' => $tenantId, 'membership_id' => $membershipId, 'permission_code' => $permissionCode, 'revoked_at' => null])->countAllResults() > 0;
    }

    private function staffMembership(int $tenantId, int $membershipId): array
    {
        $membership = $this->database->table('tenant_memberships')->where(['tenant_id' => $tenantId, 'id' => $membershipId, 'role' => 'STAFF'])->get()->getRowArray();
        if (! $membership) {
            throw new DomainException('Staff membership was not found in this Tenant.');
        }

        return $membership;
    }
}
