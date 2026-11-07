<?php

declare(strict_types=1);
namespace App\Modules\Reconciliation\Infrastructure;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use CodeIgniter\Database\BaseConnection;

final class OpeningCashQuery
{
    public function __construct(private readonly BaseConnection $database) {}
    public function state(ShopContext $context): array
    {
        $context->requireOwner();
        $rows = $this->database
            ->table("reconciliation_sessions s")
            ->select(
                "s.public_id,s.scope,s.control_kind,s.status,s.cash_period_starts_at,s.cutoff_at,s.cutoff_change_sequence,g.generation_uuid,s.started_at,s.counts_committed_at,s.finalized_at,s.aborted_at,s.abort_reason,c.actual_amount,c.expected_amount,c.variance_amount,u.username started_by_name,f.username finalized_by_name",
            )
            ->join("sync_generations g", "g.id=s.sync_generation_id")
            ->join(
                "reconciliation_financial_counts c",
                "c.tenant_id=s.tenant_id AND c.shop_id=s.shop_id AND c.reconciliation_session_id=s.id",
            )
            ->join("users u", "u.id=s.started_by_user_id")
            ->join("users f", "f.id=s.finalized_by_user_id", "left")
            ->where([
                "s.tenant_id" => $context->tenantId,
                "s.shop_id" => $context->shopId,
                "s.control_kind" => "OPENING",
            ])
            ->orderBy("s.id", "DESC")
            ->get(50)
            ->getResultArray();
        $active = null;
        $baseline = null;
        foreach ($rows as $row) {
            if (
                in_array(
                    $row["status"],
                    ["COUNTING", "COUNTS_COMMITTED", "REVIEW"],
                    true,
                )
            ) {
                $active ??= $row;
            }
            if ($row["status"] === "FINALIZED") {
                $baseline ??= $row;
            }
        }
        return [
            "active" => $active,
            "baseline" => $baseline,
            "history" => $rows,
            "cash_ready" => $baseline !== null,
            "history_limit" => 50,
        ];
    }
}
