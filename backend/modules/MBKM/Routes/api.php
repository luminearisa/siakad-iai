<?php

use Illuminate\Support\Facades\Route;
use Modules\MBKM\Controllers\MbkmActivityPlanController;
use Modules\MBKM\Controllers\MbkmApplicationController;
use Modules\MBKM\Controllers\MbkmAssessmentController;
use Modules\MBKM\Controllers\MbkmAttendanceController;
use Modules\MBKM\Controllers\MbkmCompletionController;
use Modules\MBKM\Controllers\MbkmCooperationController;
use Modules\MBKM\Controllers\MbkmDashboardController;
use Modules\MBKM\Controllers\MbkmDocumentController;
use Modules\MBKM\Controllers\MbkmIssueController;
use Modules\MBKM\Controllers\MbkmLearningAgreementController;
use Modules\MBKM\Controllers\MbkmLogbookController;
use Modules\MBKM\Controllers\MbkmNotificationController;
use Modules\MBKM\Controllers\MbkmParticipantController;
use Modules\MBKM\Controllers\MbkmPartnerController;
use Modules\MBKM\Controllers\MbkmProgramController;
use Modules\MBKM\Controllers\MbkmProgramTypeController;
use Modules\MBKM\Controllers\MbkmRecognitionController;
use Modules\MBKM\Controllers\MbkmReportController;

/*
|--------------------------------------------------------------------------
| MBKM Routes (prefix: api/v1/mbkm)
|--------------------------------------------------------------------------
|
| All routes require Sanctum authentication. Staff endpoints additionally require
| the relevant mbkm.* permission; student/lecturer endpoints rely on server-side
| ownership checks inside the controllers (never on client-supplied ids).
|
*/
Route::middleware('auth:sanctum')->prefix('mbkm')->group(function () {

    // -----------------------------------------------------------------
    // Dashboards (role-aware)
    // -----------------------------------------------------------------
    Route::get('dashboard', [MbkmDashboardController::class, 'index']);
    Route::get('dashboard/admin', [MbkmDashboardController::class, 'admin'])
        ->middleware('permission:mbkm.manage|mbkm.participants.view');
    Route::get('dashboard/lecturer', [MbkmDashboardController::class, 'lecturer'])
        ->middleware('permission:mbkm.logbook.review|mbkm.participants.view');
    Route::get('dashboard/student', [MbkmDashboardController::class, 'student']);

    // -----------------------------------------------------------------
    // Master: program types
    // -----------------------------------------------------------------
    Route::get('program-types', [MbkmProgramTypeController::class, 'index'])
        ->middleware('permission:mbkm.programs.view|mbkm.programs.manage');
    Route::post('program-types', [MbkmProgramTypeController::class, 'store'])
        ->middleware('permission:mbkm.programs.manage');
    Route::get('program-types/{programType}', [MbkmProgramTypeController::class, 'show'])
        ->middleware('permission:mbkm.programs.view|mbkm.programs.manage');
    Route::put('program-types/{programType}', [MbkmProgramTypeController::class, 'update'])
        ->middleware('permission:mbkm.programs.manage');
    Route::delete('program-types/{programType}', [MbkmProgramTypeController::class, 'destroy'])
        ->middleware('permission:mbkm.programs.manage');

    // -----------------------------------------------------------------
    // Master: partners
    // -----------------------------------------------------------------
    Route::get('partners', [MbkmPartnerController::class, 'index'])
        ->middleware('permission:mbkm.partners.view|mbkm.partners.manage');
    Route::post('partners', [MbkmPartnerController::class, 'store'])
        ->middleware('permission:mbkm.partners.manage');
    Route::get('partners/{partner}', [MbkmPartnerController::class, 'show'])
        ->middleware('permission:mbkm.partners.view|mbkm.partners.manage');
    Route::put('partners/{partner}', [MbkmPartnerController::class, 'update'])
        ->middleware('permission:mbkm.partners.manage');
    Route::delete('partners/{partner}', [MbkmPartnerController::class, 'destroy'])
        ->middleware('permission:mbkm.partners.manage');

    // -----------------------------------------------------------------
    // Master: cooperation deeds
    // -----------------------------------------------------------------
    Route::get('cooperations', [MbkmCooperationController::class, 'index'])
        ->middleware('permission:mbkm.partners.view|mbkm.partners.manage');
    Route::post('cooperations', [MbkmCooperationController::class, 'store'])
        ->middleware('permission:mbkm.partners.manage');
    Route::get('cooperations/{cooperation}', [MbkmCooperationController::class, 'show'])
        ->middleware('permission:mbkm.partners.view|mbkm.partners.manage');
    Route::put('cooperations/{cooperation}', [MbkmCooperationController::class, 'update'])
        ->middleware('permission:mbkm.partners.manage');
    Route::delete('cooperations/{cooperation}', [MbkmCooperationController::class, 'destroy'])
        ->middleware('permission:mbkm.partners.manage');

    // -----------------------------------------------------------------
    // Documents (single store for the whole module)
    // -----------------------------------------------------------------
    Route::get('documents', [MbkmDocumentController::class, 'index']);
    Route::post('documents', [MbkmDocumentController::class, 'store']);
    Route::put('documents/{document}/verify', [MbkmDocumentController::class, 'verify'])
        ->middleware('permission:mbkm.manage|mbkm.applications.verify|mbkm.participants.manage');
    Route::delete('documents/{document}', [MbkmDocumentController::class, 'destroy'])
        ->middleware('permission:mbkm.manage|mbkm.participants.manage');

    // -----------------------------------------------------------------
    // Programs
    // -----------------------------------------------------------------
    Route::get('catalog', [MbkmProgramController::class, 'catalog']);
    Route::get('programs', [MbkmProgramController::class, 'index'])
        ->middleware('permission:mbkm.programs.view|mbkm.programs.manage');
    Route::post('programs', [MbkmProgramController::class, 'store'])
        ->middleware('permission:mbkm.programs.manage');
    Route::get('programs/{program}', [MbkmProgramController::class, 'show'])
        ->middleware('permission:mbkm.programs.view|mbkm.programs.manage');
    Route::put('programs/{program}', [MbkmProgramController::class, 'update'])
        ->middleware('permission:mbkm.programs.manage');
    Route::delete('programs/{program}', [MbkmProgramController::class, 'destroy'])
        ->middleware('permission:mbkm.programs.manage');
    Route::post('programs/{program}/transition', [MbkmProgramController::class, 'transition'])
        ->middleware('permission:mbkm.programs.manage');
    Route::get('programs/{program}/eligibility', [MbkmProgramController::class, 'eligibility']);
    Route::post('programs/{program}/locations', [MbkmProgramController::class, 'storeLocation'])
        ->middleware('permission:mbkm.programs.manage');
    Route::delete('programs/{program}/locations/{location}', [MbkmProgramController::class, 'destroyLocation'])
        ->middleware('permission:mbkm.programs.manage');
    Route::post('programs/{program}/requirements', [MbkmProgramController::class, 'storeRequirement'])
        ->middleware('permission:mbkm.programs.manage');
    Route::delete('programs/{program}/requirements/{requirement}', [MbkmProgramController::class, 'destroyRequirement'])
        ->middleware('permission:mbkm.programs.manage');
    Route::put('programs/{program}/selection-criteria', [MbkmProgramController::class, 'syncCriteria'])
        ->middleware('permission:mbkm.programs.manage|mbkm.applications.decide');
    Route::put('programs/{program}/assessment-components', [MbkmProgramController::class, 'syncAssessmentComponents'])
        ->middleware('permission:mbkm.programs.manage|mbkm.assessment.manage');

    // -----------------------------------------------------------------
    // Applications
    // -----------------------------------------------------------------
    Route::get('applications', [MbkmApplicationController::class, 'index'])
        ->middleware('permission:mbkm.applications.view|mbkm.applications.verify|mbkm.applications.decide');
    Route::post('applications', [MbkmApplicationController::class, 'store']);
    Route::get('applications/{application}', [MbkmApplicationController::class, 'show']);
    Route::put('applications/{application}', [MbkmApplicationController::class, 'update']);
    Route::post('applications/{application}/submit', [MbkmApplicationController::class, 'submit']);
    Route::post('applications/{application}/verify', [MbkmApplicationController::class, 'verify'])
        ->middleware('permission:mbkm.applications.verify|mbkm.manage');
    Route::post('applications/{application}/decide', [MbkmApplicationController::class, 'decide'])
        ->middleware('permission:mbkm.applications.decide|mbkm.manage');
    Route::post('applications/{application}/score', [MbkmApplicationController::class, 'score'])
        ->middleware('permission:mbkm.applications.decide|mbkm.manage');
    Route::post('applications/{application}/withdraw', [MbkmApplicationController::class, 'withdraw']);
    Route::get('applications/{application}/history', [MbkmApplicationController::class, 'history']);

    // -----------------------------------------------------------------
    // Participants
    // -----------------------------------------------------------------
    Route::get('participants', [MbkmParticipantController::class, 'index']);
    Route::post('participants/assign', [MbkmParticipantController::class, 'assign'])
        ->middleware('permission:mbkm.participants.manage|mbkm.manage');
    Route::get('participants/{participant}', [MbkmParticipantController::class, 'show']);
    Route::put('participants/{participant}', [MbkmParticipantController::class, 'update'])
        ->middleware('permission:mbkm.participants.manage|mbkm.manage');
    Route::post('participants/{participant}/start', [MbkmParticipantController::class, 'start'])
        ->middleware('permission:mbkm.participants.manage|mbkm.manage');
    Route::post('participants/{participant}/finalize-score', [MbkmParticipantController::class, 'finalizeScore'])
        ->middleware('permission:mbkm.participants.manage|mbkm.assessment.manage|mbkm.manage');
    Route::get('participants/{participant}/history', [MbkmParticipantController::class, 'history']);
    Route::put('participants/{participant}/placement', [MbkmParticipantController::class, 'upsertPlacement'])
        ->middleware('permission:mbkm.participants.manage|mbkm.manage');
    Route::post('participants/{participant}/supervisors', [MbkmParticipantController::class, 'storeSupervisor'])
        ->middleware('permission:mbkm.participants.manage|mbkm.manage');
    Route::delete('participants/{participant}/supervisors/{supervisor}', [MbkmParticipantController::class, 'destroySupervisor'])
        ->middleware('permission:mbkm.participants.manage|mbkm.manage');

    // -----------------------------------------------------------------
    // Learning agreement
    // -----------------------------------------------------------------
    Route::get('participants/{participant}/learning-agreement', [MbkmLearningAgreementController::class, 'show']);
    Route::put('participants/{participant}/learning-agreement', [MbkmLearningAgreementController::class, 'upsert']);
    Route::post('participants/{participant}/learning-agreement/transition', [MbkmLearningAgreementController::class, 'transition']);
    Route::post('participants/{participant}/learning-agreement/reopen', [MbkmLearningAgreementController::class, 'reopen'])
        ->middleware('permission:mbkm.participants.manage|mbkm.manage');

    // -----------------------------------------------------------------
    // Activity plans
    // -----------------------------------------------------------------
    Route::get('participants/{participant}/activity-plans', [MbkmActivityPlanController::class, 'index']);
    Route::post('participants/{participant}/activity-plans', [MbkmActivityPlanController::class, 'store']);
    Route::put('activity-plans/{plan}', [MbkmActivityPlanController::class, 'update']);
    Route::delete('activity-plans/{plan}', [MbkmActivityPlanController::class, 'destroy']);

    // -----------------------------------------------------------------
    // Logbook
    // -----------------------------------------------------------------
    Route::get('logbooks', [MbkmLogbookController::class, 'all'])
        ->middleware('permission:mbkm.logbook.view|mbkm.logbook.review|mbkm.manage');
    Route::get('participants/{participant}/logbooks', [MbkmLogbookController::class, 'index']);
    Route::post('participants/{participant}/logbooks', [MbkmLogbookController::class, 'store']);
    Route::put('logbooks/{logbook}', [MbkmLogbookController::class, 'update']);
    Route::post('logbooks/{logbook}/submit', [MbkmLogbookController::class, 'submit']);
    Route::post('logbooks/{logbook}/review', [MbkmLogbookController::class, 'review']);
    Route::delete('logbooks/{logbook}', [MbkmLogbookController::class, 'destroy']);

    // -----------------------------------------------------------------
    // Attendance
    // -----------------------------------------------------------------
    Route::get('participants/{participant}/attendances', [MbkmAttendanceController::class, 'index']);
    Route::get('participants/{participant}/attendances/summary', [MbkmAttendanceController::class, 'summary']);
    Route::post('participants/{participant}/attendances', [MbkmAttendanceController::class, 'store']);
    Route::post('participants/{participant}/attendances/batch', [MbkmAttendanceController::class, 'batch']);
    Route::delete('attendances/{attendance}', [MbkmAttendanceController::class, 'destroy']);

    // -----------------------------------------------------------------
    // Issues
    // -----------------------------------------------------------------
    Route::get('issues', [MbkmIssueController::class, 'index'])
        ->middleware('permission:mbkm.participants.view|mbkm.logbook.review|mbkm.manage');
    Route::post('participants/{participant}/issues', [MbkmIssueController::class, 'store']);
    Route::put('issues/{issue}', [MbkmIssueController::class, 'update']);
    Route::delete('issues/{issue}', [MbkmIssueController::class, 'destroy']);

    // -----------------------------------------------------------------
    // Assessment
    // -----------------------------------------------------------------
    Route::get('assessment-components', [MbkmAssessmentController::class, 'components'])
        ->middleware('permission:mbkm.assessment.view|mbkm.assessment.manage|mbkm.manage');
    Route::get('participants/{participant}/assessments', [MbkmAssessmentController::class, 'index']);
    Route::post('participants/{participant}/assessments', [MbkmAssessmentController::class, 'store']);
    Route::post('participants/{participant}/assessments/partner', [MbkmAssessmentController::class, 'storePartnerAssessment'])
        ->middleware('permission:mbkm.assessment.record|mbkm.assessment.manage|mbkm.manage');
    Route::delete('assessments/{assessment}', [MbkmAssessmentController::class, 'destroy']);

    // -----------------------------------------------------------------
    // Recognition
    // -----------------------------------------------------------------
    Route::get('recognitions', [MbkmRecognitionController::class, 'index']);
    Route::get('participants/{participant}/recognitions', [MbkmRecognitionController::class, 'indexForParticipant']);
    Route::post('participants/{participant}/recognitions', [MbkmRecognitionController::class, 'store']);
    Route::get('recognitions/{recognition}', [MbkmRecognitionController::class, 'show']);
    Route::put('recognitions/{recognition}', [MbkmRecognitionController::class, 'update']);
    Route::post('recognitions/{recognition}/transition', [MbkmRecognitionController::class, 'transition'])
        ->middleware('permission:mbkm.recognition.approve|mbkm.manage|mbkm.recognition.manage');
    Route::post('recognitions/{recognition}/correct', [MbkmRecognitionController::class, 'correct'])
        ->middleware('permission:mbkm.recognition.approve|mbkm.manage');
    Route::delete('recognitions/{recognition}', [MbkmRecognitionController::class, 'destroy']);

    // -----------------------------------------------------------------
    // Completion, withdrawal, extension
    // -----------------------------------------------------------------
    Route::get('completions', [MbkmCompletionController::class, 'index'])
        ->middleware('permission:mbkm.completion.view|mbkm.manage|mbkm.participants.view');
    Route::get('participants/{participant}/completion', [MbkmCompletionController::class, 'evaluate']);
    Route::post('participants/{participant}/completion/verify', [MbkmCompletionController::class, 'verify'])
        ->middleware('permission:mbkm.completion.verify|mbkm.manage');
    Route::post('participants/{participant}/completion/certificate', [MbkmCompletionController::class, 'attachCertificate'])
        ->middleware('permission:mbkm.completion.verify|mbkm.manage');

    Route::get('withdrawals', [MbkmCompletionController::class, 'indexWithdrawals']);
    Route::post('participants/{participant}/withdrawals', [MbkmCompletionController::class, 'storeWithdrawal']);
    Route::post('withdrawals/{withdrawal}/decide', [MbkmCompletionController::class, 'decideWithdrawal'])
        ->middleware('permission:mbkm.participants.manage|mbkm.manage');

    Route::get('extensions', [MbkmCompletionController::class, 'indexExtensions']);
    Route::post('participants/{participant}/extensions', [MbkmCompletionController::class, 'storeExtension']);
    Route::post('extensions/{extension}/decide', [MbkmCompletionController::class, 'decideExtension'])
        ->middleware('permission:mbkm.participants.manage|mbkm.manage');

    // -----------------------------------------------------------------
    // Reports
    // -----------------------------------------------------------------
    Route::get('reports', [MbkmReportController::class, 'types'])
        ->middleware('permission:mbkm.reports.view|mbkm.manage');
    Route::get('reports/{type}/export', [MbkmReportController::class, 'export'])
        ->middleware('permission:mbkm.reports.view|mbkm.manage');
    Route::get('reports/{type}', [MbkmReportController::class, 'show'])
        ->middleware('permission:mbkm.reports.view|mbkm.manage');

    // -----------------------------------------------------------------
    // Notifications + workflow history
    // -----------------------------------------------------------------
    Route::get('notifications', [MbkmNotificationController::class, 'index']);
    Route::post('notifications/read-all', [MbkmNotificationController::class, 'markAllRead']);
    Route::post('notifications/{id}/read', [MbkmNotificationController::class, 'markRead']);
    // Institution-wide workflow audit feed. Deliberately NOT granted via
    // `mbkm.participants.view`: the `dosen` role holds that permission, and the
    // feed is not scoped per participant, so a plain lecturer would read every
    // student's status transitions. Per-participant history is available (and
    // scoped) at `participants/{participant}/history`.
    Route::get('history', [MbkmNotificationController::class, 'history'])
        ->middleware('permission:mbkm.manage');
});
