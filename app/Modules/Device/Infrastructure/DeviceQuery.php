<?php

declare(strict_types=1);

namespace App\Modules\Device\Infrastructure;

use App\Exceptions\ApiException;
use App\Modules\Access\Application\AccessGate;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use CodeIgniter\Database\BaseConnection;

final class DeviceQuery
{
    public function __construct(private readonly BaseConnection $database) {}

    /** READ_ONLY may inspect; host/membership and Device credential are independent authority. */
    public function requireRead(ShopContext $context, bool $owner = false): void
    {
        $member = $this->database->table('tenant_memberships')->where([
            'id' => $context->membershipId, 'tenant_id' => $context->tenantId,
            'user_id' => $context->userId, 'status' => 'ACTIVE',
        ])->get()->getRowArray();
        if (!$member || ($owner && $member['role'] !== 'PRIMARY_OWNER')) {
            throw new ApiException('PERMISSION_DENIED', 'Active Shop authority is required for this Device action.', 403);
        }
        $access = (new AccessGate($this->database))->evaluate($context->tenantId, $context->shopId);
        if (in_array($access->mode, ['BLOCKED', 'CLOSED'], true)) {
            throw new ApiException($access->mode, $access->message, 403);
        }
        (new DeviceSchema($this->database))->requireReady();
    }

    public function history(ShopContext $context, int $page, int $perPage): array
    {
        $this->requireRead($context, true);
        $where = ['tenant_id' => $context->tenantId, 'shop_id' => $context->shopId];
        $total = $this->database->table('registered_devices')->where($where)->countAllResults();
        $rows = $this->database->table('registered_devices')->where($where)
            ->orderBy('registered_at', 'DESC')->orderBy('id', 'DESC')->get($perPage, ($page - 1) * $perPage)->getResultArray();
        return ['devices' => array_map(self::safe(...), $rows), 'pagination' => [
            'page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => (int) ceil($total / $perPage),
        ]];
    }

    /** A permanent UUID alone is never proof. Return null for malformed/foreign/unknown proof. */
    public function authenticated(ShopContext $context, ?string $cookie, bool $locking = false): ?array
    {
        if ($cookie === null || !preg_match('/^([0-9a-f-]{36})\.([A-Za-z0-9_-]{43})$/D', $cookie, $parts)
            || !\App\Support\Uuid::isV4($parts[1])) {
            return null;
        }
        $row = $this->database->query(
            'SELECT * FROM registered_devices WHERE tenant_id=? AND shop_id=? AND public_id=?' . ($locking ? ' FOR UPDATE' : ''),
            [$context->tenantId, $context->shopId, $parts[1]],
        )->getRowArray();
        if (!$row || !hash_equals((string) $row['credential_hash'], hash('sha256', $parts[2]))) {
            return null;
        }
        return $row;
    }

    /** Explicit allowlist; credential_hash, internal IDs and installation UUID never leave the server. */
    public static function safe(array $row): array
    {
        return array_intersect_key($row, array_flip([
            'public_id', 'display_name', 'status', 'registered_at', 'last_seen_at', 'last_validated_at',
            'revoked_at', 'revocation_reason', 'updated_at',
        ]));
    }
}
