<?php

namespace Modules\Student\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Academic\Models\StudyProgram;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\User;
use Modules\Student\Enums\Gender;
use Modules\Student\Enums\StudentStatus;
use Modules\Student\Models\Student;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $mahasiswaUser = User::where('email', 'mahasiswa@siakad.ac.id')->first();
        $pai = StudyProgram::where('code', 'PAI')->first();
        $pba = StudyProgram::where('code', 'PBA')->first();
        $hki = StudyProgram::where('code', 'HKI')->first();
        $hes = StudyProgram::where('code', 'HES')->first();
        $es = StudyProgram::where('code', 'ES')->first();
        $iat = StudyProgram::where('code', 'IAT')->first();

        // 1. Mahasiswa Utama linked to user (Budi Santoso)
        $student1 = Student::firstOrCreate(
            ['student_number' => '202501001'],
            [
                'user_id' => $mahasiswaUser?->id,
                'study_program_id' => $pai?->id,
                'national_student_number' => '0051234567',
                'national_id' => '3201012345670001',
                'full_name' => 'Budi Santoso',
                'nickname' => 'Budi',
                'gender' => Gender::MALE,
                'birth_place' => 'Bandung',
                'birth_date' => '2005-04-15',
                'religion' => 'Islam',
                'marital_status' => 'Single',
                'phone' => '082112345678',
                'email' => 'mahasiswa@siakad.ac.id',
                'address' => 'Jl. Merdeka No. 45, Bandung',
                'postal_code' => '40115',
                'status' => StudentStatus::ACTIVE,
                'admission_year' => 2025,
                'entry_date' => '2025-09-01',
            ]
        );

        if ($mahasiswaUser && $student1->user_id !== $mahasiswaUser->id) {
            $student1->update(['user_id' => $mahasiswaUser->id]);
        }

        if ($student1->families()->count() === 0) {
            $student1->families()->createMany([
                ['relationship' => 'father', 'full_name' => 'Santoso Hadi', 'phone' => '081298765432', 'occupation' => 'PNS', 'address' => 'Jl. Merdeka No. 45, Bandung'],
                ['relationship' => 'mother', 'full_name' => 'Siti Aminah', 'phone' => '081298765433', 'occupation' => 'Guru', 'address' => 'Jl. Merdeka No. 45, Bandung'],
            ]);
        }

        if ($student1->educations()->count() === 0) {
            $student1->educations()->createMany([
                ['institution_name' => 'MAN 1 Bandung', 'level' => 'MA', 'major' => 'Keagamaan', 'graduation_year' => 2025, 'certificate_number' => 'DN-01/MA/2025/12345'],
            ]);
        }

        // Additional Students
        $students = [
            [
                'student_number' => '202501002',
                'study_program_id' => $pai?->id,
                'national_student_number' => '0051234568',
                'national_id' => '3201012345670002',
                'full_name' => 'Ahmad Dahlan Al-Fatih',
                'nickname' => 'Dahlan',
                'gender' => Gender::MALE,
                'birth_place' => 'Yogyakarta',
                'birth_date' => '2005-02-18',
                'phone' => '082112345679',
                'email' => 'dahlan@example.com',
                'address' => 'Jl. Malioboro No. 12, Yogyakarta',
                'postal_code' => '55271',
                'status' => StudentStatus::ACTIVE,
                'admission_year' => 2025,
            ],
            [
                'student_number' => '202501003',
                'study_program_id' => $pai?->id,
                'national_student_number' => '0051234569',
                'national_id' => '3201012345670003',
                'full_name' => 'Fatimah Az-Zahra',
                'nickname' => 'Zahra',
                'gender' => Gender::FEMALE,
                'birth_place' => 'Solo',
                'birth_date' => '2005-06-25',
                'phone' => '082112345680',
                'email' => 'zahra@example.com',
                'address' => 'Jl. Slamet Riyadi No. 50, Solo',
                'postal_code' => '57121',
                'status' => StudentStatus::ACTIVE,
                'admission_year' => 2025,
            ],
            [
                'student_number' => '202502001',
                'study_program_id' => $pba?->id,
                'national_student_number' => '0051234570',
                'national_id' => '3201012345670004',
                'full_name' => 'Aisyah Rahmawati',
                'nickname' => 'Aisyah',
                'gender' => Gender::FEMALE,
                'birth_place' => 'Semarang',
                'birth_date' => '2005-08-20',
                'phone' => '082112345681',
                'email' => 'aisyah@example.com',
                'address' => 'Jl. Pemuda No. 10, Semarang',
                'postal_code' => '50132',
                'status' => StudentStatus::ACTIVE,
                'admission_year' => 2025,
            ],
            [
                'student_number' => '202502002',
                'study_program_id' => $pba?->id,
                'national_student_number' => '0051234571',
                'national_id' => '3201012345670005',
                'full_name' => 'Muhammad Iqbal Habibie',
                'nickname' => 'Iqbal',
                'gender' => Gender::MALE,
                'birth_place' => 'Malang',
                'birth_date' => '2005-11-12',
                'phone' => '082112345682',
                'email' => 'iqbal@example.com',
                'address' => 'Jl. Soekarno Hatta No. 22, Malang',
                'postal_code' => '65141',
                'status' => StudentStatus::ACTIVE,
                'admission_year' => 2025,
            ],
            [
                'student_number' => '202503001',
                'study_program_id' => $hki?->id,
                'national_student_number' => '0051234572',
                'national_id' => '3201012345670006',
                'full_name' => 'Farhan Al-Ghifari',
                'nickname' => 'Farhan',
                'gender' => Gender::MALE,
                'birth_place' => 'Surakarta',
                'birth_date' => '2005-01-10',
                'phone' => '082112345683',
                'email' => 'farhan@example.com',
                'address' => 'Jl. Veteran No. 8, Surakarta',
                'postal_code' => '57111',
                'status' => StudentStatus::ACTIVE,
                'admission_year' => 2025,
            ],
            [
                'student_number' => '202503002',
                'study_program_id' => $hki?->id,
                'national_student_number' => '0051234573',
                'national_id' => '3201012345670007',
                'full_name' => 'Dewi Sartika An-Nisa',
                'nickname' => 'Dewi',
                'gender' => Gender::FEMALE,
                'birth_place' => 'Cirebon',
                'birth_date' => '2005-09-05',
                'phone' => '082112345684',
                'email' => 'dewi@example.com',
                'address' => 'Jl. Kartini No. 15, Cirebon',
                'postal_code' => '45123',
                'status' => StudentStatus::ACTIVE,
                'admission_year' => 2025,
            ],
            [
                'student_number' => '202504001',
                'study_program_id' => $es?->id,
                'national_student_number' => '0051234574',
                'national_id' => '3201012345670008',
                'full_name' => 'Rizky Ramadhan',
                'nickname' => 'Rizky',
                'gender' => Gender::MALE,
                'birth_place' => 'Jakarta',
                'birth_date' => '2005-10-30',
                'phone' => '082112345685',
                'email' => 'rizky@example.com',
                'address' => 'Jl. Tebet Raya No. 40, Jakarta',
                'postal_code' => '12810',
                'status' => StudentStatus::ACTIVE,
                'admission_year' => 2025,
            ],
            [
                'student_number' => '202505001',
                'study_program_id' => $iat?->id,
                'national_student_number' => '0051234575',
                'national_id' => '3201012345670009',
                'full_name' => 'Nurul Izzati Syarifah',
                'nickname' => 'Izzah',
                'gender' => Gender::FEMALE,
                'birth_place' => 'Kudus',
                'birth_date' => '2005-03-14',
                'phone' => '082112345686',
                'email' => 'izzah@example.com',
                'address' => 'Jl. Sunan Muria No. 7, Kudus',
                'postal_code' => '59312',
                'status' => StudentStatus::ACTIVE,
                'admission_year' => 2025,
            ],
            [
                'student_number' => '202501099',
                'study_program_id' => $pai?->id,
                'national_student_number' => '0051234599',
                'national_id' => '3201012345670099',
                'full_name' => 'Muhammad Fajar Maulana',
                'nickname' => 'Fajar',
                'gender' => Gender::MALE,
                'birth_place' => 'Bandung',
                'birth_date' => '2005-05-10',
                'phone' => '082112345699',
                'email' => 'fajar@example.com',
                'address' => 'Jl. Cibiru Indah No. 12, Bandung',
                'postal_code' => '40614',
                'status' => StudentStatus::ACTIVE,
                'admission_year' => 2025,
            ],
        ];

        foreach ($students as $s) {
            // Create user account for each student with default password password123
            $user = User::firstOrCreate(
                ['email' => $s['email']],
                [
                    'name' => $s['full_name'],
                    'password' => Hash::make('password123'),
                    'status' => UserStatus::ACTIVE,
                ]
            );
            $user->assignRole('mahasiswa');

            $studentModel = Student::firstOrCreate(
                ['student_number' => $s['student_number']],
                array_merge($s, [
                    'user_id' => $user->id,
                    'religion' => 'Islam',
                    'marital_status' => 'Single',
                    'entry_date' => '2025-09-01',
                ])
            );

            if (!$studentModel->user_id) {
                $studentModel->update(['user_id' => $user->id]);
            }

            if ($studentModel->educations()->count() === 0) {
                $studentModel->educations()->create([
                    'institution_name' => 'SMA/MA Islam Terpadu',
                    'level' => 'SMA',
                    'major' => 'IPA / Keagamaan',
                    'graduation_year' => 2025,
                ]);
            }
        }
    }
}
