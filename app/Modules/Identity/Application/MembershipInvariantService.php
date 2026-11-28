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
    /** Prevents one identity from holding multiple pending Primary Owner invitations. */
    public function assertUserMayBeInvitedAsPrimaryOwner(int $userId, ?int $exceptTenantId = null): void
    {
        $builder = $this->database->table('tenant_memberships')
            ->where('user_id', $userId)
            ->where('role', 'PRIMARY_OWNER')
            ->where('status', 'INVITED');

        if ($exceptTenantId !== null) {
            $builder->where('tenant_id !=', $exceptTenantId);
        }

        if ($builder->countAllResults() > 0) {
            throw new ApiException(
                'OWNER_INVITATION_CONFLICT',
                'This identity already has a pending Primary Owner invitation.',
                409,
            );
        }
    }

}
