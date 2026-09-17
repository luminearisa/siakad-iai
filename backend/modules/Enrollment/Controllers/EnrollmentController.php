<?php

namespace Modules\Enrollment\Controllers;

use App\Http\Controllers\Controller;
use App\Support\QueryFilter;
use App\Support\Traits\HasApiResponse;
use App\Support\Traits\ScopesToOwnLecturer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Class\Enums\ClassStatus;
use Modules\Class\Models\AcademicClass;
use Modules\Enrollment\Models\StudentEnrollment;
use Modules\Enrollment\Requests\ApproveEnrollmentRequest;
use Modules\Enrollment\Requests\CreateEnrollmentRequest;
use Modules\Enrollment\Requests\RejectEnrollmentRequest;
use Modules\Enrollment\Requests\RequestRevisionRequest;
use Modules\Enrollment\Resources\AvailableClassResource;
use Modules\Enrollment\Resources\EnrollmentResource;
use Modules\Enrollment\Services\EnrollmentService;
use Modules\Enrollment\Services\EnrollmentValidationService;
use Modules\Settings\Services\SettingService;
use Modules\Student\Enums\StudentStatus;
use Modules\Student\Models\Student;

class EnrollmentController extends Controller
{
    use HasApiResponse, ScopesToOwnLecturer;

    public function __construct(
        protected EnrollmentService $enrollmentService,
        protected SettingService $settingService,
        protected EnrollmentValidationService $validationService,
    ) {}

    /**
     * A plain lecturer (role dosen without enrollment management rights) may only
     * see the KRS of the students they actively advise, and must never be able to
     * edit perwalian data. Staff roles keep full visibility and full access.
     *
     * `enrollments.lock` is used as the "staff" marker: it is granted to
     * admin_akademik/super_admin but not to dosen.
     *
     * @return array{0: bool, 1: int|null} [isLecturerOnly, ownLecturerId]
     */
    protected function lecturerScope(Request $request): array
    {
        return $this->resolveOwnLecturerScope($request, 'enrollments.lock');
    }

    /**
     * Deny a plain lecturer access to an enrollment that is not one of their advisees.
     *
     * @return JsonResponse|null  A 403 response when access must be denied, otherwise null.
     */
    protected function denyUnlessOwnAdvisee(Request $request, StudentEnrollment $enrollment): ?JsonResponse
    {
        [$isLecturerOnly, $ownLecturerId] = $this->lecturerScope($request);

        if (!$isLecturerOnly) {
            return null;
        }

        $advisorLecturerId = $enrollment->student?->academicAdvisor?->lecturer_id;

        if (!$ownLecturerId || $advisorLecturerId !== $ownLecturerId) {
            return $this->errorResponse('Unauthorized to access this enrollment.', 403);
        }

        return null;
    }

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

        // A plain lecturer only sees the KRS of the students they advise.
        [$isLecturerOnly, $ownLecturerId] = $this->lecturerScope($request);
        if ($isLecturerOnly) {
            if ($ownLecturerId) {
                $query->whereHas('student.academicAdvisor', function ($q) use ($ownLecturerId) {
                    $q->where('lecturer_id', $ownLecturerId);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
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
        $data = array_merge($request->validated(), [
            '_actor_role' => $request->user()->hasRole('mahasiswa') ? 'mahasiswa' : 'staff',
        ]);
        $enrollment = $this->enrollmentService->create($data);

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

        if ($denied = $this->denyUnlessOwnAdvisee($request, $enrollment)) {
            return $denied;
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

    /**
     * List the classes that can be picked for this KRS, each annotated with its
     * eligibility so the catalog never offers a class that will be rejected.
     *
     * Students only see classes from their own study program (plus university
     * wide classes); staff see every open class of the semester.
     */
    public function availableClasses(Request $request, StudentEnrollment $enrollment): JsonResponse
    {
        $user = $request->user();
        $isStudent = (bool) $user?->hasRole('mahasiswa');

        if ($isStudent) {
            $ownStudent = $user->student;
            if (!$ownStudent || $enrollment->student_id !== $ownStudent->id) {
                return $this->errorResponse('Unauthorized to view available classes for this enrollment.', 403);
            }
        }

        $enrollment->loadMissing('student');
        $student = $enrollment->student;

        // Staff may opt in to skip the curriculum rule, exactly like the add-item endpoint.
        $bypassCurriculum = !$isStudent && $request->boolean('bypass_curriculum', false);

        $query = AcademicClass::query()
            ->with(['course', 'lecturers', 'schedules.room', 'studyProgram'])
            ->where('semester_id', $enrollment->semester_id)
            ->where('status', ClassStatus::OPEN);

        if ($isStudent && $student?->study_program_id) {
            $query->where(function ($q) use ($student) {
                $q->whereNull('study_program_id')
                  ->orWhere('study_program_id', $student->study_program_id);
            });
        }

        $rows = $query->orderBy('code')->get()->map(function (AcademicClass $class) use ($enrollment, $bypassCurriculum) {
            return [
                'class' => $class,
                'errors' => $this->validationService->checkClassAddition($enrollment, $class, $bypassCurriculum),
            ];
        });

        // Eligible classes first so the catalog leads with what can actually be taken.
        $rows = $rows->sortByDesc(fn (array $row) => empty($row['errors']))->values();

        $items = $rows->map(
            fn (array $row) => (new AvailableClassResource($row['class']))->withEligibility($row['errors'])
        );

        return $this->successResponse(
            data: $items,
            message: 'Available classes retrieved successfully.'
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

        $data = array_merge($request->validated(), [
            'student_id'  => $student->id,
            '_actor_role' => $request->user()->hasRole('mahasiswa') ? 'mahasiswa' : 'staff',
        ]);
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

        // A lecturer may only approve the KRS of their own advisees.
        if ($denied = $this->denyUnlessOwnAdvisee($request, $enrollment)) {
            return $denied;
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

        // A lecturer may only reject the KRS of their own advisees.
        if ($denied = $this->denyUnlessOwnAdvisee($request, $enrollment)) {
            return $denied;
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

        // A lecturer may only request revision on their own advisees' KRS.
        if ($denied = $this->denyUnlessOwnAdvisee($request, $enrollment)) {
            return $denied;
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

        // Bulk perwalian generation is an academic-office operation, not a lecturer one.
        if ($this->lecturerScope($request)[0]) {
            return $this->errorResponse('Dosen tidak diizinkan menjalankan generate perwalian.', 403);
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

        // Ambil batas SKS default dari pengaturan sistem (bukan hardcode)
        $defaultMaxCredits = (int) $this->settingService->get('max_sks', 24);

        // Hanya generate untuk mahasiswa yang berstatus AKTIF
        $students = Student::where('status', StudentStatus::ACTIVE)->get();
        $created  = 0;
        $skipped  = 0;

        foreach ($students as $student) {
            [$enrollment, $wasCreated] = [
                StudentEnrollment::firstOrCreate(
                    [
                        'student_id'  => $student->id,
                        'semester_id' => $semesterId,
                    ],
                    [
                        'status'       => 'draft',
                        'total_credits' => 0,
                        'max_credits'  => $defaultMaxCredits,
                    ]
                ),
                false,
            ];

            // firstOrCreate tidak langsung mengembalikan `wasRecentlyCreated`; cek manual
            if ($enrollment->wasRecentlyCreated) {
                $created++;
            } else {
                $skipped++;
            }
        }

        $total = $students->count();

        return $this->successResponse(
            data: [
                'total_active_students' => $total,
                'created_count'         => $created,
                'skipped_count'         => $skipped,
                'default_max_credits'   => $defaultMaxCredits,
            ],
            message: "Generate perwalian selesai: {$created} enrollment baru dibuat, {$skipped} sudah ada."
        );
    }

    public function updateAdvisorAndQuota(Request $request, StudentEnrollment $enrollment): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->hasRole('mahasiswa')) {
            return $this->errorResponse('Mahasiswa tidak diizinkan mengubah dosen pembimbing atau kuota SKS.', 403);
        }

        // Assigning advisors and raising SKS limits is an academic-office operation.
        if ($this->lecturerScope($request)[0]) {
            return $this->errorResponse('Dosen tidak diizinkan mengubah dosen pembimbing atau kuota SKS.', 403);
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

    /**
     * Load a KRS Package template into an enrollment (partial-load strategy).
     * POST /enrollments/{enrollment}/load-package
     */
    public function loadPackage(Request $request, StudentEnrollment $enrollment): JsonResponse
    {
        $user = $request->user();

        // Mahasiswa hanya bisa load ke KRS miliknya sendiri
        if ($user && $user->hasRole('mahasiswa')) {
            $student = $user->student;
            if (!$student || $enrollment->student_id !== $student->id) {
                return $this->errorResponse('Unauthorized: Anda hanya bisa memuat paket KRS ke KRS milik Anda sendiri.', 403);
            }
        }

        $validated = $request->validate([
            'krs_package_id' => ['required', 'integer', 'exists:krs_packages,id'],
        ]);

        $result = $this->enrollmentService->loadPackage(
            enrollment: $enrollment,
            packageId: $validated['krs_package_id']
        );

        $addedCount  = count($result['added']);
        $failedCount = count($result['failed']);

        $message = $addedCount > 0
            ? "Berhasil menambahkan {$addedCount} mata kuliah dari paket KRS."
            : 'Tidak ada mata kuliah yang dapat ditambahkan dari paket ini.';

        if ($failedCount > 0) {
            $message .= " {$failedCount} mata kuliah tidak dapat ditambahkan (lihat detail).";
        }

        return $this->successResponse(
            data: $result,
            message: $message,
        );
    }
}
