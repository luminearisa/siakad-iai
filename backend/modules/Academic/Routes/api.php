<?php

use Illuminate\Support\Facades\Route;
use Modules\Academic\Controllers\AcademicYearController;
use Modules\Academic\Controllers\FacultyController;
use Modules\Academic\Controllers\InstitutionController;
use Modules\Academic\Controllers\SemesterController;
use Modules\Academic\Controllers\StudyProgramController;

Route::middleware('auth:sanctum')->prefix('academic')->group(function () {
    Route::apiResource('institutions', InstitutionController::class);
    Route::apiResource('faculties', FacultyController::class);
    Route::apiResource('study-programs', StudyProgramController::class);
    Route::put('study-programs/{study_program}/settings', [StudyProgramController::class, 'updateSetting']);
    Route::put('academic-years/{academic_year}/set-active', [AcademicYearController::class, 'setActive']);
    Route::apiResource('academic-years', AcademicYearController::class);
    Route::put('semesters/{semester}/set-active', [SemesterController::class, 'setActive']);
    Route::apiResource('semesters', SemesterController::class);
});
