<?php

namespace Modules\Graduation\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Graduation\Models\YudisiumRequirement;

class YudisiumRequirementController extends Controller
{
    use HasApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = YudisiumRequirement::with('studyProgram')->orderBy('id', 'asc');

        if ($request->filled('study_program_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('study_program_id', $request->query('study_program_id'))
                    ->orWhereNull('study_program_id');
            });
        }

        if ($request->filled('search')) {
            $s = $request->query('search');
            $query->where('name', 'like', "%{$s}%");
        }

        $requirements = $query->get();

        return $this->successResponse(
            data: $requirements,
            message: 'Daftar syarat yudisium berhasil dimuat.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'study_program_id' => ['nullable', 'integer', 'exists:study_programs,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'is_document' => ['boolean'],
            'is_mandatory' => ['boolean'],
            'min_credits' => ['integer', 'min:0'],
            'min_gpa' => ['numeric', 'min:0', 'max:4'],
        ]);

        $requirement = YudisiumRequirement::create($validated);

        return $this->successResponse(
            data: $requirement->load('studyProgram'),
            message: 'Syarat yudisium berhasil ditambahkan.',
            code: 201
        );
    }

    public function update(Request $request, YudisiumRequirement $requirement): JsonResponse
    {
        $validated = $request->validate([
            'study_program_id' => ['nullable', 'integer', 'exists:study_programs,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'is_document' => ['boolean'],
            'is_mandatory' => ['boolean'],
            'min_credits' => ['integer', 'min:0'],
            'min_gpa' => ['numeric', 'min:0', 'max:4'],
        ]);

        $requirement->update($validated);

        return $this->successResponse(
            data: $requirement->load('studyProgram'),
            message: 'Syarat yudisium berhasil diperbarui.'
        );
    }

    public function destroy(YudisiumRequirement $requirement): JsonResponse
    {
        $requirement->delete();

        return $this->successResponse(
            data: null,
            message: 'Syarat yudisium berhasil dihapus.'
        );
    }
}
