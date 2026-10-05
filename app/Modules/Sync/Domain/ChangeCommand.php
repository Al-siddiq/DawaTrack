<?php

declare(strict_types=1);

namespace App\Modules\Sync\Domain;

final readonly class ChangeCommand
{
    public function __construct(
        public int $tenantId,
        public ?int $shopId,
        public string $resourceType,
        public string $resourcePublicId,
        public ChangeKind $kind = ChangeKind::UPSERT,
        public ?string $operationId = null,
    ) {
    }
}
