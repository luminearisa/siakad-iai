<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academic\Models\Semester;
use Modules\Class\Enums\ClassStatus;
use Modules\Class\Models\AcademicClass;
use Modules\Course\Models\Course;
use Modules\Enrollment\Enums\EnrollmentStatus;
use Modules\Enrollment\Models\StudentEnrollment;
use Modules\Identity\Models\User;
use Modules\Schedule\Enums\DayOfWeek;
use Modules\Schedule\Models\ClassSchedule;
use Modules\Student\Enums\StudentStatus;
use Modules\Student\Models\Student;
use Tests\TestCase;

class EnrollmentTest extends TestCase
{
    use RefreshDatabase;

    protected string $adminToken;
    protected string $studentToken;
    protected User $admin;
    protected User $studentUser;
    protected Student $student;
    protected Semester $semester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@siakad.ac.id')->first();
        $this->adminToken = $this->admin->createToken('admin_token')->plainTextToken;

        $this->studentUser = User::where('email', 'mahasiswa@siakad.ac.id')->first();
        $this->studentToken = $this->studentUser->createToken('student_token')->plainTextToken;

        $this->student = Student::where('student_number', '202501001')->first();
        $this->semester = Semester::first();
    }

    public function test_student_can_create_krs_draft(): void
    {
        // Delete any existing enrollment from seeder for clean test
        StudentEnrollment::where('student_id', $this->student->id)->forceDelete();

        $response = $this->withHeader('Authorization', "Bearer {$this->studentToken}")
            ->postJson('/api/v1/enrollments', [
                'student_id' => $this->student->id,
                'semester_id' => $this->semester->id,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'student_id' => $this->student->id,
                    'status' => 'draft',
                    'total_credits' => 0,
                ],
            ]);
    }

    public function test_can_add_and_remove_class_item_with_capacity_and_credit_updates(): void
    {
        $enrollment = StudentEnrollment::where('student_id', $this->student->id)->first();
        $enrollment->update(['status' => EnrollmentStatus::DRAFT]);

        $class = AcademicClass::where('code', 'HKI201-A')->first();
        $initialCapacity = $class->enrolled_count;

        // Add class
        $response = $this->withHeader('Authorization', "Bearer {$this->studentToken}")
            ->postJson("/api/v1/enrollments/{$enrollment->id}/items", [
                'class_id' => $class->id,
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertEquals($initialCapacity + 1, $class->fresh()->enrolled_count);

        $item = $enrollment->items()->where('class_id', $class->id)->first();
        $this->assertNotNull($item);

        // Remove class
        $delResponse = $this->withHeader('Authorization', "Bearer {$this->studentToken}")
            ->deleteJson("/api/v1/enrollments/{$enrollment->id}/items/{$item->id}");

        $delResponse->assertStatus(200);
        $this->assertEquals($initialCapacity, $class->fresh()->enrolled_count);
    }

    public function test_rejects_adding_duplicate_course_to_krs(): void
    {
        $enrollment = StudentEnrollment::where('student_id', $this->student->id)->first();
        $enrollment->update(['status' => EnrollmentStatus::DRAFT]);

        // PAI201 is already enrolled from seeder
        $class = AcademicClass::where('code', 'PAI201-A')->first();

        $response = $this->withHeader('Authorization', "Bearer {$this->studentToken}")
            ->postJson("/api/v1/enrollments/{$enrollment->id}/items", [
                'class_id' => $class->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['course_id']]);
    }

    public function test_rejects_adding_class_when_capacity_is_full(): void
    {
        $enrollment = StudentEnrollment::where('student_id', $this->student->id)->first();
        $enrollment->update(['status' => EnrollmentStatus::DRAFT]);

        $class = AcademicClass::where('code', 'HKI201-A')->first();
        $class->update(['capacity' => 10, 'enrolled_count' => 10]);

        $response = $this->withHeader('Authorization', "Bearer {$this->studentToken}")
            ->postJson("/api/v1/enrollments/{$enrollment->id}/items", [
                'class_id' => $class->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['class_id']]);
    }

    public function test_rejects_schedule_conflict_for_student(): void
    {
        $enrollment = StudentEnrollment::where('student_id', $this->student->id)->first();
        $enrollment->update(['status' => EnrollmentStatus::DRAFT]);

        // Student already has PAI201 (Mon 08:00 - 10:30)
        // Create a new class with conflicting schedule (Mon 09:00 - 11:00)
        $newCourse = Course::create([
            'code' => 'TEST-CONFLICT-MK',
            'name' => 'Mata Kuliah Uji Bentrok',
            'credits' => 2,
            'theory_credits' => 2,
            'practical_credits' => 0,
        ]);

        $newClass = AcademicClass::create([
            'semester_id' => $this->semester->id,
            'course_id' => $newCourse->id,
            'code' => 'CONFLICT-A',
            'name' => 'Mata Kuliah Uji Bentrok - A',
            'section' => 'A',
            'capacity' => 30,
            'status' => ClassStatus::OPEN,
        ]);

        ClassSchedule::create([
            'class_id' => $newClass->id,
            'day_of_week' => DayOfWeek::MONDAY,
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->studentToken}")
            ->postJson("/api/v1/enrollments/{$enrollment->id}/items", [
                'class_id' => $newClass->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['class_id']]);
    }

    public function test_krs_workflow_submit_approve_and_lock(): void
    {
        $enrollment = StudentEnrollment::where('student_id', $this->student->id)->first();
        $enrollment->update(['status' => EnrollmentStatus::DRAFT]);

        // 1. Submit
        $submitRes = $this->withHeader('Authorization', "Bearer {$this->studentToken}")
            ->postJson("/api/v1/enrollments/{$enrollment->id}/submit");

        $submitRes->assertStatus(200)
            ->assertJson(['data' => ['status' => 'submitted']]);

        // 2. Approve (Switch to Admin user)
        auth()->forgetGuards();
        $approveRes = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/enrollments/{$enrollment->id}/approve", [
                'notes' => 'KRS disetujui tanpa catatan.',
            ]);

        $approveRes->assertStatus(200)
            ->assertJson(['data' => ['status' => 'approved']]);

        // 3. Lock
        $lockRes = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/enrollments/{$enrollment->id}/lock");

        $lockRes->assertStatus(200)
            ->assertJson(['data' => ['status' => 'locked']]);
    }

    public function test_cannot_modify_approved_or_locked_krs(): void
    {
        $enrollment = StudentEnrollment::where('student_id', $this->student->id)->first();
        $enrollment->update(['status' => EnrollmentStatus::LOCKED]);

        $class = AcademicClass::where('code', 'HKI201-A')->first();

        $response = $this->withHeader('Authorization', "Bearer {$this->studentToken}")
            ->postJson("/api/v1/enrollments/{$enrollment->id}/items", [
                'class_id' => $class->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['enrollment']]);
    }
}
