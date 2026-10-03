<?php

declare(strict_types=1);

namespace App\Libraries\Http;

final class CspNonce
{
    private string $value;

    public function __construct()
    {
        $this->value = base64_encode(random_bytes(24));
    }

    public function value(): string
    {
        return $this->value;
    }
}