<?php

use Illuminate\Support\Facades\Route;
use Modules\Graduation\Controllers\YudisiumApprovalController;
use Modules\Graduation\Controllers\YudisiumEligibilityController;
use Modules\Graduation\Controllers\YudisiumParticipantController;
use Modules\Graduation\Controllers\YudisiumPeriodController;
use Modules\Graduation\Controllers\YudisiumRequirementController;

Route::middleware('auth:sanctum')->prefix('graduation/yudisium')->group(function () {
    // 1. Periods (Periode Yudisium)
    Route::apiResource('periods', YudisiumPeriodController::class);

    // 2. Eligibility (Eligible Yudisium)
    Route::get('eligible', [YudisiumEligibilityController::class, 'index']);
    Route::post('eligible/register', [YudisiumEligibilityController::class, 'register']);

    // 3. Approvals (Persetujuan Yudisium)
    Route::get('approvals', [YudisiumApprovalController::class, 'index']);
    Route::put('approvals/{participant}/status', [YudisiumApprovalController::class, 'updateStatus']);
    Route::post('approvals/finalize', [YudisiumApprovalController::class, 'finalizeParticipants']);

    // 4. Participants (Peserta Yudisium)
    Route::get('participants', [YudisiumParticipantController::class, 'index']);
    Route::get('participants/{participant}', [YudisiumParticipantController::class, 'show']);
    Route::post('participants/input-sk-batch', [YudisiumParticipantController::class, 'inputSkBatch']);
    Route::patch('participants/{participant}/toggle-certificate', [YudisiumParticipantController::class, 'toggleCertificate']);

    // 5. Requirements (Syarat Yudisium)
    Route::apiResource('requirements', YudisiumRequirementController::class);
});
