<?php

namespace Modules\Student\Services;

use Modules\Student\Actions\ChangeStudentStatusAction;
use Modules\Student\Actions\CreateStudentAction;
use Modules\Student\Actions\UpdateStudentAction;
use Modules\Student\Enums\StudentStatus;
use Modules\Student\Models\Student;

class StudentService
{
    public function __construct(
        protected CreateStudentAction $createStudentAction,
        protected UpdateStudentAction $updateStudentAction,
        protected ChangeStudentStatusAction $changeStudentStatusAction
    ) {}

    public function create(array $data): Student
    {
        return $this->createStudentAction->execute($data);
    }

    public function update(Student $student, array $data): Student
    {
        return $this->updateStudentAction->execute($student, $data);
    }

    public function changeStatus(Student $student, StudentStatus|string $status, ?string $notes = null): Student
    {
        return $this->changeStudentStatusAction->execute($student, $status, $notes);
    }
}
