<?php
declare(strict_types=1);
namespace App\Modules\TenantShop\Application;
use App\Exceptions\ApiException;
use App\Modules\Audit\Application\AuditService;
use App\Support\SensitiveReason;
use App\Support\UtcTimestamp;
use CodeIgniter\Database\BaseConnection;
final class PlatformLifecycleService
{
 public function __construct(private BaseConnection $db,private AuditService $audit){}
 public function tenant(string $publicId,string $action,int $actor,string $reason):void
 {
  $reason=SensitiveReason::require($reason);$tenant=$this->db->table('tenants')->where('public_id',$publicId)->get()->getRowArray();if(!$tenant)throw new ApiException('RESOURCE_NOT_FOUND','Tenant not found.',404);$from=$tenant['status'];$target=['suspend'=>'SUSPENDED','reactivate'=>'ACTIVE','close'=>'CLOSED'][$action]??null;$valid=($action==='suspend'&&$from==='ACTIVE')||($action==='reactivate'&&$from==='SUSPENDED')||($action==='close'&&in_array($from,['ACTIVE','SUSPENDED'],true));if(!$valid)throw new ApiException('CONFLICT','That Tenant lifecycle transition is not valid.',409);
  $now=UtcTimestamp::now();$changes=['status'=>$target,'status_reason'=>$reason,'updated_at'=>$now];if($action==='suspend')$changes+=['suspended_at'=>$now];elseif($action==='reactivate')$changes+=['activated_at'=>$now,'suspended_at'=>null];else $changes+=['closed_at'=>$now];
  $this->db->transException(true)->transStart();$this->db->table('tenants')->where('id',$tenant['id'])->update($changes);$this->audit->record(['tenant_id'=>$tenant['id'],'actor_type'=>'USER','actor_user_id'=>$actor,'action_code'=>'tenant.'.$action,'category'=>'TENANT','resource_type'=>'tenant','resource_public_id'=>$publicId,'reason'=>$reason,'change_metadata'=>['before'=>$from,'after'=>$target]]);$this->db->transComplete();
 }
 public function shop(string $publicId,string $action,int $actor,string $reason):void
 {
  $reason=SensitiveReason::require($reason);$shop=$this->db->table('shops')->where('public_id',$publicId)->get()->getRowArray();if(!$shop)throw new ApiException('RESOURCE_NOT_FOUND','Shop not found.',404);$from=$shop['status'];$target=$action==='deactivate'?'INACTIVE':($action==='reactivate'?'ACTIVE':null);if(!$target||($target===$from))throw new ApiException('CONFLICT','That Shop lifecycle transition is not valid.',409);$now=UtcTimestamp::now();$changes=['status'=>$target,'updated_at'=>$now]+($target==='ACTIVE'?['activated_at'=>$now,'deactivated_at'=>null]:['deactivated_at'=>$now]);$this->db->transException(true)->transStart();$this->db->table('shops')->where('id',$shop['id'])->update($changes);$this->audit->record(['tenant_id'=>$shop['tenant_id'],'shop_id'=>$shop['id'],'actor_type'=>'USER','actor_user_id'=>$actor,'action_code'=>'shop.'.$action,'category'=>'TENANT','resource_type'=>'shop','resource_public_id'=>$publicId,'reason'=>$reason,'change_metadata'=>['before'=>$from,'after'=>$target]]);$this->db->transComplete();
 }
}
