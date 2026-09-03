<?php

namespace Modules\Advising\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Advising\Enums\AdvisorStatus;
use Modules\Advising\Models\AcademicAdvisor;
use Modules\Audit\Services\AuditService;
use Modules\Lecturer\Enums\LecturerStatus;
use Modules\Lecturer\Models\Lecturer;
use Modules\Student\Models\Student;

class AssignAcademicAdvisorAction
{
    public function execute(int $studentId, int $lecturerId, ?string $startDate = null, ?string $notes = null): AcademicAdvisor
    {
        $student = Student::findOrFail($studentId);
        $lecturer = Lecturer::findOrFail($lecturerId);

        if ($lecturer->status !== LecturerStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'lecturer_id' => ["Lecturer is not active (status: {$lecturer->status->value}). Only active lecturers can be assigned as academic advisors."],
            ]);
        }

        $startDate = $startDate ?? now()->format('Y-m-d');

        return DB::transaction(function () use ($student, $lecturer, $startDate, $notes) {
            // Deactivate any existing active advisor for this student
            AcademicAdvisor::where('student_id', $student->id)
                ->where('status', AdvisorStatus::ACTIVE)
                ->update([
                    'status' => AdvisorStatus::TRANSFERRED,
                    'end_date' => $startDate,
                ]);

            $assignment = AcademicAdvisor::create([
                'student_id' => $student->id,
                'lecturer_id' => $lecturer->id,
                'start_date' => $startDate,
                'status' => AdvisorStatus::ACTIVE,
                'notes' => $notes,
            ]);

            AuditService::log(
                action: 'advisor_assigned',
                module: 'Advising',
                description: "Lecturer {$lecturer->full_name} assigned as academic advisor to student {$student->full_name}.",
                entity: $assignment,
                oldValues: null,
                newValues: $assignment->toArray()
            );

            return $assignment->load(['student.studyProgram', 'lecturer.homebaseStudyProgram']);
        });
    }
}
