<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Academic\Models\Semester;
use Modules\Class\Models\AcademicClass;
use Modules\Curriculum\Models\GradeScale;
use Modules\Enrollment\Models\StudentEnrollment;
use Modules\Identity\Models\User;
use Modules\Integrator\Models\ApiClient;
use Modules\Integrator\Models\ApiKey;
use Modules\Integrator\Services\ApiKeyService;
use Tests\TestCase;

class IntegratorTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $adminAkademik;

    protected string $superAdminToken;

    protected string $adminAkademikToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'admin@siakad.ac.id')->firstOrFail();
        $this->adminAkademik = User::where('email', 'akademik@siakad.ac.id')->firstOrFail();

        $this->superAdminToken = $this->superAdmin->createToken('test')->plainTextToken;
        $this->adminAkademikToken = $this->adminAkademik->createToken('test')->plainTextToken;
    }

    /* --------------------------------------------------------------------- */
    /* Staff management surface                                              */
    /* --------------------------------------------------------------------- */

    public function test_admin_akademik_can_manage_clients_and_keys_over_http(): void
    {
        $this->assertTrue($this->adminAkademik->hasPermissionTo('integrator.clients.manage'));

        $create = $this->withHeader('Authorization', "Bearer {$this->adminAkademikToken}")
            ->postJson('/api/v1/integrator/clients', [
                'name' => 'Integrator Neo Feeder',
                'description' => 'Jembatan SIAKAD ke Neo Feeder',
                'contact_email' => 'operator@siakad.ac.id',
                'allowed_ips' => ['10.0.0.0/8', '127.0.0.1'],
                'rate_limit_per_minute' => 60,
            ]);

        $create->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Integrator Neo Feeder',
                    'slug' => 'integrator-neo-feeder',
                    'is_active' => true,
                ],
            ]);

        $clientId = $create->json('data.id');

        $issued = $this->withHeader('Authorization', "Bearer {$this->adminAkademikToken}")
            ->postJson("/api/v1/integrator/clients/{$clientId}/keys", [
                'name' => 'Kunci produksi',
                'scopes' => ['students.read', 'grades.read'],
            ]);

        $issued->assertStatus(201)
            ->assertJsonPath('data.key.key_prefix', fn ($prefix) => is_string($prefix) && str_starts_with($prefix, 'sk_'))
            ->assertJsonPath('data.key.scopes', ['students.read', 'grades.read'])
            ->assertJsonPath('data.key.status', 'active');

        $plainKey = $issued->json('data.plain_key');
        $this->assertIsString($plainKey);
        $this->assertStringContainsString('.', $plainKey);

        // Only the hash is persisted.
        $stored = ApiKey::where('key_prefix', $issued->json('data.key.key_prefix'))->firstOrFail();
        $this->assertSame(hash('sha256', $plainKey), $stored->key_hash);
        $this->assertDatabaseMissing('api_keys', ['key_hash' => $plainKey]);
        $this->assertFalse(DB::table('api_keys')->get()->contains(fn ($row) => str_contains(json_encode($row), $plainKey)));

        // The hash never leaves the server.
        $this->withHeader('Authorization', "Bearer {$this->adminAkademikToken}")
            ->getJson('/api/v1/integrator/keys')
            ->assertStatus(200)
            ->assertJsonMissing(['key_hash' => $stored->key_hash])
            ->assertJsonPath('data.0.plain_key', null);

        $this->withHeader('Authorization', "Bearer {$this->adminAkademikToken}")
            ->getJson('/api/v1/integrator/scopes')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['scopes', 'groups', 'defaults', 'statuses']]);
    }

    public function test_roles_without_permission_cannot_manage_integration(): void
    {
        $mahasiswa = User::where('email', 'mahasiswa@siakad.ac.id')->firstOrFail();
        $token = $mahasiswa->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/integrator/clients')
            ->assertStatus(403);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/integrator/clients', ['name' => 'Nakal'])
            ->assertStatus(403);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/integrator/logs')
            ->assertStatus(403);
    }

    /* --------------------------------------------------------------------- */
    /* Key authentication                                                     */
    /* --------------------------------------------------------------------- */

    public function test_integration_endpoints_require_a_valid_key(): void
    {
        $this->getJson('/api/v1/integrator/v1/ping')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'API key required.');

        $this->withHeader('X-API-Key', 'sk_nonexistent.notarealtoken')
            ->getJson('/api/v1/integrator/v1/ping')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Invalid API key.');

        // A syntactically valid prefix with the wrong secret must fail too.
        ['client' => $client, 'key' => $key, 'plain' => $plain] = $this->issueKey(['reference.read']);

        $forged = $key->key_prefix.'.'.Str::random(48);

        $this->withHeader('X-API-Key', $forged)
            ->getJson('/api/v1/integrator/v1/ping')
            ->assertStatus(401);

        $this->withHeader('X-API-Key', $plain)
            ->getJson('/api/v1/integrator/v1/ping')
            ->assertStatus(200)
            ->assertJsonPath('data.client.slug', $client->slug)
            ->assertJsonPath('data.key.scopes', ['reference.read'])
            ->assertHeader('X-Integrator-Client', $client->slug);

        // Bearer tokens work as well, so agents without custom header support connect.
        $this->withHeader('Authorization', "Bearer {$plain}")
            ->getJson('/api/v1/integrator/v1/ping')
            ->assertStatus(200);
    }

    public function test_revoked_expired_and_inactive_credentials_are_rejected(): void
    {
        // Revoked key.
        ['key' => $revokedKey, 'plain' => $revokedPlain] = $this->issueKey(['reference.read']);
        app(ApiKeyService::class)->revoke($revokedKey, 'Compromised');

        $this->withHeader('X-API-Key', $revokedPlain)
            ->getJson('/api/v1/integrator/v1/ping')
            ->assertStatus(401)
            ->assertJsonPath('message', 'API key has been revoked.');

        // Expired key.
        ['plain' => $expiredPlain] = $this->issueKey(['reference.read'], [
            'expires_at' => now()->subDay()->toDateTimeString(),
        ]);

        $this->withHeader('X-API-Key', $expiredPlain)
            ->getJson('/api/v1/integrator/v1/ping')
            ->assertStatus(401)
            ->assertJsonPath('message', 'API key has expired.');

        // Deactivated client.
        ['plain' => $inactivePlain] = $this->issueKey(['reference.read'], ['is_active' => false]);

        $this->withHeader('X-API-Key', $inactivePlain)
            ->getJson('/api/v1/integrator/v1/ping')
            ->assertStatus(401)
            ->assertJsonPath('message', 'API client is inactive.');

        // Source IP outside the allow-list.
        ['plain' => $ipBoundPlain] = $this->issueKey(['reference.read'], ['allowed_ips' => ['10.0.0.0/8']]);

        $this->withHeader('X-API-Key', $ipBoundPlain)
            ->getJson('/api/v1/integrator/v1/ping')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Request IP is not allowed for this API client.');
    }

    public function test_scope_is_enforced_per_endpoint(): void
    {
        ['plain' => $plain] = $this->issueKey(['students.read']);

        $this->withHeader('X-API-Key', $plain)
            ->getJson('/api/v1/integrator/v1/students')
            ->assertStatus(200);

        $reply = $this->withHeader('X-API-Key', $plain)
            ->getJson('/api/v1/integrator/v1/grades?class_id=1');

        $reply->assertStatus(403)
            ->assertJsonPath('message', 'API key is missing a required scope.')
            ->assertJsonPath('errors.required_scopes', ['grades.read']);

        // Endpoints behind another scope are equally blocked.
        $this->withHeader('X-API-Key', $plain)
            ->getJson('/api/v1/integrator/v1/graduates')
            ->assertStatus(403);

        $this->withHeader('X-API-Key', $plain)
            ->getJson('/api/v1/integrator/v1/lecturers')
            ->assertStatus(403);
    }

    public function test_rate_limit_is_enforced_per_client(): void
    {
        ['plain' => $plain] = $this->issueKey(['reference.read'], ['rate_limit_per_minute' => 2]);

        $this->withHeader('X-API-Key', $plain)
            ->getJson('/api/v1/integrator/v1/ping')
            ->assertStatus(200)
            ->assertHeader('X-RateLimit-Limit', '2');

        $this->withHeader('X-API-Key', $plain)
            ->getJson('/api/v1/integrator/v1/ping')
            ->assertStatus(200);

        $this->withHeader('X-API-Key', $plain)
            ->getJson('/api/v1/integrator/v1/ping')
            ->assertStatus(429)
            ->assertJsonPath('message', 'Rate limit exceeded.')
            ->assertHeader('Retry-After');
    }

    /* --------------------------------------------------------------------- */
    /* Data surface                                                           */
    /* --------------------------------------------------------------------- */

    public function test_pii_masking_depends_on_the_pii_scope(): void
    {
        ['plain' => $maskedKey] = $this->issueKey(['students.read']);
        ['plain' => $fullKey] = $this->issueKey(['students.read', 'students.pii']);

        $masked = $this->withHeader('X-API-Key', $maskedKey)
            ->getJson('/api/v1/integrator/v1/students?search=202501001');

        $masked->assertStatus(200)
            ->assertJsonPath('data.0.nim', '202501001')
            ->assertJsonPath('data.0.nik', '3201********0001')
            ->assertJsonPath('data.0.email', 'm***@siakad.ac.id')
            ->assertJsonPath('data.0.phone', '0821******78')
            ->assertJsonPath('data.0.address', null)
            ->assertJsonPath('data.0.pii_included', false);

        $full = $this->withHeader('X-API-Key', $fullKey)
            ->getJson('/api/v1/integrator/v1/students?search=202501001');

        $full->assertStatus(200)
            ->assertJsonPath('data.0.nik', '3201012345670001')
            ->assertJsonPath('data.0.address', 'Jl. Merdeka No. 45, Bandung')
            ->assertJsonPath('data.0.pii_included', true);

        // Detail endpoint exposes the academic snapshot and registrations.
        $detail = $this->withHeader('X-API-Key', $fullKey)
            ->getJson('/api/v1/integrator/v1/students/202501001');

        $detail->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'nim',
                    'study_program' => ['code', 'name'],
                    'academic' => ['total_credits_passed', 'cumulative_gpa'],
                    'registrations',
                    'educations',
                ],
            ]);

        $this->withHeader('X-API-Key', $fullKey)
            ->getJson('/api/v1/integrator/v1/students/9999999999')
            ->assertStatus(404);
    }

    public function test_reference_and_academic_endpoints_expose_feeder_codes(): void
    {
        ['plain' => $referenceKey] = $this->issueKey(['reference.read', 'academic.read']);

        // Grade scale data is optional in the demo seed but must be shaped correctly.
        $scale = GradeScale::create(['name' => 'Skala Nilai Uji', 'description' => 'Uji', 'status' => 'active']);
        $scale->items()->create([
            'grade_letter' => 'A',
            'grade_point' => 4.0,
            'min_score' => 85,
            'max_score' => 100,
            'is_whitewash' => false,
        ]);

        $this->withHeader('X-API-Key', $referenceKey)
            ->getJson('/api/v1/integrator/v1/profile')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'institution' => ['name'],
                    'faculties',
                    'study_programs' => [['code', 'name', 'degree']],
                    'semesters',
                    'grade_scales' => [['name', 'items']],
                ],
            ]);

        $semesters = $this->withHeader('X-API-Key', $referenceKey)
            ->getJson('/api/v1/integrator/v1/semesters');

        $semesters->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'name', 'type', 'feeder_code']]]);

        $feederCode = $semesters->json('data.0.feeder_code');
        $this->assertMatchesRegularExpression('/^\d{4}[123]$/', (string) $feederCode);

        $this->withHeader('X-API-Key', $referenceKey)
            ->getJson('/api/v1/integrator/v1/snapshot')
            ->assertStatus(200)
            ->assertJsonPath('data.counts.students', fn ($count) => $count >= 1);
    }

    public function test_incremental_filter_and_pagination(): void
    {
        ['plain' => $plain] = $this->issueKey(['students.read']);

        $all = $this->withHeader('X-API-Key', $plain)
            ->getJson('/api/v1/integrator/v1/students');

        $all->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, $all->json('meta.total'));

        $future = $this->withHeader('X-API-Key', $plain)
            ->getJson('/api/v1/integrator/v1/students?updated_since='.urlencode(now()->addDay()->toIso8601String()));

        $future->assertStatus(200)
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('data', []);

        $past = $this->withHeader('X-API-Key', $plain)
            ->getJson('/api/v1/integrator/v1/students?updated_since='.urlencode(now()->subDay()->toIso8601String()).'&per_page=1');

        $past->assertStatus(200)
            ->assertJsonPath('meta.per_page', 1);

        // A page size above the cap is rejected instead of silently accepted.
        $this->withHeader('X-API-Key', $plain)
            ->getJson('/api/v1/integrator/v1/students?per_page=1000')
            ->assertStatus(422);

        // Nonsense dates fail loudly.
        $this->withHeader('X-API-Key', $plain)
            ->getJson('/api/v1/integrator/v1/students?updated_since=kemarin')
            ->assertStatus(422);
    }

    public function test_enrollment_akm_and_grade_endpoints_return_feeder_shaped_rows(): void
    {
        ['plain' => $plain] = $this->issueKey([
            'enrollments.read',
            'grades.read',
            'classes.read',
        ]);

        $enrollment = StudentEnrollment::query()->whereHas('items')->firstOrFail();
        $semesterId = $enrollment->semester_id;
        $classId = AcademicClass::query()->whereHas('enrollmentItems')->firstOrFail()->id;

        $this->withHeader('X-API-Key', $plain)
            ->getJson("/api/v1/integrator/v1/enrollments?semester_id={$semesterId}")
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [[
                    'student' => ['nim', 'name'],
                    'semester' => ['feeder_code'],
                    'items' => [['class_code', 'course_code', 'credits', 'status']],
                    'active_credits',
                ]],
            ]);

        $this->withHeader('X-API-Key', $plain)
            ->getJson("/api/v1/integrator/v1/akm?semester_id={$semesterId}")
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [[
                    'student' => ['nim'],
                    'semester' => ['feeder_code'],
                    'sks_semester',
                    'ips',
                    'ipk',
                ]],
            ]);

        // AKM without a semester is a caller error, not an empty list.
        $this->withHeader('X-API-Key', $plain)
            ->getJson('/api/v1/integrator/v1/akm')
            ->assertStatus(422);

        $this->withHeader('X-API-Key', $plain)
            ->getJson("/api/v1/integrator/v1/classes?semester_id={$semesterId}")
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [[
                    'code',
                    'course' => ['code', 'credits'],
                    'semester' => ['feeder_code'],
                    'lecturers',
                    'schedules',
                ]],
            ]);

        $this->withHeader('X-API-Key', $plain)
            ->getJson("/api/v1/integrator/v1/grades?class_id={$classId}")
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [[
                    'class' => ['code', 'course_code'],
                    'grades' => [['nim', 'nilai_angka', 'nilai_huruf', 'grade_point']],
                ]],
            ]);

        // Activities require an explicit type — and the scope must be granted first,
        // otherwise the request is refused by the middleware, not by the validator.
        $this->withHeader('X-API-Key', $plain)
            ->getJson('/api/v1/integrator/v1/activities?type=thesis')
            ->assertStatus(403);

        ['plain' => $activityKey] = $this->issueKey(['activities.read']);

        $this->withHeader('X-API-Key', $activityKey)
            ->getJson('/api/v1/integrator/v1/activities')
            ->assertStatus(422);

        $this->withHeader('X-API-Key', $activityKey)
            ->getJson('/api/v1/integrator/v1/activities?type=thesis')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'total']]);
    }

    /* --------------------------------------------------------------------- */
    /* Lifecycle & audit trail                                                */
    /* --------------------------------------------------------------------- */

    public function test_key_rotation_revocation_and_deletion(): void
    {
        ['client' => $client, 'key' => $key, 'plain' => $plain] = $this->issueKey(['reference.read']);

        $rotation = $this->withHeader('Authorization', "Bearer {$this->adminAkademikToken}")
            ->postJson("/api/v1/integrator/keys/{$key->id}/rotate", ['reason' => 'Rutin bulanan']);

        $rotation->assertStatus(201);
        $newPlain = $rotation->json('data.plain_key');
        $newKeyId = $rotation->json('data.key.id');

        $this->assertNotSame($plain, $newPlain);
        $this->assertNotNull($key->refresh()->revoked_at);

        $this->withHeader('X-API-Key', $plain)
            ->getJson('/api/v1/integrator/v1/ping')
            ->assertStatus(401);

        $this->withHeader('X-API-Key', $newPlain)
            ->getJson('/api/v1/integrator/v1/ping')
            ->assertStatus(200);

        // Live keys cannot be deleted; revoked ones can.
        $this->withHeader('Authorization', "Bearer {$this->adminAkademikToken}")
            ->deleteJson("/api/v1/integrator/keys/{$newKeyId}")
            ->assertStatus(422);

        $this->withHeader('Authorization', "Bearer {$this->adminAkademikToken}")
            ->postJson("/api/v1/integrator/keys/{$newKeyId}/revoke", ['reason' => 'Selesai'])
            ->assertStatus(200);

        // Revoking twice is idempotent.
        $this->withHeader('Authorization', "Bearer {$this->adminAkademikToken}")
            ->postJson("/api/v1/integrator/keys/{$newKeyId}/revoke")
            ->assertStatus(200)
            ->assertJsonPath('message', 'API key was already revoked.');

        $this->withHeader('X-API-Key', $newPlain)
            ->getJson('/api/v1/integrator/v1/ping')
            ->assertStatus(401);

        $this->withHeader('Authorization', "Bearer {$this->adminAkademikToken}")
            ->deleteJson("/api/v1/integrator/keys/{$newKeyId}")
            ->assertStatus(200);

        // The client still has the rotated (revoked) key on record, so it stays.
        $this->assertDatabaseHas('api_clients', ['id' => $client->id]);
    }

    public function test_client_with_active_keys_cannot_be_deleted(): void
    {
        ['client' => $client] = $this->issueKey(['reference.read']);

        $this->withHeader('Authorization', "Bearer {$this->adminAkademikToken}")
            ->deleteJson("/api/v1/integrator/clients/{$client->id}")
            ->assertStatus(422);

        app(ApiKeyService::class)->revoke($client->keys()->firstOrFail(), 'Cleanup');

        $this->withHeader('Authorization', "Bearer {$this->adminAkademikToken}")
            ->deleteJson("/api/v1/integrator/clients/{$client->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('api_clients', ['id' => $client->id]);
    }

    public function test_requests_are_logged_including_rejections(): void
    {
        ['client' => $client, 'plain' => $plain] = $this->issueKey(['reference.read']);

        // Rejections are logged even though no key could be resolved.
        // (Sent first: withHeader() applies to every following request in the test.)
        $this->flushHeaders();
        $this->getJson('/api/v1/integrator/v1/profile')->assertStatus(401);

        $this->withHeader('X-API-Key', $plain)
            ->getJson('/api/v1/integrator/v1/ping')
            ->assertStatus(200);

        $this->withHeader('X-API-Key', $plain)
            ->getJson('/api/v1/integrator/v1/students')
            ->assertStatus(403);

        $this->assertDatabaseHas('api_request_logs', [
            'api_client_id' => $client->id,
            'path' => '/api/v1/integrator/v1/ping',
            'status_code' => 200,
            'method' => 'GET',
        ]);

        $this->assertDatabaseHas('api_request_logs', [
            'path' => '/api/v1/integrator/v1/profile',
            'status_code' => 401,
            'error_message' => 'API key required.',
        ]);

        $this->assertDatabaseHas('api_request_logs', [
            'api_key_id' => $client->keys()->first()->id,
            'path' => '/api/v1/integrator/v1/students',
            'status_code' => 403,
            'error_message' => 'API key is missing a required scope.',
        ]);

        // Both requests that authenticated with the key are counted — the successful
        // ping and the scope-denied one. The unauthenticated attempt cannot be
        // attributed to a key and therefore only appears in the log.
        $this->assertSame(2, (int) $client->keys()->first()->request_count);

        // The log is queryable by staff and reports statistics.
        $this->withHeader('Authorization', "Bearer {$this->adminAkademikToken}")
            ->getJson('/api/v1/integrator/logs?api_client_id='.$client->id)
            ->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'path', 'status_code', 'duration_ms']]]);

        $this->withHeader('Authorization', "Bearer {$this->adminAkademikToken}")
            ->getJson('/api/v1/integrator/logs/stats')
            ->assertStatus(200)
            // Three integration requests were logged: the rejected 401, the successful
            // ping and the scope-denied call. Staff calls on `integrator/*` are not part
            // of this log; they are covered by the module-wide audit log instead.
            ->assertJsonPath('data.totals.requests', fn ($requests) => $requests === 3)
            ->assertJsonStructure(['data' => ['totals', 'top_paths', 'per_client', 'recent_errors']]);
    }

    public function test_student_cannot_read_access_log_or_clients(): void
    {
        $mahasiswa = User::where('email', 'mahasiswa@siakad.ac.id')->firstOrFail();
        $mahasiswaToken = $mahasiswa->createToken('test')->plainTextToken;

        // Separate test on purpose: switching identity inside one test reuses the
        // guard that Sanctum already resolved for the previous request.
        auth()->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$mahasiswaToken}")
            ->getJson('/api/v1/integrator/logs')
            ->assertStatus(403);

        $this->withHeader('Authorization', "Bearer {$mahasiswaToken}")
            ->getJson('/api/v1/integrator/logs/stats')
            ->assertStatus(403);

        $this->withHeader('Authorization', "Bearer {$mahasiswaToken}")
            ->getJson('/api/v1/integrator/clients')
            ->assertStatus(403);

        $this->withHeader('Authorization', "Bearer {$mahasiswaToken}")
            ->getJson('/api/v1/integrator/scopes')
            ->assertStatus(403);
    }

    public function test_rotated_key_keeps_the_client_scope_set(): void
    {
        ['key' => $key] = $this->issueKey(['students.read', 'enrollments.read']);

        $rotation = $this->withHeader('Authorization', "Bearer {$this->adminAkademikToken}")
            ->postJson("/api/v1/integrator/keys/{$key->id}/rotate");

        $rotation->assertStatus(201);

        $this->assertEqualsCanonicalizing(
            ['students.read', 'enrollments.read'],
            $rotation->json('data.key.scopes')
        );
    }

    public function test_pendek_semester_maps_to_feeder_period_three(): void
    {
        $academicYear = \Modules\Academic\Models\AcademicYear::query()->firstOrFail();

        $semester = Semester::create([
            'academic_year_id' => $academicYear->id,
            'name' => 'Semester Antara '.$academicYear->name,
            'type' => \Modules\Academic\Enums\SemesterType::PENDEK,
            'start_date' => now()->startOfYear(),
            'end_date' => now()->startOfYear()->addMonths(2),
        ]);

        ['plain' => $plain] = $this->issueKey(['academic.read']);

        $response = $this->withHeader('X-API-Key', $plain)
            ->getJson('/api/v1/integrator/v1/semesters');

        $response->assertStatus(200);

        $row = collect($response->json('data'))->firstWhere('id', $semester->id);

        $this->assertNotNull($row, 'Semester antara tidak muncul di daftar semester.');
        $this->assertStringEndsWith('3', (string) $row['feeder_code']);
    }

    /**
     * Create a client plus a usable key without going through HTTP.
     *
     * @param  array<int, string>  $scopes
     * @param  array<string, mixed>  $clientAttributes
     * @return array{client: ApiClient, key: ApiKey, plain: string}
     */
    protected function issueKey(array $scopes, array $clientAttributes = []): array
    {
        $client = ApiClient::create([
            'name' => $clientAttributes['name'] ?? 'Integrator Uji '.Str::random(4),
            'slug' => $clientAttributes['slug'] ?? 'integrator-uji-'.Str::lower(Str::random(6)),
            'is_active' => $clientAttributes['is_active'] ?? true,
            'allowed_ips' => $clientAttributes['allowed_ips'] ?? null,
            'rate_limit_per_minute' => $clientAttributes['rate_limit_per_minute'] ?? 120,
            'created_by' => $this->superAdmin->id,
        ]);

        $result = app(ApiKeyService::class)->generate($client, [
            'name' => $clientAttributes['key_name'] ?? 'Kunci Uji',
            'scopes' => $scopes,
            'expires_at' => $clientAttributes['expires_at'] ?? null,
        ], $this->superAdmin);

        return [
            'client' => $client,
            'key' => $result['key'],
            'plain' => $result['plain'],
        ];
    }
}
