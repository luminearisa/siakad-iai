<?php

namespace Modules\MBKM\Services;

use Illuminate\Http\Request;
use Modules\MBKM\Enums\ParticipantStatus;
use Modules\MBKM\Enums\RecognitionStatus;
use Modules\MBKM\Models\MbkmApplication;
use Modules\MBKM\Models\MbkmParticipant;
use Modules\MBKM\Models\MbkmProgram;
use Modules\MBKM\Models\MbkmRecognition;

/**
 * Reporting layer for the MBKM module.
 *
 * All reports are read-only projections over the module's own tables; the
 * academic-facing numbers (credits, GPA) keep coming from the existing pipeline.
 */
class MbkmReportService
{
    public function __construct(
        protected MbkmAttendanceService $attendanceService,
        protected MbkmLogbookService $logbookService,
        protected MbkmAssessmentService $assessmentService,
        protected MbkmRecognitionService $recognitionService,
        protected MbkmDashboardService $dashboardService,
    ) {}

    /**
     * Supported report keys.
     *
     * @return array<int, string>
     */
    public static function types(): array
    {
        return [
            'programs',
            'applicants',
            'participants',
            'participants_by_study_program',
            'participants_by_partner',
            'participants_by_period',
            'participant_progress',
            'attendance',
            'logbook',
            'assessment',
            'recognition',
            'recognized_credits',
            'completion',
            'summary',
        ];
    }

    /**
     * Build a report by key.
     *
     * @return array{type: string, generated_at: string, rows: array<int, mixed>, totals?: array<string, mixed>}
     */
    public function build(string $type, Request $request): array
    {
        $rows = match ($type) {
            'programs' => $this->programs($request),
            'applicants' => $this->applicants($request),
            'participants' => $this->participants($request),
            'participants_by_study_program' => $this->participantsByStudyProgram($request),
            'participants_by_partner' => $this->participantsByPartner($request),
            'participants_by_period' => $this->participantsByPeriod($request),
            'participant_progress' => $this->participantProgress($request),
            'attendance' => $this->attendance($request),
            'logbook' => $this->logbook($request),
            'assessment' => $this->assessment($request),
            'recognition' => $this->recognition($request),
            'recognized_credits' => $this->recognizedCredits($request),
            'completion' => $this->completion($request),
            'summary' => [],
            default => [],
        };

        $payload = [
            'type' => $type,
            'generated_at' => now()->toIso8601String(),
            'rows' => $rows,
        ];

        if ($type === 'summary') {
            $payload['summary'] = $this->dashboardService->adminDashboard($request);
        }

        return $payload;
    }

    /**
     * Normalise a report into a flat table (headers + rows) suitable for CSV export.
     *
     * Nested values (arrays / objects) are JSON-encoded so a single cell never
     * silently drops data. The `summary` report has no tabular rows, so its
     * counters are flattened into metric/value pairs instead.
     *
     * @return array{headers: array<int, string>, rows: array<int, array<int, mixed>>}
     */
    public function tabulate(string $type, Request $request): array
    {
        $payload = $this->build($type, $request);

        if ($type === 'summary') {
            $rows = [];

            foreach (($payload['summary'] ?? []) as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $subKey => $subValue) {
                        $rows[] = [$key . '.' . $subKey, $this->flattenCell($subValue)];
                    }

                    continue;
                }

                $rows[] = [$key, $this->flattenCell($value)];
            }

            return ['headers' => ['metric', 'value'], 'rows' => $rows];
        }

        $data = $payload['rows'] ?? [];
        $headers = [];

        foreach ($data as $row) {
            foreach (array_keys($row) as $key) {
                if (!in_array($key, $headers, true)) {
                    $headers[] = $key;
                }
            }
        }

        $rows = [];

        foreach ($data as $row) {
            $line = [];

            foreach ($headers as $key) {
                $line[] = $this->flattenCell($row[$key] ?? null);
            }

            $rows[] = $line;
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * Reduce a cell value to something fputcsv can write without data loss.
     */
    protected function flattenCell(mixed $value): string|int|float
    {
        if (is_array($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return '';
        }

        return $value;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<MbkmParticipant>
     */
    protected function participantQuery(Request $request)
    {
        $query = MbkmParticipant::query()->with([
            'program.programType',
            'student.studyProgram',
            'placement.partner',
            'placement.location',
            'supervisors.lecturer',
        ]);

        if ($request->filled('program_id')) {
            $query->where('program_id', $request->query('program_id'));
        }

        if ($request->filled('semester_id')) {
            $query->whereHas('program', fn ($q) => $q->where('semester_id', $request->query('semester_id')));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('study_program_id')) {
            $query->whereHas('student', fn ($q) => $q->where('study_program_id', $request->query('study_program_id')));
        }

        if ($request->filled('faculty_id')) {
            $query->whereHas('student.studyProgram', fn ($q) => $q->where('faculty_id', $request->query('faculty_id')));
        }

        if ($request->filled('partner_id')) {
            $query->whereHas('placement', fn ($q) => $q->where('partner_id', $request->query('partner_id')));
        }

        if ($request->filled('lecturer_id')) {
            $query->whereHas('supervisors', fn ($q) => $q->where('lecturer_id', $request->query('lecturer_id')));
        }

        if ($request->filled('admission_year')) {
            $query->whereHas('student', fn ($q) => $q->where('admission_year', $request->query('admission_year')));
        }

        return $query;
    }

    protected function programs(Request $request): array
    {
        $query = MbkmProgram::with(['programType', 'semester', 'studyProgram']);

        if ($request->filled('semester_id')) {
            $query->where('semester_id', $request->query('semester_id'));
        }

        if ($request->filled('program_type_id')) {
            $query->where('program_type_id', $request->query('program_type_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return $query->orderByDesc('id')->get()->map(fn (MbkmProgram $program) => [
            'id' => $program->id,
            'code' => $program->code,
            'name' => $program->name,
            'type' => $program->programType?->name,
            'semester' => $program->semester?->name,
            'study_program' => $program->studyProgram?->name,
            'status' => $program->status instanceof \Modules\MBKM\Enums\ProgramStatus
                ? $program->status->value
                : (string) $program->status,
            'quota' => $program->quota,
            'quota_used' => $program->usedQuota(),
            'applicants' => $program->applications()->count(),
            'participants' => $program->participants()->count(),
        ])->all();
    }

    protected function applicants(Request $request): array
    {
        $query = MbkmApplication::with(['program', 'student.studyProgram']);

        if ($request->filled('program_id')) {
            $query->where('program_id', $request->query('program_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('study_program_id')) {
            $query->whereHas('student', fn ($q) => $q->where('study_program_id', $request->query('study_program_id')));
        }

        return $query->orderByDesc('id')->get()->map(fn (MbkmApplication $application) => [
            'id' => $application->id,
            'registration_number' => $application->registration_number,
            'program' => $application->program?->name,
            'student_number' => $application->student?->student_number,
            'student_name' => $application->student?->full_name,
            'study_program' => $application->student?->studyProgram?->name,
            'status' => $application->status instanceof \Modules\MBKM\Enums\ApplicationStatus
                ? $application->status->value
                : (string) $application->status,
            'selection_score' => $application->selection_score,
            'selection_rank' => $application->selection_rank,
            'submitted_at' => optional($application->submitted_at)->toIso8601String(),
        ])->all();
    }

    protected function participants(Request $request): array
    {
        return $this->participantQuery($request)->orderByDesc('id')->get()->map(fn (MbkmParticipant $p) => [
            'id' => $p->id,
            'participant_number' => $p->participant_number,
            'program' => $p->program?->name,
            'student_number' => $p->student?->student_number,
            'student_name' => $p->student?->full_name,
            'study_program' => $p->student?->studyProgram?->name,
            'partner' => $p->placement?->partner?->name,
            'location' => $p->placement?->location?->name,
            'start_date' => optional($p->start_date)->toDateString(),
            'end_date' => optional($p->end_date)->toDateString(),
            'status' => $p->status instanceof ParticipantStatus ? $p->status->value : (string) $p->status,
            'final_score' => $p->final_score,
            'letter_grade' => $p->letter_grade,
            'recognized_credits' => $p->recognized_credits,
        ])->all();
    }

    protected function participantsByStudyProgram(Request $request): array
    {
        return collect($this->dashboardService->adminDashboard($request)['participants_by_study_program'])
            ->map(fn (int $count, string $name) => ['study_program' => $name, 'total' => $count])
            ->values()
            ->all();
    }

    protected function participantsByPartner(Request $request): array
    {
        return collect($this->dashboardService->adminDashboard($request)['participants_by_partner'])
            ->map(fn (int $count, string $name) => ['partner' => $name, 'total' => $count])
            ->values()
            ->all();
    }

    protected function participantsByPeriod(Request $request): array
    {
        return MbkmParticipant::with(['program.semester'])
            ->when($request->filled('semester_id'), fn ($q) => $q->whereHas('program', fn ($sq) => $sq->where('semester_id', $request->query('semester_id'))))
            ->get()
            ->groupBy(fn (MbkmParticipant $p) => $p->program?->semester?->name ?? 'Tanpa Periode')
            ->map(fn ($group, $name) => [
                'period' => $name,
                'total' => $group->count(),
                'active' => $group->filter(fn ($p) => $p->isActive())->count(),
                'completed' => $group->where('status', ParticipantStatus::COMPLETED)->count(),
            ])
            ->values()
            ->all();
    }

    protected function participantProgress(Request $request): array
    {
        return $this->participantQuery($request)->get()->map(function (MbkmParticipant $p) {
            $logbook = $this->logbookService->summary($p);
            $attendance = $this->attendanceService->summary($p);
            $assessment = $this->assessmentService->computeFinalScore($p);

            return [
                'participant_number' => $p->participant_number,
                'student_name' => $p->student?->full_name,
                'program' => $p->program?->name,
                'logbook_progress' => $logbook['progress_percentage'],
                'logbook_approved' => $logbook['approved'],
                'attendance_percentage' => $attendance['attendance_percentage'],
                'assessment_complete' => $assessment['is_complete'],
                'final_score' => $assessment['final_score'],
                'status' => $p->status instanceof ParticipantStatus ? $p->status->value : (string) $p->status,
            ];
        })->all();
    }

    protected function attendance(Request $request): array
    {
        return $this->participantQuery($request)->get()->map(function (MbkmParticipant $p) {
            $summary = $this->attendanceService->summary($p);

            return [
                'participant_number' => $p->participant_number,
                'student_name' => $p->student?->full_name,
                'program' => $p->program?->name,
                ...$summary,
            ];
        })->all();
    }

    protected function logbook(Request $request): array
    {
        return $this->participantQuery($request)->get()->map(function (MbkmParticipant $p) {
            $summary = $this->logbookService->summary($p);

            return [
                'participant_number' => $p->participant_number,
                'student_name' => $p->student?->full_name,
                'program' => $p->program?->name,
                ...$summary,
            ];
        })->all();
    }

    protected function assessment(Request $request): array
    {
        return $this->participantQuery($request)->get()->map(function (MbkmParticipant $p) {
            $result = $this->assessmentService->computeFinalScore($p);

            return [
                'participant_number' => $p->participant_number,
                'student_name' => $p->student?->full_name,
                'program' => $p->program?->name,
                'final_score' => $result['final_score'],
                'is_complete' => $result['is_complete'],
                'letter_grade' => $p->letter_grade,
                'grade_point' => $p->grade_point,
                'components' => $result['breakdown'],
            ];
        })->all();
    }

    protected function recognition(Request $request): array
    {
        $query = MbkmRecognition::with(['participant.student', 'course', 'program']);

        if ($request->filled('program_id')) {
            $query->where('program_id', $request->query('program_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return $query->orderByDesc('id')->get()->map(fn (MbkmRecognition $r) => [
            'id' => $r->id,
            'participant_number' => $r->participant?->participant_number,
            'student_name' => $r->participant?->student?->full_name,
            'program' => $r->program?->name,
            'source' => $r->source_label,
            'course' => $r->course ? $r->course->code . ' - ' . $r->course->name : null,
            'credits' => $r->credits,
            'score' => $r->score,
            'letter_grade' => $r->letter_grade,
            'status' => $r->status instanceof RecognitionStatus ? $r->status->value : (string) $r->status,
            'sync_status' => $r->sync_status,
        ])->all();
    }

    protected function recognizedCredits(Request $request): array
    {
        return $this->participantQuery($request)->get()->map(function (MbkmParticipant $p) {
            $summary = $this->recognitionService->summary($p);

            return [
                'participant_number' => $p->participant_number,
                'student_name' => $p->student?->full_name,
                'study_program' => $p->student?->studyProgram?->name,
                'program' => $p->program?->name,
                'total_credits' => $summary['total_credits'],
                'approved_credits' => $summary['approved_credits'],
                'pending_credits' => $summary['pending_credits'],
                'courses' => collect($summary['items'])->map(fn ($i) => $i['course_code'] . ' (' . $i['credits'] . ' SKS)')->filter()->values()->all(),
            ];
        })->all();
    }

    protected function completion(Request $request): array
    {
        return $this->participantQuery($request)->get()->map(function (MbkmParticipant $p) {
            $completion = $p->completion;

            return [
                'participant_number' => $p->participant_number,
                'student_name' => $p->student?->full_name,
                'program' => $p->program?->name,
                'status' => $p->status instanceof ParticipantStatus ? $p->status->value : (string) $p->status,
                'completion_status' => $completion?->status instanceof \Modules\MBKM\Enums\CompletionStatus
                    ? $completion->status->value
                    : ($completion?->status ? (string) $completion->status : null),
                'unmet_requirements' => $completion?->unmet_requirements ?? [],
                'completed_at' => optional($completion?->completed_at)->toIso8601String(),
            ];
        })->all();
    }
}
