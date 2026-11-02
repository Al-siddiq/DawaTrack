<?php

declare(strict_types=1);
namespace App\Modules\Sales\Application;

use App\Exceptions\ApiException;
use CodeIgniter\Database\BaseConnection;

/** Exact arithmetic contract for later Sale/Return posting; no posting or transaction ownership. */
final class CommercialDecimal
{
    public function __construct(private readonly BaseConnection $database) {}

    public function lineAmount(string $quantity, string $unitPrice): string
    {
        return $this->extend(
            $this->positiveQuantity($quantity),
            $this->decimal($unitPrice, 2, 17, "unit_selling_price"),
        );
    }
    public function lineCogs(string $quantity, string $unitCost): string
    {
        return $this->extend(
            $this->positiveQuantity($quantity),
            $this->decimal($unitCost, 6, 13, "unit_cost"),
        );
    }
    public function positiveQuantity(string $quantity): string
    {
        $quantity = $this->decimal($quantity, 4, 15, "quantity");
        if ($quantity === "0.0000") {
            throw new ApiException(
                "VALIDATION_FAILED",
                "Quantity must be greater than zero.",
                422,
            );
        }
        return $quantity;
    }
    private function decimal(
        string $value,
        int $scale,
        int $wholeDigits,
        string $field,
    ): string {
        $value = trim($value);
        if (
            !preg_match(
                "/^(?:0|[1-9][0-9]{0," .
                    ($wholeDigits - 1) .
                    "})(?:\.[0-9]{1," .
                    $scale .
                    '})?$/D',
                $value,
            )
        ) {
            throw new ApiException(
                "VALIDATION_FAILED",
                "Use a non-negative exact decimal within the supported precision.",
                422,
                [$field => ["Check the decimal value."]],
            );
        }
        [$whole, $fraction] = array_pad(explode(".", $value, 2), 2, "");
        return $whole . "." . str_pad($fraction, $scale, "0");
    }
    private function extend(string $quantity, string $basis): string
    {
        // MySQL exact DECIMAL intermediates and half-up rounding, never PHP float.
        // Compare the wide result BEFORE casting: MySQL SELECT casts may otherwise clamp on overflow.
        $row = $this->database
            ->query(
                "SELECT ROUND(CAST(? AS DECIMAL(38,12))*CAST(? AS DECIMAL(38,12)),2) amount",
                [$quantity, $basis],
            )
            ->getRowArray();
        $amount = (string) $row["amount"];
        return $this->decimal($amount, 2, 17, "line_amount");
    }
}
