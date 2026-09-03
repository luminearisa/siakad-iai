<?php

namespace Modules\Curriculum\Services;

use Modules\Curriculum\Actions\ActivateCurriculumAction;
use Modules\Curriculum\Actions\ArchiveCurriculumAction;
use Modules\Curriculum\Actions\CreateCurriculumAction;
use Modules\Curriculum\Actions\ManageCurriculumSubjectsAction;
use Modules\Curriculum\Actions\UpdateCurriculumAction;
use Modules\Curriculum\Models\Curriculum;
use Modules\Curriculum\Models\CurriculumSemester;
use Modules\Curriculum\Models\CurriculumSubject;

class CurriculumService
{
    public function __construct(
        protected CreateCurriculumAction $createCurriculumAction,
        protected UpdateCurriculumAction $updateCurriculumAction,
        protected ActivateCurriculumAction $activateCurriculumAction,
        protected ArchiveCurriculumAction $archiveCurriculumAction,
        protected ManageCurriculumSubjectsAction $manageCurriculumSubjectsAction
    ) {}

    public function create(array $data): Curriculum
    {
        return $this->createCurriculumAction->execute($data);
    }

    public function update(Curriculum $curriculum, array $data): Curriculum
    {
        return $this->updateCurriculumAction->execute($curriculum, $data);
    }

    public function activate(Curriculum $curriculum): Curriculum
    {
        return $this->activateCurriculumAction->execute($curriculum);
    }

    public function archive(Curriculum $curriculum): Curriculum
    {
        return $this->archiveCurriculumAction->execute($curriculum);
    }

    public function addSubject(CurriculumSemester $semester, array $data): CurriculumSubject
    {
        return $this->manageCurriculumSubjectsAction->addSubject($semester, $data);
    }

    public function removeSubject(CurriculumSubject $subject): bool
    {
        return $this->manageCurriculumSubjectsAction->removeSubject($subject);
    }
}
