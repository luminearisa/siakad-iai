<?php

use Illuminate\Support\Facades\Route;
use Modules\Assessment\Controllers\AssessmentComponentController;
use Modules\Assessment\Controllers\AssessmentSchemeController;
use Modules\Assessment\Controllers\StudentGradeController;

Route::middleware('auth:sanctum')->group(function () {
    // 1. Assessment Schemes
    Route::get('classes/{class}/assessment-scheme', [AssessmentSchemeController::class, 'classScheme'])
        ->middleware('permission:assessment.view,grade.view');
    Route::post('classes/{class}/assessment-scheme', [AssessmentSchemeController::class, 'storeForClass'])
        ->middleware('permission:assessment.create,assessment.scheme.manage');

    Route::get('assessment-schemes/{scheme}', [AssessmentSchemeController::class, 'show'])
        ->middleware('permission:assessment.view,grade.view');
    Route::put('assessment-schemes/{scheme}', [AssessmentSchemeController::class, 'update'])
        ->middleware('permission:assessment.update,assessment.scheme.manage');
    Route::delete('assessment-schemes/{scheme}', [AssessmentSchemeController::class, 'destroy'])
        ->middleware('permission:assessment.delete,assessment.scheme.manage');

    Route::post('assessment-schemes/{scheme}/activate', [AssessmentSchemeController::class, 'activate'])
        ->middleware('permission:assessment.update,assessment.scheme.manage');
    Route::post('assessment-schemes/{scheme}/archive', [AssessmentSchemeController::class, 'archive'])
        ->middleware('permission:assessment.update,assessment.scheme.manage');

    // 2. Assessment Components
    Route::get('classes/{class}/components', [AssessmentComponentController::class, 'classComponents'])
        ->middleware('permission:assessment.view,grade.view');
    Route::get('assessment-schemes/{scheme}/components', [AssessmentSchemeController::class, 'components'])
        ->middleware('permission:assessment.view,grade.view');
    Route::post('assessment-schemes/{scheme}/components', [AssessmentSchemeController::class, 'addComponent'])
        ->middleware('permission:assessment.update,assessment.scheme.manage');

    Route::post('assessment-components', [AssessmentComponentController::class, 'store'])
        ->middleware('permission:assessment.create,assessment.scheme.manage');
    Route::get('assessment-components/{component}', [AssessmentComponentController::class, 'show'])
        ->middleware('permission:assessment.view,grade.view');
    Route::put('assessment-components/{component}', [AssessmentComponentController::class, 'update'])
        ->middleware('permission:assessment.update,assessment.scheme.manage');
    Route::delete('assessment-components/{component}', [AssessmentComponentController::class, 'destroy'])
        ->middleware('permission:assessment.delete,assessment.scheme.manage');

    // 3. Student Grades
    Route::get('classes/{class}/grades', [StudentGradeController::class, 'classGrades'])
        ->middleware('permission:grade.view,assessment.view');
    Route::get('classes/{class}/grades/{student}', [StudentGradeController::class, 'studentClassGrades'])
        ->middleware('permission:grade.view,assessment.view');

    Route::post('classes/{class}/grades', [StudentGradeController::class, 'storeBatch'])
        ->middleware('permission:grade.input,grade.update');
    Route::put('student-grades/{grade}', [StudentGradeController::class, 'updateSingle'])
        ->middleware('permission:grade.input,grade.update');

    // 4. Grade Workflow Actions
    Route::post('classes/{class}/grades/submit', [StudentGradeController::class, 'submitClassGrades'])
        ->middleware('permission:grade.submit,grade.input');
    Route::post('classes/{class}/grades/finalize', [StudentGradeController::class, 'finalizeClassGrades'])
        ->middleware('permission:grade.finalize');

    Route::post('student-grades/{grade}/request-revision', [StudentGradeController::class, 'requestRevision'])
        ->middleware('permission:grade.finalize,assessment.scheme.manage');
    Route::post('student-grades/{grade}/revise', [StudentGradeController::class, 'reviseFinalGrade'])
        ->middleware('permission:grade.revise,grade.finalize');

    // 5. Revision History
    Route::get('student-grades/{grade}/revisions', [StudentGradeController::class, 'revisionsHistory'])
        ->middleware('permission:grade.history,grade.view');
});
