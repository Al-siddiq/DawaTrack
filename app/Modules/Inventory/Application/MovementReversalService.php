<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

use App\Enums\PermissionCode;
use App\Exceptions\ApiException;
use App\Libraries\Http\RequestContext;
use App\Modules\Audit\Application\AuditService;
use App\Modules\Audit\Domain\{AuditActor,AuditAppendCommand,AuditCategory};
use App\Modules\Identity\Application\PermissionService;
use App\Modules\Sync\Application\ChangeSequenceService;
use App\Modules\Sync\Domain\ChangeCommand;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use App\Support\{SensitiveReason,UtcTimestamp,Uuid};
use CodeIgniter\Database\BaseConnection;
use Throwable;

final class MovementReversalService{
 private readonly InventoryProjection $projection;
public function __construct(private readonly BaseConnection $database,private readonly AuditService $audit,private readonly ChangeSequenceService $changes,private readonly RequestContext $requestContext) {
$this->projection=new InventoryProjection($database);
}
 public function reverse(ShopContext $context,string $originalPublicId,string $reason): string{$this->authorize($context);
$reason=SensitiveReason::require($reason);
$this->database->transException(true)->transBegin();
try{$original=$this->database->query('SELECT * FROM stock_movements WHERE tenant_id=? AND shop_id=? AND public_id=?',[$context->tenantId,$context->shopId,strtolower($originalPublicId)])->getRowArray();
if(!$original)throw new ApiException('MOVEMENT_NOT_FOUND','The Stock Movement was not found.',404);
if($original['movement_type']==='REVERSAL')throw new ApiException('REVERSAL_OF_REVERSAL_NOT_ALLOWED','Use a Correction instead of reversing a reversal.',409);
if($original['source_kind']==='SALE_ITEM')throw new ApiException('COMMERCIAL_MOVEMENT_PROTECTED','Sale and Return stock evidence must be corrected through its commercial workflow.',409);
if($original['source_kind']!=='MANUAL')throw new ApiException('MOVEMENT_REVERSAL_REQUIRES_INTAKE','Reverse Intake-sourced evidence through the whole Intake.',409);
$existing=$this->database->table('stock_movements')->where('reversal_of_movement_id',$original['id'])->countAllResults();
if($existing)throw new ApiException('MOVEMENT_ALREADY_REVERSED','This Stock Movement has already been reversed.',409);
$product=$this->database->query('SELECT id,public_id FROM products WHERE tenant_id=? AND id=? FOR UPDATE',[$context->tenantId,$original['product_id']])->getRowArray();
$this->database->query('SELECT id FROM stock_movements WHERE tenant_id=? AND shop_id=? AND id=? FOR UPDATE',[$context->tenantId,$context->shopId,$original['id']]);
if($this->database->table('stock_movements')->where('reversal_of_movement_id',$original['id'])->countAllResults())throw new ApiException('MOVEMENT_ALREADY_REVERSED','This Stock Movement has already been reversed.',409);
$position=$this->database->query('SELECT * FROM stock_positions WHERE tenant_id=? AND shop_id=? AND id=? FOR UPDATE',[$context->tenantId,$context->shopId,$original['stock_position_id']])->getRowArray();
if(!$product||!$position)throw new ApiException('INVENTORY_STATE_CONFLICT','The current stock state is unavailable.',409);
$delta=str_starts_with((string)$original['quantity_delta'],'-')?ltrim((string)$original['quantity_delta'],'-'):'-'.(string)$original['quantity_delta'];
$after=$this->projection->add((string)$position['expected_quantity'],$delta);
$state=$this->projection->stateAfterNonAcquisition((string)$position['cost_basis_state'],$after);
$publicId=Uuid::v4();
$now=UtcTimestamp::now();
try{$this->database->table('stock_movements')->insert(['public_id'=>$publicId,'tenant_id'=>$context->tenantId,'shop_id'=>$context->shopId,'product_id'=>$product['id'],'stock_position_id'=>$position['id'],'movement_type'=>'REVERSAL','quantity_delta'=>$delta,'unit_cost_basis'=>$position['average_unit_cost'],'quantity_before'=>$position['expected_quantity'],'quantity_after'=>$after,'average_cost_before'=>$position['average_unit_cost'],'average_cost_after'=>$position['average_unit_cost'],'source_kind'=>'REVERSAL','reversal_of_movement_id'=>$original['id'],'reason_code'=>'REVERSAL','reason_text'=>$reason,'occurred_at'=>$now,'posted_at'=>$now,'created_by_user_id'=>$context->userId,'correlation_id'=>$this->requestContext->requestId(),'created_at'=>$now]);
}
catch(Throwable $e) {
if(str_contains($e->getMessage(),'Duplicate'))throw new ApiException('MOVEMENT_ALREADY_REVERSED','This Stock Movement has already been reversed.',409);
throw $e;
}$internal=(int)$this->database->insertID();
$this->database->table('stock_positions')->where('id',$position['id'])->update(['expected_quantity'=>$after,'cost_basis_state'=>$state,'last_movement_id'=>$internal,'version'=>new \CodeIgniter\Database\RawSql('version + 1'),'updated_at'=>$now]);
$this->audit->append(new AuditAppendCommand(AuditActor::tenantUser($context->userId,$context->membershipId,$context->tenantId,$context->shopId),'stock_movement.reversed',AuditCategory::INVENTORY,'stock_movement',$publicId,$reason,['original_movement_public_id'=>$original['public_id'],'product_public_id'=>$product['public_id'],'quantity_delta'=>$delta,'quantity_before'=>$position['expected_quantity'],'quantity_after'=>$after]));
$this->changes->publish([new ChangeCommand($context->tenantId,$context->shopId,'stock_movement',$publicId),new ChangeCommand($context->tenantId,$context->shopId,'stock_position',$product['public_id'])]);
$this->database->transCommit();
return $publicId;
}
catch(Throwable $e) {
$this->database->transRollback();
throw $e;
}}
 private function authorize(ShopContext $c): void{if(!(new PermissionService($this->database,$this->audit))->allows($c->tenantId,$c->membershipId,PermissionCode::InventoryReverse->value))throw new ApiException('PERMISSION_DENIED','You do not have permission to reverse stock evidence.',403);
}
}
