<?php

namespace Modules\Curriculum\Actions;

use Modules\Academic\Enums\DegreeLevel;
use Modules\Academic\Models\StudyProgram;
use Modules\Audit\Services\AuditService;
use Modules\Curriculum\Enums\CurriculumStatus;
use Modules\Curriculum\Models\Curriculum;

class CreateCurriculumAction
{
    /**
     * Default SKS yang dianjurkan per semester. Nilainya boleh diubah lewat
     * `PUT /api/v1/curricula/{curriculum}/semesters/{semester}`, jadi ini hanya titik awal.
     */
    private const DEFAULT_RECOMMENDED_CREDITS = 20;

    public function execute(array $data): Curriculum
    {
        if (!isset($data['status'])) {
            $data['status'] = CurriculumStatus::DRAFT;
        }

        $curriculum = Curriculum::create($data);

        // Kurikulum butuh satu baris semester sebagai kerangka penempatan mata kuliah.
        // Jumlahnya mengikuti jenjang program studi, bukan angka tetap: D3 memang 6
        // semester, S2 4 semester.
        $semesterCount = $this->semesterCountFor($data['study_program_id'] ?? null);

        for ($i = 1; $i <= $semesterCount; $i++) {
            $curriculum->semesters()->create([
                'semester_number' => $i,
                'name' => "Semester {$i}",
                'recommended_credits' => self::DEFAULT_RECOMMENDED_CREDITS,
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

    /**
     * Jumlah semester normal menurut jenjang. Jenjang yang tidak dikenali (mis. data
     * lama yang kosong) diperlakukan sebagai sarjana.
     */
    private function semesterCountFor(int|string|null $studyProgramId): int
    {
        $degree = $studyProgramId
            ? StudyProgram::find($studyProgramId)?->degree
            : null;

        if (is_string($degree)) {
            $degree = DegreeLevel::tryFrom($degree);
        }

        return match ($degree) {
            DegreeLevel::D3 => 6,
            DegreeLevel::S2 => 4,
            DegreeLevel::S3 => 6,
            default => 8,
        };
    }
}
