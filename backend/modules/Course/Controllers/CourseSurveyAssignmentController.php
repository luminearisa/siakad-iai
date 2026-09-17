<?php

namespace Modules\Course\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Course\Models\Course;
use Modules\Course\Models\CourseSurveyTemplate;

class CourseSurveyAssignmentController extends Controller
{
    /**
     * Get survey templates assigned to a course, along with available active templates.
     */
    public function getCourseSurvey(Course $course): JsonResponse
    {
        $assignedTemplates = $course->surveyTemplates()
            ->with(['topics.questions'])
            ->get();

        $availableTemplates = CourseSurveyTemplate::where('is_active', true)
            ->withCount(['topics'])
            ->with(['topics' => function ($q) {
                $q->withCount('questions');
            }])
            ->get()
            ->map(function ($tpl) {
                return [
                    'id' => $tpl->id,
                    'name' => $tpl->name,
                    'description' => $tpl->description,
                    'topics_count' => $tpl->topics_count,
                    'questions_count' => $tpl->topics->sum('questions_count'),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'course' => [
                    'id' => $course->id,
                    'code' => $course->code,
                    'name' => $course->name,
                ],
                'assigned_templates' => $assignedTemplates,
                'available_templates' => $availableTemplates,
            ],
            'message' => 'Data survey mata kuliah berhasil dimuat.',
        ]);
    }

    /**
     * Assign a survey template to a course.
     */
    public function assign(Request $request, Course $course): JsonResponse
    {
        $validated = $request->validate([
            'survey_template_id' => 'required|exists:course_survey_templates,id',
            'replace_existing' => 'nullable|boolean',
        ]);

        $templateId = $validated['survey_template_id'];
        $replaceExisting = $validated['replace_existing'] ?? true;

        if ($replaceExisting) {
            $course->surveyTemplates()->sync([$templateId]);
        } else {
            $course->surveyTemplates()->syncWithoutDetaching([$templateId]);
        }

        $assignedTemplate = CourseSurveyTemplate::with(['topics.questions'])->find($templateId);

        return response()->json([
            'success' => true,
            'data' => $assignedTemplate,
            'message' => "Template survey '{$assignedTemplate->name}' berhasil di-assign ke mata kuliah {$course->name}.",
        ]);
    }

    /**
     * Unassign a survey template from a course.
     */
    public function unassign(Course $course, CourseSurveyTemplate $template): JsonResponse
    {
        $course->surveyTemplates()->detach($template->id);

        return response()->json([
            'success' => true,
            'message' => "Template survey berhasil dilepas dari mata kuliah {$course->name}.",
        ]);
    }
}
