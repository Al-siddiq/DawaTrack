<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Libraries\Http\ApiResponse;
use Config\Services;
use DomainException;
use Throwable;

final class OnboardingController extends BaseController
{
    public function create()
    {
        $input = $this->request->getJSON(true) ?? [];
        try {
            $result = Services::onboardingService()->onboard($input, (int) auth()->id());
            return ApiResponse::success($this->response, $result, Services::requestContext()->requestId(), 201);
        } catch (DomainException $exception) {
            return ApiResponse::error($this->response, 'VALIDATION_FAILED', $exception->getMessage(), Services::requestContext()->requestId(), 422);
        } catch (Throwable $exception) {
            log_message('error', 'tenant.onboarding_failed {request_id} {exception}', ['request_id'=>Services::requestContext()->requestId(),'exception'=>$exception]);
            return ApiResponse::error($this->response, 'ONBOARDING_FAILED', 'The Tenant could not be created.', Services::requestContext()->requestId(), 500);
        }
    }
}
