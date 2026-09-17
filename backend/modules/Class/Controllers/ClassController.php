<?php

namespace Modules\Class\Controllers;

use App\Http\Controllers\Controller;
use App\Support\QueryFilter;
use App\Support\Traits\HasApiResponse;
use App\Support\Traits\ScopesToOwnLecturer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Class\Enums\ClassStatus;
use Modules\Class\Models\AcademicClass;
use Modules\Class\Requests\AssignClassLecturerRequest;
use Modules\Class\Requests\CreateClassRequest;
use Modules\Class\Requests\UpdateClassRequest;
use Modules\Class\Resources\ClassLecturerResource;
use Modules\Class\Resources\ClassResource;
use Modules\Class\Services\ClassService;

class ClassController extends Controller
{
    use HasApiResponse, ScopesToOwnLecturer;

    public function __construct(
        protected ClassService $classService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = AcademicClass::with(['course', 'semester.academicYear', 'studyProgram', 'lecturers']);

        // A lecturer who cannot manage classes only ever sees the classes they
        // actually teach (their own teaching schedule), regardless of filters.
        $scopedToOwnClasses = $this->scopeToOwnLecturer($request, $query, 'classes.create');

        if (!$scopedToOwnClasses && $request->filled('lecturer_id')) {
            $query->whereHas('lecturers', function ($q) use ($request) {
                $q->where('lecturers.id', $request->query('lecturer_id'));
            });
        }

        if ($request->filled('academic_year_id')) {
            $query->whereHas('semester', function ($q) use ($request) {
                $q->where('academic_year_id', $request->query('academic_year_id'));
            });
        }

        if ($request->filled('study_program_id')) {
            $spId = $request->query('study_program_id');
            if ($request->boolean('include_general', true)) {
                $query->where(function ($q) use ($spId) {
                    $q->where('study_program_id', $spId)
                      ->orWhereNull('study_program_id')
                      ->orWhereHas('course', function ($cq) use ($spId) {
                          $cq->where('study_program_id', $spId)
                             ->orWhereNull('study_program_id');
                      });
                });
            } else {
                $query->where('study_program_id', $spId);
            }
        }

        $paginator = QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: ['name', 'code', 'section'],
            filterableColumns: ['semester_id', 'course_id', 'status'],
            defaultSort: 'id',
            defaultDirection: 'desc'
        );

        return $this->paginatedResponse(
            paginator: $paginator,
            message: 'Classes retrieved successfully.',
            resourceClass: ClassResource::class
        );
    }

    public function store(CreateClassRequest $request): JsonResponse
    {
        $class = $this->classService->create($request->validated());

        return $this->successResponse(
            data: new ClassResource($class),
            message: 'Class created successfully.',
            code: 201
        );
    }

    public function show(AcademicClass $class): JsonResponse
    {
        return $this->successResponse(
            data: new ClassResource($class->load([
                'course.prerequisites',
                'semester.academicYear',
                'studyProgram.faculty',
                'lecturers',
                'schedules.room',
            ])),
            message: 'Class retrieved successfully.'
        );
    }

    public function update(UpdateClassRequest $request, AcademicClass $class): JsonResponse
    {
        $updated = $this->classService->update($class, $request->validated());

        return $this->successResponse(
            data: new ClassResource($updated),
            message: 'Class updated successfully.'
        );
    }

    public function destroy(Request $request, AcademicClass $class): JsonResponse
    {
        if (!$request->user()?->hasPermissionTo('classes.delete')) {
            return $this->errorResponse('Unauthorized to delete class.', 403);
        }

        $class->delete();

        return $this->successResponse(
            data: null,
            message: 'Class deleted successfully.'
        );
    }

    public function open(Request $request, AcademicClass $class): JsonResponse
    {
        if (!$request->user()?->hasPermissionTo('classes.open')) {
            return $this->errorResponse('Unauthorized to open class.', 403);
        }

        $updated = $this->classService->changeStatus($class, ClassStatus::OPEN);

        return $this->successResponse(
            data: new ClassResource($updated),
            message: 'Class opened for enrollment successfully.'
        );
    }

    public function close(Request $request, AcademicClass $class): JsonResponse
    {
        if (!$request->user()?->hasPermissionTo('classes.close')) {
            return $this->errorResponse('Unauthorized to close class.', 403);
        }

        $updated = $this->classService->changeStatus($class, ClassStatus::CLOSED);

        return $this->successResponse(
            data: new ClassResource($updated),
            message: 'Class closed for enrollment successfully.'
        );
    }

    public function cancel(Request $request, AcademicClass $class): JsonResponse
    {
        if (!$request->user()?->hasPermissionTo('classes.cancel')) {
            return $this->errorResponse('Unauthorized to cancel class.', 403);
        }

        $updated = $this->classService->changeStatus($class, ClassStatus::CANCELLED);

        return $this->successResponse(
            data: new ClassResource($updated),
            message: 'Class cancelled successfully.'
        );
    }

    public function lecturers(AcademicClass $class): JsonResponse
    {
        $classLecturers = $class->classLecturers()->with('lecturer')->get();

        return $this->successResponse(
            data: ClassLecturerResource::collection($classLecturers),
            message: 'Class lecturers retrieved successfully.'
        );
    }

    public function assignLecturer(AssignClassLecturerRequest $request, AcademicClass $class): JsonResponse
    {
        $classLecturer = $this->classService->assignLecturer(
            class: $class,
            lecturerId: $request->validated('lecturer_id'),
            role: $request->validated('role', 'primary')
        );

        return $this->successResponse(
            data: new ClassLecturerResource($classLecturer),
            message: 'Lecturer assigned to class successfully.',
            code: 201
        );
    }

    public function removeLecturer(Request $request, AcademicClass $class, int $lecturerId): JsonResponse
    {
        if (!$request->user()?->hasPermissionTo('classes.assign_lecturer')) {
            return $this->errorResponse('Unauthorized to remove lecturer from class.', 403);
        }

        $removed = $this->classService->removeLecturer($class, $lecturerId);

        if (!$removed) {
            return $this->errorResponse('Lecturer assignment not found.', 404);
        }

        return $this->successResponse(
            data: null,
            message: 'Lecturer removed from class successfully.'
        );
    }
}
