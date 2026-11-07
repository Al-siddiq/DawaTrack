<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

use App\Enums\PermissionCode;
use App\Exceptions\ApiException;
use App\Modules\Audit\Application\AuditService;
use App\Modules\Identity\Application\PermissionService;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use App\Support\{UtcTimestamp, Uuid};
use CodeIgniter\Database\BaseConnection;
use Throwable;

final class IntakeDraftService
{
    private readonly InventoryDecimal $decimal;
    public function __construct(
        private readonly BaseConnection $database,
        private readonly AuditService $audit,
    ) {
        $this->decimal = new InventoryDecimal($database);
    }

    /** @param array<string,mixed> $input */
    public function create(ShopContext $context, array $input): string
    {
        $this->authorize($context);
        $kind = strtoupper(trim((string) ($input["intake_kind"] ?? "")));
        if (!in_array($kind, ["OPENING", "RECEIPT"], true)) {
            throw new ApiException(
                "VALIDATION_FAILED",
                "Choose Opening stock or Receive stock.",
                422,
                ["intake_kind" => ["Choose a valid Intake type."]],
            );
        }
        $publicId = Uuid::v4();
        $now = UtcTimestamp::now();
        $occurred = $this->occurred($input["occurred_at"] ?? null, $now);
        $this->database
            ->table("stock_intakes")
            ->insert([
                "public_id" => $publicId,
                "tenant_id" => $context->tenantId,
                "shop_id" => $context->shopId,
                "intake_kind" => $kind,
                "status" => "DRAFT",
                "reference" => $this->optional(
                    $input["reference"] ?? null,
                    100,
                ),
                "notes" => $this->optional($input["notes"] ?? null, 1000),
                "occurred_at" => $occurred,
                "device_local_offset_minutes" => $this->offset(
                    $input["device_local_offset_minutes"] ?? null,
                ),
                "created_by_user_id" => $context->userId,
                "created_at" => $now,
                "updated_at" => $now,
            ]);
        return $publicId;
    }

    /** @param array<string,mixed> $input */
    private function updateHeaderLocked(
        ShopContext $context,
        string $publicId,
        array $input,
    ): void {
        $this->authorize($context);
        $draft = $this->draft($context, $publicId);
        $this->database
            ->table("stock_intakes")
            ->where("id", $draft["id"])
            ->update([
                "reference" => $this->optional(
                    $input["reference"] ?? null,
                    100,
                ),
                "notes" => $this->optional($input["notes"] ?? null, 1000),
                "occurred_at" => $this->occurred(
                    $input["occurred_at"] ?? null,
                    $draft["occurred_at"],
                ),
                "device_local_offset_minutes" => $this->offset(
                    $input["device_local_offset_minutes"] ?? null,
                ),
                "updated_at" => UtcTimestamp::now(),
            ]);
    }

    /** @param array<string,mixed> $input */
    private function addItemLocked(
        ShopContext $context,
        string $intakePublicId,
        array $input,
    ): string {
        $this->authorize($context);
        $draft = $this->draft($context, $intakePublicId);
        $product = $this->activeProduct(
            $context,
            (string) ($input["product_public_id"] ?? ""),
        );
        $quantity = $this->decimal->quantity(
            (string) ($input["quantity"] ?? ""),
        );
        $cost = $this->decimal->cost((string) ($input["unit_cost"] ?? ""));
        $line = (int) ($input["line_number"] ?? 0);
        if ($line < 1 || $line > 65535) {
            throw new ApiException(
                "VALIDATION_FAILED",
                "Choose a valid line position.",
                422,
                ["line_number" => ["Line number must be between 1 and 65535."]],
            );
        }
        $id = Uuid::v4();
        $now = UtcTimestamp::now();
        try {
            $this->database
                ->table("stock_intake_items")
                ->insert([
                    "public_id" => $id,
                    "tenant_id" => $context->tenantId,
                    "shop_id" => $context->shopId,
                    "stock_intake_id" => $draft["id"],
                    "product_id" => $product["id"],
                    "quantity" => $quantity,
                    "unit_cost" => $cost,
                    "line_cost_amount" => $this->decimal->lineAmount(
                        $quantity,
                        $cost,
                    ),
                    "line_number" => $line,
                    "created_at" => $now,
                    "updated_at" => $now,
                ]);
        } catch (Throwable $e) {
            if (str_contains($e->getMessage(), "Duplicate")) {
                throw new ApiException(
                    "INTAKE_LINE_CONFLICT",
                    "That line number is already in use.",
                    409,
                );
            }
            throw $e;
        }
        return $id;
    }

    /** @param array<string,mixed> $input */
    private function updateItemLocked(
        ShopContext $context,
        string $intakePublicId,
        string $itemPublicId,
        array $input,
    ): void {
        $this->authorize($context);
        $draft = $this->draft($context, $intakePublicId);
        $item = $this->item($context, (int) $draft["id"], $itemPublicId);
        $product = $this->activeProduct(
            $context,
            (string) ($input["product_public_id"] ?? ""),
        );
        $q = $this->decimal->quantity((string) ($input["quantity"] ?? ""));
        $c = $this->decimal->cost((string) ($input["unit_cost"] ?? ""));
        $line = (int) ($input["line_number"] ?? 0);
        if ($line < 1 || $line > 65535) {
            throw new ApiException(
                "VALIDATION_FAILED",
                "Choose a valid line position.",
                422,
                ["line_number" => ["Choose a valid line number."]],
            );
        }
        $this->database
            ->table("stock_intake_items")
            ->where("id", $item["id"])
            ->update([
                "product_id" => $product["id"],
                "quantity" => $q,
                "unit_cost" => $c,
                "line_cost_amount" => $this->decimal->lineAmount($q, $c),
                "line_number" => $line,
                "updated_at" => UtcTimestamp::now(),
            ]);
    }
    private function removeItemLocked(
        ShopContext $context,
        string $intakePublicId,
        string $itemPublicId,
    ): void {
        $this->authorize($context);
        $draft = $this->draft($context, $intakePublicId);
        $item = $this->item($context, (int) $draft["id"], $itemPublicId);
        $this->database
            ->table("stock_intake_items")
            ->where("id", $item["id"])
            ->delete();
    }
    private function discardLocked(ShopContext $context, string $publicId): void
    {
        $this->authorize($context);
        $draft = $this->draft($context, $publicId);
        $this->database
            ->table("stock_intake_items")
            ->where([
                "tenant_id" => $context->tenantId,
                "shop_id" => $context->shopId,
                "stock_intake_id" => $draft["id"],
            ])
            ->delete();
        $this->database
            ->table("stock_intakes")
            ->where("id", $draft["id"])
            ->delete();
    }
    public function updateHeader(
        ShopContext $context,
        string $publicId,
        array $input,
    ): void {
        $this->mutateDraft($context, $publicId, function () use (
            $context,
            $publicId,
            $input,
        ) {
            $this->updateHeaderLocked($context, $publicId, $input);
        });
    }

    public function addItem(
        ShopContext $context,
        string $intakePublicId,
        array $input,
    ): string {
        return $this->mutateDraft(
            $context,
            $intakePublicId,
            fn() => $this->addItemLocked($context, $intakePublicId, $input),
        );
    }

    public function updateItem(
        ShopContext $context,
        string $intakePublicId,
        string $itemPublicId,
        array $input,
    ): void {
        $this->mutateDraft($context, $intakePublicId, function () use (
            $context,
            $intakePublicId,
            $itemPublicId,
            $input,
        ) {
            $this->updateItemLocked(
                $context,
                $intakePublicId,
                $itemPublicId,
                $input,
            );
        });
    }

    public function removeItem(
        ShopContext $context,
        string $intakePublicId,
        string $itemPublicId,
    ): void {
        $this->mutateDraft($context, $intakePublicId, function () use (
            $context,
            $intakePublicId,
            $itemPublicId,
        ) {
            $this->removeItemLocked($context, $intakePublicId, $itemPublicId);
        });
    }

    public function discard(ShopContext $context, string $publicId): void
    {
        $this->mutateDraft($context, $publicId, function () use (
            $context,
            $publicId,
        ) {
            $this->discardLocked($context, $publicId);
        });
    }

    private function mutateDraft(
        ShopContext $context,
        string $publicId,
        callable $operation,
    ): mixed {
        $this->authorize($context);
        $this->database->transException(true)->transBegin();
        try {
            $this->draft($context, $publicId); // Same header lock as posting, held through mutation.
            $result = $operation();
            if (!$this->database->transCommit()) {
                throw new \RuntimeException("Draft mutation failed.");
            }
            return $result;
        } catch (Throwable $exception) {
            $this->database->transRollback();
            throw $exception;
        }
    }

    /** @return array<string,mixed> */ private function draft(
        ShopContext $c,
        string $id,
    ): array {
        if ($this->database->transDepth < 1) {
            throw new \LogicException("Draft lock requires a transaction.");
        }
        $r = $this->database
            ->query(
                "SELECT * FROM stock_intakes WHERE tenant_id=? AND shop_id=? AND public_id=? FOR UPDATE",
                [$c->tenantId, $c->shopId, strtolower($id)],
            )
            ->getRowArray();
        if (!$r) {
            throw new ApiException(
                "INTAKE_NOT_FOUND",
                "The Stock Intake was not found.",
                404,
            );
        }
        if ($r["status"] !== "DRAFT") {
            throw new ApiException(
                "INTAKE_ALREADY_POSTED",
                "This Stock Intake is already posted and cannot be changed.",
                409,
            );
        }
        return $r;
    }
    /** @return array<string,mixed> */ private function item(
        ShopContext $c,
        int $intake,
        string $id,
    ): array {
        $r = $this->database
            ->table("stock_intake_items")
            ->where([
                "tenant_id" => $c->tenantId,
                "shop_id" => $c->shopId,
                "stock_intake_id" => $intake,
                "public_id" => strtolower($id),
            ])
            ->get()
            ->getRowArray();
        if (!$r) {
            throw new ApiException(
                "INTAKE_ITEM_NOT_FOUND",
                "The Intake item was not found.",
                404,
            );
        }
        return $r;
    }
    /** @return array<string,mixed> */ private function activeProduct(
        ShopContext $c,
        string $id,
    ): array {
        $r = $this->database
            ->table("products")
            ->select("id,public_id,status")
            ->where([
                "tenant_id" => $c->tenantId,
                "public_id" => strtolower($id),
            ])
            ->get()
            ->getRowArray();
        if (!$r) {
            throw new ApiException(
                "PRODUCT_NOT_FOUND",
                "The Product was not found.",
                404,
            );
        }
        if ($r["status"] !== "ACTIVE") {
            throw new ApiException(
                "PRODUCT_INACTIVE",
                "Inactive Products cannot receive new stock.",
                409,
            );
        }
        return $r;
    }
    private function authorize(ShopContext $c): void
    {
        if (
            !(new PermissionService($this->database, $this->audit))->allows(
                $c->tenantId,
                $c->membershipId,
                PermissionCode::InventoryIntake->value,
            )
        ) {
            throw new ApiException(
                "PERMISSION_DENIED",
                "You do not have permission to manage Stock Intakes.",
                403,
            );
        }
    }
    private function optional(mixed $v, int $max): ?string
    {
        $v = trim((string) $v);
        if (mb_strlen($v) > $max) {
            throw new ApiException(
                "VALIDATION_FAILED",
                "Check the Intake details and try again.",
                422,
            );
        }
        return $v === "" ? null : $v;
    }
    private function occurred(mixed $v, string $default): string
    {
        $v = trim((string) $v);
        if ($v === "") {
            return $default;
        }
        $d = \DateTimeImmutable::createFromFormat(
            "!Y-m-d H:i:s.u",
            $v,
            new \DateTimeZone("UTC"),
        );
        if (!$d) {
            throw new ApiException(
                "VALIDATION_FAILED",
                "Enter a valid UTC occurrence time.",
                422,
                ["occurred_at" => ["Use UTC DATETIME(6) format."]],
            );
        }
        return $d->format("Y-m-d H:i:s.u");
    }
    private function offset(mixed $v): ?int
    {
        if ($v === null || $v === "") {
            return null;
        }
        $v = (int) $v;
        if ($v < -840 || $v > 840) {
            throw new ApiException(
                "VALIDATION_FAILED",
                "Enter a valid local time offset.",
                422,
            );
        }
        return $v;
    }
}
