<?php

declare(strict_types=1);
namespace App\Controllers\Api;
use App\Controllers\BaseController;use App\Enums\PermissionCode;use App\Exceptions\ApiException;use App\Libraries\Http\ApiResponse;use App\Modules\Access\Application\AccessGate;use App\Modules\Identity\Application\PermissionService;use App\Modules\Inventory\Application\{IntakeDraftService,IntakePostingService};use App\Modules\Inventory\Infrastructure\IntakeQuery;use App\Modules\TenantShop\Infrastructure\ShopContext;use Config\Services;use Throwable;
final class StockIntakeController extends BaseController{
 public function index(){return $this->run(fn()=>$this->ok((new IntakeQuery(db_connect()))->listing($this->readContext())));}
 public function productHistory(string $id){return $this->run(fn()=>$this->ok((new IntakeQuery(db_connect()))->productHistory($this->readContext(),$id)));}
 public function show(string $id){return $this->run(fn()=>$this->ok((new IntakeQuery(db_connect()))->detail($this->readContext(),$id)));}
 public function create(){return $this->run(function(){$c=$this->writeContext();$id=$this->draft()->create($c,$this->request->getJSON(true)??[]);return $this->ok((new IntakeQuery(db_connect()))->detail($c,$id),201);});}
 public function update(string $id){return $this->run(function()use($id){$c=$this->writeContext();$this->draft()->updateHeader($c,$id,$this->request->getJSON(true)??[]);return $this->ok((new IntakeQuery(db_connect()))->detail($c,$id));});}
 public function addItem(string $id){return $this->run(function()use($id){$c=$this->writeContext();$this->draft()->addItem($c,$id,$this->request->getJSON(true)??[]);return $this->ok((new IntakeQuery(db_connect()))->detail($c,$id),201);});}
 public function updateItem(string $id,string $item){return $this->run(function()use($id,$item){$c=$this->writeContext();$this->draft()->updateItem($c,$id,$item,$this->request->getJSON(true)??[]);return $this->ok((new IntakeQuery(db_connect()))->detail($c,$id));});}
 public function removeItem(string $id,string $item){return $this->run(function()use($id,$item){$c=$this->writeContext();$this->draft()->removeItem($c,$id,$item);return $this->ok((new IntakeQuery(db_connect()))->detail($c,$id));});}
 public function discard(string $id){return $this->run(function()use($id){$this->draft()->discard($this->writeContext(),$id);return $this->ok();});}
 public function post(string $id){return $this->run(function()use($id){$c=$this->writeContext();(new IntakePostingService(db_connect(),Services::auditService(),Services::changeSequenceService(),Services::requestContext()))->post($c,$id);return $this->ok((new IntakeQuery(db_connect()))->detail($c,$id));});}
 private function draft(): IntakeDraftService{return new IntakeDraftService(db_connect(),Services::auditService());}
 private function context(): ShopContext{return ShopContext::resolve(db_connect(),(int)auth()->id(),$this->request->getServer('HTTP_HOST')??'');}
 private function readContext(): ShopContext{$c=$this->context();$d=(new AccessGate(db_connect()))->evaluate($c->tenantId,$c->shopId);if(in_array($d->mode,['BLOCKED','CLOSED'],true))throw new ApiException($d->mode,$d->message,403);if(!(new PermissionService(db_connect(),Services::auditService()))->allows($c->tenantId,$c->membershipId,PermissionCode::InventoryView->value))throw new ApiException('PERMISSION_DENIED','You do not have permission to view Stock Intakes.',403);return $c;}
 private function writeContext(): ShopContext{$c=$this->context();$d=(new AccessGate(db_connect()))->evaluate($c->tenantId,$c->shopId);if(!$d->permitsMutation())throw new ApiException($d->mode,$d->message,403);return $c;}
 private function ok(array $d=[],int $s=200){return ApiResponse::success($this->response,$d,Services::requestContext()->requestId(),$s);}
 private function run(callable $op){try{return $op();}catch(ApiException $e){return ApiResponse::error($this->response,$e->errorCode,$e->getMessage(),Services::requestContext()->requestId(),$e->status,$e->details);}catch(Throwable $e){log_message('error','intake.request_failed {request_id} {exception_class}',['request_id'=>Services::requestContext()->requestId(),'exception_class'=>$e::class]);return ApiResponse::error($this->response,'REQUEST_FAILED','The request could not be completed.',Services::requestContext()->requestId(),500);}}
}
