<?php

declare(strict_types=1);

namespace App\Filters;

use App\Libraries\Http\ApiResponse;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Libraries\Http\RequestPath;
use Config\Services;

final class SensitiveRateLimitFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null): ?ResponseInterface
    {
        $path = RequestPath::normalized($request);
        $isAuditQuery = str_contains($path, '/audit-events');
        if (in_array(strtoupper($request->getMethod()), ['GET', 'HEAD', 'OPTIONS'], true) && ! $isAuditQuery) {
            return null;
        }

        [$capacity, $seconds] = $this->limitFor($path);
        $identity = auth()->id() ?? $request->getIPAddress();
        $bucket = $this->bucketFor($path);
        $key = hash('sha256', $identity . '|' . $bucket);

        if (service('throttler')->check($key, $capacity, $seconds)) {
            return null;
        }

        $retryAfter = service('throttler')->getTokenTime();
        if (RequestPath::isApi($request)) {
            return ApiResponse::error(
                Services::response()->setHeader('Retry-After', (string) $retryAfter),
                'RATE_LIMITED',
                'Too many attempts. Please wait and try again.',
                Services::requestContext()->requestId(),
                429,
            );
        }

        return Services::response()
            ->setStatusCode(429)
            ->setHeader('Retry-After', (string) $retryAfter)
            ->setBody(view('auth/rate_limited', ['retryAfter' => $retryAfter]));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
    }

    /** @return array{int, int} */
    private function limitFor(string $path): array
    {
        if (str_contains($path, 'magic-link')) {
            return [3, 900];
        }

        if (str_contains($path, 'auth/a/') || str_contains($path, '/mfa/')) {
            return [6, 900];
        }

        if (str_starts_with($path, '/api/v1/platform/')) {
            if (str_contains($path, '/audit-events')) {
                return [30, 60];
            }

            return [20, 60];
        }

        if (str_contains($path, '/shop/audit-events')) {
            return [60, 60];
        }

        if (str_contains($path, '/reset-password')) {
            return [5, 300];
        }

        if (str_starts_with($path, '/api/v1/shop/staff')) {
            return [20, 60];
        }

        return [5, 300];
    }

    private function bucketFor(string $path): string
    {
        if (str_contains($path, '/platform/audit-events')) {
            return '/api/v1/platform/audit-events';
        }

        if (str_contains($path, '/shop/audit-events')) {
            return '/api/v1/shop/audit-events';
        }

        return $path;
    }
}
