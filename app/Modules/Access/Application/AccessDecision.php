<?php

declare(strict_types=1);

namespace App\Modules\Access\Application;

final readonly class AccessDecision
{
    public function __construct(public string $mode, public string $message, public ?string $planCode = null, public ?string $endsAt = null)
    {
    }

    public function permitsMutation(): bool
    {
        return $this->mode === 'FULL';
    }
}
