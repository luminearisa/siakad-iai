<?php

namespace Modules\Enrollment\Services;

use Modules\Assessment\Models\AssessmentScheme;
use Modules\Assessment\Models\StudentGrade;
use Modules\Assessment\Services\GradeCalculationService;
use Modules\Class\Models\AcademicClass;
use Modules\Curriculum\Models\Curriculum;
use Modules\Curriculum\Models\GradeScaleItem;
use Modules\Enrollment\Enums\EnrollmentItemStatus;
use Modules\Enrollment\Models\StudentEnrollment;
use Modules\Enrollment\Models\StudentEnrollmentItem;
use Modules\Student\Models\Student;

/**
 * Single source of truth for "what has this student actually achieved?".
 *
 * Every answer here is derived from real grade records (`student_grades` via the
 * Assessment module) and never from enrolment rows alone: being listed in an
 * approved KRS only proves the student *took* a course, not that they *passed*
 * it. A course therefore counts as passed only when its computed final score
 * reaches the passing threshold of the applicable grade scale.
 *
 * NOTE ON CACHING: this service deliberately keeps no state between calls. The
 * container hands out the very same instance for every request (Route memoizes
 * its controller), so a memoised grade book would serve stale grades after a
 * lecturer publishes or revises a value. Reads are batched instead.
 */
class AcademicHistoryProvider
{
    /**
     * Grade point at/above which a course counts as "lulus".
     *
     * On the standard Indonesian scale C = 2.00, D = 1.00 and E = 0.00, so 2.00
     * is exactly the "minimal C" rule without hardcoding the letter.
     */
    public const PASSING_GRADE_POINT = 2.0;

    /** Safety net when no grade scale is configured anywhere. */
    protected const FALLBACK_PASSING_SCORE = 55.0;

    public function __construct(
        protected GradeCalculationService $gradeCalculation
    ) {}

    /**
     * Course ids the student has genuinely PASSED in previous semesters.
     *
     * A course is passed only when a grade exists for it and that grade reaches
     * the passing threshold of the applicable grade scale (default: C / 2.00).
     *
     * FALLBACK RULE (documented on purpose): a course that sits in an
     * approved/locked prior KRS but has NO grade recorded yet is treated as
     * NOT YET PASSED. Grades — not enrolment — are the proof of completion, so
     * a pending value must never unlock a prerequisite.
     *
     * @return array<int, int>
     */
    public function getPassedCourseIds(Student $student, ?int $currentSemesterId = null): array
    {
        $passed = [];

        foreach ($this->getCourseGradeResults($student, $currentSemesterId) as $courseId => $result) {
            if (!empty($result['passed'])) {
                $passed[] = (int) $courseId;
            }
        }

        return array_values(array_unique($passed));
    }

    /**
     * Grade outcome per course id for the student's previous (approved/locked) semesters.
     *
     * @return array<int, array{
     *     course_id: int,
     *     class_id: int|null,
     *     credits: int,
     *     final_score: float|null,
     *     letter_grade: string|null,
     *     grade_point: float,
     *     passed: bool,
     *     has_grade: bool,
     *     semester_id: int|null
     * }>
     */
    public function getCourseGradeResults(Student $student, ?int $currentSemesterId = null): array
    {
        $items = $this->priorEnrolledItems($student, $currentSemesterId);

        if ($items->isEmpty()) {
            return [];
        }

        $passingScore = $this->passingScore(null, $student->study_program_id);
        $scores = $this->finalScores($student, $items->map(fn ($item) => $item->academicClass)->filter()->values());

        $results = [];

        foreach ($items as $item) {
            $courseId = (int) $item->course_id;
            $class = $item->academicClass;
            $score = $class ? ($scores[$class->id] ?? null) : null;
            $hasGrade = $score !== null;

            $conversion = $hasGrade
                ? $this->gradeCalculation->convertScoreToGrade($score)
                : ['letter_grade' => null, 'grade_point' => 0.0];

            $entry = [
                'course_id' => $courseId,
                'class_id' => $item->class_id,
                'credits' => (int) ($item->credits ?: ($class?->course?->credits ?? 0)),
                'final_score' => $score,
                'letter_grade' => $conversion['letter_grade'],
                'grade_point' => (float) $conversion['grade_point'],
                'passed' => $hasGrade && $score >= $passingScore,
                'has_grade' => $hasGrade,
                'semester_id' => $item->enrollment?->semester_id,
            ];

            // Keep the best attempt when a course was retaken across semesters.
            $previous = $results[$courseId] ?? null;

            if ($previous === null
                || ($entry['passed'] && !$previous['passed'])
                || ($entry['passed'] === $previous['passed'] && $entry['grade_point'] > $previous['grade_point'])
            ) {
                $results[$courseId] = $entry;
            }
        }

        return $results;
    }

    /**
     * Check whether every prerequisite of a course is satisfied by a PASSING grade.
     *
     * The required letter comes from `course_prerequisites.minimum_grade` when it
     * is set (e.g. "B" for Microteaching), otherwise the generic passing
     * threshold of the grade scale is used.
     */
    public function hasPassedPrerequisites(Student $student, int $courseId, ?int $currentSemesterId = null): bool
    {
        $course = \Modules\Course\Models\Course::with('prerequisites')->find($courseId);
        if (!$course || $course->prerequisites->isEmpty()) {
            return true;
        }

        // Resolve the scale once for the whole prerequisite set.
        $scale = $this->gradeScale($student->study_program_id);
        $results = $this->getCourseGradeResults($student, $currentSemesterId);

        foreach ($course->prerequisites as $prerequisite) {
            $requiredLetter = $prerequisite->pivot?->minimum_grade ?: null;
            $threshold = $this->passingScoreFromScale($scale, $requiredLetter);

            $result = $results[(int) $prerequisite->id] ?? null;

            // No grade recorded yet (or a failing one) => prerequisite NOT met.
            if ($result === null || !$result['has_grade'] || (float) $result['final_score'] < $threshold) {
                return false;
            }
        }

        return true;
    }

    /**
     * IPS (Indeks Prestasi Semester) of the student's most recent PREVIOUS semester.
     *
     * Computed from real grades: SUM(sks x grade point) / SUM(sks). Returns null
     * when the student has no completed prior semester yet (e.g. mahasiswa baru),
     * which callers must treat as "no IPS history".
     */
    public function calculatePreviousIps(Student $student, ?int $excludeSemesterId = null): ?float
    {
        $previous = $this->previousEnrollment($student, $excludeSemesterId);

        if (!$previous) {
            return null;
        }

        $items = $previous->items->filter(
            fn (StudentEnrollmentItem $item) => $item->status === EnrollmentItemStatus::ENROLLED
                && $item->academicClass !== null
                && (int) ($item->credits ?: ($item->academicClass->course?->credits ?? 0)) > 0
        );

        if ($items->isEmpty()) {
            return null;
        }

        $scores = $this->finalScores($student, $items->map(fn ($item) => $item->academicClass)->values());

        $totalCredits = 0;
        $totalPoints = 0.0;

        foreach ($items as $item) {
            $score = $scores[$item->academicClass->id] ?? null;
            if ($score === null) {
                continue;
            }

            $credits = (int) ($item->credits ?: ($item->academicClass->course?->credits ?? 0));
            $point = (float) $this->gradeCalculation->convertScoreToGrade($score)['grade_point'];

            $totalCredits += $credits;
            $totalPoints += $credits * $point;
        }

        if ($totalCredits === 0) {
            return null;
        }

        return round($totalPoints / $totalCredits, 2);
    }

    /**
     * Lowest final score that maps to the given letter grade.
     *
     * @param  string|null  $letterGrade  e.g. "C", "B+". Null => generic passing score.
     */
    public function passingScore(?string $letterGrade = null, ?int $studyProgramId = null): float
    {
        return $this->passingScoreFromScale($this->gradeScale($studyProgramId), $letterGrade);
    }

    /**
     * Resolve the grade scale that applies to a study program.
     *
     * Priority: the curriculum's `grade_scale_items` (Curriculum module) ->
     * the `default_grading_scale` setting read by GradeCalculationService.
     *
     * @return array<string, array{min: float, point: float}>
     */
    public function gradeScale(?int $studyProgramId = null): array
    {
        $scale = [];

        $curriculum = $studyProgramId
            ? Curriculum::where('study_program_id', $studyProgramId)
                ->where('status', 'active')
                ->whereNotNull('grade_scale_id')
                ->first()
            : null;

        if ($curriculum?->grade_scale_id) {
            $items = GradeScaleItem::where('grade_scale_id', $curriculum->grade_scale_id)
                ->where('is_whitewash', false)
                ->orderByDesc('min_score')
                ->get();

            foreach ($items as $item) {
                $scale[strtoupper((string) $item->grade_letter)] = [
                    'min' => (float) $item->min_score,
                    'point' => (float) $item->grade_point,
                ];
            }
        }

        if (empty($scale)) {
            foreach ($this->gradeCalculation->getGradingScale() as $letter => $rule) {
                $scale[strtoupper((string) $letter)] = [
                    'min' => (float) ($rule['min'] ?? 0),
                    'point' => (float) ($rule['point'] ?? 0.0),
                ];
            }
        }

        return $scale;
    }

    /**
     * Final score (0-100) of a student in a class, or null when nothing is graded yet.
     */
    public function finalScoreFor(Student $student, AcademicClass $class): ?float
    {
        return $this->finalScores($student, collect([$class]))[$class->id] ?? null;
    }

    /**
     * Final score (0-100) per class id, resolved in two batched queries.
     *
     * Delegates the weighted formula to GradeCalculationService (passing the
     * pre-loaded scheme so it does not query for it again), which keeps the
     * scoring rule in exactly one place. When a class has no active assessment
     * scheme the service cannot weight anything, so we fall back to the plain
     * mean of the StudentGrade rows — normalised against each component's max
     * score — instead of reporting 0.00, which would silently mark every student
     * of that class as failing.
     *
     * @param  iterable<AcademicClass>  $classes
     * @return array<int, float|null>
     */
    protected function finalScores(Student $student, iterable $classes): array
    {
        $byId = [];
        foreach ($classes as $class) {
            if ($class) {
                $byId[$class->id] = $class;
            }
        }

        if (empty($byId)) {
            return [];
        }

        $classIds = array_keys($byId);

        $schemes = AssessmentScheme::query()
            ->whereIn('academic_class_id', $classIds)
            ->where('is_active', true)
            ->with('items.component')
            ->get()
            ->keyBy('academic_class_id');

        $grades = StudentGrade::query()
            ->with('component')
            ->where('student_id', $student->id)
            ->whereIn('academic_class_id', $classIds)
            ->get()
            ->groupBy('academic_class_id');

        $scores = [];

        foreach ($byId as $classId => $class) {
            $scheme = $schemes->get($classId);
            $classGrades = $grades->get($classId);

            if ($scheme) {
                $calc = $this->gradeCalculation->calculateStudentFinalScore($student, $class, $scheme);
                $scores[$classId] = (float) $calc['total_weight_graded'] > 0.0
                    ? round((float) $calc['final_score'], 2)
                    : null;

                continue;
            }

            if (!$classGrades || $classGrades->isEmpty()) {
                $scores[$classId] = null;
                continue;
            }

            $sum = 0.0;
            $count = 0;

            foreach ($classGrades as $grade) {
                $maxScore = (float) ($grade->component?->max_score ?: 100.0);
                if ($maxScore <= 0) {
                    $maxScore = 100.0;
                }

                $sum += ((float) $grade->score / $maxScore) * 100;
                $count++;
            }

            $scores[$classId] = $count > 0 ? round($sum / $count, 2) : null;
        }

        return $scores;
    }

    /**
     * @param  array<string, array{min: float, point: float}>  $scale
     */
    protected function passingScoreFromScale(array $scale, ?string $letterGrade = null): float
    {
        if ($letterGrade !== null) {
            $normalized = strtoupper(trim($letterGrade));
            if (isset($scale[$normalized])) {
                return (float) $scale[$normalized]['min'];
            }
        }

        $passing = array_filter(
            $scale,
            fn (array $row) => (float) $row['point'] >= self::PASSING_GRADE_POINT
        );

        if (empty($passing)) {
            return self::FALLBACK_PASSING_SCORE;
        }

        return (float) min(array_map(fn (array $row) => (float) $row['min'], $passing));
    }

    /**
     * Items the student is actively enrolled in, in approved/locked KRS of a
     * PREVIOUS semester. Batal-tambah rows (dropped/cancelled) never count.
     *
     * @return \Illuminate\Support\Collection<int, StudentEnrollmentItem>
     */
    protected function priorEnrolledItems(Student $student, ?int $currentSemesterId = null)
    {
        return StudentEnrollmentItem::query()
            ->with(['academicClass', 'enrollment'])
            ->where('status', EnrollmentItemStatus::ENROLLED->value)
            ->whereHas('enrollment', function ($q) use ($student, $currentSemesterId) {
                $q->where('student_id', $student->id)
                  ->whereIn('status', ['approved', 'locked'])
                  ->when($currentSemesterId, fn ($sq) => $sq->where('semester_id', '!=', $currentSemesterId));
            })
            ->get();
    }

    /**
     * The most recent approved/locked KRS of a semester before the given one.
     *
     * "Most recent" = the latest semester start date, tie-broken by id so the
     * result stays deterministic when start dates are missing.
     */
    protected function previousEnrollment(Student $student, ?int $excludeSemesterId = null): ?StudentEnrollment
    {
        $exclude = $excludeSemesterId ?? $this->activeSemesterId();

        return StudentEnrollment::query()
            ->with(['semester', 'items.academicClass'])
            ->where('student_id', $student->id)
            ->whereIn('status', ['approved', 'locked'])
            ->when($exclude, fn ($q) => $q->where('semester_id', '!=', $exclude))
            ->get()
            ->sort(function (StudentEnrollment $a, StudentEnrollment $b) {
                $byDate = ($a->semester?->start_date?->getTimestamp() ?? 0)
                    <=> ($b->semester?->start_date?->getTimestamp() ?? 0);

                return $byDate !== 0 ? $byDate : ($a->id <=> $b->id);
            })
            ->last();
    }

    protected function activeSemesterId(): ?int
    {
        return \Modules\Academic\Models\Semester::where('status', 'active')->value('id');
    }
}
