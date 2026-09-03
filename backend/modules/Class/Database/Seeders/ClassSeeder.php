<?php

namespace Modules\Class\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Academic\Models\Semester;
use Modules\Academic\Models\StudyProgram;
use Modules\Class\Enums\ClassLecturerRole;
use Modules\Class\Enums\ClassStatus;
use Modules\Class\Models\AcademicClass;
use Modules\Course\Models\Course;
use Modules\Lecturer\Models\Lecturer;

class ClassSeeder extends Seeder
{
    public function run(): void
    {
        $activeSemester = Semester::where('status', 'active')->first() ?? Semester::first();
        if (!$activeSemester) {
            return;
        }

        $pai = StudyProgram::where('code', 'PAI')->first();
        $pba = StudyProgram::where('code', 'PBA')->first();
        $hki = StudyProgram::where('code', 'HKI')->first();
        $es = StudyProgram::where('code', 'ES')->first();

        $lecturer1 = Lecturer::where('nidn', '0011223301')->first(); // Dr. Ahmad Dosen
        $lecturer2 = Lecturer::where('nidn', '0011223302')->first(); // Dr. Hj. Siti Fatimah
        $lecturer3 = Lecturer::where('nidn', '0011223303')->first(); // Muhammad Zaid
        $lecturer4 = Lecturer::where('nidn', '0011223304')->first(); // Prof. Dr. Lukman Hakim
        $lecturer5 = Lecturer::where('nidn', '0011223305')->first(); // Dr. Nurul Hidayah
        $lecturer6 = Lecturer::where('nidn', '0011223306')->first(); // Ridwan Kamil, Lc.

        $mku101 = Course::where('code', 'MKU-101')->first();
        $mku102 = Course::where('code', 'MKU-102')->first();
        $mku103 = Course::where('code', 'MKU-103')->first();
        $mku105 = Course::where('code', 'MKU-105')->first();
        $pai201 = Course::where('code', 'PAI-201')->first();
        $pai202 = Course::where('code', 'PAI-202')->first();
        $pai203 = Course::where('code', 'PAI-203')->first();
        $hki201 = Course::where('code', 'HKI-201')->first();
        $es201 = Course::where('code', 'ES-201')->first();

        $classes = [
            // 1. PAI201 Kelas A (Ilmu Pendidikan Islam - Dr. Ahmad Dosen)
            [
                'course' => $pai201,
                'study_program' => $pai,
                'code' => 'PAI201-A',
                'name' => 'Ilmu Pendidikan Islam - Kelas A',
                'section' => 'A',
                'capacity' => 40,
                'status' => ClassStatus::OPEN,
                'lecturer' => $lecturer1,
            ],
            // 2. MKU101 Kelas A (Pancasila - Dr. Ahmad Dosen)
            [
                'course' => $mku101,
                'study_program' => $pai,
                'code' => 'MKU101-A',
                'name' => 'Pancasila dan Kewarganegaraan - Kelas A',
                'section' => 'A',
                'capacity' => 45,
                'status' => ClassStatus::OPEN,
                'lecturer' => $lecturer1,
            ],
            // 3. MKU102 Kelas A (Bahasa Indonesia - Dr. Hj. Siti Fatimah)
            [
                'course' => $mku102,
                'study_program' => $pai,
                'code' => 'MKU102-A',
                'name' => 'Bahasa Indonesia & Tata Tulis Ilmiah - Kelas A',
                'section' => 'A',
                'capacity' => 45,
                'status' => ClassStatus::OPEN,
                'lecturer' => $lecturer2,
            ],
            // 4. MKU103 Kelas A (Bahasa Arab I - Dr. Nurul Hidayah)
            [
                'course' => $mku103,
                'study_program' => $pai,
                'code' => 'MKU103-A',
                'name' => 'Bahasa Arab I (Nahwu Dasar) - Kelas A',
                'section' => 'A',
                'capacity' => 40,
                'status' => ClassStatus::OPEN,
                'lecturer' => $lecturer1,
            ],
            // 5. MKU105 Kelas A (Studi Al-Qur'an - Prof. Dr. Lukman Hakim)
            [
                'course' => $mku105,
                'study_program' => $pai,
                'code' => 'MKU105-A',
                'name' => 'Studi Al-Qur\'an & Ulumul Qur\'an - Kelas A',
                'section' => 'A',
                'capacity' => 45,
                'status' => ClassStatus::OPEN,
                'lecturer' => $lecturer4,
            ],
            // 6. PAI203 Kelas A (Metodologi Pembelajaran PAI - Dr. Ahmad Dosen)
            [
                'course' => $pai203,
                'study_program' => $pai,
                'code' => 'PAI203-A',
                'name' => 'Metodologi Pembelajaran PAI - Kelas A',
                'section' => 'A',
                'capacity' => 35,
                'status' => ClassStatus::OPEN,
                'lecturer' => $lecturer1,
            ],
            // 7. HKI201 Kelas A (Pengantar Hukum Islam - Dr. Hj. Siti Fatimah)
            [
                'course' => $hki201,
                'study_program' => $hki,
                'code' => 'HKI201-A',
                'name' => 'Pengantar Hukum Islam & Ushul Fiqh - Kelas A',
                'section' => 'A',
                'capacity' => 35,
                'status' => ClassStatus::OPEN,
                'lecturer' => $lecturer2,
            ],
            // 8. ES201 Kelas A (Pengantar Ekonomi Islam - Muhammad Zaid)
            [
                'course' => $es201,
                'study_program' => $es,
                'code' => 'ES201-A',
                'name' => 'Pengantar Ekonomi Mikro & Makro Islam - Kelas A',
                'section' => 'A',
                'capacity' => 40,
                'status' => ClassStatus::OPEN,
                'lecturer' => $lecturer3,
            ],
        ];

        foreach ($classes as $c) {
            if (!$c['course']) continue;

            $academicClass = AcademicClass::firstOrCreate(
                [
                    'semester_id' => $activeSemester->id,
                    'course_id' => $c['course']->id,
                    'section' => $c['section'],
                ],
                [
                    'study_program_id' => $c['study_program']?->id,
                    'code' => $c['code'],
                    'name' => $c['name'],
                    'capacity' => $c['capacity'],
                    'status' => $c['status'],
                ]
            );

            if ($c['lecturer']) {
                $academicClass->classLecturers()->firstOrCreate(
                    ['lecturer_id' => $c['lecturer']->id],
                    ['role' => ClassLecturerRole::PRIMARY]
                );
            }
        }
    }
}
