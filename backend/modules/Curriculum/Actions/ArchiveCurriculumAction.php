<?php

namespace Modules\Curriculum\Actions;

use Modules\Audit\Services\AuditService;
use Modules\Curriculum\Enums\CurriculumStatus;
use Modules\Curriculum\Models\Curriculum;

class ArchiveCurriculumAction
{
    public function execute(Curriculum $curriculum): Curriculum
    {
        $oldStatus = $curriculum->status instanceof \BackedEnum ? $curriculum->status->value : $curriculum->status;
        $curriculum->status = CurriculumStatus::ARCHIVED;
        $curriculum->expiry_date = $curriculum->expiry_date ?: now();
        $curriculum->save();

        AuditService::log(
            action: 'archived',
            module: 'Curriculum',
            description: "Curriculum {$curriculum->code} was archived.",
            entity: $curriculum,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => CurriculumStatus::ARCHIVED->value]
        );

        return $curriculum;
    }
}
