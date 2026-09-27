<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class SecurityHeadersFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null): void
    {
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
        $response
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->setHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->setHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->setHeader('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");

        if (ENVIRONMENT === 'production') {
            $response->setHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
    }
}
