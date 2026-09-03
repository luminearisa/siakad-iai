<?php

namespace Modules\Enrollment\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Services\AuditService;
use Modules\Enrollment\Enums\EnrollmentStatus;
use Modules\Enrollment\Models\StudentEnrollment;
use Modules\Student\Enums\StudentStatus;
use Modules\Student\Models\Student;

class CreateEnrollmentAction
{
    public function execute(array $data): StudentEnrollment
    {
        $student = Student::findOrFail($data['student_id']);

        if ($student->status !== StudentStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'student_id' => ["Cannot create enrollment for student with status {$student->status->value}. Student must be active."],
            ]);
        }

        $existing = StudentEnrollment::where('student_id', $student->id)
            ->where('semester_id', $data['semester_id'])
            ->first();

        if ($existing) {
            return $existing->load(['student.studyProgram', 'semester.academicYear', 'items.academicClass.course', 'items.academicClass.schedules.room']);
        }

        $enrollment = DB::transaction(function () use ($data) {
            return StudentEnrollment::create([
                'student_id' => $data['student_id'],
                'semester_id' => $data['semester_id'],
                'status' => EnrollmentStatus::DRAFT,
                'total_credits' => 0,
                'notes' => $data['notes'] ?? null,
            ]);
        });

        AuditService::log(
            action: 'created',
            module: 'Enrollment',
            description: "Enrollment (KRS) draft created for student {$student->full_name} ({$student->student_number}).",
            entity: $enrollment,
            oldValues: null,
            newValues: $enrollment->toArray()
        );

        return $enrollment->load(['student.studyProgram', 'semester.academicYear', 'items']);
    }
}
