<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

use App\Exceptions\ApiException;
use CodeIgniter\Database\BaseConnection;

final class InventoryDecimal
{
    public function __construct(private readonly BaseConnection $database)
    {
    }

    public function quantity(string $value, string $field = 'quantity'): string
    {
        if (! preg_match('/^(?:0|[1-9]\d{0,14})(?:\.\d{1,4})?$/', trim($value)) || preg_match('/^0(?:\.0+)?$/', trim($value)) === 1) {
            throw new ApiException('VALIDATION_FAILED', 'Check the Intake item and try again.', 422, [$field => ['Enter a quantity greater than zero with no more than four decimal places.']]);
        }
        return $this->canonical(trim($value), 4);
    }

    public function cost(string $value): string
    {
        if (! preg_match('/^(?:0|[1-9]\d{0,12})(?:\.\d{1,6})?$/', trim($value))) {
            throw new ApiException('VALIDATION_FAILED', 'Check the Intake item and try again.', 422, ['unit_cost' => ['Enter a non-negative Cost price with no more than six decimal places.']]);
        }
        return $this->canonical(trim($value), 6);
    }

    public function lineAmount(string $quantity, string $cost): string
    {
        return (string) $this->database->query('SELECT CAST(ROUND(CAST(? AS DECIMAL(38,12))*CAST(? AS DECIMAL(38,12)),2) AS DECIMAL(19,2)) amount', [$quantity,$cost])->getRowArray()['amount'];
    }

    /** @return array{quantity:string,average:string,state:string} */
    public function incoming(string $quantity, string $average, string $state, string $incoming, string $cost): array
    {
        $row = $this->database->query(<<<'SQL'
SELECT
 CAST(CAST(? AS DECIMAL(19,4))+CAST(? AS DECIMAL(19,4)) AS DECIMAL(19,4)) new_quantity,
 CASE
  WHEN CAST(? AS DECIMAL(19,4)) > 0 THEN CAST(ROUND(((CAST(? AS DECIMAL(38,12))*CAST(? AS DECIMAL(38,12)))+(CAST(? AS DECIMAL(38,12))*CAST(? AS DECIMAL(38,12))))/(CAST(? AS DECIMAL(38,12))+CAST(? AS DECIMAL(38,12))),6) AS DECIMAL(19,6))
  WHEN CAST(? AS DECIMAL(19,4))+CAST(? AS DECIMAL(19,4)) > 0 THEN CAST(? AS DECIMAL(19,6))
  ELSE CAST(? AS DECIMAL(19,6))
 END new_average
SQL, [$quantity,$incoming,$quantity,$quantity,$average,$incoming,$cost,$quantity,$incoming,$quantity,$incoming,$cost,$average])->getRowArray();
        $newQuantity=(string)$row['new_quantity'];
        if ($this->compare($quantity,'0.0000') <= 0 && $this->compare($newQuantity,'0.0000') <= 0) $newState=$state==='UNESTABLISHED'?'UNESTABLISHED':'RETAINED';
        else $newState='VALID';
        return ['quantity'=>$newQuantity,'average'=>(string)$row['new_average'],'state'=>$newState];
    }

    private function canonical(string $value,int $scale): string { [$w,$f]=array_pad(explode('.',$value,2),2,''); return $w.'.'.str_pad($f,$scale,'0'); }
    private function compare(string $left,string $right): int { return (int)$this->database->query('SELECT CAST(? AS DECIMAL(19,4)) <=> CAST(? AS DECIMAL(19,4)) equal_value, CAST(? AS DECIMAL(19,4)) < CAST(? AS DECIMAL(19,4)) less_value',[$left,$right,$left,$right])->getRowArray()['equal_value']===1?0:(((int)$this->database->query('SELECT CAST(? AS DECIMAL(19,4)) < CAST(? AS DECIMAL(19,4)) less_value',[$left,$right])->getRowArray()['less_value'])===1?-1:1); }
}
