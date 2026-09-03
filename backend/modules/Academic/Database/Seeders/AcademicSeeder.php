<?php

namespace Modules\Academic\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Academic\Enums\AcademicStatus;
use Modules\Academic\Enums\DegreeLevel;
use Modules\Academic\Enums\SemesterType;
use Modules\Academic\Models\AcademicYear;
use Modules\Academic\Models\Faculty;
use Modules\Academic\Models\Institution;
use Modules\Academic\Models\Semester;
use Modules\Academic\Models\StudyProgram;

class AcademicSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Institution
        $institution = Institution::firstOrCreate(
            ['code' => 'IAI-001'],
            [
                'name' => 'Institut Agama Islam Al-Irsyad Jakarta',
                'short_name' => 'IAI Al-Irsyad Jakarta',
                'address' => 'Jl. Kramat Raya No. 23, Senen, Jakarta Pusat, DKI Jakarta 10450',
                'phone' => '+62-21-3909123',
                'website' => 'https://alirsyad.ac.id',
                'logo_path' => 'https://alirsyad.ac.id/uploads/settings/img_6a4bccce2e6408.82817006.webp',
                'status' => AcademicStatus::ACTIVE,
            ]
        );

        // 2. Create Faculties
        $ftik = Faculty::firstOrCreate(
            ['code' => 'FTIK'],
            [
                'institution_id' => $institution->id,
                'name' => 'Fakultas Tarbiyah dan Ilmu Keguruan',
                'status' => AcademicStatus::ACTIVE,
            ]
        );

        $fsh = Faculty::firstOrCreate(
            ['code' => 'FSH'],
            [
                'institution_id' => $institution->id,
                'name' => 'Fakultas Syariah dan Hukum',
                'status' => AcademicStatus::ACTIVE,
            ]
        );

        $febi = Faculty::firstOrCreate(
            ['code' => 'FEBI'],
            [
                'institution_id' => $institution->id,
                'name' => 'Fakultas Ekonomi dan Bisnis Islam',
                'status' => AcademicStatus::ACTIVE,
            ]
        );

        $fud = Faculty::firstOrCreate(
            ['code' => 'FUD'],
            [
                'institution_id' => $institution->id,
                'name' => 'Fakultas Ushuluddin dan Dakwah',
                'status' => AcademicStatus::ACTIVE,
            ]
        );

        // 3. Create Study Programs
        $studyPrograms = [
            // FTIK
            [
                'code' => 'PAI',
                'faculty_id' => $ftik->id,
                'name' => 'Pendidikan Agama Islam',
                'degree' => DegreeLevel::S1,
            ],
            [
                'code' => 'PBA',
                'faculty_id' => $ftik->id,
                'name' => 'Pendidikan Bahasa Arab',
                'degree' => DegreeLevel::S1,
            ],
            [
                'code' => 'MPI',
                'faculty_id' => $ftik->id,
                'name' => 'Manajemen Pendidikan Islam',
                'degree' => DegreeLevel::S1,
            ],
            [
                'code' => 'PGMI',
                'faculty_id' => $ftik->id,
                'name' => 'Pendidikan Guru Madrasah Ibtidaiyah',
                'degree' => DegreeLevel::S1,
            ],
            // FSH
            [
                'code' => 'HKI',
                'faculty_id' => $fsh->id,
                'name' => 'Hukum Keluarga Islam (Ahwal Syakhshiyyah)',
                'degree' => DegreeLevel::S1,
            ],
            [
                'code' => 'HES',
                'faculty_id' => $fsh->id,
                'name' => 'Hukum Ekonomi Syariah (Muamalah)',
                'degree' => DegreeLevel::S1,
            ],
            // FEBI
            [
                'code' => 'ES',
                'faculty_id' => $febi->id,
                'name' => 'Ekonomi Syariah',
                'degree' => DegreeLevel::S1,
            ],
            [
                'code' => 'PBS',
                'faculty_id' => $febi->id,
                'name' => 'Perbankan Syariah',
                'degree' => DegreeLevel::S1,
            ],
            // FUD
            [
                'code' => 'IAT',
                'faculty_id' => $fud->id,
                'name' => 'Ilmu Al-Qur\'an dan Tafsir',
                'degree' => DegreeLevel::S1,
            ],
            [
                'code' => 'KPI',
                'faculty_id' => $fud->id,
                'name' => 'Komunikasi dan Penyiaran Islam',
                'degree' => DegreeLevel::S1,
            ],
        ];

        foreach ($studyPrograms as $sp) {
            StudyProgram::firstOrCreate(
                ['code' => $sp['code']],
                [
                    'faculty_id' => $sp['faculty_id'],
                    'name' => $sp['name'],
                    'degree' => $sp['degree'],
                    'status' => AcademicStatus::ACTIVE,
                ]
            );
        }

        // 4. Create Academic Years (Current Active first, then Past/Future)
        $ayCurrent = AcademicYear::firstOrCreate(
            ['name' => '2025/2026'],
            [
                'start_date' => '2025-09-01',
                'end_date' => '2026-08-31',
                'status' => AcademicStatus::ACTIVE,
            ]
        );

        $ayPast = AcademicYear::firstOrCreate(
            ['name' => '2024/2025'],
            [
                'start_date' => '2024-09-01',
                'end_date' => '2025-08-31',
                'status' => AcademicStatus::INACTIVE,
            ]
        );

        // 5. Create Semesters (Active Semester Ganjil 2025/2026 is ID 1)
        Semester::firstOrCreate(
            [
                'academic_year_id' => $ayCurrent->id,
                'name' => 'Ganjil 2025/2026',
            ],
            [
                'type' => SemesterType::GANJIL,
                'start_date' => '2025-09-01',
                'end_date' => '2026-01-31',
                'status' => AcademicStatus::ACTIVE,
            ]
        );

        Semester::firstOrCreate(
            [
                'academic_year_id' => $ayCurrent->id,
                'name' => 'Genap 2025/2026',
            ],
            [
                'type' => SemesterType::GENAP,
                'start_date' => '2026-02-01',
                'end_date' => '2026-06-30',
                'status' => AcademicStatus::INACTIVE,
            ]
        );

        Semester::firstOrCreate(
            [
                'academic_year_id' => $ayPast->id,
                'name' => 'Ganjil 2024/2025',
            ],
            [
                'type' => SemesterType::GANJIL,
                'start_date' => '2024-09-01',
                'end_date' => '2025-01-31',
                'status' => AcademicStatus::INACTIVE,
            ]
        );

        Semester::firstOrCreate(
            [
                'academic_year_id' => $ayPast->id,
                'name' => 'Genap 2024/2025',
            ],
            [
                'type' => SemesterType::GENAP,
                'start_date' => '2025-02-01',
                'end_date' => '2025-06-30',
                'status' => AcademicStatus::INACTIVE,
            ]
        );
    }
}
