<?php

namespace Modules\MBKM\Services;

use Modules\Academic\Enums\DegreeLevel;
use Modules\Enrollment\Services\AcademicHistoryProvider;
use Modules\MBKM\Enums\ApplicationStatus;
use Modules\MBKM\Enums\ParticipantStatus;
use Modules\MBKM\Enums\RequirementType;
use Modules\MBKM\Models\MbkmApplication;
use Modules\MBKM\Models\MbkmParticipant;
use Modules\MBKM\Models\MbkmProgram;
use Modules\MBKM\Models\MbkmProgramRequirement;
use Modules\Student\Enums\StudentStatus;
use Modules\Student\Models\Student;
use Modules\Student\Services\StudentPortalService;

/**
 * Server-side eligibility evaluation for MBKM programs.
 *
 * Every requirement is configurable data (program columns + mbkm_program_requirements
 * rows) — nothing about "who may register" is hardcoded. The evaluation returns an
 * explainable result: the student always learns *why* they are or are not eligible.
 */
class MbkmEligibilityService
{
    public function __construct(
        protected StudentPortalService $portalService,
        protected AcademicHistoryProvider $historyProvider,
    ) {}

    /**
     * Academic snapshot reused across eligibility, catalog and dashboards.
     */
    public function academicSnapshot(Student $student): array
    {
        return $this->portalService->getAcademicSnapshot($student);
    }

    /**
     * Count of MBKM participations that still count against a program's
     * participation limit (withdrawn/terminated/cancelled do not count).
     */
    public function participationCount(Student $student): int
    {
        return MbkmParticipant::where('student_id', $student->id)
            ->whereIn('status', ParticipantStatus::occupyingQuota())
            ->count();
    }

    /**
     * Evaluate a student against a program.
     *
     * @param  bool  $enforceDocuments  When true (application submit) document
     *                                  requirements without an uploaded file fail.
     * @param  array<int, string>  $uploadedDocumentCodes  Codes/categories of documents already uploaded.
     * @return array{
     *     is_eligible: bool,
     *     reasons: array<int, string>,
     *     checks: array<int, array{code: string, label: string, passed: bool, message: string, type: string}>,
     *     snapshot: array<string, mixed>,
     *     quota: array{quota: int|null, used: int, remaining: int|null, available: bool}
     * }
     */
    public function evaluate(
        MbkmProgram $program,
        Student $student,
        bool $enforceDocuments = false,
        array $uploadedDocumentCodes = []
    ): array {
        $program->loadMissing(['requirements', 'programType', 'studyProgram', 'faculty']);

        $snapshot = $this->academicSnapshot($student);
        $checks = [];

        $studentStatusValue = $student->status instanceof StudentStatus
            ? $student->status->value
            : (string) $student->status;

        // 1. Program lifecycle + registration window
        $registrationOpen = $program->isRegistrationOpen();
        $checks[] = $this->check(
            'registration_window',
            'Periode Pendaftaran',
            RequirementType::ADMINISTRATIVE,
            $registrationOpen,
            $registrationOpen
                ? 'Pendaftaran sedang dibuka.'
                : 'Pendaftaran program ini sedang tidak dibuka.'
        );

        // 2. Student must be active
        $isActive = $studentStatusValue === StudentStatus::ACTIVE->value;
        $checks[] = $this->check(
            'student_status',
            'Status Mahasiswa',
            RequirementType::ADMINISTRATIVE,
            $isActive,
            $isActive
                ? 'Mahasiswa berstatus aktif.'
                : 'Hanya mahasiswa berstatus aktif yang dapat mendaftar (status saat ini: ' . $studentStatusValue . ').'
        );

        // 3. Target study program
        $targetProgramIds = $program->target_study_program_ids ?: [];
        if ($program->study_program_id) {
            $targetProgramIds[] = (int) $program->study_program_id;
        }
        $targetProgramIds = array_values(array_unique(array_map('intval', $targetProgramIds)));

        if (!empty($targetProgramIds)) {
            $match = in_array((int) $student->study_program_id, $targetProgramIds, true);
            $checks[] = $this->check(
                'study_program',
                'Program Studi Sasaran',
                RequirementType::ACADEMIC,
                $match,
                $match
                    ? 'Program studi Anda termasuk sasaran program.'
                    : 'Program ini hanya untuk program studi tertentu.'
            );
        }

        // 4. Target degree level
        $targetLevels = $program->target_degree_levels ?: [];
        if (!empty($targetLevels)) {
            $degree = $student->studyProgram?->degree;
            $degreeValue = $degree instanceof DegreeLevel ? $degree->value : ($degree !== null ? (string) $degree : null);
            $match = $degreeValue !== null && in_array($degreeValue, $targetLevels, true);
            $checks[] = $this->check(
                'degree_level',
                'Jenjang',
                RequirementType::ACADEMIC,
                $match,
                $match ? 'Jenjang Anda sesuai.' : 'Jenjang studi Anda tidak termasuk sasaran program.'
            );
        }

        // 5. Target admission year (angkatan)
        $targetYears = array_map('intval', $program->target_admission_years ?: []);
        if (!empty($targetYears)) {
            $match = in_array((int) $student->admission_year, $targetYears, true);
            $checks[] = $this->check(
                'admission_year',
                'Angkatan',
                RequirementType::ACADEMIC,
                $match,
                $match ? 'Angkatan Anda sesuai.' : 'Angkatan Anda tidak termasuk sasaran program.'
            );
        }

        // 6. Semester range
        $currentSemester = (int) ($snapshot['current_semester'] ?? 1);
        if ($program->min_semester !== null) {
            $match = $currentSemester >= $program->min_semester;
            $checks[] = $this->check(
                'min_semester',
                'Semester Minimum',
                RequirementType::ACADEMIC,
                $match,
                $match
                    ? 'Semester Anda memenuhi batas minimum.'
                    : "Program ini minimal semester {$program->min_semester} (semester Anda: {$currentSemester})."
            );
        }
        if ($program->max_semester !== null) {
            $match = $currentSemester <= $program->max_semester;
            $checks[] = $this->check(
                'max_semester',
                'Semester Maksimum',
                RequirementType::ACADEMIC,
                $match,
                $match
                    ? 'Semester Anda masih dalam batas maksimum.'
                    : "Program ini maksimal semester {$program->max_semester} (semester Anda: {$currentSemester})."
            );
        }

        // 7. Minimum GPA
        if ($program->min_gpa !== null) {
            $gpa = (float) ($snapshot['cumulative_gpa'] ?? 0);
            $match = $gpa >= (float) $program->min_gpa;
            $checks[] = $this->check(
                'min_gpa',
                'IPK Minimum',
                RequirementType::ACADEMIC,
                $match,
                $match
                    ? 'IPK Anda memenuhi syarat.'
                    : 'IPK minimum ' . number_format((float) $program->min_gpa, 2) . ' (IPK Anda: ' . number_format($gpa, 2) . ').'
            );
        }

        // 8. Minimum credits
        if ($program->min_credits !== null) {
            $credits = (int) ($snapshot['total_credits_passed'] ?? 0);
            $match = $credits >= (int) $program->min_credits;
            $checks[] = $this->check(
                'min_credits',
                'SKS Minimum',
                RequirementType::ACADEMIC,
                $match,
                $match
                    ? 'SKS lulus Anda memenuhi syarat.'
                    : "Minimal {$program->min_credits} SKS lulus (SKS Anda: {$credits})."
            );
        }

        // 9. Required passed courses (prerequisite courses)
        $requiredCourseIds = array_map('intval', $program->required_passed_course_ids ?: []);
        if (!empty($requiredCourseIds)) {
            $passed = $this->historyProvider->getPassedCourseIds($student);
            $missing = array_diff($requiredCourseIds, array_map('intval', $passed));
            $match = empty($missing);
            $checks[] = $this->check(
                'passed_courses',
                'Mata Kuliah Prasyarat',
                RequirementType::ACADEMIC,
                $match,
                $match
                    ? 'Seluruh mata kuliah prasyarat sudah lulus.'
                    : count($missing) . ' mata kuliah prasyarat belum lulus.'
            );
        }

        // 10. Participation limit
        if ($program->participation_limit !== null) {
            $used = $this->participationCount($student);
            $match = $used < (int) $program->participation_limit;
            $checks[] = $this->check(
                'participation_limit',
                'Batas Partisipasi MBKM',
                RequirementType::ADMINISTRATIVE,
                $match,
                $match
                    ? 'Anda masih dalam batas partisipasi MBKM.'
                    : "Anda sudah mengikuti {$used} program MBKM, batas maksimum {$program->participation_limit}."
            );
        }

        // 11. Configurable custom / additional requirements
        foreach ($program->requirements as $requirement) {
            if ($requirement->type === RequirementType::DOCUMENT || $requirement->is_document) {
                $uploaded = in_array($requirement->code, $uploadedDocumentCodes, true)
                    || in_array($requirement->code, $uploadedDocumentCodes, true);

                $passed = !$enforceDocuments || $uploaded || !$requirement->is_mandatory;
                $checks[] = $this->check(
                    'document:' . ($requirement->code ?: $requirement->id),
                    $requirement->name,
                    RequirementType::DOCUMENT,
                    $passed,
                    $uploaded
                        ? 'Dokumen sudah diunggah.'
                        : 'Dokumen wajib belum diunggah: ' . $requirement->name . '.'
                );
                continue;
            }

            if ($requirement->rule) {
                [$passed, $message] = $this->evaluateRule($requirement, $snapshot, $student, $studentStatusValue);
                $checks[] = $this->check(
                    'rule:' . ($requirement->code ?: $requirement->id),
                    $requirement->name,
                    $requirement->type,
                    $passed || !$requirement->is_mandatory,
                    $message
                );
            }
        }

        // 12. Quota availability (informational, also enforced at assignment time)
        $used = $program->usedQuota();
        $remaining = $program->quota === null ? null : max(0, $program->quota - $used);
        $quotaAvailable = $program->quota === null || $remaining > 0;

        $failingChecks = array_values(array_filter($checks, fn (array $c) => !$c['passed']));
        $reasons = array_map(fn (array $c) => $c['message'], $failingChecks);

        return [
            'is_eligible' => empty($failingChecks),
            'reasons' => $reasons,
            'checks' => $checks,
            'snapshot' => $snapshot,
            'quota' => [
                'quota' => $program->quota,
                'used' => $used,
                'remaining' => $remaining,
                'available' => $quotaAvailable,
            ],
        ];
    }

    /**
     * Evaluate a single machine-readable requirement rule.
     *
     * @return array{0: bool, 1: string}
     */
    protected function evaluateRule(
        MbkmProgramRequirement $requirement,
        array $snapshot,
        Student $student,
        string $studentStatusValue
    ): array {
        $rule = $requirement->rule;
        $field = $rule['field'] ?? null;
        $operator = $rule['operator'] ?? '>=';
        $expected = $rule['value'] ?? null;

        $actual = match ($field) {
            'gpa' => (float) ($snapshot['cumulative_gpa'] ?? 0),
            'total_credits' => (int) ($snapshot['total_credits_passed'] ?? 0),
            'current_semester' => (int) ($snapshot['current_semester'] ?? 1),
            'admission_year' => (int) $student->admission_year,
            'study_program_id' => (int) $student->study_program_id,
            'student_status' => $studentStatusValue,
            'passed_course_count' => count($this->historyProvider->getPassedCourseIds($student)),
            default => null,
        };

        if ($actual === null) {
            return [true, 'Persyaratan tidak dapat dievaluasi otomatis — diverifikasi manual oleh pengelola.'];
        }

        $passed = match ($operator) {
            '>=' => $actual >= $expected,
            '<=' => $actual <= $expected,
            '>' => $actual > $expected,
            '<' => $actual < $expected,
            '==' => $actual == $expected,
            '!=' => $actual != $expected,
            'in' => in_array($actual, (array) $expected, true) || in_array((string) $actual, (array) $expected, true),
            'not_in' => !in_array($actual, (array) $expected, true) && !in_array((string) $actual, (array) $expected, true),
            default => true,
        };

        return [
            $passed,
            $passed
                ? "Persyaratan {$requirement->name} terpenuhi."
                : "Persyaratan {$requirement->name} belum terpenuhi.",
        ];
    }

    /**
     * @return array{code: string, label: string, passed: bool, message: string, type: string}
     */
    protected function check(string $code, string $label, RequirementType|string $type, bool $passed, string $message): array
    {
        return [
            'code' => $code,
            'label' => $label,
            'passed' => $passed,
            'message' => $message,
            'type' => $type instanceof RequirementType ? $type->value : (string) $type,
        ];
    }

    /**
     * Active applications of a student for a program (used for duplicate checks).
     */
    public function activeApplicationExists(MbkmProgram $program, Student $student): bool
    {
        return MbkmApplication::where('program_id', $program->id)
            ->where('student_id', $student->id)
            ->whereIn('status', ApplicationStatus::activeStatuses())
            ->exists();
    }
}
