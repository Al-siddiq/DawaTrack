<?php

declare(strict_types=1);

namespace App\Modules\ProductCatalog\Application;

use App\Exceptions\ApiException;

final readonly class ProductInput
{
    private function __construct(public string $name, public string $unitName, public string $sellingPrice)
    {
    }

    /** @param array<string,mixed> $input */
    public static function from(array $input): self
    {
        $name = trim((string) ($input['name'] ?? ''));
        $unit = trim((string) ($input['unit_name'] ?? ''));
        $price = trim((string) ($input['current_selling_price'] ?? ''));
        $errors = [];
        if ($name === '' || mb_strlen($name) > 180) $errors['name'][] = 'Enter a Product name using 180 characters or fewer.';
        if ($unit === '' || mb_strlen($unit) > 40) $errors['unit_name'][] = 'Enter one base unit using 40 characters or fewer.';
        if (! preg_match('/^(?:0|[1-9]\d{0,16})(?:\.\d{1,2})?$/', $price)) $errors['current_selling_price'][] = 'Enter a non-negative selling price with no more than two decimal places.';
        if ($errors !== []) throw new ApiException('VALIDATION_FAILED', 'Check the Product details and try again.', 422, $errors);

        [$whole, $fraction] = array_pad(explode('.', $price, 2), 2, '');
        $canonicalPrice = $whole . '.' . str_pad($fraction, 2, '0');

        return new self($name, $unit, $canonicalPrice);
    }
}
