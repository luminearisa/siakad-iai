<?php

namespace Modules\MBKM\Services;

use Illuminate\Http\Request;
use Modules\Lecturer\Models\Lecturer;
use Modules\MBKM\Enums\ApplicationStatus;
use Modules\MBKM\Enums\CompletionStatus;
use Modules\MBKM\Enums\LogbookStatus;
use Modules\MBKM\Enums\ParticipantStatus;
use Modules\MBKM\Enums\ProgramStatus;
use Modules\MBKM\Enums\RecognitionStatus;
use Modules\MBKM\Models\MbkmApplication;
use Modules\MBKM\Models\MbkmAssessment;
use Modules\MBKM\Models\MbkmCompletion;
use Modules\MBKM\Models\MbkmParticipant;
use Modules\MBKM\Models\MbkmProgram;
use Modules\MBKM\Models\MbkmRecognition;
use Modules\Student\Models\Student;

/**
 * Role-aware dashboards.
 *
 * Mahasiswa / Dosen / Admin MBKM each get the view they need, built from the
 * module's own tables (no duplicated academic aggregates: GPA/credits still come
 * from the existing KHS pipeline).
 */
class MbkmDashboardService
{
    public function __construct(
        protected MbkmEligibilityService $eligibilityService,
        protected MbkmAttendanceService $attendanceService,
        protected MbkmLogbookService $logbookService,
        protected MbkmAssessmentService $assessmentService,
        protected MbkmRecognitionService $recognitionService,
    ) {}

    /**
     * Student dashboard: application status, participant progress, recognition,
     * and academic result status.
     */
    public function studentDashboard(Student $student): array
    {
        $applications = MbkmApplication::with(['program.programType', 'program.studyProgram'])
            ->where('student_id', $student->id)
            ->orderByDesc('id')
            ->get();

        $participants = MbkmParticipant::with([
            'program.programType',
            'placement.partner',
            'placement.location',
            'supervisors.lecturer',
            'completion',
        ])
            ->where('student_id', $student->id)
            ->orderByDesc('id')
            ->get();

        $active = $participants->firstWhere('status', ParticipantStatus::ONGOING)
            ?? $participants->firstWhere('status', ParticipantStatus::ASSIGNED)
            ?? $participants->first();

        $detail = null;

        if ($active) {
            $detail = [
                'participant' => $active,
                'attendance' => $this->attendanceService->summary($active),
                'logbook' => $this->logbookService->summary($active),
                'assessment' => $this->assessmentService->computeFinalScore($active),
                'recognition' => $this->recognitionService->summary($active),
            ];
        }

        return [
            'academic_snapshot' => $this->eligibilityService->academicSnapshot($student),
            'applications' => $applications,
            'participants' => $participants,
            'active_participation' => $detail,
            'counters' => [
                'applications_total' => $applications->count(),
                'applications_active' => $applications->filter(fn ($a) => $a->isActive())->count(),
                'participants_total' => $participants->count(),
                'participants_active' => $participants->filter(fn ($p) => $p->isActive())->count(),
                'participants_completed' => $participants->where('status', ParticipantStatus::COMPLETED)->count(),
                'recognized_credits' => (int) $participants->sum('recognized_credits'),
            ],
        ];
    }

    /**
     * Lecturer dashboard: supervisees, pending logbooks, pending assessments.
     */
    public function lecturerDashboard(Lecturer $lecturer): array
    {
        $participants = MbkmParticipant::with(['program', 'student.studyProgram', 'placement.partner'])
            ->whereHas('supervisors', fn ($q) => $q->where('lecturer_id', $lecturer->id))
            ->orderByDesc('id')
            ->get();

        $participantIds = $participants->pluck('id');

        $pendingLogbooks = \Modules\MBKM\Models\MbkmActivityLog::with(['participant.student', 'participant.program'])
            ->whereIn('participant_id', $participantIds)
            ->where('status', LogbookStatus::SUBMITTED)
            ->orderBy('log_date')
            ->limit(50)
            ->get();

        $pendingAssessments = MbkmParticipant::with(['program.assessmentComponents', 'assessments', 'student'])
            ->whereIn('id', $participantIds)
            ->whereIn('status', [ParticipantStatus::ASSIGNED, ParticipantStatus::ONGOING])
            ->get()
            ->map(function (MbkmParticipant $participant) {
                $result = $this->assessmentService->computeFinalScore($participant);

                return [
                    'participant_id' => $participant->id,
                    'participant_number' => $participant->participant_number,
                    'student_name' => $participant->student?->full_name,
                    'program_name' => $participant->program?->name,
                    'is_complete' => $result['is_complete'],
                    'final_score' => $result['final_score'],
                    'missing_components' => collect($result['breakdown'])
                        ->filter(fn ($row) => $row['average_score'] === null)
                        ->pluck('component_name')
                        ->values()
                        ->all(),
                ];
            })
            ->filter(fn ($row) => !$row['is_complete'])
            ->values();

        $recognitions = MbkmRecognition::with(['participant.student', 'course'])
            ->whereIn('participant_id', $participantIds)
            ->where('status', RecognitionStatus::SUBMITTED)
            ->get();

        $openIssues = \Modules\MBKM\Models\MbkmIssue::with(['participant.student'])
            ->whereIn('participant_id', $participantIds)
            ->whereIn('status', ['open', 'in_progress'])
            ->get();

        return [
            'participants' => $participants,
            'pending_logbooks' => $pendingLogbooks,
            'pending_assessments' => $pendingAssessments,
            'pending_recognitions' => $recognitions,
            'open_issues' => $openIssues,
            'counters' => [
                'supervisees' => $participants->count(),
                'active' => $participants->filter(fn ($p) => $p->isActive())->count(),
                'completed' => $participants->where('status', ParticipantStatus::COMPLETED)->count(),
                'pending_logbooks' => $pendingLogbooks->count(),
                'pending_assessments' => $pendingAssessments->count(),
                'pending_recognitions' => $recognitions->count(),
                'open_issues' => $openIssues->count(),
            ],
        ];
    }

    /**
     * Admin MBKM / academic dashboard with the standard filters.
     */
    public function adminDashboard(Request $request): array
    {
        $programQuery = MbkmProgram::query();
        $this->applyProgramFilters($programQuery, $request);

        $programs = $programQuery->with('programType')->get();
        $programIds = $programs->pluck('id');

        $participantQuery = MbkmParticipant::query()->whereIn('program_id', $programIds);
        $this->applyParticipantFilters($participantQuery, $request);

        $participants = $participantQuery->with(['program', 'student.studyProgram', 'placement.partner'])->get();
        $participantIds = $participants->pluck('id');

        $pendingSelections = MbkmApplication::whereIn('program_id', $programIds)
            ->whereIn('status', [ApplicationStatus::SUBMITTED->value, ApplicationStatus::VERIFIED->value])
            ->count();

        $pendingLogbooks = \Modules\MBKM\Models\MbkmActivityLog::whereIn('participant_id', $participantIds)
            ->where('status', LogbookStatus::SUBMITTED)
            ->count();

        $pendingRecognitions = MbkmRecognition::whereIn('participant_id', $participantIds)
            ->whereIn('status', [RecognitionStatus::SUBMITTED->value, RecognitionStatus::REVIEWED->value])
            ->count();

        $pendingCompletions = MbkmCompletion::whereIn('participant_id', $participantIds)
            ->where('status', CompletionStatus::REQUIREMENTS_UNMET->value)
            ->count();

        $pendingAssessments = $participants
            ->whereIn('status', [ParticipantStatus::ASSIGNED, ParticipantStatus::ONGOING])
            ->filter(function (MbkmParticipant $participant) {
                $participant->loadMissing(['program.assessmentComponents', 'assessments']);

                return !$this->assessmentService->computeFinalScore($participant)['is_complete'];
            })
            ->count();

        return [
            'counters' => [
                'programs_total' => $programs->count(),
                'programs_active' => $programs->filter(fn (MbkmProgram $p) => in_array(
                    $p->status instanceof ProgramStatus ? $p->status->value : (string) $p->status,
                    [ProgramStatus::PUBLISHED->value, ProgramStatus::ONGOING->value],
                    true
                ))->count(),
                'applicants' => MbkmApplication::whereIn('program_id', $programIds)->count(),
                'pending_selections' => $pendingSelections,
                'participants_active' => $participants->filter(fn ($p) => $p->isActive())->count(),
                'participants_completed' => $participants->where('status', ParticipantStatus::COMPLETED)->count(),
                'participants_problematic' => \Modules\MBKM\Models\MbkmIssue::whereIn('participant_id', $participantIds)
                    ->whereIn('status', ['open', 'in_progress'])->count(),
                'quota_total' => (int) $programs->sum('quota'),
                'quota_used' => $participants->whereIn('status', ParticipantStatus::occupyingQuota())->count(),
                'pending_logbooks' => $pendingLogbooks,
                'pending_assessments' => $pendingAssessments,
                'pending_recognitions' => $pendingRecognitions,
                'pending_completions' => $pendingCompletions,
            ],
            'participants_by_study_program' => $participants
                ->groupBy(fn (MbkmParticipant $p) => $p->student?->studyProgram?->name ?? 'Tanpa Program Studi')
                ->map->count()
                ->sortDesc()
                ->all(),
            'participants_by_partner' => $participants
                ->groupBy(fn (MbkmParticipant $p) => $p->placement?->partner?->name ?? 'Belum ditempatkan')
                ->map->count()
                ->sortDesc()
                ->all(),
            'action_items' => [
                'applications' => $pendingSelections,
                'logbooks' => $pendingLogbooks,
                'assessments' => $pendingAssessments,
                'recognitions' => $pendingRecognitions,
                'completions' => $pendingCompletions,
            ],
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<MbkmProgram>  $query
     */
    protected function applyProgramFilters($query, Request $request): void
    {
        if ($request->filled('semester_id')) {
            $query->where('semester_id', $request->query('semester_id'));
        }

        if ($request->filled('program_type_id')) {
            $query->where('program_type_id', $request->query('program_type_id'));
        }

        if ($request->filled('faculty_id')) {
            $query->where('faculty_id', $request->query('faculty_id'));
        }

        if ($request->filled('study_program_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('study_program_id', $request->query('study_program_id'))
                  ->orWhereNull('study_program_id');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<MbkmParticipant>  $query
     */
    protected function applyParticipantFilters($query, Request $request): void
    {
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('study_program_id')) {
            $query->whereHas('student', fn ($q) => $q->where('study_program_id', $request->query('study_program_id')));
        }

        if ($request->filled('admission_year')) {
            $query->whereHas('student', fn ($q) => $q->where('admission_year', $request->query('admission_year')));
        }

        if ($request->filled('partner_id')) {
            $query->whereHas('placement', fn ($q) => $q->where('partner_id', $request->query('partner_id')));
        }

        if ($request->filled('lecturer_id')) {
            $query->whereHas('supervisors', fn ($q) => $q->where('lecturer_id', $request->query('lecturer_id')));
        }
    }
}
