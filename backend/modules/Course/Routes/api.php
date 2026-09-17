<?php

use Illuminate\Support\Facades\Route;
use Modules\Course\Controllers\CourseController;
use Modules\Course\Controllers\CourseGroupController;
use Modules\Course\Controllers\CourseQuestionnaireController;
use Modules\Course\Controllers\CourseSurveyAssignmentController;
use Modules\Course\Controllers\CourseSurveyQuestionController;
use Modules\Course\Controllers\CourseSurveyTemplateController;
use Modules\Course\Controllers\CourseSurveyTopicController;
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

    // Course Questionnaire (Legacy - kept intact)
    Route::get('courses/{course}/questionnaires', [CourseQuestionnaireController::class, 'index'])->middleware('permission:courses.view');
    Route::post('courses/{course}/questionnaires/reset-template', [CourseQuestionnaireController::class, 'resetTemplate'])->middleware('permission:courses.update');
    Route::post('courses/{course}/questionnaire-topics', [CourseQuestionnaireController::class, 'storeTopic'])->middleware('permission:courses.update');
    Route::put('course-questionnaire-topics/{topic}', [CourseQuestionnaireController::class, 'updateTopic'])->middleware('permission:courses.update');
    Route::delete('course-questionnaire-topics/{topic}', [CourseQuestionnaireController::class, 'destroyTopic'])->middleware('permission:courses.update');
    Route::post('course-questionnaire-topics/{topic}/questions', [CourseQuestionnaireController::class, 'storeQuestion'])->middleware('permission:courses.update');
    Route::put('course-questionnaire-questions/{question}', [CourseQuestionnaireController::class, 'updateQuestion'])->middleware('permission:courses.update');
    Route::delete('course-questionnaire-questions/{question}', [CourseQuestionnaireController::class, 'destroyQuestion'])->middleware('permission:courses.update');

    // Course Survey Templates (Arsitektur Baru Terpusat)
    Route::apiResource('course-survey-templates', CourseSurveyTemplateController::class)
        ->parameters(['course-survey-templates' => 'template'])
        ->middleware('permission:courses.view');
    Route::post('course-survey-templates/{template}/assign-courses', [CourseSurveyTemplateController::class, 'assignCourses'])->middleware('permission:courses.update');
    Route::delete('course-survey-templates/{template}/courses/{course}', [CourseSurveyTemplateController::class, 'unassignCourse'])->middleware('permission:courses.update');

    // Topics (Judul) dalam Template Survey
    Route::post('course-survey-templates/{template}/topics', [CourseSurveyTopicController::class, 'store'])->middleware('permission:courses.update');
    Route::put('course-survey-topics/{topic}', [CourseSurveyTopicController::class, 'update'])->middleware('permission:courses.update');
    Route::delete('course-survey-topics/{topic}', [CourseSurveyTopicController::class, 'destroy'])->middleware('permission:courses.update');

    // Questions (Pertanyaan: yes_no atau scale) dalam Judul
    Route::post('course-survey-topics/{topic}/questions', [CourseSurveyQuestionController::class, 'store'])->middleware('permission:courses.update');
    Route::put('course-survey-questions/{question}', [CourseSurveyQuestionController::class, 'update'])->middleware('permission:courses.update');
    Route::delete('course-survey-questions/{question}', [CourseSurveyQuestionController::class, 'destroy'])->middleware('permission:courses.update');

    // Course Survey Assignment (Dari sisi Mata Kuliah)
    Route::get('courses/{course}/survey-assignment', [CourseSurveyAssignmentController::class, 'getCourseSurvey'])->middleware('permission:courses.view');
    Route::post('courses/{course}/survey-assignment', [CourseSurveyAssignmentController::class, 'assign'])->middleware('permission:courses.update');
    Route::delete('courses/{course}/survey-assignment/{template}', [CourseSurveyAssignmentController::class, 'unassign'])->middleware('permission:courses.update');
});
