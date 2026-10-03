<?php

declare(strict_types=1);

use App\Modules\BrandingDomain\Application\BrandResolver;

/**
 * The goal of this file is to allow developers a location
 * where they can overwrite core procedural functions and
 * replace them with their own. This file is loaded during
 * the bootstrap process and is called during the framework's
 * execution.
 *
 * This can be looked at as a `master helper` file that is
 * loaded early on, and may also contain additional functions
 * that you'd like to use throughout your entire application
 *
 * @see: https://codeigniter.com/user_guide/extending/common.html
 */

if (! function_exists('dawatrack_auth_brand')) {
    /** @return array<string, mixed>|null */
    function dawatrack_auth_brand(): ?array
    {
        try {
            $request = service('request');

            $host = method_exists($request, 'getServer')
                ? (string) $request->getServer('HTTP_HOST')
                : '';

            return (new BrandResolver(
                db_connect()
            ))->resolve($host);
        } catch (\Throwable) {
            return null;
        }
    }
}

if (! function_exists('dt_url')) {
    function dt_url(string $target = '/'): string
    {
        return service('sameOriginUrl')->to($target);
    }
}

if (! function_exists('dt_route')) {
    function dt_route(
        string $routeName,
        mixed ...$params
    ): string {
        $path = route_to(
            $routeName,
            ...$params
        );

        if ($path === false) {
            throw new LogicException(
                "Unknown route: {$routeName}"
            );
        }

        return dt_url($path);
    }
}