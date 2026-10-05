<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain;

final readonly class AuditActor
{
    private function __construct(
        public AuditAuthority $authority,
        public ?int $userId,
        public ?int $membershipId,
        public ?int $tenantId,
        public ?int $shopId,
    ) {
    }

    public static function tenantUser(
        int $userId,
        int $membershipId,
        int $tenantId,
        int $shopId,
    ): self {
        return new self(AuditAuthority::TENANT, $userId, $membershipId, $tenantId, $shopId);
    }

    public static function platformUser(int $userId, ?int $tenantId = null, ?int $shopId = null): self
    {
        return new self(AuditAuthority::PLATFORM, $userId, null, $tenantId, $shopId);
    }

    public static function identityUser(int $userId): self
    {
        return new self(AuditAuthority::IDENTITY, $userId, null, null, null);
    }

    public static function system(?int $tenantId = null, ?int $shopId = null): self
    {
        return new self(AuditAuthority::SYSTEM, null, null, $tenantId, $shopId);
    }

    public function actorType(): string
    {
        return $this->authority === AuditAuthority::SYSTEM ? 'SYSTEM' : 'USER';
    }
}
