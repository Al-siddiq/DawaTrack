<?php

declare(strict_types=1);

namespace App\Modules\ProductCatalog\Application;

use App\Enums\PermissionCode;
use App\Exceptions\ApiException;
use App\Modules\Audit\Application\AuditService;
use App\Modules\Audit\Domain\{AuditActor,AuditAppendCommand,AuditCategory};
use App\Modules\Identity\Application\PermissionService;
use App\Modules\Sync\Application\ChangeSequenceService;
use App\Modules\Sync\Domain\{ChangeCommand,ChangeKind};
use App\Modules\TenantShop\Infrastructure\ShopContext;
use App\Support\{UtcTimestamp,Uuid};
use CodeIgniter\Database\BaseConnection;
use Throwable;

final class ProductService
{
    public function __construct(private readonly BaseConnection $database, private readonly AuditService $audit, private readonly ChangeSequenceService $changes, private readonly ProductNameNormalizer $normalizer = new ProductNameNormalizer())
    {
    }

    /** @param array<string,mixed> $values */
    public function create(ShopContext $context, array $values): string
    {
        $this->authorize($context, PermissionCode::ProductManage);
        $input = ProductInput::from($values); $publicId = Uuid::v4(); $now = UtcTimestamp::now();
        $this->database->transException(true)->transBegin();
        try {
            $this->database->table('products')->insert(['public_id'=>$publicId,'tenant_id'=>$context->tenantId,'name'=>$input->name,'normalized_name'=>$this->normalizer->normalize($input->name),'unit_name'=>$input->unitName,'current_selling_price'=>$input->sellingPrice,'status'=>'ACTIVE','created_by_user_id'=>$context->userId,'created_at'=>$now,'updated_at'=>$now]);
            $productId = (int) $this->database->insertID();
            $this->database->table('stock_positions')->insert(['tenant_id'=>$context->tenantId,'shop_id'=>$context->shopId,'product_id'=>$productId,'expected_quantity'=>'0.0000','low_stock_threshold'=>'0.0000','average_unit_cost'=>'0.000000','cost_basis_state'=>'UNESTABLISHED','last_movement_id'=>null,'version'=>0,'created_at'=>$now,'updated_at'=>$now]);
            $this->audit->append(new AuditAppendCommand(AuditActor::tenantUser($context->userId,$context->membershipId,$context->tenantId,$context->shopId),'product.created',AuditCategory::INVENTORY,'product',$publicId,metadata:['name'=>$input->name,'unit_name'=>$input->unitName,'current_selling_price'=>$input->sellingPrice]));
            $this->changes->publish([new ChangeCommand($context->tenantId,$context->shopId,'product',$publicId),new ChangeCommand($context->tenantId,$context->shopId,'stock_position',$publicId)]);
            $this->database->transCommit(); return $publicId;
        } catch (Throwable $e) { $this->database->transRollback(); throw $e; }
    }

    /** @param array<string,mixed> $values */
    public function update(ShopContext $context, string $publicId, array $values): void
    {
        $this->authorize($context, PermissionCode::ProductManage); $input = ProductInput::from($values); $publicId = strtolower($publicId);
        $this->database->transException(true)->transBegin();
        try {
            $product = $this->locked($context,$publicId);
            if ($input->unitName !== $product['unit_name'] && $this->hasMovementHistory((int)$product['id'])) throw new ApiException('PRODUCT_UNIT_IMMUTABLE','The base unit cannot change after stock history exists. Create a new Product for a different unit.',409);
            $after=['name'=>$input->name,'normalized_name'=>$this->normalizer->normalize($input->name),'unit_name'=>$input->unitName,'current_selling_price'=>$input->sellingPrice];
            if ($after['name']===$product['name'] && $after['unit_name']===$product['unit_name'] && $after['current_selling_price']===$product['current_selling_price']) throw new ApiException('PRODUCT_UNCHANGED','No Product details changed.',409);
            $this->database->table('products')->where(['id'=>$product['id'],'tenant_id'=>$context->tenantId])->update([...$after,'updated_at'=>UtcTimestamp::now()]);
            $this->audit->append(new AuditAppendCommand(AuditActor::tenantUser($context->userId,$context->membershipId,$context->tenantId,$context->shopId),'product.updated',AuditCategory::INVENTORY,'product',$publicId,metadata:['before'=>['name'=>$product['name'],'unit_name'=>$product['unit_name'],'current_selling_price'=>$product['current_selling_price']],'after'=>['name'=>$after['name'],'unit_name'=>$after['unit_name'],'current_selling_price'=>$after['current_selling_price']]]));
            $this->changes->publish([new ChangeCommand($context->tenantId,$context->shopId,'product',$publicId)]); $this->database->transCommit();
        } catch (Throwable $e) { $this->database->transRollback(); throw $e; }
    }

    public function deactivate(ShopContext $context, string $publicId, string $reason): void
    {
        $this->authorize($context, PermissionCode::ProductManage); $publicId=strtolower($publicId);
        $this->database->transException(true)->transBegin();
        try {
            $product=$this->locked($context,$publicId); if($product['status']!=='ACTIVE') throw new ApiException('PRODUCT_ALREADY_INACTIVE','This Product is already inactive.',409);
            $now=UtcTimestamp::now(); $this->database->table('products')->where('id',$product['id'])->update(['status'=>'INACTIVE','deactivated_at'=>$now,'updated_at'=>$now]);
            $this->audit->append(new AuditAppendCommand(AuditActor::tenantUser($context->userId,$context->membershipId,$context->tenantId,$context->shopId),'product.deactivated',AuditCategory::INVENTORY,'product',$publicId,$reason,['before'=>'ACTIVE','after'=>'INACTIVE']));
            $this->changes->publish([new ChangeCommand($context->tenantId,$context->shopId,'product',$publicId,ChangeKind::DEACTIVATE)]); $this->database->transCommit();
        } catch(Throwable $e){$this->database->transRollback();throw $e;}
    }

    /** @return array<string,mixed> */
    private function locked(ShopContext $context,string $publicId): array
    {
        if(!Uuid::isV4($publicId)) throw new ApiException('PRODUCT_NOT_FOUND','The Product was not found.',404);
        $row=$this->database->query('SELECT * FROM products WHERE tenant_id=? AND public_id=? FOR UPDATE',[$context->tenantId,$publicId])->getRowArray();
        if($row===null) throw new ApiException('PRODUCT_NOT_FOUND','The Product was not found.',404); return $row;
    }
    private function hasMovementHistory(int $productId): bool
    {
        return $this->database->tableExists('stock_movements') && $this->database->table('stock_movements')->where('product_id',$productId)->countAllResults()>0;
    }
    private function authorize(ShopContext $context,PermissionCode $permission): void
    {
        if(!(new PermissionService($this->database,$this->audit))->allows($context->tenantId,$context->membershipId,$permission->value)) throw new ApiException('PERMISSION_DENIED','You do not have permission for this action.',403);
    }
}
