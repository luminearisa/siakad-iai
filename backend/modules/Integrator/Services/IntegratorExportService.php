<?php

namespace Modules\Integrator\Services;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Modules\Identity\Models\User;
use Modules\Integrator\Models\ApiClient;
use Modules\Integrator\Models\ApiKey;
use Modules\Integrator\Models\ApiRequestLog;

/**
 * Menyiapkan data yang berkaitan dengan pelaporan PDDikti / Neo Feeder agar dapat
 * diunduh operator ("Export as…") sebagai CSV (dibuka di Excel) atau JSON (arsip
 * mesin / diserahkan ke tim integrator).
 *
 * Setiap dataset memakai bentuk yang sama:
 *
 *     ['headers' => string[], 'rows' => iterable<int, array<int, mixed>>]
 *
 * sehingga controller cukup menyalurkannya ke berkas unduhan.
 *
 * Catatan aman:
 *  - rahasia TIDAK pernah ikut: kunci API hanya diwakili `key_prefix`, sedangkan
 *    hash token tidak pernah disimpan dalam bentuk terbaca;
 *  - jumlah baris dibatasi {@see self::MAX_ROWS} supaya satu unduhan tidak
 *    menghabiskan memori server; log lama bisa dipersempit lewat filter tanggal.
 */
final class IntegratorExportService
{
    /**
     * Batas baris per berkas unduhan.
     */
    public const MAX_ROWS = 50000;

    /**
     * Log permintaan API: bukti "siapa menarik data apa, kapan, dan berhasil/tidak".
     *
     * Filter yang dipahami sama dengan endpoint `/integrator/logs`: `api_client_id`,
     * `api_key_id`, `method`, `status_code`, `successful`, `from`, `to`, `search`.
     *
     * @param  array<string, mixed>  $filters
     * @return array{headers: array<int, string>, rows: iterable<int, array<int, mixed>>}
     */
    public function requestLogs(array $filters = [], int $limit = self::MAX_ROWS): array
    {
        $query = ApiRequestLog::query()
            ->with(['client:id,name,slug', 'key:id,name,key_prefix'])
            ->orderBy('created_at');

        $this->applyLogFilters($query, $filters);

        $headers = [
            'waktu',
            'klien',
            'slug_klien',
            'kunci',
            'metode',
            'endpoint',
            'parameter',
            'status_http',
            'berhasil',
            'durasi_ms',
            'ip',
            'user_agent',
            'pesan_galat',
        ];

        $cap = $this->clampLimit($limit);

        $rows = (function () use ($query, $cap) {
            $sent = 0;

            foreach ($query->lazy(500) as $log) {
                if ($sent >= $cap) {
                    break;
                }

                $sent++;

                yield [
                    $this->moment($log->created_at),
                    $log->client?->name,
                    $log->client?->slug,
                    $log->key?->key_prefix,
                    strtoupper((string) $log->method),
                    $log->path,
                    $log->query,
                    (int) $log->status_code,
                    $log->isSuccessful() ? 'ya' : 'tidak',
                    (int) $log->duration_ms,
                    $log->ip_address,
                    $log->user_agent,
                    $log->error_message,
                ];
            }
        })();

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * Rekap harian per klien: dipakai untuk lampiran laporan pelaporan PDDikti
     * (jumlah permintaan, tingkat keberhasilan, galat 4xx/5xx, rata-rata durasi).
     *
     * Dihitung di PHP agar hasilnya sama di SQLite/MySQL/PostgreSQL.
     *
     * @param  array<string, mixed>  $filters
     * @return array{headers: array<int, string>, rows: iterable<int, array<int, mixed>>}
     */
    public function requestSummary(array $filters = [], int $limit = self::MAX_ROWS): array
    {
        $query = ApiRequestLog::query()
            ->with('client:id,name,slug')
            ->orderBy('created_at');

        $this->applyLogFilters($query, $filters);

        $headers = [
            'tanggal',
            'klien',
            'slug_klien',
            'jumlah_permintaan',
            'sukses_2xx',
            'galat_klien_4xx',
            'galat_server_5xx',
            'rata_durasi_ms',
            'permintaan_terakhir',
        ];

        $cap = $this->clampLimit($limit);

        $rows = (function () use ($query, $cap) {
            /** @var array<string, array<string, mixed>> $buckets */
            $buckets = [];
            $seen = 0;

            foreach ($query->lazy(1000) as $log) {
                if ($seen >= $cap) {
                    break;
                }

                $seen++;

                $date = $log->created_at?->format('Y-m-d') ?? '—';
                $client = $log->client?->name ?? '(tanpa klien)';
                $bucketKey = $date.'|'.$client;

                if (! isset($buckets[$bucketKey])) {
                    $buckets[$bucketKey] = [
                        'tanggal' => $date,
                        'klien' => $client,
                        'slug' => $log->client?->slug,
                        'total' => 0,
                        'sukses' => 0,
                        'galat_klien' => 0,
                        'galat_server' => 0,
                        'durasi' => 0,
                        'terakhir' => null,
                    ];
                }

                $status = (int) $log->status_code;

                $buckets[$bucketKey]['total']++;
                $buckets[$bucketKey]['durasi'] += (int) $log->duration_ms;
                $buckets[$bucketKey]['sukses'] += ($status >= 200 && $status <= 299) ? 1 : 0;
                $buckets[$bucketKey]['galat_klien'] += ($status >= 400 && $status <= 499) ? 1 : 0;
                $buckets[$bucketKey]['galat_server'] += $status >= 500 ? 1 : 0;
                $buckets[$bucketKey]['terakhir'] = $this->moment($log->created_at);
            }

            ksort($buckets);

            foreach ($buckets as $bucket) {
                yield [
                    $bucket['tanggal'],
                    $bucket['klien'],
                    $bucket['slug'],
                    $bucket['total'],
                    $bucket['sukses'],
                    $bucket['galat_klien'],
                    $bucket['galat_server'],
                    $bucket['total'] > 0 ? round($bucket['durasi'] / $bucket['total'], 2) : 0,
                    $bucket['terakhir'],
                ];
            }
        })();

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * Daftar klien integrasi beserta ringkasan kuncinya.
     *
     * @return array{headers: array<int, string>, rows: iterable<int, array<int, mixed>>}
     */
    public function clients(): array
    {
        $headers = [
            'id',
            'nama',
            'slug',
            'deskripsi',
            'email_kontak',
            'aktif',
            'ip_diizinkan',
            'batas_per_menit',
            'jumlah_kunci',
            'kunci_aktif',
            'terakhir_dipakai',
            'dibuat',
        ];

        $rows = (function () {
            $clients = ApiClient::query()
                ->withCount(['keys', 'activeKeys'])
                ->orderBy('name')
                ->cursor();

            foreach ($clients as $client) {
                yield [
                    (int) $client->id,
                    $client->name,
                    $client->slug,
                    $client->description,
                    $client->contact_email,
                    $client->is_active ? 'ya' : 'tidak',
                    implode(', ', $client->allowed_ips ?? []),
                    (int) $client->rate_limit_per_minute,
                    (int) $client->keys_count,
                    (int) $client->active_keys_count,
                    $this->moment($client->last_used_at),
                    $this->moment($client->created_at),
                ];
            }
        })();

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * Daftar kunci API (prefix + scope + masa berlaku), tanpa rahasia apa pun.
     *
     * @param  array<string, mixed>  $filters  mendukung `api_client_id` dan `status`
     *                                          (`active`, `revoked`, `expired`).
     * @return array{headers: array<int, string>, rows: iterable<int, array<int, mixed>>}
     */
    public function keys(array $filters = []): array
    {
        $query = ApiKey::query()
            ->with('client:id,name,slug')
            ->orderBy('api_client_id')
            ->orderBy('id');

        if (! empty($filters['api_client_id'])) {
            $query->where('api_client_id', $filters['api_client_id']);
        }

        $this->applyKeyStatusFilter($query, $filters['status'] ?? null);

        $headers = [
            'id',
            'klien',
            'slug_klien',
            'nama_kunci',
            'prefix',
            'scope',
            'status',
            'kedaluwarsa',
            'dicabut',
            'alasan_pencabutan',
            'terakhir_dipakai',
            'ip_terakhir',
            'jumlah_permintaan',
            'dibuat',
        ];

        $rows = (function () use ($query) {
            foreach ($query->cursor() as $key) {
                yield [
                    (int) $key->id,
                    $key->client?->name,
                    $key->client?->slug,
                    $key->name,
                    $key->key_prefix,
                    implode(', ', $key->scopeList()),
                    $key->status()->value,
                    $this->moment($key->expires_at),
                    $this->moment($key->revoked_at),
                    $key->revoked_reason,
                    $this->moment($key->last_used_at),
                    $key->last_used_ip,
                    (int) $key->request_count,
                    $this->moment($key->created_at),
                ];
            }
        })();

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * Metadata yang ikut ditulis pada berkas JSON (dan dicatat pada log aplikasi).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function meta(string $dataset, array $filters): array
    {
        return [
            'dataset' => $dataset,
            'source' => 'SIAKAD → Integrator PDDikti / Neo Feeder',
            'generated_at' => now()->toIso8601String(),
            'timezone' => config('app.timezone'),
            'row_limit' => self::MAX_ROWS,
            'filters' => array_filter($filters, static fn ($value) => $value !== null && $value !== ''),
        ];
    }

    /**
     * Samakan semantik filter dengan endpoint `/integrator/logs` agar isi unduhan
     * persis sama dengan yang dilihat operator di layar.
     *
     * @param  array<string, mixed>  $filters
     */
    private function applyLogFilters(Builder $query, array $filters): void
    {
        if (array_key_exists('successful', $filters) && $filters['successful'] !== null && $filters['successful'] !== '') {
            filter_var($filters['successful'], FILTER_VALIDATE_BOOLEAN)
                ? $query->whereBetween('status_code', [200, 299])
                : $query->whereNotBetween('status_code', [200, 299]);
        }

        foreach (['api_client_id', 'api_key_id', 'status_code'] as $column) {
            if (! empty($filters[$column])) {
                $query->where($column, $filters[$column]);
            }
        }

        if (! empty($filters['method'])) {
            $query->where('method', strtoupper((string) $filters['method']));
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        if (! empty($filters['search']) && is_string($filters['search'])) {
            $term = '%'.$filters['search'].'%';

            $query->where(function (Builder $inner) use ($term) {
                $inner->where('path', 'like', $term)
                    ->orWhere('ip_address', 'like', $term)
                    ->orWhere('error_message', 'like', $term)
                    ->orWhere('user_agent', 'like', $term);
            });
        }
    }

    private function applyKeyStatusFilter(Builder $query, mixed $status): void
    {
        match ($status) {
            'active' => $query->whereNull('revoked_at')
                ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now())),
            'revoked' => $query->whereNotNull('revoked_at'),
            'expired' => $query->whereNull('revoked_at')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now()),
            default => null,
        };
    }

    private function clampLimit(int $limit): int
    {
        return max(1, min($limit, self::MAX_ROWS));
    }

    /**
     * Waktu lokal yang enak dibaca di Excel (kolom JSON tetap ISO 8601 di meta).
     */
    private function moment(mixed $value): ?string
    {
        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : null;
    }

    // =========================================================================
    // Unduhan data pelaporan feeder ("Export as…" di halaman data SIAKAD)
    // =========================================================================

    /**
     * Katalog dataset pelaporan: satu entri untuk setiap halaman data SIAKAD yang
     * isinya dikirim ke Neo Feeder / PDDikti (dan "profeeder").
     *
     * Sumber datanya sengaja memakai ulang {@see IntegratorDataService} — service yang
     * sama dengan endpoint `/api/v1/integrator/v1/*` — sehingga isi berkas unduhan
     * identik dengan yang dilihat/ditarik feeder, bukan hasil query kedua yang bisa
     * berbeda diam-diam. Data referensi (prodi, fakultas, universitas, tahun ajaran,
     * ruang) dibaca langsung dari modelnya karena tidak punya endpoint feeder.
     *
     * Setiap entri:
     *  - `label`      : nama dataset di katalog + nama bawaan berkas;
     *  - `permission` : permission halaman asal (harus sama dengan `meta.permission` router);
     *  - `kind`       : `service` (lewat IntegratorDataService) atau `model` (Eloquent);
     *  - `columns`    : kolom yang diunduh; kunci = jalur data bertitik, nilai = judul kolom.
     *                   Hanya kolom di daftar ini yang boleh keluar (allow-list).
     *  - `filters`    : parameter yang diteruskan dari halaman (selebihnya diabaikan);
     *  - `requires`   : parameter wajib, mis. AKM butuh `semester_id`.
     *
     * @var array<string, array<string, mixed>>
     */
    public const DATASETS = [
        'students' => [
            'label' => 'Data Mahasiswa',
            'basename' => 'data-mahasiswa',
            'permission' => 'students.view',
            'kind' => 'service',
            'method' => 'students',
            'filters' => ['study_program_id', 'status', 'admission_year', 'search', 'updated_since'],
            'columns' => [
                'id' => 'ID',
                'nim' => 'NIM',
                'nisn' => 'NISN',
                'nik' => 'NIK',
                'name' => 'Nama',
                'nickname' => 'Nama Panggilan',
                'gender' => 'Jenis Kelamin',
                'birth_place' => 'Tempat Lahir',
                'birth_date' => 'Tanggal Lahir',
                'religion' => 'Agama',
                'marital_status' => 'Status Perkawinan',
                'mother_name' => 'Nama Ibu',
                'email' => 'Email',
                'phone' => 'Telepon',
                'address' => 'Alamat',
                'province' => 'Provinsi',
                'city' => 'Kota/Kabupaten',
                'district' => 'Kecamatan',
                'postal_code' => 'Kode Pos',
                'study_program.code' => 'Kode Prodi',
                'study_program.name' => 'Program Studi',
                'study_program.degree' => 'Jenjang',
                'admission_year' => 'Tahun Masuk',
                'entry_date' => 'Tanggal Masuk',
                'status' => 'Status',
                'graduation_date' => 'Tanggal Lulus',
                'updated_at' => 'Diperbarui',
            ],
        ],
        'lecturers' => [
            'label' => 'Data Dosen',
            'basename' => 'data-dosen',
            'permission' => 'lecturers.view',
            'kind' => 'service',
            'method' => 'lecturers',
            'filters' => ['homebase_study_program_id', 'status', 'search', 'updated_since'],
            'columns' => [
                'id' => 'ID',
                'nidn' => 'NIDN',
                'nidk' => 'NIDK',
                'nip' => 'NIP',
                'lecturer_number' => 'No. Dosen',
                'name' => 'Nama',
                'gender' => 'Jenis Kelamin',
                'birth_place' => 'Tempat Lahir',
                'birth_date' => 'Tanggal Lahir',
                'academic_degree' => 'Gelar Akademik',
                'functional_position' => 'Jabatan Fungsional',
                'status' => 'Status',
                'join_date' => 'Tanggal Bergabung',
                'email' => 'Email',
                'phone' => 'Telepon',
                'homebase_study_program.code' => 'Kode Prodi Homebase',
                'homebase_study_program.name' => 'Prodi Homebase',
                'educations.level' => 'Jenjang Pendidikan',
                'educations.institution_name' => 'Institusi Pendidikan',
                'educations.major' => 'Jurusan Pendidikan',
                'educations.graduation_year' => 'Tahun Lulus Pendidikan',
            ],
        ],
        'courses' => [
            'label' => 'Mata Kuliah',
            'basename' => 'mata-kuliah',
            'permission' => 'courses.view',
            'kind' => 'service',
            'method' => 'courses',
            'filters' => ['study_program_id', 'status', 'type', 'search', 'updated_since'],
            'columns' => [
                'id' => 'ID',
                'code' => 'Kode MK',
                'name' => 'Nama Mata Kuliah',
                'short_name' => 'Nama Singkat',
                'credits' => 'SKS',
                'credits_breakdown.theory' => 'SKS Teori',
                'credits_breakdown.practical' => 'SKS Praktikum',
                'credits_breakdown.field_practical' => 'SKS Praktik Lapangan',
                'credits_breakdown.simulation' => 'SKS Simulasi',
                'credits_breakdown.seminar' => 'SKS Seminar',
                'type' => 'Jenis',
                'category' => 'Kategori',
                'status' => 'Status',
                'study_program' => 'Kode Prodi',
            ],
        ],
        'curricula' => [
            'label' => 'Kurikulum',
            'basename' => 'kurikulum',
            'permission' => 'curricula.view',
            'kind' => 'service',
            'method' => 'curricula',
            'filters' => ['study_program_id', 'status', 'search', 'updated_since'],
            'columns' => [
                'id' => 'ID',
                'code' => 'Kode Kurikulum',
                'name' => 'Nama Kurikulum',
                'version' => 'Versi',
                'start_year' => 'Tahun Mulai',
                'end_year' => 'Tahun Selesai',
                'effective_date' => 'Tanggal Berlaku',
                'expiry_date' => 'Tanggal Berakhir',
                'status' => 'Status',
                'study_program.code' => 'Kode Prodi',
                'study_program.name' => 'Program Studi',
                'curriculum_year' => 'Tahun Kurikulum',
                'semesters.semester_number' => 'Semester Ke',
                'semesters.subjects.course_code' => 'Kode MK',
                'semesters.subjects.course_name' => 'Nama Mata Kuliah',
                'semesters.subjects.credits' => 'SKS',
                'semesters.subjects.is_mandatory' => 'Wajib',
                'semesters.subjects.subject_type' => 'Jenis MK',
                'semesters.subjects.minimum_grade' => 'Nilai Minimum',
            ],
        ],
        'classes' => [
            'label' => 'Kelas Perkuliahan',
            'basename' => 'kelas-perkuliahan',
            'permission' => 'classes.view',
            'kind' => 'service',
            'method' => 'classes',
            'filters' => ['semester_id', 'study_program_id', 'course_id', 'status', 'search', 'updated_since'],
            'columns' => [
                'id' => 'ID',
                'code' => 'Kode Kelas',
                'name' => 'Nama Kelas',
                'section' => 'Rombel',
                'capacity' => 'Kapasitas',
                'enrolled_count' => 'Terisi',
                'status' => 'Status',
                'semester.feeder_code' => 'Kode Semester Feeder',
                'semester.name' => 'Semester',
                'course.code' => 'Kode MK',
                'course.name' => 'Mata Kuliah',
                'course.credits' => 'SKS',
                'study_program.code' => 'Kode Prodi',
                'study_program.name' => 'Program Studi',
                'lecturers.nidn' => 'NIDN Pengajar',
                'lecturers.name' => 'Nama Pengajar',
                'lecturers.role' => 'Peran Pengajar',
                'schedules.day_of_week' => 'Hari',
                'schedules.start_time' => 'Jam Mulai',
                'schedules.end_time' => 'Jam Selesai',
                'schedules.room' => 'Ruang',
            ],
        ],
        'enrollments' => [
            'label' => 'KRS (Enrollment)',
            'basename' => 'krs-mahasiswa',
            'permission' => 'enrollments.view',
            'kind' => 'service',
            'method' => 'enrollments',
            'filters' => ['semester_id', 'student_id', 'status', 'search', 'updated_since'],
            'columns' => [
                'id' => 'ID KRS',
                'student.nim' => 'NIM',
                'student.name' => 'Nama Mahasiswa',
                'student.study_program_code' => 'Kode Prodi',
                'semester.feeder_code' => 'Kode Semester Feeder',
                'semester.name' => 'Semester',
                'status' => 'Status KRS',
                'total_credits' => 'SKS Diambil',
                'active_credits' => 'SKS Aktif',
                'submitted_at' => 'Diajukan',
                'approved_at' => 'Disetujui',
                'items.class_code' => 'Kode Kelas',
                'items.course_code' => 'Kode MK',
                'items.course_name' => 'Mata Kuliah',
                'items.credits' => 'SKS',
                'items.status' => 'Status MK',
            ],
        ],
        'akm' => [
            'label' => 'AKM (Aktivitas Kuliah Mahasiswa)',
            'basename' => 'akm-mahasiswa',
            'permission' => 'enrollments.view',
            'kind' => 'service',
            'method' => 'akm',
            'requires' => ['semester_id'],
            'filters' => ['semester_id', 'status', 'search'],
            'columns' => [
                'student.nim' => 'NIM',
                'student.name' => 'Nama Mahasiswa',
                'student.study_program_code' => 'Kode Prodi',
                'semester.feeder_code' => 'Kode Semester Feeder',
                'semester.name' => 'Semester',
                'enrollment_status' => 'Status KRS',
                'sks_semester' => 'SKS Semester',
                'ips' => 'IPS',
                'sks_total' => 'SKS Kumulatif',
                'ipk' => 'IPK',
                'student_status' => 'Status Mahasiswa',
            ],
        ],
        'grades' => [
            'label' => 'Nilai (dari Kelas)',
            'basename' => 'nilai-mahasiswa',
            'permission' => 'grades.view',
            'kind' => 'service',
            'method' => 'grades',
            'requires' => ['semester_id'],
            'filters' => ['semester_id', 'study_program_id', 'course_id', 'search'],
            'columns' => [
                'class.code' => 'Kode Kelas',
                'class.course_code' => 'Kode MK',
                'class.course_name' => 'Mata Kuliah',
                'class.credits' => 'SKS',
                'semester.feeder_code' => 'Kode Semester Feeder',
                'semester.name' => 'Semester',
                'scheme.name' => 'Skema Penilaian',
                'summary.total_students' => 'Jumlah Mahasiswa',
                'summary.average_score' => 'Rata-rata Nilai',
                'grades.nim' => 'NIM',
                'grades.name' => 'Nama Mahasiswa',
                'grades.nilai_angka' => 'Nilai Angka',
                'grades.nilai_huruf' => 'Nilai Huruf',
                'grades.grade_point' => 'Indeks',
                'grades.is_complete' => 'Lengkap',
            ],
        ],
        'graduates' => [
            'label' => 'Lulusan (Yudisium)',
            'basename' => 'lulusan-yudisium',
            'permission' => 'students.view',
            'kind' => 'service',
            'method' => 'graduates',
            'filters' => ['yudisium_period_id', 'status', 'search'],
            'columns' => [
                'id' => 'ID',
                'nim' => 'NIM',
                'name' => 'Nama',
                'study_program_code' => 'Kode Prodi',
                'study_program_degree' => 'Jenjang',
                'status' => 'Status',
                'period' => 'Periode Yudisium',
                'feeder_semester_code' => 'Kode Semester Feeder',
                'yudisium_date' => 'Tanggal Yudisium',
                'graduation_date' => 'Tanggal Lulus',
                'sks_total' => 'SKS Total',
                'ipk' => 'IPK',
                'sk_number' => 'No. SK',
                'sk_date' => 'Tanggal SK',
            ],
        ],
        'activities' => [
            'label' => 'Aktivitas Mahasiswa (Tugas Akhir & MBKM)',
            'basename' => 'aktivitas-mahasiswa',
            'permission' => 'mbkm.participants.view',
            'kind' => 'service',
            'method' => 'activities',
            'filters' => ['type', 'program_id', 'status', 'search', 'updated_since'],
            'defaults' => ['type' => 'mbkm'],
            'columns' => [
                'id' => 'ID',
                'type' => 'Jenis Aktivitas',
                'participant_number' => 'No. Peserta',
                'nim' => 'NIM',
                'name' => 'Nama Mahasiswa',
                'study_program_code' => 'Kode Prodi',
                'title' => 'Judul',
                'topic' => 'Topik',
                'program.code' => 'Kode Program',
                'program.name' => 'Program MBKM',
                'program.organizer' => 'Penyelenggara',
                'partner' => 'Mitra',
                'status' => 'Status',
                'start_date' => 'Tanggal Mulai',
                'end_date' => 'Tanggal Selesai',
                'start_semester_code' => 'Semester Mulai',
                'completion_semester_code' => 'Semester Selesai',
                'submission_date' => 'Tanggal Pengajuan',
                'completion_date' => 'Tanggal Selesai TA',
                'final_score' => 'Nilai Akhir',
                'letter_grade' => 'Nilai Huruf',
                'grade_point' => 'Indeks',
                'recognized_credits' => 'SKS Diakui',
                'sk_number' => 'No. SK',
                'sk_date' => 'Tanggal SK',
                'supervisor.nidn' => 'NIDN Pembimbing',
                'supervisor.name' => 'Nama Pembimbing',
                'supervisors.nidn' => 'NIDN Pembimbing TA',
                'supervisors.name' => 'Nama Pembimbing TA',
                'supervisors.role' => 'Peran Pembimbing TA',
            ],
        ],
        'semesters' => [
            'label' => 'Periode Akademik (Semester)',
            'basename' => 'periode-akademik',
            'permission' => 'semesters.view',
            'kind' => 'service',
            'method' => 'semesterList',
            'filters' => [],
            'columns' => [
                'id' => 'ID',
                'name' => 'Nama Semester',
                'type' => 'Jenis',
                'feeder_code' => 'Kode Feeder',
                'academic_year' => 'Tahun Akademik',
                'start_date' => 'Tanggal Mulai',
                'end_date' => 'Tanggal Selesai',
                'lecture_start_date' => 'Awal Perkuliahan',
                'lecture_end_date' => 'Akhir Perkuliahan',
                'status' => 'Status',
            ],
        ],
        'study-programs' => [
            'label' => 'Program Studi',
            'basename' => 'program-studi',
            'permission' => 'study_programs.view',
            'kind' => 'model',
            'model' => \Modules\Academic\Models\StudyProgram::class,
            'with' => ['faculty:id,code,name'],
            'search' => ['code', 'name'],
            'filters' => ['faculty_id', 'status'],
            'columns' => [
                'id' => 'ID',
                'code' => 'Kode Prodi',
                'short_name' => 'Nama Singkat',
                'name' => 'Nama Program Studi',
                'degree' => 'Jenjang',
                'status' => 'Status',
                'faculty.code' => 'Kode Fakultas',
                'faculty.name' => 'Fakultas',
            ],
        ],
        'faculties' => [
            'label' => 'Fakultas',
            'basename' => 'fakultas',
            'permission' => 'faculties.view',
            'kind' => 'model',
            'model' => \Modules\Academic\Models\Faculty::class,
            'with' => ['institution:id,code,name'],
            'search' => ['code', 'name'],
            'filters' => ['institution_id', 'status'],
            'columns' => [
                'id' => 'ID',
                'code' => 'Kode Fakultas',
                'name' => 'Nama Fakultas',
                'name_en' => 'Nama (Inggris)',
                'status' => 'Status',
                'institution.code' => 'Kode Perguruan Tinggi',
                'institution.name' => 'Perguruan Tinggi',
            ],
        ],
        'institutions' => [
            'label' => 'Perguruan Tinggi',
            'basename' => 'perguruan-tinggi',
            'permission' => 'institutions.view',
            'kind' => 'model',
            'model' => \Modules\Academic\Models\Institution::class,
            'search' => ['code', 'name'],
            'filters' => ['status'],
            'columns' => [
                'id' => 'ID',
                'code' => 'Kode PT',
                'name' => 'Nama Perguruan Tinggi',
                'short_name' => 'Nama Singkat',
                'address' => 'Alamat',
                'phone' => 'Telepon',
                'website' => 'Situs',
                'status' => 'Status',
            ],
        ],
        'academic-years' => [
            'label' => 'Tahun Ajaran',
            'basename' => 'tahun-ajaran',
            'permission' => 'academic_years.view',
            'kind' => 'model',
            'model' => \Modules\Academic\Models\AcademicYear::class,
            'search' => ['name'],
            'filters' => ['status'],
            'columns' => [
                'id' => 'ID',
                'name' => 'Tahun Ajaran',
                'start_date' => 'Tanggal Mulai',
                'end_date' => 'Tanggal Selesai',
                'status' => 'Status',
            ],
        ],
        'rooms' => [
            'label' => 'Ruang (Sarana & Prasarana)',
            'basename' => 'ruang',
            'permission' => 'rooms.view',
            'kind' => 'model',
            'model' => \Modules\Schedule\Models\Room::class,
            'search' => ['code', 'name'],
            'filters' => ['building_id', 'room_type', 'status'],
            'columns' => [
                'id' => 'ID',
                'code' => 'Kode Ruang',
                'name' => 'Nama Ruang',
                'building' => 'Gedung',
                'floor' => 'Lantai',
                'capacity' => 'Kapasitas',
                'room_type' => 'Jenis Ruang',
                'location' => 'Lokasi',
                'status' => 'Status',
            ],
        ],
    ];

    /**
     * Daftar dataset yang boleh diunduh pengguna (dipakai tombol "Export as…").
     *
     * @return array<int, array<string, mixed>>
     */
    public function datasetCatalog(User $user): array
    {
        $catalog = [];

        foreach (self::DATASETS as $key => $definition) {
            if (! $user->can($definition['permission'])) {
                continue;
            }

            $catalog[] = [
                'key' => $key,
                'label' => $definition['label'],
                'permission' => $definition['permission'],
                'columns' => count($definition['columns']),
                'requires' => array_values($definition['requires'] ?? []),
                'filters' => array_values($definition['filters'] ?? []),
            ];
        }

        return $catalog;
    }

    /**
     * Definisi satu dataset; 404 bila tidak dikenal.
     *
     * @return array<string, mixed>
     */
    public function datasetDefinition(string $dataset): array
    {
        abort_unless(isset(self::DATASETS[$dataset]), 404, "Dataset '{$dataset}' tidak tersedia untuk diunduh.");

        return self::DATASETS[$dataset];
    }

    /**
     * Menyiapkan berkas unduhan untuk satu dataset.
     *
     * @param  array<string, mixed>  $definition
     * @return array{headers: array<int, string>, keys: array<int, string>, rows: array<int, array<int, mixed>>}
     */
    public function datasetRows(array $definition, Request $request, int $limit = self::MAX_ROWS): array
    {
        $limit = $this->clampLimit($limit);
        $columns = $definition['columns'];

        // JSON adalah wadah mesin, jadi kolomnya memakai nama field apa adanya
        // (`nim`, `study_program.code`); CSV memakai judul manusiawi kecuali `?header=api`.
        $rawHeader = strtolower((string) $request->query('header', 'label')) === 'api'
            || strtolower((string) $request->query('format', 'csv')) === 'json';

        $sourceRows = $definition['kind'] === 'service'
            ? $this->datasetServiceRows($definition, $request, $limit)
            : $this->datasetModelRows($definition, $request, $limit);

        $rows = [];

        foreach ($sourceRows as $sourceRow) {
            foreach ($this->flattenRows((array) $sourceRow) as $flat) {
                $rows[] = $this->projectRow($flat, $columns);

                if (count($rows) >= $limit) {
                    break 2;
                }
            }
        }

        return [
            // `headers` dipakai untuk CSV (label manusiawi, atau nama field mentah bila
            // diminta `?header=api`); `keys` untuk wadah JSON supaya enak diolah skrip.
            'headers' => $rawHeader ? array_keys($columns) : array_values($columns),
            'keys' => array_keys($columns),
            'rows' => $rows,
        ];
    }

    /**
     * Baris dari endpoint feeder: memakai IntegratorDataService agar isinya sama
     * dengan yang ditarik integrator (termasuk penamaan kolom feeder).
     *
     * @param  array<string, mixed>  $definition
     * @return array<int, array<string, mixed>>
     */
    private function datasetServiceRows(array $definition, Request $request, int $limit): array
    {
        $service = app(IntegratorDataService::class);
        $method = $definition['method'];
        $params = $this->datasetParams($definition, $request);

        // Halaman berjalan dengan datanya sendiri; data pribadi yang sudah terlihat
        // operator (mis. NIK di halaman Data Mahasiswa) tidak disamarkan lagi di berkas.
        $needsRequest = (new \ReflectionMethod($service, $method))->getNumberOfParameters() > 0;

        if (! $needsRequest) {
            $result = $service->{$method}();

            return is_iterable($result) ? array_values(is_array($result) ? $result : iterator_to_array($result)) : [];
        }

        $rows = [];
        $page = 1;
        $lastPage = 1;

        // Endpoint feeder dibatasi 500 baris per halaman, jadi unduhan menelusuri
        // halaman sampai habis atau sampai batas baris berkas tercapai.
        do {
            $synthetic = Request::create(
                '/api/v1/integrator/v1/'.$method,
                'GET',
                $params + ['page' => $page, 'per_page' => 500]
            );
            $synthetic->attributes->set('integrator.include_pii', true);

            $paginator = $service->{$method}($synthetic, ...array_values($definition['defaults'] ?? []));

            if ($paginator === null) {
                break;
            }

            $lastPage = (int) $paginator->lastPage();

            foreach ($paginator->items() as $item) {
                $rows[] = $item instanceof Arrayable ? $item->toArray() : (array) $item;
            }

            $page++;
        } while ($page <= $lastPage && count($rows) < $limit);

        return $rows;
    }

    /**
     * Baris dari model Eloquent untuk data referensi (prodi, fakultas, PT, tahun ajaran, ruang).
     *
     * @param  array<string, mixed>  $definition
     * @return array<int, array<string, mixed>>
     */
    private function datasetModelRows(array $definition, Request $request, int $limit): array
    {
        $model = $definition['model'];
        $query = $model::query()->with($definition['with'] ?? []);

        $params = $this->datasetParams($definition, $request);

        if ($search = trim((string) ($params['search'] ?? ''))) {
            $query->where(function ($inner) use ($definition, $search): void {
                foreach ($definition['search'] ?? [] as $column) {
                    $inner->orWhere($column, 'like', '%'.$search.'%');
                }
            });
        }

        foreach ($params as $key => $value) {
            if ($key !== 'search' && $value !== null && $value !== '') {
                $query->where($key, $value);
            }
        }

        return $query->orderBy('id')->limit($limit)->get()->toArray();
    }

    /**
     * Parameter yang diteruskan ke sumber data: hanya yang ada di daftar putih dataset,
     * supaya halaman tidak bisa menyusupkan filter liar ke berkas unduhan.
     *
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function datasetParams(array $definition, Request $request): array
    {
        $allowed = array_values($definition['filters'] ?? []);
        $params = Arr::only($request->query(), $allowed);

        foreach (($definition['defaults'] ?? []) as $key => $value) {
            if (($params[$key] ?? null) === null || $params[$key] === '') {
                $params[$key] = $value;
            }
        }

        return array_filter($params, static fn ($value) => $value !== null && $value !== '');
    }

    /**
     * Ratakan baris bertingkat menjadi baris siap-CSV.
     *
     *  - objek  → kolom bertitik, mis. `study_program.code`;
     *  - daftar objek → beberapa baris (mis. kelas + pengajarnya + jadwalnya, KRS +
     *    mata kuliahnya, kelas + nilai tiap mahasiswa);
     *  - daftar nilai sederhana → digabung `;` supaya tidak memecah baris.
     *
     * @param  array<int|string, mixed>  $row
     * @return array<int, array<string, mixed>>
     */
    private function flattenRows(array $row, string $prefix = ''): array
    {
        // Daftar: setiap anggota menjadi baris sendiri, tanpa menambah indeks numerik
        // ke nama kolom (jadi tetap `lecturers.nidn`, bukan `lecturers.0.nidn`).
        if (array_is_list($row)) {
            if ($row === []) {
                return [[]];
            }

            if (! $this->isListOfArrays($row)) {
                return [[$prefix => $this->joinScalars($row)]];
            }

            $rows = [];

            foreach ($row as $item) {
                $childRows = is_array($item) ? $this->flattenRows($item, $prefix) : [[$prefix => $this->scalarValue($item)]];

                foreach ($childRows as $child) {
                    $rows[] = $child;
                }
            }

            return array_slice($rows, 0, 5000);
        }

        $rows = [[]];

        foreach ($row as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value) && array_is_list($value) && $value !== [] && ! $this->isListOfArrays($value)) {
                $value = $this->joinScalars($value);
            }

            $childRows = is_array($value)
                ? $this->flattenRows($value, $path)
                : [[$path => $this->scalarValue($value)]];

            $merged = [];

            foreach ($rows as $base) {
                foreach ($childRows as $child) {
                    $merged[] = $base + $child;
                }
            }

            // Jaga-jaga agar satu baris bertingkat tidak meledak menjadi ribuan baris.
            $rows = array_slice($merged, 0, 5000);
        }

        return $rows;
    }

    /**
     * Daftar nilai sederhana (mis. rentang tanggal) digabung agar baris tidak pecah.
     *
     * @param  array<int, mixed>  $values
     */
    private function joinScalars(array $values): string
    {
        return implode('; ', array_map(
            static fn ($item) => is_scalar($item) ? (string) $item : (string) json_encode($item, JSON_UNESCAPED_UNICODE),
            $values
        ));
    }

    /**
     * @param  array<int, mixed>  $value
     */
    private function isListOfArrays(array $value): bool
    {
        foreach ($value as $item) {
            if (is_array($item) || is_object($item)) {
                return true;
            }
        }

        return false;
    }

    private function scalarValue(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        // Nilai ISO 8601 dari model (mis. `2026-09-01T00:00:00.000000Z`) dirapikan
        // supaya enak dibaca di Excel dan sama bentuknya dengan kolom waktu lainnya.
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})$/', $value) === 1) {
            return \Illuminate\Support\Carbon::parse($value)->format('Y-m-d H:i:s');
        }

        return is_scalar($value) || $value === null ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Ambil hanya kolom yang diizinkan, dalam urutan yang sudah ditentukan.
     *
     * @param  array<string, mixed>  $flat
     * @param  array<string, string>  $columns
     * @return array<int, mixed>
     */
    private function projectRow(array $flat, array $columns): array
    {
        $projected = [];

        foreach (array_keys($columns) as $path) {
            $projected[] = $flat[$path] ?? null;
        }

        return $projected;
    }
}
