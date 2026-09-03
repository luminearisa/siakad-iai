<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academic\Models\AcademicYear;
use Modules\Academic\Models\Faculty;
use Modules\Academic\Models\Institution;
use Modules\Academic\Models\Semester;
use Modules\Academic\Models\StudyProgram;
use Modules\Advising\Enums\AdvisorStatus;
use Modules\Advising\Models\AcademicAdvisor;
use Modules\Class\Enums\ClassStatus;
use Modules\Class\Models\AcademicClass;
use Modules\Course\Enums\CourseType;
use Modules\Course\Models\Course;
use Modules\Curriculum\Enums\CurriculumStatus;
use Modules\Curriculum\Models\Curriculum;
use Modules\Enrollment\Enums\EnrollmentStatus;
use Modules\Enrollment\Models\StudentEnrollment;
use Modules\Identity\Models\User;
use Modules\Lecturer\Enums\LecturerStatus;
use Modules\Lecturer\Models\Lecturer;
use Modules\Schedule\Enums\DayOfWeek;
use Modules\Schedule\Models\ClassSchedule;
use Modules\Schedule\Models\Room;
use Modules\Student\Enums\Gender;
use Modules\Student\Enums\StudentStatus;
use Modules\Student\Models\Student;
use Tests\TestCase;

class AcademicIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected string $adminToken;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@siakad.ac.id')->first();
        $this->adminToken = $this->admin->createToken('admin_token')->plainTextToken;
    }

    public function test_full_academic_operations_lifecycle(): void
    {
        // 1. Institution & Academic Setup
        $inst = Institution::first();
        $fac = Faculty::first();
        $prodi = StudyProgram::first();
        $ay = AcademicYear::first();
        $sem = Semester::where('status', 'active')->first() ?? Semester::first();

        // 2. Create Lecturer
        $lecturerUser = User::create([
            'name' => 'Dr. E2E Dosen, M.Pd',
            'email' => 'e2e.dosen@siakad.ac.id',
            'password' => bcrypt('password123'),
        ]);
        $lecturerUser->assignRole('dosen');

        $lecturer = Lecturer::create([
            'user_id' => $lecturerUser->id,
            'homebase_study_program_id' => $prodi->id,
            'nidn' => '9988776655',
            'full_name' => 'Dr. E2E Dosen, M.Pd',
            'gender' => Gender::MALE,
            'status' => LecturerStatus::ACTIVE,
        ]);

        // 3. Create Student
        $studentUser = User::create([
            'name' => 'Mahasiswa E2E',
            'email' => 'e2e.student@siakad.ac.id',
            'password' => bcrypt('password123'),
        ]);
        $studentUser->assignRole('mahasiswa');
        $studentToken = $studentUser->createToken('e2e_student')->plainTextToken;

        $student = Student::create([
            'user_id' => $studentUser->id,
            'study_program_id' => $prodi->id,
            'student_number' => '202699001',
            'full_name' => 'Mahasiswa E2E',
            'gender' => Gender::MALE,
            'status' => StudentStatus::ACTIVE,
            'admission_year' => 2026,
        ]);

        // 4. Create Course & Curriculum
        $course = Course::create([
            'code' => 'E2E-101',
            'name' => 'Konsep Dasar SIAKAD E2E',
            'credits' => 3,
            'theory_credits' => 3,
            'practical_credits' => 0,
            'type' => CourseType::THEORY,
        ]);

        $curriculum = Curriculum::create([
            'study_program_id' => $prodi->id,
            'code' => 'KUR-E2E-2026',
            'name' => 'Kurikulum E2E',
            'status' => CurriculumStatus::ACTIVE,
        ]);

        $curSem = $curriculum->semesters()->create(['semester_number' => 1, 'name' => 'Semester 1']);
        $curSem->subjects()->create(['course_id' => $course->id, 'is_mandatory' => true]);

        // 5. Create Academic Class & Assign Lecturer
        $class = AcademicClass::create([
            'semester_id' => $sem->id,
            'course_id' => $course->id,
            'study_program_id' => $prodi->id,
            'code' => 'E2E101-A',
            'name' => 'Konsep Dasar SIAKAD E2E - A',
            'section' => 'A',
            'capacity' => 40,
            'status' => ClassStatus::OPEN,
        ]);
        $class->classLecturers()->create(['lecturer_id' => $lecturer->id, 'role' => 'primary']);

        // 6. Create Room & Schedule
        $room = Room::first();
        ClassSchedule::create([
            'class_id' => $class->id,
            'room_id' => $room->id,
            'day_of_week' => DayOfWeek::FRIDAY,
            'start_time' => '08:00:00',
            'end_time' => '10:30:00',
        ]);

        // 7. Assign Academic Advisor to Student
        $advisor = AcademicAdvisor::create([
            'student_id' => $student->id,
            'lecturer_id' => $lecturer->id,
            'start_date' => now()->format('Y-m-d'),
            'status' => AdvisorStatus::ACTIVE,
        ]);

        // 8. Student creates KRS
        $createKrsRes = $this->withHeader('Authorization', "Bearer {$studentToken}")
            ->postJson('/api/v1/enrollments', [
                'student_id' => $student->id,
                'semester_id' => $sem->id,
            ]);
        $createKrsRes->assertStatus(201);
        $enrollmentId = $createKrsRes->json('data.id');

        // 9. Student adds class item to KRS
        $addItemRes = $this->withHeader('Authorization', "Bearer {$studentToken}")
            ->postJson("/api/v1/enrollments/{$enrollmentId}/items", [
                'class_id' => $class->id,
            ]);
        $addItemRes->assertStatus(201);

        $this->assertEquals(1, $class->fresh()->enrolled_count);
        $this->assertEquals(3, StudentEnrollment::find($enrollmentId)->total_credits);

        // 10. Student submits KRS
        $submitRes = $this->withHeader('Authorization', "Bearer {$studentToken}")
            ->postJson("/api/v1/enrollments/{$enrollmentId}/submit");
        $submitRes->assertStatus(200)
            ->assertJson(['data' => ['status' => 'submitted']]);

        // 11. Advisor / Admin approves KRS
        auth()->forgetGuards();
        $approveRes = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/enrollments/{$enrollmentId}/approve", [
                'notes' => 'Approved in full.',
            ]);
        $approveRes->assertStatus(200)
            ->assertJson(['data' => ['status' => 'approved']]);

        // 12. Lock finalized KRS
        $lockRes = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/enrollments/{$enrollmentId}/lock");
        $lockRes->assertStatus(200)
            ->assertJson(['data' => ['status' => 'locked']]);

        $this->assertEquals(EnrollmentStatus::LOCKED, StudentEnrollment::find($enrollmentId)->status);
    }
}
