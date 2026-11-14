<?php

declare(strict_types=1);

namespace App\Modules\FinancialMovement\Application;

use App\Exceptions\ApiException;
use App\Libraries\Http\RequestContext;
use App\Modules\Audit\Application\AuditService;
use App\Modules\Audit\Domain\{AuditActor, AuditAppendCommand, AuditCategory, AuditOccurrence};
use App\Modules\FinancialMovement\Infrastructure\{FinancialMovementRepository, FinancialMovementSchema};
use App\Modules\Reconciliation\Application\CashControlGuard;
use App\Modules\Sales\Application\CommercialAccess;
use App\Modules\Sync\Application\ChangeSequenceService;
use App\Modules\Sync\Domain\ChangeCommand;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use App\Support\UtcTimestamp;
use CodeIgniter\Database\BaseConnection;

final class FinancialMovementCorrectionService
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

    public function previewReverse(ShopContext $context, string $source, array $input): array
    {
        return $this->execute($context, $source, 'reverse', $input, false);
    }

    public function reverse(ShopContext $context, string $source, array $input): array
    {
        return $this->execute($context, $source, 'reverse', $input, true);
    }

    public function previewCorrection(ShopContext $context, string $source, array $input): array
    {
        return $this->execute($context, $source, 'correct', $input, false);
    }

    public function correct(ShopContext $context, string $source, array $input): array
    {
        return $this->execute($context, $source, 'correct', $input, true);
    }

    private function execute(ShopContext $context, string $source, string $kind, array $input, bool $post): array
    {
        $command = FinancialMovementCorrectionInput::normalize($input, $kind, $source, $post);
        $permissions = ['financial.view', 'financial.reverse'];
        if ($kind === 'correct') {
            $permissions[] = 'financial.correct';
        }
        return $this->access->mutate($context, $permissions, function () use ($context, $command, $input, $post): array {
            (new FinancialMovementSchema($this->database))->requireReady();
            if ($this->replay($context, $command)) {
                return ['already_saved' => true, 'public_id' => $command['public_id'],
                    'replacement_public_id' => $command['replacement_public_id'] ?? null];
            }
            $review = $this->prepare($context, $command);
            $fingerprint = FinancialMovementCorrectionInput::reviewFingerprint($command, $review['_source_state']);
            if (!$post) {
                unset($review['_source'], $review['_source_state']);
                return $review + ['already_saved' => false, 'review_fingerprint' => $fingerprint];
            }
            if (!hash_equals($fingerprint, $input['review_fingerprint'])) {
                throw new ApiException('FINANCIAL_REVIEW_STALE', 'This money record changed. Review the undo or correction again before saving.', 409);
            }
            $ids = $this->append($context, $command, $review, $fingerprint);
            return ['already_saved' => false, 'public_id' => $ids['inverse_public_id'],
                'source_public_id' => $command['source_public_id'],
                'replacement_public_id' => $ids['replacement_public_id']];
        });
    }

    private function prepare(ShopContext $context, array $command): array
    {
        $pre = $this->repository->movement($context, $command['source_public_id']);
        if (!$pre) {
            throw new ApiException('FINANCIAL_MOVEMENT_NOT_FOUND', 'The original money record was not found in this shop.', 404);
        }
        if (!in_array($pre['movement_type'], ['SHOP_EXPENSE', 'OWNER_WITHDRAWAL', 'OWNER_INTRODUCTION', 'FINANCIAL_CORRECTION'], true)
            || $pre['reversal_of_movement_id'] !== null) {
            throw new ApiException('FINANCIAL_MOVEMENT_NOT_REVERSIBLE', 'This record cannot be undone here. Open the original money record and its linked history.', 409);
        }
        if ($command['kind'] === 'correct' && $command['occurred_at'] !== null) {
            AuditOccurrence::supplied($command['occurred_at']);
        }
        $needsCashGuard = $pre['channel'] === 'CASH'
            || ($command['kind'] === 'correct' && $command['channel'] === 'CASH');
        if ($needsCashGuard) {
            (new CashControlGuard($this->database))->assertCanAffectCash($context);
        }

        // Cash eligibility is checked before movement/causal rows are locked. The Shop lock serializes writers.
        $source = $this->repository->movementForUpdate($context, $command['source_public_id']);
        if (!$source || $source['id'] !== $pre['id'] || $source['channel'] !== $pre['channel']
            || (string) $source['amount_delta'] !== (string) $pre['amount_delta']
            || $source['movement_type'] !== $pre['movement_type']
            || $source['description'] !== $pre['description']
            || $source['external_reference'] !== $pre['external_reference']
            || $source['occurred_at'] !== $pre['occurred_at']
            || $source['posted_at'] !== $pre['posted_at']
            || $source['reversal_of_movement_id'] !== $pre['reversal_of_movement_id']
            || $source['replacement_for_movement_id'] !== $pre['replacement_for_movement_id']) {
            throw new ApiException('FINANCIAL_REVIEW_STALE', 'This money record changed. Review the undo or correction again.', 409);
        }
        $sourceEvidence = $this->repository->recordedEvidence($context, $source);
        $this->validateAncestors($context, $source);

        $inverse = $this->repository->reversalChild($context, (int) $source['id'], true);
        $replacementChildren = $this->repository->replacementChildren($context, (int) $source['id'], true);
        if (count($replacementChildren) > 1) {
            throw new ApiException('FINANCIAL_EVIDENCE_INVALID', 'This record has more than one corrected replacement. Ask the shop owner to investigate.', 409);
        }
        $replacement = $replacementChildren[0] ?? null;
        if ($inverse) {
            throw new ApiException('FINANCIAL_MOVEMENT_ALREADY_SUPERSEDED', 'This record has already been undone or replaced. Open its linked correction history.', 409);
        }
        if ($replacement) {
            throw new ApiException('FINANCIAL_EVIDENCE_INVALID', 'A replacement exists without its required undo record. Ask the shop owner to investigate.', 409);
        }

        $replacementDelta = $command['kind'] === 'correct' ? $command['amount_delta'] : null;

        $inverseDelta = $this->inverse((string) $source['amount_delta']);
        $sourceState = [
            'public_id' => $source['public_id'],
            'movement_type' => $source['movement_type'],
            'channel' => $source['channel'],
            'amount_delta' => (string) $source['amount_delta'],
            'description' => $source['description'],
            'external_reference' => $source['external_reference'],
            'occurred_at' => $source['occurred_at'],
            'posted_at' => $source['posted_at'],
            'correlation_id' => $source['correlation_id'],
            'command_fingerprint' => $sourceEvidence['command_fingerprint'],
            'inverse_public_id' => null,
            'replacement_public_id' => null,
        ];
        $review = [
            'source_public_id' => $source['public_id'],
            'source_type' => $source['movement_type'],
            'source_channel' => $source['channel'],
            'source_amount_delta' => (string) $source['amount_delta'],
            'source_description' => $source['description'],
            'source_posted_at' => $source['posted_at'],
            'inverse_public_id' => $command['public_id'],
            'inverse_amount_delta' => $inverseDelta,
            'inverse_channel' => $source['channel'],
            'physical_cash_effect' => $source['channel'] === 'CASH' ? $inverseDelta : '0.00',
            'replacement_public_id' => $command['replacement_public_id'] ?? null,
            'replacement_amount_delta' => $replacementDelta,
            'replacement_channel' => $command['channel'] ?? null,
            'replacement_description' => $command['description'] ?? null,
            'reason' => $command['reason'],
            '_source' => $source,
            '_source_state' => $sourceState,
        ];
        if ($command['kind'] === 'correct') {
            $review['replacement_physical_cash_effect'] = $command['channel'] === 'CASH' ? $replacementDelta : '0.00';
            $review['physical_cash_effect'] = $source['channel'] === 'CASH' ? $inverseDelta : '0.00';
            $sourceState['replacement'] = [
                'public_id' => $command['replacement_public_id'],
                'channel' => $command['channel'],
                'amount_delta' => $replacementDelta,
                'description' => $command['description'],
                'external_reference' => $command['external_reference'],
                'occurred_at' => $command['occurred_at'],
            ];
            $review['_source_state'] = $sourceState;
        }
        return $review;
    }

    private function validateAncestors(ShopContext $context, array $source): void
    {
        $cursor = $source;
        $seen = [(int) $source['id'] => true];
        $depth = 0;
        while ($cursor['replacement_for_movement_id'] !== null) {
            if ($cursor['movement_type'] !== 'FINANCIAL_CORRECTION') {
                throw new ApiException('FINANCIAL_EVIDENCE_INVALID', 'Only a corrected replacement can continue a correction history.', 409);
            }
            if (++$depth > 100) {
                throw new ApiException('FINANCIAL_EVIDENCE_INVALID', 'The correction history is too long to verify safely.', 409);
            }
            $parentId = (int) $cursor['replacement_for_movement_id'];
            if (isset($seen[$parentId])) {
                throw new ApiException('FINANCIAL_EVIDENCE_INVALID', 'The correction history contains a cycle.', 409);
            }
            $seen[$parentId] = true;
            $parent = $this->repository->movementById($context, $parentId, true);
            if (!$parent || $parent['movement_type'] === 'REVERSAL') {
                throw new ApiException('FINANCIAL_EVIDENCE_INVALID', 'The correction history has a missing or invalid original record.', 409);
            }
            $parentInverse = $this->repository->reversalChild($context, $parentId, true);
            $children = $this->repository->replacementChildren($context, $parentId, true);
            if (!$parentInverse || $parentInverse['movement_type'] !== 'REVERSAL'
                || $parentInverse['amount_delta'] !== $this->inverse((string) $parent['amount_delta'])
                || $parentInverse['channel'] !== $parent['channel']
                || count($children) !== 1 || (int) $children[0]['id'] !== (int) $cursor['id']) {
                throw new ApiException('FINANCIAL_EVIDENCE_INVALID', 'The correction history is incomplete or has more than one replacement.', 409);
            }
            $this->repository->recordedEvidence($context, $parent);
            $parentInverseEvidence = $this->repository->recordedEvidence($context, $parentInverse);
            $cursorEvidence = $this->repository->recordedEvidence($context, $cursor);
            if (($parentInverseEvidence['counterpart_public_id'] ?? null) !== $cursor['public_id']
                || ($cursorEvidence['counterpart_public_id'] ?? null) !== $parentInverse['public_id']) {
                throw new ApiException('FINANCIAL_EVIDENCE_INVALID', 'The correction history links do not agree.', 409);
            }
            $cursor = $parent;
        }
    }

    private function replay(ShopContext $context, array $command): bool
    {
        $inverse = $this->repository->movement($context, $command['public_id']);
        $replacement = $command['kind'] === 'correct'
            ? $this->repository->movement($context, $command['replacement_public_id'])
            : null;
        if (!$inverse && !$replacement) {
            return false;
        }
        if ($command['kind'] === 'correct' && (!$inverse || !$replacement)) {
            throw new ApiException('FINANCIAL_IDENTITY_CONFLICT', 'One of these references already belongs to another saved record. Check both references before continuing.', 409);
        }
        if (!$inverse || $inverse['movement_type'] !== 'REVERSAL'
            || $inverse['reversal_of_public_id'] !== $command['source_public_id']) {
            throw new ApiException('FINANCIAL_IDENTITY_CONFLICT', 'This reference already belongs to another money record. Check saved status before continuing.', 409);
        }
        $source = $this->repository->movement($context, $command['source_public_id']);
        if (!$source || !in_array($source['movement_type'], ['SHOP_EXPENSE', 'OWNER_WITHDRAWAL', 'OWNER_INTRODUCTION', 'FINANCIAL_CORRECTION'], true)
            || $source['reversal_of_movement_id'] !== null
            || $inverse['channel'] !== $source['channel']
            || (string) $inverse['amount_delta'] !== $this->inverse((string) $source['amount_delta'])) {
            throw new ApiException('FINANCIAL_EVIDENCE_INVALID', 'The saved undo does not exactly match its original record.', 409);
        }
        $this->repository->recordedEvidence($context, $source);
        $this->validateAncestors($context, $source);
        $sourceInverse = $this->repository->reversalChild($context, (int) $source['id']);
        if (!$sourceInverse || $sourceInverse['public_id'] !== $inverse['public_id']) {
            throw new ApiException('FINANCIAL_EVIDENCE_INVALID', 'The saved undo is not the original record’s linked inverse.', 409);
        }
        $inverseEvidence = $this->repository->recordedEvidence($context, $inverse);
        $fingerprint = FinancialMovementCorrectionInput::commandFingerprint($command);
        if (!hash_equals((string) ($inverseEvidence['command_fingerprint'] ?? ''), $fingerprint)) {
            throw new ApiException('FINANCIAL_IDENTITY_CONFLICT', 'This reference already belongs to different undo or correction details.', 409);
        }
        if ($command['kind'] === 'reverse') {
            if (($inverseEvidence['counterpart_public_id'] ?? null) !== null
                || $this->repository->replacementChildren($context, (int) $source['id'])) {
                throw new ApiException('FINANCIAL_EVIDENCE_INVALID', 'The saved undo has an unexpected replacement link.', 409);
            }
            return true;
        }
        $children = $this->repository->replacementChildren($context, (int) $source['id']);
        if (count($children) !== 1 || $children[0]['public_id'] !== $replacement['public_id']
            || $replacement['movement_type'] !== 'FINANCIAL_CORRECTION'
            || $replacement['replacement_for_public_id'] !== $command['source_public_id']
            || $replacement['channel'] !== $command['channel']
            || (string) $replacement['amount_delta'] !== $command['amount_delta']
            || $replacement['description'] !== $command['description']
            || $replacement['external_reference'] !== $command['external_reference']
            || $replacement['occurred_at'] !== ($command['occurred_at'] ?? $replacement['posted_at'])
            || $replacement['correlation_id'] !== $inverse['correlation_id']
            || $replacement['posted_at'] !== $inverse['posted_at']) {
            throw new ApiException('FINANCIAL_EVIDENCE_INVALID', 'The saved correction pair does not match. Ask the shop owner to investigate.', 409);
        }
        $replacementEvidence = $this->repository->recordedEvidence($context, $replacement);
        if (!hash_equals((string) ($replacementEvidence['command_fingerprint'] ?? ''), $fingerprint)) {
            throw new ApiException('FINANCIAL_EVIDENCE_INVALID', 'The saved correction pair does not match. Ask the shop owner to investigate.', 409);
        }
        if (($inverseEvidence['counterpart_public_id'] ?? null) !== $replacement['public_id']
            || ($replacementEvidence['counterpart_public_id'] ?? null) !== $inverse['public_id']) {
            throw new ApiException('FINANCIAL_EVIDENCE_INVALID', 'The saved correction links do not agree. Ask the shop owner to investigate.', 409);
        }
        return true;
    }

    private function append(ShopContext $context, array $command, array $review, string $reviewFingerprint): array
    {
        $source = $review['_source'];
        $now = UtcTimestamp::now();
        $correlation = $this->request->requestId();
        $commandFingerprint = FinancialMovementCorrectionInput::commandFingerprint($command);
        $inverseDelta = $review['inverse_amount_delta'];
        $replacementId = $command['replacement_public_id'] ?? null;
        (new FinancialMovementPostingParticipant($this->database))->append($context, [
            'public_id' => $command['public_id'],
            'movement_type' => 'REVERSAL',
            'channel' => $source['channel'],
            'amount_delta' => $inverseDelta,
            'description' => 'Undo of ' . strtolower(str_replace('_', ' ', $source['movement_type'])),
            'external_reference' => null,
            'reversal_of_movement_id' => (int) $source['id'],
            'replacement_for_movement_id' => null,
            'occurred_at' => $now,
            'posted_at' => $now,
            'created_at' => $now,
            'correlation_id' => $correlation,
        ]);
        $actor = AuditActor::tenantUser($context->userId, $context->membershipId, $context->tenantId, $context->shopId);
        $this->audit->append(new AuditAppendCommand(
            $actor, 'financial_movement.reversed', AuditCategory::FINANCIAL, 'financial_movement', $command['public_id'], $command['reason'],
            ['movement_type' => 'REVERSAL', 'channel' => $source['channel'], 'amount_delta' => $inverseDelta,
                'source_public_id' => $source['public_id'], 'counterpart_public_id' => $replacementId,
                'command_fingerprint' => $commandFingerprint, 'review_fingerprint' => $reviewFingerprint],
            occurrence: AuditOccurrence::serverNow(),
        ));

        if ($command['kind'] === 'correct') {
            $replacementOccurred = $command['occurred_at'] ?? $now;
            (new FinancialMovementPostingParticipant($this->database))->append($context, [
                'public_id' => $replacementId,
                'movement_type' => 'FINANCIAL_CORRECTION',
                'channel' => $command['channel'],
                'amount_delta' => $command['amount_delta'],
                'description' => $command['description'],
                'external_reference' => $command['external_reference'],
                'reversal_of_movement_id' => null,
                'replacement_for_movement_id' => (int) $source['id'],
                'occurred_at' => $replacementOccurred,
                'posted_at' => $now,
                'created_at' => $now,
                'correlation_id' => $correlation,
            ]);
            $this->audit->append(new AuditAppendCommand(
                $actor, 'financial_movement.corrected', AuditCategory::FINANCIAL, 'financial_movement', $replacementId, $command['reason'],
                ['movement_type' => 'FINANCIAL_CORRECTION', 'channel' => $command['channel'], 'amount_delta' => $command['amount_delta'],
                    'source_public_id' => $source['public_id'], 'counterpart_public_id' => $command['public_id'],
                    'command_fingerprint' => $commandFingerprint, 'review_fingerprint' => $reviewFingerprint],
                occurrence: $command['occurred_at'] === null ? AuditOccurrence::serverNow() : AuditOccurrence::supplied($replacementOccurred),
            ));
        }

        $commands = [new ChangeCommand($context->tenantId, $context->shopId, 'financial_movement', $command['public_id'])];
        if ($replacementId !== null) {
            $commands[] = new ChangeCommand($context->tenantId, $context->shopId, 'financial_movement', $replacementId);
        }
        // Publish generation last, after both immutable rows and both Audit events.
        $this->changes->publish($commands);
        return ['inverse_public_id' => $command['public_id'], 'replacement_public_id' => $replacementId];
    }

    private function inverse(string $delta): string
    {
        return str_starts_with($delta, '-') ? substr($delta, 1) : '-' . $delta;
    }

}
