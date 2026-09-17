<?php

namespace Modules\Course\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Course\Models\CourseSurveyTemplate;
use Modules\Course\Models\CourseSurveyTopic;

class CourseSurveyTopicController extends Controller
{
    /**
     * Create a new topic (judul) for a survey template.
     */
    public function store(Request $request, CourseSurveyTemplate $template): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'order_number' => 'nullable|integer',
        ]);

        $maxOrder = CourseSurveyTopic::where('survey_template_id', $template->id)->max('order_number') ?? 0;

        $topic = CourseSurveyTopic::create([
            'survey_template_id' => $template->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'order_number' => $validated['order_number'] ?? ($maxOrder + 1),
        ]);

        return response()->json([
            'success' => true,
            'data' => $topic->load('questions'),
            'message' => 'Judul topik survey berhasil ditambahkan.',
        ], 201);
    }

    /**
     * Update an existing topic (judul).
     */
    public function update(Request $request, CourseSurveyTopic $topic): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'order_number' => 'nullable|integer',
        ]);

        $topic->update($validated);

        return response()->json([
            'success' => true,
            'data' => $topic->load('questions'),
            'message' => 'Judul topik survey berhasil diperbarui.',
        ]);
    }

    /**
     * Delete a topic (judul) and its questions.
     */
    public function destroy(CourseSurveyTopic $topic): JsonResponse
    {
        $topic->delete();

        return response()->json([
            'success' => true,
            'message' => 'Judul topik beserta pertanyaan di dalamnya berhasil dihapus.',
        ]);
    }
}
