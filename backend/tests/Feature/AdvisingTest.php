<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Advising\Enums\AdvisorStatus;
use Modules\Advising\Models\AcademicAdvisor;
use Modules\Advising\Models\AdvisingSession;
use Modules\Identity\Models\User;
use Modules\Lecturer\Enums\LecturerStatus;
use Modules\Lecturer\Models\Lecturer;
use Modules\Student\Models\Student;
use Tests\TestCase;

class AdvisingTest extends TestCase
{
    use RefreshDatabase;

    protected string $adminToken;
    protected string $studentToken;
    protected string $lecturerToken;
    protected User $admin;
    protected User $lecturerUser;
    protected Student $student;
    protected Lecturer $lecturer1;
    protected Lecturer $lecturer2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@siakad.ac.id')->first();
        $this->adminToken = $this->admin->createToken('admin_token')->plainTextToken;

        $studentUser = User::where('email', 'mahasiswa@siakad.ac.id')->first();
        $this->studentToken = $studentUser->createToken('student_token')->plainTextToken;

        // dosen@siakad.ac.id is linked to lecturer nidn 0011223301, who advises
        // students 202501001, 202501002 and 202501099 in the seeded data.
        $this->lecturerUser = User::where('email', 'dosen@siakad.ac.id')->first();
        $this->lecturerToken = $this->lecturerUser->createToken('lecturer_token')->plainTextToken;

        $this->student = Student::where('student_number', '202501001')->first();
        $this->lecturer1 = Lecturer::where('nidn', '0011223301')->first();
        $this->lecturer2 = Lecturer::where('nidn', '0011223302')->first();
    }

    /**
     * @return array{0: Lecturer, 1: Lecturer} [own lecturer profile, another lecturer]
     */
    protected function lecturerProfiles(): array
    {
        $own = Lecturer::where('user_id', $this->lecturerUser->id)->firstOrFail();
        $other = Lecturer::where('id', '!=', $own->id)->firstOrFail();

        return [$own, $other];
    }

    public function test_can_get_current_student_advisor(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson("/api/v1/students/{$this->student->id}/advisor");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'student_id' => $this->student->id,
                    'lecturer_id' => $this->lecturer1->id,
                    'status' => 'active',
                ],
            ]);
    }

    public function test_can_change_academic_advisor_and_transition_old_assignment(): void
    {
        $payload = [
            'new_lecturer_id' => $this->lecturer2->id,
            'change_date' => now()->format('Y-m-d'),
            'reason' => 'Pergantian pembimbing karena dosen sebelumnya menjabat struktural.',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/students/{$this->student->id}/advisor", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'student_id' => $this->student->id,
                    'lecturer_id' => $this->lecturer2->id,
                    'status' => 'active',
                ],
            ]);

        // Old advisor should be transitioned to 'transferred'
        $oldAdvisor = AcademicAdvisor::where('student_id', $this->student->id)
            ->where('lecturer_id', $this->lecturer1->id)
            ->first();

        $this->assertEquals(AdvisorStatus::TRANSFERRED, $oldAdvisor->status);
    }

    public function test_rejects_inactive_lecturer_as_advisor(): void
    {
        $inactiveLecturer = Lecturer::where('nidn', '0011223303')->first();
        $inactiveLecturer->update(['status' => LecturerStatus::INACTIVE]);

        $payload = [
            'student_id' => $this->student->id,
            'lecturer_id' => $inactiveLecturer->id,
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/advisors', $payload);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['lecturer_id']]);
    }

    public function test_can_create_advising_session(): void
    {
        $payload = [
            'student_id' => $this->student->id,
            'lecturer_id' => $this->lecturer1->id,
            'session_date' => now()->format('Y-m-d'),
            'topic' => 'Konsultasi Rencana Studi Semester Ganjil',
            'notes' => 'Mahasiswa diarahkan mengambil 20 SKS dan fokus pada pemahaman konsep dasar pedagogik.',
            'status' => 'completed',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/advising-sessions', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'topic' => 'Konsultasi Rencana Studi Semester Ganjil',
                    'status' => 'completed',
                ],
            ]);

        $this->assertDatabaseHas('advising_sessions', [
            'student_id' => $this->student->id,
            'topic' => 'Konsultasi Rencana Studi Semester Ganjil',
        ]);
    }

    // ---------------------------------------------------------------------
    // Lecturer data scoping: a plain lecturer (role dosen, no advising.assign)
    // must only ever see their own advisees and their own consultations.
    // ---------------------------------------------------------------------

    public function test_lecturer_only_sees_their_own_advisees(): void
    {
        [$own] = $this->lecturerProfiles();

        $response = $this->withHeader('Authorization', "Bearer {$this->lecturerToken}")
            ->getJson('/api/v1/advisors?per_page=100');

        $response->assertStatus(200);

        $rows = collect($response->json('data'));

        $this->assertNotEmpty($rows);
        $this->assertTrue(
            $rows->every(fn ($row) => $row['lecturer_id'] === $own->id),
            'Every advisor row must belong to the authenticated lecturer.'
        );
        $this->assertLessThan(AcademicAdvisor::count(), $rows->count());
    }

    public function test_lecturer_only_sees_their_own_students_in_distribution(): void
    {
        [$own] = $this->lecturerProfiles();

        $response = $this->withHeader('Authorization', "Bearer {$this->lecturerToken}")
            ->getJson('/api/v1/advising/distribution');

        $response->assertStatus(200);

        $rows = collect($response->json('data'));

        $this->assertNotEmpty($rows);
        $this->assertTrue(
            $rows->every(fn ($row) => $row['academic_advisor_id'] === $own->id),
            'Every student row must list the authenticated lecturer as the advisor.'
        );

        $expected = Student::whereHas('academicAdvisor', function ($q) use ($own) {
            $q->where('lecturer_id', $own->id);
        })->pluck('id')->sort()->values()->all();

        $this->assertEquals($expected, $rows->pluck('id')->sort()->values()->all());
        $this->assertLessThan(Student::count(), $rows->count());
    }

    public function test_lecturer_only_sees_their_own_advising_sessions(): void
    {
        [$own] = $this->lecturerProfiles();

        $response = $this->withHeader('Authorization', "Bearer {$this->lecturerToken}")
            ->getJson('/api/v1/advising-sessions?per_page=100');

        $response->assertStatus(200);

        $rows = collect($response->json('data'));

        $this->assertNotEmpty($rows);
        $this->assertTrue(
            $rows->every(fn ($row) => $row['lecturer_id'] === $own->id),
            'Every advising session must belong to the authenticated lecturer.'
        );
        $this->assertLessThan(AdvisingSession::count(), $rows->count());
    }

    public function test_lecturer_cannot_widen_scope_with_lecturer_id_filter(): void
    {
        [$own, $other] = $this->lecturerProfiles();

        $response = $this->withHeader('Authorization', "Bearer {$this->lecturerToken}")
            ->getJson("/api/v1/advising-sessions?per_page=100&lecturer_id={$other->id}");

        $response->assertStatus(200);

        // The forced scope wins over the query filter, so the result is empty
        // rather than leaking the other lecturer's consultations.
        $this->assertCount(0, $response->json('data'));
        $this->assertNotEquals($own->id, $other->id);
    }

    public function test_lecturer_cannot_view_another_lecturers_advisor_assignment(): void
    {
        [, $other] = $this->lecturerProfiles();

        $foreign = AcademicAdvisor::where('lecturer_id', $other->id)->firstOrFail();

        $response = $this->withHeader('Authorization', "Bearer {$this->lecturerToken}")
            ->getJson("/api/v1/advisors/{$foreign->id}");

        $response->assertStatus(403);
    }

    public function test_lecturer_cannot_view_another_lecturers_advising_session(): void
    {
        [$own] = $this->lecturerProfiles();

        // Pick a session owned by someone else (seeded sessions exist for
        // lecturer 0011223301 = own, and 0011223305).
        $foreign = AdvisingSession::where('lecturer_id', '!=', $own->id)->firstOrFail();

        $response = $this->withHeader('Authorization', "Bearer {$this->lecturerToken}")
            ->getJson("/api/v1/advising-sessions/{$foreign->id}");

        $response->assertStatus(403);
    }

    public function test_lecturer_cannot_log_a_session_for_another_lecturer(): void
    {
        [, $other] = $this->lecturerProfiles();

        $payload = [
            'student_id' => $this->student->id,
            'lecturer_id' => $other->id,
            'session_date' => now()->format('Y-m-d'),
            'topic' => 'Konsultasi palsu',
            'notes' => 'Percobaan mencatat sesi atas nama dosen lain.',
            'status' => 'completed',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->lecturerToken}")
            ->postJson('/api/v1/advising-sessions', $payload);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('advising_sessions', ['topic' => 'Konsultasi palsu']);
    }

    public function test_admin_sees_every_advisor_and_session(): void
    {
        // First request as the lecturer, then as admin. Sanctum caches the resolved
        // user on the guard, so the guards must be reset before switching identity.
        $lecturerResponse = $this->withHeader('Authorization', "Bearer {$this->lecturerToken}")
            ->getJson('/api/v1/advisors?per_page=100');
        $lecturerResponse->assertStatus(200);
        $lecturerCount = count($lecturerResponse->json('data'));

        $this->app['auth']->forgetGuards();

        $adminResponse = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/advisors?per_page=100');
        $adminResponse->assertStatus(200);

        $this->assertCount(AcademicAdvisor::count(), $adminResponse->json('data'));
        $this->assertGreaterThan($lecturerCount, count($adminResponse->json('data')));
    }
}
