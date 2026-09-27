<?php

declare(strict_types=1);

namespace App\Libraries\Http;

final class RequestContext
{
    private ?string $requestId = null;

    public function initialize(?string $candidate): string
    {
        $candidate = trim((string) $candidate);
        $this->requestId = preg_match('/^[A-Za-z0-9._-]{8,100}$/', $candidate) === 1
            ? $candidate
            : bin2hex(random_bytes(16));

        return $this->requestId;
    }

    public function requestId(): string
    {
        return $this->requestId ?? $this->initialize(null);
    }
}
