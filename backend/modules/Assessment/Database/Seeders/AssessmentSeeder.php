<?php

namespace Modules\Assessment\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Assessment\Enums\ComponentType;
use Modules\Assessment\Enums\SchemeStatus;
use Modules\Assessment\Models\AssessmentComponent;
use Modules\Assessment\Models\AssessmentScheme;
use Modules\Assessment\Models\AssessmentSchemeItem;
use Modules\Class\Models\AcademicClass;

class AssessmentSeeder extends Seeder
{
    public function run(): void
    {
        $classes = AcademicClass::all();

        foreach ($classes as $class) {
            // 1. Create Assessment Components
            $tugas = AssessmentComponent::firstOrCreate(
                [
                    'academic_class_id' => $class->id,
                    'code' => 'TUGAS',
                ],
                [
                    'name' => 'Tugas Mandiri & Terstruktur',
                    'type' => ComponentType::ASSIGNMENT,
                    'max_score' => 100.00,
                    'is_required' => true,
                    'sequence' => 1,
                ]
            );

            $kuis = AssessmentComponent::firstOrCreate(
                [
                    'academic_class_id' => $class->id,
                    'code' => 'KUIS',
                ],
                [
                    'name' => 'Kuis & Evaluasi Harian',
                    'type' => ComponentType::QUIZ,
                    'max_score' => 100.00,
                    'is_required' => false,
                    'sequence' => 2,
                ]
            );

            $uts = AssessmentComponent::firstOrCreate(
                [
                    'academic_class_id' => $class->id,
                    'code' => 'UTS',
                ],
                [
                    'name' => 'Ujian Tengah Semester (UTS)',
                    'type' => ComponentType::MIDTERM,
                    'max_score' => 100.00,
                    'is_required' => true,
                    'sequence' => 3,
                ]
            );

            $uas = AssessmentComponent::firstOrCreate(
                [
                    'academic_class_id' => $class->id,
                    'code' => 'UAS',
                ],
                [
                    'name' => 'Ujian Akhir Semester (UAS)',
                    'type' => ComponentType::FINAL_EXAM,
                    'max_score' => 100.00,
                    'is_required' => true,
                    'sequence' => 4,
                ]
            );

            $keaktifan = AssessmentComponent::firstOrCreate(
                [
                    'academic_class_id' => $class->id,
                    'code' => 'PARTICIPATION',
                ],
                [
                    'name' => 'Keaktifan & Partisipasi Diskusi',
                    'type' => ComponentType::PARTICIPATION,
                    'max_score' => 100.00,
                    'is_required' => false,
                    'sequence' => 5,
                ]
            );

            // 2. Create Active Assessment Scheme (100% total weight)
            $scheme = AssessmentScheme::firstOrCreate(
                [
                    'academic_class_id' => $class->id,
                    'name' => 'Skema Penilaian OBE Standar',
                ],
                [
                    'description' => 'Bobot penilaian standar: Tugas 20%, Kuis 10%, UTS 30%, UAS 35%, Partisipasi 5%.',
                    'status' => SchemeStatus::ACTIVE,
                    'total_weight' => 100.00,
                    'is_active' => true,
                ]
            );

            // 3. Attach components with weights (sum = 100)
            $weights = [
                $tugas->id => 20.00,
                $kuis->id => 10.00,
                $uts->id => 30.00,
                $uas->id => 35.00,
                $keaktifan->id => 5.00,
            ];

            foreach ($weights as $compId => $w) {
                AssessmentSchemeItem::firstOrCreate(
                    [
                        'assessment_scheme_id' => $scheme->id,
                        'assessment_component_id' => $compId,
                    ],
                    [
                        'weight' => $w,
                    ]
                );
            }

            $scheme->recalculateTotalWeight();
        }
    }
}
