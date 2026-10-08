<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

use App\Enums\PermissionCode;
use App\Exceptions\ApiException;
use App\Modules\Audit\Application\AuditService;
use App\Modules\Audit\Domain\{AuditActor,AuditAppendCommand,AuditCategory};
use App\Modules\Identity\Application\PermissionService;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use App\Support\{SensitiveReason,UtcTimestamp,Uuid};
use CodeIgniter\Database\BaseConnection;
use Throwable;

final class ReplacementIntakeService{
 public function __construct(private readonly BaseConnection $database,private readonly AuditService $audit) {
}
 /** @param array<string,mixed> $input */ public function create(ShopContext $context,string $originalPublicId,array $input): string{$this->authorize($context);
$reason=SensitiveReason::require((string)($input['reason']??''));
$this->database->transException(true)->transBegin();
try{$original=$this->database->query('SELECT * FROM stock_intakes WHERE tenant_id=? AND shop_id=? AND public_id=? FOR UPDATE',[$context->tenantId,$context->shopId,strtolower($originalPublicId)])->getRowArray();
if(!$original)throw new ApiException('INTAKE_NOT_FOUND','The original Stock Intake was not found.',404);
if($original['status']!=='POSTED'||!$this->database->table('stock_intakes')->where('reversal_of_intake_id',$original['id'])->countAllResults())throw new ApiException('REPLACEMENT_REQUIRES_REVERSAL','Reverse the current Intake before creating a replacement.',409);
if($this->database->table('stock_intakes')->where('replacement_for_intake_id',$original['id'])->countAllResults())throw new ApiException('INTAKE_REPLACEMENT_EXISTS','A replacement Intake already exists.',409);
$reference=trim((string)($input['reference']??''));
if(mb_strlen($reference)>100)throw new ApiException('VALIDATION_FAILED','Use a reference of 100 characters or fewer.',422,['reference'=>['Use 100 characters or fewer.']]);
$publicId=Uuid::v4();
$now=UtcTimestamp::now();
try{$this->database->table('stock_intakes')->insert(['public_id'=>$publicId,'tenant_id'=>$context->tenantId,'shop_id'=>$context->shopId,'intake_kind'=>'RECEIPT','status'=>'DRAFT','reference'=>$reference===''?null:$reference,'notes'=>null,'occurred_at'=>$now,'replacement_for_intake_id'=>$original['id'],'created_by_user_id'=>$context->userId,'created_at'=>$now,'updated_at'=>$now]);
}
catch(Throwable $e) {
if(str_contains($e->getMessage(),'Duplicate'))throw new ApiException('INTAKE_REPLACEMENT_EXISTS','A replacement Intake already exists.',409);
throw $e;
}$this->audit->append(new AuditAppendCommand(AuditActor::tenantUser($context->userId,$context->membershipId,$context->tenantId,$context->shopId),'stock_intake.replaced',AuditCategory::INVENTORY,'stock_intake',$publicId,$reason,['original_intake_public_id'=>$original['public_id'],'replacement_public_id'=>$publicId]));
$this->database->transCommit();
return $publicId;
}
catch(Throwable $e) {
$this->database->transRollback();
throw $e;
}}
 private function authorize(ShopContext $c): void{if(!(new PermissionService($this->database,$this->audit))->allows($c->tenantId,$c->membershipId,PermissionCode::InventoryReverse->value))throw new ApiException('PERMISSION_DENIED','You do not have permission to create a replacement Intake.',403);
}
}
