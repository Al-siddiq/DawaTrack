<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application;

use App\Exceptions\ApiException;
use App\Libraries\Http\RequestContext;
use App\Modules\Audit\Domain\AuditAction;
use App\Modules\Audit\Domain\AuditAppendCommand;
use App\Modules\Audit\Domain\AuditReasonPolicy;
use App\Modules\Audit\Domain\AuditResource;
use App\Support\Uuid;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\IncomingRequest;

final class AuditService
{
    private readonly AuditAuthorityValidator $authorityValidator;
    private readonly AuditCausalLinkValidator $causalLinkValidator;
    private readonly AuditMetadataPolicy $metadataPolicy;
    private readonly AuditReasonPolicy $reasonPolicy;

    public function __construct(
        private readonly BaseConnection $database,
        private readonly RequestContext $requestContext,
        private readonly ?IncomingRequest $request = null,
    ) {
        $this->authorityValidator = new AuditAuthorityValidator($database);
        $this->causalLinkValidator = new AuditCausalLinkValidator($database);
        $this->metadataPolicy = new AuditMetadataPolicy();
        $this->reasonPolicy = new AuditReasonPolicy();
    }

    /** Append immutable accountability evidence inside the caller's active transaction. */
    public function append(AuditAppendCommand $command): int
    {
        $actionCode = AuditAction::validate($command->actionCode);
        $resourceType = AuditResource::validate($command->resourceType);
        $reason = $this->reasonPolicy->validate($actionCode, $command->reason);
        $this->authorityValidator->validate($command->actor);
        $this->causalLinkValidator->validate($command->causedByAuditEventId, $command->actor);
        $resourcePublicId = $this->validateResourcePublicId($command->resourcePublicId);

        $metadata = $command->metadata === null
            ? null
            : $this->metadataPolicy->sanitize($command->metadata);
        $now = gmdate('Y-m-d H:i:s.u');
        $occurrence = $command->occurrence ?? \App\Modules\Audit\Domain\AuditOccurrence::serverNow();
        $ipAddress = $this->request?->getIPAddress();
        $userAgent = $this->request?->getUserAgent()->getAgentString();

        $this->database->table('audit_events')->insert([
            'public_id' => Uuid::v4(),
            'tenant_id' => $command->actor->tenantId,
            'shop_id' => $command->actor->shopId,
            'actor_type' => $command->actor->actorType(),
            'actor_user_id' => $command->actor->userId,
            'actor_membership_id' => $command->actor->membershipId,
            'actor_device_id' => null,
            'action_code' => $actionCode,
            'category' => $command->category->value,
            'resource_type' => $resourceType,
            'resource_public_id' => $resourcePublicId,
            'reason' => $reason,
            'change_metadata' => $metadata === null ? null : json_encode($metadata, JSON_THROW_ON_ERROR),
            'caused_by_audit_event_id' => $command->causedByAuditEventId,
            'correlation_id' => $this->requestContext->requestId(),
            'sync_operation_id' => $occurrence->operationId,
            'ip_address' => $ipAddress ? (@inet_pton($ipAddress) ?: null) : null,
            'user_agent_hash' => $userAgent ? hash('sha256', $userAgent, true) : null,
            'occurred_at' => $occurrence->formatOr($now),
            'created_at' => $now,
        ]);

        return (int) $this->database->insertID();
    }

    private function validateResourcePublicId(?string $publicId): ?string
    {
        if ($publicId === null) {
            return null;
        }

        $publicId = strtolower($publicId);
        if (! Uuid::isV4($publicId)) {
            throw new ApiException('AUDIT_CONTEXT_INVALID', 'Audit resource public identifier must be a UUIDv4.', 422);
        }

        return $publicId;
    }
}
