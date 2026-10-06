<?php

declare(strict_types=1);

namespace App\Modules\Sync\Domain;

final readonly class PublishedChange
{
    public function __construct(public int $sequence, public string $generationUuid, public string $resourceType, public string $resourcePublicId)
    {
    }
}
