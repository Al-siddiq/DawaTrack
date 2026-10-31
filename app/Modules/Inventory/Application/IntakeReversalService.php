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

final class IntakeReversalService{
 private readonly CostStateReconstructor $states;
public function __construct(private readonly BaseConnection $database,private readonly AuditService $audit,private readonly ChangeSequenceService $changes,private readonly RequestContext $requestContext) {
$this->states=new CostStateReconstructor();
}
 public function reverse(ShopContext $context,string $intakePublicId,string $reason): string{$this->authorize($context);
$reason=SensitiveReason::require($reason);
$this->database->transException(true)->transBegin();
try{$intake=$this->database->query('SELECT * FROM stock_intakes WHERE tenant_id=? AND shop_id=? AND public_id=? FOR UPDATE',[$context->tenantId,$context->shopId,strtolower($intakePublicId)])->getRowArray();
if(!$intake)throw new ApiException('INTAKE_NOT_FOUND','The Stock Intake was not found.',404);
if($intake['status']!=='POSTED')throw new ApiException('INTAKE_NOT_POSTED','Only a posted Stock Intake can be reversed.',409);
if($intake['reversal_of_intake_id']!==null)throw new ApiException('REVERSAL_OF_REVERSAL_NOT_ALLOWED','Use a Correction instead of reversing a reversal.',409);
if($this->database->table('stock_intakes')->where('reversal_of_intake_id',$intake['id'])->countAllResults())throw new ApiException('INTAKE_ALREADY_REVERSED','This Stock Intake has already been reversed.',409);
$originals=$this->database->table('stock_movements m')->select('m.*,i.line_number,p.public_id product_public_id')->join('stock_intake_items i','i.id=m.stock_intake_item_id')->join('products p','p.id=m.product_id AND p.tenant_id=m.tenant_id')->where(['m.tenant_id'=>$context->tenantId,'m.shop_id'=>$context->shopId,'i.stock_intake_id'=>$intake['id'],'m.source_kind'=>'INTAKE_ITEM'])->orderBy('m.id','ASC')->get()->getResultArray();
if(!$originals)throw new ApiException('INTAKE_REVERSAL_NOT_CURRENT','The Intake has no reversible movement chain.',409);
$itemCount=$this->database->table('stock_intake_items')->where('stock_intake_id',$intake['id'])->countAllResults();
if($itemCount!==count($originals))throw new ApiException('INVENTORY_STATE_CONFLICT','The posted Intake is missing complete movement evidence.',409);
$productIds=array_values(array_unique(array_map(static fn($m)=>(int)$m['product_id'],$originals)));
sort($productIds,SORT_NUMERIC);
$marks=implode(',',array_fill(0,count($productIds),'?'));
$this->database->query("SELECT id FROM products WHERE tenant_id=? AND id IN ($marks) ORDER BY id FOR UPDATE",[$context->tenantId,...$productIds]);
$positions=$this->database->query("SELECT * FROM stock_positions WHERE tenant_id=? AND shop_id=? AND product_id IN ($marks) ORDER BY product_id FOR UPDATE",[$context->tenantId,$context->shopId,...$productIds])->getResultArray();
if(count($positions)!==count($productIds))throw new ApiException('INVENTORY_STATE_CONFLICT','A required Stock Position is missing.',409);
$positionMap=[];
foreach($positions as $p)$positionMap[(int)$p['product_id']]=$p;
$grouped=[];
foreach($originals as $m)$grouped[(int)$m['product_id']][]=$m;
$beforeStates=[];

 foreach($productIds as $pid) {
$chain=$this->database->table('stock_movements m')->select('m.*,om.source_kind original_source_kind')->join('stock_movements om','om.id=m.reversal_of_movement_id','left')->where(['m.tenant_id'=>$context->tenantId,'m.shop_id'=>$context->shopId,'m.product_id'=>$pid])->orderBy('m.id','ASC')->get()->getResultArray();
$expected=array_map(static fn($m)=>(int)$m['id'],$grouped[$pid]);
$actual=array_map(static fn($m)=>(int)$m['id'],array_slice($chain,-count($expected)));
if($actual!==$expected||(int)$positionMap[$pid]['last_movement_id']!==end($expected))throw new ApiException('INTAKE_REVERSAL_NOT_CURRENT','Later stock activity prevents a safe direct Intake reversal. Record a Correction and replacement instead.',409);
$states=$this->states->beforeStates($chain);
foreach($expected as $id)$beforeStates[$id]=$states[$id];
foreach($grouped[$pid] as $index=>$m) {
if($this->database->table('stock_movements')->where('reversal_of_movement_id',$m['id'])->countAllResults())throw new ApiException('INTAKE_ALREADY_REVERSED','An Intake movement has already been reversed.',409);
if($index>0) {
$prev=$grouped[$pid][$index-1];
if($m['quantity_before']!==$prev['quantity_after']||$m['average_cost_before']!==$prev['average_cost_after'])throw new ApiException('INVENTORY_STATE_CONFLICT','The Intake movement chain is inconsistent.',409);
}}}
 $now=UtcTimestamp::now();
$reversalPublic=Uuid::v4();
try{$this->database->table('stock_intakes')->insert(['public_id'=>$reversalPublic,'tenant_id'=>$context->tenantId,'shop_id'=>$context->shopId,'intake_kind'=>$intake['intake_kind'],'status'=>'POSTED','reference'=>$intake['reference'],'notes'=>null,'occurred_at'=>$now,'posted_at'=>$now,'posted_by_user_id'=>$context->userId,'reversal_of_intake_id'=>$intake['id'],'correlation_id'=>$this->requestContext->requestId(),'created_by_user_id'=>$context->userId,'created_at'=>$now,'updated_at'=>$now]);
}
catch(Throwable $e) {
if(str_contains($e->getMessage(),'Duplicate'))throw new ApiException('INTAKE_ALREADY_REVERSED','This Stock Intake has already been reversed.',409);
throw $e;
}$movementChanges=[];
$positionChanges=[];

 foreach($productIds as $pid) {
$position=$positionMap[$pid];
$reverse=array_reverse($grouped[$pid]);
foreach($reverse as $original) {
if($position['expected_quantity']!==$original['quantity_after']||$position['average_unit_cost']!==$original['average_cost_after'])throw new ApiException('INTAKE_REVERSAL_NOT_CURRENT','Current stock no longer matches the reversible Intake chain.',409);
$delta='-'.ltrim((string)$original['quantity_delta'],'-');
$movementPublic=Uuid::v4();
$this->database->table('stock_movements')->insert(['public_id'=>$movementPublic,'tenant_id'=>$context->tenantId,'shop_id'=>$context->shopId,'product_id'=>$pid,'stock_position_id'=>$position['id'],'movement_type'=>'REVERSAL','quantity_delta'=>$delta,'unit_cost_basis'=>$original['unit_cost_basis'],'quantity_before'=>$position['expected_quantity'],'quantity_after'=>$original['quantity_before'],'average_cost_before'=>$position['average_unit_cost'],'average_cost_after'=>$original['average_cost_before'],'source_kind'=>'REVERSAL','reversal_of_movement_id'=>$original['id'],'reason_code'=>'INTAKE_REVERSAL','reason_text'=>$reason,'occurred_at'=>$now,'posted_at'=>$now,'created_by_user_id'=>$context->userId,'correlation_id'=>$this->requestContext->requestId(),'created_at'=>$now]);
$internal=(int)$this->database->insertID();
$this->database->table('stock_positions')->where('id',$position['id'])->update(['expected_quantity'=>$original['quantity_before'],'average_unit_cost'=>$original['average_cost_before'],'cost_basis_state'=>$beforeStates[(int)$original['id']],'last_movement_id'=>$internal,'version'=>new \CodeIgniter\Database\RawSql('version + 1'),'updated_at'=>$now]);
$position=[...$position,'expected_quantity'=>$original['quantity_before'],'average_unit_cost'=>$original['average_cost_before'],'cost_basis_state'=>$beforeStates[(int)$original['id']],'last_movement_id'=>$internal];
$movementChanges[]=new ChangeCommand($context->tenantId,$context->shopId,'stock_movement',$movementPublic);
}$productPublic=$grouped[$pid][0]['product_public_id'];
$positionChanges[$productPublic]=new ChangeCommand($context->tenantId,$context->shopId,'stock_position',$productPublic);
}
 $this->audit->append(new AuditAppendCommand(AuditActor::tenantUser($context->userId,$context->membershipId,$context->tenantId,$context->shopId),'stock_intake.reversed',AuditCategory::INVENTORY,'stock_intake',$reversalPublic,$reason,['original_intake_public_id'=>$intake['public_id'],'line_count'=>count($originals)]));
$this->changes->publish([new ChangeCommand($context->tenantId,$context->shopId,'stock_intake',$reversalPublic),...$movementChanges,...array_values($positionChanges),new ChangeCommand($context->tenantId,$context->shopId,'stock_intake',$intake['public_id'])]);
$this->database->transCommit();
return $reversalPublic;
}
catch(Throwable $e) {
$this->database->transRollback();
throw $e;
}}
 private function authorize(ShopContext $c): void{if(!(new PermissionService($this->database,$this->audit))->allows($c->tenantId,$c->membershipId,PermissionCode::InventoryReverse->value))throw new ApiException('PERMISSION_DENIED','You do not have permission to reverse Stock Intakes.',403);
}
}
