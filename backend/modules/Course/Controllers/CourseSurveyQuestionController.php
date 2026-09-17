<?php

namespace Modules\Course\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Course\Models\CourseSurveyQuestion;
use Modules\Course\Models\CourseSurveyTopic;

class CourseSurveyQuestionController extends Controller
{
    /**
     * Create a new question under a topic.
     */
    public function store(Request $request, CourseSurveyTopic $topic): JsonResponse
    {
        $validated = $request->validate([
            'question' => 'required|string',
            'question_type' => 'required|string|in:yes_no,scale',
            'scale_min' => 'nullable|integer|min:1',
            'scale_max' => 'nullable|integer|max:10',
            'scale_min_label' => 'nullable|string|max:100',
            'scale_max_label' => 'nullable|string|max:100',
            'is_required' => 'nullable|boolean',
            'order_number' => 'nullable|integer',
        ]);

        $maxOrder = CourseSurveyQuestion::where('topic_id', $topic->id)->max('order_number') ?? 0;

        $question = CourseSurveyQuestion::create([
            'topic_id' => $topic->id,
            'question' => $validated['question'],
            'question_type' => $validated['question_type'],
            'scale_min' => $validated['scale_min'] ?? 1,
            'scale_max' => $validated['scale_max'] ?? 5,
            'scale_min_label' => $validated['scale_min_label'] ?? 'Sangat Kurang',
            'scale_max_label' => $validated['scale_max_label'] ?? 'Sangat Baik',
            'is_required' => $validated['is_required'] ?? true,
            'order_number' => $validated['order_number'] ?? ($maxOrder + 1),
        ]);

        return response()->json([
            'success' => true,
            'data' => $question,
            'message' => 'Pertanyaan survey berhasil ditambahkan.',
        ], 201);
    }

    /**
     * Update an existing question.
     */
    public function update(Request $request, CourseSurveyQuestion $question): JsonResponse
    {
        $validated = $request->validate([
            'question' => 'sometimes|required|string',
            'question_type' => 'sometimes|required|string|in:yes_no,scale',
            'scale_min' => 'nullable|integer|min:1',
            'scale_max' => 'nullable|integer|max:10',
            'scale_min_label' => 'nullable|string|max:100',
            'scale_max_label' => 'nullable|string|max:100',
            'is_required' => 'nullable|boolean',
            'order_number' => 'nullable|integer',
        ]);

        $question->update($validated);

        return response()->json([
            'success' => true,
            'data' => $question,
            'message' => 'Pertanyaan survey berhasil diperbarui.',
        ]);
    }

    /**
     * Delete a question.
     */
    public function destroy(CourseSurveyQuestion $question): JsonResponse
    {
        $question->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pertanyaan survey berhasil dihapus.',
        ]);
    }
}
