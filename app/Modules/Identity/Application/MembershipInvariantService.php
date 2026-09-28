<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Exceptions\ApiException;
use CodeIgniter\Database\BaseConnection;

final class MembershipInvariantService
{
    public function __construct(private readonly BaseConnection $database)
    {
    }

    /** Prevents an ordinary identity from holding active authority in multiple Tenants. */
    public function assertUserMayActivate(int $userId, ?int $exceptMembershipId = null): void
    {
        $builder = $this->database->table('tenant_memberships')
            ->where('user_id', $userId)
            ->where('status', 'ACTIVE');

        if ($exceptMembershipId !== null) {
            $builder->where('id !=', $exceptMembershipId);
        }

        if ($builder->countAllResults() > 0) {
            throw new ApiException(
                'MEMBERSHIP_CONFLICT',
                'This identity already has an active Tenant membership.',
                409,
            );
        }
    }
}
