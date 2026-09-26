<?php

namespace Modules\Student\Actions;

use Modules\Audit\Services\AuditService;
use Modules\Student\Enums\StudentStatus;
use Modules\Student\Models\Student;

class CreateStudentAction
{
    public ?string $generatedPassword = null;

    public function __construct(
        protected ProvisionStudentAccountAction $provisionAccount
    ) {}

    public function execute(array $data): Student
    {
        $studentData = collect($data)->except(['families', 'educations', 'user_id'])->toArray();

        if (!isset($studentData['status'])) {
            $studentData['status'] = StudentStatus::ACTIVE;
        }

        // Every student always ends up with a login account; when no email is
        // supplied one is derived from the NIM so the record is never orphaned.
        if (empty($studentData['email'])) {
            $studentData['email'] = $studentData['student_number'] . '@' . ProvisionStudentAccountAction::DEFAULT_EMAIL_DOMAIN;
        }

        $user = $this->provisionAccount->execute(
            email: $studentData['email'],
            fullName: $studentData['full_name'] ?? 'Mahasiswa'
        );

        $this->generatedPassword = $this->provisionAccount->generatedPassword;
        $studentData['user_id'] = $user->id;

        $student = Student::create($studentData);

        if (!empty($data['families'])) {
            foreach ($data['families'] as $family) {
                $student->families()->create($family);
            }
        }

        if (!empty($data['educations'])) {
            foreach ($data['educations'] as $education) {
                $student->educations()->create($education);
            }
        }

        AuditService::log(
            action: 'created',
            module: 'Student',
            description: "Student {$student->full_name} ({$student->student_number}) was created.",
            entity: $student,
            oldValues: null,
            newValues: $student->toArray()
        );

        return $student->load(['studyProgram.faculty', 'families', 'educations']);
    }
}
