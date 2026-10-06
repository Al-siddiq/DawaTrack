<?php

declare(strict_types=1);

namespace App\Modules\Sync\Application;

use App\Exceptions\ApiException;
use App\Libraries\Http\RequestContext;
use App\Modules\Sync\Domain\ChangeCommand;
use App\Modules\Sync\Domain\GenerationWatermark;
use App\Modules\Sync\Domain\PublishedChange;
use App\Support\Uuid;
use CodeIgniter\Database\BaseConnection;

final class ChangeSequenceService
{
    private const RESOURCE_TYPES = [
        'tenant',
        'shop',
        'tenant_membership',
        'shop_branding_profile',
        'shop_brand_asset',
        'shop_domain',
        'subscription',
        'product',
        'stock_position',
    ];

    public function __construct(
        private readonly BaseConnection $database,
        private readonly RequestContext $requestContext,
    ) {
    }

    /** @param list<ChangeCommand> $commands @return list<PublishedChange> */
    public function publish(array $commands): array
    {
        $this->assertTransactionActive();
        if ($commands === []) {
            return [];
        }
        foreach ($commands as $command) {
            if (! $command instanceof ChangeCommand) {
                throw new \InvalidArgumentException('Change publication accepts ChangeCommand values only.');
            }
            $this->validate($command);
        }
        $generation = $this->lockActiveGeneration();
        $published = [];
        foreach ($commands as $command) {
            $now = gmdate('Y-m-d H:i:s.u');
            $this->database->table('sync_changes')->insert([
                'sync_generation_id' => $generation['id'],
                'tenant_id' => $command->tenantId,
                'shop_id' => $command->shopId,
                'resource_type' => $command->resourceType,
                'resource_public_id' => strtolower($command->resourcePublicId),
                'change_kind' => $command->kind->value,
                'changed_at' => $now,
                'caused_by_operation_id' => $command->operationId === null ? null : strtolower($command->operationId),
                'correlation_id' => $this->requestContext->requestId(),
            ]);
            $published[] = new PublishedChange(
                (int) $this->database->insertID(),
                (string) $generation['generation_uuid'],
                $command->resourceType,
                strtolower($command->resourcePublicId),
            );
        }
        return $published;
    }

    /** Locks the generation until the caller commits; no business locks may be acquired afterwards. */
    public function acquireWatermark(): GenerationWatermark
    {
        $this->assertTransactionActive();
        $generation = $this->lockActiveGeneration();
        $latest = $this->database->query(
            'SELECT change_sequence FROM sync_changes WHERE sync_generation_id = ? ORDER BY change_sequence DESC LIMIT 1 FOR UPDATE',
            [$generation['id']],
        )->getRowArray();
        return new GenerationWatermark((string) $generation['generation_uuid'], (int) ($latest['change_sequence'] ?? 0));
    }

    /** @return array{id:int,generation_uuid:string} */
    private function lockActiveGeneration(): array
    {
        $rows = $this->database->query("SELECT id,generation_uuid FROM sync_generations WHERE status='ACTIVE' FOR UPDATE")->getResultArray();
        if (count($rows) !== 1) {
            throw new ApiException('SYNC_GENERATION_UNAVAILABLE', 'The active server generation is unavailable.', 503);
        }
        return ['id' => (int) $rows[0]['id'], 'generation_uuid' => (string) $rows[0]['generation_uuid']];
    }

    private function assertTransactionActive(): void
    {
        $row = $this->database->query('SELECT @@session.in_transaction AS active_transaction')->getRowArray();
        if ((int) ($row['active_transaction'] ?? 0) !== 1) {
            throw new \LogicException('Change publication requires the owning transaction.');
        }
    }

    private function validate(ChangeCommand $command): void
    {
        $resourceId = strtolower($command->resourcePublicId);
        $validResource = in_array($command->resourceType, self::RESOURCE_TYPES, true);
        if ($command->tenantId < 1 || ! $validResource || ! Uuid::isV4($resourceId)) {
            throw new ApiException('CHANGE_CONTEXT_INVALID', 'The change context is invalid.', 422);
        }
        if ($command->operationId !== null && ! Uuid::isV4(strtolower($command->operationId))) {
            throw new ApiException('CHANGE_CONTEXT_INVALID', 'The change operation identifier is invalid.', 422);
        }
        if ($command->shopId === null) {
            return;
        }
        $validShop = $this->database->table('shops')->where([
            'id' => $command->shopId,
            'tenant_id' => $command->tenantId,
        ])->countAllResults() === 1;
        if (! $validShop) {
            throw new ApiException('CHANGE_CONTEXT_INVALID', 'The change context is invalid.', 422);
        }
    }
}
