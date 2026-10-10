<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

use App\Exceptions\ApiException;
use CodeIgniter\Database\BaseConnection;

final class InventoryProjection{
 public function __construct(private readonly BaseConnection $database) {
}
 public function positiveQuantity(string $value): string{$value=trim($value);
if(!preg_match('/^(?:0|[1-9]\d{0,14})(?:\.\d{1,4})?$/',$value)||preg_match('/^0(?:\.0+)?$/',$value))throw new ApiException('INVALID_MOVEMENT_QUANTITY','Enter a quantity greater than zero with no more than four decimal places.',422,['quantity'=>['Enter a quantity greater than zero.']]);
[$w,$f]=array_pad(explode('.',$value,2),2,'');
return $w.'.'.str_pad($f,4,'0');
}
 public function signed(string $magnitude,bool $positive): string{$q=$this->positiveQuantity($magnitude);
return $positive?$q:'-'.$q;
}
 public function add(string $current,string $delta): string{return (string)$this->database->query('SELECT CAST(CAST(? AS DECIMAL(19,4))+CAST(? AS DECIMAL(19,4)) AS DECIMAL(19,4)) value',[$current,$delta])->getRowArray()['value'];
}
 public function stateAfterNonAcquisition(string $state,string $after): string{if($state==='UNESTABLISHED')return 'UNESTABLISHED';
if($state==='RETAINED')return 'RETAINED';
return str_starts_with($after,'-')||$after==='0.0000'?'RETAINED':'VALID';
}
}
