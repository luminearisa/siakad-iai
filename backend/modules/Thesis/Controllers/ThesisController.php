<?php

namespace Modules\Thesis\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Student\Models\Student;
use Modules\Thesis\Models\Thesis;
use Modules\Thesis\Models\ThesisSupervisor;

class ThesisController extends Controller
{
    use HasApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Thesis::with([
            'student.user',
            'studyProgram',
            'startSemester',
            'completionSemester',
            'supervisors.lecturer.user',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('study_program_id')) {
            $query->where('study_program_id', $request->query('study_program_id'));
        }

        if ($request->filled('semester_id')) {
            $query->where('start_semester_id', $request->query('semester_id'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('title_id', 'like', "%{$search}%")
                  ->orWhere('title_en', 'like', "%{$search}%")
                  ->orWhereHas('student', function ($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%")
                        ->orWhere('nim', 'like', "%{$search}%");
                  });
            });
        }

        $perPage = (int) $request->query('per_page', 10);
        $theses = $query->latest('id')->paginate($perPage);

        // Stats summary
        $stats = [
            'completed' => Thesis::where('status', 'completed')->count(),
            'active' => Thesis::where('status', 'active')->count(),
            'inactive' => Thesis::where('status', 'inactive')->count(),
            'pending_approval' => Thesis::where('status', 'pending_approval')->count(),
        ];

        return $this->successResponse(
            data: $theses->items(),
            message: 'Data tugas akhir berhasil dimuat.',
            meta: [
                'current_page' => $theses->currentPage(),
                'last_page' => $theses->lastPage(),
                'per_page' => $theses->perPage(),
                'total' => $theses->total(),
                'from' => $theses->firstItem() ?? 0,
                'to' => $theses->lastItem() ?? 0,
                'stats' => $stats,
            ]
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'start_semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'start_date' => ['required', 'date'],
            'submission_date' => ['required', 'date'],
            'status' => ['required', 'string', 'in:active,completed,inactive,pending_approval'],
            'title_id' => ['required', 'string'],
            'title_en' => ['nullable', 'string'],
            'topic_id' => ['nullable', 'string'],
            'topic_en' => ['nullable', 'string'],
            'proposal_file_path' => ['nullable', 'string'],
            'supervisor_ids' => ['required', 'array', 'min:1'],
            'supervisor_ids.*' => ['integer', 'exists:lecturers,id'],

            // Optional completion fields
            'completion_date' => ['nullable', 'date'],
            'completion_semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'sk_date' => ['nullable', 'date'],
            'sk_number' => ['nullable', 'string'],
            'final_file_path' => ['nullable', 'string'],
            'final_grade' => ['nullable', 'numeric'],
            'final_grade_letter' => ['nullable', 'string'],
        ]);

        $student = Student::findOrFail($validated['student_id']);

        $thesis = Thesis::create([
            'student_id' => $student->id,
            'study_program_id' => $student->study_program_id,
            'start_semester_id' => $validated['start_semester_id'] ?? null,
            'completion_semester_id' => $validated['completion_semester_id'] ?? null,
            'start_date' => $validated['start_date'],
            'submission_date' => $validated['submission_date'],
            'completion_date' => $validated['completion_date'] ?? null,
            'status' => $validated['status'],
            'title_id' => $validated['title_id'],
            'title_en' => $validated['title_en'] ?? null,
            'topic_id' => $validated['topic_id'] ?? null,
            'topic_en' => $validated['topic_en'] ?? null,
            'proposal_file_path' => $validated['proposal_file_path'] ?? null,
            'final_file_path' => $validated['final_file_path'] ?? null,
            'sk_date' => $validated['sk_date'] ?? null,
            'sk_number' => $validated['sk_number'] ?? null,
            'final_grade' => $validated['final_grade'] ?? null,
            'final_grade_letter' => $validated['final_grade_letter'] ?? null,
        ]);

        // Attach supervisors
        foreach ($validated['supervisor_ids'] as $index => $lecturerId) {
            ThesisSupervisor::create([
                'thesis_id' => $thesis->id,
                'lecturer_id' => $lecturerId,
                'order' => $index + 1,
                'role' => $index === 0 ? 'primary' : 'co_supervisor',
                'status' => 'assigned',
            ]);
        }

        return $this->successResponse(
            data: $thesis->load(['student', 'studyProgram', 'supervisors.lecturer']),
            message: 'Data tugas akhir berhasil ditambahkan.',
            code: 201
        );
    }

    public function show(Thesis $thesis): JsonResponse
    {
        return $this->successResponse(
            data: $thesis->load([
                'student.user',
                'studyProgram',
                'startSemester',
                'completionSemester',
                'supervisors.lecturer.user',
            ]),
            message: 'Detail tugas akhir berhasil dimuat.'
        );
    }

    public function update(Request $request, Thesis $thesis): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'start_semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'start_date' => ['required', 'date'],
            'submission_date' => ['required', 'date'],
            'status' => ['required', 'string', 'in:active,completed,inactive,pending_approval'],
            'title_id' => ['required', 'string'],
            'title_en' => ['nullable', 'string'],
            'topic_id' => ['nullable', 'string'],
            'topic_en' => ['nullable', 'string'],
            'proposal_file_path' => ['nullable', 'string'],
            'supervisor_ids' => ['nullable', 'array'],
            'supervisor_ids.*' => ['integer', 'exists:lecturers,id'],

            // Optional completion fields
            'completion_date' => ['nullable', 'date'],
            'completion_semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'sk_date' => ['nullable', 'date'],
            'sk_number' => ['nullable', 'string'],
            'final_file_path' => ['nullable', 'string'],
            'final_grade' => ['nullable', 'numeric'],
            'final_grade_letter' => ['nullable', 'string'],
        ]);

        $thesis->update($validated);

        if (isset($validated['supervisor_ids'])) {
            $thesis->supervisors()->delete();
            foreach ($validated['supervisor_ids'] as $index => $lecturerId) {
                ThesisSupervisor::create([
                    'thesis_id' => $thesis->id,
                    'lecturer_id' => $lecturerId,
                    'order' => $index + 1,
                    'role' => $index === 0 ? 'primary' : 'co_supervisor',
                    'status' => 'assigned',
                ]);
            }
        }

        return $this->successResponse(
            data: $thesis->load(['student', 'studyProgram', 'supervisors.lecturer']),
            message: 'Data tugas akhir berhasil diperbarui.'
        );
    }

    public function destroy(Thesis $thesis): JsonResponse
    {
        $thesis->delete();

        return $this->successResponse(
            data: null,
            message: 'Data tugas akhir berhasil dihapus.'
        );
    }
}
