<?php

declare(strict_types=1);
namespace App\Controllers\Api;
use App\Controllers\BaseController;
use App\Exceptions\ApiException;
use App\Libraries\Http\ApiResponse;
use App\Modules\Sales\Application\CommercialAccess;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use Config\Services;
use Throwable;

abstract class CommercialController extends BaseController
{
    protected function access(): CommercialAccess
    {
        return new CommercialAccess(db_connect(), Services::auditService());
    }
    protected function input(): array
    {
        try {
            $input = $this->request->getJSON();
        } catch (Throwable) {
            throw new ApiException("VALIDATION_FAILED", "Use valid JSON.", 422);
        }
        if (!($input instanceof \stdClass)) {
            throw new ApiException(
                "VALIDATION_FAILED",
                "Use a JSON object.",
                422,
            );
        }
        return (array) $input;
    }
    protected function ok(array $data = [], int $status = 200, array $meta = [])
    {
        $response = ApiResponse::success(
            $this->response,
            $data,
            Services::requestContext()->requestId(),
            $status,
        );
        if ($meta) {
            $payload = json_decode($response->getBody(), true);
            $payload["meta"] += $meta;
            $response->setJSON($payload);
        }
        return $response;
    }
    protected function execute(callable $operation)
    {
        $this->response->setHeader("Cache-Control", "no-store, private");
        try {
            $context = ShopContext::resolve(
                db_connect(),
                (int) auth()->id(),
                $this->request->getServer("HTTP_HOST") ?? "",
            );
            return $operation($context);
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
            if (
                (int) $exception->getCode() === 1062 ||
                str_contains($exception->getMessage(), "Duplicate entry")
            ) {
                return ApiResponse::error(
                    $this->response,
                    "COMMERCIAL_CONFLICT",
                    "That identity or line number is already in use. Reload the record before retrying.",
                    Services::requestContext()->requestId(),
                    409,
                );
            }
            // Source locations only: never log SQL, request bodies, credentials or exception arguments.
            $root = str_replace('\\', '/', ROOTPATH);
            $origin = str_replace('\\', '/', $exception->getFile());
            $origin = str_starts_with($origin, $root) ? substr($origin, strlen($root)) : basename($origin);
            $applicationTrace = [];
            foreach ($exception->getTrace() as $frame) {
                $file = str_replace('\\', '/', $frame['file'] ?? '');
                if (str_starts_with($file, str_replace('\\', '/', APPPATH))) {
                    $applicationTrace[] = substr($file, strlen($root)) . ':' . ($frame['line'] ?? 0);
                    if (count($applicationTrace) === 3) break;
                }
            }
            log_message(
                "error",
                "commercial.request_failed {request_id} {exception_class} source={source_file}:{source_line} app_trace={application_trace}",
                [
                    "request_id" => Services::requestContext()->requestId(),
                    "exception_class" => $exception::class,
                    "source_file" => $origin,
                    "source_line" => $exception->getLine(),
                    "application_trace" => implode(',', $applicationTrace),
                ],
            );
            return ApiResponse::error(
                $this->response,
                "REQUEST_FAILED",
                "The request could not be completed. Reload the current record before retrying.",
                Services::requestContext()->requestId(),
                500,
            );
        }
    }
}
