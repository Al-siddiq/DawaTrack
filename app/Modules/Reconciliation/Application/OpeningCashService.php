<?php

declare(strict_types=1);
namespace App\Modules\Reconciliation\Application;

use App\Exceptions\ApiException;
use App\Modules\Audit\Application\AuditService;
use App\Modules\Audit\Domain\{AuditActor, AuditAppendCommand, AuditCategory};
use App\Modules\Sync\Application\ChangeSequenceService;
use App\Modules\Sync\Domain\ChangeCommand;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use App\Support\{SensitiveReason, UtcTimestamp, Uuid};
use CodeIgniter\Database\BaseConnection;
use Throwable;

/** Online Owner-only OPENING/CASH workflow. All commands own one complete transaction. */
final class OpeningCashService
{
    private readonly CashControlGuard $guard;
    public function __construct(
        private readonly BaseConnection $database,
        private readonly AuditService $audit,
        private readonly ChangeSequenceService $changes,
    ) {
        $this->guard = new CashControlGuard($database);
    }

    public function start(ShopContext $context, string $publicId): void
    {
        if (!Uuid::isV4($publicId)) {
            throw new ApiException(
                "VALIDATION_FAILED",
                "Use a permanent UUIDv4 for this Opening Cash attempt.",
                422,
            );
        }
        $this->transaction($context, function () use ($context, $publicId) {
            $existing = $this->locked($context, $publicId, false);
            if ($existing) {
                return;
            } // Exact UUID retry never creates another attempt.
            $this->assertFirstCash($context);
            $active = $this->database
                ->query(
                    "SELECT id FROM reconciliation_sessions WHERE tenant_id=? AND shop_id=? AND scope IN ('CASH','FULL') AND status IN ('COUNTING','COUNTS_COMMITTED','REVIEW') FOR UPDATE",
                    [$context->tenantId, $context->shopId],
                )
                ->getRowArray();
            if ($active) {
                throw new ApiException(
                    "CASH_CONTROL_ACTIVE",
                    "Continue or abort the active Opening Cash attempt.",
                    409,
                );
            }
            $generation = $this->database
                ->table("sync_generations")
                ->where("status", "ACTIVE")
                ->get()
                ->getRowArray();
            if (!$generation) {
                throw new ApiException(
                    "SYNC_GENERATION_UNAVAILABLE",
                    "The server generation is unavailable.",
                    503,
                );
            }
            $now = UtcTimestamp::now();
            $this->database
                ->table("reconciliation_sessions")
                ->insert([
                    "public_id" => $publicId,
                    "tenant_id" => $context->tenantId,
                    "shop_id" => $context->shopId,
                    "scope" => "CASH",
                    "control_kind" => "OPENING",
                    "status" => "COUNTING",
                    "cutoff_at" => $now,
                    "cutoff_change_sequence" => 0,
                    "sync_generation_id" => $generation["id"],
                    "started_by_user_id" => $context->userId,
                    "started_at" => $now,
                    "created_at" => $now,
                    "updated_at" => $now,
                ]);
            $id = (int) $this->database->insertID();
            $this->database
                ->table("reconciliation_financial_counts")
                ->insert([
                    "tenant_id" => $context->tenantId,
                    "shop_id" => $context->shopId,
                    "reconciliation_session_id" => $id,
                    "channel" => "CASH",
                    "opening_baseline_amount" => "0.00",
                    "expected_amount" => "0.00",
                    "created_at" => $now,
                    "updated_at" => $now,
                ]);
            $this->record(
                $context,
                $publicId,
                "reconciliation.opening_started",
                "Opening Cash control started.",
            );
            $this->bindWatermark($id, false);
            $this->publish($context, $publicId);
        });
    }

    public function count(
        ShopContext $context,
        string $publicId,
        mixed $amount,
    ): void {
        $amount = CashAmount::parse($amount);
        $this->transaction($context, function () use (
            $context,
            $publicId,
            $amount,
        ) {
            $session = $this->locked($context, $publicId);
            $this->requireStatus($session, "COUNTING");
            $count = $this->countRow($context, (int) $session["id"]);
            if ($count["actual_amount"] === $amount) {
                throw new ApiException(
                    "OPENING_UNCHANGED",
                    "The Cash amount has not changed.",
                    409,
                );
            }
            $now = UtcTimestamp::now();
            $this->database
                ->table("reconciliation_financial_counts")
                ->where("id", $count["id"])
                ->update([
                    "opening_baseline_amount" => $amount,
                    "expected_amount" => $amount,
                    "actual_amount" => $amount,
                    "variance_amount" => "0.00",
                    "counted_by_user_id" => $context->userId,
                    "counted_at" => $now,
                    "updated_at" => $now,
                ]);
            $this->record(
                $context,
                $publicId,
                "reconciliation.opening_counted",
                "Physical Cash amount entered.",
                ["amount" => $amount],
            );
            $this->publish($context, $publicId);
        });
    }

    public function commitCount(ShopContext $context, string $publicId): void
    {
        $this->transaction($context, function () use ($context, $publicId) {
            $session = $this->locked($context, $publicId);
            $this->requireStatus($session, "COUNTING");
            $count = $this->countRow($context, (int) $session["id"]);
            if ($count["actual_amount"] === null) {
                throw new ApiException(
                    "OPENING_COUNT_REQUIRED",
                    "Enter the physical Cash amount before confirming.",
                    422,
                );
            }
            $now = UtcTimestamp::now();
            $this->database
                ->table("reconciliation_financial_counts")
                ->where("id", $count["id"])
                ->update(["committed_at" => $now, "updated_at" => $now]);
            $this->database
                ->table("reconciliation_sessions")
                ->where("id", $session["id"])
                ->update([
                    "status" => "COUNTS_COMMITTED",
                    "counts_committed_at" => $now,
                    "counts_committed_by_user_id" => $context->userId,
                    "updated_at" => $now,
                ]);
            $this->record(
                $context,
                $publicId,
                "reconciliation.opening_committed",
                "Opening Cash amount confirmed.",
                ["amount" => $count["actual_amount"]],
            );
            $this->bindWatermark((int) $session["id"], true, $now);
            $this->publish($context, $publicId);
        });
    }

    public function review(ShopContext $context, string $publicId): void
    {
        $this->transaction($context, function () use ($context, $publicId) {
            $session = $this->locked($context, $publicId);
            $this->requireStatus($session, "COUNTS_COMMITTED");
            $this->countRow($context, (int) $session["id"]);
            $this->database
                ->table("reconciliation_sessions")
                ->where("id", $session["id"])
                ->update([
                    "status" => "REVIEW",
                    "updated_at" => UtcTimestamp::now(),
                ]);
            $this->record(
                $context,
                $publicId,
                "reconciliation.opening_reviewed",
                "Opening Cash confirmation reviewed.",
            );
            $this->assertGeneration($session);
            $this->publish($context, $publicId);
        });
    }

    public function finalize(ShopContext $context, string $publicId): void
    {
        $this->transaction($context, function () use ($context, $publicId) {
            $session = $this->locked($context, $publicId);
            if ($session["status"] === "FINALIZED") {
                return;
            }
            $this->requireStatus($session, "REVIEW");
            $this->assertFirstCash($context);
            $count = $this->countRow($context, (int) $session["id"]);
            $this->assertOpeningCount($count);
            $now = UtcTimestamp::now();
            $this->database
                ->table("reconciliation_sessions")
                ->where("id", $session["id"])
                ->update([
                    "status" => "FINALIZED",
                    "finalized_by_user_id" => $context->userId,
                    "finalized_at" => $now,
                    "updated_at" => $now,
                ]);
            $this->record(
                $context,
                $publicId,
                "reconciliation.opening_finalized",
                "Opening Cash baseline finalized.",
                [
                    "amount" => $count["actual_amount"],
                    "cutoff_at" => $session["cutoff_at"],
                    "cutoff_change_sequence" =>
                        (string) $session["cutoff_change_sequence"],
                ],
            );
            $this->assertGeneration($session);
            $this->publish($context, $publicId);
        });
    }

    public function abort(
        ShopContext $context,
        string $publicId,
        string $reason,
    ): void {
        $reason = SensitiveReason::require($reason);
        $this->transaction($context, function () use (
            $context,
            $publicId,
            $reason,
        ) {
            $session = $this->locked($context, $publicId);
            if (
                !in_array(
                    $session["status"],
                    ["COUNTING", "COUNTS_COMMITTED", "REVIEW"],
                    true,
                )
            ) {
                throw new ApiException(
                    "OPENING_STATE_CONFLICT",
                    "Finalized or aborted evidence cannot be changed.",
                    409,
                );
            }
            $this->countRow($context, (int) $session["id"]);
            $now = UtcTimestamp::now();
            $this->database
                ->table("reconciliation_sessions")
                ->where("id", $session["id"])
                ->update([
                    "status" => "ABORTED",
                    "aborted_by_user_id" => $context->userId,
                    "aborted_at" => $now,
                    "abort_reason" => $reason,
                    "updated_at" => $now,
                ]);
            $this->record(
                $context,
                $publicId,
                "reconciliation.opening_aborted",
                $reason,
            );
            $this->publish($context, $publicId);
        });
    }

    private function transaction(
        ShopContext $context,
        callable $operation,
    ): void {
        $context->requireOwner();
        $this->database->transException(true)->transBegin();
        try {
            $this->guard->lockShop($context);
            $membership = $this->database
                ->query(
                    "SELECT id FROM tenant_memberships WHERE id=? AND tenant_id=? AND user_id=? AND role='PRIMARY_OWNER' AND status='ACTIVE' FOR UPDATE",
                    [
                        $context->membershipId,
                        $context->tenantId,
                        $context->userId,
                    ],
                )
                ->getRowArray();
            if (!$membership) {
                throw new ApiException(
                    "PERMISSION_DENIED",
                    "Active Primary Owner authority is required.",
                    403,
                );
            }
            $operation();
            if (!$this->database->transCommit()) {
                throw new \RuntimeException(
                    "Opening Cash could not be committed.",
                );
            }
        } catch (Throwable $exception) {
            $this->database->transRollback();
            throw $exception;
        }
    }
    private function locked(
        ShopContext $context,
        string $publicId,
        bool $required = true,
    ): ?array {
        $row = $this->database
            ->query(
                "SELECT * FROM reconciliation_sessions WHERE tenant_id=? AND shop_id=? AND public_id=? AND control_kind='OPENING' AND scope='CASH' FOR UPDATE",
                [$context->tenantId, $context->shopId, strtolower($publicId)],
            )
            ->getRowArray();
        if (!$row && $required) {
            throw new ApiException(
                "OPENING_NOT_FOUND",
                "The Opening Cash attempt was not found.",
                404,
            );
        }
        return $row;
    }
    private function countRow(ShopContext $context, int $id): array
    {
        $row = $this->database
            ->query(
                "SELECT * FROM reconciliation_financial_counts WHERE tenant_id=? AND shop_id=? AND reconciliation_session_id=? AND channel='CASH' FOR UPDATE",
                [$context->tenantId, $context->shopId, $id],
            )
            ->getRowArray();
        if (!$row) {
            throw new ApiException(
                "OPENING_STATE_CONFLICT",
                "The Cash count is unavailable.",
                409,
            );
        }
        return $row;
    }
    private function requireStatus(array $session, string $status): void
    {
        if ($session["status"] !== $status) {
            throw new ApiException(
                "OPENING_STATE_CONFLICT",
                "This action is unavailable at the current Opening Cash stage. Reload the record.",
                409,
            );
        }
    }
    private function assertFirstCash(ShopContext $context): void
    {
        $previous = $this->database
            ->query(
                "SELECT id FROM reconciliation_sessions WHERE tenant_id=? AND shop_id=? AND scope IN ('CASH','FULL') AND status='FINALIZED' FOR UPDATE",
                [$context->tenantId, $context->shopId],
            )
            ->getRowArray();
        if ($previous) {
            throw new ApiException(
                "OPENING_ALREADY_FINALIZED",
                "This Shop already has a finalized Cash baseline.",
                409,
            );
        }
    }
    private function bindWatermark(
        int $id,
        bool $confirmed,
        ?string $now = null,
    ): void {
        $watermark = $this->changes->acquireWatermark();
        $generation = $this->database
            ->query(
                "SELECT id FROM sync_generations WHERE generation_uuid=? FOR UPDATE",
                [$watermark->generationUuid],
            )
            ->getRowArray();
        $update = [
            "sync_generation_id" => $generation["id"],
            "cutoff_change_sequence" => $watermark->changeSequence,
        ];
        if ($confirmed) {
            $update["cutoff_at"] = $now;
            $update["cash_period_starts_at"] = $now;
        }
        // Only already-owned session row is changed after the final generation lock.
        $this->database
            ->table("reconciliation_sessions")
            ->where("id", $id)
            ->update($update);
    }
    private function assertGeneration(array $session): void
    {
        $watermark = $this->changes->acquireWatermark();
        $row = $this->database
            ->query(
                "SELECT id FROM sync_generations WHERE generation_uuid=? FOR UPDATE",
                [$watermark->generationUuid],
            )
            ->getRowArray();
        if ((int) $row["id"] !== (int) $session["sync_generation_id"]) {
            throw new ApiException(
                "OPENING_GENERATION_CHANGED",
                "The server generation changed. Abort this attempt and confirm a new baseline.",
                409,
            );
        }
    }
    private function assertOpeningCount(array $count): void
    {
        if (
            $count["actual_amount"] === null ||
            $count["committed_at"] === null ||
            $count["expected_amount"] !== $count["actual_amount"] ||
            $count["opening_baseline_amount"] !== $count["actual_amount"] ||
            $count["variance_amount"] !== "0.00"
        ) {
            throw new ApiException(
                "OPENING_STATE_CONFLICT",
                "The confirmed Cash baseline is inconsistent.",
                409,
            );
        }
        foreach (
            [
                "cash_sale_receipts",
                "cash_sale_refunds",
                "cash_debt_receipts",
                "cash_introductions",
                "cash_expenses",
                "cash_withdrawals",
                "cash_corrections_net",
            ]
            as $field
        ) {
            if ($count[$field] !== "0.00") {
                throw new ApiException(
                    "OPENING_STATE_CONFLICT",
                    "Opening Cash cannot contain business activity.",
                    409,
                );
            }
        }
    }
    private function record(
        ShopContext $context,
        string $id,
        string $action,
        string $reason,
        array $metadata = [],
    ): void {
        $this->audit->append(
            new AuditAppendCommand(
                AuditActor::tenantUser(
                    $context->userId,
                    $context->membershipId,
                    $context->tenantId,
                    $context->shopId,
                ),
                $action,
                AuditCategory::RECONCILIATION,
                "reconciliation_session",
                $id,
                $reason,
                $metadata,
            ),
        );
    }
    private function publish(ShopContext $context, string $id): void
    {
        $this->changes->publish([
            new ChangeCommand(
                $context->tenantId,
                $context->shopId,
                "reconciliation_session",
                $id,
            ),
        ]);
    }
}
