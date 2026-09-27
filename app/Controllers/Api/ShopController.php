<?php

declare(strict_types=1);
namespace App\Controllers\Api;
use App\Controllers\BaseController;
use App\Libraries\Http\ApiResponse;
use App\Modules\Access\Application\AccessGate;
use App\Modules\BrandingDomain\Application\BrandingService;
use App\Modules\BrandingDomain\Application\DomainService;
use App\Modules\Identity\Application\PermissionService;
use App\Modules\Identity\Application\StaffService;
use App\Modules\TenantShop\Application\ShopSettingsService;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use Config\Services;
use DomainException;
use Throwable;
final class ShopController extends BaseController {
 private function context(): ShopContext { return ShopContext::resolve(db_connect(),(int)auth()->id(),$this->request->getServer('HTTP_HOST')??''); }
 private function mutableContext(): ShopContext { $context=$this->context(); $decision=(new AccessGate(db_connect()))->evaluate($context->tenantId,$context->shopId); if(!$decision->permitsMutation()) throw new DomainException($decision->message); return $context; }
 private function ok(array $data=[],int $status=200){ return ApiResponse::success($this->response,$data,Services::requestContext()->requestId(),$status); }
 private function execute(callable $operation){ try{return $operation();}catch(DomainException $e){return ApiResponse::error($this->response,'VALIDATION_FAILED',$e->getMessage(),Services::requestContext()->requestId(),422);}catch(Throwable $e){log_message('error','phase1.request_failed {request_id}',['request_id'=>Services::requestContext()->requestId(),'exception'=>$e]);return ApiResponse::error($this->response,'REQUEST_FAILED','The request could not be completed.',Services::requestContext()->requestId(),500);} }
 public function contextInfo(){ return $this->execute(function(){ $c=$this->context(); $gate=(new AccessGate(db_connect()))->evaluate($c->tenantId,$c->shopId); return $this->ok(['role'=>$c->role,'access'=>['mode'=>$gate->mode,'message'=>$gate->message,'plan_code'=>$gate->planCode,'ends_at'=>$gate->endsAt]]);}); }
 public function staff(){ return $this->execute(fn()=>$this->ok((new StaffService(db_connect(),Services::auditService()))->listing($this->context()))); }
 public function createStaff(){ return $this->execute(fn()=>$this->ok((new StaffService(db_connect(),Services::auditService()))->create($this->mutableContext(),$this->request->getJSON(true)??[]),201)); }
 public function staffStatus(int $id){ return $this->execute(function()use($id){$in=$this->request->getJSON(true)??[];(new StaffService(db_connect(),Services::auditService()))->setActive($this->mutableContext(),$id,(bool)($in['active']??false),(string)($in['reason']??''));return $this->ok();}); }
 public function grant(int $id){ return $this->execute(function()use($id){$c=$this->mutableContext();$c->requireOwner();$in=$this->request->getJSON(true)??[];(new PermissionService(db_connect(),Services::auditService()))->grant($c->tenantId,$id,(string)($in['permission_code']??''),$c->userId,$c->membershipId);return $this->ok();}); }
 public function revoke(int $id){ return $this->execute(function()use($id){$c=$this->mutableContext();$c->requireOwner();$in=$this->request->getJSON(true)??[];(new PermissionService(db_connect(),Services::auditService()))->revoke($c->tenantId,$id,(string)($in['permission_code']??''),$c->userId,$c->membershipId,(string)($in['reason']??''));return $this->ok();}); }
 public function settings(){ return $this->execute(fn()=>$this->ok((new ShopSettingsService(db_connect(),Services::auditService()))->get($this->context()))); }
 public function updateSettings(){ return $this->execute(fn()=>$this->ok((new ShopSettingsService(db_connect(),Services::auditService()))->update($this->mutableContext(),$this->request->getJSON(true)??[]))); }
 public function branding(){ return $this->execute(function(){ $c=$this->context(); return $this->ok(db_connect()->table('shop_branding_profiles')->where(['tenant_id'=>$c->tenantId,'shop_id'=>$c->shopId])->get()->getRowArray()??[]);}); }
 public function updateBranding(){ return $this->execute(function(){ $c=$this->mutableContext();$c->requireOwner();(new BrandingService(db_connect(),Services::auditService()))->updateProfile($c->tenantId,$c->shopId,$c->userId,$c->membershipId,$this->request->getJSON(true)??[]);return $this->branding();}); }
 public function assets(){ return $this->execute(function(){ $c=$this->context();return $this->ok(db_connect()->table('shop_brand_assets')->select('public_id,asset_type,mime_type,width_px,height_px,file_size_bytes,activated_at')->where(['tenant_id'=>$c->tenantId,'shop_id'=>$c->shopId,'is_active'=>1])->get()->getResultArray());}); }
 public function uploadAsset(){ return $this->execute(function(){ $c=$this->mutableContext();$c->requireOwner();$result=(new BrandingService(db_connect(),Services::auditService()))->replaceAsset($c->tenantId,$c->shopId,$c->userId,$c->membershipId,(string)$this->request->getPost('asset_type'),$this->request->getFile('asset'));return $this->ok($result,201);}); }
 public function domains(){ return $this->execute(function(){ $c=$this->context();return $this->ok(db_connect()->table('shop_domains')->select('public_id,hostname,domain_type,verification_status,certificate_status,is_active,is_primary,verification_method')->where(['tenant_id'=>$c->tenantId,'shop_id'=>$c->shopId])->get()->getResultArray());}); }
 public function requestDomain(){ return $this->execute(function(){ $c=$this->mutableContext();$c->requireOwner();$in=$this->request->getJSON(true)??[];return $this->ok((new DomainService(db_connect(),Services::auditService()))->requestCustom($c->tenantId,$c->shopId,(string)($in['hostname']??''),$c->userId),201);}); }
 public function subscription(){ return $this->execute(function(){ $c=$this->context();$c->requireOwner();$gate=(new AccessGate(db_connect()))->evaluate($c->tenantId,$c->shopId);$now=gmdate('Y-m-d H:i:s.u');$sub=db_connect()->table('subscriptions')->where('tenant_id',$c->tenantId)->orderBy('starts_at','DESC')->get(1)->getRowArray();$count=db_connect()->table('tenant_memberships')->where(['tenant_id'=>$c->tenantId,'role'=>'STAFF','status'=>'ACTIVE'])->countAllResults();return $this->ok(['entitlement'=>$sub,'active_staff'=>$count,'access'=>['mode'=>$gate->mode,'message'=>$gate->message]]);}); }
 public function permissions(int $id){return $this->execute(fn()=>$this->ok((new StaffService(db_connect(),Services::auditService()))->permissions($this->context(),$id)));}
 public function resetPassword(int $id){return $this->execute(function()use($id){$in=$this->request->getJSON(true)??[];(new StaffService(db_connect(),Services::auditService()))->resetPassword($this->mutableContext(),$id,(string)($in['password']??''),(string)($in['reason']??''));return $this->ok();});}

}
