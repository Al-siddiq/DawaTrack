<?php

declare(strict_types=1);

namespace App\Libraries\Http;

use App\Support\Uuid;

final class RequestContext
{
    private ?string $requestId = null;

    public function initialize(?string $candidate): string
    {
        $candidate = trim((string) $candidate);
        $this->requestId = Uuid::isV4($candidate) ? $candidate : Uuid::v4();

        return $this->requestId;
    }

    public function requestId(): string
    {
        return $this->requestId ?? $this->initialize(null);
    }
}
