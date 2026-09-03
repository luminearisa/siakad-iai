<?php

namespace Modules\Course\Controllers;

use App\Http\Controllers\Controller;
use App\Support\QueryFilter;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Course\Models\Course;
use Modules\Course\Requests\CreateCourseRequest;
use Modules\Course\Requests\SetCoursePrerequisitesRequest;
use Modules\Course\Requests\UpdateCourseRequest;
use Modules\Course\Resources\CourseResource;
use Modules\Course\Services\CourseService;

class CourseController extends Controller
{
    use HasApiResponse;

    public function __construct(
        protected CourseService $courseService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Course::with(['prerequisites', 'dependents', 'studyProgram', 'courseType', 'courseGroup']);

        $paginator = QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: ['name', 'code', 'short_name'],
            filterableColumns: ['status', 'type', 'category', 'credits', 'study_program_id', 'course_type_id', 'course_group_id'],
            defaultSort: 'code',
            defaultDirection: 'asc'
        );

        return $this->paginatedResponse(
            paginator: $paginator,
            message: 'Courses retrieved successfully.',
            resourceClass: CourseResource::class
        );
    }

    public function store(CreateCourseRequest $request): JsonResponse
    {
        $course = $this->courseService->create($request->validated());

        return $this->successResponse(
            data: new CourseResource($course->load(['studyProgram', 'courseType', 'courseGroup'])),
            message: 'Course created successfully.',
            code: 201
        );
    }

    public function show(Course $course): JsonResponse
    {
        return $this->successResponse(
            data: new CourseResource($course->load(['prerequisites', 'dependents', 'studyProgram', 'courseType', 'courseGroup'])),
            message: 'Course retrieved successfully.'
        );
    }

    public function update(UpdateCourseRequest $request, Course $course): JsonResponse
    {
        $updatedCourse = $this->courseService->update($course, $request->validated());

        return $this->successResponse(
            data: new CourseResource($updatedCourse),
            message: 'Course updated successfully.'
        );
    }

    public function destroy(Request $request, Course $course): JsonResponse
    {
        if (!$request->user()?->hasPermissionTo('courses.delete')) {
            return $this->errorResponse('Unauthorized to delete course.', 403);
        }

        $course->delete();

        return $this->successResponse(
            data: null,
            message: 'Course deleted successfully.'
        );
    }

    public function prerequisites(Course $course): JsonResponse
    {
        return $this->successResponse(
            data: CourseResource::collection($course->prerequisites),
            message: 'Course prerequisites retrieved successfully.'
        );
    }

    public function setPrerequisites(SetCoursePrerequisitesRequest $request, Course $course): JsonResponse
    {
        $updated = $this->courseService->setPrerequisites($course, $request->validated('prerequisites'));

        return $this->successResponse(
            data: new CourseResource($updated),
            message: 'Course prerequisites updated successfully.'
        );
    }
}
