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

final class ManualMovementService{
 private const OUTGOING=['DAMAGED','EXPIRED','OWNER_WITHDRAWAL','MISSING_LOSS'];
private readonly InventoryProjection $projection;

 public function __construct(private readonly BaseConnection $database,private readonly AuditService $audit,private readonly ChangeSequenceService $changes,private readonly RequestContext $requestContext) {
$this->projection=new InventoryProjection($database);
}
 /** @param array<string,mixed> $input */ public function record(ShopContext $context,array $input): string{$this->authorize($context,PermissionCode::InventoryAdjust);
$type=strtoupper(trim((string)($input['movement_type']??'')));
if(!in_array($type,[...self::OUTGOING,'CORRECTION'],true))throw new ApiException('INVALID_MOVEMENT_TYPE','Choose an approved stock movement.',422,['movement_type'=>['Choose Damaged, Expired, Owner use, Missing/lost, or Correction.']]);
$reason=SensitiveReason::require((string)($input['reason']??''));
$direction=strtoupper((string)($input['direction']??''));
if($type==='CORRECTION'&&!in_array($direction,['ADD','REMOVE'],true))throw new ApiException('INVALID_MOVEMENT_SIGN','Choose whether the Correction adds or removes stock.',422,['direction'=>['Choose add or remove.']]);
$delta=$this->projection->signed((string)($input['quantity']??''),$type==='CORRECTION'&&$direction==='ADD');
$productId=strtolower((string)($input['product_public_id']??''));
$occurred=$this->occurred($input['occurred_at']??null);
$this->database->transException(true)->transBegin();
try{$product=$this->database->query('SELECT id,public_id FROM products WHERE tenant_id=? AND public_id=? FOR UPDATE',[$context->tenantId,$productId])->getRowArray();
if(!$product)throw new ApiException('PRODUCT_NOT_FOUND','The Product was not found.',404);
$position=$this->database->query('SELECT * FROM stock_positions WHERE tenant_id=? AND shop_id=? AND product_id=? FOR UPDATE',[$context->tenantId,$context->shopId,$product['id']])->getRowArray();
if(!$position)throw new ApiException('INVENTORY_STATE_CONFLICT','The Stock Position is unavailable.',409);
$after=$this->projection->add((string)$position['expected_quantity'],$delta);
$state=$this->projection->stateAfterNonAcquisition((string)$position['cost_basis_state'],$after);
$movementId=Uuid::v4();
$now=UtcTimestamp::now();
$this->database->table('stock_movements')->insert(['public_id'=>$movementId,'tenant_id'=>$context->tenantId,'shop_id'=>$context->shopId,'product_id'=>$product['id'],'stock_position_id'=>$position['id'],'movement_type'=>$type,'quantity_delta'=>$delta,'unit_cost_basis'=>$position['average_unit_cost'],'quantity_before'=>$position['expected_quantity'],'quantity_after'=>$after,'average_cost_before'=>$position['average_unit_cost'],'average_cost_after'=>$position['average_unit_cost'],'source_kind'=>'MANUAL','reason_code'=>$type,'reason_text'=>$reason,'occurred_at'=>$occurred,'posted_at'=>$now,'created_by_user_id'=>$context->userId,'correlation_id'=>$this->requestContext->requestId(),'created_at'=>$now]);
$internal=(int)$this->database->insertID();
$this->database->table('stock_positions')->where('id',$position['id'])->update(['expected_quantity'=>$after,'cost_basis_state'=>$state,'last_movement_id'=>$internal,'version'=>new \CodeIgniter\Database\RawSql('version + 1'),'updated_at'=>$now]);
$action=$type==='CORRECTION'?'stock_movement.corrected':'stock_movement.recorded';
$this->audit->append(new AuditAppendCommand(AuditActor::tenantUser($context->userId,$context->membershipId,$context->tenantId,$context->shopId),$action,AuditCategory::INVENTORY,'stock_movement',$movementId,$reason,['movement_type'=>$type,'quantity_delta'=>$delta,'product_public_id'=>$product['public_id'],'quantity_before'=>$position['expected_quantity'],'quantity_after'=>$after,'unit_cost_basis'=>$position['average_unit_cost']]));
$this->changes->publish([new ChangeCommand($context->tenantId,$context->shopId,'stock_movement',$movementId),new ChangeCommand($context->tenantId,$context->shopId,'stock_position',$product['public_id'])]);
$this->database->transCommit();
return $movementId;
}
catch(Throwable $e) {
$this->database->transRollback();
throw $e;
}}
 private function authorize(ShopContext $c,PermissionCode $p): void{if(!(new PermissionService($this->database,$this->audit))->allows($c->tenantId,$c->membershipId,$p->value))throw new ApiException('PERMISSION_DENIED','You do not have permission for this stock action.',403);
}
 private function occurred(mixed $value): string{$value=trim((string)$value);
if($value==='')return UtcTimestamp::now();
$d=\DateTimeImmutable::createFromFormat('!Y-m-d H:i:s.u',$value,new \DateTimeZone('UTC'));
if(!$d)throw new ApiException('VALIDATION_FAILED','Enter a valid UTC occurrence time.',422,['occurred_at'=>['Use UTC DATETIME(6) format.']]);
return $d->format('Y-m-d H:i:s.u');
}
}
