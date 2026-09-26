---
name: siakad-module-conventions
description: Konvensi menambah modul baru atau fitur lintas-lapisan di repo SIAKAD IAI (Laravel modular monolith + Vue 3). Gunakan saat membuat module baru di backend/modules/*, menambah endpoint API, menambah halaman frontend, atau menelusuri kenapa sebuah fitur tidak terhubung end-to-end. Mencakup pola ApiResponse, QueryFilter, AuditService, enum transitions, registrasi route, RBAC, dan mirroring service frontend.
agent_created: true
---

# Konvensi Modul SIAKAD IAI

Repo: `backend/` (Laravel 13, modular monolith di `backend/modules/*`) + `frontend/` (Vue 3 + Vite + TS strict + Tailwind + Pinia).

## Lingkungan (WAJIB)

- PHP **hanya** ada di `/opt/homebrew/bin/php` (8.4.11). Selalu:
  ```bash
  export PATH="/opt/homebrew/bin:$PATH"
  ```
- `vue-tsc` sering kena SIGTERM/OOM di proyek ini. Selalu:
  ```bash
  NODE_OPTIONS="--max-old-space-size=4096" npx vue-tsc --noEmit -p tsconfig.json
  ```
- Tes: PHPUnit 12, sqlite `:memory:`, `RefreshDatabase`, `$this->seed()`.
- Saat tes berganti identitas user: panggil `auth()->forgetGuards()` setelah `actingAs()`, kalau tidak guard akan meng-cache user lama.

## Struktur modul backend

```
backend/modules/<Nama>/
  Controllers/        <Nama>Controller.php
  Models/
  Services/           logika domain di sini, BUKAN di controller
  Enums/              status + values() + transitions()
  Database/Migrations/
  Database/Seeders/
  Notifications/
  Traits/
  Routes/api.php
```

Modul ter-register otomatis lewat `App\Providers\ModuleServiceProvider` — tidak perlu daftar manual.

## Pola wajib backend

**Response** — pakai `HasApiResponse` + `App\Support\ApiResponse`. Jangan return array mentah.
Exception sudah dipetakan: Validation→422, Auth→401, Authorization→403, NotFound→404, MethodNotAllowed→405.

**Query** — pakai `QueryFilter::apply($query, $request)` untuk search/filter/sort/paginate. Mendukung relasi bertitik.

**Audit** — pakai trait `Auditable` + `Modules\Audit\Services\AuditService::log()`.

**Otorisasi** — `permission:` / `role:` middleware alias ada di `bootstrap/app.php`. `super_admin` bypass. `CheckPermission` mendukung alternasi `a|b`.

**Enum status** — selalu punya `values()` dan `transitions()`. Jangan tulis ulang state machine di controller.

## Aturan integritas yang tidak boleh dilanggar

1. **Jangan duplikasi entitas akademik.** Pakai `Student`, `Lecturer`, `StudyProgram`, `Course`, `Curriculum`, `AcademicClass`, `StudentEnrollment`, `StudentEnrollmentItem`, `StudentGrade`, `GradeScale`.
2. **KHS dan transkrip TIDAK punya tabel.** Keduanya diturunkan `StudentPortalService::getStudentKHS()` dari `student_enrollments` → `student_enrollment_items` → `academic_classes` → `GradeCalculationService`. Fitur baru yang ingin "muncul di KHS" harus **menulis ke tabel akademik**, bukan bikin tabel KHS sendiri.
3. **Jangan hitung IPK sendiri.** Pakai mekanisme akademik yang ada.
4. **Jangan hardcode skala nilai** (A=4, dst). Pakai `GradeScale`/`GradeScaleItem` + `GradeCalculationService::convertScoreToGrade()`.
5. **Jangan percaya `student_id`/`participant_id`/`user_id` dari request.** Selalu resolve dari `auth()->user()`.
6. **Kuota/limit** → `DB::transaction` + `lockForUpdate()`.
7. **Data final terkunci** → cek `isLocked()`/`isFinalized()` di **service**, bukan hanya di UI. Dua celah nyata yang pernah lolos: (a) `review()` logbook tidak menolak entri yang sudah difinalisasi, sehingga `approved` → `revision_required` **membuka kunci edit** bagi mahasiswa; (b) `finalizeScore()` menghitung `is_complete` tapi mengabaikannya, sehingga nilai parsial (2 dari 5 komponen) bisa dibekukan permanen dan mengalir ke KHS. Selalu tanya: *"siapa yang boleh keluar dari status terkunci, dan lewat method mana?"*
8. **Riwayat historis tidak pernah dihapus** — pembatalan lewat status, bukan `delete`.
9. **Jangan bikin endpoint/sync palsu** untuk integrasi eksternal (PDDikti/Neo Feeder) yang tidak ada di repo.
10. **State machine tidak seragam di repo ini** — jangan asumsikan semua enum punya peta transisi. Yang benar-benar punya `transitions()`/`canTransitionTo()`: `ProgramStatus` dan `ParticipantStatus`. `LearningAgreementStatus` dan `RecognitionStatus` memakai inline `match` map dengan `LOCKED => []`. `LogbookStatus` **tidak punya peta transisi sama sekali** — hanya precondition ad-hoc di service, dan itu pernah menghasilkan bug. Kalau menambah status baru, tulis peta transisinya secara eksplisit.
11. **`exists:tabel,id` BUKAN bukti kepemilikan.** Validasi itu hanya membuktikan record ada *di suatu tempat*. `document_id` yang divalidasi `exists:mbkm_documents,id` membuat mahasiswa bisa melampirkan **dokumen mahasiswa lain** ke permohonannya sendiri. Selalu tambahkan cek kepemilikan: `$participant->documents()->whereKey($id)->exists()`.
12. **Permohonan yang sudah diputuskan tidak boleh diputuskan ulang.** `decideWithdrawal()` / `decideExtension()` tidak memeriksa status `pending`, sehingga keputusan bisa ditimpa (`rejected` → `approved`) dan setiap keputusan ulang menulis baris history baru. Pola: `if ($current !== 'pending') return 422;` sebelum memanggil service.
13. **Scope listing harus lengkap, bukan hanya kasus mahasiswa.** Cabang `if (isMbkmStudent) … ` saja **tidak cukup** — kasus dosen dan "tidak berhak apa pun" harus ditangani. Pola yang benar: `visibleStudentIds()` (`null` = tanpa batas, `[]` = tidak melihat apa pun), lalu `if ($visible !== null) $query->whereHas(..., fn ($q) => $q->whereIn('student_id', $visible));` — `whereIn` dengan array kosong otomatis mengembalikan nol baris.
14. **Pola baca-lalu-tulis WAJIB di dalam transaksi + row lock.** Cek duplikat (`exists()`) dan akumulasi batas (`sum()`) adalah read-then-write. `DB::transaction` **saja tidak cukup** — tanpa lock, dua request bersamaan sama-sama membaca keadaan lama dan sama-sama lolos. Pernah terjadi di `MbkmRecognitionService`: duplikat rekognisi (MK dihitung dua kali di KHS), batas SKS terlewati, dan satu rekognisi bisa di-approve dua kali. `update()` bahkan tidak punya transaksi sama sekali.
15. **Kunci baris yang PASTI ADA, bukan baris yang akan dibuat.** `lockForUpdate()` pada query yang mengembalikan nol baris tidak dapat diandalkan (hanya mengandalkan gap lock, yang bergantung engine + isolation level). Pola yang dipakai di repo ini: **kunci baris induk** — `MbkmProgram::whereKey($id)->lockForUpdate()->first()` untuk kuota, `MbkmParticipant::whereKey($id)->lockForUpdate()->firstOrFail()` untuk rekognisi. Perhatikan juga: **SQLite (lingkungan tes) mengabaikan `lockForUpdate()`**, jadi race tidak akan pernah tertangkap oleh tes — verifikasi penjaganya, bukan race-nya.
16. **Validasi state machine di luar transaksi = basi.** `transition()` membaca status sebelum membuka transaksi, sehingga dua approve bersamaan sama-sama lolos. Pola: setelah lock, `$model->refresh()` lalu **validasi ulang transisinya**, dan tolak model basi dengan pesan "muat ulang data".
17. **Unique index tidak berlaku untuk `NULL`.** `mbkm_supervisors` unique `(participant_id, role, lecturer_id)` dengan `lecturer_id` nullable → pembimbing eksternal ganda tetap bisa dibuat. Perbaikannya butuh *partial index* (tidak didukung MySQL). Cek kolom nullable sebelum mengandalkan unique index sebagai penjaga.
18. **`destroy()` WAJIB mencerminkan aturan peran di `store()`.** `MbkmAssessmentController::store()` sudah membatasi mahasiswa ke `assessor_type = 'self'`, tetapi `destroy()` hanya memeriksa kepemilikan peserta — sehingga mahasiswa bisa menghapus nilai yang dicatat **pembimbing/mitra** untuk komponen yang sama, yang menaikkan rata-rata baris tersisa dan dengan itu nilai akhirnya. Untuk endpoint per-baris, periksa **kepemilikan baris** (siapa yang mencatat), bukan hanya kepemilikan entitas induk.
19. **Membekukan nilai harus mengunci komponennya juga.** `finalizeScore()` membekukan `final_score`, tetapi `record()` dan `destroy()` tidak memeriksa `score_finalized_at` → komponen masih bisa ditambah/diubah/dihapus setelahnya, sehingga `final_score` tidak lagi sama dengan komponen yang diklaim merangkumnya. Setiap kali ada status "final", tanyakan: **operasi apa saja yang masih bisa mengubah sumber datanya?**
20. **Waspadai stub yang menyesatkan.** `MbkmAttendanceService::assertParticipantAcceptsAttendance()` berdocblock *"Guard used by controllers"* tetapi isinya hanya `$participant->exists` — selalu benar untuk model yang di-resolve dari route. Ia tidak menjaga apa pun. Kalau sebuah method bernama "assert"/"guard" tapi isinya tidak menolak apa pun, itu jebakan bagi pembaca berikutnya.
21. **Cabang peran (`if isStudent … elseif isLecturerOnly …`) GAGAL-TERBUKA untuk semua peran lain.** Ini akar masalah paling produktif di modul ini. Konstruksi itu benar untuk dua peran yang disebutkan dan **tidak men-scope apa pun** untuk sisanya. Dua peran yang paling sering jatuh ke celah: **`admin_akademik`** (tidak memegang satu pun permission `mbkm.*`) dan **kaprodi** — kaprodi juga seorang `dosen`, jadi mudah dikira tertangkap cabang dosen padahal tidak. Jangan "memperbaiki" dengan menambah cabang ketiga: itu hanya memindahkan lubangnya. Pakai `visibleStudentIds()` dengan kontrak **default-deny** (`null` = tanpa batas, `[]` = nol baris, `[id,…]` = terbatas). Urutan cabang di dalamnya juga penting: `mbkm.manage_study_program` **harus** dievaluasi sebelum cabang dosen biasa, kalau tidak kaprodi menyusut jadi "hanya yang saya bimbing". Kasus nyata: `MbkmDashboardController::index()` memakai dashboard admin sebagai *fallback* untuk setiap peran yang bukan mahasiswa/dosen, sehingga `admin_akademik` dilayani statistik institusi; dan lima listing (`participants`, `recognitions`, `logbooks`, `issues`, `applications`) tidak punya default-deny.
22. **Endpoint administratif tanpa scope per-record, walaupun punya `permission:`.** `verify()`, `decide()`, dan `score()` pada pendaftaran bertumpu sepenuhnya pada permission module-wide, sehingga kaprodi yang diberi `mbkm.applications.decide` dapat memverifikasi/menilai/memutuskan pendaftaran prodi lain. Letakkan guard scope **sebelum** `$request->validate()` — payload tidak valid pun harus menghasilkan **403**, bukan **422**. (Catat kejujuran: ini **laten** — dari seeder bawaan hanya `super_admin` memegang permission itu. Tetap bug, karena `mbkm.manage_study_program` ada justru supaya kaprodi bisa.)
23. **Scope listing dan scope record harus predikat yang SAMA.** Setelah listing diperbaiki, detail bisa tertinggal: kaprodi melihat daftar pendaftaran prodinya tetapi `show`/`history` menjawab 403 — daftar menampilkan baris yang tidak bisa dibuka. Kalau `index()` memakai `visibleStudentIds()`, maka `mayAccessApplication()` harus memakai `maySeeStudent()` (yang memanggil helper yang sama). Ini gagal-tertutup (bukan kebocoran) tapi tetap bug fungsional.

## ⚠️ Otorisasi: aturan yang paling sering dilanggar

**Holding a module-wide permission is NOT proof of scope.** Middleware `permission:mbkm.participants.manage`
hanya membuktikan pemanggil "staf", bukan bahwa peserta itu dalam wewenangnya.

Tiga pola yang benar:

| Pola | Kapan dipakai |
|---|---|
| Resource-bound (`{participant}` di URL) | **Wajib** panggil `mayAccessParticipant` / `mayWriteParticipant` / `mayManageParticipant` di dalam method. Middleware saja TIDAK cukup. |
| Listing endpoint (tidak terikat satu resource) | **Wajib** scope query via `visibleStudentIds()` — `null` = tanpa batas (manager), `[]` = tidak boleh lihat apa pun. |
| Endpoint "milik sendiri" (notifikasi, profil) | Scope lewat `$request->user()`. |

Tambahan:
- Cek otorisasi **sebelum** `$file->store()` — jangan biarkan pemanggil tak berwenang menulis ke disk.
- Kaprodi **juga** `dosen`. Kalau memeriksa cabang dosen sebelum cabang prodi, kaprodi akan
  tereduksi jadi "hanya yang dibimbing". Periksa cabang prodi lebih dulu.

### Cara mengaudit otorisasi (jalankan setiap kali menambah route)

Skrip membandingkan `Routes/api.php` dengan isi method tiap controller. Versi lengkap ada di
`/tmp/mbkm_route_audit.py` (regenerasi bila hilang). Inti polanya:

```python
# parse Route::(get|post|...)('uri', [Ctrl::class, 'method']) + ->middleware('…') di ekornya,
# parse tiap controller jadi {method: body} dengan penghitung kurung,
# lalu klasifikasikan.
AUTH_CALLS = re.compile(r'mayAccessParticipant|mayWriteParticipant|mayManageParticipant|mbkmDeny|'
                        r'isMbkmManager|requireStudent|currentStudent|isMbkmStudent|isMbkmLecturerOnly|'
                        r'currentLecturer|hasPermissionTo|managesParticipantStudyProgram|'
                        r'lecturerSupervisesParticipant|visibleStudentIds|maySeeStudent|'
                        r'mayTouchDocument|mayUploadToEntity')
USER_ONLY = re.compile(r'\$request->user\(\)')
```

**Dua keranjang, bukan satu:**

| Keranjang | Kondisi | Arti |
|---|---|---|
| `!! OPEN` | tanpa `permission:` middleware, tanpa `AUTH_CALLS`, tanpa `$request->user()` | endpoint benar-benar terbuka |
| `?? REVIEW` | tanpa `permission:` middleware dan tanpa `AUTH_CALLS`, **tetapi** memanggil `$request->user()` | perlu diperiksa manual |

⚠️ **Jangan masukkan `\$request->user\(\)` ke `AUTH_CALLS`.** Itu kesalahan yang pernah saya buat:
versi lama memperlakukan "memanggil `$request->user()`" sebagai bukti otorisasi, sehingga method
yang hanya **mengoper user ke service** (`$this->service->decide($x, $request->user())`) lolos
sebagai "aman" padahal tidak ada cek otorisasi sama sekali. Karena itu `$request->user()` sekarang
masuk keranjang `REVIEW`, bukan `OPEN`.

Hasil saat ini: **111 route, 0 OPEN, 3 REVIEW** — ketiganya endpoint notifikasi, yang memang
self-scoped lewat `$request->user()->notifications()`.

### ⚠️ Batas kemampuan skrip ini — wajib dibaca

Skrip hanya membuktikan **ada** cek, bukan bahwa cek itu **lengkap**. Tiga kelas bug yang
**lolos** dari skrip:

1. **Scope yang tidak lengkap di listing.** `indexWithdrawals()` memuat `isMbkmStudent` +
   `currentStudent`, jadi lolos audit — padahal cabangnya hanya menangani mahasiswa, sehingga
   dosen non-pembimbing melihat **seluruh** permohonan institusi. Method `index()` di controller
   yang sama punya cabang dosen; `indexWithdrawals()` tidak.
   **Cara menangkapnya: bandingkan setiap method listing dengan method listing sejenis di
   controller yang sama.** Kalau ada yang punya cabang role dan ada yang tidak, itu bug.
2. **Cek scope yang hilang di endpoint ber-middleware.** `decideWithdrawal()` / `decideExtension()`
   punya `permission:` middleware, jadi skrip melewatinya — padahal keduanya tidak memeriksa
   apakah peserta itu dalam wewenang pemanggil, dan tidak memeriksa apakah permohonan sudah
   diputuskan. **Middleware permission bukan bukti scope** (lihat bagian otorisasi).
3. **Permission yang terlalu longgar.** `GET /mbkm/history` diberi
   `permission:mbkm.manage|mbkm.participants.view`. Role `dosen` memegang
   `mbkm.participants.view`, dan feed itu **tidak** di-scope → dosen bisa membaca seluruh
   riwayat transisi status semua mahasiswa.
   **Cara menangkapnya: untuk setiap route ber-permission, tanyakan "role mana saja yang
   memegang permission ini, dan apakah mereka semua pantas melihat data ini tanpa scope?"**
   Permission "view" yang dipegang dosen + endpoint tanpa scope = kebocoran.
4. **Cabang peran yang gagal-terbuka (kelas paling produktif).** Lihat aturan 21. Skrip lolos
   karena `isMbkmStudent`/`isMbkmLecturerOnly` memang *terlihat* seperti cek otorisasi — padahal
   keduanya tidak menangani peran lain. **Cara menangkapnya: untuk setiap method listing, tanyakan
   "peran apa yang TIDAK cocok dengan cabang mana pun, dan apa yang mereka lihat?"** Jawaban
   sebelum ronde 8: `admin_akademik` dan kaprodi melihat **seluruh** institusi. Tambahan: route
   `GET participants` dan `GET recognitions` **tidak punya middleware permission sama sekali**,
   jadi seluruh pertahanannya ada di dalam controller — periksa route tanpa `permission:` lebih
   dulu, di situ satu-satunya penjaga adalah kode yang Anda baca.

### Aturan state machine peserta (MBKM)

`ParticipantStatus` kini punya `transitions()`, `canTransitionTo()`, `isTerminal()`, dan
`workflowOnly()`. **`workflowOnly()` = `[ongoing, completed, withdrawn, terminated]`** — keempatnya
hanya boleh dicapai lewat endpoint alurnya (`start`, `completion/verify`, keputusan permohonan).
Satu-satunya status yang boleh diubah lewat `PUT participants/{id}` adalah **`failed`**, dan
perubahan itu **wajib** ditulis ke `mbkm_status_histories` lewat `MbkmParticipantService::updateAdministrative()`.

Jangan pernah menulis `$participant->update(['status' => ...])` langsung dari controller.

## Permission hanya lewat ROLE

Tidak ada pivot langsung user↔permission. `User::givePermissionTo()` **tidak ada**.
`hasPermissionTo()` menelusuri `roles → permissions`. `super_admin` selalu true.

Untuk tes yang butuh peran khusus (mis. kaprodi):
```php
$role = Role::create(['name' => 'kaprodi_uji', 'display_name' => 'Kaprodi (uji)', 'is_system' => false]);
$role->permissions()->sync(Permission::whereIn('name', [...])->pluck('id'));
$user->assignRole($role);
```

`admin_akademik` menerima permission **per grup** — grup `mbkm` tidak termasuk, jadi dengan
seed default hanya `super_admin` yang memegang permission staf MBKM.

## Pola frontend

```
frontend/src/
  types/<fitur>.ts          union status + interface + peta label/varian
  services/api/<fitur>.ts   mirror 1:1 semua route backend
  pages/<fitur>/...         Index.vue / Show.vue
  router/index.ts           daftarkan rute + meta.permission
  constants/navigation.ts   daftarkan menu
  components/navigation/Sidebar.vue   iconMap — ikon WAJIB didaftarkan
```

- `apiClient` (Axios, Sanctum bearer, error ternormalisasi).
- Komponen bersama: `PageContainer`, `Card`, `Button`, `Input`, `Textarea`, `Modal`, `Tabs`, `Badge`.
- `useToast()` untuk notifikasi; `useAuthStore()` punya `isSuperAdmin`, `isLecturer`, `isStudent`, `permissions`, `roles`.
- Router guard mendukung `meta.permission` / `meta.roles`.
- TS `strict` + `noUnusedLocals` + `noUnusedParameters` — impor tak terpakai = error build.

### Jebakan frontend yang sering kejadian

- **Ikon sidebar**: nama ikon di `navigation.ts` harus ada di import **dan** `iconMap` `Sidebar.vue`. Kalau tidak, jatuh ke fallback `LayoutDashboard` tanpa error. Cek dengan grep nama ikon di navigation.ts lalu bandingkan dengan iconMap.
- **Union type**: nilai dari form (`location_mode`, `status`) bertipe `string`. Perlu cast eksplisit ke union.
- **Field nullable**: `component_id: number | null` → cast saat dikirim.
- **Dua kosakata tercampur**: di modul MBKM, `type` komponen (apa yang dinilai) dan `assessor_type` (siapa yang menilai) adalah **dua daftar berbeda**. Peta label frontend harus persis sama dengan enum backend, kalau tidak dropdown akan menawarkan nilai yang ditolak 422. Selalu bandingkan `*_LABELS` di `types/*.ts` dengan enum backend setelah menambah nilai.
- **Satu sumber untuk daftar pilihan**: jangan definisikan daftar opsi lokal di dalam komponen kalau sudah ada `*_LABELS` di `types/`. Pakai `optionsFrom(LABELS)`.
- **Nama field HARUS mirror payload backend** ⚠️ kelas bug paling halus: panel UI **selalu kosong tanpa error apa pun**, dan tes backend tetap hijau karena endpoint mengembalikan 200 dengan struktur yang benar. Pernah terjadi: backend mengirim `final_score.breakdown[]` dengan `component_name`/`assessors`, sedangkan frontend membaca `finalScore.components`/`c.name`/`c.is_scored` — tidak satu pun ada. Penangkalnya: **jangan pakai `ref<any>`** untuk payload berstruktur tetap; definisikan interface eksplisit di `types/*.ts`, lalu TypeScript akan menolak field yang salah saat `vue-tsc`.
  Cara cepat memeriksa: grep nama field yang dibaca komponen (mis. `\.breakdown\|\.components`) dan cocokkan dengan array key yang benar-benar di-`return` service/controller backend.

### Download berkas (CSV/PDF) dari frontend

Belum ada helper bawaan — pakai `apiClient.download(url, params, filename)`. Backend-nya
`response()->streamDownload(...)` + BOM UTF-8 (`"\xEF\xBB\xBF"`) supaya Excel benar encoding-nya.
`Content-Disposition` belum tentu terekspos lewat CORS, jadi **selalu kirim `filename` eksplisit**.

### Jebakan penulisan tes

- `assertDatabaseCount('tabel', N)` menghitung **seluruh tabel**, termasuk data yang dibuat
  `MbkmSeeder` (seeder mengisi program contoh + komponennya). Scope hitungan ke `program_id`
  atau entity yang diuji: `Model::where('program_id', $id)->count()`.
- Seeder hanya mengisi bila `$program->assessmentComponents()->count() === 0` — jadi program
  bawaan **sudah punya** 5 komponen penilaian sebelum tes menambahkan miliknya sendiri.

## Urutan verifikasi sebelum melapor selesai

```bash
# backend
cd backend && export PATH="/opt/homebrew/bin:$PATH"
php artisan test --filter=<NamaTest>
php artisan test                    # pastikan tidak ada regresi

# frontend
cd frontend
NODE_OPTIONS="--max-old-space-size=4096" npx vue-tsc --noEmit -p tsconfig.json
NODE_OPTIONS="--max-old-space-size=4096" npm run build
```

Laporkan angka pass/fail apa adanya. Jangan mengaku selesai kalau hanya DB/CRUD yang ada — modul baru dihitung selesai kalau rantainya benar-benar tersambung end-to-end.

### ✅ WAJIB: buktikan tes regresi keamanan benar-benar menangkap regresinya

Tes yang lulus **baik sebelum maupun sesudah** perbaikan tidak membuktikan apa pun, tetapi mudah
terlihat "hijau" dan dianggap selesai. Cara mengujinya:

1. **Kembalikan guard sementara** ke perilaku lama (komentari `if (!$this->isMbkmManager(...))`,
   atau ubah `$visible = $this->visibleStudentIds($request)` menjadi `$visible = null`).
2. Jalankan tes barunya, pastikan **GAGAL** — dan gagal dengan pesan yang Anda harapkan
   (`Expected 403 but received 200`, `Failed asserting that 1 is identical to 0`).
3. **Kembalikan guard** memakai Edit/Write (bukan git), lalu jalankan ulang sampai hijau.

Kalau tesnya tetap **lulus** saat guard dikembalikan, tes itu tidak menguji apa pun — perbaiki
tesnya, bukan kodenya. Teknik ini dipakai dan berhasil pada ronde 8 (dua guard, dua kegagalan
persis seperti yang diprediksi).

## ⛔ JANGAN pakai `git`

Perintah user tanpa syarat: dilarang menjalankan perintah `git` apa pun terhadap berkas user. Untuk mengubah/mengembalikan kode, pakai alat Edit/Write dan tulis perubahannya langsung.
