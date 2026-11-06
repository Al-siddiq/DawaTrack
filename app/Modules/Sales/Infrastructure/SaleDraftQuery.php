<?php

declare(strict_types=1);
namespace App\Modules\Sales\Infrastructure;
use App\Exceptions\ApiException;
use App\Modules\Sales\Application\{CommercialAccess, DraftInput};
use App\Modules\TenantShop\Infrastructure\ShopContext;
use CodeIgniter\Database\BaseConnection;

final class SaleDraftQuery
{
    public function __construct(
        private readonly BaseConnection $database,
        private readonly CommercialAccess $access,
    ) {}
    public function page(ShopContext $context, array $input): array
    {
        $this->access->read($context, "sale.view");
        $page = max(1, min(100000, (int) ($input["page"] ?? 1)));
        $size = max(1, min(50, (int) ($input["per_page"] ?? 20)));
        $builder = $this->database
            ->table("sales s")
            ->select(
                "s.public_id,s.kind,s.status,s.total_amount,s.created_at,s.updated_at,c.display_name customer_name",
            )
            ->join(
                "customers c",
                "c.tenant_id=s.tenant_id AND c.id=s.customer_id",
                "left",
            )
            ->where([
                "s.tenant_id" => $context->tenantId,
                "s.shop_id" => $context->shopId,
                "s.kind" => "SALE",
                "s.status" => "DRAFT",
            ]);
        $total = $builder->countAllResults(false);
        return [
            "items" => $builder
                ->orderBy("s.updated_at", "DESC")
                ->orderBy("s.id", "DESC")
                ->get($size, ($page - 1) * $size)
                ->getResultArray(),
            "pagination" => [
                "page" => $page,
                "per_page" => $size,
                "total" => $total,
                "total_pages" => (int) ceil($total / $size),
            ],
        ];
    }
    public function detail(ShopContext $context, string $id): array
    {
        $this->access->read($context, "sale.view");
        $row = $this->database
            ->table("sales s")
            ->select(
                "s.id,s.public_id,s.kind,s.status,s.occurred_at,s.device_local_offset_minutes,s.total_amount,s.created_at,s.updated_at,c.public_id customer_public_id,c.display_name customer_name,c.status customer_status",
            )
            ->join(
                "customers c",
                "c.tenant_id=s.tenant_id AND c.id=s.customer_id",
                "left",
            )
            ->where([
                "s.tenant_id" => $context->tenantId,
                "s.shop_id" => $context->shopId,
                "s.public_id" => DraftInput::uuid($id),
                "s.kind" => "SALE",
                "s.status" => "DRAFT",
            ])
            ->get()
            ->getRowArray();
        if (!$row) {
            throw new ApiException(
                "SALE_DRAFT_NOT_FOUND",
                "The Sale draft was not found.",
                404,
            );
        }
        $row["items"] = $this->database
            ->table("sale_items i")
            ->select(
                "i.public_id,i.line_number,i.quantity,i.unit_selling_price,i.line_sales_amount,p.public_id product_public_id,p.name product_name,p.unit_name,p.status product_status,p.current_selling_price",
            )
            ->join(
                "products p",
                "p.tenant_id=i.tenant_id AND p.id=i.product_id",
            )
            ->where([
                "i.tenant_id" => $context->tenantId,
                "i.shop_id" => $context->shopId,
                "i.sale_id" => $row["id"],
            ])
            ->orderBy("i.line_number")
            ->get()
            ->getResultArray();
        unset($row["id"]);
        $row["estimate_only"] = true;
        $row["posting_available"] = false;
        return $row;
    }
    public function lookup(
        ShopContext $context,
        string $type,
        array $input,
    ): array {
        $this->access->read($context, "sale.view");
        $this->access->read($context, "sale.record");
        $search = DraftInput::text($input["search"] ?? "", "search", 160);
        if ($type === "products") {
            $builder = $this->database
                ->table("products p")
                ->select(
                    "p.public_id,p.name,p.unit_name,p.current_selling_price",
                )
                ->join(
                    "stock_positions sp",
                    "sp.product_id=p.id AND sp.tenant_id=p.tenant_id",
                )
                ->where([
                    "p.tenant_id" => $context->tenantId,
                    "sp.shop_id" => $context->shopId,
                    "p.status" => "ACTIVE",
                ]);
            if ($search !== null) {
                $builder->like("p.name", $search);
            }
            return $builder
                ->orderBy("p.name")
                ->orderBy("p.id")
                ->get(50)
                ->getResultArray();
        }
        $builder = $this->database
            ->table("customers")
            ->select("public_id,display_name")
            ->where(["tenant_id" => $context->tenantId, "status" => "ACTIVE"]);
        if ($search !== null) {
            $builder->like("display_name", $search);
        }
        return $builder
            ->orderBy("display_name")
            ->orderBy("id")
            ->get(50)
            ->getResultArray();
    }
}
