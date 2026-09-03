<?php

use Illuminate\Support\Facades\Route;
use Modules\Lecturer\Controllers\LecturerController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('lecturers', [LecturerController::class, 'index'])->middleware('permission:lecturers.view');
    Route::post('lecturers', [LecturerController::class, 'store'])->middleware('permission:lecturers.create');
    Route::get('lecturers/{lecturer}', [LecturerController::class, 'show'])->middleware('permission:lecturers.view');
    Route::put('lecturers/{lecturer}', [LecturerController::class, 'update'])->middleware('permission:lecturers.update');
    Route::put('lecturers/{lecturer}/quotas', [LecturerController::class, 'updateQuotas'])->middleware('permission:lecturers.update');
    Route::delete('lecturers/{lecturer}', [LecturerController::class, 'destroy'])->middleware('permission:lecturers.delete');
    Route::patch('lecturers/{lecturer}/status', [LecturerController::class, 'changeStatus'])->middleware('permission:lecturers.change_status');
});
