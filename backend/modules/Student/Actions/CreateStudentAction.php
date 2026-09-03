<?php

namespace Modules\Student\Actions;

use Illuminate\Support\Facades\Hash;
use Modules\Audit\Services\AuditService;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\User;
use Modules\Student\Enums\StudentStatus;
use Modules\Student\Models\Student;

class CreateStudentAction
{
    public function execute(array $data): Student
    {
        $studentData = collect($data)->except(['families', 'educations'])->toArray();

        if (!isset($studentData['status'])) {
            $studentData['status'] = StudentStatus::ACTIVE;
        }

        // Automatically provision User account with role 'mahasiswa' if not already linked
        if (empty($studentData['user_id']) && !empty($studentData['email'])) {
            $user = User::firstOrCreate(
                ['email' => $studentData['email']],
                [
                    'name' => $studentData['full_name'] ?? 'Mahasiswa',
                    'password' => Hash::make('password123'),
                    'status' => UserStatus::ACTIVE,
                ]
            );
            $user->assignRole('mahasiswa');
            $studentData['user_id'] = $user->id;
        }

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
