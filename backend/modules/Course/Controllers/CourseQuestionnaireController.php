<?php

namespace Modules\Course\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Course\Models\Course;
use Modules\Course\Models\CourseQuestionnaireQuestion;
use Modules\Course\Models\CourseQuestionnaireTopic;

class CourseQuestionnaireController extends Controller
{
    /**
     * Get all questionnaire topics and questions for a course.
     * Auto-seeds standard EDOM template if empty.
     */
    public function index(Course $course): JsonResponse
    {
        $topics = CourseQuestionnaireTopic::with('questions')
            ->where('course_id', $course->id)
            ->orderBy('order_number')
            ->get();

        if ($topics->isEmpty()) {
            $this->seedDefaultTemplate($course);
            $topics = CourseQuestionnaireTopic::with('questions')
                ->where('course_id', $course->id)
                ->orderBy('order_number')
                ->get();
        }

        return response()->json([
            'success' => true,
            'data' => $topics,
            'message' => 'Data kuisioner evaluasi mata kuliah berhasil dimuat.',
        ]);
    }

    /**
     * Create a new topic for a course.
     */
    public function storeTopic(Request $request, Course $course): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'order_number' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $maxOrder = CourseQuestionnaireTopic::where('course_id', $course->id)->max('order_number') ?? 0;

        $topic = CourseQuestionnaireTopic::create([
            'course_id' => $course->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'order_number' => $validated['order_number'] ?? ($maxOrder + 1),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'data' => $topic->load('questions'),
            'message' => 'Topik kuisioner berhasil ditambahkan.',
        ], 201);
    }

    /**
     * Update an existing topic.
     */
    public function updateTopic(Request $request, CourseQuestionnaireTopic $topic): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'order_number' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $topic->update($validated);

        return response()->json([
            'success' => true,
            'data' => $topic->load('questions'),
            'message' => 'Topik kuisioner berhasil diperbarui.',
        ]);
    }

    /**
     * Delete a topic and its questions.
     */
    public function destroyTopic(CourseQuestionnaireTopic $topic): JsonResponse
    {
        $topic->delete();

        return response()->json([
            'success' => true,
            'message' => 'Topik kuisioner beserta pertanyaan di dalamnya berhasil dihapus.',
        ]);
    }

    /**
     * Add a question to a topic.
     */
    public function storeQuestion(Request $request, CourseQuestionnaireTopic $topic): JsonResponse
    {
        $validated = $request->validate([
            'question' => 'required|string',
            'question_type' => 'nullable|string|in:likert,multiple_choice,essay',
            'scale_min' => 'nullable|integer|min:1',
            'scale_max' => 'nullable|integer|max:10',
            'scale_min_label' => 'nullable|string|max:100',
            'scale_max_label' => 'nullable|string|max:100',
            'options' => 'nullable|array',
            'is_required' => 'nullable|boolean',
            'order_number' => 'nullable|integer',
        ]);

        $maxOrder = CourseQuestionnaireQuestion::where('topic_id', $topic->id)->max('order_number') ?? 0;

        $question = CourseQuestionnaireQuestion::create([
            'topic_id' => $topic->id,
            'question' => $validated['question'],
            'question_type' => $validated['question_type'] ?? 'likert',
            'scale_min' => $validated['scale_min'] ?? 1,
            'scale_max' => $validated['scale_max'] ?? 5,
            'scale_min_label' => $validated['scale_min_label'] ?? 'Sangat Kurang',
            'scale_max_label' => $validated['scale_max_label'] ?? 'Sangat Baik',
            'options' => $validated['options'] ?? null,
            'is_required' => $validated['is_required'] ?? true,
            'order_number' => $validated['order_number'] ?? ($maxOrder + 1),
        ]);

        return response()->json([
            'success' => true,
            'data' => $question,
            'message' => 'Pertanyaan kuisioner berhasil ditambahkan.',
        ], 201);
    }

    /**
     * Update an existing question.
     */
    public function updateQuestion(Request $request, CourseQuestionnaireQuestion $question): JsonResponse
    {
        $validated = $request->validate([
            'question' => 'sometimes|required|string',
            'question_type' => 'nullable|string|in:likert,multiple_choice,essay',
            'scale_min' => 'nullable|integer|min:1',
            'scale_max' => 'nullable|integer|max:10',
            'scale_min_label' => 'nullable|string|max:100',
            'scale_max_label' => 'nullable|string|max:100',
            'options' => 'nullable|array',
            'is_required' => 'nullable|boolean',
            'order_number' => 'nullable|integer',
        ]);

        $question->update($validated);

        return response()->json([
            'success' => true,
            'data' => $question,
            'message' => 'Pertanyaan kuisioner berhasil diperbarui.',
        ]);
    }

    /**
     * Delete a question.
     */
    public function destroyQuestion(CourseQuestionnaireQuestion $question): JsonResponse
    {
        $question->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pertanyaan kuisioner berhasil dihapus.',
        ]);
    }

    /**
     * Reset / seed standard EDOM template topics and questions for this course.
     */
    public function resetTemplate(Course $course): JsonResponse
    {
        // Delete existing topics and questions for this course
        $existingTopics = CourseQuestionnaireTopic::where('course_id', $course->id)->get();
        foreach ($existingTopics as $t) {
            $t->delete();
        }

        $this->seedDefaultTemplate($course);

        $topics = CourseQuestionnaireTopic::with('questions')
            ->where('course_id', $course->id)
            ->orderBy('order_number')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $topics,
            'message' => 'Template standar kuisioner EDOM berhasil digenerate.',
        ]);
    }

    /**
     * Helper to seed standard EDOM topics & questions.
     */
    protected function seedDefaultTemplate(Course $course): void
    {
        $templates = [
            [
                'title' => 'Kompetensi Pedagogik & Penguasaan Materi',
                'description' => 'Evaluasi kejelasan penjelasan konsep, penguasaan silabus, dan relevansi referensi perkuliahan.',
                'order_number' => 1,
                'questions' => [
                    [
                        'question' => 'Dosen menguasai materi perkuliahan dengan baik dan mampu menjelaskannya secara sistematis.',
                        'question_type' => 'likert',
                        'scale_min' => 1,
                        'scale_max' => 5,
                        'scale_min_label' => 'Sangat Kurang',
                        'scale_max_label' => 'Sangat Baik',
                        'is_required' => true,
                        'order_number' => 1,
                    ],
                    [
                        'question' => 'Dosen menyampaikan silabus, rencana perkuliahan (RPS), dan kriteria penilaian di awal semester.',
                        'question_type' => 'likert',
                        'scale_min' => 1,
                        'scale_max' => 5,
                        'scale_min_label' => 'Sangat Kurang',
                        'scale_max_label' => 'Sangat Baik',
                        'is_required' => true,
                        'order_number' => 2,
                    ],
                    [
                        'question' => 'Materi dan bahan ajar yang diberikan up-to-date dan relevan dengan capaian pembelajaran.',
                        'question_type' => 'likert',
                        'scale_min' => 1,
                        'scale_max' => 5,
                        'scale_min_label' => 'Sangat Kurang',
                        'scale_max_label' => 'Sangat Baik',
                        'is_required' => true,
                        'order_number' => 3,
                    ],
                ],
            ],
            [
                'title' => 'Interaksi Pembelajaran & Suasana Kelas',
                'description' => 'Evaluasi interaktivitas dosen, stimulasi diskusi kritis mahasiswa, dan penggunaan media pembelajaran.',
                'order_number' => 2,
                'questions' => [
                    [
                        'question' => 'Dosen mendorong partisipasi aktif dan memberi kesempatan mahasiswa untuk bertanya/berdiskusi.',
                        'question_type' => 'likert',
                        'scale_min' => 1,
                        'scale_max' => 5,
                        'scale_min_label' => 'Sangat Kurang',
                        'scale_max_label' => 'Sangat Baik',
                        'is_required' => true,
                        'order_number' => 1,
                    ],
                    [
                        'question' => 'Dosen memanfaatkan media atau platform pembelajaran (e-learning/LMS) secara optimal.',
                        'question_type' => 'likert',
                        'scale_min' => 1,
                        'scale_max' => 5,
                        'scale_min_label' => 'Sangat Kurang',
                        'scale_max_label' => 'Sangat Baik',
                        'is_required' => true,
                        'order_number' => 2,
                    ],
                ],
            ],
            [
                'title' => 'Kedisiplinan & Ketepatan Waktu Perkuliahan',
                'description' => 'Evaluasi kehadiran, ketepatan waktu memulai/mengakhiri sesi kuliah, dan pemenuhan jumlah pertemuan.',
                'order_number' => 3,
                'questions' => [
                    [
                        'question' => 'Dosen hadir tepat waktu sesuai jadwal perkuliahan yang telah ditetapkan.',
                        'question_type' => 'likert',
                        'scale_min' => 1,
                        'scale_max' => 5,
                        'scale_min_label' => 'Sangat Kurang',
                        'scale_max_label' => 'Sangat Baik',
                        'is_required' => true,
                        'order_number' => 1,
                    ],
                    [
                        'question' => 'Jumlah pertemuan kuliah memenuhi target minimal 16 sesi (termasuk UTS dan UAS).',
                        'question_type' => 'likert',
                        'scale_min' => 1,
                        'scale_max' => 5,
                        'scale_min_label' => 'Sangat Kurang',
                        'scale_max_label' => 'Sangat Baik',
                        'is_required' => true,
                        'order_number' => 2,
                    ],
                ],
            ],
            [
                'title' => 'Transparansi Evaluasi, Umpan Balik & Masukan Mahasiswa',
                'description' => 'Evaluasi keterbukaan nilai tugas/ujian serta kotak saran masukan untuk pengembangan mata kuliah.',
                'order_number' => 4,
                'questions' => [
                    [
                        'question' => 'Dosen memberikan umpan balik (feedback) atas tugas dan ujian yang dikerjakan mahasiswa.',
                        'question_type' => 'likert',
                        'scale_min' => 1,
                        'scale_max' => 5,
                        'scale_min_label' => 'Sangat Kurang',
                        'scale_max_label' => 'Sangat Baik',
                        'is_required' => true,
                        'order_number' => 1,
                    ],
                    [
                        'question' => 'Tuliskan kritik, saran, atau masukan konstruktif untuk pengembangan mata kuliah ini ke depan:',
                        'question_type' => 'essay',
                        'scale_min' => 1,
                        'scale_max' => 5,
                        'scale_min_label' => null,
                        'scale_max_label' => null,
                        'is_required' => false,
                        'order_number' => 2,
                    ],
                ],
            ],
        ];

        foreach ($templates as $t) {
            $topic = CourseQuestionnaireTopic::create([
                'course_id' => $course->id,
                'title' => $t['title'],
                'description' => $t['description'],
                'order_number' => $t['order_number'],
                'is_active' => true,
            ]);

            foreach ($t['questions'] as $q) {
                CourseQuestionnaireQuestion::create([
                    'topic_id' => $topic->id,
                    'question' => $q['question'],
                    'question_type' => $q['question_type'],
                    'scale_min' => $q['scale_min'],
                    'scale_max' => $q['scale_max'],
                    'scale_min_label' => $q['scale_min_label'],
                    'scale_max_label' => $q['scale_max_label'],
                    'is_required' => $q['is_required'],
                    'order_number' => $q['order_number'],
                ]);
            }
        }
    }
}
