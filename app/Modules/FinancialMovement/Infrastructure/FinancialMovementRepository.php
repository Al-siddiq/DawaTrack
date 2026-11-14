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
             original.public_id reversal_of_public_id,replaced.public_id replacement_for_public_id,
             inverse.public_id reversed_by_public_id,replacement.public_id replacement_public_id
             FROM financial_movements m
             JOIN shops s ON s.tenant_id=m.tenant_id AND s.id=m.shop_id
             LEFT JOIN users u ON u.id=m.created_by_user_id
             LEFT JOIN financial_movements original ON original.tenant_id=m.tenant_id AND original.shop_id=m.shop_id AND original.id=m.reversal_of_movement_id
             LEFT JOIN financial_movements replaced ON replaced.tenant_id=m.tenant_id AND replaced.shop_id=m.shop_id AND replaced.id=m.replacement_for_movement_id
             LEFT JOIN financial_movements inverse ON inverse.tenant_id=m.tenant_id AND inverse.shop_id=m.shop_id AND inverse.reversal_of_movement_id=m.id
             LEFT JOIN financial_movements replacement ON replacement.tenant_id=m.tenant_id AND replacement.shop_id=m.shop_id AND replacement.replacement_for_movement_id=m.id
             WHERE m.tenant_id=? AND m.shop_id=? AND m.public_id=?',
            [$context->tenantId, $context->shopId, $id],
        )->getRowArray();
    }

    public function movementForUpdate(ShopContext $context, string $id): ?array
    {
        $found = $this->database->query(
            'SELECT id FROM financial_movements WHERE tenant_id=? AND shop_id=? AND public_id=? FOR UPDATE',
            [$context->tenantId, $context->shopId, $id],
        )->getRowArray();
        return $found ? $this->movement($context, $id) : null;
    }

    public function movementById(ShopContext $context, int $id, bool $lock = false): ?array
    {
        $sql = 'SELECT public_id FROM financial_movements WHERE tenant_id=? AND shop_id=? AND id=?' . ($lock ? ' FOR UPDATE' : '');
        $found = $this->database->query($sql, [$context->tenantId, $context->shopId, $id])->getRowArray();
        return $found ? $this->movement($context, $found['public_id']) : null;
    }

    public function reversalChild(ShopContext $context, int $sourceId, bool $lock = false): ?array
    {
        $sql = 'SELECT public_id FROM financial_movements WHERE tenant_id=? AND shop_id=? AND reversal_of_movement_id=? ORDER BY id LIMIT 1' . ($lock ? ' FOR UPDATE' : '');
        $found = $this->database->query($sql, [$context->tenantId, $context->shopId, $sourceId])->getRowArray();
        return $found ? $this->movement($context, $found['public_id']) : null;
    }

    /** @return list<array<string,mixed>> */
    public function replacementChildren(ShopContext $context, int $sourceId, bool $lock = false): array
    {
        $sql = 'SELECT public_id FROM financial_movements WHERE tenant_id=? AND shop_id=? AND replacement_for_movement_id=? ORDER BY id' . ($lock ? ' FOR UPDATE' : '');
        $rows = $this->database->query($sql, [$context->tenantId, $context->shopId, $sourceId])->getResultArray();
        $items = [];
        foreach ($rows as $row) {
            $movement = $this->movement($context, $row['public_id']);
            if (!$movement) {
                throw new ApiException('FINANCIAL_EVIDENCE_INVALID', 'The linked correction record is missing. Ask the shop owner to investigate.', 409);
            }
            $items[] = $movement;
        }
        return $items;
    }

    public function replacementChild(ShopContext $context, int $sourceId, bool $lock = false): ?array
    {
        $children = $this->replacementChildren($context, $sourceId, $lock);
        return $children[0] ?? null;
    }

    public function recordedEvidence(ShopContext $context, array $row): array
    {
        $action = match ($row['movement_type']) {
            'REVERSAL' => 'financial_movement.reversed',
            'FINANCIAL_CORRECTION' => 'financial_movement.corrected',
            default => 'financial_movement.recorded',
        };
        $audits = $this->database->table('audit_events')->select('change_metadata,reason,correlation_id')
            ->where(['tenant_id' => $context->tenantId, 'shop_id' => $context->shopId,
                'resource_type' => 'financial_movement', 'resource_public_id' => $row['public_id'],
                'action_code' => $action])->orderBy('id')->get()->getResultArray();
        $audit = count($audits) === 1 ? $audits[0] : null;
        $metadata = $audit ? json_decode($audit['change_metadata'] ?? '', true, 512, JSON_THROW_ON_ERROR) : null;
        $valid = is_array($metadata)
            && is_string($metadata['command_fingerprint'] ?? null)
            && preg_match('/^[a-f0-9]{64}$/D', $metadata['command_fingerprint'])
            && ($metadata['movement_type'] ?? null) === $row['movement_type']
            && ($metadata['channel'] ?? null) === $row['channel']
            && ($metadata['amount_delta'] ?? null) === (string) $row['amount_delta']
            && $audit && ($audit['correlation_id'] ?? null) === ($row['correlation_id'] ?? null);
        if ($row['movement_type'] === 'REVERSAL') {
            $valid = $valid && is_string($metadata['source_public_id'] ?? null)
                && $metadata['source_public_id'] === ($row['reversal_of_public_id'] ?? null);
        } elseif ($row['movement_type'] === 'FINANCIAL_CORRECTION') {
            $valid = $valid && is_string($metadata['source_public_id'] ?? null)
                && $metadata['source_public_id'] === ($row['replacement_for_public_id'] ?? null);
        } else {
            $valid = $valid && $audit && $audit['reason'] === $row['description'];
        }
        if (!$valid || !$audit || !is_string($audit['reason']) || trim($audit['reason']) === '') {
            throw new ApiException('FINANCIAL_EVIDENCE_INVALID', 'This money record needs checking. Ask the shop owner for help.', 409);
        }
        $metadata['audit_reason'] = $audit['reason'];
        return $metadata;
    }
}
