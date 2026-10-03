<?php

declare(strict_types=1);

namespace App\Libraries\Http;

use CodeIgniter\HTTP\RequestInterface;

final class RequestPath
{
    public static function normalized(RequestInterface $request): string
    {
        $path = '/' . ltrim($request->getUri()->getPath(), '/');

        $indexPage = trim((string) config('App')->indexPage, '/');

        if ($indexPage !== '') {
            $prefix = '/' . $indexPage;

            if ($path === $prefix) {
                return '/';
            }

            if (str_starts_with($path, $prefix . '/')) {
                $path = substr($path, strlen($prefix));
            }
        }

        return '/' . ltrim($path, '/');
    }

    public static function isApi(RequestInterface $request): bool
    {
        $path = self::normalized($request);

        return $path === '/api'
            || str_starts_with($path, '/api/');
    }
}