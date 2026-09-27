<?php

declare(strict_types=1);

/**
 * Front controller for the standalone integrator dashboard.
 */

use Integrator\App;
use Integrator\Support\Config;
use Integrator\Web\Controllers\AuthController;
use Integrator\Web\Controllers\DashboardController;
use Integrator\Web\Controllers\LogController;
use Integrator\Web\Controllers\MappingController;
use Integrator\Web\Controllers\ReferenceController;
use Integrator\Web\Controllers\SettingsController;
use Integrator\Web\Controllers\SyncController;
use Integrator\Web\Router;

$root = dirname(__DIR__);

require $root.'/src/autoload.php';

Config::boot($root);

$app = new App($root);
$app->boot();

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => (($_SERVER['HTTPS'] ?? '') === 'on'),
]);
session_start();
csrf_token();

$router = new Router($app);

$router->get('/login', [AuthController::class, 'showLogin'], auth: false);
$router->post('/login', [AuthController::class, 'login'], auth: false);
$router->post('/logout', [AuthController::class, 'logout']);

$router->get('/', [DashboardController::class, 'index']);
$router->post('/dashboard/check', [DashboardController::class, 'check']);

$router->get('/settings', [SettingsController::class, 'index']);
$router->post('/settings', [SettingsController::class, 'save']);
$router->post('/settings/default-semester', [SettingsController::class, 'defaultSemester']);

$router->get('/sync', [SyncController::class, 'index']);
$router->post('/sync/run', [SyncController::class, 'run']);
$router->get('/sync/run/{runId}', [SyncController::class, 'show']);
$router->post('/sync/retry', [SyncController::class, 'retryRow']);

$router->get('/logs', [LogController::class, 'index']);

$router->get('/mappings', [MappingController::class, 'index']);
$router->post('/mappings/update', [MappingController::class, 'update']);
$router->post('/mappings/delete', [MappingController::class, 'delete']);

$router->get('/reference', [ReferenceController::class, 'index']);
$router->post('/reference/rename', [ReferenceController::class, 'rename']);

$response = $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
$response->send();
