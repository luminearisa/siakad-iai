<?php

namespace Modules\Enrollment\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Enrollment\Models\StudentEnrollment;
use Modules\Enrollment\Models\StudentEnrollmentItem;
use Modules\Enrollment\Requests\AddEnrollmentItemRequest;
use Modules\Enrollment\Resources\EnrollmentItemResource;
use Modules\Enrollment\Services\EnrollmentService;

class EnrollmentItemController extends Controller
{
    use HasApiResponse;

    public function __construct(
        protected EnrollmentService $enrollmentService
    ) {}

    public function index(Request $request, StudentEnrollment $enrollment): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->hasRole('mahasiswa')) {
            $student = $user->student;
            if (!$student || $enrollment->student_id !== $student->id) {
                return $this->errorResponse('Unauthorized to view items for this enrollment.', 403);
            }
        }

        $items = $enrollment->items()->with([
            'academicClass.course',
            'academicClass.lecturers',
            'academicClass.schedules.room',
            'course',
        ])->get();

        return $this->successResponse(
            data: EnrollmentItemResource::collection($items),
            message: 'Enrollment items retrieved successfully.'
        );
    }

    public function store(AddEnrollmentItemRequest $request, StudentEnrollment $enrollment): JsonResponse
    {
        // bypass_curriculum hanya berlaku untuk admin/dosen, bukan mahasiswa
        $bypassCurriculum = !$request->user()->hasRole('mahasiswa')
            && $request->boolean('bypass_curriculum', false);

        $item = $this->enrollmentService->addItem(
            enrollment: $enrollment,
            classId: $request->validated('class_id'),
            notes: $request->validated('notes'),
            bypassCurriculum: $bypassCurriculum,
        );

        return $this->successResponse(
            data: new EnrollmentItemResource($item),
            message: 'Class added to enrollment (KRS) successfully.',
            code: 201
        );
    }

    public function destroy(Request $request, StudentEnrollment $enrollment, StudentEnrollmentItem $item): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->hasRole('mahasiswa')) {
            $student = $user->student;
            if (!$student || $enrollment->student_id !== $student->id) {
                return $this->errorResponse('Unauthorized to remove item from this enrollment.', 403);
            }
        } elseif (!$user?->hasPermissionTo('enrollments.update')) {
            return $this->errorResponse('Unauthorized to update enrollment.', 403);
        }

        $this->enrollmentService->removeItem($enrollment, $item);

        return $this->successResponse(
            data: null,
            message: 'Class removed from enrollment (KRS) successfully.'
        );
    }
}
