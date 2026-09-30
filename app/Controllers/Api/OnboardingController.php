<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Exceptions\ApiException;
use App\Libraries\Http\ApiResponse;
use Config\Services;
use Throwable;

final class OnboardingController extends BaseController
{
    public function create()
    {
        try {
            $result = Services::onboardingService()->onboard(
                $this->request->getJSON(true) ?? [],
                (int) auth()->id(),
            );

            return ApiResponse::success($this->response, $result, Services::requestContext()->requestId(), 201);
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
            log_message('error', 'tenant.onboarding_failed {request_id} {exception_class}', [
                'request_id' => Services::requestContext()->requestId(),
                'exception_class' => $exception::class,
            ]);

            return ApiResponse::error($this->response, 'ONBOARDING_FAILED', 'The Tenant could not be created.', Services::requestContext()->requestId(), 500);
        }
    }
}
