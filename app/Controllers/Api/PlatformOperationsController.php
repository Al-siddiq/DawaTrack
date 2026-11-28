<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Exceptions\ApiException;
use App\Libraries\Http\ApiResponse;
use App\Modules\Subscription\Application\SubscriptionService;
use App\Modules\TenantShop\Application\PlatformDashboardQuery;
use App\Modules\TenantShop\Application\PlatformLifecycleService;
use App\Modules\TenantShop\Application\PlatformTenantDetailQuery;
use App\Modules\TenantShop\Application\PlatformTenantDirectoryQuery;
use Config\Services;
use DomainException;
use Throwable;

final class PlatformOperationsController extends BaseController
{
    public function dashboard()
    {
        return $this->run(fn () => $this->ok((new PlatformDashboardQuery(db_connect()))->summary()));
    }

    public function tenants()
    {
        return $this->run(fn () => $this->ok((new PlatformTenantDirectoryQuery(db_connect()))->search($this->request->getGet())));
    }

    public function tenantDetail(string $tenantPublicId)
    {
        return $this->run(fn () => $this->ok((new PlatformTenantDetailQuery(db_connect()))->find($tenantPublicId)));
    }

    public function subscription(string $tenant)
    {
        return $this->run(fn () => $this->ok((new SubscriptionService(db_connect(), Services::auditService()))->create(
            $tenant,
            (int) auth()->id(),
            $this->request->getJSON(true) ?? [],
        )));
    }

    public function tenantLifecycle(string $tenant, string $action)
    {
        return $this->run(function () use ($tenant, $action) {
            $input = $this->request->getJSON(true) ?? [];
            (new PlatformLifecycleService(db_connect(), Services::auditService()))->tenant(
                $tenant,
                $action,
                (int) auth()->id(),
                (string) ($input['reason'] ?? ''),
            );
            return $this->ok();
        });
    }

    public function shopLifecycle(string $shop, string $action)
    {
        return $this->run(function () use ($shop, $action) {
            $input = $this->request->getJSON(true) ?? [];
            (new PlatformLifecycleService(db_connect(), Services::auditService()))->shop(
                $shop,
                $action,
                (int) auth()->id(),
                (string) ($input['reason'] ?? ''),
            );
            return $this->ok();
        });
    }

    private function ok(array $data = [], int $status = 200)
    {
        return ApiResponse::success($this->response, $data, Services::requestContext()->requestId(), $status);
    }

    private function run(callable $operation)
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
            return ApiResponse::error(
                $this->response,
                'CONFLICT',
                $exception->getMessage(),
                Services::requestContext()->requestId(),
                409,
            );
        } catch (Throwable $exception) {
            log_message('error', 'platform.operation_failed {request_id} {class}', [
                'request_id' => Services::requestContext()->requestId(),
                'class' => $exception::class,
            ]);
            return ApiResponse::error(
                $this->response,
                'REQUEST_FAILED',
                'The operation could not be completed.',
                Services::requestContext()->requestId(),
                500,
            );
        }
    }
}
