<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Enums\PermissionCode;
use App\Exceptions\ApiException;
use App\Libraries\Http\ApiResponse;
use App\Modules\Access\Application\AccessGate;
use App\Modules\Identity\Application\PermissionService;
use App\Modules\Inventory\Application\ThresholdService;
use App\Modules\ProductCatalog\Application\ProductService;
use App\Modules\ProductCatalog\Infrastructure\ProductQuery;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use Config\Services;
use Throwable;

final class ProductController extends BaseController
{
    public function index()
    {
        return $this->execute(function () {
            $context = $this->readContext();
            $result = (new ProductQuery(db_connect()))->page(
                $context,
                (string) $this->request->getGet('search'),
                (string) ($this->request->getGet('status') ?? 'ACTIVE'),
                (int) ($this->request->getGet('page') ?? 1),
                (int) ($this->request->getGet('per_page') ?? 20),
                (string) ($this->request->getGet('stock_state') ?? ($this->request->getGet('low_stock') === '1' ? 'LOW' : 'ALL')),
            );

            return $this->ok($result['items'], 200, ['pagination' => $result['pagination']]);
        });
    }

    public function show(string $publicId)
    {
        return $this->execute(fn () => $this->ok(
            (new ProductQuery(db_connect()))->detail($this->readContext(), $publicId),
        ));
    }

    public function create()
    {
        return $this->execute(function () {
            $context = $this->writeContext();
            $publicId = $this->productService()->create($context, $this->request->getJSON(true) ?? []);

            return $this->ok((new ProductQuery(db_connect()))->detail($context, $publicId), 201);
        });
    }

    public function update(string $publicId)
    {
        return $this->execute(function () use ($publicId) {
            $context = $this->writeContext();
            $this->productService()->update($context, $publicId, $this->request->getJSON(true) ?? []);

            return $this->ok((new ProductQuery(db_connect()))->detail($context, $publicId));
        });
    }

    public function deactivate(string $publicId)
    {
        return $this->execute(function () use ($publicId) {
            $input = $this->request->getJSON(true) ?? [];
            $this->productService()->deactivate($this->writeContext(), $publicId, (string) ($input['reason'] ?? ''));

            return $this->ok();
        });
    }

    public function threshold(string $publicId)
    {
        return $this->execute(function () use ($publicId) {
            $input = $this->request->getJSON(true) ?? [];
            $context = $this->writeContext();
            (new ThresholdService(db_connect(), Services::auditService(), Services::changeSequenceService()))
                ->update($context, $publicId, (string) ($input['low_stock_threshold'] ?? ''));

            return $this->ok((new ProductQuery(db_connect()))->detail($context, $publicId));
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

    private function readContext(): ShopContext
    {
        $context = $this->context();
        $decision = (new AccessGate(db_connect()))->evaluate($context->tenantId, $context->shopId);
        if (in_array($decision->mode, ['BLOCKED', 'CLOSED'], true)) {
            throw new ApiException($decision->mode, $decision->message, 403);
        }
        if (! (new PermissionService(db_connect(), Services::auditService()))
            ->allows($context->tenantId, $context->membershipId, PermissionCode::InventoryView->value)) {
            throw new ApiException('PERMISSION_DENIED', 'You do not have permission to view Products and stock.', 403);
        }

        return $context;
    }

    private function writeContext(): ShopContext
    {
        $context = $this->context();
        $decision = (new AccessGate(db_connect()))->evaluate($context->tenantId, $context->shopId);
        if (! $decision->permitsMutation()) {
            throw new ApiException($decision->mode, $decision->message, 403);
        }

        return $context;
    }

    private function productService(): ProductService
    {
        return new ProductService(db_connect(), Services::auditService(), Services::changeSequenceService());
    }

    /** @param array<string,mixed> $data @param array<string,mixed> $extraMeta */
    private function ok(array $data = [], int $status = 200, array $extraMeta = [])
    {
        $response = ApiResponse::success($this->response, $data, Services::requestContext()->requestId(), $status);
        if ($extraMeta !== []) {
            $payload = json_decode($response->getBody(), true);
            $payload['meta'] = array_merge($payload['meta'], $extraMeta);
            $response->setJSON($payload);
        }

        return $response;
    }

    private function execute(callable $operation)
    {
        try {
            return $operation();
        } catch (ApiException $exception) {
            return ApiResponse::error($this->response, $exception->errorCode, $exception->getMessage(), Services::requestContext()->requestId(), $exception->status, $exception->details);
        } catch (Throwable $exception) {
            log_message('error', 'product.request_failed {request_id} {exception_class}', [
                'request_id' => Services::requestContext()->requestId(),
                'exception_class' => $exception::class,
            ]);

            return ApiResponse::error($this->response, 'REQUEST_FAILED', 'The request could not be completed.', Services::requestContext()->requestId(), 500);
        }
    }
}
