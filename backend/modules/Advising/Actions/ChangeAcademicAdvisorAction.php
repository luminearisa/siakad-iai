<?php

namespace Modules\Advising\Actions;

use Modules\Advising\Enums\AdvisorStatus;
use Modules\Advising\Models\AcademicAdvisor;
use Modules\Audit\Services\AuditService;

class ChangeAcademicAdvisorAction
{
    public function __construct(
        protected AssignAcademicAdvisorAction $assignAction
    ) {}

    public function execute(int $studentId, int $newLecturerId, ?string $changeDate = null, ?string $reason = null): AcademicAdvisor
    {
        return $this->assignAction->execute($studentId, $newLecturerId, $changeDate, $reason);
    }
}
