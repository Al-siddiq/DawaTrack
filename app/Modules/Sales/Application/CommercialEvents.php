<?php
declare(strict_types=1);
namespace App\Modules\Sales\Application;
use App\Modules\Audit\Application\AuditService;
use App\Modules\Audit\Domain\{AuditActor, AuditAppendCommand, AuditCategory};
use App\Modules\Sync\Application\ChangeSequenceService;
use App\Modules\Sync\Domain\ChangeCommand;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use App\Support\Uuid;
use CodeIgniter\Database\BaseConnection;

final class CommercialEvents
{
    public function __construct(
        private readonly BaseConnection $db,
        private readonly AuditService $audit,
        private readonly ChangeSequenceService $changes,
    ) {}
    public function payment(
        ShopContext $c,
        int $sale,
        string $direction,
        string $channel,
        string $amount,
        string $occurred,
        string $now,
        string $correlation,
        ?int $reversal = null,
    ): void {
        if ($amount === "0.00") {
            return;
        }
        $this->db
            ->table("sale_payments")
            ->insert([
                "public_id" => Uuid::v4(),
                "tenant_id" => $c->tenantId,
                "shop_id" => $c->shopId,
                "sale_id" => $sale,
                "direction" => $direction,
                "channel" => $channel,
                "amount" => $amount,
                "occurred_at" => $occurred,
                "posted_at" => $now,
                "created_by_user_id" => $c->userId,
                "reversal_of_payment_id" => $reversal,
                "correlation_id" => $correlation,
                "created_at" => $now,
            ]);
    }
    public function adjustment(
        ShopContext $c,
        array $credit,
        int $sale,
        string $type,
        string $delta,
        string $reason,
        string $occurred,
        string $now,
        string $correlation,
        ?int $reversal = null,
    ): void {
        (new \App\Modules\CustomerDebt\Application\ReceivableAdjustmentParticipant($this->db))->append(
            $c, $credit, $sale, $type, $delta, $reason, $occurred, $now, $correlation, $reversal,
        );
    }

    public function audit(
        ShopContext $c,
        string $action,
        string $id,
        string $reason,
        array $metadata = [],
    ): void {
        $this->audit->append(
            new AuditAppendCommand(
                AuditActor::tenantUser(
                    $c->userId,
                    $c->membershipId,
                    $c->tenantId,
                    $c->shopId,
                ),
                $action,
                AuditCategory::SALE,
                "sale",
                $id,
                $reason,
                $metadata,
            ),
        );
    }
    public function publish(
        ShopContext $c,
        array $headers,
        array $movements,
        array $productPublic,
        ?array $credit = null,
        ?string $customer = null,
    ): void {
        $commands = [];
        foreach (array_unique($headers) as $uuid) {
            $commands[] = new ChangeCommand(
                $c->tenantId,
                $c->shopId,
                "sale",
                $uuid,
            );
        }
        foreach ($movements as $uuid) {
            $commands[] = new ChangeCommand(
                $c->tenantId,
                $c->shopId,
                "stock_movement",
                $uuid,
            );
        }
        foreach (array_unique($productPublic) as $uuid) {
            $commands[] = new ChangeCommand(
                $c->tenantId,
                $c->shopId,
                "stock_position",
                $uuid,
            );
        }
        if ($credit) {
            $commands[] = new ChangeCommand(
                $c->tenantId,
                $c->shopId,
                "receivable",
                $credit["public_id"],
            );
        }
        if ($customer) {
            $commands[] = new ChangeCommand(
                $c->tenantId,
                null,
                "customer",
                $customer,
            );
        }
        $this->changes->publish($commands);
    }
}
