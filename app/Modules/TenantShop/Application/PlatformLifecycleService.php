<?php

declare(strict_types=1);

namespace App\Modules\TenantShop\Application;

use App\Exceptions\ApiException;
use App\Modules\Audit\Application\AuditService;
use App\Support\SensitiveReason;
use App\Support\UtcTimestamp;
use CodeIgniter\Database\BaseConnection;
use Throwable;

final class PlatformLifecycleService
{
    public function __construct(
        private readonly BaseConnection $db,
        private readonly AuditService $audit,
    ) {
    }

    public function tenant(
        string $publicId,
        string $action,
        int $actor,
        string $reason,
    ): void {
        $reason = SensitiveReason::require($reason);

        $this->db
            ->transException(true)
            ->transBegin();

        try {
            $tenant = $this->db
                ->query(
                    'SELECT * FROM tenants WHERE public_id = ? FOR UPDATE',
                    [$publicId],
                )
                ->getRowArray();

            if ($tenant === null) {
                throw new ApiException(
                    'RESOURCE_NOT_FOUND',
                    'Tenant not found.',
                    404,
                );
            }

            $from = (string) $tenant['status'];
            $target = $this->tenantTarget(
                $action,
                $from,
            );

            $now = UtcTimestamp::now();

            $changes = [
                'status' => $target,
                'status_reason' => $reason,
                'updated_at' => $now,
            ];

            if ($action === 'suspend') {
                $changes['suspended_at'] = $now;
            } elseif ($action === 'reactivate') {
                $changes['activated_at'] = $now;
                $changes['suspended_at'] = null;
            } else {
                $changes['closed_at'] = $now;
            }

            /*
             * The row is already locked, but retaining the expected status
             * in the UPDATE also provides an explicit stale-writer guard.
             */
            $this->db
                ->table('tenants')
                ->where('id', $tenant['id'])
                ->where('status', $from)
                ->update($changes);

            if ($this->db->affectedRows() !== 1) {
                throw new ApiException(
                    'CONFLICT',
                    'The Tenant status changed before this operation could be completed.',
                    409,
                );
            }

            $this->audit->record([
                'tenant_id' => $tenant['id'],
                'actor_type' => 'USER',
                'actor_user_id' => $actor,
                'action_code' => 'tenant.' . $action,
                'category' => 'TENANT',
                'resource_type' => 'tenant',
                'resource_public_id' => $publicId,
                'reason' => $reason,
                'change_metadata' => [
                    'before' => $from,
                    'after' => $target,
                ],
            ]);

            if (! $this->db->transCommit()) {
                throw new ApiException(
                    'REQUEST_FAILED',
                    'Tenant lifecycle change could not be committed.',
                    500,
                );
            }
        } catch (Throwable $exception) {
            $this->db->transRollback();

            throw $exception;
        }
    }

    public function shop(
        string $publicId,
        string $action,
        int $actor,
        string $reason,
    ): void {
        $reason = SensitiveReason::require($reason);

        $this->db
            ->transException(true)
            ->transBegin();

        try {
            $shop = $this->db
                ->query(
                    'SELECT * FROM shops WHERE public_id = ? FOR UPDATE',
                    [$publicId],
                )
                ->getRowArray();

            if ($shop === null) {
                throw new ApiException(
                    'RESOURCE_NOT_FOUND',
                    'Shop not found.',
                    404,
                );
            }

            $from = (string) $shop['status'];
            $target = $this->shopTarget(
                $action,
                $from,
            );

            $now = UtcTimestamp::now();

            $changes = [
                'status' => $target,
                'updated_at' => $now,
            ];

            if ($target === 'ACTIVE') {
                $changes['activated_at'] = $now;
                $changes['deactivated_at'] = null;
            } else {
                $changes['deactivated_at'] = $now;
            }

            $this->db
                ->table('shops')
                ->where('id', $shop['id'])
                ->where('status', $from)
                ->update($changes);

            if ($this->db->affectedRows() !== 1) {
                throw new ApiException(
                    'CONFLICT',
                    'The Shop status changed before this operation could be completed.',
                    409,
                );
            }

            $this->audit->record([
                'tenant_id' => $shop['tenant_id'],
                'shop_id' => $shop['id'],
                'actor_type' => 'USER',
                'actor_user_id' => $actor,
                'action_code' => 'shop.' . $action,
                'category' => 'TENANT',
                'resource_type' => 'shop',
                'resource_public_id' => $publicId,
                'reason' => $reason,
                'change_metadata' => [
                    'before' => $from,
                    'after' => $target,
                ],
            ]);

            if (! $this->db->transCommit()) {
                throw new ApiException(
                    'REQUEST_FAILED',
                    'Shop lifecycle change could not be committed.',
                    500,
                );
            }
        } catch (Throwable $exception) {
            $this->db->transRollback();

            throw $exception;
        }
    }

    private function tenantTarget(
        string $action,
        string $from,
    ): string {
        $target = match ($action) {
            'suspend' => $from === 'ACTIVE'
                ? 'SUSPENDED'
                : null,

            'reactivate' => $from === 'SUSPENDED'
                ? 'ACTIVE'
                : null,

            'close' => in_array(
                $from,
                ['ACTIVE', 'SUSPENDED'],
                true,
            )
                ? 'CLOSED'
                : null,

            default => null,
        };

        if ($target === null) {
            throw new ApiException(
                'CONFLICT',
                'That Tenant lifecycle transition is not valid.',
                409,
            );
        }

        return $target;
    }

    private function shopTarget(
        string $action,
        string $from,
    ): string {
        $target = match ($action) {
            'deactivate' => $from === 'ACTIVE'
                ? 'INACTIVE'
                : null,

            'reactivate' => $from === 'INACTIVE'
                ? 'ACTIVE'
                : null,

            default => null,
        };

        if ($target === null) {
            throw new ApiException(
                'CONFLICT',
                'That Shop lifecycle transition is not valid.',
                409,
            );
        }

        return $target;
    }
}