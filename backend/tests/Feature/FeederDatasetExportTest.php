<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academic\Models\StudyProgram;
use Modules\Class\Models\AcademicClass;
use Modules\Identity\Models\User;
use Tests\TestCase;

/**
 * Unduhan data pelaporan feeder ("Export as…" di halaman data SIAKAD).
 *
 * Yang dijaga di sini: dataset hanya berisi kolom yang diizinkan, isinya sama dengan
 * yang dilihat feeder (termasuk baris bertingkat: pengajar/jadwal kelas, mata kuliah
 * per kurikulum, mata kuliah per KRS, nilai per mahasiswa), hak akses mengikuti
 * permission halaman asalnya, dan data sensitif tidak bocor ke pengguna yang tidak berhak.
 */
class FeederDatasetExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@siakad.ac.id')->firstOrFail();
        $this->token = $this->admin->createToken('test')->plainTextToken;
    }

    /** Unduhan sebagai admin (Authorization header + URL dataset). */
    private function download(string $path)
    {
        return $this->withHeader('Authorization', "Bearer {$this->token}")->get($path);
    }

    public function test_catalog_only_lists_datasets_the_user_may_download(): void
    {
        $body = json_decode($this->download('/api/v1/integrator/datasets')->assertOk()->getContent(), true);
        $keys = array_column($body['data']['datasets'], 'key');

        // Halaman-halaman yang datanya dikirim ke Neo Feeder / PDDikti.
        foreach (['students', 'lecturers', 'courses', 'curricula', 'classes', 'enrollments',
            'akm', 'grades', 'graduates', 'activities', 'semesters', 'study-programs',
            'faculties', 'institutions', 'academic-years', 'rooms'] as $expected) {
            $this->assertContains($expected, $keys, "Dataset {$expected} hilang dari katalog.");
        }

        // Seorang dosen tanpa hak kelola mahasiswa tidak melihat dataset mahasiswa.
        $lecturer = User::where('email', 'dosen@siakad.ac.id')->firstOrFail();
        $token = $lecturer->createToken('test')->plainTextToken;

        $limited = json_decode(
            $this->withHeader('Authorization', "Bearer {$token}")->get('/api/v1/integrator/datasets')
                ->assertOk()->getContent(),
            true
        );

        $limitedKeys = array_column($limited['data']['datasets'], 'key');

        if (! $lecturer->hasPermissionTo('students.view')) {
            $this->assertNotContains('students', $limitedKeys);
        }

        $this->assertNotContains('users', $keys);
    }

    public function test_students_export_carries_the_columns_the_feeder_needs(): void
    {
        $response = $this->download('/api/v1/integrator/datasets/students/export?format=csv')->assertOk();

        $response->assertHeader('X-Export-Dataset', 'students');
        $response->assertHeader('X-Export-Row-Limit', '50000');

        $body = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $this->assertStringContainsString('ID,NIM,NISN,NIK,Nama,"Nama Panggilan","Jenis Kelamin"', $body);
        $this->assertStringContainsString('"Kode Prodi","Program Studi",Jenjang', $body);

        // Baris nyata dari basis data: nomor induk mahasiswa yang di-seed.
        $student = \Modules\Student\Models\Student::where('student_number', '202501001')->first();
        if ($student) {
            $this->assertStringContainsString('202501001', $body);
        }

        // NIK ditulis apa adanya untuk operator yang memang sudah melihatnya di halaman
        // Data Mahasiswa (bukan versi bertopeng seperti pada API key tanpa scope PII).
        $bodyWithNik = \Modules\Student\Models\Student::whereNotNull('national_id')->value('national_id');
        if ($bodyWithNik) {
            $this->assertStringContainsString((string) $bodyWithNik, $body);
        }
    }

    public function test_nested_feeder_rows_are_expanded_instead_of_dropped(): void
    {
        // Kelas: pengajar dan jadwal harus muncul di kolomnya, bukan kosong.
        $class = AcademicClass::query()->whereHas('lecturers')->whereHas('schedules')->first();
        $lecturer = $class?->lecturers()->first()?->lecturer;
        $schedule = $class?->schedules()->first();

        if ($class && $lecturer && $schedule) {
            $csv = $this->download('/api/v1/integrator/datasets/classes/export?format=csv&semester_id='.$class->semester_id)
                ->assertOk()->streamedContent();

            $this->assertStringContainsString((string) $lecturer->nidn, $csv);
            $this->assertStringContainsString((string) $schedule->room?->name ?? 'Ruang', $csv);
        }

        // Kurikulum: satu baris per mata kuliah (bukan satu baris ringkasan).
        $curricula = $this->download('/api/v1/integrator/datasets/curricula/export?format=csv')->assertOk()->streamedContent();
        $curriculumSubjects = \Modules\Curriculum\Models\Curriculum::query()
            ->withCount('semesters')->get()->sum('semesters_count');
        if ($curriculumSubjects > 0) {
            $this->assertStringContainsString('"Semester Ke","Kode MK","Nama Mata Kuliah",SKS', $curricula);
        }

        // KRS: satu baris per mata kuliah yang diambil.
        $krs = $this->download('/api/v1/integrator/datasets/enrollments/export?format=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('"Kode Kelas","Kode MK","Mata Kuliah",SKS,"Status MK"', $krs);

        // Nilai: satu baris per mahasiswa per kelas.
        $grades = $this->download('/api/v1/integrator/datasets/grades/export?format=csv&semester_id=1')->assertOk()->streamedContent();
        $this->assertStringContainsString('NIM,"Nama Mahasiswa","Nilai Angka","Nilai Huruf",Indeks', $grades);
    }

    public function test_model_backed_reference_datasets_export_labels_or_raw_field_names(): void
    {
        $program = StudyProgram::query()->first();
        $this->assertNotNull($program, 'Seeder harus menyiapkan program studi.');

        $labelled = $this->download('/api/v1/integrator/datasets/study-programs/export?format=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('"Kode Prodi","Nama Singkat","Nama Program Studi",Jenjang,Status,"Kode Fakultas",Fakultas', $labelled);
        $this->assertStringContainsString($program->code, $labelled);
        $this->assertStringContainsString($program->name, $labelled);

        // `?header=api` berguna untuk pencocokan dengan berkas yang ditarik feeder.
        $raw = $this->download('/api/v1/integrator/datasets/study-programs/export?format=csv&header=api')->assertOk()->streamedContent();
        $this->assertStringContainsString('code,short_name,name,degree,status,faculty.code,faculty.name', $raw);

        // Data referensi lain juga tersedia.
        foreach (['faculties', 'institutions', 'academic-years', 'rooms', 'semesters'] as $dataset) {
            $this->download("/api/v1/integrator/datasets/{$dataset}/export?format=csv")->assertOk();
        }
    }

    public function test_json_export_uses_raw_field_names_and_metadata(): void
    {
        $body = $this->download('/api/v1/integrator/datasets/students/export?format=json')
            ->assertOk()
            ->streamedContent();

        $decoded = json_decode($body, true);

        $this->assertSame('students', $decoded['meta']['dataset']);
        $this->assertSame('Data Mahasiswa', $decoded['meta']['dataset_label']);
        $this->assertArrayHasKey('generated_by', $decoded['meta']);
        $this->assertContains('nim', $decoded['headers']);

        if ($decoded['rows'] > 0) {
            $this->assertArrayHasKey('nim', $decoded['data'][0]);
            $this->assertArrayHasKey('study_program.code', $decoded['data'][0]);
        }
    }

    public function test_holistic_filters_are_forwarded_but_unknown_parameters_are_ignored(): void
    {
        $program = StudyProgram::query()->firstOrFail();

        $filtered = $this->download('/api/v1/integrator/datasets/students/export?format=csv&study_program_id='.$program->id)
            ->assertOk()
            ->streamedContent();

        $other = StudyProgram::where('id', '!=', $program->id)->first();

        if ($other) {
            $otherStudent = \Modules\Student\Models\Student::where('study_program_id', $other->id)->first();

            if ($otherStudent) {
                $this->assertStringNotContainsString($otherStudent->student_number, $filtered);
            }
        }

        // Parameter asing tidak boleh mengubah isi berkas (mis. mencoba membaca tabel lain).
        $this->download('/api/v1/integrator/datasets/students/export?format=csv&with=password&columns=*')
            ->assertOk()
            ->assertHeader('X-Export-Dataset', 'students');
    }

    public function test_dataset_requiring_a_semester_says_so_instead_of_failing(): void
    {
        $response = $this->download('/api/v1/integrator/datasets/akm/export?format=csv');

        $response->assertStatus(422);
        $this->assertStringContainsString('semester_id', (string) $response->getContent());

        $this->download('/api/v1/integrator/datasets/akm/export?format=csv&semester_id=1')->assertOk();
    }

    public function test_unknown_dataset_is_not_downloadable(): void
    {
        $this->download('/api/v1/integrator/datasets/tidak-ada/export?format=csv')->assertNotFound();
        $this->download('/api/v1/integrator/datasets/users/export?format=csv')->assertNotFound();
    }

    public function test_download_requires_the_permission_of_the_origin_page(): void
    {
        $outsider = User::query()->get()->first(
            static fn (User $user) => ! $user->hasPermissionTo('students.view')
        );

        $this->assertNotNull($outsider, 'Perlu satu pengguna tanpa hak students.view untuk menguji penolakan.');

        $token = $outsider->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->get('/api/v1/integrator/datasets/students/export')
            ->assertStatus(403);

        // Dataset lain yang jelas bukan haknya (dipilih dinamis supaya tes tetap sahih
        // bila seeder memberi mahasiswa akses baca data referensi tertentu).
        $blocked = collect(\Modules\Integrator\Services\IntegratorExportService::DATASETS)
            ->except(['students', 'study-programs'])
            ->first(fn (array $definition) => ! $outsider->hasPermissionTo($definition['permission']));

        if ($blocked) {
            $key = collect(\Modules\Integrator\Services\IntegratorExportService::DATASETS)
                ->search(fn (array $definition) => $definition === $blocked);

            $this->withHeader('Authorization', "Bearer {$token}")
                ->get("/api/v1/integrator/datasets/{$key}/export")
                ->assertStatus(403);
        }
    }

    public function test_export_is_recorded_in_the_audit_log(): void
    {
        \Illuminate\Support\Facades\Log::spy();

        $this->download('/api/v1/integrator/datasets/rooms/export?format=csv')->assertOk();

        \Illuminate\Support\Facades\Log::shouldHaveReceived('info')
            ->withArgs(fn (string $message, array $context = []) => $message === 'integrator.export'
                && ($context['dataset'] ?? null) === 'rooms'
                && ($context['generated_by']['email'] ?? null) === $this->admin->email)
            ->once();
    }
}
