<?php

use Illuminate\Support\Facades\Route;
use Modules\Schedule\Controllers\RoomController;
use Modules\Schedule\Controllers\ScheduleController;

Route::middleware('auth:sanctum')->group(function () {
    // Rooms
    Route::get('rooms', [RoomController::class, 'index'])->middleware('permission:rooms.view');
    Route::post('rooms', [RoomController::class, 'store'])->middleware('permission:rooms.create');
    Route::get('rooms/{room}', [RoomController::class, 'show'])->middleware('permission:rooms.view');
    Route::put('rooms/{room}', [RoomController::class, 'update'])->middleware('permission:rooms.update');
    Route::delete('rooms/{room}', [RoomController::class, 'destroy'])->middleware('permission:rooms.delete');
    Route::patch('rooms/{room}/status', [RoomController::class, 'changeStatus'])->middleware('permission:rooms.change_status');

    // Schedules
    Route::get('schedules', [ScheduleController::class, 'index'])->middleware('permission:schedules.view');
    Route::post('schedules', [ScheduleController::class, 'store'])->middleware('permission:schedules.create');
    Route::get('schedules/{schedule}', [ScheduleController::class, 'show'])->middleware('permission:schedules.view');
    Route::put('schedules/{schedule}', [ScheduleController::class, 'update'])->middleware('permission:schedules.update');
    Route::delete('schedules/{schedule}', [ScheduleController::class, 'destroy'])->middleware('permission:schedules.delete');

    // Nested Class Schedules
    Route::get('classes/{class}/schedules', [ScheduleController::class, 'classSchedules'])->middleware('permission:schedules.view');
    Route::post('classes/{class}/schedules', [ScheduleController::class, 'storeClassSchedule'])->middleware('permission:schedules.create');

    // Exam Schedules
    Route::get('exam-schedules', [\Modules\Schedule\Controllers\ExamScheduleController::class, 'index'])->middleware('permission:schedules.view');
    Route::post('classes/{class}/exam-schedules', [\Modules\Schedule\Controllers\ExamScheduleController::class, 'updateSchedule'])->middleware('permission:schedules.create');
    Route::post('classes/{class}/exam-proctor', [\Modules\Schedule\Controllers\ExamScheduleController::class, 'updateProctor'])->middleware('permission:schedules.create');
    Route::post('exam-schedules/announce', [\Modules\Schedule\Controllers\ExamScheduleController::class, 'announce'])->middleware('permission:schedules.create');

    // Campuses & Buildings
    Route::apiResource('campuses', \Modules\Schedule\Controllers\CampusController::class);
    Route::apiResource('buildings', \Modules\Schedule\Controllers\BuildingController::class);
});
