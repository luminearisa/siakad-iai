<?php

use Illuminate\Support\Facades\Route;
use Modules\Course\Controllers\CourseController;
use Modules\Course\Controllers\CourseGroupController;
use Modules\Course\Controllers\CourseQuestionnaireController;
use Modules\Course\Controllers\CourseTypeController;

Route::middleware('auth:sanctum')->group(function () {
    // Course Types (Jenis Mata Kuliah)
    Route::apiResource('course-types', CourseTypeController::class);

    // Course Groups (Kelompok Mata Kuliah)
    Route::apiResource('course-groups', CourseGroupController::class);

    // Courses Master
    Route::get('courses', [CourseController::class, 'index'])->middleware('permission:courses.view');
    Route::post('courses', [CourseController::class, 'store'])->middleware('permission:courses.create');
    Route::get('courses/{course}', [CourseController::class, 'show'])->middleware('permission:courses.view');
    Route::put('courses/{course}', [CourseController::class, 'update'])->middleware('permission:courses.update');
    Route::delete('courses/{course}', [CourseController::class, 'destroy'])->middleware('permission:courses.delete');
    Route::get('courses/{course}/prerequisites', [CourseController::class, 'prerequisites'])->middleware('permission:courses.view');
    Route::post('courses/{course}/prerequisites', [CourseController::class, 'setPrerequisites'])->middleware('permission:courses.update');

    // Course Questionnaire (Kelola Kuisioner EDOM)
    Route::get('courses/{course}/questionnaires', [CourseQuestionnaireController::class, 'index'])->middleware('permission:courses.view');
    Route::post('courses/{course}/questionnaires/reset-template', [CourseQuestionnaireController::class, 'resetTemplate'])->middleware('permission:courses.update');
    Route::post('courses/{course}/questionnaire-topics', [CourseQuestionnaireController::class, 'storeTopic'])->middleware('permission:courses.update');
    Route::put('course-questionnaire-topics/{topic}', [CourseQuestionnaireController::class, 'updateTopic'])->middleware('permission:courses.update');
    Route::delete('course-questionnaire-topics/{topic}', [CourseQuestionnaireController::class, 'destroyTopic'])->middleware('permission:courses.update');
    
    Route::post('course-questionnaire-topics/{topic}/questions', [CourseQuestionnaireController::class, 'storeQuestion'])->middleware('permission:courses.update');
    Route::put('course-questionnaire-questions/{question}', [CourseQuestionnaireController::class, 'updateQuestion'])->middleware('permission:courses.update');
    Route::delete('course-questionnaire-questions/{question}', [CourseQuestionnaireController::class, 'destroyQuestion'])->middleware('permission:courses.update');
});
