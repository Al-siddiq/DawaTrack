<?php

declare(strict_types=1);

namespace App\Filters;

use App\Libraries\Http\ApiResponse;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

final class PlatformAdminIdentityFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null): ?ResponseInterface
    {
        $user = auth()->user();
        if ($user !== null && $user->inGroup('platform_admin')) {
            return null;
        }

        if (str_starts_with($request->getUri()->getPath(), 'api/')) {
            if ($user === null) {
                return ApiResponse::error(
                    Services::response(),
                    'AUTHENTICATION_REQUIRED',
                    'Authentication is required.',
                    Services::requestContext()->requestId(),
                    401,
                );
            }
            return ApiResponse::error(
                Services::response(),
                'PERMISSION_DENIED',
                'Platform Administrator access is required.',
                Services::requestContext()->requestId(),
                403,
            );
        }

        return redirect()->to(site_url('platform-admin/login'));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
    }
}
