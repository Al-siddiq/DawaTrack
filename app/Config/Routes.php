<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->setDefaultNamespace('App\Controllers');
$routes->setDefaultController('Web\ShellController');
$routes->setDefaultMethod('shop');
$routes->setAutoRoute(false);
$routes->set404Override('App\Controllers\Api\ErrorController::notFound');

$routes->get('/', 'Web\ShellController::shop', ['as' => 'shop.shell', 'filter' => 'authenticated']);
$routes->get('brand-assets/(:segment)', 'Web\BrandAssetController::show/$1');
$routes->get('manifest.webmanifest', 'Web\ShellController::manifest', ['as' => 'pwa.manifest']);
$routes->get('auth', 'Web\ShellController::authentication', ['as' => 'auth.shell']);
$routes->get('platform-admin/login', 'Web\AdminAuthController::login', ['as' => 'admin.login']);
$routes->get('platform-admin/mfa', 'Web\AdminAuthController::stepUp', ['as' => 'admin.mfa', 'filter' => 'platformAdminIdentity']);
$routes->post('platform-admin/mfa/restart', 'Web\AdminAuthController::restart', ['as' => 'admin.mfa.restart', 'filter' => 'platformAdminIdentity']);
$routes->get('platform-admin', 'Web\ShellController::admin', ['as' => 'admin.shell', 'filter' => 'platformAdmin:platform.access']);
$routes->get('logout', 'Web\SecuritySessionController::logout', ['as' => 'dawatrack.logout']);

service('auth')->routes($routes, ['except' => ['register', 'logout']]);

$routes->group('api/v1', static function (RouteCollection $routes): void {
    $routes->get('health/live', 'Api\HealthController::live', ['as' => 'api.health.live']);
    $routes->get('health/ready', 'Api\HealthController::ready', ['as' => 'api.health.ready']);
    $routes->get('shop/context', 'Api\ShopController::contextInfo', ['filter' => 'authenticated']);
    $routes->get('shop/staff/(:num)/permissions', 'Api\ShopController::permissions/$1', ['filter' => 'authenticated']);
    $routes->post('shop/staff/(:num)/reset-password', 'Api\ShopController::resetPassword/$1', ['filter' => 'authenticated']);
    $routes->get('shop/staff', 'Api\ShopController::staff', ['filter' => 'authenticated']);
    $routes->post('shop/staff', 'Api\ShopController::createStaff', ['filter' => 'authenticated']);
    $routes->patch('shop/staff/(:num)/status', 'Api\ShopController::staffStatus/$1', ['filter' => 'authenticated']);
    $routes->post('shop/staff/(:num)/permissions', 'Api\ShopController::grant/$1', ['filter' => 'authenticated']);
    $routes->delete('shop/staff/(:num)/permissions', 'Api\ShopController::revoke/$1', ['filter' => 'authenticated']);
    $routes->get('shop/settings', 'Api\ShopController::settings', ['filter' => 'authenticated']);
    $routes->put('shop/settings', 'Api\ShopController::updateSettings', ['filter' => 'authenticated']);
    $routes->get('shop/branding', 'Api\ShopController::branding', ['filter' => 'authenticated']);
    $routes->put('shop/branding', 'Api\ShopController::updateBranding', ['filter' => 'authenticated']);
    $routes->get('shop/assets', 'Api\ShopController::assets', ['filter' => 'authenticated']);
    $routes->post('shop/assets', 'Api\ShopController::uploadAsset', ['filter' => 'authenticated']);
    $routes->get('shop/domains', 'Api\ShopController::domains', ['filter' => 'authenticated']);
    $routes->post('shop/domains', 'Api\ShopController::requestDomain', ['filter' => 'authenticated']);
    $routes->post('shop/domains/(:segment)/rotate', 'Api\ShopController::rotateDomain/$1', ['filter' => 'authenticated']);
    $routes->post('shop/domains/(:segment)/verify', 'Api\ShopController::verifyDomain/$1', ['filter' => 'authenticated']);
    $routes->get('shop/subscription', 'Api\ShopController::subscription', ['filter' => 'authenticated']);
    $routes->get('shop/assets/(:segment)', 'Api\AssetController::show/$1', ['filter' => 'authenticated']);
    $routes->post('platform/tenants', 'Api\OnboardingController::create', ['as' => 'api.platform.tenants.create', 'filter' => 'platformAdmin:platform.onboard']);
    $routes->get('platform/domains', 'Api\PlatformDomainController::index', ['filter' => 'platformAdmin:platform.domains']);
    $routes->post('platform/domains/(:segment)/tls', 'Api\PlatformDomainController::tls/$1', ['filter' => 'platformAdmin:platform.domains']);
    $routes->post('platform/domains/(:segment)/activate', 'Api\PlatformDomainController::activate/$1', ['filter' => 'platformAdmin:platform.domains']);
    $routes->post('platform/domains/(:segment)/primary', 'Api\PlatformDomainController::primary/$1', ['filter' => 'platformAdmin:platform.domains']);
    $routes->post('platform/domains/(:segment)/deactivate', 'Api\PlatformDomainController::deactivate/$1', ['filter' => 'platformAdmin:platform.domains']);
    $routes->post('platform/domains/(:segment)/fallback', 'Api\PlatformDomainController::fallback/$1', ['filter' => 'platformAdmin:platform.domains']);
    $routes->get('platform/operations/tenants', 'Api\PlatformOperationsController::tenants', ['filter' => 'platformAdmin:platform.access']);
    $routes->post('platform/operations/tenants/(:segment)/subscriptions', 'Api\PlatformOperationsController::subscription/$1', ['filter' => 'platformAdmin:platform.subscriptions']);
    $routes->post('platform/operations/tenants/(:segment)/(:segment)', 'Api\PlatformOperationsController::tenantLifecycle/$1/$2', ['filter' => 'platformAdmin:platform.tenants']);
    $routes->post('platform/operations/shops/(:segment)/(:segment)', 'Api\PlatformOperationsController::shopLifecycle/$1/$2', ['filter' => 'platformAdmin:platform.tenants']);
});
