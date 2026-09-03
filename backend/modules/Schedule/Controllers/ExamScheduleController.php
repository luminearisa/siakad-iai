<?php

namespace Modules\Schedule\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Class\Models\AcademicClass;
use Modules\Schedule\Models\ExamSchedule;

class ExamScheduleController extends Controller
{
    use HasApiResponse;

    public function index(Request $request): JsonResponse
    {
        $examType = $request->query('exam_type', 'uts');

        $query = AcademicClass::with([
            'course',
            'studyProgram',
            'semester',
            'lecturers',
            'enrollmentItems',
        ]);

        if ($request->filled('study_program_id')) {
            $query->where('study_program_id', $request->query('study_program_id'));
        }

        if ($request->filled('semester_id')) {
            $query->where('semester_id', $request->query('semester_id'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhereHas('course', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                  })
                  ->orWhereHas('studyProgram', function ($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $classes = $query->orderBy('code', 'asc')->get();

        $examSchedules = ExamSchedule::with(['room', 'proctor'])
            ->where('exam_type', $examType)
            ->whereIn('academic_class_id', $classes->pluck('id'))
            ->get()
            ->keyBy('academic_class_id');

        $data = $classes->map(function ($class) use ($examSchedules, $examType) {
            $exam = $examSchedules->get($class->id);

            return [
                'class_id' => $class->id,
                'class_code' => $class->code,
                'class_name' => $class->name,
                'curriculum_year' => $class->course?->curriculum_year ?? 2026,
                'study_program' => $class->studyProgram ? [
                    'id' => $class->studyProgram->id,
                    'name' => $class->studyProgram->name,
                    'degree' => $class->studyProgram->degree,
                ] : null,
                'course' => $class->course ? [
                    'id' => $class->course->id,
                    'code' => $class->course->code,
                    'name' => $class->course->name,
                    'credits' => $class->course->credits,
                ] : null,
                'student_count' => $class->enrolled_count ?? $class->enrollmentItems->count(),
                'exam_schedule' => $exam ? [
                    'id' => $exam->id,
                    'exam_type' => $exam->exam_type,
                    'exam_date' => $exam->exam_date ? $exam->exam_date->format('Y-m-d') : null,
                    'start_time' => $exam->start_time ? substr($exam->start_time, 0, 5) : null,
                    'end_time' => $exam->end_time ? substr($exam->end_time, 0, 5) : null,
                    'room_id' => $exam->room_id,
                    'room_name' => $exam->room?->name ?? $exam->room?->code,
                    'proctor_lecturer_id' => $exam->proctor_lecturer_id,
                    'proctor_name' => $exam->proctor?->full_name,
                    'is_announced' => (bool) $exam->is_announced,
                    'announced_at' => $exam->announced_at?->toISOString(),
                    'notes' => $exam->notes,
                ] : null,
            ];
        });

        return $this->successResponse(
            data: $data,
            message: 'Jadwal ujian berhasil dimuat.'
        );
    }

    public function updateSchedule(Request $request, AcademicClass $class): JsonResponse
    {
        $validated = $request->validate([
            'exam_type' => ['required', 'string', 'in:uts,uas'],
            'exam_date' => ['required', 'date'],
            'start_time' => ['required', 'string'],
            'end_time' => ['required', 'string'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $exam = ExamSchedule::updateOrCreate(
            [
                'academic_class_id' => $class->id,
                'exam_type' => $validated['exam_type'],
            ],
            [
                'exam_date' => $validated['exam_date'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'room_id' => $validated['room_id'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]
        );

        return $this->successResponse(
            data: $exam->load(['room', 'proctor']),
            message: 'Jadwal ujian berhasil disimpan.'
        );
    }

    public function updateProctor(Request $request, AcademicClass $class): JsonResponse
    {
        $validated = $request->validate([
            'exam_type' => ['required', 'string', 'in:uts,uas'],
            'proctor_lecturer_id' => ['required', 'integer', 'exists:lecturers,id'],
        ]);

        $exam = ExamSchedule::updateOrCreate(
            [
                'academic_class_id' => $class->id,
                'exam_type' => $validated['exam_type'],
            ],
            [
                'proctor_lecturer_id' => $validated['proctor_lecturer_id'],
            ]
        );

        return $this->successResponse(
            data: $exam->load(['room', 'proctor']),
            message: 'Pengawas ujian berhasil ditetapkan.'
        );
    }

    public function announce(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'exam_type' => ['required', 'string', 'in:uts,uas'],
        ]);

        $count = ExamSchedule::where('exam_type', $validated['exam_type'])
            ->whereNotNull('exam_date')
            ->update([
                'is_announced' => true,
                'announced_at' => now(),
            ]);

        $typeLabel = strtoupper($validated['exam_type']);

        return $this->successResponse(
            data: ['count' => $count],
            message: "Jadwal {$typeLabel} berhasil diumumkan ke mahasiswa dan dosen."
        );
    }
}
