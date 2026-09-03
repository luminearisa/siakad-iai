<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\Controllers\AuthController;
use Modules\Identity\Controllers\PermissionController;
use Modules\Identity\Controllers\RoleController;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('roles', RoleController::class);
    Route::get('permissions', [PermissionController::class, 'index']);
    Route::post('users/{user}/roles', [AuthController::class, 'assignRole']);
});
