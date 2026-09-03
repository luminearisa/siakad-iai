<?php

use Illuminate\Support\Facades\Route;
use Modules\Audit\Controllers\AuditLogController;

Route::middleware(['auth:sanctum', 'permission:audit.view'])->prefix('audit')->group(function () {
    Route::get('logs', [AuditLogController::class, 'index']);
    Route::get('logs/{auditLog}', [AuditLogController::class, 'show']);
});
