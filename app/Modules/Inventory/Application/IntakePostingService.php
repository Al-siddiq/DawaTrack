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

final class IntakePostingService
{
    private readonly InventoryDecimal $decimal;
    private readonly OpeningEligibility $openingEligibility;
    public function __construct(private readonly BaseConnection $database,private readonly AuditService $audit,private readonly ChangeSequenceService $changes,private readonly RequestContext $requestContext){$this->decimal=new InventoryDecimal($database);$this->openingEligibility=new OpeningEligibility($database);}

    public function post(ShopContext $context,string $publicId): void
    {
        $this->authorize($context);$publicId=strtolower($publicId);$this->database->transException(true)->transBegin();
        try {
            $intake=$this->database->query('SELECT * FROM stock_intakes WHERE tenant_id=? AND shop_id=? AND public_id=? FOR UPDATE',[$context->tenantId,$context->shopId,$publicId])->getRowArray();
            if(!$intake)throw new ApiException('INTAKE_NOT_FOUND','The Stock Intake was not found.',404);if($intake['status']!=='DRAFT')throw new ApiException('INTAKE_ALREADY_POSTED','This Stock Intake has already been posted.',409);
            $replacementOriginal=null;$replacementReason=null;
            if($intake['replacement_for_intake_id']!==null){$replacementReason=SensitiveReason::require((string)($intake['notes']??''));$replacementOriginal=$this->database->query('SELECT id,public_id,status FROM stock_intakes WHERE tenant_id=? AND shop_id=? AND id=? FOR UPDATE',[$context->tenantId,$context->shopId,$intake['replacement_for_intake_id']])->getRowArray();if(!$replacementOriginal||$replacementOriginal['status']!=='POSTED')throw new ApiException('INVENTORY_STATE_CONFLICT','The original replacement Intake is unavailable.',409);}
            $items=$this->database->table('stock_intake_items i')->select('i.*,p.public_id product_public_id,p.name product_name,p.status product_status')->join('products p','p.tenant_id=i.tenant_id AND p.id=i.product_id')->where(['i.tenant_id'=>$context->tenantId,'i.shop_id'=>$context->shopId,'i.stock_intake_id'=>$intake['id']])->orderBy('i.line_number','ASC')->get()->getResultArray();
            if($items===[])throw new ApiException('INTAKE_EMPTY','Add at least one Product before posting.',422);
            $productIds=array_values(array_unique(array_map(static fn($i)=>(int)$i['product_id'],$items)));sort($productIds,SORT_NUMERIC);$marks=implode(',',array_fill(0,count($productIds),'?'));
            $products=$this->database->query("SELECT id,status FROM products WHERE tenant_id=? AND id IN ($marks) ORDER BY id FOR UPDATE",[$context->tenantId,...$productIds])->getResultArray();if(count($products)!==count($productIds)||array_filter($products,static fn($p)=>$p['status']!=='ACTIVE'))throw new ApiException('PRODUCT_INACTIVE','Every Product must be active when stock is posted.',409);
            $positions=$this->database->query("SELECT * FROM stock_positions WHERE tenant_id=? AND shop_id=? AND product_id IN ($marks) ORDER BY product_id FOR UPDATE",[$context->tenantId,$context->shopId,...$productIds])->getResultArray();if(count($positions)!==count($productIds))throw new ApiException('INVENTORY_STATE_CONFLICT','A required Stock Position is missing.',409);
            $byProduct=[];foreach($items as $item)$byProduct[(int)$item['product_id']][]=$item;$positionMap=[];foreach($positions as $position)$positionMap[(int)$position['product_id']]=$position;
            $movementChanges=[];$positionChanges=[];$now=UtcTimestamp::now();$movementType=$intake['intake_kind']==='OPENING'?'OPENING':'STOCK_INTAKE';
            foreach($productIds as $productId){$position=$positionMap[$productId];if($intake['intake_kind']==='OPENING')$this->openingEligibility->assertEligible($position);
                foreach($byProduct[$productId] as $item){$next=$this->decimal->incoming((string)$position['expected_quantity'],(string)$position['average_unit_cost'],(string)$position['cost_basis_state'],(string)$item['quantity'],(string)$item['unit_cost']);$movementId=Uuid::v4();$this->database->table('stock_movements')->insert(['public_id'=>$movementId,'tenant_id'=>$context->tenantId,'shop_id'=>$context->shopId,'product_id'=>$productId,'stock_position_id'=>$position['id'],'movement_type'=>$movementType,'quantity_delta'=>$item['quantity'],'unit_cost_basis'=>$item['unit_cost'],'quantity_before'=>$position['expected_quantity'],'quantity_after'=>$next['quantity'],'average_cost_before'=>$position['average_unit_cost'],'average_cost_after'=>$next['average'],'source_kind'=>'INTAKE_ITEM','stock_intake_item_id'=>$item['id'],'occurred_at'=>$intake['occurred_at'],'posted_at'=>$now,'created_by_user_id'=>$context->userId,'correlation_id'=>$this->requestContext->requestId(),'created_at'=>$now]);$internalMovement=(int)$this->database->insertID();$this->database->table('stock_positions')->where('id',$position['id'])->update(['expected_quantity'=>$next['quantity'],'average_unit_cost'=>$next['average'],'cost_basis_state'=>$next['state'],'last_movement_id'=>$internalMovement,'version'=>new \CodeIgniter\Database\RawSql('version + 1'),'updated_at'=>$now]);$position=[...$position,'expected_quantity'=>$next['quantity'],'average_unit_cost'=>$next['average'],'cost_basis_state'=>$next['state'],'last_movement_id'=>$internalMovement];$movementChanges[]=new ChangeCommand($context->tenantId,$context->shopId,'stock_movement',$movementId);}
                $productPublic=(string)$byProduct[$productId][0]['product_public_id'];$positionChanges[$productPublic]=new ChangeCommand($context->tenantId,$context->shopId,'stock_position',$productPublic);
            }
            $this->database->table('stock_intakes')->where('id',$intake['id'])->update(['status'=>'POSTED','posted_at'=>$now,'posted_by_user_id'=>$context->userId,'correlation_id'=>$this->requestContext->requestId(),'updated_at'=>$now]);$total=(string)$this->database->query('SELECT CAST(SUM(line_cost_amount) AS DECIMAL(19,2)) total FROM stock_intake_items WHERE stock_intake_id=?',[$intake['id']])->getRowArray()['total'];
            $this->audit->append(new AuditAppendCommand(AuditActor::tenantUser($context->userId,$context->membershipId,$context->tenantId,$context->shopId),'stock_intake.posted',AuditCategory::INVENTORY,'stock_intake',$publicId,metadata:['intake_kind'=>$intake['intake_kind'],'line_count'=>count($items),'total_amount'=>$total,'occurred_at'=>$intake['occurred_at']]));
            $intakeChanges=[new ChangeCommand($context->tenantId,$context->shopId,'stock_intake',$publicId)];
            if($replacementOriginal!==null){$this->audit->append(new AuditAppendCommand(AuditActor::tenantUser($context->userId,$context->membershipId,$context->tenantId,$context->shopId),'stock_intake.replaced',AuditCategory::INVENTORY,'stock_intake',$publicId,$replacementReason,['original_intake_public_id'=>$replacementOriginal['public_id'],'replacement_public_id'=>$publicId,'intake_kind'=>$intake['intake_kind']]));$intakeChanges[]=new ChangeCommand($context->tenantId,$context->shopId,'stock_intake',(string)$replacementOriginal['public_id']);}
            $this->changes->publish([...$intakeChanges,...$movementChanges,...array_values($positionChanges)]);$this->database->transCommit();
        } catch(Throwable $e){$this->database->transRollback();throw $e;}
    }
    private function authorize(ShopContext $c): void{if(!(new PermissionService($this->database,$this->audit))->allows($c->tenantId,$c->membershipId,PermissionCode::InventoryIntake->value))throw new ApiException('PERMISSION_DENIED','You do not have permission to post Stock Intakes.',403);}
}
