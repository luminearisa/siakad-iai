<?php

namespace Modules\Graduation\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Assessment\Models\StudentGrade;
use Modules\Enrollment\Models\StudentEnrollment;
use Modules\Graduation\Models\YudisiumRequirement;
use Modules\Student\Models\Student;
use Modules\Thesis\Models\Thesis;

class YudisiumEligibilityService
{
    /**
     * Audit eligibility for a collection of students or a single student.
     */
    public function auditStudent(Student $student): array
    {
        // 1. Calculate Masa Studi
        $startDate = $student->entry_date
            ? Carbon::parse($student->entry_date)
            : ($student->admission_year
                ? Carbon::createFromDate((int) $student->admission_year, 9, 1)
                : $student->created_at);

        $now = Carbon::now();
        $diff = $startDate->diff($now);
        $studyDurationFormatted = "{$diff->y} Tahun {$diff->m} Bulan {$diff->d} Hari";
        $studyDurationDays = $startDate->diffInDays($now);

        // 2. Calculate SKS Lulus & IPK Lulus from enrollments/grades
        $studentGrades = StudentGrade::with(['academicClass.course'])
            ->where('student_id', $student->id)
            ->get();

        $totalCredits = 0;
        $weightedPoints = 0;

        foreach ($studentGrades as $grade) {
            $course = $grade->academicClass?->course;
            $credits = $course?->credit_points ?? $course?->credits ?? 0;
            $numericGrade = $grade->score ?? 0;

            if ($numericGrade >= 55) { // Passed (>= C)
                $gradePoint = $numericGrade >= 85 ? 4.0 : ($numericGrade >= 70 ? 3.0 : 2.0);
                $totalCredits += $credits;
                $weightedPoints += ($credits * $gradePoint);
            }
        }

        $gpa = $totalCredits > 0 ? round($weightedPoints / $totalCredits, 2) : 0.00;

        // 3. Check Thesis
        $thesis = Thesis::where('student_id', $student->id)->latest()->first();
        $thesisStatus = 'Belum Mengambil';
        $thesisCompleted = false;

        if ($thesis) {
            if ($thesis->status === 'completed') {
                $thesisStatus = 'Lulus';
                $thesisCompleted = true;
            } elseif ($thesis->status === 'active') {
                $thesisStatus = 'Sedang Mengambil';
            } else {
                $thesisStatus = ucfirst($thesis->status);
            }
        }

        // 4. Requirements check
        $minCreditsRequired = $student->studyProgram?->degree === 'D3' ? 108 : 144;
        $minGpaRequired = 2.75;

        $reasons = [];
        if (! $thesisCompleted) {
            $reasons[] = 'Belum mengambil Tugas Akhir';
        }
        if ($totalCredits < $minCreditsRequired) {
            $reasons[] = "SKS lulus minimal {$minCreditsRequired}";
        }
        if ($gpa < $minGpaRequired) {
            $reasons[] = "IPK lulus minimal " . number_format($minGpaRequired, 2);
        }

        $isEligible = empty($reasons);

        return [
            'student_id' => $student->id,
            'student' => $student,
            'study_program' => $student->studyProgram,
            'study_duration' => $studyDurationFormatted,
            'study_duration_days' => $studyDurationDays,
            'passed_credits' => $totalCredits,
            'passed_gpa' => $gpa,
            'thesis_status' => $thesisStatus,
            'thesis' => $thesis,
            'is_eligible' => $isEligible,
            'reasons' => $reasons,
        ];
    }

    /**
     * Audit all students in a study program or across campus.
     */
    public function auditAll(array $filters = []): Collection
    {
        $query = Student::with(['studyProgram', 'user'])
            ->where('status', 'active');

        if (! empty($filters['study_program_id'])) {
            $query->where('study_program_id', $filters['study_program_id']);
        }

        if (! empty($filters['admission_year'])) {
            $query->where('admission_year', $filters['admission_year']);
        }

        if (! empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('full_name', 'like', "%{$s}%")
                    ->orWhere('student_number', 'like', "%{$s}%");
            });
        }

        $students = $query->get();

        return $students->map(function ($student) {
            return $this->auditStudent($student);
        });
    }
}
