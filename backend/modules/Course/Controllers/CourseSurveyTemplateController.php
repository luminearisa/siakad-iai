<?php

namespace Modules\Course\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Course\Models\Course;
use Modules\Course\Models\CourseSurveyTemplate;

class CourseSurveyTemplateController extends Controller
{
    /**
     * List all survey templates with aggregated counts.
     */
    public function index(Request $request): JsonResponse
    {
        $query = CourseSurveyTemplate::query()
            ->withCount(['topics', 'courses'])
            ->with(['topics' => function ($q) {
                $q->withCount('questions');
            }]);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
        }

        if ($request->has('is_active') && $request->input('is_active') !== null && $request->input('is_active') !== '') {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $templates = $query->latest()->get();

        // Calculate total questions across all topics for each template
        $data = $templates->map(function ($tpl) {
            $totalQuestions = $tpl->topics->sum('questions_count');
            return [
                'id' => $tpl->id,
                'name' => $tpl->name,
                'description' => $tpl->description,
                'is_active' => $tpl->is_active,
                'topics_count' => $tpl->topics_count,
                'questions_count' => $totalQuestions,
                'courses_count' => $tpl->courses_count,
                'created_at' => $tpl->created_at,
                'updated_at' => $tpl->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Daftar template survey berhasil dimuat.',
        ]);
    }

    /**
     * Create a new survey template.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $template = CourseSurveyTemplate::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'data' => $template,
            'message' => 'Template survey berhasil dibuat.',
        ], 201);
    }

    /**
     * Show template detail with topics (judul), questions, and assigned courses.
     */
    public function show(CourseSurveyTemplate $template): JsonResponse
    {
        $template->load([
            'topics.questions',
            'courses' => function ($q) {
                $q->with('studyProgram:id,name,code')->select('courses.id', 'courses.study_program_id', 'courses.code', 'courses.name', 'courses.credits', 'courses.type');
            }
        ]);

        return response()->json([
            'success' => true,
            'data' => $template,
            'message' => 'Detail template survey berhasil dimuat.',
        ]);
    }

    /**
     * Update an existing survey template.
     */
    public function update(Request $request, CourseSurveyTemplate $template): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $template->update($validated);

        return response()->json([
            'success' => true,
            'data' => $template,
            'message' => 'Template survey berhasil diperbarui.',
        ]);
    }

    /**
     * Delete a survey template.
     */
    public function destroy(CourseSurveyTemplate $template): JsonResponse
    {
        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Template survey berhasil dihapus.',
        ]);
    }

    /**
     * Bulk assign courses to this template.
     */
    public function assignCourses(Request $request, CourseSurveyTemplate $template): JsonResponse
    {
        $validated = $request->validate([
            'course_ids' => 'required|array',
            'course_ids.*' => 'exists:courses,id',
        ]);

        // Attach without detaching existing ones
        $template->courses()->syncWithoutDetaching($validated['course_ids']);

        return response()->json([
            'success' => true,
            'message' => count($validated['course_ids']) . ' mata kuliah berhasil di-assign ke template survey ini.',
        ]);
    }

    /**
     * Unassign a course from this template.
     */
    public function unassignCourse(CourseSurveyTemplate $template, Course $course): JsonResponse
    {
        $template->courses()->detach($course->id);

        return response()->json([
            'success' => true,
            'message' => "Mata kuliah {$course->name} berhasil dilepas dari template survey.",
        ]);
    }
}
