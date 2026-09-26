<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Academic\Models\AcademicYear;
use Modules\Academic\Models\Semester;
use Modules\Curriculum\Models\CreditLimit;
use Modules\Enrollment\Models\StudentEnrollment;
use Modules\Identity\Models\User;
use Modules\Student\Models\Student;
use Tests\TestCase;

/**
 * Audit test: asserts the STANDARD siakad behaviour for KRS, academic period,
 * and student account provisioning. A failing test here is a confirmed gap,
 * not a regression.
 */
class StandardsAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function tokenFor(string $email): string
    {
        return User::where('email', $email)->firstOrFail()->createToken('audit')->plainTextToken;
    }

    private function asGet(string $url, string $email): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->tokenFor($email))->getJson($url);
    }

    private function asPost(string $url, array $payload, string $email): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->tokenFor($email))->postJson($url, $payload);
    }

    private function asPut(string $url, array $payload, string $email): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->tokenFor($email))->putJson($url, $payload);
    }

    // ---------------------------------------------------------------------
    // A. PENGGALIAN AKUN & PORTAL MAHASISWA
    // ---------------------------------------------------------------------

    /**
     * STANDARD: a user whose account is not linked to any student record must be
     * rejected (403/404) instead of silently resolving to an arbitrary student.
     */
    public function test_portal_does_not_leak_an_arbitrary_student_to_unlinked_user(): void
    {
        $victim = Student::query()->orderBy('id')->firstOrFail();

        // A mahasiswa-role account that is not linked to any student record must
        // not resolve to Student::first(). (Non-student roles are covered by
        // test_portal_endpoints_are_restricted_to_students — Sanctum memoises the
        // resolved user per application instance, so only one identity is
        // exercised per test.)
        $unlinked = User::create([
            'name' => 'Mahasiswa Tanpa Relasi',
            'email' => 'unlinked.audit@siakad.ac.id',
            'password' => Hash::make('secret12345'),
            'status' => 'active',
        ]);
        $unlinked->assignRole('mahasiswa');
        $this->assertNull(Student::where('user_id', $unlinked->id)->first());

        $response = $this->withHeader(
            'Authorization',
            'Bearer ' . $unlinked->createToken('unlinked')->plainTextToken
        )->getJson('/api/v1/students/me/profile');

        $response->assertStatus(404);
        $this->assertNotSame(
            $victim->id,
            data_get($response->json(), 'data.student.id'),
            'PORTAL IDOR: unlinked user resolved to Student::first()'
        );
    }

    /** STANDARD: student portal endpoints must require the mahasiswa role. */
    public function test_portal_endpoints_are_restricted_to_students(): void
    {
        $this->asGet('/api/v1/students/me/profile', 'dosen@siakad.ac.id')->assertStatus(403);
        $this->asGet('/api/v1/students/me/khs', 'dosen@siakad.ac.id')->assertStatus(403);
    }

    /**
     * STANDARD: resetting a student's password must never touch an account that
     * is not that student's own linked user.
     */
    public function test_reset_password_cannot_hijack_another_users_account(): void
    {
        $admin = User::where('email', 'admin@siakad.ac.id')->firstOrFail();
        $originalHash = $admin->password;

        $orphan = Student::create([
            'student_number' => 'AUDIT9999',
            'full_name' => 'Mahasiswa Tanpa Akun',
            'email' => $admin->email, // collides with the admin account
            'study_program_id' => \Modules\Academic\Models\StudyProgram::firstOrFail()->id,
            'gender' => 'male',
            'status' => 'active',
        ]);

        
        $this->asPost(
            "/api/v1/students/{$orphan->id}/reset-password",
            ['password' => 'hijacked123'],
            'admin@siakad.ac.id'
        );

        $this->assertSame(
            $originalHash,
            $admin->fresh()->password,
            'ACCOUNT TAKEOVER: reset-password overwrote the admin account via email collision'
        );
    }

    /** STANDARD: NIM is the identity anchor and must be immutable after creation. */
    public function test_student_number_cannot_be_changed_after_creation(): void
    {
        $student = Student::query()->orderBy('id')->firstOrFail();
        $original = $student->student_number;

        $this->asPut("/api/v1/students/{$student->id}", [
            'student_number' => '999999999',
            'full_name' => $student->full_name,
            'study_program_id' => $student->study_program_id,
            'gender' => $student->gender instanceof \BackedEnum ? $student->gender->value : $student->gender,
        ], 'admin@siakad.ac.id');

        $this->assertSame($original, $student->fresh()->student_number, 'NIM was mutable via update');
    }

    /** STANDARD: a provisioned account must not keep a well-known default password. */
    public function test_new_student_account_does_not_use_a_hardcoded_default_password(): void
    {
        $response = $this->asPost('/api/v1/students', [
            'student_number' => 'AUDIT1001',
            'full_name' => 'Audit Mahasiswa Baru',
            'email' => 'audit.mahasiswa@siakad.ac.id',
            'study_program_id' => \Modules\Academic\Models\StudyProgram::firstOrFail()->id,
            'gender' => 'male',
            'status' => 'active',
        ], 'admin@siakad.ac.id');

        $response->assertStatus(201);
        $user = User::where('email', 'audit.mahasiswa@siakad.ac.id')->firstOrFail();

        $this->assertFalse(
            Hash::check('password123', $user->password),
            'Account was provisioned with the hardcoded default password "password123"'
        );
    }

    /** STANDARD: creating a student must not grant the mahasiswa role to a pre-existing privileged account. */
    public function test_email_collision_does_not_leak_roles(): void
    {
        $admin = User::where('email', 'admin@siakad.ac.id')->firstOrFail();

        $this->asPost('/api/v1/students', [
            'student_number' => 'AUDIT1002',
            'full_name' => 'Audit Kolisi Email',
            'email' => $admin->email,
            'study_program_id' => \Modules\Academic\Models\StudyProgram::firstOrFail()->id,
            'gender' => 'male',
            'status' => 'active',
        ], 'admin@siakad.ac.id');

        $this->assertFalse(
            $admin->fresh()->hasRole('mahasiswa'),
            'ROLE LEAK: admin account acquired the mahasiswa role through an email collision'
        );
    }

    /** STANDARD: every student record should end up with a usable login account. */
    public function test_student_created_without_email_still_gets_an_account(): void
    {
        $response = $this->asPost('/api/v1/students', [
            'student_number' => 'AUDIT1003',
            'full_name' => 'Audit Tanpa Email',
            'study_program_id' => \Modules\Academic\Models\StudyProgram::firstOrFail()->id,
            'gender' => 'male',
            'status' => 'active',
        ], 'admin@siakad.ac.id');

        $response->assertStatus(201);
        $student = Student::where('student_number', 'AUDIT1003')->firstOrFail();

        $this->assertNotNull($student->user_id, 'Student was created with no user account and no warning');
    }

    /** STANDARD: a non-active student (cuti/lulus/DO) must not be able to log in. */
    public function test_login_is_blocked_for_non_active_student_status(): void
    {
        $student = Student::where('student_number', '202501001')->first() ?? Student::query()->firstOrFail();
        $user = $student->user ?? User::where('email', 'mahasiswa@siakad.ac.id')->firstOrFail();
        $user->update(['password' => Hash::make('known-password'), 'status' => 'active']);
        $student->update(['user_id' => $user->id, 'status' => 'leave']);

        // Sanity check: the same credentials work while the student is active.
        $student->update(['status' => 'active']);
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'known-password',
        ])->assertStatus(200);

        $student->update(['status' => 'leave']);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'known-password',
        ])->assertStatus(422);
    }

    // ---------------------------------------------------------------------
    // B. TAHUN AJARAN & SEMESTER
    // ---------------------------------------------------------------------

    /** STANDARD: at most one active academic year and one active semester. */
    public function test_only_one_active_academic_year_and_semester_exist(): void
    {
        $this->assertSame(1, AcademicYear::where('status', 'active')->count(), 'Multiple active academic years');
        $this->assertSame(1, Semester::where('status', 'active')->count(), 'Multiple active semesters');
    }

    /**
     * STANDARD: creating a new academic year must NOT silently deactivate the
     * running one — activation is an explicit, deliberate act.
     */
    public function test_creating_an_academic_year_does_not_hijack_the_active_one(): void
    {
        $current = AcademicYear::where('status', 'active')->firstOrFail();

        $this->asPost('/api/v1/academic/academic-years', [
            'name' => '2027/2028',
            'start_date' => '2027-09-01',
            'end_date' => '2028-08-31',
        ], 'admin@siakad.ac.id')->assertStatus(201);

        $this->assertSame(
            'active',
            $current->fresh()->status->value,
            'Creating a new academic year silently deactivated the running one'
        );
    }

    /** STANDARD: creating a semester must not hijack the active period either. */
    public function test_creating_a_semester_does_not_hijack_the_active_period(): void
    {
        $activeSemester = Semester::where('status', 'active')->firstOrFail();
        $year = AcademicYear::where('status', 'active')->firstOrFail();

        $this->asPost('/api/v1/academic/semesters', [
            'academic_year_id' => $year->id,
            'name' => 'Audit Semester Baru',
            'type' => 'genap',
            'start_date' => '2027-02-01',
            'end_date' => '2027-07-31',
        ], 'admin@siakad.ac.id')->assertStatus(201);

        $this->assertSame(
            'active',
            $activeSemester->fresh()->status->value,
            'Creating a new semester silently deactivated the running one'
        );
    }

    /** STANDARD: an academic year that still has KRS/grades cannot be deleted. */
    public function test_academic_year_with_enrollments_cannot_be_deleted(): void
    {
        $year = Semester::where('status', 'active')->firstOrFail()->academicYear;
        $hasEnrollment = StudentEnrollment::whereHas('semester', function ($q) use ($year) {
            $q->where('academic_year_id', $year->id);
        })->exists();

        if (!$hasEnrollment) {
            $this->markTestSkipped('No enrollment data seeded for this academic year.');
        }

        $this->deleteJson("/api/v1/academic/academic-years/{$year->id}", [], [
            'Authorization' => 'Bearer ' . $this->tokenFor('admin@siakad.ac.id'),
        ])->assertStatus(409);
    }

    /** STANDARD: semester date windows must be validated against the parent year. */
    public function test_semester_dates_must_fall_inside_the_parent_academic_year(): void
    {
        $year = AcademicYear::where('status', 'active')->firstOrFail();

        $this->asPost('/api/v1/academic/semesters', [
            'academic_year_id' => $year->id,
            'name' => 'Audit Semester Di Luar TA',
            'type' => 'ganjil',
            'start_date' => '1999-01-01',
            'end_date' => '1999-06-30',
        ], 'admin@siakad.ac.id')->assertStatus(422);
    }

    /** STANDARD: the active semester must carry KRS windows, otherwise gating is a no-op. */
    public function test_active_semester_has_krs_window_configured(): void
    {
        $semester = Semester::where('status', 'active')->firstOrFail();

        $this->assertNotNull($semester->krs_start_date, 'Active semester has no krs_start_date — KRS window check is inert');
        $this->assertNotNull($semester->krs_end_date, 'Active semester has no krs_end_date — KRS window check is inert');
    }

    // ---------------------------------------------------------------------
    // C. KRS
    // ---------------------------------------------------------------------

    /**
     * STANDARD (Permendikbud): SKS ceiling per semester is derived from the
     * previous IPS — 24 / 22 / 20 / 18 tiers — not one global constant.
     */
    public function test_sks_ceiling_follows_the_configured_credit_limit_rules(): void
    {
        CreditLimit::create([
            'name' => 'Audit Batas SKS Berjenjang',
            'rules' => [
                ['min_gpa' => 3.00, 'max_gpa' => 4.00, 'max_sks' => 24],
                ['min_gpa' => 2.50, 'max_gpa' => 2.99, 'max_sks' => 21],
                ['min_gpa' => 2.00, 'max_gpa' => 2.49, 'max_sks' => 18],
                ['min_gpa' => 0.00, 'max_gpa' => 1.99, 'max_sks' => 15],
            ],
        ]);

        $service = app(\Modules\Enrollment\Services\EnrollmentValidationService::class);

        $this->assertTrue(
            method_exists($service, 'getMaxCredits'),
            'EnrollmentValidationService has no IPS-aware max-SKS resolver; credit_limits.rules is dead configuration'
        );
    }

    /** STANDARD: a prerequisite counts as satisfied only when the course was passed (grade >= C). */
    public function test_prerequisite_requires_a_passing_grade(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(\Modules\Enrollment\Services\AcademicHistoryProvider::class))->getFileName()
        );

        $this->assertStringContainsString(
            'StudentGrade',
            $source,
            'Prerequisite check ignores actual grades — any previously enrolled course counts as "passed"'
        );
    }

    /** STANDARD: batal-tambah (drop after approval) must leave an auditable trail, not hard-delete. */
    public function test_dropping_an_enrollment_item_keeps_an_audit_trail(): void
    {
        $this->assertTrue(
            defined(\Modules\Enrollment\Enums\EnrollmentItemStatus::class . '::DROPPED'),
            'DROPPED status exists'
        );

        $source = file_get_contents(
            (new \ReflectionClass(\Modules\Enrollment\Actions\RemoveEnrollmentItemAction::class))->getFileName()
        );

        $this->assertStringNotContainsString(
            '->delete()',
            $source,
            'RemoveEnrollmentItemAction hard-deletes items — no batal-tambah trail (DROPPED/CANCELLED never assigned)'
        );
    }

    /**
     * STANDARD: load-package must be scoped to the student's own academic advisor,
     * the same way approve/reject are guarded by denyUnlessOwnAdvisee().
     */
    public function test_load_package_is_restricted_to_the_own_advisor(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(\Modules\Enrollment\Controllers\EnrollmentController::class))->getFileName()
        );

        $method = substr($source, strpos($source, 'function loadPackage'));
        $method = substr($method, 0, strpos($method, 'public function', 10) ?: strlen($method));

        $this->assertStringContainsString(
            'denyUnlessOwnAdvisee',
            $method,
            'loadPackage has no advisor scoping — any lecturer can bulk-inject a KRS package into any enrollment'
        );
    }

    /**
     * STANDARD: the invariant "exactly one active" must survive ordinary CRUD.
     * A newly created year/semester defaults to `active` in the schema without
     * deactivating the incumbent, so two periods end up active at once.
     */
    public function test_creating_a_year_or_semester_never_yields_two_active_periods(): void
    {
        $this->asPost('/api/v1/academic/academic-years', [
            'name' => '2027/2028',
            'start_date' => '2027-09-01',
            'end_date' => '2028-08-31',
        ], 'admin@siakad.ac.id')->assertStatus(201);

        $year = AcademicYear::where('name', '2027/2028')->firstOrFail();

        $this->asPost('/api/v1/academic/semesters', [
            'academic_year_id' => $year->id,
            'name' => 'Audit Ganjil 2027',
            'type' => 'ganjil',
            'start_date' => '2027-09-01',
            'end_date' => '2028-01-31',
        ], 'admin@siakad.ac.id')->assertStatus(201);

        $this->assertSame(
            1,
            AcademicYear::where('status', 'active')->count(),
            'More than one academic year is active after a plain create'
        );
        $this->assertSame(
            1,
            Semester::where('status', 'active')->count(),
            'More than one semester is active after a plain create'
        );
    }

    /**
     * STANDARD: submit must re-validate the KRS window. A draft assembled inside
     * the window cannot be submitted after it closes.
     */
    public function test_krs_submit_revalidates_the_registration_window(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(\Modules\Enrollment\Actions\SubmitEnrollmentAction::class))->getFileName()
        );

        $this->assertStringContainsString(
            'KrsWindow',
            $source,
            'SubmitEnrollmentAction never re-checks the KRS window — a stale draft can be submitted after the deadline'
        );
    }

    /** STANDARD: per-enrollment max_credits must actually be enforced. */
    public function test_per_enrollment_max_credits_is_enforced(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(\Modules\Enrollment\Services\EnrollmentValidationService::class))->getFileName()
        );

        $this->assertStringContainsString(
            'max_credits',
            $source,
            'The per-enrollment max_credits column is never read by validation — raising an advisor quota has no effect'
        );
    }
}
