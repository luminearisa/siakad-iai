<?php

use Illuminate\Support\Facades\Route;
use Modules\Student\Controllers\StudentController;
use Modules\Student\Controllers\StudentPortalController;

Route::middleware('auth:sanctum')->group(function () {
    // Student Portal (Self-Service)
    Route::get('students/me/profile', [StudentPortalController::class, 'profile']);
    Route::get('students/me/khs', [StudentPortalController::class, 'khs']);
    Route::get('students/me/schedules', [StudentPortalController::class, 'schedules']);
    Route::post('students/me/request-update', [StudentPortalController::class, 'requestUpdate']);

    // Admin & Staff Student Management
    Route::get('students', [StudentController::class, 'index'])->middleware('permission:students.view');
    Route::post('students', [StudentController::class, 'store'])->middleware('permission:students.create');
    Route::get('students/{student}', [StudentController::class, 'show'])->middleware('permission:students.view');
    Route::put('students/{student}', [StudentController::class, 'update'])->middleware('permission:students.update');
    Route::delete('students/{student}', [StudentController::class, 'destroy'])->middleware('permission:students.delete');
    Route::patch('students/{student}/status', [StudentController::class, 'changeStatus'])->middleware('permission:students.change_status');
    Route::post('students/{student}/families', [StudentController::class, 'addFamily'])->middleware('permission:students.update');
    Route::post('students/{student}/educations', [StudentController::class, 'addEducation'])->middleware('permission:students.update');
    Route::post('students/{student}/create-account', [StudentController::class, 'createAccount'])->middleware('permission:students.update');
    Route::post('students/{student}/reset-password', [StudentController::class, 'resetPassword'])->middleware('permission:students.update');
    Route::patch('students/{student}/toggle-account-status', [StudentController::class, 'toggleAccountStatus'])->middleware('permission:students.update');
});
