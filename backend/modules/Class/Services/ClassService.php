<?php

namespace Modules\Class\Services;

use Modules\Class\Actions\AssignClassLecturerAction;
use Modules\Class\Actions\ChangeClassStatusAction;
use Modules\Class\Actions\CreateClassAction;
use Modules\Class\Actions\UpdateClassAction;
use Modules\Class\Enums\ClassStatus;
use Modules\Class\Models\AcademicClass;
use Modules\Class\Models\ClassLecturer;

class ClassService
{
    public function __construct(
        protected CreateClassAction $createClassAction,
        protected UpdateClassAction $updateClassAction,
        protected ChangeClassStatusAction $changeClassStatusAction,
        protected AssignClassLecturerAction $assignClassLecturerAction
    ) {}

    public function create(array $data): AcademicClass
    {
        return $this->createClassAction->execute($data);
    }

    public function update(AcademicClass $class, array $data): AcademicClass
    {
        return $this->updateClassAction->execute($class, $data);
    }

    public function changeStatus(AcademicClass $class, ClassStatus|string $status): AcademicClass
    {
        return $this->changeClassStatusAction->execute($class, $status);
    }

    public function assignLecturer(AcademicClass $class, int $lecturerId, string $role = 'primary'): ClassLecturer
    {
        return $this->assignClassLecturerAction->assign($class, $lecturerId, $role);
    }

    public function removeLecturer(AcademicClass $class, int $lecturerId): bool
    {
        return $this->assignClassLecturerAction->remove($class, $lecturerId);
    }
}
