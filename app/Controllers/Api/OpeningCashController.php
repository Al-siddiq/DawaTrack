<?php

declare(strict_types=1);
namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Exceptions\ApiException;
use App\Libraries\Http\ApiResponse;
use App\Modules\Access\Application\AccessGate;
use App\Modules\Reconciliation\Application\OpeningCashService;
use App\Modules\Reconciliation\Infrastructure\OpeningCashQuery;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use Config\Services;
use Throwable;

final class OpeningCashController extends BaseController
{
    public function state()
    {
        return $this->execute(fn($context) => null);
    }
    public function start()
    {
        return $this->execute(function ($context) {
            $input = $this->input();
            $this->service()->start(
                $context,
                $this->text($input, "public_id"),
            );
        });
    }
    public function count(string $id)
    {
        return $this->execute(function ($context) use ($id) {
            $this->service()->count(
                $context,
                $id,
                $this->input()["amount"] ?? null,
            );
        });
    }
    public function commitCount(string $id)
    {
        return $this->execute(
            fn($context) => $this->service()->commitCount($context, $id),
        );
    }
    public function review(string $id)
    {
        return $this->execute(
            fn($context) => $this->service()->review($context, $id),
        );
    }
    public function finalize(string $id)
    {
        return $this->execute(
            fn($context) => $this->service()->finalize($context, $id),
        );
    }
    public function abort(string $id)
    {
        return $this->execute(
            fn($context) => $this->service()->abort(
                $context,
                $id,
                $this->text($this->input(), "reason"),
            ),
        );
    }

    private function text(array $input, string $field): string
    {
        if (!isset($input[$field]) || !is_string($input[$field])) {
            throw new ApiException('VALIDATION_FAILED', 'Use text for this field.', 422, [$field => ['Text is required.']]);
        }
        return $input[$field];
    }

    private function input(): array
    {
        try {
            $input = $this->request->getJSON(true);
        } catch (\Throwable) {
            throw new ApiException("VALIDATION_FAILED", "Use valid JSON.", 422);
        }
        if (!is_array($input)) {
            throw new ApiException(
                "VALIDATION_FAILED",
                "Use a JSON object.",
                422,
            );
        }
        return $input;
    }
    private function service(): OpeningCashService
    {
        return new OpeningCashService(
            db_connect(),
            Services::auditService(),
            Services::changeSequenceService(),
        );
    }
    private function execute(callable $operation)
    {
        $this->response->setHeader("Cache-Control", "no-store, private");
        try {
            $context = ShopContext::resolve(
                db_connect(),
                (int) auth()->id(),
                $this->request->getServer("HTTP_HOST") ?? "",
            );
            $context->requireOwner();
            $decision = (new AccessGate(db_connect()))->evaluate(
                $context->tenantId,
                $context->shopId,
            );
            if (in_array($decision->mode, ["BLOCKED", "CLOSED"], true)) {
                throw new ApiException(
                    $decision->mode,
                    $decision->message,
                    403,
                );
            }
            $operation($context);
            return ApiResponse::success(
                $this->response,
                (new OpeningCashQuery(db_connect()))->state($context),
                Services::requestContext()->requestId(),
            );
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
            log_message(
                "error",
                "opening_cash.request_failed {request_id} {exception_class}",
                [
                    "request_id" => Services::requestContext()->requestId(),
                    "exception_class" => $exception::class,
                ],
            );
            return ApiResponse::error(
                $this->response,
                "REQUEST_FAILED",
                "Opening Cash could not be completed. Reload its current status before retrying.",
                Services::requestContext()->requestId(),
                500,
            );
        }
    }
}
