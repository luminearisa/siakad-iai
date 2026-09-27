<?php

use Illuminate\Support\Facades\Route;
use Modules\Integrator\Controllers\ApiClientController;
use Modules\Integrator\Controllers\ApiKeyController;
use Modules\Integrator\Controllers\ApiRequestLogController;
use Modules\Integrator\Controllers\IntegrationController;
use Modules\Integrator\Controllers\IntegratorExportController;

/*
|--------------------------------------------------------------------------
| Integrator routes
|--------------------------------------------------------------------------
|
| Two surfaces live here:
|
| 1. `integrator/*`      — staff management of clients, keys and the access log.
|                          Session (Sanctum) auth + RBAC permissions.
| 2. `integrator/v1/*`   — read-only data API for external systems. API key auth,
|                          one scope per route, no session, writes nothing.
|
| The scope is declared on the route itself (not in a controller) so that a review
| of this single file answers "what can a key do?".
|
*/

Route::middleware('auth:sanctum')->prefix('integrator')->group(function () {
    // Available scopes for the key picker.
    Route::get('scopes', [ApiKeyController::class, 'scopes'])
        ->middleware('permission:integrator.keys.view');

    // Unduhan data pelaporan PDDikti / Neo Feeder ("Export as…").
    //
    // Didaftarkan SEBELUM `clients/{client}` supaya `/clients/export` tidak
    // tertangkap sebagai route parameter.
    Route::get('logs/export', [IntegratorExportController::class, 'logs'])
        ->middleware('permission:integrator.logs.view');
    Route::get('logs/summary/export', [IntegratorExportController::class, 'summary'])
        ->middleware('permission:integrator.logs.view');
    Route::get('clients/export', [IntegratorExportController::class, 'clients'])
        ->middleware('permission:integrator.clients.view');
    Route::get('keys/export', [IntegratorExportController::class, 'keys'])
        ->middleware('permission:integrator.keys.view');

    // Clients (the systems allowed to pull data).
    Route::get('clients', [ApiClientController::class, 'index'])
        ->middleware('permission:integrator.clients.view');
    Route::post('clients', [ApiClientController::class, 'store'])
        ->middleware('permission:integrator.clients.manage');
    Route::get('clients/{client}', [ApiClientController::class, 'show'])
        ->middleware('permission:integrator.clients.view');
    Route::put('clients/{client}', [ApiClientController::class, 'update'])
        ->middleware('permission:integrator.clients.manage');
    Route::delete('clients/{client}', [ApiClientController::class, 'destroy'])
        ->middleware('permission:integrator.clients.manage');

    // Keys.
    Route::get('keys', [ApiKeyController::class, 'index'])
        ->middleware('permission:integrator.keys.view');
    Route::get('clients/{client}/keys', [ApiKeyController::class, 'index'])
        ->middleware('permission:integrator.keys.view');
    Route::post('clients/{client}/keys', [ApiKeyController::class, 'store'])
        ->middleware('permission:integrator.keys.manage');
    Route::post('keys/{key}/rotate', [ApiKeyController::class, 'rotate'])
        ->middleware('permission:integrator.keys.manage');
    Route::post('keys/{key}/revoke', [ApiKeyController::class, 'revoke'])
        ->middleware('permission:integrator.keys.manage');
    Route::delete('keys/{key}', [ApiKeyController::class, 'destroy'])
        ->middleware('permission:integrator.keys.manage');

    // Access log.
    Route::get('logs', [ApiRequestLogController::class, 'index'])
        ->middleware('permission:integrator.logs.view');
    Route::get('logs/stats', [ApiRequestLogController::class, 'stats'])
        ->middleware('permission:integrator.logs.view');
});

Route::prefix('integrator/v1')->group(function () {
    // Any valid key.
    Route::get('ping', [IntegrationController::class, 'ping'])
        ->middleware('api.key');

    // Reference & academic structure.
    Route::get('profile', [IntegrationController::class, 'profile'])
        ->middleware('api.key:reference.read');
    Route::get('snapshot', [IntegrationController::class, 'snapshot'])
        ->middleware('api.key:reference.read');
    Route::get('semesters', [IntegrationController::class, 'semesters'])
        ->middleware('api.key:academic.read');

    // Master data.
    Route::get('students', [IntegrationController::class, 'students'])
        ->middleware('api.key:students.read');
    Route::get('students/{studentNumber}', [IntegrationController::class, 'student'])
        ->middleware('api.key:students.read');
    Route::get('lecturers', [IntegrationController::class, 'lecturers'])
        ->middleware('api.key:lecturers.read');
    Route::get('courses', [IntegrationController::class, 'courses'])
        ->middleware('api.key:courses.read');
    Route::get('curricula', [IntegrationController::class, 'curricula'])
        ->middleware('api.key:curricula.read');

    // Teaching & results.
    Route::get('classes', [IntegrationController::class, 'classes'])
        ->middleware('api.key:classes.read');
    Route::get('enrollments', [IntegrationController::class, 'enrollments'])
        ->middleware('api.key:enrollments.read');
    Route::get('akm', [IntegrationController::class, 'akm'])
        ->middleware('api.key:enrollments.read');
    Route::get('grades', [IntegrationController::class, 'grades'])
        ->middleware('api.key:grades.read');
    Route::get('activities', [IntegrationController::class, 'activities'])
        ->middleware('api.key:activities.read');
    Route::get('graduates', [IntegrationController::class, 'graduates'])
        ->middleware('api.key:graduation.read');
});
