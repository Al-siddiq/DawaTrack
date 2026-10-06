<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Exceptions\ApiException;
use App\Libraries\Http\ApiResponse;
use App\Modules\Audit\Application\AuditQuery;
use App\Modules\Audit\Application\AuditQueryService;
use App\Modules\Audit\Application\SupportAuditAccessService;
use App\Modules\Audit\Domain\AuditPage;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Throwable;

final class AuditController extends BaseController
{
    public function ownerIndex(): ResponseInterface
    {
        return $this->run(function (): ResponseInterface {
            $context = $this->ownerContext();
            $query = AuditQuery::from($this->request->getGet());
            $page = $this->queryService()->ownerPage($context->tenantId, $context->shopId, $query);

            return $this->page($page);
        });
    }

    public function ownerDetail(string $publicId): ResponseInterface
    {
        return $this->run(function () use ($publicId): ResponseInterface {
            $context = $this->ownerContext();
            $event = $this->queryService()->ownerDetail($context->tenantId, $context->shopId, $publicId);

            return $this->ok($event);
        });
    }

    public function platformIndex(): ResponseInterface
    {
        return $this->run(function (): ResponseInterface {
            $query = AuditQuery::from($this->request->getGet());

            return $this->page($this->queryService()->platformPage($query));
        });
    }

    public function platformDetail(string $publicId): ResponseInterface
    {
        return $this->run(fn (): ResponseInterface => $this->ok(
            $this->queryService()->platformDetail($publicId),
        ));
    }

    public function supportQuery(): ResponseInterface
    {
        return $this->run(function () {
            $input = $this->jsonInput();
            $access = $this->supportAccess($input);
            $query = AuditQuery::from($this->filters($input));
            $page = $this->queryService()->supportPage($access['tenant_id'], $access['shop_id'], $query);

            return $this->ok([
                'support' => $this->safeSupport($access),
                'events' => $page->events,
                'next_cursor' => $page->nextCursor,
            ]);
        });
    }

    public function supportDetail(string $publicId): ResponseInterface
    {
        return $this->run(function () use ($publicId) {
            $input = $this->jsonInput();
            $access = $this->supportAccess($input);
            $event = $this->queryService()->supportDetail(
                $access['tenant_id'],
                $access['shop_id'],
                $publicId,
            );

            return $this->ok(['support' => $this->safeSupport($access), 'event' => $event]);
        });
    }

    private function ownerContext(): ShopContext
    {
        $context = ShopContext::resolve(db_connect(), (int) auth()->id(), $this->request->getServer('HTTP_HOST') ?? '');
        $context->requireOwner();
        return $context;
    }

    /** @return array<string, mixed> */
    private function jsonInput(): array
    {
        $input = $this->request->getJSON(true);
        if (! is_array($input)) {
            throw new ApiException('AUDIT_FILTER_INVALID', 'Use a valid JSON Audit request.', 422);
        }

        return $input;
    }

    /** @param array<string, mixed> $input */
    private function filters(array $input): array
    {
        $filters = $input['filters'] ?? [];
        if (! is_array($filters)) {
            throw new ApiException('AUDIT_FILTER_INVALID', 'Audit filters must be an object.', 422);
        }

        return $filters;
    }

    private function supportAccess(array $input): array
    {
        $service = new SupportAuditAccessService(db_connect(), Services::auditService());
        if (! empty($input['access_id'])) {
            return $service->resume((int) auth()->id(), (string) $input['access_id']);
        }

        return $service->establish(
            (int) auth()->id(),
            (string) ($input['tenant_public_id'] ?? ''),
            (string) ($input['shop_public_id'] ?? ''),
            (string) ($input['reason'] ?? ''),
        );
    }

    private function safeSupport(array $access): array
    {
        return [
            'access_id' => $access['access_id'],
            'tenant_public_id' => $access['tenant_public_id'],
            'shop_public_id' => $access['shop_public_id'],
            'tenant_name' => $access['tenant_name'],
            'shop_name' => $access['shop_name'],
            'reason' => $access['reason'],
        ];
    }

    private function queryService(): AuditQueryService
    {
        return new AuditQueryService(db_connect());
    }

    private function page(AuditPage $page): ResponseInterface
    {
        return $this->ok([
            'events' => $page->events,
            'next_cursor' => $page->nextCursor,
        ]);
    }

    private function ok(array $data): ResponseInterface
    {
        return ApiResponse::success(
            $this->response,
            $data,
            Services::requestContext()->requestId(),
        );
    }

    private function run(callable $operation): ResponseInterface
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
        } catch (Throwable $exception) {
            log_message('error', 'audit.query_failed {request_id} {class}', [
                'request_id' => Services::requestContext()->requestId(),
                'class' => $exception::class,
            ]);

            return ApiResponse::error(
                $this->response,
                'REQUEST_FAILED',
                'The Audit request could not be completed.',
                Services::requestContext()->requestId(),
                500,
            );
        }
    }
}
