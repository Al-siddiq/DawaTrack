<?php

declare(strict_types=1);

namespace Config;

use App\Libraries\Http\RequestContext;
use CodeIgniter\Config\BaseService;
use App\Libraries\Http\CspNonce;
use App\Libraries\Http\SameOriginUrl;
use CodeIgniter\HTTP\IncomingRequest;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    public static function requestContext(bool $getShared = true): RequestContext
    {
        if ($getShared) {
            return static::getSharedInstance('requestContext');
        }

        return new RequestContext();
    }

    public static function auditService(bool $getShared = true): \App\Modules\Audit\Application\AuditService
    {
        if ($getShared) {
            return static::getSharedInstance('auditService');
        }

        $request = static::request();
        return new \App\Modules\Audit\Application\AuditService(
            db_connect(),
            static::requestContext(),
            $request instanceof \CodeIgniter\HTTP\IncomingRequest ? $request : null
        );
    }

    public static function changeSequenceService(bool $getShared = true): \App\Modules\Sync\Application\ChangeSequenceService
    {
        if ($getShared) return static::getSharedInstance('changeSequenceService');
        return new \App\Modules\Sync\Application\ChangeSequenceService(db_connect(), static::requestContext());
    }

    public static function onboardingService(bool $getShared = true): \App\Modules\TenantShop\Application\OnboardingService
    {
        if ($getShared) {
            return static::getSharedInstance('onboardingService');
        }

        return new \App\Modules\TenantShop\Application\OnboardingService(
            db_connect(),
            static::auditService()
        );
    }

    public static function platformMfa(bool $getShared = true): \App\Security\PlatformMfaService
    {
        if ($getShared) {
            return static::getSharedInstance('platformMfa');
        }

        return new \App\Security\PlatformMfaService(static::session(), static::auditService());
    }

    public static function userSessionRevocation(bool $getShared = true): \App\Security\UserSessionRevocationService
    {
        if ($getShared) {
            return static::getSharedInstance('userSessionRevocation');
        }

        return new \App\Security\UserSessionRevocationService(static::cache(), static::session());
    }

    public static function cspNonce(bool $getShared = true): CspNonce
    {
        if ($getShared) {
            return static::getSharedInstance('cspNonce');
        }

        return new CspNonce();
    }

    public static function sameOriginUrl(bool $getShared = true): SameOriginUrl 
    {
        if ($getShared) {
            return static::getSharedInstance(
                'sameOriginUrl'
            );
        }

        $request = static::request();

        if (! $request instanceof IncomingRequest) {
            throw new \LogicException(
                'Same-origin URL generation requires an HTTP request.'
            );
        }

        return new SameOriginUrl(
            db_connect(),
            $request
        );
    }

    /*
     * public static function example($getShared = true)
     * {
     *     if ($getShared) {
     *         return static::getSharedInstance('example');
     *     }
     *
     *     return new \CodeIgniter\Example();
     * }
     */
}
