<?php

namespace Modules\Curriculum\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Curriculum\Models\GraduateProfile;

class GraduateProfileController extends Controller
{
    use HasApiResponse;

    public function index(Request $request): JsonResponse
    {
        $profiles = GraduateProfile::with('studyProgram')
            ->when($request->search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('profession', 'like', "%{$search}%");
                });
            })
            ->when($request->study_program_id, function ($q, $prodiId) {
                $q->where('study_program_id', $prodiId);
            })
            ->orderBy('id', 'asc')
            ->get();

        return $this->successResponse(
            data: $profiles,
            message: 'Graduate profiles retrieved successfully.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'study_program_id' => ['nullable', 'integer', 'exists:study_programs,id'],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'profession' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $profile = GraduateProfile::create($validated);

        return $this->successResponse(
            data: $profile->load('studyProgram'),
            message: 'Graduate profile created successfully.',
            code: 201
        );
    }

    public function show(GraduateProfile $graduateProfile): JsonResponse
    {
        return $this->successResponse(
            data: $graduateProfile->load('studyProgram'),
            message: 'Graduate profile retrieved successfully.'
        );
    }

    public function update(Request $request, GraduateProfile $graduateProfile): JsonResponse
    {
        $validated = $request->validate([
            'study_program_id' => ['nullable', 'integer', 'exists:study_programs,id'],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'profession' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $graduateProfile->update($validated);

        return $this->successResponse(
            data: $graduateProfile->load('studyProgram'),
            message: 'Graduate profile updated successfully.'
        );
    }

    public function destroy(GraduateProfile $graduateProfile): JsonResponse
    {
        $graduateProfile->delete();

        return $this->successResponse(
            data: null,
            message: 'Graduate profile deleted successfully.'
        );
    }
}
