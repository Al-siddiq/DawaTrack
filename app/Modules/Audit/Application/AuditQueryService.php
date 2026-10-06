<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application;

use App\Exceptions\ApiException;
use App\Modules\Audit\Domain\AuditPage;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;

final class AuditQueryService
{
    public function __construct(private readonly BaseConnection $database, private readonly AuditOutputMapper $mapper = new AuditOutputMapper())
    {
    }

    public function ownerPage(int $tenantId, int $shopId, AuditQuery $query): AuditPage
    {
        return $this->page($query, $tenantId, $shopId, false);
    }

    public function platformPage(AuditQuery $query): AuditPage
    {
        return $this->page($query, null, null, true);
    }

    public function ownerDetail(int $tenantId, int $shopId, string $publicId): array
    {
        return $this->detail($publicId, $tenantId, $shopId, false);
    }

    public function platformDetail(string $publicId): array
    {
        return $this->detail($publicId, null, null, true);
    }

    public function supportPage(int $tenantId, int $shopId, AuditQuery $query): AuditPage
    {
        return $this->page($query, $tenantId, $shopId, false);
    }

    public function supportDetail(int $tenantId, int $shopId, string $publicId): array
    {
        return $this->detail($publicId, $tenantId, $shopId, false);
    }

    private function page(AuditQuery $query, ?int $tenantId, ?int $shopId, bool $platformOnly): AuditPage
    {
        $builder = $this->baseBuilder();
        $this->applyScope($builder, $tenantId, $shopId, $platformOnly);
        $this->applyFilters($builder, $query);
        if ($query->cursor !== null) {
            $cursor = $this->database->table('audit_events a')
                ->select('a.id')
                ->where([
                    'a.public_id' => $query->cursor->publicId,
                    'a.occurred_at' => $query->cursor->occurredAt,
                ]);
            $this->applyScope($cursor, $tenantId, $shopId, $platformOnly);
            $cursorRow = $cursor->get()->getRowArray();
            if ($cursorRow === null) {
                throw new ApiException('AUDIT_PAGE_INVALID', 'The Audit page cursor is invalid or expired.', 422);
            }

            $builder->groupStart()
                ->where('a.occurred_at <', $query->cursor->occurredAt)
                ->orGroupStart()
                    ->where('a.occurred_at', $query->cursor->occurredAt)
                    ->where('a.id <', $cursorRow['id'])
                ->groupEnd()
            ->groupEnd();
        }

        $rows = $builder->orderBy('a.occurred_at', 'DESC')->orderBy('a.id', 'DESC')->get($query->pageSize + 1)->getResultArray();
        $hasMore = count($rows) > $query->pageSize;
        if ($hasMore) {
            array_pop($rows);
        }

        $events = array_map(fn (array $row): array => $this->present($row, false), $rows);
        $last = $hasMore ? end($rows) : null;
        $next = is_array($last) ? AuditCursor::encode((string) $last['occurred_at'], (string) $last['public_id']) : null;

        return new AuditPage($events, $next);
    }

    private function detail(string $publicId, ?int $tenantId, ?int $shopId, bool $platformOnly): array
    {
        $builder = $this->baseBuilder()->where('a.public_id', strtolower($publicId));
        $this->applyScope($builder, $tenantId, $shopId, $platformOnly);
        $row = $builder->get()->getRowArray();
        if ($row === null) {
            throw new ApiException('AUDIT_EVENT_NOT_FOUND', 'The Audit event was not found.', 404);
        }

        $detail = $this->present($row, true);
        $scope = $this->database->table('audit_events')->select('public_id,action_code,resource_type,resource_public_id,occurred_at,correlation_id');
        $this->applyScope($scope, $tenantId, $shopId, $platformOnly, '');
        $related = $scope->groupStart()->where('id', $row['caused_by_audit_event_id'] ?? 0)->orWhere('caused_by_audit_event_id', $row['id']);
        if ($row['correlation_id'] !== null) {
            $related->orWhere('correlation_id', $row['correlation_id']);
        }

        $detail['related_events'] = $related->groupEnd()->where('id !=', $row['id'])->orderBy('occurred_at', 'DESC')->get(10)->getResultArray();

        return $detail;
    }

    private function baseBuilder(): BaseBuilder
    {
        return $this->database->table('audit_events a')
            ->select('a.*, u.username AS actor_name, m.role AS actor_role, cause.public_id AS caused_by_public_id')
            ->join('users u', 'u.id = a.actor_user_id', 'left')
            ->join('tenant_memberships m', 'm.id = a.actor_membership_id AND m.tenant_id = a.tenant_id', 'left')
            ->join('audit_events cause', 'cause.id = a.caused_by_audit_event_id', 'left');
    }

    private function applyScope(
        BaseBuilder $builder,
        ?int $tenantId,
        ?int $shopId,
        bool $platformOnly,
        string $alias = 'a.',
    ): void {
        if ($platformOnly) {
            $builder->where($alias . 'tenant_id', null);

            return;
        }

        $builder->where($alias . 'tenant_id', $tenantId)
            ->groupStart()
                ->where($alias . 'shop_id', $shopId)
                ->orWhere($alias . 'shop_id', null)
            ->groupEnd();
    }

    private function applyFilters(BaseBuilder $builder, AuditQuery $query): void
    {
        $filters = [
            'a.occurred_at >=' => $query->from,
            'a.occurred_at <=' => $query->to,
            'a.category' => $query->category,
            'a.action_code' => $query->action,
            'u.username' => $query->actor,
            'a.resource_type' => $query->resourceType,
            'a.resource_public_id' => $query->resourcePublicId,
            'a.correlation_id' => $query->correlationId,
        ];

        foreach ($filters as $column => $value) {
            if ($value !== null) {
                $builder->where($column, $value);
            }
        }
    }

    private function present(array $row, bool $detail): array
    {
        $safe = $this->mapper->toSafeArray($this->mapper->fromRow($row));
        $safe['actor'] = [
            'type' => $row['actor_type'],
            'name' => $row['actor_name'] ?: ($row['actor_type'] === 'SYSTEM' ? 'DawaTrack system' : 'Platform Administrator'),
            'role' => $row['actor_role'],
            'authority' => $this->authorityLabel($row),
        ];
        $safe['caused_by_public_id'] = $row['caused_by_public_id'] ?? null;

        if (! $detail) {
            unset($safe['change_metadata'], $safe['operation_id']);
        }

        return $safe;
    }

    private function authorityLabel(array $row): string
    {
        if ($row['actor_type'] === 'SYSTEM') {
            return 'SYSTEM';
        }

        if ($row['actor_membership_id'] !== null) {
            return 'TENANT';
        }

        if ($row['resource_type'] === 'user_identity') {
            return 'IDENTITY';
        }

        return $row['tenant_id'] === null ? 'PLATFORM' : 'PLATFORM_SUPPORT';
    }
}
