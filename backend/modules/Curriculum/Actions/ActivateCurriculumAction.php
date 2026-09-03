<?php

namespace Modules\Curriculum\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Audit\Services\AuditService;
use Modules\Curriculum\Enums\CurriculumStatus;
use Modules\Curriculum\Models\Curriculum;

class ActivateCurriculumAction
{
    /**
     * Activate a curriculum after verifying business validation rules.
     *
     * @throws ValidationException
     */
    public function execute(Curriculum $curriculum): Curriculum
    {
        $curriculum->load('semesters.subjects');

        if ($curriculum->semesters->isEmpty()) {
            throw ValidationException::withMessages([
                'curriculum' => ['Curriculum must contain at least one semester before activation.'],
            ]);
        }

        $totalSubjects = $curriculum->semesters->flatMap->subjects->count();
        if ($totalSubjects === 0) {
            throw ValidationException::withMessages([
                'curriculum' => ['Curriculum must contain at least one subject before activation.'],
            ]);
        }

        // Set previous active curriculum for the same study program to inactive
        Curriculum::where('study_program_id', $curriculum->study_program_id)
            ->where('id', '!=', $curriculum->id)
            ->where('status', CurriculumStatus::ACTIVE)
            ->update(['status' => CurriculumStatus::INACTIVE]);

        $oldStatus = $curriculum->status instanceof \BackedEnum ? $curriculum->status->value : $curriculum->status;
        $curriculum->status = CurriculumStatus::ACTIVE;
        $curriculum->effective_date = $curriculum->effective_date ?: now();
        $curriculum->save();

        AuditService::log(
            action: 'activated',
            module: 'Curriculum',
            description: "Curriculum {$curriculum->code} - {$curriculum->name} was activated.",
            entity: $curriculum,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => CurriculumStatus::ACTIVE->value]
        );

        return $curriculum;
    }
}
