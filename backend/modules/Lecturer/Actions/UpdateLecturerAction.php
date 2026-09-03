<?php

namespace Modules\Lecturer\Actions;

use Modules\Audit\Services\AuditService;
use Modules\Lecturer\Models\Lecturer;

class UpdateLecturerAction
{
    public function execute(Lecturer $lecturer, array $data): Lecturer
    {
        $oldValues = $lecturer->toArray();
        $lecturerData = collect($data)->except(['status', 'educations', 'expertises'])->toArray();

        $lecturer->update($lecturerData);

        AuditService::log(
            action: 'updated',
            module: 'Lecturer',
            description: "Lecturer {$lecturer->full_name} was updated.",
            entity: $lecturer,
            oldValues: $oldValues,
            newValues: $lecturer->fresh()->toArray()
        );

        return $lecturer->fresh(['homebaseStudyProgram.faculty', 'educations', 'expertises']);
    }
}
