<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academic\Database\Seeders\AcademicSeeder;
use Modules\Academic\Enums\AcademicStatus;
use Modules\Academic\Enums\SemesterType;
use Modules\Academic\Models\AcademicYear;
use Modules\Academic\Models\Faculty;
use Modules\Academic\Models\Institution;
use Modules\Academic\Models\Semester;
use Modules\Academic\Models\StudyProgram;
use Modules\Class\Enums\ClassStatus;
use Modules\Class\Models\AcademicClass;
use Modules\Course\Models\Course;
use Modules\Enrollment\Enums\EnrollmentStatus;
use Modules\Enrollment\Models\StudentEnrollment;
use Modules\Identity\Models\User;
use Modules\Student\Models\Student;
use Tests\TestCase;

class AcademicTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@siakad.ac.id')->first();
        $this->token = $this->admin->createToken('test_token')->plainTextToken;
    }

    public function test_can_list_and_filter_institutions(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/academic/institutions?search=IAI');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => ['id', 'name', 'code', 'status'],
                ],
                'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }

    public function test_can_create_institution(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/academic/institutions', [
                'name' => 'Institut Baru',
                'short_name' => 'IB',
                'code' => 'IB-002',
                'address' => 'Jl. Baru No. 10',
                'phone' => '08123456789',
                'website' => 'https://ib.ac.id',
                'status' => 'active',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'code' => 'IB-002',
                ],
            ]);

        $this->assertDatabaseHas('institutions', ['code' => 'IB-002']);
    }

    public function test_can_create_faculty_linked_to_institution(): void
    {
        $institution = Institution::first();

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/academic/faculties', [
                'institution_id' => $institution->id,
                'code' => 'FK',
                'name' => 'Fakultas Kedokteran',
                'status' => 'active',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'code' => 'FK',
                    'institution_id' => $institution->id,
                ],
            ]);
    }

    public function test_can_create_study_program_linked_to_faculty(): void
    {
        $faculty = Faculty::first();

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/academic/study-programs', [
                'faculty_id' => $faculty->id,
                'code' => 'IF',
                'name' => 'Informatika',
                'degree' => 'S1',
                'status' => 'active',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'code' => 'IF',
                    'degree' => 'S1',
                ],
            ]);
    }

    public function test_academic_year_and_semesters_relationship(): void
    {
        $academicYear = AcademicYear::where('status', 'active')->firstOrFail();
        $this->assertCount(2, $academicYear->semesters);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/academic/academic-years/{$academicYear->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $academicYear->id,
                    'name' => $academicYear->name,
                ],
            ]);
    }

    // -----------------------------------------------------------------
    // Gap #9 — invariant "hanya satu periode akademik aktif"
    // -----------------------------------------------------------------

    public function test_creating_an_academic_year_without_status_defaults_to_inactive(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/academic/academic-years', [
                'name' => '2099/3000',
                'start_date' => '2099-09-01',
                'end_date' => '2100-08-31',
            ]);

        $response->assertStatus(201)->assertJsonPath('data.status', 'inactive');
        $this->assertSame(1, AcademicYear::where('status', 'active')->count());
    }

    public function test_creating_a_semester_without_status_defaults_to_inactive(): void
    {
        $year = AcademicYear::where('status', 'active')->firstOrFail();

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/academic/semesters', [
                'academic_year_id' => $year->id,
                'name' => 'Semester Uji Nonaktif',
                'type' => 'pendek',
                'start_date' => $year->start_date->toDateString(),
                'end_date' => $year->end_date->toDateString(),
            ]);

        $response->assertStatus(201)->assertJsonPath('data.status', 'inactive');
        $this->assertSame(1, Semester::where('status', 'active')->count());
    }

    public function test_setting_a_year_active_deactivates_the_previous_one(): void
    {
        $incumbent = AcademicYear::where('status', 'active')->firstOrFail();
        $target = AcademicYear::where('id', '!=', $incumbent->id)->firstOrFail();

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson("/api/v1/academic/academic-years/{$target->id}/set-active")
            ->assertStatus(200);

        $this->assertSame(1, AcademicYear::where('status', 'active')->count());
        $this->assertSame('inactive', $incumbent->fresh()->status->value);
        $this->assertSame('active', $target->fresh()->status->value);
    }

    // -----------------------------------------------------------------
    // Gap #10 — periode akademik yang masih dipakai tidak boleh dihapus
    // -----------------------------------------------------------------

    public function test_active_academic_year_cannot_be_deleted(): void
    {
        $year = AcademicYear::where('status', 'active')->firstOrFail();

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->deleteJson("/api/v1/academic/academic-years/{$year->id}")
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('academic_years', ['id' => $year->id]);
    }

    public function test_active_semester_cannot_be_deleted(): void
    {
        $semester = Semester::where('status', 'active')->firstOrFail();

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->deleteJson("/api/v1/academic/semesters/{$semester->id}")
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('semesters', ['id' => $semester->id]);
    }

    public function test_semester_with_enrollment_cannot_be_deleted(): void
    {
        [$year, $semester] = $this->createUnusedPeriod('2031/2032');

        StudentEnrollment::create([
            'student_id' => Student::query()->orderBy('id')->value('id'),
            'semester_id' => $semester->id,
            'status' => EnrollmentStatus::DRAFT,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->deleteJson("/api/v1/academic/semesters/{$semester->id}");

        $response->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertStringContainsString(
            'student_enrollments',
            $response->json('message'),
            'Pesan 409 harus menyebutkan data yang menghalangi penghapusan.'
        );
        $this->assertDatabaseHas('semesters', ['id' => $semester->id]);

        // Tahun ajaran induk ikut terlindungi secara transitif.
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->deleteJson("/api/v1/academic/academic-years/{$year->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('academic_years', ['id' => $year->id]);
    }

    public function test_semester_with_class_cannot_be_deleted(): void
    {
        [, $semester] = $this->createUnusedPeriod('2032/2033');

        AcademicClass::create([
            'semester_id' => $semester->id,
            'course_id' => Course::query()->orderBy('id')->value('id'),
            'study_program_id' => StudyProgram::query()->orderBy('id')->value('id'),
            'code' => 'GUARD-101',
            'name' => 'Kelas Uji Penghapusan',
            'section' => 'A',
            'capacity' => 30,
            'status' => ClassStatus::OPEN,
        ]);

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->deleteJson("/api/v1/academic/semesters/{$semester->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('semesters', ['id' => $semester->id]);
    }

    public function test_unused_academic_period_can_be_deleted(): void
    {
        [$year, $semester] = $this->createUnusedPeriod('2033/2034');

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->deleteJson("/api/v1/academic/semesters/{$semester->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('semesters', ['id' => $semester->id]);

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->deleteJson("/api/v1/academic/academic-years/{$year->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('academic_years', ['id' => $year->id]);
    }

    // -----------------------------------------------------------------
    // Gap #11 — tanggal semester divalidasi terhadap tahun ajaran induk
    // -----------------------------------------------------------------

    public function test_semester_dates_outside_the_academic_year_are_rejected(): void
    {
        $year = AcademicYear::where('status', 'active')->firstOrFail();

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/academic/semesters', [
                'academic_year_id' => $year->id,
                'name' => 'Semester Di Luar TA',
                'type' => 'ganjil',
                'start_date' => '1999-01-01',
                'end_date' => '1999-06-30',
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['start_date']);
        $this->assertDatabaseMissing('semesters', ['name' => 'Semester Di Luar TA']);
    }

    public function test_semester_partially_outside_the_academic_year_is_rejected(): void
    {
        $year = AcademicYear::where('status', 'active')->firstOrFail();

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/academic/semesters', [
                'academic_year_id' => $year->id,
                'name' => 'Semester Melewati Akhir TA',
                'type' => 'ganjil',
                'start_date' => $year->start_date->toDateString(),
                'end_date' => $year->end_date->copy()->addMonth()->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['start_date']);
    }

    public function test_sub_windows_outside_the_semester_period_are_rejected(): void
    {
        $year = AcademicYear::where('status', 'active')->firstOrFail();
        $start = $year->start_date->copy();
        $end = $start->copy()->addMonths(4);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/academic/semesters', [
                'academic_year_id' => $year->id,
                'name' => 'Semester Jendela Salah',
                'type' => 'ganjil',
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'krs_start_date' => $start->copy()->subMonth()->toDateString(),
                'krs_end_date' => $end->copy()->addMonth()->toDateString(),
                'uas_start_date' => $end->copy()->addMonths(2)->toDateString(),
                'uas_end_date' => $end->copy()->addMonths(3)->toDateString(),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'krs_start_date',
                'krs_end_date',
                'uas_start_date',
                'uas_end_date',
            ]);
    }

    public function test_inverted_sub_window_is_rejected(): void
    {
        $year = AcademicYear::where('status', 'active')->firstOrFail();
        $start = $year->start_date->copy();
        $end = $start->copy()->addMonths(4);

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/academic/semesters', [
                'academic_year_id' => $year->id,
                'name' => 'Semester UTS Terbalik',
                'type' => 'ganjil',
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'uts_start_date' => $end->toDateString(),
                'uts_end_date' => $start->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['uts_end_date']);
    }

    public function test_lecture_window_must_precede_uts_and_uas(): void
    {
        $year = AcademicYear::where('status', 'active')->firstOrFail();
        $start = $year->start_date->copy();
        $end = $start->copy()->addMonths(5);

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/academic/semesters', [
                'academic_year_id' => $year->id,
                'name' => 'Semester Kuliah Setelah UTS',
                'type' => 'ganjil',
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'lecture_start_date' => $start->copy()->addMonths(3)->toDateString(),
                'lecture_end_date' => $end->toDateString(),
                'uts_start_date' => $start->copy()->addMonth()->toDateString(),
                'uts_end_date' => $start->copy()->addMonth()->addDays(7)->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['uts_start_date']);
    }

    public function test_valid_semester_with_full_windows_is_accepted(): void
    {
        $year = AcademicYear::where('status', 'active')->firstOrFail();
        $start = $year->start_date->copy();
        $end = $start->copy()->addMonths(5);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/academic/semesters', [
                'academic_year_id' => $year->id,
                'name' => 'Semester Valid Lengkap',
                'type' => 'ganjil',
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'krs_start_date' => $start->toDateString(),
                'krs_end_date' => $start->copy()->addDays(21)->toDateString(),
                'kprs_start_date' => $start->copy()->addDays(22)->toDateString(),
                'kprs_end_date' => $start->copy()->addDays(29)->toDateString(),
                'lecture_start_date' => $start->toDateString(),
                'lecture_end_date' => $end->copy()->subDays(8)->toDateString(),
                'uts_start_date' => $start->copy()->addMonths(2)->toDateString(),
                'uts_end_date' => $start->copy()->addMonths(2)->addDays(7)->toDateString(),
                'uas_start_date' => $end->copy()->subDays(7)->toDateString(),
                'uas_end_date' => $end->toDateString(),
            ]);

        $response->assertStatus(201)->assertJsonPath('data.status', 'inactive');
        $this->assertSame(1, Semester::where('status', 'active')->count());
    }

    // -----------------------------------------------------------------
    // Gap #12 — semester aktif harus punya jendela KRS yang konsisten
    // -----------------------------------------------------------------

    public function test_every_seeded_semester_has_consistent_academic_windows(): void
    {
        $active = Semester::where('status', 'active')->firstOrFail();

        $this->assertNotNull($active->krs_start_date);
        $this->assertNotNull($active->krs_end_date);

        // Jendela KRS semester berjalan harus mencakup hari ini, jika tidak
        // seluruh alur KRS akan selalu ditolak oleh EnrollmentValidationService.
        $today = now()->startOfDay();
        $this->assertTrue(
            $today->betweenIncluded($active->krs_start_date->startOfDay(), $active->krs_end_date->endOfDay()),
            'Jendela KRS semester aktif tidak mencakup tanggal hari ini.'
        );

        foreach (Semester::all() as $semester) {
            foreach ([
                'krs' => ['krs_start_date', 'krs_end_date'],
                'kprs' => ['kprs_start_date', 'kprs_end_date'],
                'lecture' => ['lecture_start_date', 'lecture_end_date'],
                'uts' => ['uts_start_date', 'uts_end_date'],
                'uas' => ['uas_start_date', 'uas_end_date'],
            ] as $label => [$from, $to]) {
                $this->assertNotNull($semester->{$from}, "{$semester->name}: {$from} masih NULL.");
                $this->assertNotNull($semester->{$to}, "{$semester->name}: {$to} masih NULL.");
                $this->assertFalse(
                    $semester->{$to}->lt($semester->{$from}),
                    "{$semester->name}: jendela {$label} terbalik."
                );
                $this->assertFalse(
                    $semester->{$from}->lt($semester->start_date->startOfDay())
                        || $semester->{$to}->gt($semester->end_date->startOfDay()),
                    "{$semester->name}: jendela {$label} keluar dari rentang semester."
                );
            }

            $this->assertFalse(
                $semester->uts_start_date->lt($semester->lecture_start_date),
                "{$semester->name}: UTS dimulai sebelum perkuliahan."
            );
            $this->assertFalse(
                $semester->uas_start_date->lt($semester->lecture_start_date),
                "{$semester->name}: UAS dimulai sebelum perkuliahan."
            );

            // Semester harus berada di dalam tahun ajaran induknya.
            $year = $semester->academicYear;
            $this->assertFalse(
                $semester->start_date->lt($year->start_date->startOfDay())
                    || $semester->end_date->gt($year->end_date->startOfDay()),
                "{$semester->name}: keluar dari rentang tahun ajaran {$year->name}."
            );
        }
    }

    public function test_academic_seeder_is_idempotent(): void
    {
        $before = [
            'years' => AcademicYear::count(),
            'semesters' => Semester::count(),
            'active_year' => AcademicYear::where('status', 'active')->value('id'),
            'active_semester' => Semester::where('status', 'active')->value('id'),
        ];

        $this->seed(AcademicSeeder::class);

        $this->assertSame($before['years'], AcademicYear::count());
        $this->assertSame($before['semesters'], Semester::count());
        $this->assertSame($before['active_year'], AcademicYear::where('status', 'active')->value('id'));
        $this->assertSame($before['active_semester'], Semester::where('status', 'active')->value('id'));
        $this->assertSame(1, AcademicYear::where('status', 'active')->count());
        $this->assertSame(1, Semester::where('status', 'active')->count());
    }

    /**
     * Buat satu tahun ajaran nonaktif + satu semester tanpa data turunan apa pun.
     *
     * @return array{0: AcademicYear, 1: Semester}
     */
    private function createUnusedPeriod(string $name): array
    {
        $startYear = (int) explode('/', $name)[0];

        $year = AcademicYear::create([
            'name' => $name,
            'start_date' => "{$startYear}-09-01",
            'end_date' => ($startYear + 1) . '-08-31',
            'status' => AcademicStatus::INACTIVE,
        ]);

        $semester = Semester::create([
            'academic_year_id' => $year->id,
            'name' => "Ganjil {$name}",
            'type' => SemesterType::GANJIL,
            'start_date' => "{$startYear}-09-01",
            'end_date' => ($startYear + 1) . '-01-31',
            'status' => AcademicStatus::INACTIVE,
        ]);

        return [$year, $semester];
    }
}
