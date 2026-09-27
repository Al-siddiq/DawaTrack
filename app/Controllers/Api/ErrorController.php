<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Libraries\Http\ApiResponse;
use Config\Services;

final class ErrorController extends BaseController
{
    public function notFound()
    {
        return ApiResponse::error($this->response, 'RESOURCE_NOT_FOUND', 'The requested resource was not found.', Services::requestContext()->requestId(), 404);
    }
}
