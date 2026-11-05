<?php

declare(strict_types=1);
namespace App\Modules\Sales\Application;
use App\Exceptions\ApiException;
use App\Modules\Access\Application\AccessGate;
use App\Modules\Identity\Application\PermissionService;
use App\Modules\Audit\Application\AuditService;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use CodeIgniter\Database\BaseConnection;
use Throwable;

final class CommercialAccess
{
    public function __construct(
        private readonly BaseConnection $database,
        private readonly AuditService $audit,
    ) {}
    public function allows(ShopContext $context, string $permission): bool
    {
        return (new PermissionService($this->database, $this->audit))->allows(
            $context->tenantId,
            $context->membershipId,
            $permission,
        );
    }
    public function read(ShopContext $context, string $permission): void
    {
        $access = (new AccessGate($this->database))->evaluate(
            $context->tenantId,
            $context->shopId,
        );
        if (in_array($access->mode, ["BLOCKED", "CLOSED"], true)) {
            throw new ApiException($access->mode, $access->message, 403);
        }
        if (!$this->allows($context, $permission)) {
            throw new ApiException(
                "PERMISSION_DENIED",
                "This commercial permission is required.",
                403,
            );
        }
    }
    public function mutate(
        ShopContext $context,
        string|array $permission,
        callable $operation,
    ): mixed {
        $permissions = is_array($permission) ? $permission : [$permission];
        foreach ($permissions as $required) {
            $this->read($context, $required);
        }
        if ($this->database->transDepth !== 0) {
            throw new \LogicException(
                "Commercial mutations require an owning transaction.",
            );
        }
        $this->database->transException(true);
        if (!$this->database->transBegin()) {
            throw new \RuntimeException("Commercial mutation could not start.");
        }
        try {
            $this->database
                ->query("SELECT id FROM tenants WHERE id=? FOR UPDATE", [
                    $context->tenantId,
                ])
                ->getRowArray();
            $this->database
                ->query(
                    "SELECT id FROM shops WHERE tenant_id=? AND id=? FOR UPDATE",
                    [$context->tenantId, $context->shopId],
                )
                ->getRowArray();
            $member = $this->database
                ->query(
                    "SELECT id FROM tenant_memberships WHERE tenant_id=? AND id=? AND user_id=? AND status='ACTIVE' FOR UPDATE",
                    [
                        $context->tenantId,
                        $context->membershipId,
                        $context->userId,
                    ],
                )
                ->getRowArray();
            if (!$member) {
                throw new ApiException(
                    "PERMISSION_DENIED",
                    "Active membership is required.",
                    403,
                );
            }
            foreach ($permissions as $required) {
                $this->read($context, $required);
            }
            $access = (new AccessGate($this->database))->evaluate(
                $context->tenantId,
                $context->shopId,
            );
            if (!$access->permitsMutation()) {
                throw new ApiException($access->mode, $access->message, 403);
            }
            $result = $operation();
            if (!$this->database->transCommit()) {
                throw new \RuntimeException(
                    "Commercial mutation could not be committed.",
                );
            }
            return $result;
        } catch (Throwable $exception) {
            $this->database->transRollback();
            throw $exception;
        }
    }
}
