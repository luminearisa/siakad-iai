<?php

namespace Modules\Enrollment\Controllers;

use App\Http\Controllers\Controller;
use App\Support\QueryFilter;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Enrollment\Models\StudentEnrollment;
use Modules\Enrollment\Requests\ApproveEnrollmentRequest;
use Modules\Enrollment\Requests\CreateEnrollmentRequest;
use Modules\Enrollment\Requests\RejectEnrollmentRequest;
use Modules\Enrollment\Requests\RequestRevisionRequest;
use Modules\Enrollment\Resources\EnrollmentResource;
use Modules\Enrollment\Services\EnrollmentService;
use Modules\Student\Models\Student;

class EnrollmentController extends Controller
{
    use HasApiResponse;

    public function __construct(
        protected EnrollmentService $enrollmentService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = StudentEnrollment::with([
            'student.studyProgram',
            'student.academicAdvisor.lecturer',
            'semester.academicYear',
            'approver',
            'items.course',
            'items.academicClass.schedules.room',
            'items.academicClass.lecturers'
        ]);

        // Scope to student's own enrollments if student role
        if ($user && $user->hasRole('mahasiswa')) {
            $student = $user->student;
            if (!$student) {
                return $this->errorResponse('Student profile not found for user.', 404);
            }
            $query->where('student_id', $student->id);
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('notes', 'like', "%{$search}%")
                  ->orWhereHas('student', function ($sq) use ($search) {
                      $sq->where('full_name', 'like', "%{$search}%")
                        ->orWhere('student_number', 'like', "%{$search}%");
                  });
            });
        }

        $paginator = QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: [],
            filterableColumns: ['student_id', 'semester_id', 'status'],
            defaultSort: 'id',
            defaultDirection: 'desc'
        );

        return $this->paginatedResponse(
            paginator: $paginator,
            message: 'Enrollments retrieved successfully.',
            resourceClass: EnrollmentResource::class
        );
    }

    public function store(CreateEnrollmentRequest $request): JsonResponse
    {
        $enrollment = $this->enrollmentService->create($request->validated());

        return $this->successResponse(
            data: new EnrollmentResource($enrollment),
            message: 'Enrollment created successfully.',
            code: 201
        );
    }

    public function show(Request $request, StudentEnrollment $enrollment): JsonResponse
    {
        if (!$enrollment->exists && $request->route('enrollment')) {
            $enrollment = StudentEnrollment::findOrFail($request->route('enrollment'));
        }

        $user = $request->user();
        if ($user && $user->hasRole('mahasiswa')) {
            $student = $user->student;
            if (!$student || $enrollment->student_id !== $student->id) {
                return $this->errorResponse('Unauthorized to access this enrollment.', 403);
            }
        }

        return $this->successResponse(
            data: new EnrollmentResource($enrollment->load([
                'student.studyProgram.faculty',
                'semester.academicYear',
                'approver',
                'items.course',
                'items.academicClass.course',
                'items.academicClass.lecturers',
                'items.academicClass.schedules.room',
            ])),
            message: 'Enrollment retrieved successfully.'
        );
    }

    public function studentEnrollments(Request $request, Student $student): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->hasRole('mahasiswa') && $user->student?->id !== $student->id) {
            return $this->errorResponse('Unauthorized to view other students enrollments.', 403);
        }

        $enrollments = $student->enrollments()
            ->with(['semester.academicYear', 'items.academicClass.course'])
            ->orderBy('id', 'desc')
            ->get();

        return $this->successResponse(
            data: EnrollmentResource::collection($enrollments),
            message: 'Student enrollments retrieved successfully.'
        );
    }

    public function storeStudentEnrollment(CreateEnrollmentRequest $request, Student $student): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->hasRole('mahasiswa') && $user->student?->id !== $student->id) {
            return $this->errorResponse('Unauthorized to create enrollment for other students.', 403);
        }

        $data = array_merge($request->validated(), ['student_id' => $student->id]);
        $enrollment = $this->enrollmentService->create($data);

        return $this->successResponse(
            data: new EnrollmentResource($enrollment),
            message: 'Student enrollment created successfully.',
            code: 201
        );
    }

    public function submit(Request $request, StudentEnrollment $enrollment): JsonResponse
    {
        if (!$enrollment->exists && $request->route('enrollment')) {
            $enrollment = StudentEnrollment::findOrFail($request->route('enrollment'));
        }

        $user = $request->user();
        if ($user && $user->hasRole('mahasiswa') && $user->student?->id !== $enrollment->student_id) {
            return $this->errorResponse('Unauthorized to submit this enrollment.', 403);
        }

        $submitted = $this->enrollmentService->submit($enrollment);

        return $this->successResponse(
            data: new EnrollmentResource($submitted),
            message: 'Enrollment submitted for approval successfully.'
        );
    }

    public function approve(ApproveEnrollmentRequest $request, StudentEnrollment $enrollment): JsonResponse
    {
        if (!$enrollment->exists && $request->route('enrollment')) {
            $enrollment = StudentEnrollment::findOrFail($request->route('enrollment'));
        }

        $approved = $this->enrollmentService->approve(
            enrollment: $enrollment,
            approverUserId: $request->user()->id,
            notes: $request->validated('notes')
        );

        return $this->successResponse(
            data: new EnrollmentResource($approved),
            message: 'Enrollment approved successfully.'
        );
    }

    public function reject(RejectEnrollmentRequest $request, StudentEnrollment $enrollment): JsonResponse
    {
        if (!$enrollment->exists && $request->route('enrollment')) {
            $enrollment = StudentEnrollment::findOrFail($request->route('enrollment'));
        }

        $rejected = $this->enrollmentService->reject(
            enrollment: $enrollment,
            userId: $request->user()->id,
            reason: $request->validated('reason')
        );

        return $this->successResponse(
            data: new EnrollmentResource($rejected),
            message: 'Enrollment rejected successfully.'
        );
    }

    public function requestRevision(RequestRevisionRequest $request, StudentEnrollment $enrollment): JsonResponse
    {
        if (!$enrollment->exists && $request->route('enrollment')) {
            $enrollment = StudentEnrollment::findOrFail($request->route('enrollment'));
        }

        $revised = $this->enrollmentService->requestRevision(
            enrollment: $enrollment,
            userId: $request->user()->id,
            revisionNotes: $request->validated('notes')
        );

        return $this->successResponse(
            data: new EnrollmentResource($revised),
            message: 'Enrollment revision requested successfully.'
        );
    }

    public function lock(Request $request, StudentEnrollment $enrollment): JsonResponse
    {
        if (!$request->user()?->hasPermissionTo('enrollments.lock')) {
            return $this->errorResponse('Unauthorized to lock enrollment.', 403);
        }

        if (!$enrollment->exists && $request->route('enrollment')) {
            $enrollment = StudentEnrollment::findOrFail($request->route('enrollment'));
        }

        $locked = $this->enrollmentService->lock($enrollment, $request->user()->id);

        return $this->successResponse(
            data: new EnrollmentResource($locked),
            message: 'Enrollment locked successfully.'
        );
    }

    public function generate(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->hasRole('mahasiswa')) {
            return $this->errorResponse('Mahasiswa tidak memiliki hak akses untuk generate perwalian.', 403);
        }

        $semesterId = $request->input('semester_id');
        if (!$semesterId) {
            $currentSemester = \Modules\Academic\Models\Semester::where('status', 'active')->first()
                ?? \Modules\Academic\Models\Semester::first();
            $semesterId = $currentSemester?->id;
        }

        if (!$semesterId) {
            return $this->errorResponse('Tidak ada semester aktif untuk generate perwalian.', 422);
        }

        $students = Student::all();
        $count = 0;

        foreach ($students as $student) {
            StudentEnrollment::firstOrCreate(
                [
                    'student_id' => $student->id,
                    'semester_id' => $semesterId,
                ],
                [
                    'status' => 'draft',
                    'total_credits' => 0,
                    'max_credits' => 24,
                ]
            );
            $count++;
        }

        return $this->successResponse(
            data: ['generated_count' => $count],
            message: "Berhasil generate monitoring perwalian untuk {$count} mahasiswa."
        );
    }

    public function updateAdvisorAndQuota(Request $request, StudentEnrollment $enrollment): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->hasRole('mahasiswa')) {
            return $this->errorResponse('Mahasiswa tidak diizinkan mengubah dosen pembimbing atau kuota SKS.', 403);
        }

        $validated = $request->validate([
            'lecturer_id' => ['nullable', 'integer', 'exists:lecturers,id'],
            'max_credits' => ['required', 'integer', 'min:1', 'max:36'],
        ]);

        $enrollment->update([
            'max_credits' => $validated['max_credits'],
        ]);

        if (array_key_exists('lecturer_id', $validated)) {
            if ($validated['lecturer_id']) {
                \Modules\Advising\Models\AcademicAdvisor::updateOrCreate(
                    ['student_id' => $enrollment->student_id],
                    [
                        'lecturer_id' => $validated['lecturer_id'],
                        'status' => 'active',
                        'start_date' => now(),
                    ]
                );
            } else {
                \Modules\Advising\Models\AcademicAdvisor::where('student_id', $enrollment->student_id)
                    ->update(['status' => 'inactive']);
            }
        }

        return $this->successResponse(
            data: new EnrollmentResource($enrollment->load([
                'student.studyProgram',
                'student.academicAdvisor.lecturer',
                'semester.academicYear',
            ])),
            message: 'Data perwalian berhasil diperbarui.'
        );
    }
}
