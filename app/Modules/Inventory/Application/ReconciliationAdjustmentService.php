<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

use App\Exceptions\ApiException;
use App\Libraries\Http\RequestContext;
use App\Modules\Audit\Application\AuditService;
use App\Modules\Audit\Domain\{AuditActor, AuditAppendCommand, AuditCategory};
use App\Modules\Reconciliation\Application\{CashControlInput, StockControlGuard};
use App\Modules\Reconciliation\Infrastructure\{ControlRepository, StockControlSchema, StockCountEvidence};
use App\Modules\Sales\Application\CommercialAccess;
use App\Modules\Sync\Application\ChangeSequenceService;
use App\Modules\Sync\Domain\ChangeCommand;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use App\Support\UtcTimestamp;
use CodeIgniter\Database\BaseConnection;

/** Inventory owns this one exact source-linked delta; never overwrite position with an old actual. */
final class ReconciliationAdjustmentService
{
    public function __construct(
        private readonly BaseConnection $database,
        private readonly CommercialAccess $access,
        private readonly AuditService $audit,
        private readonly ChangeSequenceService $changes,
        private readonly RequestContext $request,
        private readonly string $scope,
    ) {
        if (!in_array($scope, ['STOCK', 'FULL'], true)) {
            throw new \InvalidArgumentException('Stock adjustments require a server-bound Stock-containing scope.');
        }
    }

    public function preview(ShopContext $context, string $id, array $input): array
    {
        $command = $this->normalize($input, false);
        return $this->access->mutate($context, ['reconciliation.view', 'reconciliation.review', 'reconciliation.adjust'], function () use ($context, $id, $command): array {
            (new StockControlSchema($this->database))->requireReady();
            $saved = $this->saved($context, $id, $command);
            if ($saved) {
                return ['already_saved' => true, ...$saved];
            }
            return $this->review($context, $id, $command);
        });
    }

    public function post(ShopContext $context, string $id, array $input): array
    {
        $command = $this->normalize($input, true);
        return $this->access->mutate($context, ['reconciliation.view', 'reconciliation.review', 'reconciliation.adjust'], function () use ($context, $id, $command, $input): array {
            (new StockControlSchema($this->database))->requireReady();
            // Recover an accepted exact command before today's new-control/position eligibility checks.
            $saved = $this->saved($context, $id, $command);
            if ($saved) {
                return $saved;
            }
            $review = $this->review($context, $id, $command);
            if (!hash_equals($review['review_fingerprint'], (string) ($input['review_fingerprint'] ?? ''))) {
                throw new ApiException('STOCK_ADJUSTMENT_REVIEW_STALE', 'Stock has changed since you looked at these numbers. Load the stock change again before saving it.', 409);
            }
            $source = $this->source($context, $id, $command['variance_public_id']);
            $position = $this->position($context, (int) $source['product_id']);
            $projection = new InventoryProjection($this->database);
            $after = StockCountAmount::add((string) $position['expected_quantity'], (string) $source['quantity_variance']);
            $state = $projection->stateAfterNonAcquisition((string) $position['cost_basis_state'], $after);
            $now = UtcTimestamp::now();
            $this->database->table('stock_movements')->insert([
                'public_id' => $command['movement_public_id'], 'tenant_id' => $context->tenantId, 'shop_id' => $context->shopId,
                'product_id' => $source['product_id'], 'stock_position_id' => $position['id'],
                'movement_type' => 'RECONCILIATION_ADJUSTMENT', 'source_kind' => 'RECON_VARIANCE',
                'reconciliation_variance_id' => $source['id'], 'quantity_delta' => $source['quantity_variance'],
                'unit_cost_basis' => $position['average_unit_cost'], 'quantity_before' => $position['expected_quantity'], 'quantity_after' => $after,
                'average_cost_before' => $position['average_unit_cost'], 'average_cost_after' => $position['average_unit_cost'],
                'reason_code' => 'RECONCILIATION_VARIANCE', 'reason_text' => $command['reason'], 'occurred_at' => $now, 'posted_at' => $now,
                'created_by_user_id' => $context->userId, 'correlation_id' => $this->request->requestId(), 'created_at' => $now,
            ]);
            $movement = (int) $this->database->insertID();
            $this->database->table('stock_positions')->where('id', $position['id'])->update([
                'expected_quantity' => $after, 'cost_basis_state' => $state, 'last_movement_id' => $movement,
                'version' => new \CodeIgniter\Database\RawSql('version + 1'), 'updated_at' => $now,
            ]);
            $this->audit->append(new AuditAppendCommand(
                AuditActor::tenantUser($context->userId, $context->membershipId, $context->tenantId, $context->shopId),
                'reconciliation.stock_adjusted', AuditCategory::INVENTORY, 'stock_movement', $command['movement_public_id'], $command['reason'],
                ['control_public_id' => $id, 'variance_public_id' => $command['variance_public_id'], 'product_public_id' => $source['product_public_id'],
                    'quantity_delta' => $source['quantity_variance'], 'quantity_before' => $position['expected_quantity'], 'quantity_after' => $after,
                    'cost_basis_state_before' => $position['cost_basis_state'], 'cost_basis_state_after' => $state],
            ));
            // Existing count/variance/session are deliberately not updated.
            $this->changes->publish([
                new ChangeCommand($context->tenantId, $context->shopId, 'stock_movement', $command['movement_public_id']),
                new ChangeCommand($context->tenantId, $context->shopId, 'stock_position', $source['product_public_id']),
                new ChangeCommand($context->tenantId, $context->shopId, 'reconciliation_session', $id),
            ]);
            return ['public_id' => $command['movement_public_id'], 'variance_public_id' => $command['variance_public_id'], 'control_public_id' => $id];
        });
    }

    private function normalize(array $input, bool $posting): array
    {
        CashControlInput::only($input, $posting ? ['movement_public_id', 'variance_public_id', 'reason', 'review_fingerprint'] : ['movement_public_id', 'variance_public_id', 'reason']);
        return ['movement_public_id' => CashControlInput::uuid($input['movement_public_id'] ?? null),
            'variance_public_id' => CashControlInput::uuid($input['variance_public_id'] ?? null),
            'reason' => CashControlInput::text($input['reason'] ?? null, 500)];
    }

    private function saved(ShopContext $context, string $id, array $command): ?array
    {
        $row = $this->database->query('SELECT m.*,v.public_id variance_public_id,s.public_id control_public_id,s.scope FROM stock_movements m LEFT JOIN reconciliation_variances v ON v.tenant_id=m.tenant_id AND v.shop_id=m.shop_id AND v.id=m.reconciliation_variance_id LEFT JOIN reconciliation_sessions s ON s.tenant_id=v.tenant_id AND s.shop_id=v.shop_id AND s.id=v.reconciliation_session_id WHERE m.tenant_id=? AND m.shop_id=? AND m.public_id=? FOR UPDATE',
            [$context->tenantId, $context->shopId, $command['movement_public_id']])->getRowArray();
        if (!$row) {
            return null;
        }
        if ($row['source_kind'] !== 'RECON_VARIANCE' || $row['movement_type'] !== 'RECONCILIATION_ADJUSTMENT'
            || $row['variance_public_id'] !== $command['variance_public_id'] || $row['control_public_id'] !== $id || $row['scope'] !== $this->scope
            || $row['reason_text'] !== $command['reason']) {
            throw new ApiException('STOCK_ADJUSTMENT_IDENTITY_CONFLICT', 'This stock change number is already saved with different details. Open the saved record before trying again.', 409);
        }
        return ['public_id' => $row['public_id'], 'variance_public_id' => $row['variance_public_id'], 'control_public_id' => $id];
    }

    private function review(ShopContext $context, string $id, array $command): array
    {
        (new StockControlGuard($this->database))->assertCanAffectStock($context);
        $source = $this->source($context, $id, $command['variance_public_id']);
        $latest = $this->database->query("SELECT id FROM reconciliation_sessions WHERE tenant_id=? AND shop_id=? AND scope IN ('STOCK','FULL') AND status='FINALIZED' ORDER BY cutoff_at DESC,id DESC LIMIT 1 FOR UPDATE",
            [$context->tenantId, $context->shopId])->getRowArray();
        if (!$latest || (int) $latest['id'] !== (int) $source['reconciliation_session_id']) {
            throw new ApiException('STOCK_ADJUSTMENT_SUPERSEDED', 'A newer stock check has been finished. Use that check if you need to change stock. This older check will be kept.', 409);
        }
        $existing = $this->database->query('SELECT id FROM stock_movements WHERE tenant_id=? AND shop_id=? AND reconciliation_variance_id=? FOR UPDATE',
            [$context->tenantId, $context->shopId, $source['id']])->getRowArray();
        if ($existing) {
            throw new ApiException('STOCK_VARIANCE_ALREADY_ADJUSTED', 'Stock has already been changed for this difference. Open that saved change. Do not save it again with another number.', 409);
        }
        $position = $this->position($context, (int) $source['product_id']);
        $after = StockCountAmount::add((string) $position['expected_quantity'], (string) $source['quantity_variance']);
        $review = ['already_saved' => false, 'control_public_id' => $id, 'variance_public_id' => $source['public_id'],
            'movement_public_id' => $command['movement_public_id'], 'product_public_id' => $source['product_public_id'],
            'frozen_expected_quantity' => $source['expected_quantity'], 'frozen_actual_quantity' => $source['actual_quantity'],
            'quantity_delta' => $source['quantity_variance'], 'current_quantity' => $position['expected_quantity'], 'quantity_after' => $after,
            'average_unit_cost' => $position['average_unit_cost'], 'cost_basis_state' => $position['cost_basis_state'], 'reason' => $command['reason']];
        $review['review_fingerprint'] = hash('sha256', json_encode([$review, (string) $position['version'], $position['last_movement_id']], JSON_THROW_ON_ERROR));
        return $review;
    }

    private function source(ShopContext $context, string $id, string $variance): array
    {
        $repo = new ControlRepository($this->database, $this->scope);
        $control = $repo->control($context, $id, true);
        if ($control['status'] !== 'FINALIZED') {
            throw new ApiException('STOCK_ADJUSTMENT_NOT_FINAL', 'Finish the stock check first. You can change stock only when the count and the shop records differ.', 409);
        }
        $source = $this->database->query("SELECT v.*,c.product_id,c.expected_quantity,c.actual_quantity,p.public_id product_public_id FROM reconciliation_variances v JOIN reconciliation_stock_counts c ON c.tenant_id=v.tenant_id AND c.shop_id=v.shop_id AND c.reconciliation_session_id=v.reconciliation_session_id AND c.id=v.stock_count_id JOIN products p ON p.tenant_id=c.tenant_id AND p.id=c.product_id WHERE v.tenant_id=? AND v.shop_id=? AND v.reconciliation_session_id=? AND v.public_id=? AND v.variance_kind='STOCK' FOR UPDATE",
            [$context->tenantId, $context->shopId, $control['id'], $variance])->getRowArray();
        if (!$source || $source['quantity_variance'] === '0.0000'
            || StockCountAmount::difference((string) $source['actual_quantity'], (string) $source['expected_quantity']) !== (string) $source['quantity_variance']) {
            throw new ApiException('STOCK_VARIANCE_NOT_FOUND', 'We could not use this saved stock difference. Keep the record and ask the shop owner for help.', 409);
        }
        // Frozen membership/cost metadata must still agree with the original immutable count rows.
        (new StockCountEvidence($this->database))->rows($context, $control, true);
        return $source;
    }

    private function position(ShopContext $context, int $product): array
    {
        $this->database->query('SELECT id FROM products WHERE tenant_id=? AND id=? FOR UPDATE', [$context->tenantId, $product]);
        $row = $this->database->query('SELECT * FROM stock_positions WHERE tenant_id=? AND shop_id=? AND product_id=? FOR UPDATE',
            [$context->tenantId, $context->shopId, $product])->getRowArray();
        if (!$row) {
            throw new ApiException('INVENTORY_STATE_CONFLICT', 'We could not load the stock record. Try loading it again.', 409);
        }
        return $row;
    }
}
