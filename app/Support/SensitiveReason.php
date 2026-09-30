<?php
declare(strict_types=1);
namespace App\Support;
use App\Exceptions\ApiException;
final class SensitiveReason
{
 public static function require(string $value):string{$value=trim($value);if($value===''||mb_strlen($value)>500)throw new ApiException('VALIDATION_FAILED','Enter a reason of 500 characters or fewer.',422,['reason'=>['A reason of 500 characters or fewer is required.']]);return $value;}
}
