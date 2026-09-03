<?php

namespace Modules\Student\Actions;

use Modules\Audit\Services\AuditService;
use Modules\Student\Models\Student;

class UpdateStudentAction
{
    public function execute(Student $student, array $data): Student
    {
        $oldValues = $student->toArray();
        $studentData = collect($data)->except(['status', 'families', 'educations'])->toArray();

        $student->update($studentData);

        if (isset($data['families']) && is_array($data['families'])) {
            $student->families()->delete();
            foreach ($data['families'] as $family) {
                if (!empty($family['full_name'])) {
                    $student->families()->create($family);
                }
            }
        }

        AuditService::log(
            action: 'updated',
            module: 'Student',
            description: "Student {$student->full_name} ({$student->student_number}) was updated.",
            entity: $student,
            oldValues: $oldValues,
            newValues: $student->fresh()->toArray()
        );

        return $student->fresh(['studyProgram.faculty', 'families', 'educations']);
    }
}
