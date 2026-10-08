<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

final class CostStateReconstructor{
 /** @param list<array<string,mixed>> $movements @return array<int,string> */ public function beforeStates(array $movements): array{$state='UNESTABLISHED';
$before=[];
$known=[];
foreach($movements as $movement) {
$id=(int)$movement['id'];
$before[$id]=$state;
if($movement['source_kind']==='INTAKE_ITEM') {
$state=$this->positive((string)$movement['quantity_after'])?'VALID':($state==='UNESTABLISHED'?'UNESTABLISHED':'RETAINED');
}
elseif($movement['source_kind']==='REVERSAL'&&($movement['original_source_kind']??null)==='INTAKE_ITEM') {
$original=(int)$movement['reversal_of_movement_id'];
$state=$before[$original]??$state;
}
elseif($state==='VALID'&&!$this->positive((string)$movement['quantity_after'])) {
$state='RETAINED';
}$known[$id]=$state;
}return $before;
}
 private function positive(string $value): bool{return !str_starts_with($value,'-')&&$value!=='0.0000';
}
}
