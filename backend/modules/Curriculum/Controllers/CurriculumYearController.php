<?php

namespace Modules\Curriculum\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Curriculum\Models\CurriculumYear;

class CurriculumYearController extends Controller
{
    use HasApiResponse;

    public function index(Request $request): JsonResponse
    {
        $years = CurriculumYear::query()
            ->when($request->search, function ($q, $search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('year', 'like', "%{$search}%");
            })
            ->orderBy('year', 'desc')
            ->get();

        return $this->successResponse(
            data: $years,
            message: 'Curriculum years retrieved successfully.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100', 'unique:curriculum_years,year'],
            'name' => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $year = CurriculumYear::create($validated);

        return $this->successResponse(
            data: $year,
            message: 'Curriculum year created successfully.',
            code: 201
        );
    }

    public function show(CurriculumYear $curriculumYear): JsonResponse
    {
        return $this->successResponse(
            data: $curriculumYear,
            message: 'Curriculum year retrieved successfully.'
        );
    }

    public function update(Request $request, CurriculumYear $curriculumYear): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100', 'unique:curriculum_years,year,' . $curriculumYear->id],
            'name' => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $curriculumYear->update($validated);

        return $this->successResponse(
            data: $curriculumYear,
            message: 'Curriculum year updated successfully.'
        );
    }

    public function destroy(CurriculumYear $curriculumYear): JsonResponse
    {
        $curriculumYear->delete();

        return $this->successResponse(
            data: null,
            message: 'Curriculum year deleted successfully.'
        );
    }
}
