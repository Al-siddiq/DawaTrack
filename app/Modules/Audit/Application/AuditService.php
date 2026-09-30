<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application;

use App\Libraries\Http\RequestContext;
use App\Support\Uuid;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\IncomingRequest;
use InvalidArgumentException;

final class AuditService
{
    public function __construct(private BaseConnection $database, private RequestContext $requestContext, private ?IncomingRequest $request = null)
    {
    }

    /** Append immutable accountability evidence inside the caller's active transaction. */
    public function record(array $event): int
    {
        foreach (['actor_type', 'action_code', 'category', 'resource_type'] as $required) {
            if (empty($event[$required])) {
                throw new InvalidArgumentException("Audit field {$required} is required.");
            }
        }

        $now = gmdate('Y-m-d H:i:s.u');
        $requestId = $this->requestContext->requestId();
        $correlationId = preg_match('/^[0-9a-f-]{36}$/i', $requestId) === 1 ? strtolower($requestId) : null;
        $ip = $this->request?->getIPAddress();
        $userAgent = $this->request?->getUserAgent()->getAgentString();

        $reason=isset($event['reason'])?trim((string)$event['reason']):null;if($reason!==null&&mb_strlen($reason)>500)throw new InvalidArgumentException('Audit reason exceeds 500 characters.');
        $metadata=isset($event['change_metadata'])?(new AuditMetadataPolicy())->sanitize($event['change_metadata']):null;
        $this->database->table('audit_events')->insert([
            'public_id' => Uuid::v4(), 'tenant_id' => $event['tenant_id'] ?? null, 'shop_id' => $event['shop_id'] ?? null,
            'actor_type' => $event['actor_type'], 'actor_user_id' => $event['actor_user_id'] ?? null,
            'actor_membership_id' => $event['actor_membership_id'] ?? null, 'actor_device_id' => null,
            'action_code' => $event['action_code'], 'category' => $event['category'], 'resource_type' => $event['resource_type'],
            'resource_public_id' => $event['resource_public_id'] ?? null, 'reason' => $reason,
            'change_metadata' => $metadata!==null ? json_encode($metadata, JSON_THROW_ON_ERROR) : null,
            'caused_by_audit_event_id' => $event['caused_by_audit_event_id'] ?? null, 'correlation_id' => $correlationId,
            'sync_operation_id' => null, 'ip_address' => $ip ? @inet_pton($ip) ?: null : null,
            'user_agent_hash' => $userAgent ? hash('sha256', $userAgent, true) : null, 'occurred_at' => $now, 'created_at' => $now,
        ]);

        return (int) $this->database->insertID();
    }
}
