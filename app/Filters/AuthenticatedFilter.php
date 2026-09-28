<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Libraries\Http\ApiResponse;
use Config\Services;

final class AuthenticatedFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null): ?ResponseInterface
    {
        if (! auth()->loggedIn()) {
            if (str_starts_with($request->getUri()->getPath(), 'api/')) {
                return ApiResponse::error(
                    Services::response(),
                    'AUTHENTICATION_REQUIRED',
                    'Authentication is required.',
                    Services::requestContext()->requestId(),
                    401,
                );
            }

            session()->setTempdata('beforeLoginUrl', $this->safePath($request->getUri()->getPath()), 600);

            return redirect()->to(site_url('auth'));
        }

        return null;
    }

    private function safePath(string $path): string
    {
        return str_starts_with($path, '/') && ! str_starts_with($path, '//') ? $path : '/';
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
    }
}
