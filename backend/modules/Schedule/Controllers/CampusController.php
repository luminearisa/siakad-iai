<?php

namespace Modules\Schedule\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Schedule\Models\Campus;

class CampusController extends Controller
{
    use HasApiResponse;

    public function index(Request $request): JsonResponse
    {
        $campuses = Campus::query()
            ->withCount('buildings')
            ->when($request->search, function ($q, $search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            })
            ->orderBy('id', 'asc')
            ->get();

        return $this->successResponse(
            data: $campuses,
            message: 'Campuses retrieved successfully.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:campuses,code'],
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $campus = Campus::create($validated);

        return $this->successResponse(
            data: $campus,
            message: 'Campus created successfully.',
            code: 201
        );
    }

    public function show(Campus $campus): JsonResponse
    {
        return $this->successResponse(
            data: $campus->load('buildings.rooms'),
            message: 'Campus retrieved successfully.'
        );
    }

    public function update(Request $request, Campus $campus): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:campuses,code,' . $campus->id],
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $campus->update($validated);

        return $this->successResponse(
            data: $campus,
            message: 'Campus updated successfully.'
        );
    }

    public function destroy(Campus $campus): JsonResponse
    {
        $campus->delete();

        return $this->successResponse(
            data: null,
            message: 'Campus deleted successfully.'
        );
    }
}
