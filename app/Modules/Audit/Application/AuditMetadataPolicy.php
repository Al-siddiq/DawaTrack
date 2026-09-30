<?php
declare(strict_types=1);
namespace App\Modules\Audit\Application;
use InvalidArgumentException;
final class AuditMetadataPolicy
{
 private const FORBIDDEN='/password|passwd|mfa.*(code|secret)|recovery.*secret|magic.*link|verification.*token|tls.*(key|secret)|cookie|session.*token|auth.*token|bearer|api.*secret|raw.*(bytes|binary)|image.*bytes/i';
 public function sanitize(mixed $value,int $depth=0):mixed
 {
  if($depth>4)throw new InvalidArgumentException('Audit metadata nesting exceeds the safe limit.');
  if(is_string($value))return mb_substr($value,0,500);
  if(is_int($value)||is_float($value)||is_bool($value)||$value===null)return $value;
  if(!is_array($value))throw new InvalidArgumentException('Audit metadata must contain only scalar values and arrays.');
  if(count($value)>40)throw new InvalidArgumentException('Audit metadata has too many entries.');
  $safe=[];foreach($value as $key=>$item){$name=(string)$key;if(preg_match(self::FORBIDDEN,$name))throw new InvalidArgumentException('Audit metadata contains a forbidden sensitive key.');$safe[$key]=$this->sanitize($item,$depth+1);}return $safe;
 }
}
