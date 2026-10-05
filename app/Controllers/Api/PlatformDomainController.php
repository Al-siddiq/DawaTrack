<?php
declare(strict_types=1);
namespace App\Controllers\Api;
use App\Controllers\BaseController;
use App\Exceptions\ApiException;
use App\Libraries\Http\ApiResponse;
use App\Modules\BrandingDomain\Application\DomainService;
use Config\Services;
use DomainException;
use Throwable;
final class PlatformDomainController extends BaseController
{
 public function index(){return $this->run(fn()=>ApiResponse::success($this->response,db_connect()->table('shop_domains d')->select('d.public_id,d.hostname,d.domain_type,d.verification_status,d.certificate_status,d.is_active,d.is_primary,d.verification_reference,d.verified_at,d.activated_at,s.name AS shop_name')->join('shops s','s.id=d.shop_id AND s.tenant_id=d.tenant_id')->orderBy('d.created_at','DESC')->get()->getResultArray(),Services::requestContext()->requestId()));}
 public function tls(string $id){return $this->action($id,'tls');} public function activate(string $id){return $this->action($id,'activate');} public function primary(string $id){return $this->action($id,'primary');} public function deactivate(string $id){return $this->action($id,'deactivate');} public function fallback(string $id){return $this->action($id,'fallback');}
 private function action(string $id,string $action){return $this->run(function()use($id,$action){$input=$this->request->getJSON(true)??[];$service=new DomainService(db_connect(),Services::auditService());$reason=(string)($input['reason']??'');$actor=(int)auth()->id();if($action==='tls')$service->tls($id,$actor,(bool)($input['ready']??false),$reason);else $service->{$action}($id,$actor,$reason);return ApiResponse::success($this->response,[],Services::requestContext()->requestId());});}
 private function run(callable $operation){try{return $operation();}catch(ApiException $e){return ApiResponse::error($this->response,$e->errorCode,$e->getMessage(),Services::requestContext()->requestId(),$e->status,$e->details);}catch(DomainException $e){return ApiResponse::error($this->response,'DOMAIN_STATE_CONFLICT',$e->getMessage(),Services::requestContext()->requestId(),409);}catch(Throwable $e){log_message('error','platform.domain_failed {request_id} {class}',['request_id'=>Services::requestContext()->requestId(),'class'=>$e::class]);return ApiResponse::error($this->response,'REQUEST_FAILED','The domain operation could not be completed.',Services::requestContext()->requestId(),500);}}
}
