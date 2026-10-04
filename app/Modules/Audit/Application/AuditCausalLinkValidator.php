<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application;

use App\Modules\Audit\Domain\AuditActor;
use CodeIgniter\Database\BaseConnection;
use App\Exceptions\ApiException;

final class AuditCausalLinkValidator
{
    public function __construct(private readonly BaseConnection $database)
    {
    }

    public function validate(?int $causedById, AuditActor $actor): void
    {
        if ($causedById === null) {
            return;
        }
        if ($causedById < 1) {
            throw new ApiException('AUDIT_CAUSE_INVALID', 'Audit causal link is invalid.', 422);
        }

        $cause = $this->database->table('audit_events')
            ->select('tenant_id,shop_id')
            ->where('id', $causedById)
            ->get()
            ->getRowArray();

        $sameTenant = $cause !== null && $this->sameNullableId($cause['tenant_id'], $actor->tenantId);
        $sameShop = $cause !== null && $this->sameNullableId($cause['shop_id'], $actor->shopId);
        if (! $sameTenant || ! $sameShop) {
            throw new ApiException('AUDIT_CAUSE_INVALID', 'Audit causal link is invalid.', 422);
        }
    }

    private function sameNullableId(mixed $stored, ?int $expected): bool
    {
        return $stored === null ? $expected === null : (int) $stored === $expected;
    }
}
