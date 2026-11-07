<?php

declare(strict_types=1);

namespace App\Filters;

use App\Libraries\Http\ApiResponse;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

final class SessionSecurityFilter implements FilterInterface
{
    private const STARTED_AT_KEY = 'security_session_started_at';
    private const ABSOLUTE_LIFETIME_SECONDS = 28_800;

    public function before(RequestInterface $request, $arguments = null): ?ResponseInterface
    {
        if (! auth()->loggedIn()) {
            session()->remove(self::STARTED_AT_KEY);
            Services::userSessionRevocation()->clearSessionMarker();

            return null;
        }

        $startedAt = (int) session(self::STARTED_AT_KEY);
        $user = auth()->user();
        if ($user === null || ! $user->active || Services::userSessionRevocation()->currentSessionIsRevoked((int) $user->id)) {
            auth()->logout();
            session()->destroy();

            if (\App\Libraries\Http\RequestPath::isApi($request)) {
                return ApiResponse::error(Services::response(), 'SESSION_EXPIRED', 'Your access has changed. Please sign in again.', Services::requestContext()->requestId(), 401);
            }

            return redirect()->to(service('sameOriginUrl')->to('auth?expired=1'));
        }
        if ($startedAt === 0) {
            session()->set(self::STARTED_AT_KEY, time());

            return null;
        }

        if ($startedAt >= time() - self::ABSOLUTE_LIFETIME_SECONDS) {
            return null;
        }

        if ($user !== null && $user->inGroup('platform_admin')) {
            Services::platformMfa()->clear((int) $user->id, 'Platform Administrator session reached its absolute lifetime.');
        }
        auth()->logout();
        session()->destroy();

        if (\App\Libraries\Http\RequestPath::isApi($request)) {
            return ApiResponse::error(
                Services::response(),
                'SESSION_EXPIRED',
                'Your session has expired. Please sign in again.',
                Services::requestContext()->requestId(),
                401,
            );
        }

        return redirect()->to(service('sameOriginUrl')->to('auth?expired=1'));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
        if (auth()->loggedIn()) {
            Services::userSessionRevocation()->markCurrentSession();
        }
    }
}
