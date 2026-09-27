<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\Models\User;
use Modules\Integrator\Models\ApiClient;
use Modules\Integrator\Models\ApiKey;
use Modules\Integrator\Models\ApiRequestLog;
use Modules\Integrator\Services\ApiKeyService;
use Modules\Integrator\Services\IntegratorExportService;
use Tests\TestCase;

/**
 * Unduhan data pelaporan PDDikti / Neo Feeder ("Export as…").
 *
 * Yang dijaga di sini: isi unduhan sama dengan yang dilihat operator, format CSV
 * aman dibuka di Excel (BOM + anti CSV-injection), JSON memuat meta, rahasia kunci
 * tidak pernah ikut, dan hak akses tetap dihormati.
 */
class IntegratorExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected string $token;

    protected ApiClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@siakad.ac.id')->firstOrFail();
        $this->token = $this->admin->createToken('test')->plainTextToken;

        $this->client = ApiClient::create([
            'name' => 'Integrator Neo Feeder',
            'slug' => 'integrator-neo-feeder',
            'description' => 'Jembatan SIAKAD ke Neo Feeder',
            'contact_email' => 'pddikti@siakad.ac.id',
            'is_active' => true,
            'allowed_ips' => ['10.0.0.0/8'],
            'rate_limit_per_minute' => 120,
            'created_by' => $this->admin->id,
        ]);
    }

    private function log(ApiClient $client, array $overrides = []): ApiRequestLog
    {
        return ApiRequestLog::create(array_merge([
            'api_client_id' => $client->id,
            'method' => 'GET',
            'path' => '/api/v1/integrator/v1/students',
            'query' => ['semester' => '20251'],
            'status_code' => 200,
            'duration_ms' => 42,
            'ip_address' => '10.1.2.3',
            'user_agent' => 'NeoFeederBridge/1.0',
            'error_message' => null,
        ], $overrides));
    }

    public function test_log_export_as_csv_matches_the_screen_filters(): void
    {
        $other = ApiClient::create([
            'name' => 'SIAKAD 4.0',
            'slug' => 'siakad-4',
            'is_active' => true,
            'allowed_ips' => [],
            'rate_limit_per_minute' => 60,
        ]);

        $this->log($this->client);
        $this->log($other, ['path' => '/api/v1/integrator/v1/courses', 'status_code' => 403, 'error_message' => 'Missing scope']);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->get('/api/v1/integrator/logs/export?api_client_id='.$this->client->id.'&format=csv');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $body = $response->streamedContent();

        // BOM UTF-8 supaya Excel tidak mengacak karakter.
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);

        // Header kolom + baris milik klien yang difilter, tanpa baris klien lain.
        $this->assertStringContainsString('waktu,klien,slug_klien,kunci', $body);
        $this->assertStringContainsString('Integrator Neo Feeder', $body);
        $this->assertStringContainsString('/api/v1/integrator/v1/students', $body);
        $this->assertStringNotContainsString('/api/v1/integrator/v1/courses', $body);
        $this->assertStringNotContainsString('SIAKAD 4.0', $body);
    }

    public function test_log_export_keeps_formula_like_values_as_text(): void
    {
        // Pesan galat bisa berasal dari pihak luar; jangan sampai Excel mengeksekusinya.
        $this->log($this->client, [
            'status_code' => 500,
            'error_message' => '=HYPERLINK("http://penyerang.example","klik")',
        ]);

        $body = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->get('/api/v1/integrator/logs/export?format=csv')
            ->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $body);
        $this->assertStringNotContainsString(',=HYPERLINK', $body);
    }

    public function test_log_export_as_json_carries_meta_headers_and_rows(): void
    {
        $this->log($this->client);

        $body = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/integrator/logs/export?format=json')
            ->assertOk()
            ->streamedContent();

        $decoded = json_decode($body, true);

        $this->assertIsArray($decoded);
        $this->assertSame('log-akses-integrasi', $decoded['meta']['dataset']);
        $this->assertSame($this->admin->email, $decoded['meta']['generated_by']['email']);
        $this->assertSame(1, $decoded['rows']);
        $this->assertContains('endpoint', $decoded['headers']);
        $this->assertSame('/api/v1/integrator/v1/students', $decoded['data'][0]['endpoint']);
        $this->assertSame('ya', $decoded['data'][0]['berhasil']);
    }

    public function test_summary_export_groups_requests_per_day_and_client(): void
    {
        $this->log($this->client, ['status_code' => 200, 'duration_ms' => 100]);
        $this->log($this->client, ['status_code' => 404, 'duration_ms' => 20]);
        $this->log($this->client, ['status_code' => 500, 'duration_ms' => 60]);

        $body = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->get('/api/v1/integrator/logs/summary/export?format=csv')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('tanggal,klien,slug_klien,jumlah_permintaan,sukses_2xx,galat_klien_4xx,galat_server_5xx', $body);

        // 3 permintaan: 1 sukses, 1 galat klien, 1 galat server, rata-rata 60 ms.
        // Nama klien mengandung spasi sehingga dikutip (RFC 4180).
        $this->assertMatchesRegularExpression(
            '/'.now()->format('Y-m-d').',"?Integrator Neo Feeder"?,integrator-neo-feeder,3,1,1,1,60/u',
            $body
        );
    }

    public function test_clients_export_is_not_captured_by_the_client_detail_route(): void
    {
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->get('/api/v1/integrator/clients/export?format=csv')
            ->assertOk()
            ->assertHeader('X-Export-Dataset', 'klien-integrasi');
    }

    public function test_clients_export_lists_key_counts_without_any_secret(): void
    {
        $issued = app(ApiKeyService::class)->generate($this->client, [
            'name' => 'Produksi PDDikti',
            'scopes' => ['students.read', 'classes.read'],
        ], $this->admin);

        $this->log($this->client);

        $body = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->get('/api/v1/integrator/clients/export?format=csv')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('jumlah_kunci,kunci_aktif', $body);
        $this->assertStringContainsString('"Integrator Neo Feeder",integrator-neo-feeder', $body);
        $this->assertStringContainsString(',1,1,', $body);

        // Rahasia tidak boleh ikut: token penuh maupun hash-nya.
        $this->assertStringNotContainsString($issued['plain'], $body);
        $this->assertStringNotContainsString((string) $issued['key']->key_hash, $body);
    }

    public function test_keys_export_shows_prefix_and_scopes_but_never_the_token(): void
    {
        $issued = app(ApiKeyService::class)->generate($this->client, [
            'name' => 'Produksi PDDikti',
            'scopes' => ['students.read', 'students.pii'],
        ], $this->admin);

        $body = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->get('/api/v1/integrator/keys/export?format=csv')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('prefix,scope,status', $body);
        $this->assertStringContainsString($issued['key']->key_prefix, $body);
        $this->assertStringContainsString('students.pii', $body);
        $this->assertStringContainsString('active', $body);
        $this->assertStringNotContainsString($issued['plain'], $body);
        $this->assertStringNotContainsString((string) $issued['key']->key_hash, $body);
    }

    public function test_keys_export_can_filter_by_status_and_client(): void
    {
        $service = app(ApiKeyService::class);

        $service->generate($this->client, ['name' => 'Aktif', 'scopes' => ['students.read']], $this->admin);

        $revoked = $service->generate($this->client, ['name' => 'Dicabut', 'scopes' => ['students.read']], $this->admin);
        $service->revoke($revoked['key'], 'diganti kunci baru', $this->admin);

        $active = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->get('/api/v1/integrator/keys/export?status=active&format=csv')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Aktif', $active);
        $this->assertStringNotContainsString('Dicabut', $active);

        $onlyRevoked = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->get('/api/v1/integrator/keys/export?status=revoked&format=csv')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Dicabut', $onlyRevoked);
        $this->assertStringContainsString('diganti kunci baru', $onlyRevoked);
    }

    public function test_export_requires_the_matching_permission(): void
    {
        $lecturer = User::where('email', 'dosen@siakad.ac.id')->firstOrFail();
        $lecturerToken = $lecturer->createToken('test')->plainTextToken;

        $this->assertFalse($lecturer->hasPermissionTo('integrator.logs.view'));

        $this->withHeader('Authorization', "Bearer {$lecturerToken}")
            ->get('/api/v1/integrator/logs/export')
            ->assertStatus(403);

        $this->withHeader('Authorization', "Bearer {$lecturerToken}")
            ->get('/api/v1/integrator/clients/export')
            ->assertStatus(403);
    }

    public function test_export_row_cap_is_announced_in_the_response_headers(): void
    {
        $this->log($this->client);

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->get('/api/v1/integrator/logs/export?format=csv')
            ->assertOk()
            ->assertHeader('X-Export-Row-Limit', (string) IntegratorExportService::MAX_ROWS);
    }
}
