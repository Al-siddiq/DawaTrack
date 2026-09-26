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
$routes->get('auth', 'Web\ShellController::authentication', ['as' => 'auth.shell']);
$routes->get('platform-admin', 'Web\ShellController::admin', ['as' => 'admin.shell', 'filter' => 'platformAdmin']);

service('auth')->routes($routes);

$routes->group('api/v1', static function (RouteCollection $routes): void {
    $routes->get('health/live', 'Api\HealthController::live', ['as' => 'api.health.live']);
    $routes->get('health/ready', 'Api\HealthController::ready', ['as' => 'api.health.ready']);
    $routes->post('platform/tenants', 'Api\OnboardingController::create', ['as' => 'api.platform.tenants.create', 'filter' => 'platformAdmin']);
});
