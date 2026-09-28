<?php

declare(strict_types=1);

namespace App\Filters;

use App\Libraries\Http\ApiResponse;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

final class SensitiveRateLimitFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null): ?ResponseInterface
    {
        if (in_array(strtoupper($request->getMethod()), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return null;
        }

        [$capacity, $seconds] = $this->limitFor($request->getUri()->getPath());
        $identity = auth()->id() ?? $request->getIPAddress();
        $key = hash('sha256', $identity . '|' . $request->getUri()->getPath());

        if (service('throttler')->check($key, $capacity, $seconds)) {
            return null;
        }

        $retryAfter = service('throttler')->getTokenTime();
        if (str_starts_with($request->getUri()->getPath(), 'api/')) {
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

        if (str_starts_with($path, 'api/v1/platform/')) {
            return [20, 60];
        }

        return [5, 300];
    }
}
