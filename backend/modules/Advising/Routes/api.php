<?php

use Illuminate\Support\Facades\Route;
use Modules\Advising\Controllers\AcademicAdvisorController;
use Modules\Advising\Controllers\AdvisingSessionController;

Route::middleware('auth:sanctum')->group(function () {
    // Advisor Distribution Endpoints
    Route::get('advising/distribution', [AcademicAdvisorController::class, 'distribution'])->middleware('permission:advising.view');
    Route::post('advising/batch-assign', [AcademicAdvisorController::class, 'batchAssign'])->middleware('permission:advising.assign');
    Route::post('advising/generate', [AcademicAdvisorController::class, 'generate'])->middleware('permission:advising.assign');

    // Advisor Management
    Route::get('advisors', [AcademicAdvisorController::class, 'index'])->middleware('permission:advising.view');
    Route::post('advisors', [AcademicAdvisorController::class, 'store'])->middleware('permission:advising.assign');
    Route::get('advisors/{advisor}', [AcademicAdvisorController::class, 'show'])->middleware('permission:advising.view');
    Route::patch('advisors/{advisor}/end', [AcademicAdvisorController::class, 'endAdvisor'])->middleware('permission:advising.assign');

    // Student Advisor Shortcuts
    Route::get('students/{student}/advisor', [AcademicAdvisorController::class, 'currentStudentAdvisor'])->middleware('permission:advising.view');
    Route::get('students/{student}/advisor-history', [AcademicAdvisorController::class, 'studentAdvisorHistory'])->middleware('permission:advising.view');
    Route::post('students/{student}/advisor', [AcademicAdvisorController::class, 'changeAdvisor'])->middleware('permission:advising.assign');

    // Advising Sessions
    Route::get('advising-sessions', [AdvisingSessionController::class, 'index'])->middleware('permission:advising.view');
    Route::post('advising-sessions', [AdvisingSessionController::class, 'store'])->middleware('permission:advising.create_session');
    Route::get('advising-sessions/{session}', [AdvisingSessionController::class, 'show'])->middleware('permission:advising.view');
    Route::put('advising-sessions/{session}', [AdvisingSessionController::class, 'update'])->middleware('permission:advising.update_session');
    Route::delete('advising-sessions/{session}', [AdvisingSessionController::class, 'destroy'])->middleware('permission:advising.update_session');
});
