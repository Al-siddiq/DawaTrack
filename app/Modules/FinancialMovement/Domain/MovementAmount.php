<?php

declare(strict_types=1);

namespace App\Modules\FinancialMovement\Domain;

use App\Exceptions\ApiException;
use App\Modules\Reconciliation\Application\CashAmount;

/** A positive DECIMAL(19,2) magnitude; commands, never the client, choose its effect. */
final readonly class MovementAmount
{
    private function __construct(public string $magnitude) {}

    public static function fromInput(mixed $value): self
    {
        try {
            $amount = CashAmount::parse($value);
        } catch (ApiException) {
            throw new ApiException('VALIDATION_FAILED', 'Enter an amount greater than zero with up to two decimal places.', 422,
                ['amount' => ['Use a number such as 1500.50, without commas or a currency sign.']]);
        }
        if ($amount === '0.00') {
            throw new ApiException('VALIDATION_FAILED', 'Enter an amount greater than zero.', 422, ['amount' => ['Enter the amount spent, such as 1500.00.']]);
        }
        return new self($amount);
    }

    public function expenseDelta(): string
    {
        return '-' . $this->magnitude;
    }

    public static function fromDelta(string $delta): self
    {
        return self::fromInput(str_starts_with($delta, '-') ? substr($delta, 1) : $delta);
    }
}
