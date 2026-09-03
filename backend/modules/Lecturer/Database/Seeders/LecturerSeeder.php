<?php

namespace Modules\Lecturer\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Academic\Models\StudyProgram;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\User;
use Modules\Lecturer\Enums\LecturerStatus;
use Modules\Lecturer\Models\Lecturer;
use Modules\Student\Enums\Gender;

class LecturerSeeder extends Seeder
{
    public function run(): void
    {
        $dosenUser = User::where('email', 'dosen@siakad.ac.id')->first();
        $pai = StudyProgram::where('code', 'PAI')->first();
        $pba = StudyProgram::where('code', 'PBA')->first();
        $hki = StudyProgram::where('code', 'HKI')->first();
        $hes = StudyProgram::where('code', 'HES')->first();
        $es = StudyProgram::where('code', 'ES')->first();
        $iat = StudyProgram::where('code', 'IAT')->first();

        // 1. Dosen Utama (Linked to dosen@siakad.ac.id)
        $lecturer1 = Lecturer::firstOrCreate(
            ['nidn' => '0011223301'],
            [
                'user_id' => $dosenUser?->id,
                'homebase_study_program_id' => $pai?->id,
                'lecturer_number' => 'DOS-PAI-001',
                'nip' => '198001012005011001',
                'full_name' => 'Dr. Ahmad Dosen, M.Kom',
                'gender' => Gender::MALE,
                'birth_place' => 'Jakarta',
                'birth_date' => '1980-01-01',
                'academic_degree' => 'Dr., M.Kom',
                'functional_position' => 'Lektor Kepala',
                'phone' => '081234567890',
                'email' => 'dosen@siakad.ac.id',
                'address' => 'Jl. Akademisi No. 1, Jakarta Selatan',
                'status' => LecturerStatus::ACTIVE,
                'join_date' => '2005-01-01',
            ]
        );

        if ($lecturer1->educations()->count() === 0) {
            $lecturer1->educations()->createMany([
                ['degree' => 'S1', 'institution_name' => 'UIN Syarif Hidayatullah', 'major' => 'Pendidikan Agama Islam', 'graduation_year' => 2002],
                ['degree' => 'S2', 'institution_name' => 'Universitas Indonesia', 'major' => 'Ilmu Komputer', 'graduation_year' => 2005],
                ['degree' => 'S3', 'institution_name' => 'Universitas Gadjah Mada', 'major' => 'Ilmu Komputer', 'graduation_year' => 2012],
            ]);
        }

        if ($lecturer1->expertises()->count() === 0) {
            $lecturer1->expertises()->createMany([
                ['name' => 'Sistem Informasi Pendidikan', 'description' => 'Riset dalam teknologi informasi kurikulum islam'],
                ['name' => 'Kecerdasan Buatan', 'description' => 'Penerapan AI untuk e-learning pembelajaran islam'],
            ]);
        }

        // Helper to ensure User exists for other lecturers
        $createLecturerUser = function(string $email, string $name) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('password123'),
                    'status' => UserStatus::ACTIVE,
                ]
            );
            $user->assignRole('dosen');
            return $user;
        };

        // 2. Dr. Hj. Siti Fatimah, M.Ag (Syariah)
        $userFatima = $createLecturerUser('siti.fatimah@siakad.ac.id', 'Dr. Hj. Siti Fatimah, M.Ag');
        $lecturer2 = Lecturer::firstOrCreate(
            ['nidn' => '0011223302'],
            [
                'user_id' => $userFatima->id,
                'homebase_study_program_id' => $hki?->id,
                'lecturer_number' => 'DOS-HKI-001',
                'nip' => '198205122008012002',
                'full_name' => 'Dr. Hj. Siti Fatimah, M.Ag',
                'gender' => Gender::FEMALE,
                'birth_place' => 'Surabaya',
                'birth_date' => '1982-05-12',
                'academic_degree' => 'Dr., M.Ag',
                'functional_position' => 'Lektor',
                'phone' => '081234567891',
                'email' => 'siti.fatimah@siakad.ac.id',
                'address' => 'Jl. Syariah No. 5, Surabaya',
                'status' => LecturerStatus::ACTIVE,
                'join_date' => '2008-01-01',
            ]
        );
        if (!$lecturer2->user_id) {
            $lecturer2->update(['user_id' => $userFatima->id]);
        }

        if ($lecturer2->educations()->count() === 0) {
            $lecturer2->educations()->createMany([
                ['degree' => 'S1', 'institution_name' => 'UIN Sunan Ampel Surabaya', 'major' => 'Al-Ahwal Asy-Syakhshiyyah', 'graduation_year' => 2004],
                ['degree' => 'S2', 'institution_name' => 'UIN Sunan Kalijaga Yogyakarta', 'major' => 'Hukum Islam', 'graduation_year' => 2007],
                ['degree' => 'S3', 'institution_name' => 'UIN Syarif Hidayatullah Jakarta', 'major' => 'Studi Islam / Hukum Islam', 'graduation_year' => 2015],
            ]);
        }

        if ($lecturer2->expertises()->count() === 0) {
            $lecturer2->expertises()->createMany([
                ['name' => 'Hukum Perkawinan Islam', 'description' => 'Kajian komparasi fikih munakahat dan hukum positif'],
                ['name' => 'Ushul Fiqh Kontemporer', 'description' => 'Ijtihad dan metodologi istinbath hukum islam'],
            ]);
        }

        // 3. Muhammad Zaid, M.E.Sy (Ekonomi Syariah)
        $userZaid = $createLecturerUser('muhammad.zaid@siakad.ac.id', 'Muhammad Zaid, M.E.Sy');
        $lecturer3 = Lecturer::firstOrCreate(
            ['nidn' => '0011223303'],
            [
                'user_id' => $userZaid->id,
                'homebase_study_program_id' => $es?->id,
                'lecturer_number' => 'DOS-ES-001',
                'nip' => '198507202010011003',
                'full_name' => 'Muhammad Zaid, M.E.Sy',
                'gender' => Gender::MALE,
                'birth_place' => 'Yogyakarta',
                'birth_date' => '1985-07-20',
                'academic_degree' => 'M.E.Sy',
                'functional_position' => 'Asisten Ahli',
                'phone' => '081234567892',
                'email' => 'muhammad.zaid@siakad.ac.id',
                'address' => 'Jl. Kaliurang KM 5, Yogyakarta',
                'status' => LecturerStatus::ACTIVE,
                'join_date' => '2010-01-01',
            ]
        );
        if (!$lecturer3->user_id) {
            $lecturer3->update(['user_id' => $userZaid->id]);
        }

        // 4. Prof. Dr. H. Lukman Hakim, M.A. (Tafsir & Hadis)
        $userLukman = $createLecturerUser('lukman.hakim@siakad.ac.id', 'Prof. Dr. H. Lukman Hakim, M.A.');
        $lecturer4 = Lecturer::firstOrCreate(
            ['nidn' => '0011223304'],
            [
                'user_id' => $userLukman->id,
                'homebase_study_program_id' => $iat?->id,
                'lecturer_number' => 'DOS-IAT-001',
                'nip' => '197003151995031001',
                'full_name' => 'Prof. Dr. H. Lukman Hakim, M.A.',
                'gender' => Gender::MALE,
                'birth_place' => 'Cirebon',
                'birth_date' => '1970-03-15',
                'academic_degree' => 'Prof. Dr., M.A.',
                'functional_position' => 'Guru Besar',
                'phone' => '081234567893',
                'email' => 'lukman.hakim@siakad.ac.id',
                'address' => 'Jl. Sunan Gunung Jati No. 12, Cirebon',
                'status' => LecturerStatus::ACTIVE,
                'join_date' => '1995-03-01',
            ]
        );
        if (!$lecturer4->user_id) {
            $lecturer4->update(['user_id' => $userLukman->id]);
        }

        // 5. Dr. Nurul Hidayah, M.Pd. (PBA & Linguistik)
        $userNurul = $createLecturerUser('nurul.hidayah@siakad.ac.id', 'Dr. Nurul Hidayah, M.Pd.');
        $lecturer5 = Lecturer::firstOrCreate(
            ['nidn' => '0011223305'],
            [
                'user_id' => $userNurul->id,
                'homebase_study_program_id' => $pba?->id,
                'lecturer_number' => 'DOS-PBA-001',
                'nip' => '198811252015042005',
                'full_name' => 'Dr. Nurul Hidayah, M.Pd.',
                'gender' => Gender::FEMALE,
                'birth_place' => 'Malang',
                'birth_date' => '1988-11-25',
                'academic_degree' => 'Dr., M.Pd.',
                'functional_position' => 'Lektor',
                'phone' => '081234567894',
                'email' => 'nurul.hidayah@siakad.ac.id',
                'address' => 'Jl. Ijen No. 88, Malang',
                'status' => LecturerStatus::ACTIVE,
                'join_date' => '2015-04-01',
            ]
        );
        if (!$lecturer5->user_id) {
            $lecturer5->update(['user_id' => $userNurul->id]);
        }

        // 6. Ustadz Ridwan Kamil, Lc., M.H. (Hukum Ekonomi Syariah)
        $userRidwan = $createLecturerUser('ridwan.kamil@siakad.ac.id', 'Ridwan Kamil, Lc., M.H.');
        $lecturer6 = Lecturer::firstOrCreate(
            ['nidn' => '0011223306'],
            [
                'user_id' => $userRidwan->id,
                'homebase_study_program_id' => $hes?->id,
                'lecturer_number' => 'DOS-HES-001',
                'nip' => '199002142018011006',
                'full_name' => 'Ridwan Kamil, Lc., M.H.',
                'gender' => Gender::MALE,
                'birth_place' => 'Bandung',
                'birth_date' => '1990-02-14',
                'academic_degree' => 'Lc., M.H.',
                'functional_position' => 'Asisten Ahli',
                'phone' => '081234567895',
                'email' => 'ridwan.kamil@siakad.ac.id',
                'address' => 'Jl. Dago No. 101, Bandung',
                'status' => LecturerStatus::ACTIVE,
                'join_date' => '2018-01-01',
            ]
        );
        if (!$lecturer6->user_id) {
            $lecturer6->update(['user_id' => $userRidwan->id]);
        }
    }
}
