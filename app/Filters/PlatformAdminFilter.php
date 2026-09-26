<?php

declare(strict_types=1);

namespace App\Filters;

use App\Libraries\Http\ApiResponse;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

final class PlatformAdminFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null): ?ResponseInterface
    {
        $user = auth()->user();
        $mfaAt = (int) session('platform_mfa_verified_at');
        if ($user === null || ! $user->inGroup('platform_admin')) {
            return ApiResponse::error(Services::response(), 'PERMISSION_DENIED', 'Platform Administrator access is required.', Services::requestContext()->requestId(), 403);
        }
        if ($mfaAt < time() - 900) {
            return ApiResponse::error(Services::response(), 'MFA_REQUIRED', 'Multi-factor verification is required.', Services::requestContext()->requestId(), 403);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
    }
}
