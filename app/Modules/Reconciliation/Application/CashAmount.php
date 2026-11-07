<?php

declare(strict_types=1);
namespace App\Modules\Reconciliation\Application;
use App\Exceptions\ApiException;

final class CashAmount
{
    public static function parse(mixed $input): string
    {
        // Monetary JSON inputs must be strings: do not accept already-rounded binary floats.
        if (
            !is_string($input) ||
            !preg_match(
                '/^(?:0|[1-9][0-9]{0,16})(?:\.[0-9]{1,2})?$/D',
                trim($input),
            )
        ) {
            throw new ApiException(
                "VALIDATION_FAILED",
                "Enter a non-negative Cash amount with at most two decimal places.",
                422,
                ["amount" => ["Use a Cash amount such as 0.00 or 12500.50."]],
            );
        }
        [$whole, $fraction] = array_pad(explode(".", trim($input), 2), 2, "");
        return $whole . "." . str_pad($fraction, 2, "0");
    }
}
