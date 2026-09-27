# Integrator SIAKAD → Neo Feeder PDDikti

Aplikasi **standalone** yang menarik data akademik dari SIAKAD (module `Integrator`
di `backend/modules/Integrator`) lalu mendorongnya ke **Neo Feeder** PDDikti.

Dirancang untuk dijalankan di server yang sama dengan Neo Feeder, tanpa Composer,
tanpa framework, tanpa dependency eksternal — cukup PHP 8.2+ dan SQLite.

```
┌────────────────────┐        API key (X-API-Key)        ┌──────────────────────┐
│  SIAKAD IAI        │ ◄──────────────────────────────── │  integrator/         │
│  modules/Integrator │  /api/v1/integrator/v1/*          │  (aplikasi ini)      │
│  – kelola API key   │ ────────────────────────────────► │  – setting & ledger  │
└────────────────────┘        JSON (read-only)            └──────────┬───────────┘
                                                                    │ Web Service
                                                                    │ ws/live2.php
                                                                    │ ws/sandbox2.php
                                                                    ▼
                                                         ┌──────────────────────┐
                                                         │  Neo Feeder          │
                                                         │  (PDDikti)           │
                                                         └──────────────────────┘
```

SIAKAD tetap menjadi *system of record*; aplikasi ini menyimpan tiga hal saja:
pengaturan, pemetaan id (SIAKAD ↔ PDDikti), dan log sinkronisasi.

---

## 1. Prasyarat

| Kebutuhan | Keterangan |
|---|---|
| PHP | 8.2 atau lebih baru |
| Ekstensi | `curl`, `openssl`, `pdo_sqlite`, `json`, `mbstring` |
| SIAKAD | module `Integrator` (sudah ada di repo ini) dan satu API key |
| Neo Feeder | instalasi Neo Feeder (versi 3.x) dengan akun PT + akses ke Web Service |
| Jaringan | aplikasi ini harus bisa menjangkau SIAKAD dan Neo Feeder |

Server yang disarankan sama dengan host Neo Feeder (seringkali satu server kampus),
sehingga password feeder tidak perlu keluar dari jaringan internal.

## 2. Instalasi

```bash
cd integrator
cp .env.example .env          # opsional: nilai utama diisi lewat UI
php bin/console migrate        # membuat storage/integrator.sqlite
php bin/console key:generate   # APP_KEY untuk enkripsi kredensial (sekali saja)
php bin/console user:create "Operator PDDikti" operator@<domain-kampus> "<sandi-kuat-anda>"
```

Menjalankan dashboard:

```bash
# pengembangan
php -S 127.0.0.1:3000 -t public public/router.php

# produksi: arahkan document root ke folder public/ dan rewrite semua request
# (selain file statis) ke public/index.php
```

Contoh nginx:

```nginx
server {
    listen 80;
    server_name feeder-bridge.<domain-kampus>;
    root /var/www/integrator/public;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

Batasi akses dashboard ke jaringan internal/VPN; jangan pernah membukanya ke internet publik.

## 3. Menyiapkan sisi SIAKAD

1. Masuk SIAKAD sebagai `admin_akademik` (atau `super_admin`).
2. Buka **Integrator → Klien API**, tambahkan klien (mis. `Integrator Neo Feeder`),
   isi allow-list IP server integrator bila perlu, dan atur rate limit.
3. Terbitkan **API key** dengan scope sesuai kebutuhan:
   - `reference.read`, `academic.read` — referensi & struktur akademik
   - `students.read` (+ `students.pii` bila NIK/alamat asli diperlukan feeder)
   - `lecturers.read`, `courses.read`, `curricula.read`, `classes.read`
   - `enrollments.read` (KRS + AKM), `grades.read`
   - `activities.read` (skripsi/MBKM), `graduation.read` (lulusan)
4. **Token hanya ditampilkan sekali.** Simpan ke halaman Pengaturan aplikasi ini.
5. Pantau pemakaian di SIAKAD → **Integrator → Log Akses** (setiap permintaan,
   termasuk yang ditolak, tercatat beserta scope yang diminta).

## 4. Mengisi pengaturan

Halaman **Pengaturan** aplikasi ini menyimpan:

| Setting | Isi |
|---|---|
| Base URL SIAKAD | alamat SIAKAD kampus, tanpa `/api/...` |
| API key SIAKAD | token `sk_...` dari langkah 3 (disimpan terenkripsi AES-256-GCM) |
| Host Neo Feeder | mis. `http://10.0.0.5:8100` — endpoint otomatis `/ws/live2.php` atau `/ws/sandbox2.php` |
| Username/Password feeder | akun PT yang dipakai untuk sinkronisasi (password terenkripsi) |
| Mode sandbox | gunakan `sandbox2.php` sampai rangkaian uji selesai |
| Dry-run default | biarkan aktif hingga hasil dry-run bersih |

Setelah disimpan, tekan **Uji koneksi SIAKAD & Neo Feeder** di dashboard. Yang diuji:
`ping` SIAKAD (menampilkan scope key) dan `GetProfilPT` pada Neo Feeder.

## 5. Alur sinkronisasi yang benar

Urutannya bukan pilihan bebas — PDDikti memvalidasi relasi antar data:

```
1. reference              tarik PT, prodi, periode, dosen, kategori kegiatan, kamus kolom
2. students               biodata mahasiswa                     → id_mahasiswa
3. student_registrations  riwayat pendidikan (per prodi)        → id_registrasi_mahasiswa
4. courses                mata kuliah
5. curricula              kurikulum
6. classes                kelas kuliah                          → id_kelas
7. class_lecturers        dosen pengampu kelas
8. enrollments            KRS / peserta kelas
9. akm                    SKS & IPS per semester
10. grades                nilai perkuliahan
11. activities            aktivitas mahasiswa (skripsi)
12. activity_supervisors  pembimbing skripsi
13. graduates             lulusan / mahasiswa keluar
```

Cara menjalankan (UI: **Sinkronisasi**, atau CLI):

```bash
# 1. tarik referensi dulu (mode live; dry-run tidak menarik data)
php bin/console sync:run reference --live

# 2. uji 20 baris pertama tanpa mengirim apa pun
php bin/console sync:run students --dry-run --limit=20

# 3. kirim sungguhan, sekaligus prasyaratnya secara berurutan
php bin/console sync:run students --live --limit=20 --with-dependencies

# 4. giliran perkuliahan satu semester (contoh kode PDDikti 20251)
php bin/console sync:run classes    --semester=20251 --live
php bin/console sync:run enrollments --semester=20251 --live
php bin/console sync:run akm        --semester=20251 --live
php bin/console sync:run grades     --semester=20251 --live
```

Job terjadwal yang aman (hanya menyentuh baris berubah, karena payload yang sama
persis akan dilewati):

```cron
# setiap 30 menit: nilai & AKM semester ganjil
*/30 * * * * cd /var/www/integrator && php bin/console sync:run grades --semester=20251 --live >> storage/logs/cron.log 2>&1
15 1 * * *  cd /var/www/integrator && php bin/console sync:run reference --live >> storage/logs/cron.log 2>&1
```

## 6. Perilaku pengaman yang sudah tertanam

| Situasi | Yang dilakukan |
|---|---|
| Baris sudah pernah dikirim dan payload tidak berubah | **dilewati** (`unchanged`) — tidak ada panggilan feeder |
| Baris sudah dipetakan pada endpoint insert-only | dilewati, tidak pernah dobel-insert |
| Kolom wajib kosong (mis. `id_prodi` tidak ketemu) | dilewati + pesan `Kolom wajib belum lengkap: ...` |
| Feeder menolak (`error_code != 0`) | dicatat lengkap dengan payload + respons PDDikti |
| 200 kegagalan berturut-turut | run dihentikan otomatis agar tidak membanjiri feeder |
| Mode dry-run | membaca & memetakan semua, tidak memanggil feeder |
| Prasyarat belum jalan | peringatan di UI; mode `--with-dependencies` menjalankan urutannya |

Semua keputusan tersimpan di `sync_logs` (bisa diekspor/dibaca lewat halaman Log),
sehingga operator bisa membuktikan apa yang dikirim ke PDDikti dan kapan.

## 7. Mengubah pemetaan kolom

Nama kolom PDDikti dapat berbeda antar versi/patch. Semua pemetaan ada di
`config/feeder_mapping.php`; tidak ada satu pun nama kolom yang di-hardcode di PHP.

```php
'students' => [
    'source' => ['endpoint' => 'students', 'scope' => 'students.read', 'semester' => false],
    'local_key' => 'nim:{nim}',
    'act_insert' => 'InsertBiodataMahasiswa',
    'act_update' => 'UpdateBiodataMahasiswa',
    'required' => ['nama_mahasiswa', 'jenis_kelamin'],
    'fields' => [
        'nama_mahasiswa' => 'name',                 // kolom SIAKAD
        'jenis_kelamin'  => 'gender',               // sudah dikonversi L/P oleh SIAKAD
        'tanggal_lahir'  => '@date:birth_date',     // konversi tipe
        'id_prodi'       => '@ref:prodi:{study_program.code}',   // id PDDikti dari referensi
        'id_semester'    => '@semester',
        'id_mahasiswa'   => '@mapping:students:nim:{nim}',       // untuk update
    ],
],
```

Sebelum menjalankan mode live, buka **Referensi Feeder → Kamus Kolom**
(`GetDictionary`) dan pastikan nama kolom yang dipakai benar-benar ada di instalasi
Anda. Bila kode prodi di SIAKAD berbeda dengan kode PDDikti, pakai tombol
**Petakan** pada baris referensi untuk menautkannya (`PAI` → kode PDDikti).

## 8. Perintah CLI

```
php bin/console help
php bin/console migrate
php bin/console key:generate
php bin/console user:create <nama> <email> <sandi>
php bin/console user:password <email> <sandi>
php bin/console siakad:ping
php bin/console feeder:ping
php bin/console sync:list
php bin/console sync:run <entity> [--dry-run|--live] [--limit=N] [--semester=20251] [--force] [--with-dependencies]
php bin/console logs:tail [--entity=students] [--status=failed] [--limit=20]
```

## 9. Struktur folder

```
integrator/
  bin/console              CLI
  config/feeder_mapping.php  satu-satunya tempat pemetaan kolom feeder
  public/index.php         front controller (dashboard)
  public/router.php        router untuk `php -S` (pengembangan)
  public/assets/app.css
  src/
    App.php                perakitan service
    Siakad/SiakadClient.php      klien API SIAKAD (API key, paginasi, error mapping)
    NeoFeeder/NeoFeederClient.php klien WS feeder (GetToken, token cache, retry)
    Sync/                  mesin sinkronisasi (Runner, Registry, FieldMapper, ledger)
    Web/                   router, response, controller dashboard
    Support/               config, crypto, database, http, settings, auth, view
  storage/                 SQLite + cache token + log (tidak di-commit)
  templates/               tampilan dashboard
  tests/fake_feeder.php    mock Neo Feeder untuk uji lokal
```

## 10. Uji lokal tanpa feeder asli

```bash
php -S 127.0.0.1:3001 integrator/tests/fake_feeder.php      # mock Neo Feeder
php -S 127.0.0.1:3000 -t integrator/public integrator/public/router.php
```

Arahkan **Host Neo Feeder** ke `http://127.0.0.1:3001` (sandbox tetap aktif), lalu
jalankan `reference` dan `students` dalam mode dry-run. Mock ini meniru validasi
umum: NIK duplikat ditolak, `id_prodi`/`id_semester` wajib, dan token harus valid.

## 11. Troubleshooting

| Gejala | Penyebab yang paling sering |
|---|---|
| `API key ditolak atau sudah dicabut` | key dirotasi/dicabut di SIAKAD, atau token salah salin |
| `API key tidak memiliki scope yang dibutuhkan` | scope di SIAKAD kurang (mis. `grades.read`); terbitkan/rotasi key |
| `Login Neo Feeder gagal` | host WS salah, akun bukan akun PT, atau mode sandbox/live tertukar |
| Banyak `Kolom wajib belum lengkap: id_prodi` | kode prodi di SIAKAD belum dipetakan; jalankan `reference` lalu Petakan kode prodi |
| Banyak `id_kelas` kosong pada KRS | entity `classes` belum dijalankan untuk semester tersebut |
| Semua baris `skipped/unchanged` | memang tidak ada perubahan sejak sinkronisasi terakhir (pakai `--force` bila perlu kirim ulang) |
| `Semester ... tidak ditemukan di SIAKAD` | kode semester PDDikti tidak cocok dengan semester di SIAKAD |
| Dashboard kosong setelah login | `storage/` tidak writable untuk user web server |

## 12. Catatan kepatuhan

- Pelaporan ke PDDikti wajib (UU 12/2012, Permenristekdikti 61/2016): rencana studi
  ≤ 2 bulan sejak perkuliahan dimulai, hasil studi ≤ 2 bulan setelah perkuliahan selesai.
- **Data lulusan tidak bisa dihapus di PDDikti.** Jalankan `graduates` hanya setelah
  yudisium final (SK terbit) dan setelah AKM/nilai semester terakhir tersinkron.
- Gunakan sandbox (`ws/sandbox2.php`) untuk seluruh uji coba; mode live hanya setelah
  operator PDDikti menyetujui.
- Token API key tidak pernah disimpan dalam bentuk asli, baik di SIAKAD (hash)
  maupun di aplikasi ini (terenkripsi + hanya ditampilkan sekali saat dibuat).
