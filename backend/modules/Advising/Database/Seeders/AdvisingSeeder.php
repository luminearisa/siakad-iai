<?php

namespace Modules\Advising\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Advising\Enums\AdvisingSessionStatus;
use Modules\Advising\Enums\AdvisorStatus;
use Modules\Advising\Models\AcademicAdvisor;
use Modules\Advising\Models\AdvisingSession;
use Modules\Lecturer\Models\Lecturer;
use Modules\Student\Models\Student;

class AdvisingSeeder extends Seeder
{
    public function run(): void
    {
        $std1 = Student::where('student_number', '202501001')->first(); // Budi Santoso (PAI)
        $std2 = Student::where('student_number', '202501002')->first(); // Ahmad Dahlan (PAI)
        $std3 = Student::where('student_number', '202501003')->first(); // Fatimah Az-Zahra (PAI)
        $std4 = Student::where('student_number', '202502001')->first(); // Aisyah (PBA)
        $std5 = Student::where('student_number', '202503001')->first(); // Farhan (HKI)
        $std6 = Student::where('student_number', '202504001')->first(); // Rizky (ES)

        $lec1 = Lecturer::where('nidn', '0011223301')->first(); // Dr. Ahmad Dosen (PAI)
        $lec2 = Lecturer::where('nidn', '0011223302')->first(); // Dr. Hj. Siti Fatimah (HKI)
        $lec3 = Lecturer::where('nidn', '0011223303')->first(); // Muhammad Zaid (ES)
        $lec5 = Lecturer::where('nidn', '0011223305')->first(); // Dr. Nurul Hidayah (PBA)

        // 1. Dr. Ahmad Dosen -> PA for Budi Santoso & Ahmad Dahlan
        if ($std1 && $lec1) {
            AcademicAdvisor::firstOrCreate(
                [
                    'student_id' => $std1->id,
                    'lecturer_id' => $lec1->id,
                ],
                [
                    'start_date' => '2025-09-01',
                    'status' => AdvisorStatus::ACTIVE,
                    'notes' => 'Pembimbing Akademik Mahasiswa Angkatan 2025 - SK Dekan FTIK No 10/2025.',
                ]
            );

            AdvisingSession::firstOrCreate(
                [
                    'student_id' => $std1->id,
                    'lecturer_id' => $lec1->id,
                    'topic' => 'Konsultasi Rencana Studi & Pengisian KRS Semester Gasal',
                ],
                [
                    'session_date' => '2025-09-02',
                    'notes' => 'Mahasiswa mengkonsultasikan pengambilan 7 SKS (Ilmu Pendidikan Islam, Pancasila, dan Bahasa Indonesia). Pembimbing menyetujui dan memberikan arahan manajemen waktu.',
                    'status' => AdvisingSessionStatus::COMPLETED,
                ]
            );

            AdvisingSession::firstOrCreate(
                [
                    'student_id' => $std1->id,
                    'lecturer_id' => $lec1->id,
                    'topic' => 'Evaluasi Kemajuan Belajar Tengah Semester',
                ],
                [
                    'session_date' => '2025-10-25',
                    'notes' => 'Diskusi persiapan UTS dan pemahaman materi dasar pedagogi islam. Mahasiswa aktif dalam perkuliahan.',
                    'status' => AdvisingSessionStatus::COMPLETED,
                ]
            );
        }

        if ($std2 && $lec1) {
            AcademicAdvisor::firstOrCreate(
                [
                    'student_id' => $std2->id,
                    'lecturer_id' => $lec1->id,
                ],
                [
                    'start_date' => '2025-09-01',
                    'status' => AdvisorStatus::ACTIVE,
                    'notes' => 'Pembimbing Akademik Angkatan 2025.',
                ]
            );

            AdvisingSession::firstOrCreate(
                [
                    'student_id' => $std2->id,
                    'lecturer_id' => $lec1->id,
                    'topic' => 'Konsultasi Penyesuaian Beban SKS Semester 1',
                ],
                [
                    'session_date' => '2025-09-03',
                    'notes' => 'Mahasiswa berkonsultasi mengenai mata kuliah pilihan bahasa.',
                    'status' => AdvisingSessionStatus::COMPLETED,
                ]
            );
        }

        // 2. Dr. Nurul Hidayah -> PA for Aisyah (PBA)
        if ($std4 && $lec5) {
            AcademicAdvisor::firstOrCreate(
                [
                    'student_id' => $std4->id,
                    'lecturer_id' => $lec5->id,
                ],
                [
                    'start_date' => '2025-09-01',
                    'status' => AdvisorStatus::ACTIVE,
                    'notes' => 'Penugasan PA Prodi PBA.',
                ]
            );

            AdvisingSession::firstOrCreate(
                [
                    'student_id' => $std4->id,
                    'lecturer_id' => $lec5->id,
                    'topic' => 'Bimbingan Peningkatan Kemahiran Bahasa Arab',
                ],
                [
                    'session_date' => '2025-09-05',
                    'notes' => 'Rekomendasi buku rujukan qawaid dan program intensif muhadatsah.',
                    'status' => AdvisingSessionStatus::COMPLETED,
                ]
            );
        }

        // 3. Dr. Hj. Siti Fatimah -> PA for Farhan (HKI)
        if ($std5 && $lec2) {
            AcademicAdvisor::firstOrCreate(
                [
                    'student_id' => $std5->id,
                    'lecturer_id' => $lec2->id,
                ],
                [
                    'start_date' => '2025-09-01',
                    'status' => AdvisorStatus::ACTIVE,
                    'notes' => 'Penugasan PA FSH HKI.',
                ]
            );
        }

        // 4. Muhammad Zaid -> PA for Rizky (ES)
        if ($std6 && $lec3) {
            AcademicAdvisor::firstOrCreate(
                [
                    'student_id' => $std6->id,
                    'lecturer_id' => $lec3->id,
                ],
                [
                    'start_date' => '2025-09-01',
                    'status' => AdvisorStatus::ACTIVE,
                    'notes' => 'Penugasan PA FEBI ES.',
                ]
            );
        }

        // 5. Dr. Ahmad Dosen -> PA for Fajar (PAI - Belum KRS)
        $stdFajar = Student::where('student_number', '202501099')->first();
        if ($stdFajar && $lec1) {
            AcademicAdvisor::firstOrCreate(
                [
                    'student_id' => $stdFajar->id,
                    'lecturer_id' => $lec1->id,
                ],
                [
                    'start_date' => '2025-09-01',
                    'status' => AdvisorStatus::ACTIVE,
                    'notes' => 'Pembimbing Akademik Mahasiswa Baru 2025 - Belum Mengisi KRS.',
                ]
            );
        }
    }
}
