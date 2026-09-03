<?php

namespace Modules\Advising\Services;

use Modules\Advising\Actions\AssignAcademicAdvisorAction;
use Modules\Advising\Actions\ChangeAcademicAdvisorAction;
use Modules\Advising\Actions\CreateAdvisingSessionAction;
use Modules\Advising\Actions\EndAcademicAdvisorAssignmentAction;
use Modules\Advising\Actions\UpdateAdvisingSessionAction;
use Modules\Advising\Models\AcademicAdvisor;
use Modules\Advising\Models\AdvisingSession;

class AdvisingService
{
    public function __construct(
        protected AssignAcademicAdvisorAction $assignAdvisorAction,
        protected ChangeAcademicAdvisorAction $changeAdvisorAction,
        protected EndAcademicAdvisorAssignmentAction $endAdvisorAction,
        protected CreateAdvisingSessionAction $createSessionAction,
        protected UpdateAdvisingSessionAction $updateSessionAction
    ) {}

    public function assign(int $studentId, int $lecturerId, ?string $startDate = null, ?string $notes = null): AcademicAdvisor
    {
        return $this->assignAdvisorAction->execute($studentId, $lecturerId, $startDate, $notes);
    }

    public function change(int $studentId, int $newLecturerId, ?string $changeDate = null, ?string $reason = null): AcademicAdvisor
    {
        return $this->changeAdvisorAction->execute($studentId, $newLecturerId, $changeDate, $reason);
    }

    public function end(AcademicAdvisor $advisor, ?string $endDate = null, ?string $notes = null): AcademicAdvisor
    {
        return $this->endAdvisorAction->execute($advisor, $endDate, $notes);
    }

    public function createSession(array $data): AdvisingSession
    {
        return $this->createSessionAction->execute($data);
    }

    public function updateSession(AdvisingSession $session, array $data): AdvisingSession
    {
        return $this->updateSessionAction->execute($session, $data);
    }
}
