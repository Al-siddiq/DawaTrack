<?php

declare(strict_types=1);

namespace App\Modules\FinancialMovement\Infrastructure;

use App\Exceptions\ApiException;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use CodeIgniter\Database\BaseConnection;

/** Internal evidence access. The owning service/query supplies authority and transaction. */
final class FinancialMovementRepository
{
    public function __construct(private readonly BaseConnection $database) {}

    public function movement(ShopContext $context, string $id): ?array
    {
        return $this->database->query(
            'SELECT m.*,s.name shop_name,s.timezone_name,u.username recorded_by,
             original.public_id reversal_of_public_id,replaced.public_id replacement_for_public_id
             FROM financial_movements m
             JOIN shops s ON s.tenant_id=m.tenant_id AND s.id=m.shop_id
             LEFT JOIN users u ON u.id=m.created_by_user_id
             LEFT JOIN financial_movements original ON original.tenant_id=m.tenant_id AND original.shop_id=m.shop_id AND original.id=m.reversal_of_movement_id
             LEFT JOIN financial_movements replaced ON replaced.tenant_id=m.tenant_id AND replaced.shop_id=m.shop_id AND replaced.id=m.replacement_for_movement_id
             WHERE m.tenant_id=? AND m.shop_id=? AND m.public_id=?',
            [$context->tenantId, $context->shopId, $id],
        )->getRowArray();
    }

    public function recordedEvidence(ShopContext $context, array $row): array
    {
        $audit = $this->database->table('audit_events')->select('change_metadata,reason')
            ->where(['tenant_id' => $context->tenantId, 'shop_id' => $context->shopId,
                'resource_type' => 'financial_movement', 'resource_public_id' => $row['public_id'],
                'action_code' => 'financial_movement.recorded'])->orderBy('id')->get(1)->getRowArray();
        $metadata = $audit ? json_decode($audit['change_metadata'] ?? '', true, 512, JSON_THROW_ON_ERROR) : null;
        if (!is_array($metadata) || !is_string($metadata['command_fingerprint'] ?? null)
            || !preg_match('/^[a-f0-9]{64}$/D', $metadata['command_fingerprint'])
            || ($metadata['movement_type'] ?? null) !== $row['movement_type']
            || ($metadata['channel'] ?? null) !== $row['channel']
            || ($metadata['amount_delta'] ?? null) !== (string) $row['amount_delta']
            || $audit['reason'] !== $row['description']) {
            throw new ApiException('FINANCIAL_EVIDENCE_INVALID', 'This money record needs checking. Ask the shop owner for help.', 409);
        }
        return $metadata;
    }
}
