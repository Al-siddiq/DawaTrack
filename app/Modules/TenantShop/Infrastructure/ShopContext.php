<?php

declare(strict_types=1);

namespace App\Modules\TenantShop\Infrastructure;

use CodeIgniter\Database\BaseConnection;
use DomainException;

final readonly class ShopContext
{
    public function __construct(public int $tenantId, public int $shopId, public int $membershipId, public int $userId, public string $role)
    {
    }

    /** Resolves hostname and authenticated membership together; neither client IDs nor hostname grant authority. */
    public static function resolve(BaseConnection $database, int $userId, string $host): self
    {
        $hostname = strtolower((string) preg_replace('/:\\d+$/', '', trim($host)));
        $domain = $database->table('shop_domains')->where([
            'hostname' => $hostname,
            'is_active' => 1,
            'verification_status' => 'VERIFIED',
        ])->get()->getRowArray();
        if (! $domain) {
            throw new DomainException('This Shop address is unavailable.');
        }
        $membership = $database->table('tenant_memberships')->where([
            'tenant_id' => $domain['tenant_id'],
            'user_id' => $userId,
            'status' => 'ACTIVE',
        ])->get()->getRowArray();
        if (! $membership) {
            throw new DomainException('You do not have access to this Shop.');
        }

        return new self((int) $domain['tenant_id'], (int) $domain['shop_id'], (int) $membership['id'], $userId, $membership['role']);
    }

    public function requireOwner(): void
    {
        if ($this->role !== 'PRIMARY_OWNER') {
            throw new DomainException('Primary Owner access is required.');
        }
    }
}
