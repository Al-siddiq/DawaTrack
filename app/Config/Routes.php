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

$routes->get('change-password', 'Web\\PasswordChangeController::show', ['as' => 'password.change','filter' => 'authenticated',
    ]
);

$routes->post('change-password', 'Web\\PasswordChangeController::update', ['filter' => 'authenticated',]);

service('auth')->routes($routes, ['except' => ['register', 'logout']]);

$routes->group('api/v1', static function (RouteCollection $routes): void {
    $routes->get('health/live', 'Api\HealthController::live', ['as' => 'api.health.live']);
    $routes->get('health/ready', 'Api\HealthController::ready', ['as' => 'api.health.ready']);
    $routes->get('shop/opening-cash', 'Api\OpeningCashController::state', ['filter' => 'authenticated']);
    $routes->post('shop/opening-cash', 'Api\OpeningCashController::start', ['filter' => 'authenticated']);
    $routes->post('shop/opening-cash/(:segment)/count', 'Api\OpeningCashController::count/$1', ['filter' => 'authenticated']);
    $routes->post('shop/opening-cash/(:segment)/commit-count', 'Api\OpeningCashController::commitCount/$1', ['filter' => 'authenticated']);
    $routes->post('shop/opening-cash/(:segment)/review', 'Api\OpeningCashController::review/$1', ['filter' => 'authenticated']);
    $routes->post('shop/opening-cash/(:segment)/finalize', 'Api\OpeningCashController::finalize/$1', ['filter' => 'authenticated']);
    $routes->post('shop/opening-cash/(:segment)/abort', 'Api\OpeningCashController::abort/$1', ['filter' => 'authenticated']);
    $routes->get('shop/customers', 'Api\CustomerController::index', ['filter' => 'authenticated']);
    $routes->post('shop/customers', 'Api\CustomerController::create', ['filter' => 'authenticated']);
    $routes->get('shop/customers/(:segment)', 'Api\CustomerController::show/$1', ['filter' => 'authenticated']);
    $routes->put('shop/customers/(:segment)', 'Api\CustomerController::update/$1', ['filter' => 'authenticated']);
    $routes->post('shop/customers/(:segment)/deactivate', 'Api\CustomerController::deactivate/$1', ['filter' => 'authenticated']);
    $routes->post('shop/sales/(:segment)/returns', 'Api\ReturnController::create/$1', ['filter' => 'authenticated']);
    $routes->get('shop/sales/(:segment)/returnable-lines', 'Api\ReturnController::eligible/$1', ['filter' => 'authenticated']);
    $routes->get('shop/returns', 'Api\ReturnController::index', ['filter' => 'authenticated']);
    $routes->get('shop/returns/(:segment)', 'Api\ReturnController::detail/$1', ['filter' => 'authenticated']);
    $routes->put('shop/returns/(:segment)', 'Api\ReturnController::update/$1', ['filter' => 'authenticated']);
    $routes->get('shop/returns/(:segment)/estimate', 'Api\ReturnController::estimate/$1', ['filter' => 'authenticated']);
    $routes->post('shop/returns/(:segment)/preview', 'Api\ReturnController::preview/$1', ['filter' => 'authenticated']);
    $routes->post('shop/returns/(:segment)/post', 'Api\ReturnController::post/$1', ['filter' => 'authenticated']);
    $routes->delete('shop/returns/(:segment)', 'Api\ReturnController::discard/$1', ['filter' => 'authenticated']);
    $routes->get('shop/sales', 'Api\SaleController::index', ['filter' => 'authenticated']);
    $routes->get('shop/sales/(:segment)', 'Api\SaleController::show/$1', ['filter' => 'authenticated']);
    $routes->post('shop/sales/(:segment)/post', 'Api\SaleController::post/$1', ['filter' => 'authenticated']);
    $routes->get('shop/sale-credit/(:segment)', 'Api\SaleController::receivable/$1', ['filter' => 'authenticated']);
    $routes->get('shop/sale-drafts', 'Api\SaleDraftController::index', ['filter' => 'authenticated']);
    $routes->post('shop/sale-drafts', 'Api\SaleDraftController::create', ['filter' => 'authenticated']);
    $routes->get('shop/sale-draft-lookups/products', 'Api\SaleDraftController::products', ['filter' => 'authenticated']);
    $routes->get('shop/sale-draft-lookups/customers', 'Api\SaleDraftController::customers', ['filter' => 'authenticated']);
    $routes->get('shop/sale-drafts/(:segment)', 'Api\SaleDraftController::show/$1', ['filter' => 'authenticated']);
    $routes->put('shop/sale-drafts/(:segment)', 'Api\SaleDraftController::update/$1', ['filter' => 'authenticated']);
    $routes->post('shop/sale-drafts/(:segment)/items', 'Api\SaleDraftController::addItem/$1', ['filter' => 'authenticated']);
    $routes->put('shop/sale-drafts/(:segment)/items/(:segment)', 'Api\SaleDraftController::updateItem/$1/$2', ['filter' => 'authenticated']);
    $routes->delete('shop/sale-drafts/(:segment)/items/(:segment)', 'Api\SaleDraftController::removeItem/$1/$2', ['filter' => 'authenticated']);
    $routes->delete('shop/sale-drafts/(:segment)', 'Api\SaleDraftController::discard/$1', ['filter' => 'authenticated']);
    $routes->post('shop/sale-drafts/(:segment)/preview', 'Api\SaleDraftController::preview/$1', ['filter' => 'authenticated']);
    $routes->get('shop/context', 'Api\ShopController::contextInfo', ['filter' => 'authenticated']);
    $routes->get('shop/products', 'Api\ProductController::index', ['filter' => 'authenticated']);
    $routes->post('shop/products', 'Api\ProductController::create', ['filter' => 'authenticated']);
    $routes->get('shop/products/(:segment)', 'Api\ProductController::show/$1', ['filter' => 'authenticated']);
    $routes->put('shop/products/(:segment)', 'Api\ProductController::update/$1', ['filter' => 'authenticated']);
    $routes->post('shop/products/(:segment)/deactivate', 'Api\ProductController::deactivate/$1', ['filter' => 'authenticated']);
    $routes->put('shop/products/(:segment)/low-stock-threshold', 'Api\ProductController::threshold/$1', ['filter' => 'authenticated']);
    $routes->get('shop/products/(:segment)/stock-history', 'Api\StockIntakeController::productHistory/$1', ['filter' => 'authenticated']);
    $routes->get('shop/stock-intakes', 'Api\StockIntakeController::index', ['filter' => 'authenticated']);
    $routes->post('shop/stock-intakes', 'Api\StockIntakeController::create', ['filter' => 'authenticated']);
    $routes->get('shop/stock-intakes/(:segment)', 'Api\StockIntakeController::show/$1', ['filter' => 'authenticated']);
    $routes->put('shop/stock-intakes/(:segment)', 'Api\StockIntakeController::update/$1', ['filter' => 'authenticated']);
    $routes->post('shop/stock-intakes/(:segment)/items', 'Api\StockIntakeController::addItem/$1', ['filter' => 'authenticated']);
    $routes->put('shop/stock-intakes/(:segment)/items/(:segment)', 'Api\StockIntakeController::updateItem/$1/$2', ['filter' => 'authenticated']);
    $routes->delete('shop/stock-intakes/(:segment)/items/(:segment)', 'Api\StockIntakeController::removeItem/$1/$2', ['filter' => 'authenticated']);
    $routes->delete('shop/stock-intakes/(:segment)', 'Api\StockIntakeController::discard/$1', ['filter' => 'authenticated']);
    $routes->post('shop/stock-intakes/(:segment)/post', 'Api\StockIntakeController::post/$1', ['filter' => 'authenticated']);
    $routes->post('shop/stock-intakes/(:segment)/reverse', 'Api\StockIntakeController::reverse/$1', ['filter' => 'authenticated']);
    $routes->post('shop/stock-intakes/(:segment)/replacement', 'Api\StockIntakeController::replacement/$1', ['filter' => 'authenticated']);
    $routes->get('shop/stock-movements/(:segment)', 'Api\StockMovementController::show/$1', ['filter' => 'authenticated']);
    $routes->post('shop/stock-movements', 'Api\StockMovementController::create', ['filter' => 'authenticated']);
    $routes->post('shop/stock-movements/(:segment)/reverse', 'Api\StockMovementController::reverse/$1', ['filter' => 'authenticated']);
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
    $routes->get('shop/audit-events', 'Api\AuditController::ownerIndex', ['filter' => 'authenticated']);
    $routes->get('shop/audit-events/(:segment)', 'Api\AuditController::ownerDetail/$1', ['filter' => 'authenticated']);
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
    $routes->get('platform/audit-events', 'Api\AuditController::platformIndex', ['filter' => 'platformAdmin:platform.audit']);
    $routes->get('platform/audit-events/(:segment)', 'Api\AuditController::platformDetail/$1', ['filter' => 'platformAdmin:platform.audit']);
    $routes->post('platform/audit-events/support/query', 'Api\AuditController::supportQuery', ['filter' => 'platformAdmin:platform.audit_support']);
    $routes->post('platform/audit-events/support/events/(:segment)', 'Api\AuditController::supportDetail/$1', ['filter' => 'platformAdmin:platform.audit_support']);
});
