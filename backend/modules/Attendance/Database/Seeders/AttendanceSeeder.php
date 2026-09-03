<?php

namespace Modules\Attendance\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Attendance\Enums\AttendanceStatus;
use Modules\Attendance\Enums\SessionStatus;
use Modules\Attendance\Enums\TeachingMethod;
use Modules\Attendance\Models\StudentAttendance;
use Modules\Attendance\Models\TeachingSession;
use Modules\Class\Models\AcademicClass;
use Modules\Enrollment\Models\StudentEnrollment;
use Modules\Enrollment\Models\StudentEnrollmentItem;
use Modules\Lecturer\Models\Lecturer;
use Modules\Student\Models\Student;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $classes = AcademicClass::with(['course', 'lecturers', 'schedules'])->get();
        if ($classes->isEmpty()) {
            return;
        }

        $bapTopics = [
            1 => [
                'topic' => 'Pengantar Perkuliahan, Kontrak Belajar, dan RPS',
                'notes' => 'Pemaparan silabus, target capaian pembelajaran lulusan (CPL), pembagian tugas kelompok, dan tata tertib perkuliahan.',
            ],
            2 => [
                'topic' => 'Landasan Filosofis dan Konsep Dasar Keilmuan',
                'notes' => 'Diskusi komparatif teori klasik dan pemikiran kontemporer. Mahasiswa aktif memberikan tanggapan.',
            ],
            3 => [
                'topic' => 'Metodologi, Pendekatan Epistemologis, dan Ruang Lingkup',
                'notes' => 'Bedah literatur jurnal terakreditasi dan analisis framework kajian.',
            ],
            4 => [
                'topic' => 'Studi Kasus 1: Problematika dan Aplikasi di Lapangan',
                'notes' => 'Presentasi kelompok 1 dan 2 dilanjutkan sesi tanya jawab kritis.',
            ],
            5 => [
                'topic' => 'Workshop Terarah & Penyusunan Laporan Proyek',
                'notes' => 'Bimbingan teknis penyusunan instrumen dan analisis data awal.',
            ],
            6 => [
                'topic' => 'Penguatan Konsep Lanjutan & Diskusi Tematik',
                'notes' => 'Eksplorasi isu mutakhir dan elaborasi solusi berbasis nilai-nilai keislaman.',
            ],
            7 => [
                'topic' => 'Review Materi Perkuliahan Pra-UTS',
                'notes' => 'Rangkuman materi pertemuan 1–6 dan kisi-kisi Ujian Tengah Semester.',
            ],
            8 => [
                'topic' => 'Pelaksanaan & Pembahasan Ujian Tengah Semester (UTS)',
                'notes' => 'Evaluasi capaian belajar paruh semester dan umpan balik hasil ujian.',
            ],
        ];

        foreach ($classes as $class) {
            $lecturer = $class->lecturers->first() ?? Lecturer::first();
            $schedule = $class->schedules->first();
            $roomId = $schedule?->room_id;

            // Fetch enrolled students for this class
            $enrolledEnrollmentIds = StudentEnrollmentItem::where('class_id', $class->id)
                ->pluck('enrollment_id');
            $students = Student::whereIn('id', StudentEnrollment::whereIn('id', $enrolledEnrollmentIds)->pluck('student_id'))->get();

            if ($students->isEmpty()) {
                $students = Student::take(3)->get();
            }

            for ($meeting = 1; $meeting <= 8; $meeting++) {
                $date = now()->subWeeks(9 - $meeting)->format('Y-m-d');
                $status = $meeting <= 7 ? SessionStatus::CLOSED : SessionStatus::OPEN;

                $session = TeachingSession::firstOrCreate(
                    [
                        'academic_class_id' => $class->id,
                        'meeting_number' => $meeting,
                    ],
                    [
                        'schedule_id' => $schedule?->id,
                        'lecturer_id' => $lecturer->id,
                        'session_date' => $date,
                        'start_time' => $schedule?->start_time ?? '08:00',
                        'end_time' => $schedule?->end_time ?? '09:40',
                        'topic' => $bapTopics[$meeting]['topic'] ?? "Pembahasan Materi Pertemuan ke-{$meeting}",
                        'notes' => $bapTopics[$meeting]['notes'] ?? "Perkuliahan berjalan lancar dan interaktif.",
                        'teaching_method' => TeachingMethod::OFFLINE,
                        'room_id' => $roomId,
                        'status' => $status,
                        'check_in_code' => $meeting === 8 ? 'TOKEN8' : null,
                        'check_in_expires_at' => $meeting === 8 ? now()->addHours(3) : null,
                    ]
                );

                foreach ($students as $index => $student) {
                    $attStatus = AttendanceStatus::PRESENT;
                    $notes = null;

                    // Vary attendance realistically
                    if ($index === 1 && $meeting === 4) {
                        $attStatus = AttendanceStatus::PERMIT;
                        $notes = 'Izin delegasi lomba karya tulis ilmiah mahasiswa';
                    } elseif ($index === 2 && $meeting === 2) {
                        $attStatus = AttendanceStatus::SICK;
                        $notes = 'Sakit flu dan demam (surat dokter terlampir)';
                    } elseif ($index === 3 && $meeting === 6) {
                        $attStatus = AttendanceStatus::ABSENT;
                        $notes = 'Tanpa keterangan';
                    }

                    StudentAttendance::firstOrCreate(
                        [
                            'teaching_session_id' => $session->id,
                            'student_id' => $student->id,
                        ],
                        [
                            'academic_class_id' => $class->id,
                            'status' => $attStatus,
                            'notes' => $notes,
                            'recorded_at' => $date . ' 08:15:00',
                        ]
                    );
                }
            }
        }
    }
}
