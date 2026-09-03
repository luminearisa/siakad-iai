<?php

namespace Modules\Schedule\Controllers;

use App\Http\Controllers\Controller;
use App\Support\QueryFilter;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Audit\Services\AuditService;
use Modules\Schedule\Enums\RoomStatus;
use Modules\Schedule\Models\Room;
use Modules\Schedule\Requests\CreateRoomRequest;
use Modules\Schedule\Requests\UpdateRoomRequest;
use Modules\Schedule\Resources\RoomResource;

class RoomController extends Controller
{
    use HasApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Room::with(['institution', 'buildingModel']);

        $paginator = QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: ['name', 'code', 'building'],
            filterableColumns: ['status', 'room_type', 'building', 'floor'],
            defaultSort: 'code',
            defaultDirection: 'asc'
        );

        return $this->paginatedResponse(
            paginator: $paginator,
            message: 'Rooms retrieved successfully.',
            resourceClass: RoomResource::class
        );
    }

    public function store(CreateRoomRequest $request): JsonResponse
    {
        $room = Room::create($request->validated());

        AuditService::log(
            action: 'created',
            module: 'Room',
            description: "Room {$room->code} - {$room->name} was created.",
            entity: $room,
            oldValues: null,
            newValues: $room->toArray()
        );

        return $this->successResponse(
            data: new RoomResource($room),
            message: 'Room created successfully.',
            code: 201
        );
    }

    public function show(Room $room): JsonResponse
    {
        return $this->successResponse(
            data: new RoomResource($room->load('institution')),
            message: 'Room retrieved successfully.'
        );
    }

    public function update(UpdateRoomRequest $request, Room $room): JsonResponse
    {
        $oldValues = $room->toArray();
        $room->update($request->validated());

        AuditService::log(
            action: 'updated',
            module: 'Room',
            description: "Room {$room->code} was updated.",
            entity: $room,
            oldValues: $oldValues,
            newValues: $room->fresh()->toArray()
        );

        return $this->successResponse(
            data: new RoomResource($room->fresh()),
            message: 'Room updated successfully.'
        );
    }

    public function destroy(Request $request, Room $room): JsonResponse
    {
        if (!$request->user()?->hasPermissionTo('rooms.delete')) {
            return $this->errorResponse('Unauthorized to delete room.', 403);
        }

        $room->delete();

        return $this->successResponse(
            data: null,
            message: 'Room deleted successfully.'
        );
    }

    public function changeStatus(Request $request, Room $room): JsonResponse
    {
        if (!$request->user()?->hasPermissionTo('rooms.change_status')) {
            return $this->errorResponse('Unauthorized to change room status.', 403);
        }

        $request->validate([
            'status' => ['required', 'string', 'in:' . implode(',', RoomStatus::values())],
        ]);

        $oldStatus = $room->status instanceof \BackedEnum ? $room->status->value : $room->status;
        $room->status = RoomStatus::from($request->input('status'));
        $room->save();

        AuditService::log(
            action: 'status_changed',
            module: 'Room',
            description: "Room {$room->code} status changed from {$oldStatus} to {$room->status->value}.",
            entity: $room,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => $room->status->value]
        );

        return $this->successResponse(
            data: new RoomResource($room),
            message: 'Room status updated successfully.'
        );
    }
}
