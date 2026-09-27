<?php

namespace Modules\Integrator\Services;

use App\Support\QueryFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\Academic\Models\AcademicYear;
use Modules\Academic\Models\Faculty;
use Modules\Academic\Models\Institution;
use Modules\Academic\Models\Semester;
use Modules\Academic\Models\StudyProgram;
use Modules\Assessment\Services\GradeCalculationService;
use Modules\Class\Models\AcademicClass;
use Modules\Course\Models\Course;
use Modules\Curriculum\Models\Curriculum;
use Modules\Curriculum\Models\GradeScale;
use Modules\Enrollment\Models\StudentEnrollment;
use Modules\Graduation\Models\YudisiumParticipant;
use Modules\Integrator\Enums\ApiKeyScope;
use Modules\Lecturer\Models\Lecturer;
use Modules\MBKM\Models\MbkmParticipant;
use Modules\Student\Models\Student;
use Modules\Student\Services\StudentPortalService;
use Modules\Thesis\Models\Thesis;

/**
 * Read model behind the integration API (`/api/v1/integrator/v1/*`).
 *
 * Every method answers with "feeder friendly" rows: stable identifiers, feeder-ish
 * field names (`nim`, `nidn`, `sks`, `nilai_angka`, ...) and a semester code in the
 * PDDikti format (`20251`). Mapping into the exact Neo Feeder payload is the job of
 * the consuming integrator, so this service deliberately stays close to SIAKAD data
 * instead of guessing undocumented feeder columns.
 *
 * Personal data is masked unless the calling key holds the `students.pii` scope.
 */
class IntegratorDataService
{
    public function __construct(
        protected GradeCalculationService $gradeCalculationService,
        protected StudentPortalService $studentPortalService
    ) {}

    /**
     * Health probe: proves the key works and shows what it is allowed to read.
     *
     * @return array<string, mixed>
     */
    public function ping(): array
    {
        return [
            'application' => config('app.name', 'SIAKAD'),
            'server_time' => Carbon::now()->toIso8601String(),
            'timezone' => config('app.timezone'),
            'api_version' => 'v1',
        ];
    }

    /**
     * Institutional profile: institution, faculties, study programs and period structure.
     *
     * @return array<string, mixed>
     */
    public function profile(): array
    {
        $institution = Institution::query()->first();

        $studyPrograms = StudyProgram::query()
            ->with('faculty:id,name,code')
            ->orderBy('code')
            ->get()
            ->map(fn (StudyProgram $program) => [
                'id' => $program->id,
                'code' => $program->code,
                'name' => $program->name,
                'short_name' => $program->short_name,
                'degree' => $program->degree?->value,
                'status' => $program->status?->value,
                'faculty' => $program->faculty ? [
                    'id' => $program->faculty->id,
                    'code' => $program->faculty->code,
                    'name' => $program->faculty->name,
                ] : null,
            ]);

        return [
            'institution' => $institution ? [
                'id' => $institution->id,
                'code' => $institution->code,
                'name' => $institution->name,
                'short_name' => $institution->short_name,
                'address' => $institution->address,
                'phone' => $institution->phone,
                'website' => $institution->website,
            ] : null,
            'faculties' => Faculty::query()
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'short_name'])
                ->map(fn (Faculty $faculty) => [
                    'id' => $faculty->id,
                    'code' => $faculty->code,
                    'name' => $faculty->name,
                    'short_name' => $faculty->short_name,
                ]),
            'study_programs' => $studyPrograms,
            'academic_years' => AcademicYear::query()
                ->orderBy('name')
                ->get(['id', 'name', 'start_date', 'end_date', 'status'])
                ->map(fn (AcademicYear $year) => [
                    'id' => $year->id,
                    'name' => $year->name,
                    'start_date' => $year->start_date?->toDateString(),
                    'end_date' => $year->end_date?->toDateString(),
                    'status' => $year->status?->value,
                ]),
            'semesters' => $this->semesterList(),
            'grade_scales' => GradeScale::query()
                ->with('items')
                ->get()
                ->map(fn (GradeScale $scale) => [
                    'id' => $scale->id,
                    'name' => $scale->name,
                    'items' => $scale->items->map(fn ($item) => [
                        'grade_letter' => $item->grade_letter,
                        'grade_point' => (float) $item->grade_point,
                        'min_score' => (float) $item->min_score,
                        'max_score' => (float) $item->max_score,
                        'is_whitewash' => (bool) $item->is_whitewash,
                    ]),
                ]),
        ];
    }

    /**
     * Period structure on its own, including the PDDikti semester code.
     *
     * @return array<int, array<string, mixed>>
     */
    public function semesterList(): array
    {
        return Semester::query()
            ->with('academicYear:id,name')
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (Semester $semester) => [
                'id' => $semester->id,
                'name' => $semester->name,
                'type' => $semester->type?->value,
                'feeder_code' => $this->feederSemesterCode($semester),
                'academic_year' => $semester->academicYear?->name,
                'start_date' => $semester->start_date?->toDateString(),
                'end_date' => $semester->end_date?->toDateString(),
                'lecture_start_date' => $semester->lecture_start_date?->toDateString(),
                'lecture_end_date' => $semester->lecture_end_date?->toDateString(),
                'status' => $semester->status?->value,
            ])
            ->all();
    }

    /**
     * Students, newest first, cursor-friendly via `updated_since`.
     */
    public function students(Request $request): LengthAwarePaginator
    {
        $query = Student::query()
            ->with('studyProgram:id,code,name,degree')
            ->whereNull('deleted_at');

        $this->applyUpdatedSince($query, $request->query('updated_since'));

        $paginator = $this->paginate(
            $query,
            $request,
            searchable: ['student_number', 'full_name', 'national_student_number'],
            filterable: ['study_program_id', 'status', 'admission_year'],
        );

        $pii = $this->includesPii($request);

        return $paginator->through(fn (Student $student) => $this->studentPayload($student, $pii));
    }

    /**
     * Single student with registrations, family and school history.
     *
     * @return array<string, mixed>|null
     */
    public function student(string $studentNumber, bool $pii): ?array
    {
        $student = Student::query()
            ->with(['studyProgram:id,code,name,degree', 'families', 'educations'])
            ->where('student_number', $studentNumber)
            ->first();

        if (! $student) {
            return null;
        }

        $snapshot = $this->studentPortalService->getAcademicSnapshot($student);

        return [
            ...$this->studentPayload($student, $pii),
            'academic' => [
                'total_credits_passed' => $snapshot['total_credits_passed'],
                'cumulative_gpa' => $snapshot['cumulative_gpa'],
                'last_semester_gpa' => $snapshot['last_semester_gpa'],
                'current_semester' => $snapshot['current_semester'],
                'max_credits_next' => $snapshot['max_credits_next'],
            ],
            'families' => $student->families->map(fn ($family) => [
                'relation' => $family->relation ?? null,
                'name' => $family->name ?? null,
                'occupation' => $pii ? ($family->occupation ?? null) : null,
                'phone' => $pii ? ($family->phone ?? null) : null,
            ])->all(),
            'educations' => $student->educations->map(fn ($education) => [
                'institution_name' => $education->institution_name,
                'level' => $education->level,
                'major' => $education->major,
                'graduation_year' => $education->graduation_year,
                'certificate_number' => $education->certificate_number,
            ])->all(),
            'registrations' => StudentEnrollment::query()
                ->with('semester.academicYear:id,name')
                ->where('student_id', $student->id)
                ->orderBy('semester_id')
                ->get()
                ->map(function (StudentEnrollment $enrollment) {
                    $summary = $this->khsSummary($enrollment->student, $enrollment->semester_id);

                    return [
                        'semester_id' => $enrollment->semester_id,
                        'feeder_semester_code' => $enrollment->semester ? $this->feederSemesterCode($enrollment->semester) : null,
                        'status' => $enrollment->status?->value,
                        'sks_semester' => $summary['semester_credits'],
                        'ips' => $summary['semester_gpa'],
                        'sks_total' => $summary['cumulative_credits'],
                        'ipk' => $summary['cumulative_gpa'],
                        'max_credits_next' => $summary['max_credits_next'],
                    ];
                })
                ->all(),
        ];
    }

    /**
     * Lecturers (dosen), including home base study program and education history.
     */
    public function lecturers(Request $request): LengthAwarePaginator
    {
        $query = Lecturer::query()
            ->with(['homebaseStudyProgram:id,code,name', 'educations'])
            ->whereNull('deleted_at');

        $this->applyUpdatedSince($query, $request->query('updated_since'));

        $paginator = $this->paginate(
            $query,
            $request,
            searchable: ['lecturer_number', 'nidn', 'nidk', 'nip', 'full_name'],
            filterable: ['homebase_study_program_id', 'status'],
        );

        return $paginator->through(fn (Lecturer $lecturer) => [
            'id' => $lecturer->id,
            'nidn' => $lecturer->nidn,
            'nidk' => $lecturer->nidk,
            'nip' => $lecturer->nip,
            'lecturer_number' => $lecturer->lecturer_number,
            'name' => $lecturer->full_name,
            'gender' => $lecturer->gender ? $this->feederGender($lecturer->gender->value) : null,
            'birth_place' => $lecturer->birth_place,
            'birth_date' => $lecturer->birth_date?->toDateString(),
            'academic_degree' => $lecturer->academic_degree,
            'functional_position' => $lecturer->functional_position,
            'status' => $lecturer->status?->value,
            'join_date' => $lecturer->join_date?->toDateString(),
            'email' => $lecturer->email,
            'phone' => $lecturer->phone,
            'homebase_study_program' => $lecturer->homebaseStudyProgram ? [
                'id' => $lecturer->homebaseStudyProgram->id,
                'code' => $lecturer->homebaseStudyProgram->code,
                'name' => $lecturer->homebaseStudyProgram->name,
            ] : null,
            'educations' => $lecturer->educations->map(fn ($education) => [
                'level' => $education->level ?? null,
                'institution_name' => $education->institution_name ?? null,
                'major' => $education->major ?? null,
                'graduation_year' => $education->graduation_year ?? null,
            ])->all(),
        ]);
    }

    /**
     * Course catalogue.
     */
    public function courses(Request $request): LengthAwarePaginator
    {
        $query = Course::query()
            ->with('studyProgram:id,code,name')
            ->whereNull('deleted_at');

        $this->applyUpdatedSince($query, $request->query('updated_since'));

        $paginator = $this->paginate(
            $query,
            $request,
            searchable: ['code', 'name'],
            filterable: ['study_program_id', 'status', 'type'],
        );

        return $paginator->through(fn (Course $course) => $this->coursePayload($course));
    }

    /**
     * Curricula with their subjects grouped per curriculum semester.
     */
    public function curricula(Request $request): LengthAwarePaginator
    {
        $query = Curriculum::query()
            ->with([
                'studyProgram:id,code,name',
                'curriculumYear:id,name,year',
                'semesters.subjects.course:id,code,name,credits',
            ])
            ->whereNull('deleted_at');

        $this->applyUpdatedSince($query, $request->query('updated_since'));

        $paginator = $this->paginate(
            $query,
            $request,
            searchable: ['code', 'name'],
            filterable: ['study_program_id', 'status'],
        );

        return $paginator->through(fn (Curriculum $curriculum) => [
            'id' => $curriculum->id,
            'code' => $curriculum->code,
            'name' => $curriculum->name,
            'version' => $curriculum->version,
            'start_year' => $curriculum->start_year,
            'end_year' => $curriculum->end_year,
            'effective_date' => $curriculum->effective_date?->toDateString(),
            'expiry_date' => $curriculum->expiry_date?->toDateString(),
            'status' => $curriculum->status?->value,
            'study_program' => $curriculum->studyProgram ? [
                'id' => $curriculum->studyProgram->id,
                'code' => $curriculum->studyProgram->code,
                'name' => $curriculum->studyProgram->name,
            ] : null,
            'curriculum_year' => $curriculum->curriculumYear?->name,
            'semesters' => $curriculum->semesters
                ->sortBy('semester_number')
                ->map(fn ($semester) => [
                    'semester_number' => $semester->semester_number,
                    'subjects' => collect($semester->subjects)->map(fn ($subject) => [
                        'course_id' => $subject->course_id,
                        'course_code' => $subject->course?->code,
                        'course_name' => $subject->course?->name,
                        'credits' => $subject->credits_override ?? $subject->course?->credits,
                        'is_mandatory' => (bool) $subject->is_mandatory,
                        'subject_type' => $subject->subject_type,
                        'minimum_grade' => $subject->minimum_grade,
                    ])->values()->all(),
                ])->values()->all(),
        ]);
    }

    /**
     * Academic classes for a semester, with lecturers and schedules.
     */
    public function classes(Request $request): LengthAwarePaginator
    {
        $query = AcademicClass::query()
            ->with([
                'semester.academicYear:id,name',
                'course:id,code,name,credits',
                'studyProgram:id,code,name',
                'classLecturers.lecturer:id,nidn,full_name,lecturer_number',
                'schedules.room:id,code,name',
            ])
            ->whereNull('deleted_at');

        $this->applyUpdatedSince($query, $request->query('updated_since'));

        $paginator = $this->paginate(
            $query,
            $request,
            searchable: ['code', 'name'],
            filterable: ['semester_id', 'study_program_id', 'course_id', 'status'],
        );

        return $paginator->through(fn (AcademicClass $class) => [
            'id' => $class->id,
            'code' => $class->code,
            'name' => $class->name,
            'section' => $class->section,
            'capacity' => $class->capacity,
            'enrolled_count' => $class->enrolled_count,
            'status' => $class->status?->value,
            'semester' => $class->semester ? [
                'id' => $class->semester->id,
                'name' => $class->semester->name,
                'feeder_code' => $this->feederSemesterCode($class->semester),
            ] : null,
            'course' => $class->course ? $this->coursePayload($class->course) : null,
            'study_program' => $class->studyProgram ? [
                'id' => $class->studyProgram->id,
                'code' => $class->studyProgram->code,
                'name' => $class->studyProgram->name,
            ] : null,
            'lecturers' => $class->classLecturers->map(fn ($lecturer) => [
                'lecturer_id' => $lecturer->lecturer_id,
                'nidn' => $lecturer->lecturer?->nidn,
                'name' => $lecturer->lecturer?->full_name,
                'role' => $lecturer->role?->value ?? $lecturer->role,
            ])->values()->all(),
            'schedules' => $class->schedules->map(fn ($schedule) => [
                'day_of_week' => $schedule->day_of_week?->value ?? $schedule->day_of_week,
                'start_time' => $schedule->start_time,
                'end_time' => $schedule->end_time,
                'room' => $schedule->room?->name,
            ])->values()->all(),
        ]);
    }

    /**
     * KRS rows (peserta kelas) for one semester — the source of feeder KRS data.
     */
    public function enrollments(Request $request): LengthAwarePaginator
    {
        $query = StudentEnrollment::query()
            ->with([
                'student:id,student_number,full_name,study_program_id',
                'student.studyProgram:id,code,name',
                'semester.academicYear:id,name',
                'items.academicClass:id,code',
                'items.course:id,code,name,credits',
            ])
            ->whereNull('deleted_at');

        $this->applyUpdatedSince($query, $request->query('updated_since'));

        $paginator = $this->paginate(
            $query,
            $request,
            searchable: ['student.student_number', 'student.full_name'],
            filterable: ['semester_id', 'student_id', 'status'],
        );

        return $paginator->through(function (StudentEnrollment $enrollment) {
            $items = $enrollment->items;

            return [
                'id' => $enrollment->id,
                'student' => [
                    'id' => $enrollment->student?->id,
                    'nim' => $enrollment->student?->student_number,
                    'name' => $enrollment->student?->full_name,
                    'study_program_code' => $enrollment->student?->studyProgram?->code,
                ],
                'semester' => $enrollment->semester ? [
                    'id' => $enrollment->semester->id,
                    'name' => $enrollment->semester->name,
                    'feeder_code' => $this->feederSemesterCode($enrollment->semester),
                ] : null,
                'status' => $enrollment->status?->value,
                'total_credits' => $enrollment->total_credits,
                'max_credits' => $enrollment->max_credits,
                'submitted_at' => $enrollment->submitted_at?->toIso8601String(),
                'approved_at' => $enrollment->approved_at?->toIso8601String(),
                'active_credits' => $items->filter(fn ($item) => $item->isActive())->sum(fn ($item) => (int) $item->credits),
                'items' => $items->map(fn ($item) => [
                    'id' => $item->id,
                    'class_id' => $item->class_id,
                    'class_code' => $item->academicClass?->code,
                    'course_id' => $item->course_id,
                    'course_code' => $item->course?->code,
                    'course_name' => $item->course?->name,
                    'credits' => $item->credits,
                    'status' => $item->status?->value,
                    'finalized_at' => $item->finalized_at?->toIso8601String(),
                ])->values()->all(),
            ];
        });
    }

    /**
     * AKM rows (Aktivitas Kuliah Mahasiswa): SKS and IPS per student, per semester.
     *
     * Values come from `StudentPortalService` so the numbers match what students see
     * on their KHS — including recognitions and locked grades.
     */
    public function akm(Request $request): ?LengthAwarePaginator
    {
        $semesterId = $request->query('semester_id');

        if (! $semesterId) {
            return null;
        }

        $query = StudentEnrollment::query()
            ->with(['student:id,student_number,full_name,study_program_id', 'student.studyProgram:id,code,name', 'semester.academicYear:id,name'])
            ->where('semester_id', $semesterId)
            ->whereNull('deleted_at');

        $this->applyUpdatedSince($query, $request->query('updated_since'));

        $paginator = $this->paginate(
            $query,
            $request,
            searchable: ['student.student_number', 'student.full_name'],
            filterable: ['status'],
        );

        return $paginator->through(function (StudentEnrollment $enrollment) {
            $summary = $this->khsSummary($enrollment->student, $enrollment->semester_id);

            return [
                'student' => [
                    'id' => $enrollment->student?->id,
                    'nim' => $enrollment->student?->student_number,
                    'name' => $enrollment->student?->full_name,
                    'study_program_code' => $enrollment->student?->studyProgram?->code,
                ],
                'semester' => $enrollment->semester ? [
                    'id' => $enrollment->semester->id,
                    'name' => $enrollment->semester->name,
                    'feeder_code' => $this->feederSemesterCode($enrollment->semester),
                ] : null,
                'enrollment_status' => $enrollment->status?->value,
                'sks_semester' => $summary['semester_credits'],
                'ips' => $summary['semester_gpa'],
                'sks_total' => $summary['cumulative_credits'],
                'ipk' => $summary['cumulative_gpa'],
                'student_status' => $enrollment->student?->status?->value,
            ];
        });
    }

    /**
     * Grade recap per class, computed with the academic grade engine.
     *
     * Pass `class_id` to pull a single class, or `student_number` to pull everything
     * one student is enrolled in for the given semester.
     */
    public function grades(Request $request): ?LengthAwarePaginator
    {
        $classId = $request->query('class_id');
        $studentNumber = $request->query('student_number');

        if ($studentNumber) {
            return $this->studentGrades($request, $studentNumber);
        }

        $query = AcademicClass::query()
            ->with(['course:id,code,name,credits', 'semester.academicYear:id,name'])
            ->whereNull('deleted_at')
            ->when($classId, fn (Builder $q) => $q->whereKey($classId));

        $this->applyUpdatedSince($query, $request->query('updated_since'));

        $paginator = $this->paginate(
            $query,
            $request,
            searchable: ['code', 'name'],
            filterable: ['semester_id', 'study_program_id', 'course_id'],
        );

        $withComponents = $request->boolean('with_components');

        return $paginator->through(function (AcademicClass $class) use ($withComponents) {
            $recap = $this->gradeCalculationService->calculateClassGradeRecap($class);

            return [
                'class' => [
                    'id' => $class->id,
                    'code' => $class->code,
                    'course_code' => $class->course?->code,
                    'course_name' => $class->course?->name,
                    'credits' => $class->course?->credits,
                ],
                'semester' => $class->semester ? [
                    'id' => $class->semester->id,
                    'name' => $class->semester->name,
                    'feeder_code' => $this->feederSemesterCode($class->semester),
                ] : null,
                'scheme' => $recap['scheme'],
                'summary' => $recap['summary'],
                'grades' => collect($recap['grades'])->map(fn ($row) => [
                    'student_id' => $row['student']['id'],
                    'nim' => $row['student']['student_number'],
                    'name' => $row['student']['full_name'],
                    'nilai_angka' => $row['final_score'],
                    'nilai_huruf' => $row['letter_grade'],
                    'grade_point' => $row['grade_point'],
                    'is_complete' => $row['is_complete'],
                    'components' => $withComponents ? $row['components'] : null,
                ])->values()->all(),
            ];
        });
    }

    /**
     * Thesis and MBKM activities, in the shape PDDikti expects for "aktivitas mahasiswa".
     */
    public function activities(Request $request, string $type): LengthAwarePaginator
    {
        return $type === 'thesis'
            ? $this->thesisActivities($request)
            : $this->mbkmActivities($request);
    }

    /**
     * Graduates / drop-outs from yudisium: the source of `InsertMahasiswaLulusDO`.
     */
    public function graduates(Request $request): LengthAwarePaginator
    {
        $query = YudisiumParticipant::query()
            ->with(['student:id,student_number,full_name,study_program_id,graduation_date', 'student.studyProgram:id,code,name', 'period.semester.academicYear:id,name']);

        $paginator = $this->paginate(
            $query,
            $request,
            searchable: ['student.student_number', 'student.full_name', 'sk_number'],
            filterable: ['yudisium_period_id', 'status'],
        );

        return $paginator->through(fn (YudisiumParticipant $participant) => [
            'id' => $participant->id,
            'nim' => $participant->student?->student_number,
            'name' => $participant->student?->full_name,
            'study_program_code' => $participant->student?->studyProgram?->code,
            // Jenjang dipakai validasi pelaporan: aturan patch Neo Feeder 3.0.1
            // melarang pengiriman nomor ijazah untuk D3/D4/S1/S2/S3.
            'study_program_degree' => $participant->student?->studyProgram?->degree?->value,
            'status' => is_string($participant->status) ? $participant->status : $participant->status?->value,
            'period' => $participant->period?->name,
            'feeder_semester_code' => $participant->period?->semester
                ? $this->feederSemesterCode($participant->period->semester)
                : null,
            'yudisium_date' => $participant->period?->yudisium_date?->toDateString(),
            'graduation_date' => $participant->student?->graduation_date?->toDateString(),
            'sks_total' => $participant->total_credits,
            'ipk' => $participant->gpa !== null ? (float) $participant->gpa : null,
            'sk_number' => $participant->sk_number,
            'sk_date' => $participant->sk_date?->toDateString(),
        ]);
    }

    /**
     * Lightweight counters, for the integrator dashboard.
     *
     * @return array<string, mixed>
     */
    public function snapshot(?int $semesterId = null): array
    {
        // Prefer the semester the campus has activated; only fall back to "latest by
        // start date", because that one is often a period nobody has data for yet.
        $semester = $semesterId
            ? Semester::with('academicYear:id,name')->find($semesterId)
            : (Semester::with('academicYear:id,name')->where('status', 'active')->orderByDesc('start_date')->first()
                ?? Semester::with('academicYear:id,name')->orderByDesc('start_date')->first());

        $classQuery = AcademicClass::query()->whereNull('deleted_at');
        $enrollmentQuery = StudentEnrollment::query()->whereNull('deleted_at');

        if ($semester) {
            $classQuery->where('semester_id', $semester->id);
            $enrollmentQuery->where('semester_id', $semester->id);
        }

        return [
            'semester' => $semester ? [
                'id' => $semester->id,
                'name' => $semester->name,
                'feeder_code' => $this->feederSemesterCode($semester),
                'academic_year' => $semester->academicYear?->name,
            ] : null,
            'counts' => [
                'students' => Student::query()->whereNull('deleted_at')->count(),
                'students_active' => Student::query()->whereNull('deleted_at')->where('status', 'active')->count(),
                'lecturers' => Lecturer::query()->whereNull('deleted_at')->count(),
                'courses' => Course::query()->whereNull('deleted_at')->count(),
                'curricula' => Curriculum::query()->whereNull('deleted_at')->count(),
                'classes' => (clone $classQuery)->count(),
                'enrollments' => (clone $enrollmentQuery)->count(),
                'theses' => Thesis::query()->count(),
                'mbkm_participants' => MbkmParticipant::query()->count(),
                'graduates' => YudisiumParticipant::query()->count(),
            ],
        ];
    }

    /**
     * Payload of a single student row.
     *
     * @return array<string, mixed>
     */
    protected function studentPayload(Student $student, bool $pii): array
    {
        return [
            'id' => $student->id,
            'nim' => $student->student_number,
            'nisn' => $student->national_student_number,
            'nik' => $pii ? $student->national_id : $this->maskNationalId($student->national_id),
            'name' => $student->full_name,
            'nickname' => $student->nickname,
            'gender' => $student->gender ? $this->feederGender($student->gender->value) : null,
            'birth_place' => $student->birth_place,
            'birth_date' => $student->birth_date?->toDateString(),
            'religion' => $student->religion,
            'marital_status' => $student->marital_status,
            'mother_name' => $pii ? $student->mother_name : null,
            'email' => $pii ? $student->email : $this->maskEmail($student->email),
            'phone' => $pii ? $student->phone : $this->maskPhone($student->phone),
            'address' => $pii ? $student->address : null,
            'province' => $pii ? $student->province : null,
            'city' => $pii ? $student->city : null,
            'district' => $pii ? $student->district : null,
            'postal_code' => $pii ? $student->postal_code : null,
            'study_program' => $student->studyProgram ? [
                'id' => $student->studyProgram->id,
                'code' => $student->studyProgram->code,
                'name' => $student->studyProgram->name,
                'degree' => $student->studyProgram->degree?->value,
            ] : null,
            'admission_year' => $student->admission_year,
            'entry_date' => $student->entry_date?->toDateString(),
            'status' => $student->status?->value,
            'graduation_date' => $student->graduation_date?->toDateString(),
            'pii_included' => $pii,
            'updated_at' => $student->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Course payload shared by the course, class and curriculum endpoints.
     *
     * @return array<string, mixed>
     */
    protected function coursePayload(Course $course): array
    {
        return [
            'id' => $course->id,
            'code' => $course->code,
            'name' => $course->name,
            'short_name' => $course->short_name,
            'credits' => $course->credits,
            'credits_breakdown' => [
                'theory' => $course->theory_credits,
                'practical' => $course->practical_credits,
                'field_practical' => $course->field_practical_credits,
                'simulation' => $course->simulation_credits,
                'seminar' => $course->seminar_credits,
            ],
            'type' => $course->type?->value,
            'category' => $course->category,
            'status' => $course->status?->value,
            'study_program' => $course->studyProgram ? [
                'id' => $course->studyProgram->id,
                'code' => $course->studyProgram->code,
                'name' => $course->studyProgram->name,
            ] : null,
        ];
    }

    /**
     * Thesis rows for the activities endpoint.
     */
    protected function thesisActivities(Request $request): LengthAwarePaginator
    {
        $query = Thesis::query()->with([
            'student:id,student_number,full_name,study_program_id',
            'student.studyProgram:id,code,name',
            'startSemester.academicYear:id,name',
            'completionSemester.academicYear:id,name',
            'supervisors.lecturer:id,nidn,full_name',
        ]);

        $this->applyUpdatedSince($query, $request->query('updated_since'));

        $paginator = $this->paginate(
            $query,
            $request,
            searchable: ['title_id', 'title_en', 'student.student_number', 'student.full_name'],
            filterable: ['study_program_id', 'start_semester_id', 'completion_semester_id'],
        );

        return $paginator->through(fn (Thesis $thesis) => [
            'id' => $thesis->id,
            'type' => 'thesis',
            'nim' => $thesis->student?->student_number,
            'name' => $thesis->student?->full_name,
            'study_program_code' => $thesis->student?->studyProgram?->code,
            'title' => $thesis->title_id,
            'title_en' => $thesis->title_en,
            'topic' => $thesis->topic_id,
            'status' => is_string($thesis->status) ? $thesis->status : $thesis->status?->value,
            'start_semester_code' => $thesis->startSemester ? $this->feederSemesterCode($thesis->startSemester) : null,
            'completion_semester_code' => $thesis->completionSemester ? $this->feederSemesterCode($thesis->completionSemester) : null,
            'start_date' => $thesis->start_date?->toDateString(),
            'submission_date' => $thesis->submission_date?->toDateString(),
            'completion_date' => $thesis->completion_date?->toDateString(),
            'sk_number' => $thesis->sk_number,
            'sk_date' => $thesis->sk_date?->toDateString(),
            'final_score' => $thesis->final_grade !== null ? (float) $thesis->final_grade : null,
            'letter_grade' => $thesis->final_grade_letter,
            'supervisors' => $thesis->supervisors->map(fn ($supervisor) => [
                'nidn' => $supervisor->lecturer?->nidn,
                'name' => $supervisor->lecturer?->full_name,
                'role' => $supervisor->role,
                'order' => $supervisor->order,
            ])->values()->all(),
        ]);
    }

    /**
     * MBKM rows for the activities endpoint.
     */
    protected function mbkmActivities(Request $request): LengthAwarePaginator
    {
        $query = MbkmParticipant::query()->with([
            'student:id,student_number,full_name,study_program_id',
            'student.studyProgram:id,code,name',
            'program:id,code,name,organizer_name',
            'program.programType:id,name',
            'program.semester.academicYear:id,name',
            'placement.partner:id,name,type',
            'internalSupervisor.lecturer:id,nidn,full_name',
        ]);

        $this->applyUpdatedSince($query, $request->query('updated_since'));

        $paginator = $this->paginate(
            $query,
            $request,
            searchable: ['participant_number', 'student.student_number', 'student.full_name'],
            filterable: ['program_id', 'status'],
        );

        return $paginator->through(fn (MbkmParticipant $participant) => [
            'id' => $participant->id,
            'type' => 'mbkm',
            'participant_number' => $participant->participant_number,
            'nim' => $participant->student?->student_number,
            'name' => $participant->student?->full_name,
            'study_program_code' => $participant->student?->studyProgram?->code,
            'program' => $participant->program ? [
                'code' => $participant->program->code,
                'name' => $participant->program->name,
                'type' => $participant->program->programType?->name,
                'organizer' => $participant->program->organizer_name,
            ] : null,
            'partner' => $participant->placement?->partner?->name,
            'status' => is_string($participant->status) ? $participant->status : $participant->status?->value,
            'start_date' => $participant->start_date?->toDateString(),
            'end_date' => $participant->end_date?->toDateString(),
            'completed_at' => $participant->completed_at?->toIso8601String(),
            'final_score' => $participant->final_score !== null ? (float) $participant->final_score : null,
            'letter_grade' => $participant->letter_grade,
            'grade_point' => $participant->grade_point !== null ? (float) $participant->grade_point : null,
            'recognized_credits' => $participant->recognized_credits,
            'supervisor' => $participant->internalSupervisor ? [
                'nidn' => $participant->internalSupervisor->lecturer?->nidn,
                'name' => $participant->internalSupervisor->lecturer?->full_name,
            ] : null,
        ]);
    }

    /**
     * Grades of one student for a semester.
     */
    protected function studentGrades(Request $request, string $studentNumber): LengthAwarePaginator
    {
        $student = Student::query()->where('student_number', $studentNumber)->first();

        $query = $student
            ? AcademicClass::query()
                ->whereHas('enrollmentItems', function (Builder $q) use ($student) {
                    $q->where('status', 'enrolled')
                        ->whereHas('enrollment', fn (Builder $e) => $e->where('student_id', $student->id));
                })
            : AcademicClass::query()->whereRaw('1 = 0');

        $query->with(['course:id,code,name,credits', 'semester.academicYear:id,name'])
            ->when($request->query('semester_id'), fn (Builder $q, $semester) => $q->where('semester_id', $semester))
            ->whereNull('deleted_at');

        $paginator = $this->paginate($query, $request, searchable: ['code', 'name'], filterable: ['semester_id']);

        $withComponents = $request->boolean('with_components');

        return $paginator->through(function (AcademicClass $class) use ($student, $withComponents) {
            $calculation = $this->gradeCalculationService->calculateStudentFinalScore($student, $class);

            return [
                'class' => [
                    'id' => $class->id,
                    'code' => $class->code,
                    'course_code' => $class->course?->code,
                    'course_name' => $class->course?->name,
                    'credits' => $class->course?->credits,
                ],
                'semester' => $class->semester ? [
                    'id' => $class->semester->id,
                    'name' => $class->semester->name,
                    'feeder_code' => $this->feederSemesterCode($class->semester),
                ] : null,
                'nim' => $student?->student_number,
                'nilai_angka' => $calculation['final_score'],
                'nilai_huruf' => $calculation['letter_grade'],
                'grade_point' => $calculation['grade_point'],
                'is_complete' => $calculation['is_complete'],
                'components' => $withComponents ? $calculation['components_breakdown'] : null,
            ];
        });
    }

    /**
     * KHS summary of one student for one semester, using the academic engine.
     *
     * @return array{semester_credits: int, semester_gpa: float, cumulative_credits: int, cumulative_gpa: float, max_credits_next: int}
     */
    protected function khsSummary(?Student $student, ?int $semesterId): array
    {
        $empty = [
            'semester_credits' => 0,
            'semester_gpa' => 0.0,
            'cumulative_credits' => 0,
            'cumulative_gpa' => 0.0,
            'max_credits_next' => 0,
        ];

        if (! $student) {
            return $empty;
        }

        $khs = $this->studentPortalService->getStudentKHS($student, $semesterId);
        $summary = $khs['summary'] ?? [];

        return [
            'semester_credits' => (int) ($summary['semester_credits'] ?? 0),
            'semester_gpa' => (float) ($summary['semester_gpa'] ?? 0),
            'cumulative_credits' => (int) ($summary['cumulative_credits'] ?? 0),
            'cumulative_gpa' => (float) ($summary['cumulative_gpa'] ?? 0),
            'max_credits_next' => (int) ($summary['max_credits_next'] ?? 0),
        ];
    }

    /**
     * Shared list pagination: search, exact filters, safe sort, capped page size.
     *
     * @param  Builder<*>  $query
     * @param  array<int, string>  $searchable
     * @param  array<int, string>  $filterable
     */
    protected function paginate(
        Builder $query,
        Request $request,
        array $searchable,
        array $filterable,
        string $defaultSort = 'id',
        string $defaultDirection = 'asc'
    ): LengthAwarePaginator {
        return QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: $searchable,
            filterableColumns: $filterable,
            defaultSort: $defaultSort,
            defaultDirection: $defaultDirection,
            defaultPerPage: 100,
            maxPerPage: 500
        );
    }

    /**
     * Incremental sync hook: `?updated_since=2026-09-01T00:00:00Z`.
     *
     * @param  Builder<*>  $query
     */
    protected function applyUpdatedSince(Builder $query, mixed $updatedSince): void
    {
        if (! $updatedSince || ! is_string($updatedSince)) {
            return;
        }

        $query->where('updated_at', '>=', Carbon::parse($updatedSince));
    }

    /**
     * Whether the calling key may see unmasked personal data.
     */
    protected function includesPii(Request $request): bool
    {
        $key = $request->attributes->get('integrator.api_key');

        return $key?->can(ApiKeyScope::STUDENTS_PII) ?? false;
    }

    /**
     * PDDikti uses `L`/`P`; SIAKAD stores `male`/`female`.
     */
    protected function feederGender(?string $gender): ?string
    {
        return match ($gender) {
            'male' => 'L',
            'female' => 'P',
            default => null,
        };
    }

    /**
     * PDDikti semester code: `<tahun-awal><1|2|3>` e.g. `20251` (ganjil), `20253` (antara).
     */
    public function feederSemesterCode(Semester $semester): ?string
    {
        $yearName = (string) ($semester->academicYear?->name ?? '');

        $startYear = preg_match('/(\d{4})/', $yearName, $matches)
            ? (int) $matches[1]
            : (int) ($semester->start_date?->format('Y') ?? 0);

        if ($startYear === 0) {
            return null;
        }

        $period = match ($semester->type?->value) {
            'genap' => 2,
            'pendek' => 3,
            default => 1,
        };

        return $startYear.$period;
    }

    /**
     * Keep the first four and last four digits of a NIK.
     */
    protected function maskNationalId(?string $value): ?string
    {
        return $this->maskKeepEdges($value, 4, 4);
    }

    /**
     * `budi@example.com` becomes `b***@example.com`.
     */
    protected function maskEmail(?string $email): ?string
    {
        if (! $email || ! str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 1).'***@'.$domain;
    }

    /**
     * Keep the first four and last two digits of a phone number.
     */
    protected function maskPhone(?string $phone): ?string
    {
        return $this->maskKeepEdges($phone, 4, 2);
    }

    /**
     * Mask the middle of a string, keeping a prefix and suffix.
     */
    protected function maskKeepEdges(?string $value, int $prefix, int $suffix): ?string
    {
        $value = $value === null ? null : trim($value);

        if ($value === null || $value === '') {
            return $value;
        }

        $length = mb_strlen($value);

        if ($length <= $prefix + $suffix) {
            return str_repeat('*', $length);
        }

        return mb_substr($value, 0, $prefix)
            .str_repeat('*', max(1, $length - $prefix - $suffix))
            .mb_substr($value, -$suffix);
    }
}
