<?php

declare(strict_types=1);

namespace App\Modules\ProductCatalog\Application;

final class ProductNameNormalizer
{
    public function normalize(string $name): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($name)) ?? '';

        return mb_strtolower($collapsed, 'UTF-8');
    }
}
