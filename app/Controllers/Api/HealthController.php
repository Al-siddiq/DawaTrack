<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Libraries\Http\ApiResponse;
use Config\Services;

final class HealthController extends BaseController
{
    public function live()
    {
        return ApiResponse::success($this->response, ['status' => 'ok'], Services::requestContext()->requestId());
    }

    public function ready()
    {
        $checks = ['configuration' => $this->configurationIsReady()];
        $ready = ! in_array(false, $checks, true);

        return $ready
            ? ApiResponse::success($this->response, ['status' => 'ready', 'checks' => $checks], Services::requestContext()->requestId())
            : ApiResponse::error($this->response, 'SERVICE_NOT_READY', 'The application is not ready to receive traffic.', Services::requestContext()->requestId(), 503, ['configuration' => ['A required server setting is missing.']]);
    }

    private function configurationIsReady(): bool
    {
        return trim((string) env('DAWATRACK_DEPLOYMENT_ID', '')) !== '';
    }
}
