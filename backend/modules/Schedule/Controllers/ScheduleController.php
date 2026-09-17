<?php

namespace Modules\Schedule\Controllers;

use App\Http\Controllers\Controller;
use App\Support\QueryFilter;
use App\Support\Traits\HasApiResponse;
use App\Support\Traits\ScopesToOwnLecturer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Audit\Services\AuditService;
use Modules\Class\Models\AcademicClass;
use Modules\Schedule\Models\ClassSchedule;
use Modules\Schedule\Requests\CreateScheduleRequest;
use Modules\Schedule\Requests\UpdateScheduleRequest;
use Modules\Schedule\Resources\ScheduleResource;
use Modules\Schedule\Services\ScheduleService;

class ScheduleController extends Controller
{
    use HasApiResponse, ScopesToOwnLecturer;

    public function __construct(
        protected ScheduleService $scheduleService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = ClassSchedule::with(['academicClass.course', 'academicClass.lecturers', 'room']);

        // A lecturer who cannot manage schedules only sees their own teaching
        // schedule, regardless of the filters they send.
        $scopedToOwnSchedule = $this->scopeToOwnLecturer(
            $request,
            $query,
            'schedules.create',
            'academicClass.lecturers'
        );

        if (!$scopedToOwnSchedule && $request->filled('lecturer_id')) {
            $query->whereHas('academicClass.lecturers', function ($q) use ($request) {
                $q->where('lecturers.id', $request->query('lecturer_id'));
            });
        }

        if ($request->filled('semester_id')) {
            $query->whereHas('academicClass', function ($q) use ($request) {
                $q->where('semester_id', $request->query('semester_id'));
            });
        }

        $paginator = QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: ['notes'],
            filterableColumns: ['class_id', 'room_id', 'day_of_week', 'status'],
            defaultSort: 'day_of_week',
            defaultDirection: 'asc'
        );

        return $this->paginatedResponse(
            paginator: $paginator,
            message: 'Schedules retrieved successfully.',
            resourceClass: ScheduleResource::class
        );
    }

    public function store(CreateScheduleRequest $request): JsonResponse
    {
        $schedule = $this->scheduleService->create($request->validated());

        return $this->successResponse(
            data: new ScheduleResource($schedule),
            message: 'Schedule created successfully.',
            code: 201
        );
    }

    public function show(ClassSchedule $schedule): JsonResponse
    {
        return $this->successResponse(
            data: new ScheduleResource($schedule->load(['academicClass.course', 'academicClass.lecturers', 'room'])),
            message: 'Schedule retrieved successfully.'
        );
    }

    public function update(UpdateScheduleRequest $request, ClassSchedule $schedule): JsonResponse
    {
        $updated = $this->scheduleService->update($schedule, $request->validated());

        return $this->successResponse(
            data: new ScheduleResource($updated),
            message: 'Schedule updated successfully.'
        );
    }

    public function destroy(Request $request, ClassSchedule $schedule): JsonResponse
    {
        if (!$request->user()?->hasPermissionTo('schedules.delete')) {
            return $this->errorResponse('Unauthorized to delete schedule.', 403);
        }

        $oldValues = $schedule->toArray();
        $schedule->delete();

        AuditService::log(
            action: 'deleted',
            module: 'Schedule',
            description: "Schedule #{$schedule->id} was deleted.",
            entity: null,
            oldValues: $oldValues,
            newValues: null
        );

        return $this->successResponse(
            data: null,
            message: 'Schedule deleted successfully.'
        );
    }

    public function classSchedules(AcademicClass $class): JsonResponse
    {
        $schedules = $class->schedules()->with('room')->get();

        return $this->successResponse(
            data: ScheduleResource::collection($schedules),
            message: 'Class schedules retrieved successfully.'
        );
    }

    public function storeClassSchedule(CreateScheduleRequest $request, AcademicClass $class): JsonResponse
    {
        $data = array_merge($request->validated(), ['class_id' => $class->id]);
        $schedule = $this->scheduleService->create($data);

        return $this->successResponse(
            data: new ScheduleResource($schedule),
            message: 'Schedule added to class successfully.',
            code: 201
        );
    }
}
