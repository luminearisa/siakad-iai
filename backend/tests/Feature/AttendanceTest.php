<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Attendance\Enums\AttendanceStatus;
use Modules\Attendance\Enums\SessionStatus;
use Modules\Attendance\Models\StudentAttendance;
use Modules\Attendance\Models\TeachingSession;
use Modules\Class\Models\AcademicClass;
use Modules\Enrollment\Models\StudentEnrollment;
use Modules\Enrollment\Models\StudentEnrollmentItem;
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
    protected AcademicClass $academicClass;
    protected Lecturer $lecturer;
    protected Student $student;

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

        $this->academicClass = AcademicClass::first();
        $this->lecturer = Lecturer::first();
        $this->student = Student::where('student_number', '202501001')->first() ?? Student::first();
        $this->student->user_id = $this->studentUser->id;
        $this->student->save();
        if ($this->lecturer) {
            $this->lecturer->user_id = $this->dosenUser->id;
            $this->lecturer->save();
        }
    }

    public function test_can_create_teaching_session_and_initialize_attendances(): void
    {
        $payload = [
            'academic_class_id' => $this->academicClass->id,
            'lecturer_id' => $this->lecturer->id,
            'meeting_number' => 10,
            'session_date' => now()->format('Y-m-d'),
            'start_time' => '08:00',
            'end_time' => '09:40',
            'topic' => 'Pengenalan Testing & QA Otomatis',
            'notes' => 'Diskusi aktif seputar arsitektur unit testing.',
            'teaching_method' => 'offline',
            'status' => 'scheduled',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/attendance/sessions', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'academic_class_id' => $this->academicClass->id,
                    'meeting_number' => 10,
                    'topic' => 'Pengenalan Testing & QA Otomatis',
                ],
            ]);

        $this->assertDatabaseHas('teaching_sessions', [
            'academic_class_id' => $this->academicClass->id,
            'meeting_number' => 10,
        ]);
    }

    public function test_can_record_batch_attendances(): void
    {
        $session = TeachingSession::first();

        $payload = [
            'attendances' => [
                [
                    'student_id' => $this->student->id,
                    'status' => 'permit',
                    'notes' => 'Izin keperluan dinas',
                ],
            ],
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->dosenToken}")
            ->postJson("/api/v1/attendance/sessions/{$session->id}/record-batch", $payload);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('student_attendances', [
            'teaching_session_id' => $session->id,
            'student_id' => $this->student->id,
            'status' => AttendanceStatus::PERMIT->value,
            'notes' => 'Izin keperluan dinas',
        ]);
    }

    public function test_can_open_and_execute_self_checkin(): void
    {
        $session = TeachingSession::first();

        // 1. Dosen opens self check-in
        $openResponse = $this->withHeader('Authorization', "Bearer {$this->dosenToken}")
            ->postJson("/api/v1/attendance/sessions/{$session->id}/open-checkin", [
                'duration_minutes' => 30,
            ]);

        $this->assertEquals(200, $openResponse->status(), 'Open checkin failed: ' . json_encode($openResponse->json()));
        $code = $openResponse->json('data.check_in_code');
        $this->assertNotEmpty($code);

        // 2. Student inputs self check-in code
        $checkInResponse = $this->withHeader('Authorization', "Bearer {$this->studentToken}")
            ->postJson('/api/v1/attendance/self-checkin', [
                'teaching_session_id' => $session->id,
                'check_in_code' => $code,
            ]);

        $this->assertEquals(200, $checkInResponse->status(), 'Checkin failed: ' . json_encode($checkInResponse->json()));
        $checkInResponse->assertJson([
            'success' => true,
            'data' => [
                'status' => 'present',
            ],
        ]);
    }

    public function test_can_get_class_attendance_recap(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->dosenToken}")
            ->getJson("/api/v1/attendance/classes/{$this->academicClass->id}/recap");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'class',
                    'total_sessions',
                    'sessions',
                    'recap',
                ],
            ]);
    }

    public function test_student_can_view_own_attendance(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->studentToken}")
            ->getJson('/api/v1/attendance/my-attendance');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'student',
                    'summary' => [
                        'total_classes',
                        'total_sessions',
                        'overall_percentage',
                        'is_eligible_overall',
                    ],
                    'classes',
                ],
            ]);
    }
}
