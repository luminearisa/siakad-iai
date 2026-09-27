# Laporan Implementasi: Integrator SIAKAD → Neo Feeder PDDikti

Tanggal: 27 September 2026
Status: **selesai & teruji end-to-end** (backend 247 tes hijau, integrasi nyata terhadap mock Neo Feeder)

---

## 1. Yang dibangun

Dua komponen yang saling melengkapi:

| Komponen | Lokasi | Peran |
|---|---|---|
| **Modul Integrator** (bagian dari SIAKAD) | `backend/modules/Integrator/` + UI `frontend/src/pages/integrator/` | Menerbitkan & mengelola API key, menyediakan endpoint data read-only ber-scope, mencatat seluruh akses |
| **Aplikasi standalone** | `integrator/` | Menarik data dari SIAKAD dengan API key, memetakannya, lalu mendorong ke Neo Feeder (`ws/live2.php` / `ws/sandbox2.php`) |

```
SIAKAD ──(API key + scope, read-only)──► integrator/ ──(Web Service feeder)──► Neo Feeder PDDikti
   ▲                                          │
   └── kelola klien, kunci, dan log akses ◄────┘  (operator memantau dari dashboard)
```

Aplikasi `integrator/` sengaja **tanpa framework dan tanpa Composer**: PHP 8.2+ dan
SQLite saja, sehingga bisa ditempatkan di server yang sama dengan Neo Feeder tanpa
menambah risiko dependensi. Semua pemetaan kolom berada di
`integrator/config/feeder_mapping.php` (tidak ada nama kolom PDDikti yang di-hardcode
di kode PHP).

## 2. Modul SIAKAD (`backend/modules/Integrator/`)

- **4 controller**: klien API, kunci API, log akses, dan endpoint data integrasi.
- **2 enum**: `ApiKeyScope` (12 scope) dan `ApiKeyStatus` (status turunan: aktif /
  dicabut / kedaluwarsa / klien nonaktif — tidak pernah disimpan, jadi tidak bisa basi).
- **Middleware `api.key`** (alias di `bootstrap/app.php`): membaca `X-API-Key` atau
  `Authorization: Bearer`, verifikasi hash konstan waktu, cek status klien, allow-list
  IP (CIDR IPv4/IPv6), rate limit per klien, lalu cek scope **per route**.
- **`ApiKeyService`**: terbitkan (`sk_<prefix>.<secret>`), rotasi (cabut + terbit baru),
  cabut (idempoten), verifikasi, dan pencatatan pemakaian. Hanya **prefix + SHA-256**
  yang masuk database.
- **`IntegratorDataService`**: read model yang menghasilkan baris siap-feeder
  (`nim`, `nidn`, `nilai_angka`, `kode semester PDDikti`) dan menegakkan masking PII:
  `students.read` menyamarkan NIM/NIK/telepon/email, `students.pii` membukanya.
- **`api_request_logs`**: append-only, ditulis dari `terminate()` termasuk untuk
  permintaan yang ditolak (401/403/429) — inilah bukti audit "siapa menarik data apa".
- **Permission** `integrator.clients.view/manage`, `integrator.keys.view/manage`,
  `integrator.logs.view`; diberikan ke `super_admin` **dan** `admin_akademik`
  (`IntegratorSeeder`) — mengikuti pelajaran dari modul MBKM, di mana role
  `admin_akademik` tidak otomatis mendapat permission grup baru.
- **UI Vue**: `pages/integrator/Clients.vue` (klien, kunci, terbitkan/rotasi/cabut,
  modal token-tampil-sekali) dan `pages/integrator/Logs.vue` (statistik + log), dengan
  route ber-`meta.permission` dan menu sidebar baru **Integrasi & PDDikti**.

## 3. Aplikasi integrator (`integrator/`)

Mesin sinkronisasi *config-driven*: definisi setiap entity (endpoint SIAKAD, fungsi
feeder, kunci lokal, kolom wajib, pemetaan kolom) ada di
`config/feeder_mapping.php`.

**13 entity** yang sudah dipetakan, dengan urutan ketergantungan yang dijaga
(`--with-dependencies` menjalankannya berurutan):

```
reference → students → student_registrations → courses → curricula → classes
         → class_lecturers → enrollments → akm → grades → activities
         → activity_supervisors → graduates
```

Pengaman yang tertanam di `SyncRunner`:

1. **Idempoten** — baris yang sudah dipetakan dan isinya tidak berubah dilewati
   (`unchanged`); endpoint insert-only tidak pernah dipanggil dua kali untuk baris sama.
2. **Hash hanya dari kolom milik SIAKAD** — id yang berasal dari feeder
   (`@mapping`, `@ref`, `@semester`, konstanta) tidak dihitung, sehingga baris yang baru
   di-insert tidak terkirim ulang hanya karena payload update memuat `id_mahasiswa`.
3. **Kolom wajib** — baris dengan kolom wajib kosong (mis. `id_prodi` tidak ketemu)
   dilewati dengan pesan eksplisit; tidak pernah dikirim setengah jadi ke PDDikti.
4. **Berhenti otomatis** setelah 200 kegagalan berturut-turut.
5. **Dry-run default** — membaca, memetakan, dan mencatat rencana tanpa memanggil feeder.
6. **Ledger pemetaan** (`mappings`) bisa dibetulkan manual lewat UI, dan referensi feeder
   (`GetProfilPT`, `GetAllProdi`, `GetPeriode`, `GetListDosen`, `GetKategoriKegiatan`,
   `GetDictionary`) bisa dipetakan ulang bila kode prodi SIAKAD berbeda dengan PDDikti.
7. **Kredensial terenkripsi** (AES-256-GCM dengan `APP_KEY`), dashboard ber-session +
   CSRF, dan pengujian koneksi on-demand dari UI/CLI.

## 4. Bukti uji end-to-end

Dijalankan nyata di sandbox: SIAKAD (Laravel, SQLite) + `integrator/` +
`integrator/tests/fake_feeder.php` (mock Neo Feeder yang meniru kontrak `live2.php`).

| Skenario | Hasil |
|---|---|
| `siakad:ping` | ✓ key diterima, 12 scope, 10 mahasiswa / 8 kelas / 18 baris KRS terbaca |
| `feeder:ping` | ✓ login `GetToken` + `GetProfilPT` (mode sandbox) |
| `sync:run reference --live` | ✓ 27 baris referensi (PT, 10 prodi, 4 periode, 6 dosen, 2 kategori, 4 kolom kamus); dijalankan ulang → tetap konsisten (upsert) |
| `sync:run students` | ✓ 9/10 berhasil; **1 ditolak** mock dengan pesan PDDikti "NIK … sudah digunakan" — pesan asli feeder tampil, bukan error generik |
| `student_registrations` | ✓ baris anak dari mahasiswa yang gagal **otomatis dilewati** (`id_mahasiswa` kosong) — tidak ada data riwayat yatim |
| `classes` / `class_lecturers` | ✓ 8 kelas + 8 pengampu |
| `enrollments` / `akm` / `grades` | ✓ 15 peserta kelas, 5 AKM, 15 nilai; dijalankan ulang → **0 panggilan feeder** (semua `unchanged`) |
| Baris wajib tidak lengkap | ✓ contoh nyata: periode `20261` belum ada di referensi feeder → baris dilewati dengan pesan `Kolom wajib belum lengkap: id_semester` |
| Rate limit & scope | ✓ 429 + `Retry-After`; key tanpa scope → 403 dengan daftar scope yang kurang |
| Dashboard integrator | ✓ login → dashboard → sinkronisasi → log → referensi → pengaturan (uji browser, 0 galat konsol) |

Distribusi akhir ledger: `students 9`, `student_registrations 9`, `classes 8`,
`class_lecturers 8`, `enrollments 15`, `akm 5`, `grades 15` — 69 baris terpetakan,
1 baris gagal yang memang ditolak feeder (dan itu terlihat di log).

## 5. Perbaikan di luar lingkup (kecil, disengaja)

1. **Token presensi bocor di rekap kelas** — `AttendanceService::classRecap()` mengirim
   `check_in_code` ke semua pengampu. Resource rekap baru
   (`TeachingSessionRecapResource`) menghapus token; tes yang sudah lama merah
   (`AttendanceTest:474`) kini hijau.
2. **Satu assertion tes yang tidak mungkin benar** — `assertJsonPath(…, 85.0)` selalu gagal
   karena JSON menuliskan `85.0` sebagai `85`; dibandingkan sebagai angka.
3. **Suite backend sekarang 247 tes / 1678 assertion, 0 gagal.**

Sisa yang belum disentuh (sudah ada sebelum pekerjaan ini): 8 tes Vitest gagal di
`search-select.spec.ts` (7) dan `attendance.spec.ts` (1), serta 7 galat `vue-tsc` di
`pages/attendance/ClassAttendance.vue` dan `BatchAttendanceModal.vue`.

## 6. Cara menjalankan

```bash
# 1. SIAKAD
cd backend && php artisan migrate --seed        # seeder modul ikut berjalan
php artisan serve --host=127.0.0.1 --port=8080

# 2. Terbitkan API key: UI "Integrasi & PDDikti → Klien & Kunci API"
#    (atau lewat API, lihat backend/modules/Integrator/README.md)

# 3. Aplikasi integrator
cd integrator
php bin/console migrate
php bin/console key:generate
php bin/console user:create "Operator PDDikti" operator@<domain-kampus> "<sandi-kuat-anda>"
php -S 127.0.0.1:3000 -t public public/router.php
# buka http://127.0.0.1:3000 → isi Pengaturan → Uji koneksi → Sinkronisasi
```

Urutan operasional yang disarankan sebelum mode live:
`reference` (live) → `students` (dry-run, lalu live) → `student_registrations` →
`courses`/`curricula` → `classes` → `class_lecturers` → `enrollments` → `akm` → `grades`
→ `activities` → `graduates` (terakhir, setelah yudisium final karena data lulusan tidak
bisa dihapus di PDDikti).

## 6b. Pembaruan: validasi kesiapan pelaporan (rujukan ProFeeder, Open Feeder, Neo Feeder 3.x)

Pembaruan ini berangkat dari analisis produk sejenis — **SEVIMA ProFeeder**, **Open Feeder
(Suteki)**, dan fitur bawaan **Neo Feeder 3.0.1/3.1** — lalu menambahkan yang belum ada di
integrator tanpa menduplikasi data akademik.

### Yang ditemukan pada produk pembanding

| Sumber | Fitur/kebijakan yang relevan |
|---|---|
| ProFeeder (SEVIMA) | Dashboard status pelaporan *real-time*, **persentase pelaporan**, **pratinjau pelaporan**, **rekap data tidak valid menurut aturan Neo Feeder**, **validasi data akademik**, **komparasi data SIAKAD vs PDDikti**, pembukaan periode pelaporan **per prodi**, simulasi PIN, pengguna aplikasi berperan |
| Open Feeder (Suteki) | Impor massal, validasi & pemetaan sebelum kirim, **monitoring + log aktivitas**, pengaturan preferensi sinkronisasi |
| Neo Feeder 3.0.1/3.1 (resmi) | **Nomor HP wajib** pada biodata + format `08…` (tanpa `+62`), validasi email (OTP pelaporan), kolom **tanggal terbit ijazah** pada data kelulusan, aturan **nomor ijazah dikosongkan untuk D3/D4/S1/S2/S3**, akun khusus WS, kewajiban patch terbaru, sinkronisasi per grup tabel, jalur lulus UKOM |
| Dokumentasi Web Service PDDikti | Fungsi tulis memakai body `{"act":..., "token":..., "record":{...}}`; `jenis_kelamin` bernilai `L`/`P`; `GetDictionary` untuk memastikan nama kolom per versi |

### Yang ditambahkan ke integrator

1. **Mesin validasi aturan feeder** (`config/feeder_rules.php` + `src/Validation/`)
   - Pemeriksaan per baris sebelum dikirim: NIK 16 digit, NISN 10 digit, **nomor HP wajib
     format `08…`**, email valid, tanggal (termasuk larangan tanggal masa depan),
     `jenis_kelamin` `L`/`P`, nilai 0–100/indeks 0–4, IPS/IPK 0–4, SKS > 0.
   - **Konsistensi antar kolom**: komposisi SKS mata kuliah = SKS totalnya, jumlah mahasiswa
     kelas ≤ kapasitas, IPS wajib bila SKS semester terisi, tanggal selesai ≥ tanggal mulai.
   - **Keberadaan data referensi**: `id_prodi`, `id_semester`, `id_dosen` harus ada pada hasil
     tarikan referensi feeder.
   - **Deteksi kolom ter-mask**: bila kunci API SIAKAD tidak memegang scope `students.pii`,
     nilai PII dikirim tertutup (`3174**********01`) — dilaporkan sebagai temuan tersendiri
     dengan instruksi menambah scope, bukan dibiarkan lolos.
   - **Pembedaan error vs ketergantungan**: baris yang menunggu sinkronisasi entitas induk
     dicatat sebagai `warning` (tidak dikirim lebih dulu), bukan dianggap data salah.
   - **Catatan tingkat entity** untuk pemetaan yang masih memakai nilai default
     (`id_agama=1`, `id_pembiayaan=1`, `jumlah_sks_pilihan=0`, `id_jenis_keluar=1`) dan untuk
     kolom yang belum ada di SIAKAD (tanggal terbit ijazah).
2. **Gerbang validasi di pipeline sinkronisasi**: baris yang pasti ditolak feeder ditandai
   `invalid`, **tidak dikirim**, dan dihitung terpisah dari kegagalan pengiriman (bisa
   dimatikan lewat `--skip-validation` atau Pengaturan).
3. **Halaman Validasi + `validate:run` + `report:summary`**: rekap alasan tidak valid,
   cakupan per entity, dan **persentase pelaporan per program studi** (padanan fitur
   ProFeeder "persentase pelaporan per prodi"). Kode keluar `1` bila masih ada baris tidak
   valid, sehingga bisa dipakai sebagai gerbang cron sebelum sinkronisasi live.
4. **Katalog error** (`config/feeder_errors.php`): pesan feeder diterjemahkan menjadi kategori
   (autentikasi, hak akses, ketergantungan, duplikat, periode, referensi, validasi data,
   SKS/nilai, kelulusan, ketersediaan, versi/patch) + langkah penanganan, dipakai di log,
   dashboard, dan rekap kegagalan.
5. **Kepatuhan patch Web Service**: body fungsi tulis dibungkus `record` sesuai dokumentasi
   resmi, dengan **fallback otomatis** ke bentuk datar bila instalasi menolak (dan sebaliknya),
   plus pencatatan versi feeder saat uji koneksi + peringatan bila di bawah minimum.
6. **Jeda antar baris** (`request_delay_ms`) agar server feeder yang sibuk tidak dibanjiri.

### Temuan yang ikut diperbaiki

- `IntegratorDataService` kini mengirim `study_program_degree` pada payload lulusan supaya
  aturan nomor ijazah per jenjang (patch 3.0.1) bisa divalidasi.
- Kolom **tanggal terbit ijazah** belum ada di model yudisium SIAKAD; ini dilaporkan sebagai
  catatan pada halaman Validasi, bukan diisi nilai karangan.
- Aturan `jenis_kelamin` sempat ditulis 1/2 dan **dikoreksi menjadi `L`/`P`** setelah
  diverifikasi ke dokumentasi Web Service PDDikti.

### Belum dikerjakan (sengaja)

- **Komparasi data SIAKAD vs PDDikti (Daftar BBM)** ala ProFeeder: perlu menarik balik
  `GetList*` per entity lalu membandingkan field per field dengan tabel `mappings`.
- **Simulasi PIN mahasiswa**: menunggu aturan kelayakan PIN resmi (bukan menebak).
- Impor massal Excel (padanan Open Feeder) — tidak relevan karena data berasal dari SIAKAD.
- Notifikasi email/WhatsApp dan ekspor CSV temuan.

## 7. Langkah berikutnya (belum dikerjakan)

- Entity MBKM ke `InsertAktivitasMahasiswa` (kategori kegiatan sudah ditarik; tinggal
  memetakan MbkmParticipant + rekognisi SKS) dan `InsertUjiMahasiswa` untuk penguji.
- Tombol "jalankan semua entity" dari UI integrator (sekarang via
  `--with-dependencies` di CLI).
- Notifikasi (email/WhatsApp) ketika run gagal, plus ekspor CSV log/temuan validasi.
- Jadwal cron per semester yang otomatis mengikuti semester aktif.
- Uji terhadap instalasi Neo Feeder nyata di sandbox kampus (mock hanya meniru
  validasi umum; validasi kolom wajib diverifikasi lewat `GetDictionary`).
- Komparasi data SIAKAD vs PDDikti (padanan "Daftar BBM") dan ekspor CSV temuan validasi.
