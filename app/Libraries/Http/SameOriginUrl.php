<?php

declare(strict_types=1);

namespace App\Libraries\Http;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\IncomingRequest;
use InvalidArgumentException;
use Throwable;

final class SameOriginUrl
{
    public function __construct(
        private readonly BaseConnection $database,
        private readonly IncomingRequest $request,
    ) {
    }

    /**
     * Build an application URL on the current validated origin.
     *
     * The raw Host header is never trusted by itself:
     * - the configured Platform hostname is accepted; or
     * - the hostname must exist as an active, verified Shop domain.
     */
    public function to(string $target = '/'): string
    {
        $target = trim($target);

        if (
            preg_match('#^[a-z][a-z0-9+\-.]*://#i', $target)
            || str_starts_with($target, '//')
        ) {
            throw new InvalidArgumentException(
                'Only application-relative URL targets are allowed.'
            );
        }

        $parsedPath = parse_url($target, PHP_URL_PATH);
        $query = parse_url($target, PHP_URL_QUERY);

        $path = is_string($parsedPath) && $parsedPath !== ''
            ? '/' . ltrim($parsedPath, '/')
            : '/';

        $indexPage = trim(
            (string) config('App')->indexPage,
            '/'
        );

        /*
         * Avoid /index.php/index.php/... if a caller supplies
         * an already front-controller-prefixed path.
         */
        if ($indexPage !== '') {
            $indexPrefix = '/' . $indexPage;

            if ($path === $indexPrefix) {
                $path = '/';
            } elseif (str_starts_with($path, $indexPrefix . '/')) {
                $path = substr(
                    $path,
                    strlen($indexPrefix)
                );
            }

            $path = $path === '/'
                ? $indexPrefix
                : $indexPrefix . $path;
        }

        return $this->trustedOrigin()
            . $path
            . ($query !== null ? '?' . $query : '');
    }

    private function trustedOrigin(): string
    {
        /*
         * Do not use $request->getUri()->getHost() here.
         *
         * CI4's SiteURI may deliberately fall back to App::$baseURL
         * when the incoming hostname is not present in
         * App::$allowedHostnames.
         *
         * DawaTrack instead validates the actual HTTP Host against
         * its authoritative platform/domain data.
         */
        $rawAuthority = strtolower(
            trim(
                (string) $this->request->getServer(
                    'HTTP_HOST'
                )
            )
        );

        if (
            $rawAuthority === ''
            || preg_match('/[\x00-\x20\/\\\\@]/', $rawAuthority)
        ) {
            return $this->canonicalOrigin();
        }

        /*
         * Prefix with a dummy scheme so parse_url() can safely
         * separate hostname and development port.
         */
        $parts = parse_url(
            'http://' . $rawAuthority
        );

        if (
            $parts === false
            || empty($parts['host'])
        ) {
            return $this->canonicalOrigin();
        }

        $host = strtolower(
            rtrim(
                (string) $parts['host'],
                '.'
            )
        );

        if (! $this->isTrustedHost($host)) {
            return $this->canonicalOrigin();
        }

        $scheme = $this->request->isSecure()
            ? 'https'
            : 'http';

        $port = isset($parts['port'])
            ? (int) $parts['port']
            : null;

        $origin = $scheme . '://' . $host;

        if (
            $port !== null
            && ! ($scheme === 'http' && $port === 80)
            && ! ($scheme === 'https' && $port === 443)
        ) {
            $origin .= ':' . $port;
        }

        return $origin;
    }

    private function isTrustedHost(string $host): bool
    {
        if ($host === '') {
            return false;
        }

        $platformDomain = strtolower(
            rtrim(
                trim(
                    (string) env(
                        'DAWATRACK_PLATFORM_DOMAIN',
                        ''
                    )
                ),
                '.'
            )
        );

        if (
            $platformDomain !== ''
            && hash_equals(
                $platformDomain,
                $host
            )
        ) {
            return true;
        }

        try {
            if (
                ! $this->database->tableExists(
                    'shop_domains'
                )
            ) {
                return false;
            }

            return $this->database
                ->table('shop_domains')
                ->where('hostname', $host)
                ->where('is_active', 1)
                ->where(
                    'verification_status',
                    'VERIFIED'
                )
                ->countAllResults() === 1;
        } catch (Throwable) {
            return false;
        }
    }

    private function canonicalOrigin(): string
    {
        $baseUrl = rtrim(
            (string) config('App')->baseURL,
            '/'
        );

        $parts = parse_url($baseUrl);

        if (
            $parts === false
            || empty($parts['scheme'])
            || empty($parts['host'])
        ) {
            throw new \RuntimeException(
                'The configured application base URL is invalid.'
            );
        }

        $origin = strtolower(
            (string) $parts['scheme']
        )
            . '://'
            . strtolower(
                (string) $parts['host']
            );

        if (isset($parts['port'])) {
            $origin .= ':'
                . (int) $parts['port'];
        }

        return $origin;
    }
}