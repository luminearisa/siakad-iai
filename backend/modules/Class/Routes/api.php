<?php

use Illuminate\Support\Facades\Route;
use Modules\Class\Controllers\ClassController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('classes', [ClassController::class, 'index'])->middleware('permission:classes.view');
    Route::post('classes', [ClassController::class, 'store'])->middleware('permission:classes.create');
    Route::get('classes/{class}', [ClassController::class, 'show'])->middleware('permission:classes.view');
    Route::put('classes/{class}', [ClassController::class, 'update'])->middleware('permission:classes.update');
    Route::delete('classes/{class}', [ClassController::class, 'destroy'])->middleware('permission:classes.delete');

    Route::patch('classes/{class}/open', [ClassController::class, 'open'])->middleware('permission:classes.open');
    Route::patch('classes/{class}/close', [ClassController::class, 'close'])->middleware('permission:classes.close');
    Route::patch('classes/{class}/cancel', [ClassController::class, 'cancel'])->middleware('permission:classes.cancel');

    Route::get('classes/{class}/lecturers', [ClassController::class, 'lecturers'])->middleware('permission:classes.view');
    Route::post('classes/{class}/lecturers', [ClassController::class, 'assignLecturer'])->middleware('permission:classes.assign_lecturer');
    Route::delete('classes/{class}/lecturers/{lecturerId}', [ClassController::class, 'removeLecturer'])->middleware('permission:classes.assign_lecturer');

    // Master Perkuliahan
    Route::apiResource('lecture-session-types', \Modules\Class\Controllers\LectureSessionTypeController::class);
    Route::apiResource('lecture-programs', \Modules\Class\Controllers\LectureProgramController::class);
    Route::apiResource('grading-components', \Modules\Class\Controllers\GradingComponentController::class);
    Route::apiResource('student-groups', \Modules\Class\Controllers\StudentGroupController::class);
});
