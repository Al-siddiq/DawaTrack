<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application;

use App\Exceptions\ApiException;
use App\Modules\Audit\Domain\AuditActor;
use App\Modules\Audit\Domain\AuditAppendCommand;
use App\Modules\Audit\Domain\AuditCategory;
use App\Support\SensitiveReason;
use App\Support\Uuid;
use CodeIgniter\Database\BaseConnection;
use Throwable;

final class SupportAuditAccessService
{
    public function __construct(private readonly BaseConnection $database, private readonly AuditService $audit)
    {
    }

    /** @return array{access_id:string,tenant_id:int,shop_id:int,tenant_public_id:string,shop_public_id:string,tenant_name:string,shop_name:string,reason:string} */
    public function establish(int $adminUserId, string $tenantPublicId, string $shopPublicId, string $reason): array
    {
        $reason = SensitiveReason::require($reason);
        $target = $this->resolveTarget($tenantPublicId, $shopPublicId);
        $this->database->transException(true)->transBegin();
        try {
            $id = $this->audit->append(new AuditAppendCommand(
                actor: AuditActor::platformUser($adminUserId, $target['tenant_id'], $target['shop_id']),
                actionCode: 'support.audit_accessed',
                category: AuditCategory::SUPPORT,
                resourceType: 'support_audit_scope',
                resourcePublicId: $target['shop_public_id'],
                reason: $reason,
                metadata: ['purpose' => 'AUDIT_DIAGNOSTIC_READ', 'tenant_public_id' => $target['tenant_public_id'], 'shop_public_id' => $target['shop_public_id']],
            ));
            $event = $this->database->table('audit_events')->select('public_id')->where('id', $id)->get()->getRowArray();
            if ($event === null || ! $this->database->transCommit()) {
                throw new ApiException(
                    'SUPPORT_ACCESS_FAILED',
                    'Support Audit access could not be established.',
                    500,
                );
            }

            return ['access_id' => $event['public_id'], 'reason' => $reason] + $target;
        } catch (Throwable $exception) {
            $this->database->transRollback();
            throw $exception;
        }
    }

    /** @return array{access_id:string,tenant_id:int,shop_id:int,tenant_public_id:string,shop_public_id:string,tenant_name:string,shop_name:string,reason:string} */
    public function resume(int $adminUserId, string $accessId): array
    {
        $accessId = strtolower(trim($accessId));
        if (! Uuid::isV4($accessId)) {
            throw new ApiException('SUPPORT_SCOPE_INVALID', 'The support Audit scope is invalid or expired.', 403);
        }

        $row = $this->database->table('audit_events a')
            ->select(implode(',', [
                'a.public_id',
                'a.tenant_id',
                'a.shop_id',
                'a.actor_user_id',
                'a.reason',
                'a.created_at',
                't.public_id tenant_public_id',
                't.legal_or_business_name tenant_name',
                's.public_id shop_public_id',
                's.name shop_name',
            ]))
            ->join('tenants t', 't.id = a.tenant_id')
            ->join('shops s', 's.id = a.shop_id AND s.tenant_id = a.tenant_id')
            ->where([
                'a.public_id' => $accessId,
                'a.action_code' => 'support.audit_accessed',
                'a.category' => AuditCategory::SUPPORT,
                'a.actor_user_id' => $adminUserId,
            ])
            ->where('a.created_at >=', gmdate('Y-m-d H:i:s', time() - 900))
            ->get()
            ->getRowArray();
        if ($row === null) {
            throw new ApiException('SUPPORT_SCOPE_INVALID', 'The support Audit scope is invalid or expired.', 403);
        }

        return [
            'access_id' => $row['public_id'],
            'tenant_id' => (int) $row['tenant_id'],
            'shop_id' => (int) $row['shop_id'],
            'tenant_public_id' => $row['tenant_public_id'],
            'shop_public_id' => $row['shop_public_id'],
            'tenant_name' => $row['tenant_name'],
            'shop_name' => $row['shop_name'],
            'reason' => $row['reason'],
        ];
    }

    /** @return array{tenant_id:int,shop_id:int,tenant_public_id:string,shop_public_id:string,tenant_name:string,shop_name:string} */
    private function resolveTarget(string $tenantPublicId, string $shopPublicId): array
    {
        $tenantPublicId = strtolower(trim($tenantPublicId));
        $shopPublicId = strtolower(trim($shopPublicId));
        if (! Uuid::isV4($tenantPublicId) || ! Uuid::isV4($shopPublicId)) {
            throw new ApiException('SUPPORT_SCOPE_INVALID', 'The support Audit target is invalid.', 404);
        }

        $row = $this->database->table('tenants t')
            ->select(implode(',', [
                't.id tenant_id',
                't.public_id tenant_public_id',
                't.legal_or_business_name tenant_name',
                's.id shop_id',
                's.public_id shop_public_id',
                's.name shop_name',
            ]))
            ->join('shops s', 's.tenant_id = t.id')
            ->where([
                't.public_id' => $tenantPublicId,
                's.public_id' => $shopPublicId,
            ])
            ->get()
            ->getRowArray();
        if ($row === null) {
            throw new ApiException('SUPPORT_SCOPE_INVALID', 'The support Audit target is invalid.', 404);
        }

        return [
            'tenant_id' => (int) $row['tenant_id'],
            'shop_id' => (int) $row['shop_id'],
            'tenant_public_id' => $row['tenant_public_id'],
            'shop_public_id' => $row['shop_public_id'],
            'tenant_name' => $row['tenant_name'],
            'shop_name' => $row['shop_name'],
        ];
    }
}
