<?php

use Illuminate\Support\Facades\Route;
use Modules\Settings\Controllers\SettingController;

Route::middleware('auth:sanctum')->prefix('settings')->group(function () {
    Route::get('/', [SettingController::class, 'index']);
    Route::put('/batch', [SettingController::class, 'batchUpdate']);
    Route::get('/{key}', [SettingController::class, 'show']);
    Route::put('/{key}', [SettingController::class, 'update']);
});
