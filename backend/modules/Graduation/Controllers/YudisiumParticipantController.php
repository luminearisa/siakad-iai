<?php

namespace Modules\Graduation\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Graduation\Models\YudisiumParticipant;

class YudisiumParticipantController extends Controller
{
    use HasApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = YudisiumParticipant::with([
            'student.studyProgram',
            'student.user',
            'period.semester',
            'thesis',
        ])
            ->whereIn('status', ['passed', 'ready', 'submitted'])
            ->orderBy('id', 'desc');

        if ($request->filled('yudisium_period_id')) {
            $query->where('yudisium_period_id', $request->query('yudisium_period_id'));
        }

        if ($request->filled('study_program_id')) {
            $query->whereHas('student', function ($q) use ($request) {
                $q->where('study_program_id', $request->query('study_program_id'));
            });
        }

        if ($request->filled('is_certificate_taken')) {
            $query->where('is_certificate_taken', filter_var($request->query('is_certificate_taken'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $s = $request->query('search');
            $query->whereHas('student', function ($q) use ($s) {
                $q->where('full_name', 'like', "%{$s}%")
                    ->orWhere('student_number', 'like', "%{$s}%");
            });
        }

        $perPage = (int) $request->query('per_page', 10);
        $participants = $query->paginate($perPage);

        return $this->successResponse(
            data: $participants->items(),
            message: 'Daftar peserta yudisium berhasil dimuat.',
            meta: [
                'current_page' => $participants->currentPage(),
                'last_page' => $participants->lastPage(),
                'per_page' => $participants->perPage(),
                'total' => $participants->total(),
            ]
        );
    }

    public function inputSkBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'yudisium_period_id' => ['required', 'integer', 'exists:yudisium_periods,id'],
            'study_program_id' => ['required', 'integer', 'exists:study_programs,id'],
            'sk_number' => ['required', 'string', 'max:255'],
            'sk_date' => ['required', 'date'],
        ]);

        $count = YudisiumParticipant::where('yudisium_period_id', $validated['yudisium_period_id'])
            ->whereHas('student', function ($q) use ($validated) {
                $q->where('study_program_id', $validated['study_program_id']);
            })
            ->update([
                'sk_number' => $validated['sk_number'],
                'sk_date' => $validated['sk_date'],
                'status' => 'passed',
            ]);

        return $this->successResponse(
            data: null,
            message: "SK Yudisium berhasil disimpan untuk {$count} mahasiswa."
        );
    }

    public function toggleCertificate(Request $request, YudisiumParticipant $participant): JsonResponse
    {
        $taken = ! $participant->is_certificate_taken;

        $participant->update([
            'is_certificate_taken' => $taken,
            'certificate_taken_at' => $taken ? now() : null,
            'certificate_taken_by' => $taken ? ($request->input('taken_by') ?? auth()->user()?->name ?? 'Admin') : null,
        ]);

        return $this->successResponse(
            data: $participant->fresh(['student.studyProgram', 'period']),
            message: $taken ? 'Ijazah ditandai sudah diambil.' : 'Ijazah ditandai belum diambil.'
        );
    }

    public function show(YudisiumParticipant $participant): JsonResponse
    {
        return $this->successResponse(
            data: $participant->load([
                'student.studyProgram.faculty',
                'student.user',
                'period.semester',
                'thesis.supervisors.lecturer',
                'documentSubmissions.requirement',
            ]),
            message: 'Detail peserta yudisium berhasil dimuat.'
        );
    }
}
