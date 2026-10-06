<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain;

final readonly class AuditAppendCommand
{
    /** @param array<string|int, mixed>|null $metadata */
    public function __construct(
        public AuditActor $actor,
        public string $actionCode,
        public AuditCategory $category,
        public string $resourceType,
        public ?string $resourcePublicId = null,
        public ?string $reason = null,
        public ?array $metadata = null,
        public ?int $causedByAuditEventId = null,
        public ?AuditOccurrence $occurrence = null,
    ) {
    }
}
