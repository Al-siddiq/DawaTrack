<?php

declare(strict_types=1);

namespace App\Filters;

use App\Libraries\Http\RequestPath;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

final class SecurityHeadersFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null): void
    {
    }

    public function after(
        RequestInterface $request,
        ResponseInterface $response,
        $arguments = null
    ): void {
        /*
         * A fresh request-scoped nonce is shared with the rendered views.
         *
         * DawaTrack uses nonce-protected <style> blocks for constrained
         * dynamic Shop branding while keeping unsafe-inline disabled.
         */
        $styleNonce = Services::cspNonce()->value();

        $response
            ->setHeader(
                'X-Content-Type-Options',
                'nosniff'
            )
            ->setHeader(
                'Referrer-Policy',
                'strict-origin-when-cross-origin'
            )
            ->setHeader(
                'Permissions-Policy',
                'camera=(), microphone=(), geolocation=()'
            )
            ->setHeader(
                'Cross-Origin-Opener-Policy',
                'same-origin'
            )
            ->setHeader(
                'Content-Security-Policy',
                "default-src 'self'; "
                . "script-src 'self'; "
                . "style-src 'self' 'nonce-{$styleNonce}'; "
                . "img-src 'self' data:; "
                . "font-src 'self'; "
                . "connect-src 'self'; "
                . "object-src 'none'; "
                . "base-uri 'self'; "
                . "frame-ancestors 'none'; "
                . "form-action 'self'"
            );

        $response->setHeader(
            config('Security')->headerName,
            csrf_hash()
        );

        /*
         * Authentication and Platform Admin pages must never be cached.
         */
        $path = RequestPath::normalized($request);

        if (
            $path === '/login'
            || str_starts_with($path, '/login/')
            || $path === '/auth'
            || str_starts_with($path, '/auth/')
            || str_starts_with($path, '/platform-admin')
        ) {
            $response->setHeader(
                'Cache-Control',
                'no-store, private'
            );
        }

        if (ENVIRONMENT === 'production') {
            $response->setHeader(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }
    }
}