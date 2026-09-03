<?php

namespace Modules\Curriculum\Actions;

use Modules\Audit\Services\AuditService;
use Modules\Curriculum\Models\Curriculum;

class UpdateCurriculumAction
{
    public function execute(Curriculum $curriculum, array $data): Curriculum
    {
        $oldValues = $curriculum->toArray();
        $curriculum->update($data);

        AuditService::log(
            action: 'updated',
            module: 'Curriculum',
            description: "Curriculum {$curriculum->code} was updated.",
            entity: $curriculum,
            oldValues: $oldValues,
            newValues: $curriculum->fresh()->toArray()
        );

        return $curriculum->fresh(['studyProgram.faculty', 'semesters.subjects.course']);
    }
}
