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
        $isApi = str_starts_with($request->getUri()->getPath(), 'api/');
        if ($user === null) {
            if (! $isApi) {
                return redirect()->to(site_url('platform-admin/login'));
            }
            return ApiResponse::error(Services::response(), 'AUTHENTICATION_REQUIRED', 'Authentication is required.', Services::requestContext()->requestId(), 401);
        }
        if (! $user->inGroup('platform_admin')) {
            if (! $isApi) {
                return redirect()->to(site_url('platform-admin/login'));
            }
            return ApiResponse::error(Services::response(), 'PERMISSION_DENIED', 'Platform Administrator access is required.', Services::requestContext()->requestId(), 403);
        }
        foreach ((array) $arguments as $permission) {
            if (! $user->can((string) $permission)) {
                if (! $isApi) {
                    return Services::response()
                        ->setStatusCode(403)
                        ->setBody(view('auth/admin_denied', ['pageTitle' => 'Administrator access denied', 'brand' => null]));
                }

                return ApiResponse::error(
                    Services::response(),
                    'PERMISSION_DENIED',
                    'This Platform Administrator permission is required.',
                    Services::requestContext()->requestId(),
                    403,
                );
            }
        }
        if (! Services::platformMfa()->isRecent((int) $user->id)) {
            if (! $isApi) {
                session()->setTempdata('platform_mfa_destination', '/' . ltrim($request->getUri()->getPath(), '/'), 600);
                return redirect()->to(site_url('platform-admin/mfa'));
            }
            return ApiResponse::error(Services::response(), 'MFA_REQUIRED', 'Multi-factor verification is required.', Services::requestContext()->requestId(), 403);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
    }
}
