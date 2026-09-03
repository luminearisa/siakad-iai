<?php

namespace Modules\Curriculum\Actions;

use Modules\Audit\Services\AuditService;
use Modules\Curriculum\Enums\CurriculumStatus;
use Modules\Curriculum\Models\Curriculum;

class CreateCurriculumAction
{
    public function execute(array $data): Curriculum
    {
        if (!isset($data['status'])) {
            $data['status'] = CurriculumStatus::DRAFT;
        }

        $curriculum = Curriculum::create($data);

        // By default create 8 standard semester placeholders for higher education
        for ($i = 1; $i <= 8; $i++) {
            $curriculum->semesters()->create([
                'semester_number' => $i,
                'name' => "Semester {$i}",
                'recommended_credits' => 20,
            ]);
        }

        AuditService::log(
            action: 'created',
            module: 'Curriculum',
            description: "Curriculum {$curriculum->code} - {$curriculum->name} was created.",
            entity: $curriculum,
            oldValues: null,
            newValues: $curriculum->toArray()
        );

        return $curriculum->load(['studyProgram.faculty', 'semesters.subjects.course']);
    }
}
