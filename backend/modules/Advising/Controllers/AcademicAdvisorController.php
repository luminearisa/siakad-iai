<?php

namespace Modules\Advising\Controllers;

use App\Http\Controllers\Controller;
use App\Support\QueryFilter;
use App\Support\Traits\HasApiResponse;
use App\Support\Traits\ScopesToOwnLecturer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Advising\Models\AcademicAdvisor;
use Modules\Advising\Requests\AssignAcademicAdvisorRequest;
use Modules\Advising\Requests\ChangeAcademicAdvisorRequest;
use Modules\Advising\Resources\AcademicAdvisorResource;
use Modules\Advising\Services\AdvisingService;
use Modules\Student\Models\Student;

class AcademicAdvisorController extends Controller
{
    use HasApiResponse, ScopesToOwnLecturer;

    public function __construct(
        protected AdvisingService $advisingService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = AcademicAdvisor::with(['student.studyProgram.faculty', 'lecturer.homebaseStudyProgram.faculty']);

        $user = $request->user();
        if ($user && $user->hasRole('mahasiswa')) {
            $student = $user->student;
            if ($student) {
                $query->where('student_id', $student->id);
            }
        }

        // A plain lecturer only sees the students they advise.
        $this->scopeToOwnLecturer($request, $query, 'advising.assign', null, 'lecturer_id');

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('notes', 'like', "%{$search}%")
                  ->orWhereHas('student', function ($sq) use ($search) {
                      $sq->where('full_name', 'like', "%{$search}%")
                        ->orWhere('student_number', 'like', "%{$search}%");
                  })
                  ->orWhereHas('lecturer', function ($lq) use ($search) {
                      $lq->where('full_name', 'like', "%{$search}%")
                        ->orWhere('nidn', 'like', "%{$search}%")
                        ->orWhere('lecturer_number', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('study_program_id')) {
            $spId = $request->query('study_program_id');
            $query->where(function ($q) use ($spId) {
                $q->whereHas('student', function ($sq) use ($spId) {
                    $sq->where('study_program_id', $spId);
                })->orWhereHas('lecturer', function ($lq) use ($spId) {
                    $lq->where('homebase_study_program_id', $spId);
                });
            });
        }

        $paginator = QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: [],
            filterableColumns: ['student_id', 'lecturer_id', 'status'],
            defaultSort: 'id',
            defaultDirection: 'desc'
        );

        return $this->paginatedResponse(
            paginator: $paginator,
            message: 'Academic advisors retrieved successfully.',
            resourceClass: AcademicAdvisorResource::class
        );
    }

    public function store(AssignAcademicAdvisorRequest $request): JsonResponse
    {
        $advisor = $this->advisingService->assign(
            studentId: $request->validated('student_id'),
            lecturerId: $request->validated('lecturer_id'),
            startDate: $request->validated('start_date'),
            notes: $request->validated('notes')
        );

        return $this->successResponse(
            data: new AcademicAdvisorResource($advisor),
            message: 'Academic advisor assigned successfully.',
            code: 201
        );
    }

    public function show(Request $request, AcademicAdvisor $advisor): JsonResponse
    {
        if (!$this->lecturerMayAccessOwnedRecord($request, $advisor->lecturer_id, 'advising.assign')) {
            return $this->errorResponse('Unauthorized to view this academic advisor assignment.', 403);
        }

        return $this->successResponse(
            data: new AcademicAdvisorResource($advisor->load(['student.studyProgram.faculty', 'lecturer.homebaseStudyProgram.faculty'])),
            message: 'Academic advisor assignment retrieved successfully.'
        );
    }

    public function currentStudentAdvisor(Student $student): JsonResponse
    {
        $advisor = AcademicAdvisor::where('student_id', $student->id)
            ->where('status', 'active')
            ->with(['lecturer.homebaseStudyProgram.faculty'])
            ->first();

        if (!$advisor) {
            return $this->errorResponse('No active academic advisor assigned to this student.', 404);
        }

        return $this->successResponse(
            data: new AcademicAdvisorResource($advisor),
            message: 'Active academic advisor retrieved successfully.'
        );
    }

    public function studentAdvisorHistory(Student $student): JsonResponse
    {
        $history = AcademicAdvisor::where('student_id', $student->id)
            ->with(['lecturer.homebaseStudyProgram'])
            ->orderBy('id', 'desc')
            ->get();

        return $this->successResponse(
            data: AcademicAdvisorResource::collection($history),
            message: 'Academic advisor history retrieved successfully.'
        );
    }

    public function changeAdvisor(ChangeAcademicAdvisorRequest $request, Student $student): JsonResponse
    {
        $advisor = $this->advisingService->change(
            studentId: $student->id,
            newLecturerId: $request->validated('new_lecturer_id'),
            changeDate: $request->validated('change_date'),
            reason: $request->validated('reason')
        );

        return $this->successResponse(
            data: new AcademicAdvisorResource($advisor),
            message: 'Academic advisor changed successfully.'
        );
    }

    public function endAdvisor(Request $request, AcademicAdvisor $advisor): JsonResponse
    {
        if (!$request->user()?->hasPermissionTo('advising.assign')) {
            return $this->errorResponse('Unauthorized to end academic advisor assignment.', 403);
        }

        $ended = $this->advisingService->end(
            advisor: $advisor,
            endDate: $request->input('end_date'),
            notes: $request->input('notes')
        );

        return $this->successResponse(
            data: new AcademicAdvisorResource($ended),
            message: 'Academic advisor assignment ended successfully.'
        );
    }

    public function distribution(Request $request): JsonResponse
    {
        $query = Student::with(['studyProgram', 'academicAdvisor.lecturer']);

        // A plain lecturer only sees the students they advise (dosen wali).
        [$isLecturerOnly, $ownLecturerId] = $this->resolveOwnLecturerScope($request, 'advising.assign');
        if ($isLecturerOnly) {
            if ($ownLecturerId) {
                $query->whereHas('academicAdvisor', function ($q) use ($ownLecturerId) {
                    $q->where('lecturer_id', $ownLecturerId);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('student_number', 'like', "%{$search}%")
                  ->orWhereHas('studyProgram', function ($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('admission_year')) {
            $query->where('admission_year', $request->query('admission_year'));
        }

        if ($request->filled('study_program_id')) {
            $query->where('study_program_id', $request->query('study_program_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $students = $query->orderBy('admission_year', 'desc')
            ->orderBy('student_number', 'asc')
            ->get();

        $data = $students->map(function ($s) {
            return [
                'id' => $s->id,
                'admission_year' => $s->admission_year,
                'student_number' => $s->student_number,
                'full_name' => $s->full_name,
                'study_program_id' => $s->study_program_id,
                'study_program' => $s->studyProgram ? [
                    'id' => $s->studyProgram->id,
                    'name' => $s->studyProgram->name,
                    'degree' => $s->studyProgram->degree,
                    'code' => $s->studyProgram->code,
                ] : null,
                'status' => $s->status instanceof \BackedEnum ? $s->status->value : $s->status,
                'academic_advisor' => $s->academicAdvisor?->lecturer?->full_name ?? null,
                'academic_advisor_id' => $s->academicAdvisor?->lecturer_id ?? null,
                'academic_advisor_model' => $s->academicAdvisor ? [
                    'id' => $s->academicAdvisor->id,
                    'lecturer_id' => $s->academicAdvisor->lecturer_id,
                    'lecturer' => $s->academicAdvisor->lecturer ? [
                        'id' => $s->academicAdvisor->lecturer->id,
                        'name' => $s->academicAdvisor->lecturer->full_name,
                        'nidn' => $s->academicAdvisor->lecturer->nidn,
                    ] : null,
                ] : null,
            ];
        });

        return $this->successResponse(
            data: $data,
            message: 'Data distribusi pembimbing akademik berhasil dimuat.'
        );
    }

    public function batchAssign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'exists:students,id'],
            'lecturer_id' => ['required', 'integer', 'exists:lecturers,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $count = 0;
        foreach ($validated['student_ids'] as $studentId) {
            AcademicAdvisor::updateOrCreate(
                ['student_id' => $studentId],
                [
                    'lecturer_id' => $validated['lecturer_id'],
                    'status' => 'active',
                    'start_date' => now(),
                    'notes' => $validated['notes'] ?? null,
                ]
            );
            $count++;
        }

        return $this->successResponse(
            data: ['updated_count' => $count],
            message: "Berhasil menetapkan Dosen Wali untuk {$count} mahasiswa."
        );
    }

    public function generate(Request $request): JsonResponse
    {
        $lecturers = \Modules\Lecturer\Models\Lecturer::where('status', 'active')->get();
        if ($lecturers->isEmpty()) {
            return $this->errorResponse('Tidak ada dosen aktif untuk distribusi pembimbing.', 422);
        }

        $unassignedStudents = Student::whereDoesntHave('academicAdvisor', function ($q) {
            $q->where('status', 'active');
        })->get();

        $lecturerIndex = 0;
        $lecturerCount = $lecturers->count();
        $assignedCount = 0;

        foreach ($unassignedStudents as $student) {
            $lecturer = $lecturers[$lecturerIndex % $lecturerCount];
            AcademicAdvisor::updateOrCreate(
                ['student_id' => $student->id],
                [
                    'lecturer_id' => $lecturer->id,
                    'status' => 'active',
                    'start_date' => now(),
                ]
            );
            $lecturerIndex++;
            $assignedCount++;
        }

        return $this->successResponse(
            data: ['assigned_count' => $assignedCount],
            message: "Berhasil mendistribusikan pembimbing akademik untuk {$assignedCount} mahasiswa."
        );
    }
}
