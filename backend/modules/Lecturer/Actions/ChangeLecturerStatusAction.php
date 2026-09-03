<?php

namespace Modules\Lecturer\Actions;

use Modules\Audit\Services\AuditService;
use Modules\Lecturer\Enums\LecturerStatus;
use Modules\Lecturer\Models\Lecturer;

class ChangeLecturerStatusAction
{
    public function execute(Lecturer $lecturer, LecturerStatus|string $status, ?string $notes = null): Lecturer
    {
        $oldStatus = $lecturer->status instanceof \BackedEnum ? $lecturer->status->value : $lecturer->status;
        $newStatus = $status instanceof LecturerStatus ? $status : LecturerStatus::from($status);

        $lecturer->status = $newStatus;

        if ($notes !== null) {
            $lecturer->notes = ($lecturer->notes ? $lecturer->notes . "\n" : '') . "[Status Change to {$newStatus->value}]: {$notes}";
        }

        $lecturer->save();

        AuditService::log(
            action: 'status_changed',
            module: 'Lecturer',
            description: "Lecturer {$lecturer->full_name} status changed from {$oldStatus} to {$newStatus->value}.",
            entity: $lecturer,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => $newStatus->value, 'notes' => $notes]
        );

        return $lecturer;
    }
}
