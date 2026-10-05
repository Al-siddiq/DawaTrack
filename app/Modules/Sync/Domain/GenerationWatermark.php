<?php

declare(strict_types=1);

namespace App\Modules\Sync\Domain;

final readonly class GenerationWatermark
{
    public function __construct(public string $generationUuid, public int $changeSequence)
    {
    }
}
