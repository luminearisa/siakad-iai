<?php

namespace Modules\Assessment\Services;

use Modules\Assessment\Models\AssessmentScheme;
use Modules\Assessment\Models\StudentGrade;
use Modules\Class\Models\AcademicClass;
use Modules\Settings\Models\Setting;
use Modules\Student\Models\Student;

class GradeCalculationService
{
    /**
     * Get the grading scale configuration from Settings module.
     *
     * @return array<string, array{min: float, max: float, point: float}>
     */
    public function getGradingScale(): array
    {
        $setting = Setting::where('key', 'default_grading_scale')->first();

        if ($setting && is_array($setting->typed_value)) {
            return $setting->typed_value;
        }

        // Standard fallback grading scale
        return [
            'A'  => ['min' => 85.0, 'max' => 100.0, 'point' => 4.0],
            'B+' => ['min' => 75.0, 'max' => 84.99, 'point' => 3.5],
            'B'  => ['min' => 65.0, 'max' => 74.99, 'point' => 3.0],
            'C+' => ['min' => 60.0, 'max' => 64.99, 'point' => 2.5],
            'C'  => ['min' => 55.0, 'max' => 59.99, 'point' => 2.0],
            'D'  => ['min' => 40.0, 'max' => 54.99, 'point' => 1.0],
            'E'  => ['min' => 0.0,  'max' => 39.99, 'point' => 0.0],
        ];
    }

    /**
     * Convert numerical score (0-100) to Letter Grade and Grade Point.
     *
     * @param float $finalScore
     * @return array{letter_grade: string, grade_point: float}
     */
    public function convertScoreToGrade(float $finalScore): array
    {
        $scale = $this->getGradingScale();
        $roundedScore = round($finalScore, 2);

        foreach ($scale as $letter => $rule) {
            $min = (float) ($rule['min'] ?? 0);
            $max = (float) ($rule['max'] ?? 100);
            $point = (float) ($rule['point'] ?? 0.0);

            if ($roundedScore >= $min && $roundedScore <= $max) {
                return [
                    'letter_grade' => $letter,
                    'grade_point' => round($point, 2),
                ];
            }
        }

        // Default fallback if score exceeds 100 or below 0
        if ($roundedScore > 100) {
            return ['letter_grade' => 'A', 'grade_point' => 4.0];
        }

        return ['letter_grade' => 'E', 'grade_point' => 0.0];
    }

    /**
     * Calculate final score for a specific student in an academic class.
     * Formula: SUM( (student_score / component_max_score) * component_weight )
     *
     * @param Student $student
     * @param AcademicClass $class
     * @param AssessmentScheme|null $scheme
     * @return array{
     *     final_score: float,
     *     letter_grade: string,
     *     grade_point: float,
     *     components_breakdown: array,
     *     total_weight_graded: float,
     *     is_complete: boolean
     * }
     */
    public function calculateStudentFinalScore(
        Student $student,
        AcademicClass $class,
        ?AssessmentScheme $scheme = null
    ): array {
        if (!$scheme) {
            $scheme = AssessmentScheme::where('academic_class_id', $class->id)
                ->where('is_active', true)
                ->with(['items.component'])
                ->first();
        }

        if (!$scheme) {
            return [
                'final_score' => 0.00,
                'letter_grade' => 'E',
                'grade_point' => 0.00,
                'components_breakdown' => [],
                'total_weight_graded' => 0.00,
                'is_complete' => false,
            ];
        }

        $items = $scheme->items;
        $componentIds = $items->pluck('assessment_component_id')->toArray();

        $grades = StudentGrade::where('student_id', $student->id)
            ->where('academic_class_id', $class->id)
            ->whereIn('assessment_component_id', $componentIds)
            ->get()
            ->keyBy('assessment_component_id');

        $finalScore = 0.00;
        $totalWeightGraded = 0.00;
        $breakdown = [];
        $isComplete = true;

        foreach ($items as $item) {
            $component = $item->component;
            $weight = (float) $item->weight;
            $maxScore = (float) ($component?->max_score ?: 100.0);

            $grade = $grades->get($item->assessment_component_id);
            $rawScore = $grade ? (float) $grade->score : 0.00;
            $hasScore = $grade !== null;

            if (!$hasScore && $component?->is_required) {
                $isComplete = false;
            }

            if ($hasScore) {
                $totalWeightGraded += $weight;
            }

            $weightedScore = $maxScore > 0 ? ($rawScore / $maxScore) * $weight : 0.00;
            $finalScore += $weightedScore;

            $breakdown[] = [
                'component_id' => $item->assessment_component_id,
                'component_name' => $component?->name,
                'component_code' => $component?->code,
                'component_type' => $component?->type?->value ?? $component?->type,
                'is_required' => (bool) $component?->is_required,
                'max_score' => $maxScore,
                'weight' => $weight,
                'raw_score' => $rawScore,
                'weighted_score' => round($weightedScore, 2),
                'status' => $grade?->status?->value ?? 'not_graded',
                'graded_at' => $grade?->graded_at?->toIso8601String(),
                'notes' => $grade?->notes,
            ];
        }

        $finalScore = round($finalScore, 2);
        $gradeConversion = $this->convertScoreToGrade($finalScore);

        return [
            'final_score' => $finalScore,
            'letter_grade' => $gradeConversion['letter_grade'],
            'grade_point' => $gradeConversion['grade_point'],
            'components_breakdown' => $breakdown,
            'total_weight_graded' => round($totalWeightGraded, 2),
            'is_complete' => $isComplete && $totalWeightGraded >= 100.0,
        ];
    }

    /**
     * Calculate full class grade recapitulation.
     *
     * @param AcademicClass $class
     * @return array
     */
    public function calculateClassGradeRecap(AcademicClass $class): array
    {
        $scheme = AssessmentScheme::where('academic_class_id', $class->id)
            ->where('is_active', true)
            ->with(['items.component'])
            ->first();

        // Enrolled students via class enrollments or student_classes
        $students = $class->enrolledStudents() ?? collect([]);
        if ($students->isEmpty() && method_exists($class, 'students')) {
            $students = $class->students;
        }

        $recap = [];
        $gradeCounts = ['A' => 0, 'B+' => 0, 'B' => 0, 'C+' => 0, 'C' => 0, 'D' => 0, 'E' => 0];
        $totalScores = 0.0;

        foreach ($students as $student) {
            $calc = $this->calculateStudentFinalScore($student, $class, $scheme);

            $recap[] = [
                'student' => [
                    'id' => $student->id,
                    'student_number' => $student->student_number,
                    'full_name' => $student->full_name,
                    'study_program' => $student->studyProgram?->name,
                ],
                'final_score' => $calc['final_score'],
                'letter_grade' => $calc['letter_grade'],
                'grade_point' => $calc['grade_point'],
                'is_complete' => $calc['is_complete'],
                'components' => $calc['components_breakdown'],
            ];

            if (isset($gradeCounts[$calc['letter_grade']])) {
                $gradeCounts[$calc['letter_grade']]++;
            }
            $totalScores += $calc['final_score'];
        }

        $studentCount = count($recap);
        $averageScore = $studentCount > 0 ? round($totalScores / $studentCount, 2) : 0.00;

        return [
            'class' => [
                'id' => $class->id,
                'code' => $class->code,
                'name' => $class->name,
                'section' => $class->section,
                'course' => $class->course ? [
                    'id' => $class->course->id,
                    'code' => $class->course->code,
                    'name' => $class->course->name,
                    'credits' => $class->course->credits,
                ] : null,
            ],
            'scheme' => $scheme ? [
                'id' => $scheme->id,
                'name' => $scheme->name,
                'total_weight' => $scheme->total_weight,
                'is_active' => $scheme->is_active,
                'components_count' => $scheme->items->count(),
            ] : null,
            'summary' => [
                'total_students' => $studentCount,
                'average_score' => $averageScore,
                'grade_distribution' => $gradeCounts,
            ],
            'grades' => $recap,
        ];
    }
}
