<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application;

use App\Modules\Audit\Domain\AuditActor;
use App\Modules\Audit\Domain\AuditAuthority;
use CodeIgniter\Database\BaseConnection;
use App\Exceptions\ApiException;

final class AuditAuthorityValidator
{
    public function __construct(private readonly BaseConnection $database)
    {
    }

    public function validate(AuditActor $actor): void
    {
        if ($actor->tenantId === null && $actor->shopId !== null) {
            throw new ApiException('AUDIT_CONTEXT_INVALID', 'Audit Shop scope requires Tenant scope.', 422);
        }

        $this->validateShopScope($actor);

        match ($actor->authority) {
            AuditAuthority::TENANT => $this->validateTenantAuthority($actor),
            AuditAuthority::PLATFORM => $this->validatePlatformAuthority($actor),
            AuditAuthority::IDENTITY => $this->validateIdentityAuthority($actor),
            AuditAuthority::SYSTEM => $this->validateSystemAuthority($actor),
        };
    }

    private function validateShopScope(AuditActor $actor): void
    {
        if ($actor->shopId === null) {
            return;
        }

        $exists = $this->database->table('shops')->where([
            'id' => $actor->shopId,
            'tenant_id' => $actor->tenantId,
        ])->countAllResults() === 1;

        if (! $exists) {
            throw new ApiException('AUDIT_CONTEXT_INVALID', 'Audit authority context is invalid.', 422);
        }
    }

    private function validateTenantAuthority(AuditActor $actor): void
    {
        if ($actor->userId === null || $actor->membershipId === null || $actor->tenantId === null || $actor->shopId === null) {
            throw new ApiException('AUDIT_CONTEXT_INVALID', 'Tenant Audit authority is incomplete.', 422);
        }

        $valid = $this->database->table('tenant_memberships')->where([
            'id' => $actor->membershipId,
            'tenant_id' => $actor->tenantId,
            'user_id' => $actor->userId,
            'status' => 'ACTIVE',
        ])->countAllResults() === 1;

        if (! $valid) {
            throw new ApiException('AUDIT_CONTEXT_INVALID', 'Audit authority context is invalid.', 422);
        }
    }

    private function validatePlatformAuthority(AuditActor $actor): void
    {
        if ($actor->userId === null || $actor->membershipId !== null) {
            throw new ApiException('AUDIT_CONTEXT_INVALID', 'Platform Audit authority is invalid.', 422);
        }

        $valid = $this->database->table('auth_groups_users')->where([
            'user_id' => $actor->userId,
            'group' => 'platform_admin',
        ])->countAllResults() === 1;

        if (! $valid) {
            throw new ApiException('AUDIT_CONTEXT_INVALID', 'Audit authority context is invalid.', 422);
        }
    }

    private function validateIdentityAuthority(AuditActor $actor): void
    {
        if ($actor->userId === null || $actor->membershipId !== null || $actor->tenantId !== null || $actor->shopId !== null) {
            throw new ApiException('AUDIT_CONTEXT_INVALID', 'Identity Audit authority is invalid.', 422);
        }

        if ($this->database->table('users')->where('id', $actor->userId)->countAllResults() !== 1) {
            throw new ApiException('AUDIT_CONTEXT_INVALID', 'Audit authority context is invalid.', 422);
        }
    }

    private function validateSystemAuthority(AuditActor $actor): void
    {
        if ($actor->userId !== null || $actor->membershipId !== null) {
            throw new ApiException('AUDIT_CONTEXT_INVALID', 'System Audit authority cannot have a user or membership.', 422);
        }
    }
}
