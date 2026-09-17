<?php

use Illuminate\Support\Facades\Route;
use Modules\Enrollment\Controllers\EnrollmentController;
use Modules\Enrollment\Controllers\EnrollmentItemController;
use Modules\Enrollment\Controllers\KrsPackageController;

Route::middleware('auth:sanctum')->group(function () {
    // KRS Packages Endpoints
    Route::apiResource('krs-packages', KrsPackageController::class)->middleware('permission:enrollments.view');
    // General Enrollment Endpoints
    Route::get('enrollments', [EnrollmentController::class, 'index'])->middleware('permission:enrollments.view');
    Route::post('enrollments', [EnrollmentController::class, 'store']);
    Route::post('enrollments/generate', [EnrollmentController::class, 'generate'])->middleware('permission:enrollments.view');
    Route::put('enrollments/{enrollment}/advisor-quota', [EnrollmentController::class, 'updateAdvisorAndQuota'])->middleware('permission:enrollments.view');
    Route::get('enrollments/{enrollment}', [EnrollmentController::class, 'show'])->middleware('permission:enrollments.view');

    // Student specific enrollments
    Route::get('students/{student}/enrollments', [EnrollmentController::class, 'studentEnrollments'])->middleware('permission:enrollments.view');
    Route::post('students/{student}/enrollments', [EnrollmentController::class, 'storeStudentEnrollment']);

    // Items (Classes in KRS)
    Route::get('enrollments/{enrollment}/available-classes', [EnrollmentController::class, 'availableClasses'])->middleware('permission:enrollments.view');
    Route::get('enrollments/{enrollment}/items', [EnrollmentItemController::class, 'index'])->middleware('permission:enrollments.view');
    Route::post('enrollments/{enrollment}/items', [EnrollmentItemController::class, 'store']);
    Route::delete('enrollments/{enrollment}/items/{item}', [EnrollmentItemController::class, 'destroy']);

    // Workflow Endpoints
    Route::post('enrollments/{enrollment}/submit', [EnrollmentController::class, 'submit'])->middleware('permission:enrollments.submit');
    Route::post('enrollments/{enrollment}/approve', [EnrollmentController::class, 'approve'])->middleware('permission:enrollments.approve');
    Route::post('enrollments/{enrollment}/reject', [EnrollmentController::class, 'reject'])->middleware('permission:enrollments.reject');
    Route::post('enrollments/{enrollment}/request-revision', [EnrollmentController::class, 'requestRevision'])->middleware('permission:enrollments.revise');
    Route::post('enrollments/{enrollment}/lock', [EnrollmentController::class, 'lock'])->middleware('permission:enrollments.lock');
    Route::post('enrollments/{enrollment}/load-package', [EnrollmentController::class, 'loadPackage']);
});
