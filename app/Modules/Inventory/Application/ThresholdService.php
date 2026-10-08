<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

use App\Enums\PermissionCode;
use App\Exceptions\ApiException;
use App\Modules\Audit\Application\AuditService;
use App\Modules\Audit\Domain\{AuditActor,AuditAppendCommand,AuditCategory};
use App\Modules\Identity\Application\PermissionService;
use App\Modules\Sync\Application\ChangeSequenceService;
use App\Modules\Sync\Domain\ChangeCommand;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use App\Support\{UtcTimestamp,Uuid};
use CodeIgniter\Database\BaseConnection;
use Throwable;

final class ThresholdService
{
    public function __construct(private readonly BaseConnection $database,private readonly AuditService $audit,private readonly ChangeSequenceService $changes)
    {
    }

    public function update(ShopContext $context,string $productPublicId,string $threshold): void
    {
        if(!(new PermissionService($this->database,$this->audit))->allows($context->tenantId,$context->membershipId,PermissionCode::InventoryAdjust->value)) throw new ApiException('PERMISSION_DENIED','You do not have permission to change low-stock levels.',403);
        $threshold=trim($threshold);
        if(!preg_match('/^(?:0|[1-9]\d{0,14})(?:\.\d{1,4})?$/',$threshold)) throw new ApiException('VALIDATION_FAILED','Check the low-stock level and try again.',422,['low_stock_threshold'=>['Enter a non-negative quantity with no more than four decimal places.']]);
        if(!Uuid::isV4(strtolower($productPublicId))) throw new ApiException('PRODUCT_NOT_FOUND','The Product was not found.',404);
        [$whole,$fraction]=array_pad(explode('.',$threshold,2),2,''); $threshold=$whole.'.'.str_pad($fraction,4,'0');
        $this->database->transException(true)->transBegin();
        try {
            $row=$this->database->query('SELECT sp.id,sp.low_stock_threshold,p.public_id FROM stock_positions sp JOIN products p ON p.tenant_id=sp.tenant_id AND p.id=sp.product_id WHERE sp.tenant_id=? AND sp.shop_id=? AND p.public_id=? FOR UPDATE',[$context->tenantId,$context->shopId,strtolower($productPublicId)])->getRowArray();
            if($row===null) throw new ApiException('PRODUCT_NOT_FOUND','The Product was not found.',404);
            if($row['low_stock_threshold']===$threshold) throw new ApiException('THRESHOLD_UNCHANGED','The low-stock level is unchanged.',409);
            $this->database->table('stock_positions')->where('id',$row['id'])->update(['low_stock_threshold'=>$threshold,'version'=>new \CodeIgniter\Database\RawSql('version + 1'),'updated_at'=>UtcTimestamp::now()]);
            $this->audit->append(new AuditAppendCommand(AuditActor::tenantUser($context->userId,$context->membershipId,$context->tenantId,$context->shopId),'stock.threshold_updated',AuditCategory::INVENTORY,'stock_position',$row['public_id'],metadata:['before'=>$row['low_stock_threshold'],'after'=>$threshold]));
            $this->changes->publish([new ChangeCommand($context->tenantId,$context->shopId,'stock_position',$row['public_id'])]); $this->database->transCommit();
        } catch(Throwable $e){$this->database->transRollback();throw $e;}
    }
}
