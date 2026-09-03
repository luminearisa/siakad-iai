<?php

namespace Modules\Class\Actions;

use Modules\Audit\Services\AuditService;
use Modules\Class\Enums\ClassStatus;
use Modules\Class\Models\AcademicClass;

class ChangeClassStatusAction
{
    public function execute(AcademicClass $class, ClassStatus|string $status): AcademicClass
    {
        $oldStatus = $class->status instanceof \BackedEnum ? $class->status->value : $class->status;
        $newStatus = $status instanceof ClassStatus ? $status : ClassStatus::from($status);

        $class->status = $newStatus;
        $class->save();

        AuditService::log(
            action: 'status_changed',
            module: 'Class',
            description: "Class {$class->code} status changed from {$oldStatus} to {$newStatus->value}.",
            entity: $class,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => $newStatus->value]
        );

        return $class;
    }
}
