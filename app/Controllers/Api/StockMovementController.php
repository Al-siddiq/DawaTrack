<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Enums\PermissionCode;
use App\Modules\Inventory\Application\{ManualMovementService,MovementReversalService};
use App\Modules\Inventory\Infrastructure\IntakeQuery;

final class StockMovementController extends InventoryApiController{
 public function show(string $id) {
return $this->execute(fn()=>$this->ok((new IntakeQuery(db_connect()))->movement($this->readContext(),$id)));
}
 public function create() {
return $this->execute(function() {
$c=$this->writeContext();
$id=(new ManualMovementService(db_connect(),service('auditService'),service('changeSequenceService'),service('requestContext')))->record($c,$this->request->getJSON(true)??[]);
return $this->ok((new IntakeQuery(db_connect()))->movement($c,$id),201);
});
}
 public function reverse(string $id) {
return $this->execute(function()use($id) {
$input=$this->request->getJSON(true)??[];
$c=$this->writeContext();
$new=(new MovementReversalService(db_connect(),service('auditService'),service('changeSequenceService'),service('requestContext')))->reverse($c,$id,(string)($input['reason']??''));
return $this->ok((new IntakeQuery(db_connect()))->movement($c,$new),201);
});
}
}
