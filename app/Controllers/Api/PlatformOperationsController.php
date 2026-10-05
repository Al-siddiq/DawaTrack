<?php
declare(strict_types=1);
namespace App\Controllers\Api;
use App\Controllers\BaseController;
use App\Exceptions\ApiException;
use App\Libraries\Http\ApiResponse;
use App\Modules\Subscription\Application\SubscriptionQuery;
use App\Modules\Subscription\Application\SubscriptionService;
use App\Modules\TenantShop\Application\PlatformLifecycleService;
use Config\Services;
use DomainException;
use Throwable;
final class PlatformOperationsController extends BaseController
{
 public function tenants(){return $this->run(function(){$db=db_connect();$rows=$db->table('tenants t')->select('t.public_id,t.legal_or_business_name,t.status,t.status_reason,t.activated_at,t.suspended_at,t.closed_at,s.public_id AS shop_public_id,s.name AS shop_name,s.status AS shop_status')->join('shops s','s.tenant_id=t.id','left')->orderBy('t.created_at','DESC')->get()->getResultArray();foreach($rows as &$row){$tenant=$db->table('tenants')->select('id')->where('public_id',$row['public_id'])->get()->getRowArray();$row['subscription']=(new SubscriptionQuery($db))->forTenant((int)$tenant['id']);$row['active_staff']=$db->table('tenant_memberships')->where(['tenant_id'=>$tenant['id'],'role'=>'STAFF','status'=>'ACTIVE'])->countAllResults();}return $this->ok($rows);});}
 public function subscription(string $tenant){return $this->run(fn()=>$this->ok((new SubscriptionService(db_connect(),Services::auditService()))->create($tenant,(int)auth()->id(),$this->request->getJSON(true)??[])));}
 public function tenantLifecycle(string $tenant,string $action){return $this->run(function()use($tenant,$action){$input=$this->request->getJSON(true)??[];(new PlatformLifecycleService(db_connect(),Services::auditService()))->tenant($tenant,$action,(int)auth()->id(),(string)($input['reason']??''));return $this->ok();});}
 public function shopLifecycle(string $shop,string $action){return $this->run(function()use($shop,$action){$input=$this->request->getJSON(true)??[];(new PlatformLifecycleService(db_connect(),Services::auditService()))->shop($shop,$action,(int)auth()->id(),(string)($input['reason']??''));return $this->ok();});}
 private function ok(array $data=[],int $status=200){return ApiResponse::success($this->response,$data,Services::requestContext()->requestId(),$status);}
 private function run(callable $fn){try{return $fn();}catch(ApiException $e){return ApiResponse::error($this->response,$e->errorCode,$e->getMessage(),Services::requestContext()->requestId(),$e->status,$e->details);}catch(DomainException $e){return ApiResponse::error($this->response,'CONFLICT',$e->getMessage(),Services::requestContext()->requestId(),409);}catch(Throwable $e){log_message('error','platform.operation_failed {request_id} {class}',['request_id'=>Services::requestContext()->requestId(),'class'=>$e::class]);return ApiResponse::error($this->response,'REQUEST_FAILED','The operation could not be completed.',Services::requestContext()->requestId(),500);}}
}
