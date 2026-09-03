<?php

use Illuminate\Support\Facades\Route;
use Modules\Thesis\Controllers\ThesisController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('theses', [ThesisController::class, 'index']);
    Route::post('theses', [ThesisController::class, 'store']);
    Route::get('theses/{thesis}', [ThesisController::class, 'show']);
    Route::put('theses/{thesis}', [ThesisController::class, 'update']);
    Route::delete('theses/{thesis}', [ThesisController::class, 'destroy']);
});
