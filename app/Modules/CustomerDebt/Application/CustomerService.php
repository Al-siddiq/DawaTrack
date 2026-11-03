<?php

declare(strict_types=1);
namespace App\Modules\CustomerDebt\Application;
use App\Exceptions\ApiException;
use App\Modules\Sales\Application\{CommercialAccess, DraftInput};
use App\Modules\Audit\Application\AuditService;
use App\Modules\Audit\Domain\{AuditActor, AuditAppendCommand, AuditCategory};
use App\Modules\Sync\Application\ChangeSequenceService;
use App\Modules\Sync\Domain\{ChangeCommand, ChangeKind};
use App\Modules\TenantShop\Infrastructure\ShopContext;
use App\Support\{UtcTimestamp, SensitiveReason};
use CodeIgniter\Database\BaseConnection;

final class CustomerService
{
    public function __construct(
        private readonly BaseConnection $database,
        private readonly CommercialAccess $access,
        private readonly AuditService $audit,
        private readonly ChangeSequenceService $changes,
    ) {}
    public function create(ShopContext $context, array $input): string
    {
        DraftInput::only($input, [
            "public_id",
            "display_name",
            "phone",
            "notes",
        ]);
        $id = DraftInput::uuid($input["public_id"] ?? null);
        $this->access->read($context, "customer.view");
        return $this->access->mutate(
            $context,
            ["customer.view", "customer.manage"],
            function () use ($context, $input, $id) {
                $data = $this->values($context, $input);
                $existing = $this->database
                    ->query(
                        "SELECT display_name,phone,notes,status FROM customers WHERE tenant_id=? AND public_id=? FOR UPDATE",
                        [$context->tenantId, $id],
                    )
                    ->getRowArray();
                if ($existing) {
                    if (
                        $existing["status"] !== "ACTIVE" ||
                        array_intersect_key($existing, $data) != $data
                    ) {
                        throw new ApiException(
                            "CUSTOMER_ID_CONFLICT",
                            "This Customer identity already has different details.",
                            409,
                        );
                    }
                    return $id;
                }
                $now = UtcTimestamp::now();
                $this->database->table("customers")->insert(
                    $data + [
                        "public_id" => $id,
                        "tenant_id" => $context->tenantId,
                        "status" => "ACTIVE",
                        "created_by_user_id" => $context->userId,
                        "created_at" => $now,
                        "updated_at" => $now,
                    ],
                );
                $this->record(
                    $context,
                    $id,
                    "customer.created",
                    "Customer created.",
                    ["fields" => array_keys($data)],
                );
                $this->changes->publish([
                    new ChangeCommand(
                        $context->tenantId,
                        null,
                        "customer",
                        $id,
                    ),
                ]);
                return $id;
            },
        );
    }
    public function update(ShopContext $context, string $id, array $input): void
    {
        DraftInput::only($input, [
            "display_name",
            "phone",
            "notes",
            "expected_updated_at",
        ]);
        $this->access->read($context, "customer.view");
        $id = DraftInput::uuid($id);
        $this->access->mutate(
            $context,
            ["customer.view", "customer.manage"],
            function () use ($context, $id, $input) {
                $row = $this->locked($context, $id);
                DraftInput::version(
                    $row,
                    $input["expected_updated_at"] ?? null,
                );
                if ($row["status"] !== "ACTIVE") {
                    throw new ApiException(
                        "CUSTOMER_INACTIVE",
                        "Inactive Customers retain history and cannot be edited.",
                        409,
                    );
                }
                $data = $this->values($context, $input, $row);
                if (array_intersect_key($row, $data) == $data) {
                    throw new ApiException(
                        "CUSTOMER_UNCHANGED",
                        "No Customer detail changed.",
                        409,
                    );
                }
                $this->database
                    ->table("customers")
                    ->where("id", $row["id"])
                    ->update(
                        $data + [
                            "updated_at" => DraftInput::nextTime(
                                $row["updated_at"],
                            ),
                        ],
                    );
                $this->record(
                    $context,
                    $id,
                    "customer.updated",
                    "Customer profile updated.",
                    ["fields" => array_keys($data)],
                );
                $this->changes->publish([
                    new ChangeCommand(
                        $context->tenantId,
                        null,
                        "customer",
                        $id,
                    ),
                ]);
            },
        );
    }
    public function deactivate(
        ShopContext $context,
        string $id,
        array $input,
    ): void {
        DraftInput::only($input, ["reason", "expected_updated_at"]);
        $id = DraftInput::uuid($id);
        $reason = SensitiveReason::require(
            DraftInput::text($input["reason"] ?? null, "reason", 500, true),
        );
        $this->access->read($context, "customer.view");
        $this->access->mutate(
            $context,
            ["customer.view", "customer.manage"],
            function () use ($context, $id, $input, $reason) {
                $row = $this->locked($context, $id);
                DraftInput::version(
                    $row,
                    $input["expected_updated_at"] ?? null,
                );
                if ($row["status"] !== "ACTIVE") {
                    throw new ApiException(
                        "CUSTOMER_INACTIVE",
                        "The Customer is already inactive.",
                        409,
                    );
                }
                $now = DraftInput::nextTime($row["updated_at"]);
                $this->database
                    ->table("customers")
                    ->where("id", $row["id"])
                    ->update([
                        "status" => "INACTIVE",
                        "deactivated_at" => $now,
                        "updated_at" => $now,
                    ]);
                $this->record($context, $id, "customer.deactivated", $reason);
                $this->changes->publish([
                    new ChangeCommand(
                        $context->tenantId,
                        null,
                        "customer",
                        $id,
                        ChangeKind::DEACTIVATE,
                    ),
                ]);
            },
        );
    }
    private function values(
        ShopContext $context,
        array $input,
        ?array $old = null,
    ): array {
        $data = [
            "display_name" => DraftInput::text(
                $input["display_name"] ?? ($old["display_name"] ?? null),
                "display_name",
                160,
                true,
            ),
        ];
        foreach (["phone", "notes"] as $field) {
            if (array_key_exists($field, $input)) {
                if (!$this->access->allows($context, "customer.private")) {
                    throw new ApiException(
                        "PERMISSION_DENIED",
                        "Customer private-detail permission is required to edit contact information.",
                        403,
                    );
                }
                $data[$field] =
                    $field === "phone"
                        ? DraftInput::phone($input[$field])
                        : DraftInput::text($input[$field], "notes", 500);
            }
        }
        return $data;
    }
    private function locked(ShopContext $context, string $id): array
    {
        $row = $this->database
            ->query(
                "SELECT * FROM customers WHERE tenant_id=? AND public_id=? FOR UPDATE",
                [$context->tenantId, $id],
            )
            ->getRowArray();
        if (!$row) {
            throw new ApiException(
                "CUSTOMER_NOT_FOUND",
                "The Customer was not found.",
                404,
            );
        }
        return $row;
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
                AuditCategory::DEBT,
                "customer",
                $id,
                $reason,
                $metadata,
            ),
        );
    }
}
