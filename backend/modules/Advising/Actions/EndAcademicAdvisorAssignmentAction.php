<?php

namespace Modules\Advising\Actions;

use Modules\Advising\Enums\AdvisorStatus;
use Modules\Advising\Models\AcademicAdvisor;
use Modules\Audit\Services\AuditService;

class EndAcademicAdvisorAssignmentAction
{
    public function execute(AcademicAdvisor $advisor, ?string $endDate = null, ?string $notes = null): AcademicAdvisor
    {
        $oldValues = $advisor->toArray();
        $advisor->status = AdvisorStatus::COMPLETED;
        $advisor->end_date = $endDate ?? now()->format('Y-m-d');
        if ($notes) {
            $advisor->notes = ($advisor->notes ? $advisor->notes . "\n" : '') . "[Ended]: {$notes}";
        }
        $advisor->save();

        AuditService::log(
            action: 'advisor_ended',
            module: 'Advising',
            description: "Academic advisor assignment #{$advisor->id} ended.",
            entity: $advisor,
            oldValues: $oldValues,
            newValues: $advisor->fresh()->toArray()
        );

        return $advisor;
    }
}
