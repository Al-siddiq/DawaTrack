<?php

declare(strict_types=1);

namespace App\Modules\Device\Application;

use App\Exceptions\ApiException;
use App\Modules\Audit\Application\AuditService;
use App\Modules\Audit\Domain\{AuditActor, AuditAppendCommand, AuditCategory};
use App\Modules\Device\Infrastructure\{DeviceQuery, DeviceSchema};
use App\Modules\Sales\Application\CommercialAccess;
use App\Modules\Sync\Application\ChangeSequenceService;
use App\Modules\Sync\Domain\ChangeCommand;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use App\Support\{SensitiveReason, UtcTimestamp};
use CodeIgniter\Database\BaseConnection;

/** Commands own one CommercialAccess transaction; Device locks precede Audit/publication. */
final class DeviceService
{
    public function __construct(
        private readonly BaseConnection $database,
        private readonly CommercialAccess $access,
        private readonly AuditService $audit,
        private readonly ChangeSequenceService $changes,
    ) {}

    /** cookie_value is consumed only by the controller's HttpOnly Set-Cookie, never by JSON. */
    public function register(ShopContext $context, array $input, ?string $cookie): array
    {
        DeviceInput::only($input, ['public_id', 'device_uuid', 'display_name']);
        $id = DeviceInput::uuid($input['public_id'] ?? null);
        $installation = DeviceInput::uuid($input['device_uuid'] ?? null);
        $name = DeviceInput::name($input['display_name'] ?? null);
        return $this->ownerCommand($context, function () use ($context, $id, $installation, $name, $cookie): array {
            $existing = $this->database->query(
                'SELECT * FROM registered_devices WHERE tenant_id=? AND shop_id=? AND (device_uuid=? OR public_id=?) FOR UPDATE',
                [$context->tenantId, $context->shopId, $installation, $id],
            )->getResultArray();
            if ($existing) {
                $proof = (new DeviceQuery($this->database))->authenticated($context, $cookie, true);
                if (count($existing) !== 1 || !$proof || $proof['public_id'] !== $id || $proof['device_uuid'] !== $installation
                    || $proof['display_name'] !== $name || $proof['status'] !== 'ACTIVE') {
                    throw new ApiException('DEVICE_IDENTITY_CONFLICT', 'This installation reference is already registered. Check saved status or use explicit installation recovery.', 409);
                }
                return ['device' => DeviceQuery::safe($proof), 'changed' => false];
            }
            $secret = $this->secret();
            $now = UtcTimestamp::now();
            $this->database->table('registered_devices')->insert([
                'public_id' => $id, 'device_uuid' => $installation, 'tenant_id' => $context->tenantId, 'shop_id' => $context->shopId,
                'display_name' => $name, 'status' => 'ACTIVE', 'credential_hash' => hash('sha256', $secret),
                'registered_by_user_id' => $context->userId, 'registered_at' => $now,
                'last_seen_at' => $now, 'last_validated_at' => null, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $device = $this->locked($context, $id);
            $this->record($context, $device, 'device.registered', 'Online installation enrolled by the Primary Owner.');
            return ['device' => DeviceQuery::safe($device), 'cookie_value' => $id . '.' . $secret];
        });
    }

    public function rename(ShopContext $context, string $id, array $input): array
    {
        DeviceInput::only($input, ['display_name']);
        $id = DeviceInput::uuid($id);
        $name = DeviceInput::name($input['display_name'] ?? null);
        return $this->ownerCommand($context, function () use ($context, $id, $name): array {
            $row = $this->locked($context, $id);
            if ($row['status'] !== 'ACTIVE') {
                throw new ApiException('DEVICE_NOT_ACTIVE', 'A revoked or pending installation cannot be renamed.', 409);
            }
            if ($row['display_name'] === $name) {
                return ['device' => DeviceQuery::safe($row), 'changed' => false];
            }
            $this->database->table('registered_devices')->where('id', $row['id'])->update([
                'display_name' => $name, 'updated_at' => UtcTimestamp::now(),
            ]);
            $this->record($context, $row, 'device.renamed', 'Device display name changed by the Primary Owner.',
                ['before_name' => $row['display_name'], 'after_name' => $name]);
            return ['device' => DeviceQuery::safe($this->locked($context, $id))];
        });
    }

    public function revoke(ShopContext $context, string $id, array $input): array
    {
        DeviceInput::only($input, ['reason']);
        $id = DeviceInput::uuid($id);
        $reason = $this->reason($input);
        return $this->ownerCommand($context, function () use ($context, $id, $reason): array {
            $row = $this->locked($context, $id);
            if ($row['status'] === 'REVOKED') {
                if ($row['revocation_reason'] !== $reason) {
                    throw new ApiException('DEVICE_IDENTITY_CONFLICT', 'This Device was already revoked with different details.', 409);
                }
                return ['device' => DeviceQuery::safe($row), 'changed' => false];
            }
            $now = UtcTimestamp::now();
            // Audit the administrative decision without claiming a revoked Device as the acting installation.
            $this->record($context, $row, 'device.revoked', $reason);
            $this->database->table('registered_devices')->where('id', $row['id'])->update([
                'status' => 'REVOKED', 'revoked_at' => $now, 'revoked_by_user_id' => $context->userId,
                'revocation_reason' => $reason, 'updated_at' => $now,
            ]);
            return ['device' => DeviceQuery::safe($this->locked($context, $id))];
        });
    }

    /** Explicit Owner recovery rotates proof; it never reactivates revoked or rebinds foreign Devices. */
    public function recover(ShopContext $context, string $id, array $input): array
    {
        DeviceInput::only($input, ['device_uuid', 'reason']);
        $id = DeviceInput::uuid($id);
        $installation = DeviceInput::uuid($input['device_uuid'] ?? null);
        $reason = $this->reason($input);
        return $this->ownerCommand($context, function () use ($context, $id, $installation, $reason): array {
            $row = $this->locked($context, $id);
            if ($row['status'] !== 'ACTIVE' || $row['device_uuid'] !== $installation) {
                throw new ApiException('DEVICE_RECOVERY_CONFLICT', 'Only this active installation can be recovered. A revoked installation needs a new enrollment.', 409);
            }
            $secret = $this->secret();
            $now = UtcTimestamp::now();
            $this->database->table('registered_devices')->where('id', $row['id'])->update([
                'credential_hash' => hash('sha256', $secret), 'last_validated_at' => null, 'last_seen_at' => $now, 'updated_at' => $now,
            ]);
            $row = $this->locked($context, $id);
            $this->record($context, $row, 'device.credential_recovered', $reason);
            return ['device' => DeviceQuery::safe($row), 'cookie_value' => $id . '.' . $secret];
        });
    }

    /** Staff may validate only the cookie-proven ACTIVE installation; no admin/list authority. */
    public function validateCurrent(ShopContext $context, ?string $cookie): array
    {
        return $this->access->mutate($context, [], function () use ($context, $cookie): array {
            (new DeviceSchema($this->database))->requireReady();
            $row = (new DeviceQuery($this->database))->authenticated($context, $cookie, true);
            if (!$row || $row['status'] !== 'ACTIVE') {
                throw new ApiException('DEVICE_PROOF_REQUIRED', 'This browser needs an active installation registered by the Owner.', 409);
            }
            $now = UtcTimestamp::now();
            $this->database->table('registered_devices')->where('id', $row['id'])->update([
                'last_seen_at' => $now, 'last_validated_at' => $now, 'updated_at' => $now,
            ]);
            $this->record($context, $row, 'device.validated', 'Current online installation validated.', [], true);
            $result = ['device' => DeviceQuery::safe($this->locked($context, $row['public_id']))];
            $this->changes->publish([new ChangeCommand($context->tenantId, $context->shopId, 'registered_device', $row['public_id'])]);
            return $result;
        });
    }

    private function ownerCommand(ShopContext $context, callable $operation): array
    {
        return $this->access->mutate($context, [], function () use ($context, $operation): array {
            $member = $this->database->table('tenant_memberships')->where([
                'tenant_id' => $context->tenantId, 'id' => $context->membershipId, 'user_id' => $context->userId, 'status' => 'ACTIVE',
            ])->get()->getRowArray();
            if (!$member || $member['role'] !== 'PRIMARY_OWNER') {
                throw new ApiException('PERMISSION_DENIED', 'Current Primary Owner authority is required.', 403);
            }
            (new DeviceSchema($this->database))->requireReady();
            $result = $operation();
            if ($result['changed'] ?? true) {
                $this->changes->publish([new ChangeCommand($context->tenantId, $context->shopId, 'registered_device', $result['device']['public_id'])]);
            }
            unset($result['changed']);
            return $result;
        });
    }

    private function locked(ShopContext $context, string $id): array
    {
        $row = $this->database->query('SELECT * FROM registered_devices WHERE tenant_id=? AND shop_id=? AND public_id=? FOR UPDATE',
            [$context->tenantId, $context->shopId, $id])->getRowArray();
        if (!$row) {
            throw new ApiException('DEVICE_NOT_FOUND', 'The Device is unavailable.', 404);
        }
        return $row;
    }

    private function record(ShopContext $context, array $row, string $action, string $reason, array $metadata = [], bool $deviceActor = false): void
    {
        $actor = $deviceActor
            ? AuditActor::tenantDevice($context->userId, $context->membershipId, $context->tenantId, $context->shopId, (int) $row['id'])
            : AuditActor::tenantUser($context->userId, $context->membershipId, $context->tenantId, $context->shopId);
        $this->audit->append(new AuditAppendCommand($actor, $action, AuditCategory::SYNC, 'registered_device', $row['public_id'], $reason,
            ['device_public_id' => $row['public_id'], ...$metadata]));

    }

    private function secret(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function reason(array $input): string
    {
        if (!is_string($input['reason'] ?? null)) {
            throw new ApiException('VALIDATION_FAILED', 'Enter a reason for this action.', 422, ['reason' => ['A reason is required.']]);
        }
        return SensitiveReason::require($input['reason']);
    }
}
