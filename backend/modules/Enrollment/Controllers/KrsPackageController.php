<?php

namespace Modules\Enrollment\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Course\Models\Course;
use Modules\Enrollment\Models\KrsPackage;
use Modules\Enrollment\Models\KrsPackageItem;

class KrsPackageController extends Controller
{
    use HasApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = KrsPackage::with(['studyProgram', 'items.course']);

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhereHas('studyProgram', function ($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('study_program_id')) {
            $query->where('study_program_id', $request->query('study_program_id'));
        }

        if ($request->filled('semester_level')) {
            $query->where('semester_level', $request->query('semester_level'));
        }

        $packages = $query->orderBy('study_program_id')->orderBy('semester_level')->get();

        return $this->successResponse(
            data: $packages,
            message: 'Daftar paket KRS berhasil dimuat.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'study_program_id' => ['required', 'integer', 'exists:study_programs,id'],
            'semester_level'   => ['required', 'integer', 'min:1', 'max:14'],
            'description'      => ['nullable', 'string'],
            'course_ids'       => ['nullable', 'array'],
            'course_ids.*'     => ['integer', 'exists:courses,id'],
        ]);

        $courseIds = $validated['course_ids'] ?? [];
        $courses = Course::whereIn('id', $courseIds)->get();
        $totalCredits = $courses->sum('credits');

        $package = KrsPackage::create([
            'name'             => $validated['name'],
            'study_program_id' => $validated['study_program_id'],
            'semester_level'   => $validated['semester_level'],
            'total_credits'    => $totalCredits,
            'description'      => $validated['description'] ?? null,
        ]);

        foreach ($courses as $course) {
            KrsPackageItem::create([
                'krs_package_id' => $package->id,
                'course_id'      => $course->id,
                'credits'        => $course->credits,
            ]);
        }

        return $this->successResponse(
            data: $package->load(['studyProgram', 'items.course']),
            message: 'Paket KRS berhasil ditambahkan.',
            statusCode: 201
        );
    }

    public function show(KrsPackage $krsPackage): JsonResponse
    {
        return $this->successResponse(
            data: $krsPackage->load(['studyProgram', 'items.course']),
            message: 'Detail paket KRS berhasil dimuat.'
        );
    }

    public function update(Request $request, KrsPackage $krsPackage): JsonResponse
    {
        $validated = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'study_program_id' => ['required', 'integer', 'exists:study_programs,id'],
            'semester_level'   => ['required', 'integer', 'min:1', 'max:14'],
            'description'      => ['nullable', 'string'],
            'course_ids'       => ['nullable', 'array'],
            'course_ids.*'     => ['integer', 'exists:courses,id'],
        ]);

        $courseIds = $validated['course_ids'] ?? [];
        $courses = Course::whereIn('id', $courseIds)->get();
        $totalCredits = $courses->sum('credits');

        $krsPackage->update([
            'name'             => $validated['name'],
            'study_program_id' => $validated['study_program_id'],
            'semester_level'   => $validated['semester_level'],
            'total_credits'    => $totalCredits,
            'description'      => $validated['description'] ?? null,
        ]);

        // Sync items
        $krsPackage->items()->delete();
        foreach ($courses as $course) {
            KrsPackageItem::create([
                'krs_package_id' => $krsPackage->id,
                'course_id'      => $course->id,
                'credits'        => $course->credits,
            ]);
        }

        return $this->successResponse(
            data: $krsPackage->load(['studyProgram', 'items.course']),
            message: 'Paket KRS berhasil diperbarui.'
        );
    }

    public function destroy(KrsPackage $krsPackage): JsonResponse
    {
        $krsPackage->items()->delete();
        $krsPackage->delete();

        return $this->successResponse(
            data: null,
            message: 'Paket KRS berhasil dihapus.'
        );
    }
}
