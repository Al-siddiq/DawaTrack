<?php

declare(strict_types=1);

namespace App\Modules\BrandingDomain\Application;

use DomainException;

final class HostnamePolicy
{
    public function normalize(string $input): string
    {
        $host = rtrim(trim($input), '.');
        if ($host === '' || str_contains($host, '://') || preg_match('/[\/:?#@]/', $host)) {
            throw new DomainException('Enter a hostname without a scheme, port, path, or credentials.');
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false || str_contains($host, '*')) {
            throw new DomainException('IP addresses and wildcard hostnames are not supported.');
        }
        if (preg_match('/[^\x00-\x7F]/', $host)) {
            if (! function_exists('idn_to_ascii')) {
                throw new DomainException('International hostnames require the PHP intl extension.');
            }
            $ascii = idn_to_ascii($host, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);
            if ($ascii === false) {
                throw new DomainException('The international hostname is invalid.');
            }
            $host = $ascii;
        }
        $host = strtolower($host);
        if (strlen($host) > 253 || preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $host) !== 1) {
            throw new DomainException('Enter a valid fully-qualified hostname with valid DNS labels.');
        }
        $platform = strtolower(trim((string) env('DAWATRACK_PLATFORM_DOMAIN', 'dawatrack.local')));
        if ($host === $platform || str_ends_with($host, '.' . $platform)) {
            throw new DomainException('Platform hostnames cannot be requested as custom domains.');
        }
        $reserved = ['localhost', 'local', 'internal', 'invalid', 'test', 'example'];
        $labels = explode('.', $host);
        if (in_array($labels[0], ['www', 'admin', 'api', 'mail'], true) || in_array(end($labels), $reserved, true)) {
            throw new DomainException('That hostname is reserved.');
        }
        return $host;
    }

    public function recordName(string $hostname): string
    {
        return '_dawatrack-verification.' . $hostname;
    }
}
