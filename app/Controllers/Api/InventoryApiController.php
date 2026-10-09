<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Enums\PermissionCode;
use App\Exceptions\ApiException;
use App\Libraries\Http\ApiResponse;
use App\Modules\Access\Application\AccessGate;
use App\Modules\Identity\Application\PermissionService;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use Config\Services;
use Throwable;

abstract class InventoryApiController extends BaseController{
 protected function readContext(): ShopContext{$c=$this->context();
$d=(new AccessGate(db_connect()))->evaluate($c->tenantId,$c->shopId);
if(in_array($d->mode,['BLOCKED','CLOSED'],true))throw new ApiException($d->mode,$d->message,403);
if(!(new PermissionService(db_connect(),Services::auditService()))->allows($c->tenantId,$c->membershipId,PermissionCode::InventoryView->value))throw new ApiException('PERMISSION_DENIED','You do not have permission to view Inventory.',403);
return $c;
}
 protected function writeContext(): ShopContext{$c=$this->context();
$d=(new AccessGate(db_connect()))->evaluate($c->tenantId,$c->shopId);
if(!$d->permitsMutation())throw new ApiException($d->mode,$d->message,403);
return $c;
}
 private function context(): ShopContext{return ShopContext::resolve(db_connect(),(int)auth()->id(),$this->request->getServer('HTTP_HOST')??'');
}
 protected function ok(array $data=[],int $status=200) {
return ApiResponse::success($this->response,$data,Services::requestContext()->requestId(),$status);
}
 protected function execute(callable $operation) {
try{return $operation();
}
catch(ApiException $e) {
return ApiResponse::error($this->response,$e->errorCode,$e->getMessage(),Services::requestContext()->requestId(),$e->status,$e->details);
}
catch(Throwable $e) {
log_message('error','inventory.request_failed {request_id} {exception_class}',['request_id'=>Services::requestContext()->requestId(),'exception_class'=>$e::class]);
return ApiResponse::error($this->response,'REQUEST_FAILED','The request could not be completed.',Services::requestContext()->requestId(),500);
}}
}
