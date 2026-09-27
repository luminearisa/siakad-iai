# Module Integrator

API read-only untuk sistem eksternal yang perlu menarik data SIAKAD, lengkap dengan
**pengelolaan API key** (klien, scope, rotasi, pencabutan) dan **log akses**.

Dipakai pertama kali oleh aplikasi standalone [`integrator/`](../../../integrator)
yang menjembatani SIAKAD ke **Neo Feeder PDDikti**.

## Dua permukaan API

| Prefix | Autentikasi | Untuk siapa |
|---|---|---|
| `api/v1/integrator/*` | Sanctum + permission `integrator.*` | Staf SIAKAD (kelola klien, kunci, log) |
| `api/v1/integrator/v1/*` | **API key** + scope per route | Sistem eksternal (baca data saja) |

### Endpoint staf

| Method | URL | Permission |
|---|---|---|
| GET | `integrator/scopes` | `integrator.keys.view` |
| GET/POST | `integrator/clients` | `integrator.clients.view` / `.manage` |
| GET/PUT/DELETE | `integrator/clients/{client}` | `integrator.clients.view` / `.manage` |
| GET | `integrator/clients/{client}/keys` | `integrator.keys.view` |
| POST | `integrator/clients/{client}/keys` | `integrator.keys.manage` |
| GET | `integrator/keys` | `integrator.keys.view` |
| POST | `integrator/keys/{key}/rotate` · `/revoke` | `integrator.keys.manage` |
| DELETE | `integrator/keys/{key}` | `integrator.keys.manage` (hanya kunci yang sudah dicabut) |
| GET | `integrator/logs` · `integrator/logs/stats` | `integrator.logs.view` |

### Endpoint data (API key)

| URL | Scope | Isi |
|---|---|---|
| `integrator/v1/ping` | *(key valid apa pun)* | uji kredensial + daftar scope |
| `integrator/v1/profile` | `reference.read` | institusi, fakultas, prodi, tahun ajaran, semester, skala nilai |
| `integrator/v1/snapshot` | `reference.read` | jumlah mahasiswa/kelas/KRS per semester |
| `integrator/v1/semesters` | `academic.read` | periode + **kode semester PDDikti** (`20251`) |
| `integrator/v1/students` · `/students/{nim}` | `students.read` | biodata (+ `students.pii` untuk NIK/alamat asli) |
| `integrator/v1/lecturers` | `lecturers.read` | dosen + homebase |
| `integrator/v1/courses` | `courses.read` | mata kuliah + rincian SKS |
| `integrator/v1/curricula` | `curricula.read` | kurikulum + mata kuliah per semester |
| `integrator/v1/classes` | `classes.read` | kelas kuliah + pengampu + jadwal |
| `integrator/v1/enrollments` | `enrollments.read` | KRS/peserta kelas |
| `integrator/v1/akm` | `enrollments.read` | SKS, IPS, IPK per mahasiswa per semester |
| `integrator/v1/grades` | `grades.read` | rekap nilai per kelas atau per mahasiswa |
| `integrator/v1/activities?type=thesis\|mbkm` | `activities.read` | skripsi & MBKM + pembimbing |
| `integrator/v1/graduates` | `graduation.read` | peserta yudisium/lulusan |

Semua endpoint data **read-only** dan mendukung `?search=`, filter (mis.
`semester_id`, `study_program_id`), `per_page` (maks 500), `sort`, `direction`, serta
`updated_since` untuk sinkronisasi inkremental.

## Model keamanan

- Token berbentuk `sk_<prefix>.<secret>`; yang disimpan hanya **prefix** dan
  **SHA-256 hash** (`key_hash` di-hide dari serialisasi). Token asli ditampilkan
  **sekali** pada respons `store`/`rotate`.
- Verifikasi memakai `hash_equals` (konstan waktu) sehingga tidak bocor lewat timing.
- Key punya masa berlaku (`expires_at`) dan bisa dicabut (`revoked_at` + alasan);
  status dihitung (`ApiKeyStatus::for()`) sehingga "revoked"/"expired"/"client nonaktif"
  tidak pernah bisa melenceng dari data.
- **Client** punya allow-list IP (mendukung CIDR, IPv4 & IPv6) dan rate limit per menit
  yang ditegakkan middleware (429 + `Retry-After` + header `X-RateLimit-*`).
- Scope dideklarasikan **per route** di `Routes/api.php`, satu tempat untuk mengaudit
  "apa yang boleh dilakukan sebuah key". Tidak ada scope wildcard.
- **PII dipisah**: `students.read` mengembalikan NIK/telepon/email tersamar
  (`3201********0001`), `students.pii` membuka nilai asli. Keputusan ada di service
  (`IntegratorDataService::includesPii()`), bukan di resource.
- Setiap permintaan — termasuk yang ditolak (401/403/429) — ditulis ke
  `api_request_logs` beserta status, durasi, IP, dan pesan penolakan.
- `ApiKey` sengaja **tidak** memakai trait `Auditable`: trait itu menyalin seluruh
  atribut ke `audit_logs`, yang berarti menyalin material kredensial. Perubahan
  siklus hidup key dicatat eksplisit dengan nilai yang sudah dibersihkan.

## Struktur

```
modules/Integrator/
  Controllers/       ApiClientController, ApiKeyController, ApiRequestLogController, IntegrationController
  Enums/             ApiKeyScope, ApiKeyStatus
  Middleware/        AuthenticateApiKey   (alias route: api.key)
  Models/            ApiClient, ApiKey, ApiRequestLog
  Requests/          Store/UpdateApiClientRequest, Store/Rotate/RevokeApiKeyRequest, IntegrationQueryRequest
  Resources/         ApiClientResource, ApiKeyResource, ApiRequestLogResource
  Services/          ApiKeyService (terbit/rotasi/cabut/verifikasi), IntegratorDataService (read model)
  Database/          Migrations (api_clients, api_keys, api_request_logs) + IntegratorSeeder
  Routes/api.php
```

## Menyiapkan

```bash
php artisan migrate --seed        # seeder modul dijalankan dari DatabaseSeeder
```

Role `super_admin` dan `admin_akademik` menerima seluruh permission `integrator.*`
(`IntegratorSeeder` menambahkan `admin_akademik` secara eksplisit — pola yang sama
dengan bug MBKM, di mana grup permission baru tidak otomatis ikut ke role itu).

Terbitkan kunci lewat UI SIAKAD (**Integrasi & PDDikti → Klien & Kunci API**) atau API:

```bash
curl -X POST "$SIAKAD_BASE_URL/api/v1/integrator/clients" \
  -H "Authorization: Bearer <token-sanctum>" -H "Content-Type: application/json" \
  -d '{"name":"Integrator Neo Feeder","allowed_ips":["10.0.0.0/8"]}'

curl -X POST "$SIAKAD_BASE_URL/api/v1/integrator/clients/1/keys" \
  -H "Authorization: Bearer <token-sanctum>" -H "Content-Type: application/json" \
  -d '{"name":"Produksi","scopes":["students.read","classes.read","enrollments.read","grades.read"]}'
```

Pemakaian oleh sistem eksternal:

```bash
export SIAKAD_BASE_URL="https://siakad.<domain-kampus>"   # tanpa /api/...
export SIAKAD_API_KEY="<token-hasil-terbitkan-kunci>"

curl "$SIAKAD_BASE_URL/api/v1/integrator/v1/students?per_page=50" \
  -H "X-API-Key: $SIAKAD_API_KEY"
```

## Tes

`tests/Feature/IntegratorTest.php` — 16 tes / 216 assertion, mencakup:

- klien & kunci hanya bisa dikelola dengan permission (mahasiswa → 403);
- token hanya ditampilkan sekali, yang tersimpan hanya hash (dicek langsung ke DB);
- key dicabut / kedaluwarsa / klien nonaktif / IP di luar allow-list → 401/403;
- scope per endpoint (mis. key `students.read` tidak bisa membaca nilai);
- masking PII vs `students.pii`;
- rate limit per klien (429 + `Retry-After`);
- `updated_since` dan batas `per_page`;
- rotasi & pencabutan (idempoten), larangan menghapus klien berkunci aktif;
- audit log termasuk penolakan, plus statistiknya;
- kode semester PDDikti (`20261`, dan `…3` untuk semester antara).

```bash
php artisan test --filter=IntegratorTest
```
