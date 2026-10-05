<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain;

final readonly class AuditPage
{
    /** @param list<array<string, mixed>> $events */
    public function __construct(public array $events, public ?string $nextCursor)
    {
    }
}
