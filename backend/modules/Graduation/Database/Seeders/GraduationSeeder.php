<?php

namespace Modules\Graduation\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Academic\Models\Semester;
use Modules\Academic\Models\StudyProgram;
use Modules\Graduation\Models\YudisiumPeriod;
use Modules\Graduation\Models\YudisiumRequirement;

/**
 * Menyiapkan kerangka yudisium: periode dan persyaratan.
 *
 * Seeder ini sengaja TIDAK membuat mahasiswa atau peserta contoh. Peserta yudisium
 * ditentukan oleh data nyata (KRS, nilai, dan kelayakan mahasiswa), sehingga data
 * buatan di sini akan langsung terbaca sebagai kelulusan palsu di portal dan di
 * laporan PDDikti.
 */
class GraduationSeeder extends Seeder
{
    public function run(): void
    {
        $semester = Semester::first();
        $prodi = StudyProgram::first();

        // 1. Periode yudisium
        YudisiumPeriod::firstOrCreate(
            ['name' => 'Yudisium 75', 'semester_id' => $semester?->id],
            [
                'registration_start_date' => '2026-07-13',
                'registration_end_date' => '2026-07-31',
                'yudisium_date' => '2026-08-01',
                'is_active' => false,
            ]
        );

        YudisiumPeriod::firstOrCreate(
            ['name' => 'Yudisium 76', 'semester_id' => $semester?->id],
            [
                'registration_start_date' => '2026-07-03',
                'registration_end_date' => '2026-07-31',
                'yudisium_date' => '2026-08-07',
                'is_active' => true,
            ]
        );

        // 2. Persyaratan yudisium
        $requirements = [
            [
                'name' => 'Bebas Pustaka',
                'code' => 'REQ-LIB',
                'is_mandatory' => true,
            ],
            [
                'name' => 'Naskah Publikasi Ilmiah / Jurnal',
                'code' => 'REQ-PUB',
                'is_mandatory' => true,
            ],
            [
                'name' => 'Sertifikat TOEFL Score >= 450',
                'code' => 'REQ-TOEFL',
                'is_mandatory' => false,
            ],
        ];

        foreach ($requirements as $requirement) {
            YudisiumRequirement::firstOrCreate(
                ['study_program_id' => $prodi?->id, 'code' => $requirement['code']],
                $requirement + [
                    'is_document' => true,
                    'min_credits' => 144,
                    'min_gpa' => 2.00,
                ]
            );
        }
    }
}
