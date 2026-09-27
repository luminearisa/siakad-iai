<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Attendance\Enums\AttendanceStatus;
use Modules\Attendance\Enums\SessionStatus;
use Modules\Attendance\Models\StudentAttendance;
use Modules\Attendance\Models\TeachingSession;
use Modules\Attendance\Support\AttendancePolicy;
use Modules\Audit\Models\AuditLog;
use Modules\Class\Models\AcademicClass;
use Modules\Identity\Models\User;
use Modules\Lecturer\Models\Lecturer;
use Modules\Student\Models\Student;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected string $adminToken;

    protected string $dosenToken;

    protected string $studentToken;

    protected User $admin;

    protected User $dosenUser;

    protected User $studentUser;

    protected AcademicClass $ownClass;

    protected AcademicClass $foreignClass;

    protected TeachingSession $openSession;

    protected TeachingSession $closedSession;

    protected Lecturer $lecturer;

    protected Student $student;

    protected Student $pendingStudent;

    protected Student $foreignStudent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@siakad.ac.id')->first();
        $this->adminToken = $this->admin->createToken('admin_token')->plainTextToken;

        $this->dosenUser = User::where('email', 'dosen@siakad.ac.id')->first();
        $this->dosenToken = $this->dosenUser->createToken('dosen_token')->plainTextToken;

        $this->studentUser = User::where('email', 'mahasiswa@siakad.ac.id')->first();
        $this->studentToken = $this->studentUser->createToken('student_token')->plainTextToken;

        // PAI201-A diampu dosen@siakad (nidn 0011223301); HKI201-A diampu dosen lain.
        $this->ownClass = AcademicClass::where('code', 'PAI201-A')->firstOrFail();
        $this->foreignClass = AcademicClass::where('code', 'HKI201-A')->firstOrFail();
        $this->lecturer = Lecturer::where('nidn', '0011223301')->firstOrFail();
        $this->lecturer->forceFill(['user_id' => $this->dosenUser->id])->save();

        // Budi Santoso: KRS approved pada PAI201-A → peserta aktif.
        $this->student = Student::where('student_number', '202501001')->firstOrFail();
        $this->student->forceFill(['user_id' => $this->studentUser->id])->save();

        // Ahmad Dahlan: punya item PAI201-A tapi KRS masih submitted → bukan roster.
        $this->pendingStudent = Student::where('student_number', '202501002')->firstOrFail();

        // Rizky (ES): sama sekali tidak mengambil PAI201-A.
        $this->foreignStudent = Student::where('student_number', '202504001')->firstOrFail();

        // Seeder membuat pertemuan 1-8: 1-7 closed, 8 open dengan token TOKEN8.
        $this->closedSession = $this->sessionOf($this->ownClass, 1);
        $this->openSession = $this->sessionOf($this->ownClass, 8);
    }

    protected function sessionOf(AcademicClass $class, int $meeting): TeachingSession
    {
        return TeachingSession::where('academic_class_id', $class->id)
            ->where('meeting_number', $meeting)
            ->firstOrFail();
    }

    protected function callAs(string $token, string $method, string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        // Guard cache bertahan antar-request dalam satu proses test, sehingga
        // identitas request sebelumnya bisa ikut terpakai. Lepas sebelum memanggil.
        auth()->forgetGuards();

        return $this->withHeader('Authorization', "Bearer {$token}")->json($method, $uri, $data);
    }

    protected function asAdmin(string $method, string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->callAs($this->adminToken, $method, $uri, $data);
    }

    protected function asDosen(string $method, string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->callAs($this->dosenToken, $method, $uri, $data);
    }

    protected function asStudent(string $method, string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->callAs($this->studentToken, $method, $uri, $data);
    }

    protected function futureSessionPayload(array $overrides = []): array
    {
        return array_merge([
            'academic_class_id' => $this->ownClass->id,
            'lecturer_id' => $this->lecturer->id,
            'meeting_number' => 9,
            'session_date' => now()->addWeeks(2)->format('Y-m-d'),
            'start_time' => '08:00',
            'end_time' => '09:40',
            'topic' => 'Pembahasan Materi Pertemuan ke-9',
            'teaching_method' => 'offline',
            'status' => 'scheduled',
        ], $overrides);
    }

    public function test_creating_session_does_not_seed_attendance_rows(): void
    {
        $response = $this->asAdmin('POST', '/api/v1/attendance/sessions', $this->futureSessionPayload());

        $response->assertStatus(201)
            ->assertJsonPath('data.meeting_number', 9);

        $session = $this->sessionOf($this->ownClass, 9);

        // Baris `present` otomatis membuat kelas tampak hadir 100% walau tak diabsen.
        $this->assertSame(0, StudentAttendance::where('teaching_session_id', $session->id)->count());

        $sheet = $this->asDosen('GET', "/api/v1/attendance/sessions/{$session->id}/students");
        $sheet->assertStatus(200);

        $rows = $sheet->json('data');
        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertFalse($row['is_recorded']);
            $this->assertNull($row['status']);
            $this->assertSame('Belum dicatat', $row['status_label']);
            $this->assertSame('-', $row['status_code']);
        }
    }

    public function test_duplicate_meeting_number_is_rejected(): void
    {
        $this->asAdmin('POST', '/api/v1/attendance/sessions', $this->futureSessionPayload(['meeting_number' => 1]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('meeting_number');

        $this->assertSame(1, TeachingSession::where('academic_class_id', $this->ownClass->id)->where('meeting_number', 1)->count());
    }

    public function test_lecturer_cannot_create_session_for_class_they_do_not_teach(): void
    {
        $this->asDosen('POST', '/api/v1/attendance/sessions', $this->futureSessionPayload([
            'academic_class_id' => $this->foreignClass->id,
            'lecturer_id' => $this->foreignClass->lecturers()->first()->id,
        ]))->assertStatus(403);

        $this->asDosen('POST', '/api/v1/attendance/sessions', $this->futureSessionPayload([
            'academic_class_id' => $this->foreignClass->id,
            'lecturer_id' => $this->lecturer->id,
        ]))->assertStatus(403);
    }

    public function test_lecturer_can_record_attendance_on_own_open_session(): void
    {
        $response = $this->asDosen('POST', "/api/v1/attendance/sessions/{$this->openSession->id}/record-batch", [
            'attendances' => [
                ['student_id' => $this->student->id, 'status' => 'present', 'notes' => null],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('student_attendances', [
            'teaching_session_id' => $this->openSession->id,
            'student_id' => $this->student->id,
            'status' => AttendanceStatus::PRESENT->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'Attendance',
            'action' => 'attendance_recorded',
        ]);
    }

    public function test_record_batch_rejects_students_that_are_not_active_roster(): void
    {
        $sessionId = $this->asAdmin('POST', '/api/v1/attendance/sessions', $this->futureSessionPayload())
            ->assertStatus(201)
            ->json('data.id');

        // Satu baris sah + satu baris bukan peserta → semuanya ditolak (all-or-nothing).
        // Ahmad Dahlan punya item PAI201-A tapi KRS-nya masih submitted.
        $this->asDosen('POST', "/api/v1/attendance/sessions/{$sessionId}/record-batch", [
            'attendances' => [
                ['student_id' => $this->student->id, 'status' => 'present'],
                ['student_id' => $this->pendingStudent->id, 'status' => 'present'],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors('attendances');

        // Mahasiswa dari prodi lain.
        $this->asDosen('POST', "/api/v1/attendance/sessions/{$sessionId}/record-batch", [
            'attendances' => [
                ['student_id' => $this->foreignStudent->id, 'status' => 'present'],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors('attendances');

        $this->assertSame(0, StudentAttendance::where('teaching_session_id', $sessionId)->count());
    }

    public function test_izin_sakit_alpa_wajib_dengan_keterangan(): void
    {
        foreach (['permit', 'sick', 'absent'] as $status) {
            $this->asDosen('POST', "/api/v1/attendance/sessions/{$this->openSession->id}/record-batch", [
                'attendances' => [
                    ['student_id' => $this->student->id, 'status' => $status, 'notes' => '   '],
                ],
            ])->assertStatus(422)->assertJsonValidationErrors("attendances.0.notes");
        }

        $this->asDosen('PATCH', "/api/v1/attendance/sessions/{$this->openSession->id}/students/{$this->student->id}", [
            'status' => 'sick',
        ])->assertStatus(422)->assertJsonValidationErrors('notes');
    }

    public function test_unknown_attendance_status_returns_422_instead_of_500(): void
    {
        $this->asDosen('PATCH', "/api/v1/attendance/sessions/{$this->openSession->id}/students/{$this->student->id}", [
            'status' => 'hadir-sekali',
        ])->assertStatus(422)->assertJsonValidationErrors('status');

        $this->asDosen('POST', "/api/v1/attendance/sessions/{$this->openSession->id}/record-batch", [
            'attendances' => [['student_id' => $this->student->id, 'status' => ' Alpha ']],
        ])->assertStatus(422);
    }

    public function test_lecturer_cannot_record_on_another_lecturers_session(): void
    {
        $foreignSession = TeachingSession::where('academic_class_id', $this->foreignClass->id)
            ->where('status', SessionStatus::OPEN->value)
            ->firstOrFail();

        $this->asDosen('POST', "/api/v1/attendance/sessions/{$foreignSession->id}/record-batch", [
            'attendances' => [['student_id' => $this->foreignStudent->id, 'status' => 'present']],
        ])->assertStatus(403);

        $this->asDosen('GET', "/api/v1/attendance/sessions/{$foreignSession->id}/students")
            ->assertStatus(403);
    }

    public function test_closed_session_requires_a_correction_reason(): void
    {
        $payload = ['attendances' => [['student_id' => $this->student->id, 'status' => 'absent', 'notes' => 'Tanpa keterangan']]];

        $this->asDosen('POST', "/api/v1/attendance/sessions/{$this->closedSession->id}/record-batch", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('correction_reason');

        $this->asDosen('POST', "/api/v1/attendance/sessions/{$this->closedSession->id}/record-batch", $payload + [
            'correction_reason' => 'Koreksi: mahasiswa ternyata mengikuti ujian susulan.',
        ])->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'Attendance',
            'action' => 'attendance_corrected',
        ]);
    }

    public function test_cancelled_session_cannot_be_attended(): void
    {
        $this->openSession->update(['status' => SessionStatus::CANCELLED]);

        $this->asDosen('POST', "/api/v1/attendance/sessions/{$this->openSession->id}/record-batch", [
            'attendances' => [['student_id' => $this->student->id, 'status' => 'present']],
        ])->assertStatus(422)->assertJsonValidationErrors('session');

        // Alasan koreksi sekalipun tidak membuka sesi yang dibatalkan.
        $this->asDosen('POST', "/api/v1/attendance/sessions/{$this->openSession->id}/record-batch", [
            'attendances' => [['student_id' => $this->student->id, 'status' => 'present']],
            'correction_reason' => 'alasan',
        ])->assertStatus(422);
    }

    public function test_session_status_transitions_are_constrained(): void
    {
        $payload = fn (array $extra) => [
            'academic_class_id' => $this->ownClass->id,
            'lecturer_id' => $this->lecturer->id,
            'meeting_number' => 8,
            'session_date' => $this->openSession->session_date->format('Y-m-d'),
        ] + $extra;

        // open → closed diizinkan.
        $this->asDosen('PUT', "/api/v1/attendance/sessions/{$this->openSession->id}", $payload(['status' => 'closed']))
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'closed');

        // Sesi terkunci: perubahan apa pun butuh alasan koreksi.
        $this->asDosen('PUT', "/api/v1/attendance/sessions/{$this->openSession->id}", $payload(['status' => 'scheduled']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('correction_reason');

        // closed → scheduled tetap bukan transisi yang sah.
        $this->asDosen('PUT', "/api/v1/attendance/sessions/{$this->openSession->id}", $payload([
            'status' => 'scheduled',
            'correction_reason' => 'Perlu memperbaiki nomor pertemuan.',
        ]))->assertStatus(422)->assertJsonValidationErrors('status');

        // closed → open sah, dan tidak bisa dilewatkan lewat PUT tanpa state machine.
        $this->asDosen('PUT', "/api/v1/attendance/sessions/{$this->openSession->id}", $payload([
            'status' => 'open',
            'correction_reason' => 'Presensi belum lengkap.',
        ]))->assertStatus(200)->assertJsonPath('data.status', 'open');
    }

    public function test_reopen_session_requires_a_reason(): void
    {
        $this->closedSession->update(['status' => SessionStatus::CLOSED]);

        $this->asDosen('POST', "/api/v1/attendance/sessions/{$this->closedSession->id}/reopen", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->asDosen('POST', "/api/v1/attendance/sessions/{$this->closedSession->id}/reopen", [
            'reason' => 'Salah tutup sesi, presensi belum lengkap.',
        ])->assertStatus(200)->assertJsonPath('data.status', 'open');

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'Attendance',
            'action' => 'session_reopened',
        ]);
    }

    public function test_self_checkin_records_presence_for_roster_student(): void
    {
        $response = $this->asStudent('POST', '/api/v1/attendance/self-checkin', [
            'teaching_session_id' => $this->openSession->id,
            'check_in_code' => 'TOKEN8',
        ]);

        $response->assertStatus(200)->assertJsonPath('data.status', 'present');

        $this->assertDatabaseHas('student_attendances', [
            'teaching_session_id' => $this->openSession->id,
            'student_id' => $this->student->id,
            'status' => AttendanceStatus::PRESENT->value,
            'recorded_by' => $this->studentUser->id,
        ]);
    }

    public function test_self_checkin_rejects_wrong_or_expired_code(): void
    {
        $this->asStudent('POST', '/api/v1/attendance/self-checkin', [
            'teaching_session_id' => $this->openSession->id,
            'check_in_code' => 'SALAH1',
        ])->assertStatus(422)->assertJsonValidationErrors('check_in_code');

        $this->openSession->update(['check_in_expires_at' => now()->subMinutes(5)]);

        $this->asStudent('POST', '/api/v1/attendance/self-checkin', [
            'teaching_session_id' => $this->openSession->id,
            'check_in_code' => 'TOKEN8',
        ])->assertStatus(422);
    }

    public function test_self_checkin_rejects_student_outside_the_class(): void
    {
        $this->student->forceFill(['user_id' => null])->save();
        $this->foreignStudent->forceFill(['user_id' => $this->studentUser->id])->save();

        $response = $this->asStudent('POST', '/api/v1/attendance/self-checkin', [
            'teaching_session_id' => $this->openSession->id,
            'check_in_code' => 'TOKEN8',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('teaching_session_id');

        $this->assertDatabaseMissing('student_attendances', [
            'teaching_session_id' => $this->openSession->id,
            'student_id' => $this->foreignStudent->id,
        ]);
    }

    public function test_check_in_code_is_never_sent_to_students(): void
    {
        $this->asStudent('GET', "/api/v1/attendance/sessions/{$this->openSession->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.can_manage', false)
            ->assertJsonMissingPath('data.check_in_code')
            ->assertJsonMissingPath('data.check_in_expires_at');

        $this->asDosen('GET', "/api/v1/attendance/sessions/{$this->openSession->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.can_manage', true)
            ->assertJsonPath('data.check_in_code', 'TOKEN8');
    }

    public function test_opening_checkin_on_closed_session_requires_reopen(): void
    {
        $this->asDosen('POST', "/api/v1/attendance/sessions/{$this->closedSession->id}/open-checkin")
            ->assertStatus(422)
            ->assertJsonValidationErrors('session');

        $this->asDosen('POST', "/api/v1/attendance/sessions/{$this->openSession->id}/open-checkin", [
            'duration_minutes' => 20,
        ])->assertStatus(200)->assertJsonPath('data.is_check_in_active', true);
    }

    public function test_session_listing_is_scoped_to_the_caller(): void
    {
        // Dosen hanya melihat kelas yang ia ampu.
        $this->asDosen('GET', "/api/v1/attendance/sessions?academic_class_id={$this->ownClass->id}")
            ->assertStatus(200)
            ->assertJsonCount(8, 'data');

        $this->asDosen('GET', "/api/v1/attendance/sessions?academic_class_id={$this->foreignClass->id}")
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');

        // Mahasiswa hanya melihat sesi kelas yang ia ambil.
        $this->asStudent('GET', "/api/v1/attendance/sessions?academic_class_id={$this->foreignClass->id}")
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');

        $foreignSession = TeachingSession::where('academic_class_id', $this->foreignClass->id)->firstOrFail();
        $this->asStudent('GET', "/api/v1/attendance/sessions/{$foreignSession->id}")->assertStatus(403);

        // Staf melihat semuanya.
        $this->asAdmin('GET', '/api/v1/attendance/sessions')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', TeachingSession::count());
    }

    public function test_class_recap_reads_threshold_from_semester_config(): void
    {
        $semester = $this->ownClass->semester;
        $semester->forceFill([
            'uts_start_date' => now()->addMonth()->toDateString(),
            'min_attendance_uts_percentage' => 60.5,
            'min_attendance_uas_percentage' => 85,
        ])->save();

        $response = $this->asDosen('GET', "/api/v1/attendance/classes/{$this->ownClass->id}/recap");

        $response->assertStatus(200)
            ->assertJsonPath('data.threshold_stage', 'uts')
            ->assertJsonPath('data.min_attendance_percentage', 60.5)
            ->assertJsonPath('data.held_sessions', 8)
            ->assertJsonPath('data.total_sessions', 8);

        $row = collect($response->json('data.recap'))
            ->firstWhere('student.id', $this->student->id);

        $this->assertNotNull($row);
        $this->assertSame(60.5, $row['min_attendance_percentage']);
        $this->assertSame(8, $row['total_meetings']);

        // Token presensi tidak ikut terkirim pada rekap kelas.
        $this->assertArrayNotHasKey('check_in_code', $response->json('data.sessions.0'));

        // Ambang ikut berubah setelah tanggal UTS.
        $semester->forceFill(['uts_start_date' => now()->subMonth()->toDateString()])->save();
        $this->asDosen('GET', "/api/v1/attendance/classes/{$this->ownClass->id}/recap")
            ->assertStatus(200)
            ->assertJsonPath('data.threshold_stage', 'uas')
            // JSON tidak membedakan 85 dan 85.0, jadi bandingkan sebagai angka.
            ->assertJsonPath('data.min_attendance_percentage', fn ($value) => (float) $value === 85.0);
    }

    public function test_student_recap_only_counts_sessions_already_held(): void
    {
        $this->asAdmin('POST', '/api/v1/attendance/sessions', $this->futureSessionPayload(['meeting_number' => 9]));
        $this->asAdmin('POST', '/api/v1/attendance/sessions', $this->futureSessionPayload([
            'meeting_number' => 10,
            'status' => 'cancelled',
        ]));

        $response = $this->asStudent('GET', '/api/v1/attendance/my-attendance');
        $response->assertStatus(200);

        $class = collect($response->json('data.classes'))
            ->firstWhere('class_code', $this->ownClass->code);

        $this->assertNotNull($class);
        $this->assertSame(10, $class['total_sessions']);
        $this->assertSame(8, $class['held_sessions']);

        $future = collect($class['meetings'])->firstWhere('meeting_number', 9);
        $this->assertNull($future['status']);
        $this->assertSame('Belum berlangsung', $future['status_label']);
        $this->assertFalse($future['is_recorded']);

        $summary = $response->json('data.summary');
        $this->assertArrayHasKey('total_unrecorded', $summary);
        $this->assertArrayHasKey('min_attendance_percentage', $summary);
        $this->assertArrayHasKey('threshold_stage', $summary);
    }

    public function test_unrecorded_past_meeting_is_counted_as_absent(): void
    {
        StudentAttendance::where('teaching_session_id', $this->closedSession->id)
            ->where('student_id', $this->student->id)
            ->delete();

        $class = collect($this->asStudent('GET', '/api/v1/attendance/my-attendance')->json('data.classes'))
            ->firstWhere('class_code', $this->ownClass->code);

        $this->assertSame(1, $class['unrecorded_count']);
        $this->assertGreaterThanOrEqual(1, $class['absent_count']);
        $this->assertSame(7, $class['present_count'] + $class['permit_count'] + $class['sick_count']);
    }

    public function test_session_without_attendance_is_not_read_as_hundred_percent(): void
    {
        // Kelas yang belum pernah diabsen sama sekali tidak boleh terbaca 100%.
        StudentAttendance::where('academic_class_id', $this->ownClass->id)->delete();

        $class = collect($this->asStudent('GET', '/api/v1/attendance/my-attendance')->json('data.classes'))
            ->firstWhere('class_code', $this->ownClass->code);

        $this->assertSame(0.0, (float) $class['percentage']);
        $this->assertFalse($class['is_eligible']);
        $this->assertSame(8, $class['unrecorded_count']);
        $this->assertSame(8, $class['absent_count']);
    }

    public function test_deleting_a_session_requires_staff_and_blocks_recorded_history(): void
    {
        $this->asDosen('DELETE', "/api/v1/attendance/sessions/{$this->closedSession->id}")
            ->assertStatus(403);

        // Sesi pertemuan 1 sudah punya presensi tercatat → tidak bisa dihapus.
        $this->asAdmin('DELETE', "/api/v1/attendance/sessions/{$this->closedSession->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('session');

        $created = $this->asAdmin('POST', '/api/v1/attendance/sessions', $this->futureSessionPayload())->json('data');
        $this->asAdmin('DELETE', "/api/v1/attendance/sessions/{$created['id']}")->assertStatus(200);
        $this->assertDatabaseMissing('teaching_sessions', ['id' => $created['id']]);
    }

    public function test_syncing_sessions_is_a_staff_action(): void
    {
        $this->asDosen('POST', "/api/v1/attendance/classes/{$this->ownClass->id}/sync-sessions")
            ->assertStatus(403);

        $this->asStudent('POST', "/api/v1/attendance/classes/{$this->ownClass->id}/sync-sessions")
            ->assertStatus(403);
    }

    public function test_attendance_endpoints_deny_accounts_without_a_student_profile(): void
    {
        $this->student->forceFill(['user_id' => null])->save();

        $this->asStudent('GET', '/api/v1/attendance/my-attendance')->assertStatus(403);
        $this->asStudent('POST', '/api/v1/attendance/self-checkin', [
            'teaching_session_id' => $this->openSession->id,
            'check_in_code' => 'TOKEN8',
        ])->assertStatus(403);
    }

    public function test_student_cannot_read_another_students_recap(): void
    {
        $this->asStudent('GET', "/api/v1/attendance/students/{$this->foreignStudent->id}/recap")
            ->assertStatus(403);

        // Dosen pengampu/PA boleh.
        $this->asDosen('GET', "/api/v1/attendance/students/{$this->student->id}/recap")
            ->assertStatus(200)
            ->assertJsonPath('data.student.id', $this->student->id);
    }

    public function test_threshold_comes_from_the_semester_and_falls_back_without_one(): void
    {
        $semester = $this->ownClass->semester;
        $semester->forceFill([
            'uts_start_date' => now()->addMonth()->toDateString(),
            'min_attendance_uts_percentage' => 62.5,
        ])->save();

        $threshold = AttendancePolicy::threshold($semester->fresh());
        $this->assertSame('uts', $threshold['stage']);
        $this->assertSame(62.5, $threshold['percentage']);

        // Semester tanpa konfigurasi (mis. data lama) memakai ambang bawaan,
        // bukan nol dan bukan angka yang di-hardcode di service/UI.
        $this->assertSame(
            AttendancePolicy::FALLBACK_MINIMUM_PERCENTAGE,
            AttendancePolicy::threshold(null)['percentage']
        );
        $this->assertSame(
            AttendancePolicy::FALLBACK_TEACHING_WEEKS,
            AttendancePolicy::totalTeachingWeeks(null)
        );

        // Tanpa pertemuan berjalan: 0%, tidak layak (bukan 100% dan bukan layak).
        $eligibility = AttendancePolicy::eligibility(0, 0, $semester);
        $this->assertSame(0.0, $eligibility['percentage']);
        $this->assertFalse($eligibility['is_eligible']);
    }

    public function test_correction_is_audited_with_before_and_after_values(): void
    {
        $before = StudentAttendance::where('teaching_session_id', $this->closedSession->id)
            ->where('student_id', $this->student->id)
            ->firstOrFail();
        $originalStatus = $before->status->value;

        $this->asDosen('PATCH', "/api/v1/attendance/sessions/{$this->closedSession->id}/students/{$this->student->id}", [
            'status' => 'sick',
            'notes' => 'Surat dokter terlampir',
            'correction_reason' => 'Salah input oleh dosen pengampu.',
        ])->assertStatus(200);

        $log = AuditLog::where('module', 'Attendance')
            ->where('action', 'attendance_corrected')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($this->dosenUser->id, $log->user_id);
        $this->assertSame($originalStatus, $log->old_values['status']);
        $this->assertSame('sick', $log->new_values['status']);
        $this->assertStringContainsString('Salah input', $log->description);
    }
}
