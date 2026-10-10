<?php

declare(strict_types=1);

namespace App\Modules\ProductCatalog\Infrastructure;

use App\Exceptions\ApiException;
use App\Modules\ProductCatalog\Application\ProductNameNormalizer;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use CodeIgniter\Database\BaseConnection;

final class ProductQuery
{
    public function __construct(private readonly BaseConnection $database, private readonly ProductNameNormalizer $normalizer = new ProductNameNormalizer())
    {
    }

    /** @return array{items:list<array<string,mixed>>,pagination:array<string,int>} */
    public function page(ShopContext $context, string $search, string $status, int $page, int $perPage, string $stockState): array
    {
        $page = max(1, $page); $perPage = max(1, min(50, $perPage));
        $status = strtoupper($status);
        if (! in_array($status, ['ALL','ACTIVE','INACTIVE'], true)) throw new ApiException('PRODUCT_FILTER_INVALID', 'Choose a valid Product status filter.', 422);
        $builder = $this->base($context);
        if ($status !== 'ALL') $builder->where('p.status', $status);
        if (trim($search) !== '') $builder->like('p.normalized_name', $this->normalizer->normalize($search));
        $stockState = strtoupper($stockState);
        if (! in_array($stockState, ['ALL', 'LOW', 'NEGATIVE'], true)) throw new ApiException('PRODUCT_FILTER_INVALID', 'Choose a valid stock filter.', 422);
        if ($stockState === 'LOW') $builder->where('sp.expected_quantity <= sp.low_stock_threshold');
        if ($stockState === 'NEGATIVE') $builder->where('sp.expected_quantity <', 0);
        $total = $builder->countAllResults(false);
        $items = $builder->orderBy('p.name','ASC')->orderBy('p.id','ASC')->get($perPage, ($page - 1) * $perPage)->getResultArray();

        return ['items' => array_map([$this, 'map'], $items), 'pagination' => ['page'=>$page,'per_page'=>$perPage,'total'=>$total,'total_pages'=>(int) ceil($total/$perPage)]];
    }

    /** @return array<string,mixed> */
    public function detail(ShopContext $context, string $publicId): array
    {
        $row = $this->base($context)->where('p.public_id', strtolower($publicId))->get()->getRowArray();
        if ($row === null) throw new ApiException('PRODUCT_NOT_FOUND', 'The Product was not found.', 404);
        return $this->map($row);
    }

    private function base(ShopContext $context): \CodeIgniter\Database\BaseBuilder
    {
        return $this->database->table('products p')->select('p.public_id,p.name,p.normalized_name,p.unit_name,p.current_selling_price,p.status,p.created_at,p.updated_at,p.deactivated_at,sp.expected_quantity,sp.low_stock_threshold,sp.average_unit_cost,sp.cost_basis_state,sp.version')
            ->join('stock_positions sp', 'sp.tenant_id=p.tenant_id AND sp.product_id=p.id')
            ->where(['p.tenant_id'=>$context->tenantId,'sp.shop_id'=>$context->shopId]);
    }

    private function compareDecimal(string $left, string $right, int $scale): int
    {
        $normalize = static function (string $value) use ($scale): array {
            $negative = str_starts_with($value, '-');
            $value = ltrim($value, '+-');
            [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
            return [$negative, ltrim($whole, '0') ?: '0', str_pad(substr($fraction, 0, $scale), $scale, '0')];
        };
        [$leftNegative, $leftWhole, $leftFraction] = $normalize($left);
        [$rightNegative, $rightWhole, $rightFraction] = $normalize($right);
        if ($leftNegative !== $rightNegative) return $leftNegative ? -1 : 1;
        $magnitude = strlen($leftWhole) <=> strlen($rightWhole);
        if ($magnitude === 0) $magnitude = strcmp($leftWhole . $leftFraction, $rightWhole . $rightFraction) <=> 0;
        return $leftNegative ? -$magnitude : $magnitude;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function map(array $row): array
    {
        $row['is_low_stock'] = $this->compareDecimal((string) $row['expected_quantity'], (string) $row['low_stock_threshold'], 4) <= 0;
        $row['is_negative_stock'] = str_starts_with((string) $row['expected_quantity'], '-');
        return $row;
    }
}
