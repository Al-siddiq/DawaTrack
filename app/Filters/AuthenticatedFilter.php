<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Libraries\Http\ApiResponse;
use App\Libraries\Http\RequestPath;
use Config\Services;

final class AuthenticatedFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null): ?ResponseInterface
    {
        if (! auth()->loggedIn()) {
            if (RequestPath::isApi($request)) {
                return ApiResponse::error(
                    Services::response(),
                    'AUTHENTICATION_REQUIRED',
                    'Authentication is required.',
                    Services::requestContext()->requestId(),
                    401,
                );
            }

            session()->setTempdata('beforeLoginUrl', $this->safePath($request->getUri()->getPath()), 600);

            return redirect()->to(service('sameOriginUrl')->to('auth'));
        }

        $path = strtolower(rawurldecode(RequestPath::normalized($request)));
        $path = preg_replace('#/+#', '/', $path);
        $indexPage = strtolower(trim((string) config('App')->indexPage, '/'));
        if ($indexPage !== '' && str_starts_with($path, '/' . $indexPage . '/')) {
            $path = substr($path, strlen($indexPage) + 1);
        }
        if (str_starts_with($path, '/api/v1/shop/')) {
            try {
                $context = \App\Modules\TenantShop\Infrastructure\ShopContext::resolve(
                    db_connect(), (int) auth()->id(), $request->getServer('HTTP_HOST') ?? '',
                );
                (new \App\Modules\Reconciliation\Application\BlindReadPolicy(db_connect()))->assertSourceVisible($context, $path);
            } catch (\App\Exceptions\ApiException $exception) {
                $response = Services::response()->setHeader('Cache-Control', 'no-store, private');
                return ApiResponse::error($response, $exception->errorCode, $exception->getMessage(),
                    Services::requestContext()->requestId(), $exception->status, $exception->details);
            }
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
