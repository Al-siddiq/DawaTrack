<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain;

final readonly class AuditEvent
{
    /** @param array<string|int, mixed>|null $metadata */
    public function __construct(
        public string $publicId,
        public ?int $tenantId,
        public ?int $shopId,
        public string $actorType,
        public ?int $actorUserId,
        public ?int $actorMembershipId,
        public string $actionCode,
        public string $category,
        public string $resourceType,
        public ?string $resourcePublicId,
        public ?string $reason,
        public ?array $metadata,
        public ?string $correlationId,
        public ?string $operationId,
        public string $occurredAt,
        public string $createdAt,
    ) {
    }
}
