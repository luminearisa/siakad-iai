<?php

namespace Modules\Graduation\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Academic\Models\Semester;
use Modules\Academic\Models\StudyProgram;
use Modules\Graduation\Models\YudisiumParticipant;
use Modules\Graduation\Models\YudisiumPeriod;
use Modules\Graduation\Models\YudisiumRequirement;
use Modules\Student\Models\Student;

class GraduationSeeder extends Seeder
{
    public function run(): void
    {
        $semester = Semester::first();
        $prodi = StudyProgram::first();

        // 1. Periods (Matching Screenshot 1)
        $p1 = YudisiumPeriod::create([
            'semester_id' => $semester?->id,
            'name' => 'Yudisium 75',
            'registration_start_date' => '2026-07-13',
            'registration_end_date' => '2026-07-31',
            'yudisium_date' => '2026-08-01',
            'is_active' => false,
        ]);

        $p2 = YudisiumPeriod::create([
            'semester_id' => $semester?->id,
            'name' => 'Yudisium Saat ini',
            'registration_start_date' => '2026-07-03',
            'registration_end_date' => '2026-07-31',
            'yudisium_date' => '2026-08-07',
            'is_active' => true,
        ]);

        // 2. Requirements
        YudisiumRequirement::create([
            'study_program_id' => $prodi?->id,
            'name' => 'Bebas Pustaka',
            'code' => 'REQ-LIB',
            'is_document' => true,
            'is_mandatory' => true,
            'min_credits' => 144,
            'min_gpa' => 2.00,
        ]);

        YudisiumRequirement::create([
            'study_program_id' => $prodi?->id,
            'name' => 'Naskah Publikasi Ilmiah / Jurnal',
            'code' => 'REQ-PUB',
            'is_document' => true,
            'is_mandatory' => true,
            'min_credits' => 144,
            'min_gpa' => 2.00,
        ]);

        YudisiumRequirement::create([
            'study_program_id' => $prodi?->id,
            'name' => 'Sertifikat TOEFL Score >= 450',
            'code' => 'REQ-TOEFL',
            'is_document' => true,
            'is_mandatory' => false,
            'min_credits' => 144,
            'min_gpa' => 2.00,
        ]);

        // 3. Create / find sample students for Participants (Screenshot 2: yudi bahtera, ABC, Difa, Dani)
        $sampleStudents = [
            ['student_number' => '0401', 'full_name' => 'yudi bahtera'],
            ['student_number' => '202501004', 'full_name' => 'ABC'],
            ['student_number' => '0403', 'full_name' => 'Difa'],
            ['student_number' => '0405', 'full_name' => 'Dani'],
        ];

        foreach ($sampleStudents as $sData) {
            $student = Student::firstOrCreate(
                ['student_number' => $sData['student_number']],
                [
                    'study_program_id' => $prodi?->id ?? 1,
                    'full_name' => $sData['full_name'],
                    'gender' => 'male',
                    'status' => 'active',
                    'admission_year' => 2022,
                    'entry_date' => '2022-09-01',
                ]
            );

            YudisiumParticipant::create([
                'yudisium_period_id' => $p2->id,
                'student_id' => $student->id,
                'application_date' => '2026-07-20',
                'status' => 'passed',
                'sk_number' => 'SK1',
                'sk_date' => '2026-08-21',
                'is_certificate_taken' => false,
                'total_credits' => 144,
                'gpa' => 3.65,
                'study_duration_days' => 1420,
            ]);
        }
    }
}
