<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class ApiException extends RuntimeException
{
    /** @param array<string, list<string>> $details */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }
}
