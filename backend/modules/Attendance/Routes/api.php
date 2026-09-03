<?php

use Illuminate\Support\Facades\Route;
use Modules\Attendance\Controllers\StudentAttendanceController;
use Modules\Attendance\Controllers\TeachingSessionController;

Route::middleware(['auth:sanctum'])->prefix('attendance')->group(function () {
    // Student self-service routes
    Route::get('my-attendance', [StudentAttendanceController::class, 'myAttendance'])
        ->middleware('permission:attendance.view,enrollments.view');
    Route::post('self-checkin', [StudentAttendanceController::class, 'selfCheckIn'])
        ->middleware('permission:attendance.view,attendance.self_checkin,enrollments.view');

    // Class attendance recap
    Route::get('classes/{class}/recap', [TeachingSessionController::class, 'classRecap'])
        ->middleware('permission:attendance.view,classes.view');

    // Student attendance recap (by admin/lecturer/advisor)
    Route::get('students/{student}/recap', [StudentAttendanceController::class, 'studentRecap'])
        ->middleware('permission:attendance.view,students.view');

    // Teaching Sessions CRUD & actions
    Route::get('sessions', [TeachingSessionController::class, 'index'])
        ->middleware('permission:attendance.view,classes.view');
    Route::post('sessions', [TeachingSessionController::class, 'store'])
        ->middleware('permission:attendance.manage,attendance.record,classes.create,classes.update');
    Route::get('sessions/{teaching_session}', [TeachingSessionController::class, 'show'])
        ->middleware('permission:attendance.view,classes.view');
    Route::put('sessions/{teaching_session}', [TeachingSessionController::class, 'update'])
        ->middleware('permission:attendance.manage,attendance.record,classes.update');
    Route::delete('sessions/{teaching_session}', [TeachingSessionController::class, 'destroy'])
        ->middleware('permission:attendance.manage,classes.delete');

    Route::post('sessions/{teaching_session}/open-checkin', [TeachingSessionController::class, 'openCheckIn'])
        ->middleware('permission:attendance.manage,attendance.record,classes.update');
    Route::post('sessions/{teaching_session}/close', [TeachingSessionController::class, 'closeSession'])
        ->middleware('permission:attendance.manage,attendance.record,classes.update');

    // Session Students Attendance
    Route::get('sessions/{teaching_session}/students', [StudentAttendanceController::class, 'sessionStudents'])
        ->middleware('permission:attendance.view,classes.view');
    Route::post('sessions/{teaching_session}/record-batch', [StudentAttendanceController::class, 'recordBatch'])
        ->middleware('permission:attendance.manage,attendance.record,classes.update');
    Route::patch('sessions/{teaching_session}/students/{student}', [StudentAttendanceController::class, 'updateSingle'])
        ->middleware('permission:attendance.manage,attendance.record,classes.update');
});
