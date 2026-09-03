<?php

namespace Modules\Schedule\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Schedule\Models\Building;

class BuildingController extends Controller
{
    use HasApiResponse;

    public function index(Request $request): JsonResponse
    {
        $buildings = Building::query()
            ->with(['campus'])
            ->withCount('rooms')
            ->when($request->search, function ($q, $search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            })
            ->when($request->campus_id, function ($q, $campusId) {
                $q->where('campus_id', $campusId);
            })
            ->orderBy('id', 'asc')
            ->get();

        return $this->successResponse(
            data: $buildings,
            message: 'Buildings retrieved successfully.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'campus_id' => ['nullable', 'integer', 'exists:campuses,id'],
            'code' => ['required', 'string', 'max:50', 'unique:buildings,code'],
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string'],
            'total_floors' => ['nullable', 'integer', 'min:1'],
            'total_rooms' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $building = Building::create($validated);

        return $this->successResponse(
            data: $building->load('campus'),
            message: 'Building created successfully.',
            code: 201
        );
    }

    public function show(Building $building): JsonResponse
    {
        return $this->successResponse(
            data: $building->load(['campus', 'rooms']),
            message: 'Building retrieved successfully.'
        );
    }

    public function update(Request $request, Building $building): JsonResponse
    {
        $validated = $request->validate([
            'campus_id' => ['nullable', 'integer', 'exists:campuses,id'],
            'code' => ['required', 'string', 'max:50', 'unique:buildings,code,' . $building->id],
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string'],
            'total_floors' => ['nullable', 'integer', 'min:1'],
            'total_rooms' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $building->update($validated);

        return $this->successResponse(
            data: $building->load('campus'),
            message: 'Building updated successfully.'
        );
    }

    public function destroy(Building $building): JsonResponse
    {
        $building->delete();

        return $this->successResponse(
            data: null,
            message: 'Building deleted successfully.'
        );
    }
}
