<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Exceptions\ApiException;
use App\Libraries\Http\ApiResponse;
use App\Modules\Access\Application\AccessGate;
use App\Modules\Audit\Domain\AuditActor;
use App\Modules\BrandingDomain\Application\BrandingService;
use App\Modules\BrandingDomain\Application\DomainService;
use App\Modules\Identity\Application\PermissionService;
use App\Modules\Identity\Application\StaffService;
use App\Modules\Subscription\Application\SubscriptionQuery;
use App\Modules\TenantShop\Application\ShopSettingsService;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use Config\Services;
use DomainException;
use Throwable;

final class ShopController extends BaseController
{
    public function contextInfo()
    {
        return $this->execute(function () {
            $context = $this->context();
            $decision = (new AccessGate(db_connect()))->evaluate($context->tenantId, $context->shopId);
            $permissions = $context->role === 'PRIMARY_OWNER'
                ? array_column(PermissionService::catalogue(), 'code')
                : $this->activePermissions($context);
            $hasPermission = static fn (string $code): bool => in_array($code, $permissions, true);
            $shop = db_connect()->table('shops s')
                ->select('s.public_id,s.name,t.public_id AS tenant_public_id')
                ->join('tenants t', 't.id=s.tenant_id')
                ->where(['s.id' => $context->shopId, 's.tenant_id' => $context->tenantId])
                ->get()
                ->getRowArray();

            return $this->ok([
                'role' => $context->role,
                'current_user' => [
                    'display_name' => (string) (auth()->user()?->username ?? 'Signed-in user'),
                    // Browser marker namespace only; never an authorization credential.
                    'recovery_scope' => hash('sha256', $shop['public_id'] . '|' . $context->userId),
                ],
                'shop' => $shop,
                'permissions' => $permissions,
                'capabilities' => [
                    'manage_staff' => $context->role === 'PRIMARY_OWNER',
                    'manage_shop_settings' => $context->role === 'PRIMARY_OWNER',
                    'manage_branding' => $context->role === 'PRIMARY_OWNER',
                    'manage_domains' => $context->role === 'PRIMARY_OWNER',
                    'view_subscription' => $context->role === 'PRIMARY_OWNER',
                    'manage_opening_cash' => $context->role === 'PRIMARY_OWNER',
                    'view_audit' => $context->role === 'PRIMARY_OWNER',
                    'view_customers' => $hasPermission('customer.view'),
                    'view_debt' => $hasPermission('customer.view') && $hasPermission('debt.view'),
                    'collect_debt_payments' => $hasPermission('customer.view') && $hasPermission('debt.view') && $hasPermission('debt.payment.record'),
                    'allocate_debt_payments' => $hasPermission('customer.view') && $hasPermission('debt.view') && $hasPermission('debt.payment.record') && $hasPermission('debt.payment.allocate'),
                    'view_sale_drafts' => $hasPermission('sale.view'),
                    'view_inventory' => $hasPermission('inventory.view'),
                    'manage_products' => $hasPermission('product.manage'),
                    'adjust_inventory' => $hasPermission('inventory.adjust'),
                    'manage_intakes' => $hasPermission('inventory.intake'),
                    'reverse_inventory' => $hasPermission('inventory.reverse'),
                ],
                'access' => [
                    'mode' => $decision->mode,
                    'message' => $decision->message,
                    'plan_code' => $decision->planCode,
                    'ends_at' => $decision->endsAt,
                ],
            ]);
        });
    }

    public function staff()
    {
        return $this->execute(fn () => $this->ok($this->staffService()->listing($this->context())));
    }

    public function createStaff()
    {
        return $this->execute(fn () => $this->ok(
            $this->staffService()->create($this->mutableContext(), $this->request->getJSON(true) ?? []),
            201,
        ));
    }

    public function staffStatus(int $id)
    {
        return $this->execute(function () use ($id) {
            $input = $this->request->getJSON(true) ?? [];
            $this->staffService()->setActive(
                $this->mutableContext(),
                $id,
                (bool) ($input['active'] ?? false),
                (string) ($input['reason'] ?? ''),
            );

            return $this->ok();
        });
    }

    public function permissions(int $id)
    {
        return $this->execute(fn () => $this->ok($this->staffService()->permissions($this->context(), $id)));
    }

    public function grant(int $id)
    {
        return $this->execute(function () use ($id) {
            $context = $this->mutableContext();
            $input = $this->request->getJSON(true) ?? [];
            $this->permissionService()->grant(
                $context,
                $id,
                (string) ($input['permission_code'] ?? ''),
                (string) ($input['reason'] ?? ''),
            );

            return $this->ok($this->staffService()->permissions($context, $id));
        });
    }

    public function revoke(int $id)
    {
        return $this->execute(function () use ($id) {
            $context = $this->mutableContext();
            $input = $this->request->getJSON(true) ?? [];
            $this->permissionService()->revoke(
                $context,
                $id,
                (string) ($input['permission_code'] ?? ''),
                (string) ($input['reason'] ?? ''),
            );

            return $this->ok($this->staffService()->permissions($context, $id));
        });
    }

    public function resetPassword(int $id)
    {
        $this->response->setHeader('Cache-Control', 'no-store, private');
        return $this->execute(function () use ($id) {
            $input = $this->request->getJSON(true) ?? [];
            $result = $this->staffService()->resetPassword(
                $this->mutableContext(),
                $id,
                (string) ($input['reason'] ?? ''),
            );

            return $this->ok($result);
        });
    }

    public function settings()
    {
        return $this->execute(function () {
            $context = $this->context();
            $context->requireOwner();

            return $this->ok($this->settingsService()->get($context));
        });
    }

    public function updateSettings()
    {
        return $this->execute(fn () => $this->ok($this->settingsService()->update(
            $this->mutableContext(),
            $this->request->getJSON(true) ?? [],
        )));
    }

    public function branding()
    {
        return $this->execute(function () {
            $context = $this->context();
            $context->requireOwner();
            $profile = db_connect()->table('shop_branding_profiles')
                ->where(['tenant_id' => $context->tenantId, 'shop_id' => $context->shopId])
                ->get()
                ->getRowArray() ?? [];

            return $this->ok($profile);
        });
    }

    public function updateBranding()
    {
        return $this->execute(function () {
            $context = $this->mutableContext();
            $context->requireOwner();
            (new BrandingService(db_connect(), Services::auditService()))->updateProfile(
                $context->tenantId,
                $context->shopId,
                $context->userId,
                $context->membershipId,
                $this->request->getJSON(true) ?? [],
            );

            return $this->branding();
        });
    }

    public function assets()
    {
        return $this->execute(function () {
            $context = $this->context();
            $context->requireOwner();
            $assets = db_connect()->table('shop_brand_assets')
                ->select('public_id,asset_type,mime_type,width_px,height_px,file_size_bytes,activated_at')
                ->where(['tenant_id' => $context->tenantId, 'shop_id' => $context->shopId, 'is_active' => 1])
                ->get()
                ->getResultArray();

            return $this->ok($assets);
        });
    }

    public function uploadAsset()
    {
        return $this->execute(function () {
            $context = $this->mutableContext();
            $context->requireOwner();
            $result = (new BrandingService(db_connect(), Services::auditService()))->replaceAsset(
                $context->tenantId,
                $context->shopId,
                $context->userId,
                $context->membershipId,
                (string) $this->request->getPost('asset_type'),
                $this->request->getFile('asset'),
            );

            return $this->ok($result, 201);
        });
    }

    public function domains()
    {
        return $this->execute(function () {
            $context = $this->context();
            $context->requireOwner();
            $domains = db_connect()->table('shop_domains')
                ->select('public_id,hostname,domain_type,verification_status,certificate_status,is_active,is_primary,verification_method,verification_reference,verified_at,activated_at')
                ->where(['tenant_id' => $context->tenantId, 'shop_id' => $context->shopId])
                ->get()
                ->getResultArray();

            return $this->ok($domains);
        });
    }

    public function requestDomain()
    {
        return $this->execute(function () {
            $context = $this->mutableContext();
            $context->requireOwner();
            $input = $this->request->getJSON(true) ?? [];
            $result = (new DomainService(db_connect(), Services::auditService()))->requestCustom(
                $context->tenantId,
                $context->shopId,
                (string) ($input['hostname'] ?? ''),
                $context->userId,
                $context->membershipId,
            );

            return $this->ok($result, 201);
        });
    }

    public function rotateDomain(string $publicId)
    {
        return $this->execute(function () use ($publicId) {
            $context=$this->mutableContext();$context->requireOwner();
            return $this->ok((new DomainService(db_connect(),Services::auditService()))->rotate($context->tenantId,$context->shopId,$publicId,$context->userId,$context->membershipId));
        });
    }

    public function verifyDomain(string $publicId)
    {
        return $this->execute(function () use ($publicId) {
            $context=$this->mutableContext();$context->requireOwner();
            $domain=db_connect()->table('shop_domains')->where(['tenant_id'=>$context->tenantId,'shop_id'=>$context->shopId,'public_id'=>$publicId])->get()->getRowArray();
            if(!$domain)throw new ApiException('RESOURCE_NOT_FOUND','Domain not found.',404);
            $matched=(new DomainService(db_connect(),Services::auditService()))->verify(
                $domain,
                AuditActor::tenantUser($context->userId, $context->membershipId, $context->tenantId, $context->shopId),
            );
            if(!$matched)throw new ApiException('DNS_CHALLENGE_PENDING','The expected TXT value is not visible yet. Retry after DNS propagation.',409);
            return $this->ok(['verified'=>true]);
        });
    }

    public function subscription()
    {
        return $this->execute(function () {
            $context = $this->context();
            $context->requireOwner();
            $gate = (new AccessGate(db_connect()))->evaluate($context->tenantId, $context->shopId);
            $subscription = (new SubscriptionQuery(db_connect()))->forTenant($context->tenantId);
            $count = db_connect()->table('tenant_memberships')->where([
                'tenant_id' => $context->tenantId,
                'role' => 'STAFF',
                'status' => 'ACTIVE',
            ])->countAllResults();

            return $this->ok([
                ...$subscription,
                'active_staff' => $count,
                'staff_limit' => $subscription['current_entitlement']['max_active_staff_snapshot'] ?? null,
                'remaining_staff_capacity' => isset($subscription['current_entitlement']) && $subscription['current_entitlement'] !== null ? max(0, (int)$subscription['current_entitlement']['max_active_staff_snapshot']-$count) : 0,
                'access' => ['mode' => $gate->mode, 'message' => $gate->message],
            ]);
        });
    }

    private function context(): ShopContext
    {
        return ShopContext::resolve(
            db_connect(),
            (int) auth()->id(),
            $this->request->getServer('HTTP_HOST') ?? '',
        );
    }

    private function mutableContext(): ShopContext
    {
        $context = $this->context();
        $decision = (new AccessGate(db_connect()))->evaluate($context->tenantId, $context->shopId);
        if (! $decision->permitsMutation()) {
            throw new ApiException($decision->mode, $decision->message, 403);
        }

        return $context;
    }

    /** @return list<string> */
    private function activePermissions(ShopContext $context): array
    {
        return array_column(db_connect()->table('membership_permission_grants')
            ->select('permission_code')
            ->where([
                'tenant_id' => $context->tenantId,
                'membership_id' => $context->membershipId,
                'revoked_at' => null,
            ])->get()->getResultArray(), 'permission_code');
    }

    private function staffService(): StaffService
    {
        return new StaffService(db_connect(), Services::auditService(), Services::userSessionRevocation());
    }

    private function permissionService(): PermissionService
    {
        return new PermissionService(db_connect(), Services::auditService());
    }

    private function settingsService(): ShopSettingsService
    {
        return new ShopSettingsService(db_connect(), Services::auditService());
    }

    private function ok(array $data = [], int $status = 200)
    {
        return ApiResponse::success($this->response, $data, Services::requestContext()->requestId(), $status);
    }

    private function execute(callable $operation)
    {
        try {
            return $operation();
        } catch (ApiException $exception) {
            return ApiResponse::error(
                $this->response,
                $exception->errorCode,
                $exception->getMessage(),
                Services::requestContext()->requestId(),
                $exception->status,
                $exception->details,
            );
        } catch (DomainException $exception) {
            $status = str_contains($exception->getMessage(), 'access is required') ? 403 : 422;
            $code = $status === 403 ? 'PERMISSION_DENIED' : 'VALIDATION_FAILED';

            return ApiResponse::error($this->response, $code, $exception->getMessage(), Services::requestContext()->requestId(), $status);
        } catch (Throwable $exception) {
            log_message('error', 'phase1.request_failed {request_id} {exception_class}', [
                'request_id' => Services::requestContext()->requestId(),
                'exception_class' => $exception::class,
            ]);

            return ApiResponse::error($this->response, 'REQUEST_FAILED', 'The request could not be completed.', Services::requestContext()->requestId(), 500);
        }
    }
}
