<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application;

use App\Modules\Audit\Domain\AuditEvent;
use JsonException;

final class AuditOutputMapper
{
    public function __construct(private readonly AuditMetadataPolicy $metadataPolicy = new AuditMetadataPolicy())
    {
    }

    /** @param array<string, mixed> $row */
    public function fromRow(array $row): AuditEvent
    {
        $metadata = $this->decodeMetadata($row['change_metadata'] ?? null);

        return new AuditEvent(
            publicId: (string) $row['public_id'],
            tenantId: isset($row['tenant_id']) ? (int) $row['tenant_id'] : null,
            shopId: isset($row['shop_id']) ? (int) $row['shop_id'] : null,
            actorType: (string) $row['actor_type'],
            actorUserId: isset($row['actor_user_id']) ? (int) $row['actor_user_id'] : null,
            actorMembershipId: isset($row['actor_membership_id']) ? (int) $row['actor_membership_id'] : null,
            actionCode: (string) $row['action_code'],
            category: (string) $row['category'],
            resourceType: (string) $row['resource_type'],
            resourcePublicId: $row['resource_public_id'] ?? null,
            reason: $row['reason'] ?? null,
            metadata: $metadata,
            correlationId: $row['correlation_id'] ?? null,
            operationId: $row['sync_operation_id'] ?? null,
            occurredAt: (string) $row['occurred_at'],
            createdAt: (string) $row['created_at'],
        );
    }

    /** @return array<string, mixed> Safe API-ready evidence without storage-only numeric/security context. */
    public function toSafeArray(AuditEvent $event): array
    {
        return [
            'public_id' => $event->publicId,
            'actor_type' => $event->actorType,
            'action_code' => $event->actionCode,
            'category' => $event->category,
            'resource_type' => $event->resourceType,
            'resource_public_id' => $event->resourcePublicId,
            'reason' => $event->reason,
            'change_metadata' => $event->metadata,
            'correlation_id' => $event->correlationId,
            'operation_id' => $event->operationId,
            'occurred_at' => $event->occurredAt,
            'created_at' => $event->createdAt,
        ];
    }

    /** @return array<string|int, mixed>|null */
    private function decodeMetadata(mixed $encoded): ?array
    {
        if ($encoded === null || $encoded === '') {
            return null;
        }

        try {
            $decoded = is_array($encoded) ? $encoded : json_decode((string) $encoded, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $this->metadataPolicy->sanitize($decoded) : null;
    }
}
