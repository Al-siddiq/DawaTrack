<?php

declare(strict_types=1);
namespace App\Modules\CustomerDebt\Infrastructure;
use App\Exceptions\ApiException;
use App\Modules\Sales\Application\{CommercialAccess, DraftInput};
use App\Modules\TenantShop\Infrastructure\ShopContext;
use CodeIgniter\Database\BaseConnection;

final class CustomerQuery
{
    public function __construct(
        private readonly BaseConnection $database,
        private readonly CommercialAccess $access,
    ) {}
    public function page(ShopContext $context, array $input): array
    {
        $this->access->read($context, "customer.view");
        $page = max(1, min(100000, (int) ($input["page"] ?? 1)));
        $size = max(1, min(50, (int) ($input["per_page"] ?? 20)));
        $search = DraftInput::text($input["search"] ?? "", "search", 160);
        $status = $input["status"] ?? "ACTIVE";
        if (!in_array($status, ["ACTIVE", "INACTIVE", "ALL"], true)) {
            throw new ApiException(
                "VALIDATION_FAILED",
                "Choose a Customer status.",
                422,
            );
        }
        $private = $this->access->allows($context, "customer.private");
        $builder = $this->database
            ->table("customers")
            ->select(
                "public_id,display_name,status,created_at,updated_at,deactivated_at" .
                    ($private ? ",phone,notes" : ""),
            )
            ->where("tenant_id", $context->tenantId);
        if ($status !== "ALL") {
            $builder->where("status", $status);
        }
        if ($search !== null) {
            $builder->groupStart()->like("display_name", $search);
            if ($private) {
                $builder->orLike("phone", $search);
            }
            $builder->groupEnd();
        }
        $total = $builder->countAllResults(false);
        return [
            "items" => $builder
                ->orderBy("display_name")
                ->orderBy("id")
                ->get($size, ($page - 1) * $size)
                ->getResultArray(),
            "pagination" => [
                "page" => $page,
                "per_page" => $size,
                "total" => $total,
                "total_pages" => (int) ceil($total / $size),
            ],
            "private_visible" => $private,
        ];
    }
    public function detail(ShopContext $context, string $id): array
    {
        $this->access->read($context, "customer.view");
        $private = $this->access->allows($context, "customer.private");
        $row = $this->database
            ->table("customers")
            ->select(
                "public_id,display_name,status,created_at,updated_at,deactivated_at" .
                    ($private ? ",phone,notes" : ""),
            )
            ->where([
                "tenant_id" => $context->tenantId,
                "public_id" => DraftInput::uuid($id),
            ])
            ->get()
            ->getRowArray();
        if (!$row) {
            throw new ApiException(
                "CUSTOMER_NOT_FOUND",
                "The Customer was not found.",
                404,
            );
        }
        return $row + ["private_visible" => $private];
    }
}
