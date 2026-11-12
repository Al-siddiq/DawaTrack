<?php

declare(strict_types=1);

namespace App\Modules\CustomerDebt\Application;

use App\Exceptions\ApiException;
use App\Modules\Reconciliation\Application\CashAmount;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use App\Support\{SensitiveReason, Uuid};
use CodeIgniter\Database\BaseConnection;

/** Internal append participant: caller owns authorization, locks, balance/dependency checks, Audit, changes and commit. */
final class ReceivableAdjustmentParticipant
{
    public function __construct(private readonly BaseConnection $database) {}

    public function append(
        ShopContext $context,
        array $lockedCredit,
        ?int $sale,
        string $type,
        string $delta,
        string $reason,
        string $occurred,
        string $now,
        string $correlation,
        ?int $reversal = null,
        ?string $publicId = null,
    ): ?string {
        // Preserve the accepted commercial participant's zero-effect no-op.
        if ($delta === '0.00' || $delta === '-0.00') {
            return null;
        }
        if ($this->database->transDepth < 1) {
            throw new \LogicException('Receivable adjustment requires the owning transaction.');
        }
        if ((int) ($lockedCredit['tenant_id'] ?? 0) !== $context->tenantId
            || (int) ($lockedCredit['shop_id'] ?? 0) !== $context->shopId
            || (int) ($lockedCredit['id'] ?? 0) < 1) {
            throw new \LogicException('The caller must supply the same-scope locked Receivable.');
        }
        $negative = str_starts_with($delta, '-');
        $amount = CashAmount::parse($negative ? substr($delta, 1) : $delta);
        if ($amount === '0.00'
            || !in_array($type, ['RETURN_REDUCTION', 'CORRECTION_INCREASE', 'CORRECTION_DECREASE', 'REVERSAL'], true)
            || (in_array($type, ['RETURN_REDUCTION', 'CORRECTION_DECREASE'], true) && !$negative)
            || ($type === 'CORRECTION_INCREASE' && $negative)
            || ($type === 'RETURN_REDUCTION' && $sale === null)
            || (($type === 'REVERSAL') !== ($reversal !== null))) {
            throw new ApiException('VALIDATION_FAILED', 'The bill correction does not match its source.', 422);
        }
        $id = $publicId ?? Uuid::v4();
        $this->database->table('receivable_adjustments')->insert([
            'public_id' => $id, 'tenant_id' => $context->tenantId, 'shop_id' => $context->shopId,
            'receivable_id' => $lockedCredit['id'], 'sale_id' => $sale,
            'adjustment_type' => $type, 'amount_delta' => ($negative ? '-' : '') . $amount,
            'reason' => SensitiveReason::require($reason), 'reversal_of_adjustment_id' => $reversal,
            'occurred_at' => $occurred, 'posted_at' => $now, 'created_by_user_id' => $context->userId,
            'correlation_id' => $correlation, 'created_at' => $now,
        ]);
        return $id;
    }
}
