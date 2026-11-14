<?php

declare(strict_types=1);

namespace App\Modules\FinancialMovement\Application;

use App\Exceptions\ApiException;
use App\Libraries\Http\RequestContext;
use App\Modules\Audit\Application\AuditService;
use App\Modules\Audit\Domain\{AuditActor, AuditAppendCommand, AuditCategory, AuditOccurrence};
use App\Modules\FinancialMovement\Domain\MovementAmount;
use App\Modules\FinancialMovement\Infrastructure\{FinancialMovementRepository, FinancialMovementSchema};
use App\Modules\Reconciliation\Application\CashControlGuard;
use App\Modules\Sales\Application\CommercialAccess;
use App\Modules\Sync\Application\ChangeSequenceService;
use App\Modules\Sync\Domain\ChangeCommand;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use App\Support\UtcTimestamp;
use CodeIgniter\Database\BaseConnection;

final class MovementRecordingService
{
    private readonly FinancialMovementRepository $repository;

    public function __construct(
        private readonly BaseConnection $database,
        private readonly CommercialAccess $access,
        private readonly AuditService $audit,
        private readonly ChangeSequenceService $changes,
        private readonly RequestContext $request,
    ) {
        $this->repository = new FinancialMovementRepository($database);
    }

    public function previewExpense(ShopContext $context, array $input): array
    {
        return $this->preview($context, $input, 'SHOP_EXPENSE');
    }

    public function postExpense(ShopContext $context, array $input): string
    {
        return $this->post($context, $input, 'SHOP_EXPENSE');
    }

    public function previewOwnerWithdrawal(ShopContext $context, array $input): array
    {
        return $this->preview($context, $input, 'OWNER_WITHDRAWAL');
    }

    public function postOwnerWithdrawal(ShopContext $context, array $input): string
    {
        return $this->post($context, $input, 'OWNER_WITHDRAWAL');
    }

    public function previewOwnerIntroduction(ShopContext $context, array $input): array
    {
        return $this->preview($context, $input, 'OWNER_INTRODUCTION');
    }

    public function postOwnerIntroduction(ShopContext $context, array $input): string
    {
        return $this->post($context, $input, 'OWNER_INTRODUCTION');
    }

    private function preview(ShopContext $context, array $input, string $type): array
    {
        $command = ExpenseInput::normalize($input);
        return $this->access->mutate($context, ['financial.view', 'financial.record'], function () use ($context, $command, $type): array {
            $this->requireActionAuthority($context, $type);
            (new FinancialMovementSchema($this->database))->requireReady();
            if ($this->replay($context, $command, $type)) {
                return ['already_saved' => true, 'public_id' => $command['public_id']];
            }
            $this->validateNew($context, $command);
            $delta = $this->delta($command, $type);
            return ['already_saved' => false, 'public_id' => $command['public_id'], 'command' => $command,
                'movement_type' => $type, 'amount_delta' => $delta,
                'physical_cash_effect' => $command['channel'] === 'CASH' ? $delta : '0.00',
                'review_fingerprint' => ExpenseInput::fingerprint($command, $type)];
        });
    }

    private function post(ShopContext $context, array $input, string $type): string
    {
        $command = ExpenseInput::normalize($input, true);
        return $this->access->mutate($context, ['financial.view', 'financial.record'], function () use ($context, $command, $input, $type): string {
            $this->requireActionAuthority($context, $type);
            (new FinancialMovementSchema($this->database))->requireReady();
            if ($this->replay($context, $command, $type)) {
                return $command['public_id'];
            }
            $this->validateNew($context, $command);
            if (!hash_equals(ExpenseInput::fingerprint($command, $type), $input['review_fingerprint'])) {
                throw new ApiException('FINANCIAL_REVIEW_STALE', 'The money record details changed. Review them again before saving.', 409);
            }
            $now = UtcTimestamp::now();
            $occurred = $command['occurred_at'] ?? $now;
            $delta = $this->delta($command, $type);
            (new FinancialMovementPostingParticipant($this->database))->append($context, [
                'public_id' => $command['public_id'], 'movement_type' => $type, 'channel' => $command['channel'],
                'amount_delta' => $delta, 'description' => $command['description'], 'external_reference' => $command['external_reference'],
                'reversal_of_movement_id' => null, 'replacement_for_movement_id' => null,
                'occurred_at' => $occurred, 'posted_at' => $now, 'created_at' => $now, 'correlation_id' => $this->request->requestId(),
            ]);
            $this->audit->append(new AuditAppendCommand(
                AuditActor::tenantUser($context->userId, $context->membershipId, $context->tenantId, $context->shopId),
                'financial_movement.recorded', AuditCategory::FINANCIAL, 'financial_movement', $command['public_id'], $command['description'],
                ['movement_type' => $type, 'channel' => $command['channel'], 'amount_delta' => $delta,
                    'command_fingerprint' => ExpenseInput::fingerprint($command, $type)],
                occurrence: $command['occurred_at'] === null ? AuditOccurrence::serverNow() : AuditOccurrence::supplied($occurred),
            ));
            // Generation last: no further domain reads or writes after publication.
            $this->changes->publish([new ChangeCommand($context->tenantId, $context->shopId, 'financial_movement', $command['public_id'])]);
            return $command['public_id'];
        });
    }

    /** Called inside CommercialAccess after its Tenant → Shop → active membership locks. */
    private function requireActionAuthority(ShopContext $context, string $type): void
    {
        if ($type === 'SHOP_EXPENSE') {
            return;
        }
        $owner = $this->database->query(
            "SELECT id FROM tenant_memberships WHERE tenant_id=? AND id=? AND user_id=? AND role='PRIMARY_OWNER' AND status='ACTIVE' FOR UPDATE",
            [$context->tenantId, $context->membershipId, $context->userId],
        )->getRowArray();
        if (!$owner) {
            throw new ApiException('PERMISSION_DENIED', 'Only the shop owner can record money taken out or added by the owner.', 403);
        }
    }

    private function delta(array $command, string $type): string
    {
        $amount = MovementAmount::fromInput($command['amount']);
        return $type === 'OWNER_INTRODUCTION' ? $amount->magnitude : $amount->expenseDelta();
    }

    private function validateNew(ShopContext $context, array $command): void
    {
        if ($command['occurred_at'] !== null) {
            AuditOccurrence::supplied($command['occurred_at']);
        }
        if ($command['channel'] === 'CASH') {
            (new CashControlGuard($this->database))->assertCanAffectCash($context);
        }
    }

    private function replay(ShopContext $context, array $command, string $type): bool
    {
        $row = $this->repository->movement($context, $command['public_id']);
        if (!$row) {
            return false;
        }
        $metadata = $this->repository->recordedEvidence($context, $row);
        if ($row['movement_type'] !== $type || $row['reversal_of_movement_id'] !== null || $row['replacement_for_movement_id'] !== null
            || $row['channel'] !== $command['channel'] || (string) $row['amount_delta'] !== $this->delta($command, $type)
            || $row['description'] !== $command['description'] || $row['external_reference'] !== $command['external_reference']
            || ($command['occurred_at'] !== null && $row['occurred_at'] !== $command['occurred_at'])
            || ($command['occurred_at'] === null && $row['occurred_at'] !== $row['posted_at'])
            || !hash_equals($metadata['command_fingerprint'], ExpenseInput::fingerprint($command, $type))) {
            throw new ApiException('FINANCIAL_IDENTITY_CONFLICT', 'This reference already belongs to different saved details. Check the saved record before starting another money record.', 409);
        }
        return true;
    }
}
