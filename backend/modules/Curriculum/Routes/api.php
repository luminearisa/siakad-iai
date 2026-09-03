<?php

use Illuminate\Support\Facades\Route;
use Modules\Curriculum\Controllers\CurriculumController;
use Modules\Curriculum\Controllers\CurriculumSemesterController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('curricula', [CurriculumController::class, 'index'])->middleware('permission:curricula.view');
    Route::post('curricula', [CurriculumController::class, 'store'])->middleware('permission:curricula.create');
    Route::get('curricula/{curriculum}', [CurriculumController::class, 'show'])->middleware('permission:curricula.view');
    Route::put('curricula/{curriculum}', [CurriculumController::class, 'update'])->middleware('permission:curricula.update');
    Route::delete('curricula/{curriculum}', [CurriculumController::class, 'destroy'])->middleware('permission:curricula.delete');

    Route::patch('curricula/{curriculum}/activate', [CurriculumController::class, 'activate'])->middleware('permission:curricula.activate');
    Route::patch('curricula/{curriculum}/archive', [CurriculumController::class, 'archive'])->middleware('permission:curricula.archive');

    Route::get('curricula/{curriculum}/semesters', [CurriculumController::class, 'semesters'])->middleware('permission:curricula.view');
    Route::post('curricula/{curriculum}/semesters', [CurriculumController::class, 'storeSemester'])->middleware('permission:curricula.update');

    Route::get('curriculum-semesters/{semester}/subjects', [CurriculumSemesterController::class, 'subjects'])->middleware('permission:curricula.view');
    Route::post('curriculum-semesters/{semester}/subjects', [CurriculumSemesterController::class, 'storeSubject'])->middleware('permission:curricula.manage_subjects');
    Route::delete('curriculum-semesters/{semester}/subjects/{subject}', [CurriculumSemesterController::class, 'destroySubject'])->middleware('permission:curricula.manage_subjects');

    // Master Kurikulum
    Route::apiResource('curriculum-years', \Modules\Curriculum\Controllers\CurriculumYearController::class);
    Route::apiResource('credit-limits', \Modules\Curriculum\Controllers\CreditLimitController::class);
    Route::apiResource('grade-scales', \Modules\Curriculum\Controllers\GradeScaleController::class);

    // OBE Learning Outcomes (Capaian Lulusan)
    Route::apiResource('graduate-profiles', \Modules\Curriculum\Controllers\GraduateProfileController::class);
    Route::apiResource('learning-outcomes', \Modules\Curriculum\Controllers\LearningOutcomeController::class);
    Route::apiResource('course-learning-outcomes', \Modules\Curriculum\Controllers\CourseLearningOutcomeController::class);
    Route::apiResource('sub-cpmks', \Modules\Curriculum\Controllers\SubCpmkController::class);
    Route::post('outcomes/generate-ai', [\Modules\Curriculum\Controllers\OutcomeAiGeneratorController::class, 'generate']);
});
