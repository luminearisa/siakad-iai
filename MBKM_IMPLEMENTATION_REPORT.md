# Laporan Implementasi Modul MBKM — SIAKAD IAI

**Tanggal:** 18 September 2026
**Status:** Selesai dan terverifikasi (backend + frontend)
**Lingkup:** Modul MBKM (Merdeka Belajar Kampus Merdeka) end-to-end, terintegrasi dengan sistem akademik yang sudah ada.

---

## 1. Ringkasan Hasil Verifikasi

| Verifikasi | Perintah | Hasil |
|---|---|---|
| Tes MBKM | `php artisan test --filter=MbkmWorkflowTest` | **25 passed (306 assertions)** |
| Tes seluruh backend (regresi) | `php artisan test` | **156 passed (1038 assertions)** |
| Type-check frontend | `npx vue-tsc --noEmit -p tsconfig.json` | **Bersih (0 error)** |
| Build produksi frontend | `npm run build` | **Sukses** |
| Audit otorisasi | skrip pembanding route ↔ controller | **111 route, 0 OPEN, 3 REVIEW** |

**Tidak ada regresi** pada modul akademik yang sudah ada.

> Laporan ini mencakup **delapan ronde**: pembangunan modul, lalu tujuh putaran audit yang
> menemukan dan memperbaiki **21 bug nyata** (sebelas di antaranya celah keamanan / kebocoran data).
> Lihat bagian 15, 16, 17, 18, 19, 20, dan 21.

---

## 2. Audit Repository (Temuan Awal)

Sebelum implementasi, dilakukan audit menyeluruh. Temuan yang menentukan arah desain:

| Aspek | Temuan |
|---|---|
| Framework backend | **Laravel 13.17**, modular monolith di `backend/modules/*`, auto-register via `ModuleServiceProvider` |
| Auth | **Laravel Sanctum** (token), middleware alias `auth:sanctum`, `permission:`, `role:` di `bootstrap/app.php` |
| RBAC | `Role`, `Permission`, trait `HasRolesAndPermissions`; `super_admin` bypass; `CheckPermission` mendukung alternasi `a\|b` |
| Respons API | Terpusat: `App\Support\ApiResponse` + trait `HasApiResponse`; exception mapping 422/401/403/404/405 |
| Filtering | `QueryFilter::apply` (search/filter/sort/paginate, mendukung relasi bertitik) |
| Audit log | `Modules\Audit\Services\AuditService::log()` + trait `Auditable` |
| **KHS** | **TIDAK ADA tabel KHS.** `StudentPortalService::getStudentKHS()` menurunkan KHS dari `student_enrollments` → `student_enrollment_items` → `academic_classes` → `GradeCalculationService` |
| **Transkrip** | **TIDAK ADA tabel transkrip.** Diturunkan dari data yang sama |
| **Notifikasi** | **TIDAK ADA sistem notifikasi** sebelumnya |
| **Document management** | **TIDAK ADA** sebelumnya |
| **PDDikti / Neo Feeder** | **TIDAK ADA** di repository |
| Frontend | Vue 3 + Vite + TypeScript (strict) + Tailwind + Pinia; `vue-tsc -b && vite build` |

**Konsekuensi desain:**
1. MBKM **mendorong** hasil ke tabel akademik yang ada — tidak membuat tabel KHS sendiri, tidak menghitung IPK terpisah.
2. MBKM memakai **notifikasi native Laravel** (`Notifiable` + tabel `notifications`) — modul ini yang pertama membutuhkan.
3. MBKM memiliki **satu** penyimpanan dokumen miliknya sendiri (`mbkm_documents`) karena belum ada document management.
4. PDDikti/Neo Feeder **tidak dibuat** (tidak ada endpoint/sync palsu) — ada guard yang menolak permintaan sync.

---

## 3. Entitas yang Digunakan Ulang (Reuse — Tidak Diduplikasi)

Tidak ada satu pun entitas akademik yang diduplikasi. Semua ini dipakai langsung:

**Identitas & Akademik**
- `Student`, `Lecturer`, `User`, `Role`, `Permission`
- `Faculty`, `StudyProgram`, `Semester`, `AcademicYear`

**Kurikulum & Mata Kuliah**
- `Course`, `Curriculum`, `CurriculumSemester`, `CurriculumSubject`
- `CreditLimit`

**Kelas & KRS**
- `AcademicClass`, `ClassLecturer`
- `StudentEnrollment`, `StudentEnrollmentItem`

**Penilaian**
- `AssessmentScheme`, `AssessmentComponent`, `AssessmentSchemeItem`
- `StudentGrade`, `GradeRevision`
- `GradeCalculationService::convertScoreToGrade()` — **skala nilai yang ada, tidak ada A=4 hardcoded**
- `GradeFinalizationService`

**Layanan Pendukung**
- `AcademicHistoryProvider`
- `SettingService`
- `StudentPortalService::getStudentKHS()` — sumber KHS/transkrip
- `Modules\Audit\Services\AuditService`

**Modifikasi pada modul lama (minimal, tidak mengubah perilaku):**
- `backend/database/seeders/DatabaseSeeder.php` — menambahkan pemanggilan `MbkmSeeder`
- `backend/modules/Identity/Database/Seeders/IdentitySeeder.php` — menambahkan 26 permission `mbkm.*` + grant ke role
- `backend/modules/Student/Services/StudentPortalService.php` — menambahkan method `getAcademicSnapshot()` (read-only, dipakai untuk snapshot eligibility)

---

## 4. Entitas Baru (Milik Modul MBKM)

### 4.1 Tabel (27 tabel, 3 migration)

**Migration 1 — `2026_09_18_000001_create_mbkm_master_tables.php`**

| Tabel | Fungsi |
|---|---|
| `mbkm_program_types` | Master jenis program (reference data, **tidak hardcoded**) |
| `mbkm_partners` | Master mitra MBKM |
| `mbkm_programs` | Master program MBKM |
| `mbkm_program_locations` | Lokasi program (domestik/luar negeri) |
| `mbkm_program_requirements` | Persyaratan program (configurable, **tidak hardcoded**) |
| `mbkm_cooperations` | MoU/MoA/IA/PKS dengan mitra |
| `mbkm_documents` | Penyimpanan dokumen milik MBKM |

**Migration 2 — `2026_09_18_000002_create_mbkm_workflow_tables.php`**

| Tabel | Fungsi |
|---|---|
| `mbkm_applications` | Pendaftaran mahasiswa |
| `mbkm_selection_criteria` | Kriteria seleksi (configurable per program) |
| `mbkm_selection_scores` | Skor seleksi per kriteria |
| `mbkm_participants` | Peserta MBKM (peserta ditetapkan) |
| `mbkm_placements` | Penempatan (level peserta) |
| `mbkm_supervisors` | Pembimbing (dosen + pembimbing lapangan eksternal) |
| `mbkm_learning_agreements` + `mbkm_learning_agreement_items` | Learning Agreement beserta mata kuliah yang dikonversi |
| `mbkm_activity_plans` | Rencana aktivitas |
| `mbkm_activity_logs` | Logbook harian |
| `mbkm_attendances` | Presensi MBKM (**terpisah dari presensi kuliah**) |
| `mbkm_issues` | Monitoring kendala |
| `mbkm_assessment_components` | Komponen penilaian (configurable + bobot configurable) |
| `mbkm_assessments` | Nilai per komponen |
| `mbkm_recognitions` | Rekognisi/konversi SKS (+ kolom integrasi akademik) |
| `mbkm_completions` | Verifikasi penyelesaian |
| `mbkm_extension_requests` | Permohonan perpanjangan |
| `mbkm_withdrawal_requests` | Permohonan pengunduran diri |
| `mbkm_status_histories` | Riwayat perubahan status (audit trail) |

**Migration 3 — `2026_09_18_000003_create_notifications_table.php`**
- `notifications` — tabel notifikasi native Laravel

### 4.2 Enums (17) — Status Machine Terpusat

Semua enum punya `values()` dan `transitions()` sehingga transisi status **tidak ditulis ulang di controller**:

`ProgramStatus`, `ApplicationStatus`, `ParticipantStatus`, `DocumentStatus`,
`LearningAgreementStatus`, `ActivityPlanStatus`, `LogbookStatus`, `MbkmAttendanceStatus`,
`IssueSeverity`, `IssueStatus`, `RecognitionStatus`, `RecognitionType`, `SupervisorRole`,
`CompletionStatus`, `WithdrawalType`, `RequirementType`, `ApprovalDecision`

### 4.3 Models (30)
Master (7), workflow (23) — sesuai daftar tabel di atas.

### 4.4 Services (17)

| Service | Tanggung Jawab |
|---|---|
| `MbkmEligibilityService` | Evaluasi kelayakan **explainable** (`checks[]` + `reasons[]`) |
| `MbkmApplicationService` | Validasi server-side, submit, decide, withdraw |
| `MbkmSelectionService` | Kriteria & skor seleksi |
| `MbkmParticipantService` | Penetapan peserta + **kontrol kuota** (transaction + `lockForUpdate`) |
| `MbkmPlacementService` | Penempatan + pembimbing |
| `MbkmLearningAgreementService` | draft → submitted → reviewed → approved → **locked** |
| `MbkmLogbookService` | Activity plan + logbook + review pembimbing + **lock saat finalized** |
| `MbkmAttendanceService` | Presensi MBKM (single & batch) + ringkasan |
| `MbkmAssessmentService` | Komponen configurable, bobot configurable, nilai akhir |
| `MbkmRecognitionService` | Konversi SKS Model 1 & 2, workflow + revisi formal |
| `MbkmAcademicIntegrationService` | **Mendorong hasil ke tabel akademik** (KRS/nilai) |
| `MbkmCompletionService` | Verifikasi penyelesaian berbasis requirement program |
| `MbkmDashboardService` | Dashboard mahasiswa/dosen/admin |
| `MbkmReportService` | 14 jenis laporan |
| `MbkmHistoryService` | Riwayat status |
| `MbkmNotificationService` | Notifikasi |
| `MbkmPlacementService` | (lihat di atas) |

### 4.5 Controllers (18) & Routes

**110 definisi route** di `backend/modules/MBKM/Routes/api.php`, prefix `mbkm`, middleware `auth:sanctum`.

Controller: `MbkmProgramController`, `MbkmProgramTypeController`, `MbkmPartnerController`,
`MbkmCooperationController`, `MbkmDocumentController`, `MbkmApplicationController`,
`MbkmParticipantController`, `MbkmLearningAgreementController`, `MbkmActivityPlanController`,
`MbkmLogbookController`, `MbkmAttendanceController`, `MbkmIssueController`,
`MbkmAssessmentController`, `MbkmRecognitionController`, `MbkmCompletionController`,
`MbkmDashboardController`, `MbkmReportController`, `MbkmNotificationController`.

### 4.6 Traits & Notifications
- `Traits/MbkmAuthorization.php` — otorisasi terpusat
- `Notifications/MbkmDatabaseNotification.php` — notifikasi database

### 4.7 Seeder
- `Database/Seeders/MbkmSeeder.php` — jenis program + data contoh

---

## 5. Alur End-to-End yang Terhubung

Rantai berikut benar-benar terhubung (bukan hanya CRUD per entitas):

```
Program MBKM (draft → published → registration_open)
        ↓
Pendaftaran  →  Verifikasi Eligibility (explainable)  →  Seleksi (kriteria + skor)
        ↓
Penetapan Peserta  ←  KONTROL KUOTA (transaction + lockForUpdate)
        ↓
Penempatan (level peserta)  →  Pembimbing (utama/co/internal/lapangan)
        ↓
Learning Agreement (draft → submitted → reviewed → approved → LOCKED)
        ↓
Pelaksanaan  →  Presensi MBKM  +  Activity Plan/Logbook (review pembimbing → finalized = LOCKED)
        ↓
Monitoring (dashboard + issue tracking)
        ↓
Penilaian (komponen + bobot CONFIGURABLE)  →  Nilai Akhir
        ↓
Skala Nilai yang ADA (GradeScale / GradeCalculationService)
        ↓
Rekognisi/Konversi SKS (Model 1: aktivitas→MK, 1-ke-banyak & banyak-ke-1; Model 2: aktivitas→bobot SKS)
   draft → submitted → reviewed → approved → LOCKED  (koreksi lewat revisi formal + audit trail)
        ↓
APPROVAL
        ↓
MbkmAcademicIntegrationService  →  student_enrollments / student_enrollment_items / student_grades
        ↓
KHS (diturunkan StudentPortalService::getStudentKHS)  →  Transkrip
        ↓
Verifikasi Penyelesaian (berbasis requirement program)  →  Laporan
```

**Jalur mundur/pembatalan juga tertangani:** withdrawal, cancellation, termination, extension — riwayat historis **tidak pernah dihapus**.

---

## 6. Aturan Bisnis & Validasi

### 6.1 Eligibility Configurable
Dievaluasi di server dari: prodi, fakultas, jenjang, semester, angkatan, IPK, SKS, status mahasiswa, prerequisite, status cuti, batas partisipasi, dan persyaratan lain yang **didefinisikan sebagai data** (`mbkm_program_requirements`), bukan hardcoded.

Katalog mahasiswa hanya menampilkan program yang relevan, dengan status kelayakan yang **dapat dijelaskan**:
```json
{ "is_eligible": false, "eligibility_reasons": [...],
  "eligibility_checks": [ {"code":"ipk","label":"IPK minimal","passed":false,"message":"..."} ] }
```

### 6.2 Validasi Pendaftaran (server-side, saat submit)
Mahasiswa aktif · periode pendaftaran terbuka · prodi/jenjang/angkatan/semester cocok · IPK memenuhi · SKS memenuhi · prerequisite terpenuhi · dokumen wajib lengkap · **tidak ada pendaftaran aktif duplikat** · batas partisipasi tidak terlampaui.

### 6.3 Kuota Aman dari Race Condition
```php
DB::transaction(function () {
    $program = MbkmProgram::whereKey($id)->lockForUpdate()->first();
    // ... cek sisa kuota, baru tetapkan peserta
});
```
Dua approval bersamaan tidak dapat melampaui kuota.

### 6.4 Data Final Terkunci
Learning Agreement dan logbook yang sudah `finalized` **tidak dapat diubah lewat endpoint edit biasa**. Perbaikan hanya melalui alur revisi formal dengan audit trail.

### 6.5 Bobot Penilaian Configurable
Komponen penilaian dan bobotnya adalah **data**, bukan konstanta di kode. UI program menampilkan total bobot hidup dan memvalidasi jumlah = 100%.

### 6.6 Skala Nilai
Nilai akhir dikonversi memakai `GradeCalculationService::convertScoreToGrade()` — memakai `GradeScale`/`GradeScaleItem` yang sudah ada. **Tidak ada skala baru, tidak ada A=4 hardcoded.**

---

## 7. Keamanan & Otorisasi

### 7.1 Prinsip Utama
> **`student_id` / `participant_id` / `user_id` dari request TIDAK PERNAH dipercaya.** Semuanya di-resolve dari `auth()->user()`.

### 7.2 Permission (26 permission `mbkm.*`)
```
mbkm.manage                       mbkm.manage_study_program
mbkm.programs.view                mbkm.programs.manage
mbkm.partners.view                mbkm.partners.manage
mbkm.applications.view            mbkm.applications.apply
mbkm.applications.verify          mbkm.applications.decide
mbkm.participants.view            mbkm.participants.manage
mbkm.logbook.view                 mbkm.logbook.record      mbkm.logbook.review
mbkm.attendance.view              mbkm.attendance.record
mbkm.assessment.view              mbkm.assessment.record   mbkm.assessment.manage
mbkm.recognition.view             mbkm.recognition.manage  mbkm.recognition.approve
mbkm.completion.view              mbkm.completion.verify
mbkm.reports.view
```

### 7.3 Pembagian Otorisasi (`MbkmAuthorization` trait)
- `mayWriteParticipant()` — pemilik (peserta) **atau** pembimbing **atau** manager
- `mayManageParticipant()` — manager/supervisor saja (**peserta dikecualikan**)
- `mbkm.manage_study_program` — membatasi admin ke program studi-nya sendiri

### 7.4 Yang Dicegah (dan bagaimana)
| Ancaman | Pencegahan |
|---|---|
| Akses data mahasiswa lain | `participant` selalu di-resolve dari `auth()->user()`, bukan dari request |
| Dosen lain mengedit data | Cek `mayManageParticipant` terhadap relasi pembimbing |
| Admin prodi lain mengedit | Permission `mbkm.manage_study_program` + filter prodi |
| Kuota terlampaui | Transaction + `lockForUpdate` |
| Pendaftaran duplikat | Cek pendaftaran aktif di `MbkmApplicationService` |
| Rekognisi duplikat | Cek rekognisi existing per peserta |
| MK/kurikulum tidak valid | Validasi `CurriculumSubject` sebelum konversi |
| Nilai untuk peserta tidak valid | Validasi status peserta sebelum penilaian |
| Edit data final | Cek `isLocked()`/`isFinalized()` di service (bukan hanya di UI) |
| Manipulasi ID | Semua lookup divalidasi kepemilikan/otorisasi |
| Lompat status tanpa alur (mis. langsung `completed`) | `ParticipantStatus::transitions()` + `workflowOnly()`; hanya `failed` boleh lewat `PUT participants/{id}` |
| Perubahan status tanpa jejak audit | Setiap perubahan status ditulis ke `mbkm_status_histories` lewat service |
| Permohonan diputuskan ulang | Cek `status === 'pending'` sebelum keputusan |
| Melampirkan dokumen milik orang lain | `exists:` **plus** cek kepemilikan lewat relasi |
| Listing lintas mahasiswa | `visibleStudentIds()` (`null` = tanpa batas, `[]` = tidak melihat apa pun) |

---

## 8. Integrasi

| Integrasi | Status | Keterangan |
|---|---|---|
| **KRS / Enrollment** | ✅ | `MbkmAcademicIntegrationService` menulis ke `student_enrollments` + `student_enrollment_items` |
| **Nilai Akademik** | ✅ | Menulis ke `student_grades`, memakai `AssessmentScheme` yang ada |
| **KHS** | ✅ | Otomatis muncul karena `StudentPortalService::getStudentKHS()` membaca tabel yang sama. **Tidak ada tabel KHS MBKM.** |
| **Transkrip** | ✅ | Diturunkan dari sumber yang sama — tidak ada tabel terpisah |
| **IPK** | ✅ | **Tidak dihitung ulang** — memakai mekanisme akademik yang ada |
| **Skala Nilai** | ✅ | `GradeScale` / `GradeScaleItem` yang ada |
| **Dokumen** | ✅ | Satu penyimpanan `mbkm_documents`; MoU/MoA/IA/PKS lewat `mbkm_cooperations` |
| **Notifikasi** | ✅ | Notifikasi native Laravel (database channel) |
| **Audit Log** | ✅ | `AuditService` + `mbkm_status_histories` |
| **PDDikti / Neo Feeder** | ⛔ | **Tidak diimplementasikan** — tidak ada di repo; ada guard yang menolak permintaan sync |

### Catatan dokumen pendaftaran vs pelaksanaan
Dokumen saat pendaftaran menempel ke `application`; dokumen saat pelaksanaan menempel ke `participant`. Agar verifikasi penyelesaian tetap benar tanpa memindah atau menduplikasi baris:

```php
public function hasDocumentCategory(string $category, array $statuses = ['uploaded','verified']): bool
{
    if ($this->documents()->where('category', $category)->whereIn('status', $statuses)->exists()) {
        return true;
    }
    return (bool) $this->application?->documents()
        ->where('category', $category)->whereIn('status', $statuses)->exists();
}
```

---

## 9. Laporan (14 Jenis)

`GET /api/v1/mbkm/reports/{type}` — semua mendukung filter:

`programs` · `applicants` · `participants` · `participants_by_study_program` ·
`participants_by_partner` · `participants_by_period` · `participant_progress` ·
`attendance` · `logbook` · `assessment` · `recognition` · `recognized_credits` ·
`completion` · `summary`

Format respons: `{ type, generated_at, rows, summary? }`

---

## 10. Frontend

### 10.1 Berkas Baru (16 berkas, ~8.900 baris)

| Halaman | Fungsi |
|---|---|
| `pages/mbkm/dashboard/Index.vue` | Dashboard **role-aware** (mahasiswa / dosen / admin) + notifikasi |
| `pages/mbkm/catalog/Index.vue` | Katalog program untuk mahasiswa + **eligibility explainable** + alur daftar (draft → upload dokumen wajib → submit) |
| `pages/mbkm/my/Index.vue` | Portal mahasiswa: status, logbook, presensi, penilaian (+ self-assessment), rekognisi (+ tautan ke KHS), pendaftaran saya |
| `pages/mbkm/programs/Index.vue` | CRUD program + transisi lifecycle (grafik transisi mencerminkan `ProgramStatus::transitions()`) |
| `pages/mbkm/programs/Show.vue` | Tab Informasi / Lokasi / Persyaratan / Kriteria Seleksi / Komponen Penilaian (grid editable + validasi bobot 100%) |
| `pages/mbkm/applications/Index.vue` | Daftar pendaftar, modal verify/decide/score, aksi tetapkan peserta, modal detail (snapshot akademik + rincian seleksi) |
| `pages/mbkm/participants/Index.vue` | Daftar peserta + penempatan + pembimbing + nilai akhir + SKS direkognisi |
| `pages/mbkm/participants/Show.vue` | **8 tab**: Ringkasan, Penempatan & Pembimbing, LA, Logbook, Presensi, Penilaian, Rekognisi SKS, Penyelesaian + 8 modal aksi |
| `pages/mbkm/monitoring/Index.vue` | Monitoring dosen: peserta bimbingan, review logbook, rekognisi, kendala |
| `pages/mbkm/recognition/Index.vue` | Workflow rekognisi + `sync_status`, approve/reject/lock/correct |
| `pages/mbkm/completion/Index.vue` | Verifikasi penyelesaian (checklist requirement), unggah sertifikat, keputusan withdrawal/extension |
| `pages/mbkm/master/Index.vue` | CRUD jenis program / mitra / kerja sama |
| `pages/mbkm/reports/Index.vue` | 14 jenis laporan (sidebar) + tabel dinamis; `summary` merender counter dashboard |
| `pages/mbkm/components/MbkmStatusBadge.vue` | Badge status (label + varian) |
| `pages/mbkm/components/MbkmStatCard.vue` | Kartu statistik |
| `pages/mbkm/components/MbkmFilterBar.vue` | Filter bar configurable (semester/fakultas/prodi/program/jenis/mitra/status/angkatan/dosen) dengan lazy loading dependensi |

**Pendukung:**
- `types/mbkm.ts` (~29 KB) — semua union status, interface entitas, peta label/varian, helper `optionsFrom()` / `labelOf()` / `variantOf()`
- `services/api/mbkm.ts` (~24 KB) — **mirror 1:1** dari seluruh 110 route (termasuk upload `FormData`)

### 10.2 Berkas yang Diubah
| Berkas | Perubahan |
|---|---|
| `src/router/index.ts` | Blok rute `mbkm/*` (13 rute) dengan `meta.permission` |
| `src/constants/navigation.ts` | Item mahasiswa (`student-mbkm-catalog`, `student-mbkm-my`), dosen (`lecturer-mbkm`), admin grup `mbkm-group` (8 anak) |
| `src/components/navigation/Sidebar.vue` | Menambahkan 4 ikon ke import + `iconMap`: `Compass`, `Medal`, `ClipboardList`, `FileBarChart2` (sebelumnya jatuh ke fallback `LayoutDashboard`) |

---

## 11. Tes Otomatis

**`backend/tests/Feature/MbkmWorkflowTest.php` — 25 passed (306 assertions)**

| Tes | Yang Diverifikasi |
|---|---|
| `full mbkm workflow from program to academic result` | Rantai penuh: program → pendaftaran → verifikasi → seleksi → penetapan → penempatan → pembimbing → LA → logbook → presensi → penilaian → nilai akhir → rekognisi → approval → **hasil akademik muncul di KHS** |
| `program quota is enforced on participant assignment` | Kuota tidak dapat dilampaui |
| `program lifecycle rejects invalid transition` | Transisi status ilegal ditolak |
| `recognition credit ceiling is enforced` | Batas atas SKS rekognisi ditegakkan |
| `completion reports unmet requirements` | Penyelesaian melaporkan persyaratan yang belum terpenuhi |
| `partner assessment is scoped to the participant` | Dosen non-pembimbing **403**, mahasiswa **403**, admin **201**; DB tetap kosong sebelum admin menyimpan |
| `assessment component vocabulary is validated` | `assessor_type='presentation'` → **422**; `type='final_report'` → **422**; kosakata benar → diterima |
| `report can be exported as csv` | Content-type CSV, isi memuat kode program, key laporan tak dikenal → **404** |
| `document listing is scoped to the viewer` | Mahasiswa hanya melihat dokumennya; dosen non-pembimbing melihat kosong; manager melihat semua; mahasiswa **403** saat memverifikasi dokumen mahasiswa lain |
| `study program scoped admin cannot touch other programs participants` | Kaprodi prodi lain **403** pada `start` + dokumen tidak terlihat; kaprodi prodi yang sama **200** + dokumen terlihat |
| `finalized logbook cannot be reopened for editing` | Logbook `approved` tidak dapat ditinjau ulang menjadi `revision_required` (**422**); status tetap `approved` |
| `final score cannot be frozen from an incomplete assessment` | Finalisasi tanpa `force` saat penilaian belum lengkap → **422** dengan error `assessment`; `final_score` tetap `null`; dengan `force=true` → tersimpan |
| `participant status cannot skip the workflow` | `assigned → completed/ongoing/failed` **422**; `start` **200**; `ongoing → failed` **200** dan tercatat di `mbkm_status_histories` |
| `withdrawal decision is scoped and cannot be repeated` | Mahasiswa **403**; kaprodi prodi lain **403**; kaprodi prodi sendiri **200** → peserta jadi `withdrawn`; keputusan ulang **422** |
| `withdrawal and extension listing is scoped to the viewer` | Mahasiswa melihat miliknya; dosen non-pembimbing tidak melihat apa pun; manager melihat semua |
| `global workflow history is staff only` | Dosen **403**, mahasiswa **403**, admin **200** |
| `withdrawal document must belong to the participant` | Dokumen milik entitas lain **422**; dokumen milik peserta **201** |
| `recognition transition rejects a stale model` | Transisi normal tetap **200**; model basi yang statusnya sudah berubah di DB **ditolak**, bukan diterapkan |
| `student cannot delete another assessors score` | Menghapus nilai pembimbing → **403** (baris tetap ada); menghapus self-assessment sendiri → **200** |
| `assessment is frozen after the score is finalized` | Menimpa komponen setelah finalisasi → **422**; menghapusnya → **422**; `final_score` tetap 90 |
| `dashboard denies a role without mbkm permissions` | `admin_akademik` → **403** pada `dashboard` dan `dashboard/admin` |
| `listings are empty for a role without mbkm permissions` | `admin_akademik` melihat `meta.total = 0` pada `participants`/`recognitions`/`withdrawals`/`extensions`, padahal admin melihat 1 |
| `application staff actions are scoped to the study program` | Kaprodi prodi lain **403** pada `verify`/`decide`/`score`; kaprodi prodi sendiri **200**; status di DB tidak berubah |
| `kaprodi can open the applications their list shows` | Prodi lain: list `total = 0` + `show`/`history` **403**; prodi sendiri: list `total = 1` + `show`/`history` **200** |
| `kaprodi listings are scoped to their study program` | `participants`/`logbooks`/`issues`/`applications` semua `total = 0` untuk prodi lain; prodi sendiri `total = 1` |

**Regresi:** `php artisan test` → **156 passed (1038 assertions)** — seluruh modul akademik lama tetap hijau.

---

## 12. Bagian yang Belum Diimplementasikan (dan Alasannya)

| Bagian | Alasan |
|---|---|
| **Sinkronisasi PDDikti / Neo Feeder** | Tidak ada di repository. Sesuai instruksi, **tidak dibuat endpoint atau sync palsu**. Terdapat guard yang menolak permintaan sync. |
| **Export laporan ke PDF** | **Export CSV sudah tersedia** (lihat bagian 15). PDF belum, karena butuh dependensi renderer tambahan. |
| **Object storage eksternal untuk dokumen** | Saat ini memakai disk lokal Laravel. Belum ada konfigurasi S3/OSS di repo. |
| **Multi-tenant / MBKM lintas universitas** | Di luar lingkup sistem akademik satu institusi. |
| **Portal terpisah untuk mitra eksternal** | Penilaian pihak ketiga **sudah bisa dicatat** lewat modal "Input Penilaian" (`assessor_type` = Mitra / Pembimbing Lapangan + field nama penilai). Yang belum ada hanya portal login khusus untuk mitra — tidak diperlukan selama mitra tidak punya akun sistem. |

---

## 15. Ronde 2 — Audit Ulang & Perbaikan Bug

Setelah modul dinyatakan selesai, dilakukan audit ulang terhadap tiga celah yang dilaporkan.
Hasilnya: **dua dari tiga klaim ternyata tidak akurat**, tetapi ditemukan **dua bug nyata**.

### 15.1 Koreksi klaim

| Klaim awal | Kenyataan |
|---|---|
| "UI penilaian mitra belum ada" | **Tidak akurat.** Modal "Input Penilaian" di `participants/Show.vue` sudah menyediakan dropdown `assessor_type` (termasuk `partner` dan `field_supervisor`) plus field `assessor_name`. Kapabilitasnya sudah ada — yang tidak ada hanya shortcut khusus. **Tidak ditambahkan UI baru** agar tidak menjadi duplikat. |
| "Export laporan belum ada" | **Benar.** Sudah ditambahkan (bagian 15.3). |
| "Object storage belum ada" | **Benar**, dan tetap tidak dikerjakan (butuh kredensial eksternal). |

### 15.2 Bug yang ditemukan dan diperbaiki

#### Bug 1 — KEAMANAN: otorisasi endpoint penilaian mitra

**Berkas:** `backend/modules/MBKM/Controllers/MbkmAssessmentController.php`

`storePartnerAssessment()` **tidak memiliki pemeriksaan otorisasi per-peserta sama sekali**.
Middleware hanya `permission:mbkm.assessment.record|mbkm.assessment.manage|mbkm.manage`, dan
role `dosen` memang memegang `mbkm.assessment.record`.

**Dampak:** dosen mana pun dapat memberi nilai mitra untuk **mahasiswa mana pun**, tanpa perlu
menjadi pembimbingnya. Ini melanggar dua aturan wajib: *"grades for invalid participants"* dan
*"never trust participant_id from request"*.

**Perbaikan:** menambahkan `mayManageParticipant($request, $participant)` → **403** bila pemanggil
bukan manager maupun pembimbing peserta. Peserta dikecualikan (mereka sudah punya endpoint
self-assessment yang terpisah).

```php
public function storePartnerAssessment(Request $request, MbkmParticipant $participant): JsonResponse
{
    if (!$this->mayManageParticipant($request, $participant)) {
        return $this->mbkmDeny('Anda tidak berhak mencatat penilaian mitra untuk peserta ini.');
    }
    // ...
}
```

#### Bug 2 — INTEGRITAS DATA: dua kosakata tercampur

**Berkas:** `frontend/src/types/mbkm.ts`, `MbkmProgramController`, `MbkmAssessmentController`

Ada **dua kosakata berbeda** yang tercampur:

| Kosakata | Nilai sah |
|---|---|
| `type` komponen (apa yang dinilai) | `performance`, `logbook`, `final_project`, `partner`, `presentation`, `report`, `self`, `other` |
| `assessor_type` (siapa yang menilai) | `internal_supervisor`, `co_supervisor`, `field_supervisor`, `partner`, `self`, `committee` |

`ASSESSOR_TYPE_LABELS` di frontend berisi `final_report`, `presentation`, `project` — nilai yang
**pasti ditolak backend dengan 422** — sekaligus **kehilangan** `co_supervisor` dan `committee`.
Dropdown "Tipe Penilai" karena itu menawarkan pilihan yang tidak mungkin berhasil.

Selain itu `components.*.assessor_type` divalidasi sebagai `nullable|string|max:40` (teks bebas),
sementara endpoint penilaian memakai daftar `in:` yang di-hardcode. Akibatnya komponen bisa
dikonfigurasi dengan `assessor_type` tidak valid, lalu nilai itu **bocor ke record penilaian**
lewat fallback `$component->assessor_type` di `MbkmAssessmentService::record()`.

**Perbaikan:**
- Enum baru sebagai **satu sumber kebenaran**: `Enums/AssessorType.php` dan `Enums/AssessmentComponentType.php`
- `syncAssessmentComponents` → `Rule::in(...)` untuk `type` dan `assessor_type`
- `MbkmAssessmentController::store()` → daftar `in:` hardcoded diganti `Rule::in(AssessorType::values())`
- Frontend: `ASSESSOR_TYPE_LABELS` diperbaiki (6 nilai), `COMPONENT_TYPE_LABELS` baru (8 nilai),
  union `AssessorType` diperbaiki, union `ComponentType` ditambahkan
- `programs/Show.vue`: konstanta lokal `COMPONENT_TYPES` dihapus, memakai `optionsFrom(COMPONENT_TYPE_LABELS)`

### 15.3 Fitur baru — Export laporan CSV

| Lapisan | Berkas | Perubahan |
|---|---|---|
| Service | `MbkmReportService::tabulate()` | Normalisasi laporan menjadi tabel rata (header + baris). Nilai bersarang di-JSON-encode agar tidak ada data hilang. Laporan `summary` diratakan menjadi pasangan metric/value. |
| Controller | `MbkmReportController::export()` | Streamed CSV + BOM UTF-8 agar Excel membaca encoding dengan benar. |
| Route | `GET mbkm/reports/{type}/export` | Permission sama dengan `show` |
| Frontend | `ApiClient.download()` (`services/api/client.ts`) | Helper download baru (`responseType: 'blob'`), membaca nama berkas dari `Content-Disposition`, fallback ke nama dari pemanggil |
| Frontend | `mbkmService.exportReport()` | Memakai filter aktif yang sama dengan `getReport()` |
| UI | `pages/mbkm/reports/Index.vue` | Tombol **Export CSV** pada kartu hasil |

Karena filter yang aktif ikut terkirim, **isi berkas CSV identik dengan yang tampil di layar**.

### 15.4 Jebakan saat menulis tes

`assertDatabaseCount('mbkm_assessment_components', 5)` gagal (ternyata 10) karena `MbkmSeeder`
sudah mengisi 5 komponen untuk program contohnya sendiri. Hitungan tabel harus **di-scope ke
`program_id` yang diuji**, bukan dihitung global.

### 15.5 Verifikasi ronde 2

| Perintah | Hasil |
|---|---|
| `php artisan test --filter=MbkmWorkflowTest` | **8 passed (143 assertions)** |
| `php artisan test` | **139 passed (875 assertions)** — naik dari 136/851, tanpa regresi |
| `npx vue-tsc --noEmit` | bersih |
| `npm run build` | sukses |

### 15.6 Sisa yang benar-benar belum ada

- Export **PDF** (CSV sudah ada; PDF butuh dependensi renderer).
- **Object storage** eksternal untuk dokumen (butuh kredensial; tidak ada di repo).
- **Sinkronisasi PDDikti/Neo Feeder** (tidak ada di repo — sengaja tidak dibuat).

---

## 16. Ronde 3 — Audit Otorisasi Sistematis

Ronde 2 menemukan bug secara kebetulan. Ronde 3 memakai **skrip audit** yang membandingkan
`Routes/api.php` dengan isi setiap method controller, lalu menandai endpoint yang
**tidak punya cek otorisasi di method DAN tidak punya middleware `permission:`** — kombinasi
itu berarti endpoint terbuka untuk siapa pun yang sudah login.

**Hasil awal: 4 endpoint terbuka**, dan **9 endpoint participant-bound tanpa scope peserta.**

### 16.1 Temuan 1 — KEAMANAN (P1): kebocoran dokumen lintas mahasiswa

**Berkas:** `backend/modules/MBKM/Controllers/MbkmDocumentController.php`

`GET /mbkm/documents` **tidak punya cek otorisasi dan tidak punya middleware**. Siapa pun yang
sudah login dapat menarik sampai **200 dokumen seluruh institusi** — KTM, CV, dan sertifikat
milik **semua** mahasiswa — lengkap dengan `file_path`. Ini persis melanggar aturan wajib
*"Must prevent: cross-student data access"*.

`store()` juga hanya membatasi **mahasiswa**. Pengguna non-mahasiswa mana pun (misalnya dosen
tanpa satu pun permission MBKM) dapat mengunggah berkas ke entitas MBKM mana pun.

**Perbaikan:**
- Helper baru di `MbkmAuthorization`: `visibleStudentIds()` — mengembalikan `null` untuk module
  manager (tanpa batas) dan `[]` untuk "tidak boleh melihat apa pun"; plus `maySeeStudent()`.
- `index()` menerapkan scope **sebelum** filter apa pun:

| Peran | Yang terlihat |
|---|---|
| Module manager (`mbkm.manage`) | Semua |
| Mahasiswa | Dokumen pendaftaran/peserta miliknya sendiri |
| Kaprodi (`mbkm.manage_study_program`) | Seluruh mahasiswa prodinya |
| Dosen pembimbing | Peserta yang mereka bimbing |
| Peran lain | Tidak ada |

- `store()` untuk non-mahasiswa lewat `mayUploadToEntity()`
- `verify()` / `destroy()` lewat `mayTouchDocument()`

### 16.2 Temuan 2 — KEAMANAN: 9 endpoint administratif tanpa scope peserta

Endpoint berikut hanya mengandalkan middleware `permission:mbkm.participants.manage|…` dan
**tidak memeriksa apakah peserta tersebut dalam wewenang pemanggil**:

`PUT participants/{id}` · `start` · `finalize-score` · `placement` · `supervisors` (POST/DELETE) ·
`learning-agreement/reopen` · `completion/verify` · `completion/certificate`

**Dengan seed default ini belum dapat dieksploitasi** — hanya `super_admin` yang memegang
permission staf MBKM, karena `admin_akademik` menerima permission **per grup** dan grup `mbkm`
tidak termasuk daftarnya. Namun begitu dibuat role kaprodi yang wajar
(`mbkm.manage_study_program` + `mbkm.participants.manage`), admin prodi A dapat mengubah data
peserta prodi B. Ini melanggar aturan wajib *"Must prevent: cross-prodi edits"*.

**Perbaikan:**
- Cabang scope prodi diekstrak menjadi `managesParticipantStudyProgram()` dan ditambahkan ke
  `mayManageParticipant()`
- Guard `mayManageParticipant()` dipasang di kesembilan endpoint tersebut
- Khusus `attachCertificate`, pemeriksaan dipasang **sebelum** `$file->store()` agar pemanggil
  tanpa wewenang tidak sempat menulis berkas ke disk

### 16.3 Temuan 3 — BUG di kode yang baru saya tulis

`visibleStudentIds()` versi pertama memeriksa cabang **dosen** sebelum cabang **prodi**.
Karena seorang kaprodi **juga** berperan `dosen`, `isMbkmLecturerOnly()` bernilai `true` untuk
mereka — sehingga kaprodi hanya melihat peserta yang mereka bimbing sendiri, bukan seluruh
prodinya. Bukan kebocoran, tetapi perilaku yang salah.

**Perbaikan:** cabang prodi diperiksa **lebih dulu**, baru cabang dosen. Perilaku ini dikunci
oleh tes (kaprodi pada tes tersebut memang tidak membimbing peserta yang diuji).

### 16.4 Hasil audit akhir

| Metrik | Nilai |
|---|---|
| Route diperiksa | **111** |
| Endpoint tanpa otorisasi DAN tanpa middleware permission | **0** |

### 16.5 Verifikasi ronde 3

| Perintah | Hasil |
|---|---|
| `php artisan test --filter=MbkmWorkflowTest` | **10 passed (164 assertions)** |
| `php artisan test` | **141 passed (896 assertions)** — naik dari 139/896, tanpa regresi |
| Audit otorisasi ulang | **111 route, 0 endpoint terbuka** |

### 16.6 Cara mengulang audit ini

Skrip audit mem-parse setiap `Route::(get|post|put|patch|delete)('uri', [Ctrl::class, 'method'])`
beserta `->middleware('…')` di ekornya, lalu mem-parse setiap controller menjadi peta
`method → body` dengan penghitung kurung. Sebuah endpoint ditandai bila
`not has_auth and not has_permission`.

Regex `AUTH` **harus** mencakup `visibleStudentIds`, `maySeeStudent`, `mayTouchDocument`,
`mayUploadToEntity`, **dan** `$request->user()` — endpoint notifikasi memakai `$request->user()`
sebagai mekanisme scope (bukan helper), sehingga tanpa itu akan salah dilaporkan sebagai terbuka.

### 16.7 Jebakan saat menulis tes otorisasi

Permission di repo ini **hanya diberikan melalui role** — tidak ada pivot langsung
user↔permission, dan `User::givePermissionTo()` tidak ada. Untuk menguji peran seperti kaprodi,
buat `Role` lalu `$role->permissions()->sync([...])` dan `$user->assignRole($role)`.

---

## 17. Ronde 4 — Audit State Machine & Konektivitas Frontend

Ronde 4 menguji dua hal yang belum pernah diperiksa: **konsistensi state machine** dan
**kesesuaian nama field antara payload backend dan tipe frontend**.

### 17.1 State machine tidak seragam

| Entitas | Mekanisme | Dipakai di |
|---|---|---|
| `ProgramStatus` | `transitions()` + `canTransitionTo()` | `MbkmProgramController:185` |
| `LearningAgreementStatus` | inline `match` map + `LOCKED => []` | service LA |
| `RecognitionStatus` | inline `match` map + `LOCKED => []` | service rekognisi |
| `LogbookStatus` | **hanya cek precondition di service — tidak ada peta transisi** | ← celah |
| Nilai akhir peserta | `is_final` flag, tanpa cek kelengkapan | ← celah |

Artinya: dua entitas yang memakai pola eksplisit aman, sedangkan dua sisanya
mengandalkan pengecekan ad-hoc yang ternyata tidak lengkap.

### 17.2 BUG — logbook yang sudah difinalisasi dapat dibuka kembali

`MbkmLogbookService::review()` **tidak memeriksa apakah logbook sudah difinalisasi**.
Dosen pembimbing (atau siapa pun yang memegang `mbkm.logbook.verify`) dapat memindahkan
logbook berstatus `approved` (terkunci) kembali ke `revision_required` — yang otomatis
**membuka kuncinya bagi mahasiswa untuk diedit**. Ini melanggar aturan
"data yang sudah final tidak dapat diubah lewat endpoint normal".

Perbaikan (dua lapis):
1. Tolak bila `$log->isFinalized()`.
2. Tolak bila status asal bukan `submitted` — sebelumnya transisi dari `draft` pun lolos.

### 17.3 BUG — nilai akhir dapat dibekukan dari penilaian yang belum lengkap

`MbkmParticipantService::finalizeScore()` menghitung `is_complete` lewat
`MbkmAssessmentService::computeFinalScore()`, tetapi **hasilnya diabaikan**. Akibatnya
penilaian yang baru terisi 2 dari 5 komponen bisa difinalisasi, dan nilai yang **terlalu
rendah secara permanen** itu langsung mengalir ke KHS dan transkrip.

Perbaikan: `finalizeScore()` kini menolak bila `is_complete` bernilai false, dengan pesan
yang menyebutkan **komponen mana saja yang belum dinilai**. Ditambahkan parameter `force`
sebagai jalan keluar sadar-risiko untuk kasus sah (mis. komponen mitra yang memang tidak
akan pernah diisi), yang harus diminta eksplisit lewat body `{ "force": true }`.

Frontend menyesuaikan: halaman peserta menampilkan peringatan `AlertTriangle` saat
`is_complete` false, dan dialog konfirmasi finalisasi berubah teksnya serta mengirim
`force: true` hanya pada kondisi itu.

### 17.4 BUG — panel "Komponen & Bobot" selalu kosong (frontend)

Ini bug yang paling halus dan **tidak akan pernah terdeteksi oleh tes backend**.

Backend mengirim `final_score` dengan bentuk:

```json
{ "final_score": 86.5, "is_complete": true, "total_weight": 100,
  "breakdown": [ { "component_id": 3, "component_code": "UTS",
                   "component_name": "Ujian Tengah Semester", "type": "performance",
                   "assessor_type": "internal_supervisor", "weight": 30,
                   "average_score": 88, "normalized_score": 88,
                   "weighted_score": 26.4, "assessors": 2 } ] }
```

Frontend membaca `finalScore.components` (tidak pernah dikirim), `c.code`, `c.name`, dan
`c.is_scored` (tidak ada). Karena `finalScore` ditipekan `ref<any>`, TypeScript **tidak bisa**
menangkap kesalahan ini — panelnya sekadar **selalu kosong** tanpa error apa pun.

Tiga halaman terdampak, semuanya diperbaiki:

| Halaman | Kesalahan | Perbaikan |
|---|---|---|
| `participants/Show.vue` | `components`, `c.code`, `c.name`, `c.is_scored` (3 tempat) | `breakdown`, `c.component_id`, `c.component_name`, `c.assessors > 0` |
| `my/Index.vue` | idem (3 tempat) | idem |
| `applications/Index.vue` | `selection.weighted_score`, `selection.components`, `c.name`, `c.is_scored` | `selection.score`, `selection.breakdown`, `c.criteria_name`, `c.reviewers > 0` |

Tipe `MbkmFinalScore` diganti dari `any` menjadi interface eksplisit
(`MbkmFinalScoreBreakdown`) sehingga kesalahan serupa akan **gagal saat type-check**, bukan
diam-diam kosong.

### 17.5 BUG — kosakata `assessor_type` ganda (dituntaskan di ronde ini)

`ASSESSOR_TYPE_LABELS` di frontend memuat `final_report` / `presentation` / `project` —
nilai yang **ditolak backend dengan 422** — dan tidak memuat `co_supervisor` / `committee`.
Sementara itu `components.*.assessor_type` divalidasi sebagai teks bebas (`max:40`) padahal
penilaian memakai daftar `in:` yang di-hardcode, sehingga nilai tak sah dapat menyelinap ke
record penilaian lewat `$component->assessor_type`.

Perbaikan: dua enum baru sebagai sumber kebenaran tunggal —
`Enums/AssessorType.php` (**siapa** yang menilai) dan `Enums/AssessmentComponentType.php`
(**apa** yang diukur) — dipakai di kedua endpoint, dan peta label frontend disamakan.

### 17.6 Verifikasi ronde 4

| Pemeriksaan | Hasil |
|---|---|
| `php artisan test --filter=MbkmWorkflowTest` | **12 passed (188 assertions)** |
| `php artisan test` (regresi penuh) | **143 passed (920 assertions)** |
| `vue-tsc --noEmit` | **bersih (exit 0)** |
| Audit route ↔ controller | **111 route, 0 endpoint terbuka** |

### 17.7 Pelajaran

**Nama field frontend harus mencerminkan payload backend apa adanya.** Tes backend tidak
akan pernah menangkap kelas bug ini: endpoint mengembalikan 200 dengan struktur yang benar,
tetapi UI membaca kunci yang tidak ada sehingga tampil kosong tanpa error. Penangkalnya
adalah **menghindari `ref<any>`** untuk payload yang punya bentuk tetap, dan mendefinisikan
interface-nya secara eksplisit.

---

## 18. Ronde 5 — Mass Assignment, State Machine Peserta & Kebocoran Listing

Ronde 5 mengaudit tiga area yang belum pernah diperiksa: **mass assignment / kepercayaan pada
payload request**, **state machine `ParticipantStatus`**, dan **kelengkapan scope pada endpoint
listing**. Ditemukan **5 bug** — dan **satu kelemahan pada skrip audit saya sendiri**.

### 18.1 Mass assignment — bersih

Pencarian `->all()`, `->except()`, `->only()`, `->merge()`, `->replace()` di seluruh modul:
**tidak ada satu pun**. Setiap `create()`/`update()` memakai array hasil `$request->validate()`
dengan daftar field eksplisit, dan model memakai `$fillable`. Tidak ada celah.

### 18.2 BUG (integritas) — `PUT participants/{id}` adalah pintu belakang state machine

`MbkmParticipantController::update()` menerima `status` dan menulisnya **langsung ke kolom**
dengan satu-satunya penjagaan berupa `in:` terhadap daftar enum:

```php
'status' => ['nullable', 'string', 'in:' . implode(',', ParticipantStatus::values())],
...
$participant->update($validated);   // langsung, tanpa cek transisi
```

Akibatnya seorang pemanggil dapat melompat dari `assigned` **langsung ke `completed`** —
melewati penempatan, learning agreement, logbook, presensi, penilaian, dan verifikasi
penyelesaian. Peserta itu:

- dihitung `completed` di dashboard, padahal tidak ada **satu pun** record penyelesaian;
- tetap **memakan slot kuota** (`occupyingQuota()` mencakup `completed`), sehingga mahasiswa
  lain ditolak karena "kuota penuh" padahal tidak ada yang benar-benar lulus;
- tidak meninggalkan **jejak apa pun** di `mbkm_status_histories`, karena `update()` melewati
  history service — jadi perubahan status tidak terlihat di audit trail.

Sebaliknya, peserta yang sudah `completed`/`withdrawn`/`terminated` dapat **dihidupkan kembali**
menjadi `ongoing`.

**Perbaikan** — dua lapis:

1. `ParticipantStatus` kini punya `transitions()`, `canTransitionTo()`, `isTerminal()`, dan
   `workflowOnly()` — sejajar dengan `ProgramStatus`:

   | Status | Boleh dicapai lewat |
   |---|---|
   | `ongoing` | `POST participants/{id}/start` |
   | `completed` | `POST participants/{id}/completion/verify` |
   | `withdrawn` / `terminated` | keputusan `POST withdrawals/{id}/decide` |
   | `failed` | **satu-satunya** yang boleh lewat `PUT participants/{id}` |

2. `MbkmParticipantService::updateAdministrative()` — memvalidasi transisi, menolak target
   `workflowOnly()` dengan pesan yang menunjuk alur resminya, dan **selalu menulis riwayat**
   (`action = participant.status_changed`) untuk setiap perubahan yang diterima.
   Controller tidak lagi menyentuh kolom `status` secara langsung.

### 18.3 BUG (kebocoran) — `indexWithdrawals()` & `indexExtensions()` hanya men-scope mahasiswa

```php
if ($this->isMbkmStudent($request)) {          // ← hanya cabang mahasiswa
    $query->whereHas('participant', fn ($q) => $q->where('student_id', $student?->id));
}
// tidak ada cabang dosen, tidak ada default "tidak melihat apa pun"
```

Route-nya **tanpa** middleware `permission:`, sehingga **setiap pengguna yang terautentikasi** —
termasuk dosen yang tidak membimbing siapa pun — dapat membaca **seluruh** permohonan
pengunduran diri dan perpanjangan se-institusi.

Ironinya method `index()` (penyelesaian) di controller yang sama **sudah** punya cabang dosen;
`indexWithdrawals()` dan `indexExtensions()` tidak. Ketidakkonsistenan antar-method inilah akarnya.

**Perbaikan:** keduanya memakai `visibleStudentIds()` — pola yang sama dengan
`MbkmDocumentController` (perbaikan ronde 3): `null` = tanpa batas (manager), `[]` = tidak
melihat apa pun, dan `whereIn('student_id', [])` otomatis mengembalikan nol baris.

### 18.4 BUG (otorisasi) — keputusan permohonan tanpa scope & bisa diulang

`decideWithdrawal()` dan `decideExtension()` hanya mengandalkan middleware route
(`permission:mbkm.participants.manage|mbkm.manage`) dan **tidak memeriksa**:

1. apakah peserta itu **dalam wewenang** pemanggil — admin prodi A dapat memutuskan permohonan
   peserta prodi B (kelas bug yang sama dengan ronde 3);
2. apakah permohonan masih `pending` — keputusan dapat **ditimpa** (`rejected` → `approved`),
   dan setiap keputusan ulang menulis ulang status peserta **serta** menambah baris history baru.

**Perbaikan:** `mayManageParticipant()` sebelum validasi, plus penjagaan
`if ($current !== 'pending') → 422` di kedua endpoint.

### 18.5 BUG (kebocoran) — `GET /mbkm/history` terbuka untuk dosen

Route feed audit workflow institusi diberi `permission:mbkm.manage|mbkm.participants.view`.
Role **`dosen` memegang `mbkm.participants.view`**, sedangkan feed itu **tidak di-scope** —
jadi setiap dosen dapat membaca seluruh riwayat transisi status MBKM semua mahasiswa.

**Perbaikan:** permission dipersempit ke `mbkm.manage` saja. Riwayat **per peserta** tetap
tersedia dan tetap ter-scope di `GET participants/{id}/history` (`mayAccessParticipant`).
Endpoint ini juga tidak dipakai UI mana pun (`getHistory()` di service frontend tidak pernah
dipanggil), jadi tidak ada fitur yang hilang.

### 18.6 BUG (validasi) — `exists:` bukan bukti kepemilikan dokumen

`storeWithdrawal()` memvalidasi `document_id` dengan `exists:mbkm_documents,id` **saja**. Itu
hanya membuktikan dokumen ada *di suatu tempat* di modul, bukan bahwa dokumen itu milik peserta
tersebut. Seorang mahasiswa dapat melampirkan **dokumen mahasiswa lain** ke permohonannya, dan
dokumen itu akan tampil di hadapan peninjau.

**Perbaikan:** cek kepemilikan `$participant->documents()->whereKey($id)->exists()` → **422**
bila tidak cocok.

### 18.7 ⚠️ Koreksi: skrip audit saya sendiri punya kelas false-negative

Pada ronde 3 saya melaporkan **"111 route, 0 endpoint terbuka"** dan mendokumentasikan regex
audit itu di skill sebagai rujukan. Regex `AUTH` versi itu memuat **`\$request->user\(\)`**.

Itu salah. `$request->user()` **bukan** bukti otorisasi: method yang hanya mengoper user ke
service (`$this->service->decide($x, $request->user())`) lolos sebagai "aman" padahal tidak
melakukan cek apa pun. Kelas ini persis yang menyembunyikan temuan 18.4.

**Perbaikan skrip:** regex dipecah menjadi dua, dan hasilnya diklasifikasikan ke dua keranjang:

| Keranjang | Kondisi |
|---|---|
| `!! OPEN` | tanpa `permission:`, tanpa `AUTH_CALLS`, tanpa `$request->user()` |
| `?? REVIEW` | tanpa `permission:` dan tanpa `AUTH_CALLS`, tetapi memanggil `$request->user()` |

Hasil setelah koreksi: **111 route, 0 OPEN, 3 REVIEW** — ketiganya endpoint notifikasi, yang
memang self-scoped lewat `$request->user()->notifications()`.

**Batas kemampuan skrip ini (penting).** Skrip hanya membuktikan **ada** cek, bukan bahwa cek
itu **lengkap**. Empat dari lima temuan ronde ini **lolos** audit:

| Temuan | Kenapa lolos |
|---|---|
| 18.3 listing tidak lengkap | memuat `isMbkmStudent` + `currentStudent` → tampak seperti otorisasi, padahal hanya satu cabang role |
| 18.4 keputusan tanpa scope | punya `permission:` middleware |
| 18.5 `history` terlalu longgar | punya `permission:` middleware |
| 18.2 state machine | punya `permission:` middleware |

Tiga pemeriksaan manual yang kini didokumentasikan di skill:

1. **Bandingkan method listing dengan method listing sejenis di controller yang sama.** Kalau ada
   yang punya cabang role dan ada yang tidak, itu bug.
2. **Untuk setiap route ber-`permission:`, tanyakan role mana yang memegangnya** dan apakah
   mereka semua pantas melihat data itu **tanpa scope**. Permission "view" yang dipegang dosen
   + endpoint tanpa scope = kebocoran.
3. **Untuk setiap endpoint tulis, tanyakan apakah record sasaran diperiksa ada dalam wewenang
   pemanggil** — middleware hanya membuktikan "staf".

### 18.8 Koreksi lain terhadap laporan saya sendiri

Saat investigasi 18.4 saya sempat menyimpulkan `decideWithdrawal()` "tanpa otorisasi sama
sekali". Itu **tidak akurat**: route-nya memang punya middleware
`permission:mbkm.participants.manage|mbkm.manage`. Kesimpulan itu muncul karena `grep -B 3`
memotong baris `->middleware(...)` yang berada di baris berikutnya. Yang benar-benar hilang
adalah **cek scope** dan **penjagaan keputusan ulang** — bukan seluruh otorisasi.

### 18.9 Verifikasi ronde 5

| Pemeriksaan | Hasil |
|---|---|
| `php artisan test --filter=MbkmWorkflowTest` | **17 passed (236 assertions)** |
| `php artisan test` (regresi penuh) | **148 passed (968 assertions)** |
| Audit route ↔ controller (skrip terkoreksi) | **111 route, 0 OPEN, 3 REVIEW** |

### 18.10 Tes baru ronde 5

| Tes | Yang Diverifikasi |
|---|---|
| `participant_status_cannot_skip_the_workflow` | `assigned → completed` **422**, `assigned → ongoing` **422**, `assigned → failed` **422**; `start` **200**; `ongoing → failed` **200** **dan** tercatat di `mbkm_status_histories` |
| `withdrawal_decision_is_scoped_and_cannot_be_repeated` | mahasiswa **403**; kaprodi prodi lain **403**; kaprodi prodi yang sama **200** → status peserta jadi `withdrawn`; keputusan ulang **422** dan status tidak berubah |
| `withdrawal_and_extension_listing_is_scoped_to_the_viewer` | mahasiswa melihat miliknya; dosen non-pembimbing **tidak** melihat apa pun; manager melihat semua |
| `global_workflow_history_is_staff_only` | dosen **403**, mahasiswa **403**, admin **200** |
| `withdrawal_document_must_belong_to_the_participant` | dokumen milik entitas lain **422**; dokumen milik peserta **201** |

---

## 19. Ronde 6 — Integritas Level Database & Race Condition

Ronde 6 mengaudit apa yang belum pernah diperiksa: **constraint di level database** (apakah klaim
"mencegah duplikat" ditopang index unique, atau hanya cek di aplikasi) dan **race condition pada
alur tulis yang memakai pola baca-lalu-tulis**.

### 19.1 Constraint database — sebagian besar sudah benar

| Tabel | Unique index | Status |
|---|---|---|
| `mbkm_participants` | `(program_id, student_id)`, `participant_number` | ✅ |
| `mbkm_selection_scores` | `(application_id, criteria_id, reviewer_id)` | ✅ |
| `mbkm_placements` | `participant_id` | ✅ |
| `mbkm_learning_agreements` | `participant_id` | ✅ |
| `mbkm_completions` | `participant_id` | ✅ |
| `mbkm_attendances` | `(participant_id, attendance_date)` | ✅ |
| `mbkm_assessment_components` | `(program_id, code)` | ✅ |
| `mbkm_assessments` | `(participant_id, component_id, assessor_type, assessor_user_id)` | ✅ (`assessor_user_id` selalu terisi) |
| `mbkm_applications` | hanya `index(program_id, student_id)` | ⚠️ lihat 19.3 |
| `mbkm_recognitions` | hanya `index(participant_id, course_id)` | ⚠️ lihat 19.2 |
| `mbkm_supervisors` | `(participant_id, role, lecturer_id)` — `lecturer_id` **nullable** | ⚠️ lihat 19.3 |

### 19.2 BUG (integritas) — race condition pada rekognisi

`assertNoDuplicate()` dan `assertCreditCeiling()` keduanya **baca-lalu-tulis** dan **tidak
mengunci apa pun**:

```php
// assertNoDuplicate
if ($query->exists()) { throw ... }        // SELECT

// assertCreditCeiling
$existing = (int) $query->sum('credits');  // SELECT
if (($existing + $credits) > $ceiling) { throw ... }
```

Yang memperburuk:

| Method | Transaksi? | Lock? |
|---|---|---|
| `create()` | ✅ `DB::transaction` | ❌ tidak ada |
| `update()` | ❌ **tidak ada transaksi sama sekali** | ❌ |
| `transition()` | ✅ | ❌ — validasi state machine dilakukan **di luar** transaksi |

Akibatnya, dua permintaan bersamaan dapat:

- **membuat dua rekognisi untuk mata kuliah yang sama** — keduanya lolos `assertNoDuplicate`,
  dan MK itu **dihitung dua kali** di KHS/transkrip karena tiap rekognisi yang disetujui menulis
  satu `student_enrollment_item`;
- **melewati batas SKS** — keduanya membaca `$existing` yang sama, masing-masing merasa masih di
  bawah `max_recognized_credits`, tetapi totalnya melewati batas;
- **menyetujui satu rekognisi dua kali** — dua approve bersamaan sama-sama lolos validasi
  transisi dan sama-sama mendorong SKS yang sama ke pipeline akademik.

**Perbaikan:**

1. Helper `lockParticipant()` — mengambil row lock pada **baris peserta**, bukan pada baris
   rekognisi. Baris peserta dipilih karena **selalu ada**, sehingga `lockForUpdate()`
   deterministik; mengunci baris yang belum ada tidak mungkin, dan mengandalkan gap lock pada
   `mbkm_recognitions` bergantung pada engine dan isolation level.
2. `create()` — lock di awal transaksi.
3. `update()` — kini **dibungkus `DB::transaction`** dan ikut mengunci.
4. `transition()` — setelah lock, baris **dibaca ulang** (`refresh()`) dan transisi divalidasi
   ulang terhadap status terbaru. Model yang sudah basi ditolak dengan pesan yang meminta muat
   ulang, bukan diterapkan.

### 19.3 Catatan yang sengaja **tidak** diubah

Ketiga hal berikut adalah temuan nyata, tetapi perbaikannya akan mematahkan alur sah atau
memerlukan fitur database yang tidak didukung MySQL. Saya laporkan apa adanya alih-alih menambal.

| Temuan | Kenapa tidak diubah |
|---|---|
| **`mbkm_applications` tanpa unique `(program_id, student_id)`** | Unique biasa akan **mematahkan daftar-ulang yang sah** — mahasiswa boleh mendaftar lagi setelah pendaftaran sebelumnya ditolak/dibatalkan, dan itu memang diizinkan `ApplicationStatus::activeStatuses()`. Yang dibutuhkan adalah *partial unique index*, yang tidak didukung MySQL. `create()` sudah memakai `lockForUpdate()` pada query cek duplikat; di MySQL/InnoDB itu mengambil gap lock pada index `(program_id, student_id)` sehingga serialisasi **memang terjadi**, tetapi perlindungannya implisit dan bergantung pada engine + isolation level — dan **tidak teruji**, karena lingkungan tes memakai SQLite yang mengabaikan `lockForUpdate()`. |
| **`mbkm_supervisors` unique dengan `lecturer_id` nullable** | Dalam unique index, `NULL` tidak dianggap sama, sehingga **pembimbing lapangan eksternal ganda** dapat dibuat untuk peserta yang sama. Perbaikannya juga butuh partial index (unique hanya bila `lecturer_id IS NOT NULL`). |
| **Satu penilai dapat memberi dua nilai untuk satu komponen** | Unique index memuat `assessor_type`, dan `store()` menerima nilai `assessor_type` apa pun yang valid — bukan hanya milik komponen. Jadi satu penilai dapat membuat baris kedua dengan `assessor_type` berbeda untuk komponen yang sama, lalu `computeFinalScore` **merata-ratakan keduanya** sehingga suara penilai itu berbobot ganda. Tidak saya ubah karena modal "Input Penilaian" di UI memang menawarkan dropdown `assessor_type`; memaksakan nilai milik komponen berisiko mematahkan alur yang sudah bekerja. **Perlu keputusan produk.** |

Catatan kecil: `MbkmAssessmentService::record()` memakai `updateOrCreate` yang tidak atomik —
dua submit bersamaan dapat menabrak unique index dan menghasilkan error 500, bukan korupsi data.

### 19.4 Verifikasi ronde 6

| Pemeriksaan | Hasil |
|---|---|
| `php artisan test --filter=MbkmWorkflowTest` | **18 passed (241 assertions)** |
| `php artisan test` (regresi penuh) | **149 passed (973 assertions)** |
| Audit route ↔ controller | **111 route, 0 OPEN, 3 REVIEW** |

### 19.5 Tes baru ronde 6

| Tes | Yang Diverifikasi |
|---|---|
| `recognition_transition_rejects_a_stale_model` | Transisi normal tetap **200**; model basi yang statusnya sudah berubah di database **ditolak** (`ValidationException`), bukan diterapkan |

> **Batas kejujuran:** race condition yang sesungguhnya **tidak dapat direproduksi** di database
> tes (SQLite in-memory mengabaikan `lockForUpdate()`). Tes di atas memverifikasi **penjaga**
> yang membuat race itu mustahil — yaitu pembacaan ulang di dalam lock — bukan race-nya sendiri.
> Bukti bahwa lock benar-benar menyerialkan transaksi memerlukan pengujian terhadap MySQL/InnoDB.

---

## 20. Ronde 7 — Operasi Hapus & Pembekuan Nilai

Ronde 7 menguji kelas bug yang sama seperti `review()` dan `finalizeScore()` (ronde 4), tetapi
pada operasi **hapus**: apakah data yang sudah final juga terkunci dari `DELETE`? Ini langsung
menyentuh mandat "riwayat historis tidak pernah dihapus".

### 20.1 Sebagian besar endpoint hapus sudah dijaga dengan benar

| Endpoint | Penjagaan | Status |
|---|---|---|
| `DELETE logbooks/{logbook}` | `isFinalized()` → **422** | ✅ |
| `DELETE recognitions/{recognition}` | `isLocked()` → **422** ("gunakan alur koreksi") | ✅ |
| `DELETE programs/{program}` | menolak bila sudah ada peserta | ✅ |
| `DELETE program-types/{type}` | menolak bila masih dipakai program | ✅ |
| `DELETE partners/{partner}` | menolak bila masih dipakai penempatan | ✅ |
| `DELETE documents/{document}` | `mayTouchDocument()` | ✅ |
| `DELETE participants/{id}/supervisors/{s}` | `mayManageParticipant()` | ✅ |
| `DELETE assessments/{assessment}` | **hanya cek kepemilikan peserta** | ❌ lihat 20.2 |
| `DELETE attendances/{attendance}` | `mayWriteParticipant()` | ⚠️ lihat 20.4 |

Siklus hidup logbook ternyata **lengkap**: `update()`, `submit()`, `review()`, dan controller
`destroy()` semuanya memeriksa `isFinalized()`. Tidak ada celah di sana.

### 20.2 BUG — mahasiswa dapat menghapus nilai yang dicatat pembimbingnya

`MbkmAssessmentController::destroy()` hanya memeriksa **kepemilikan peserta**, bukan kepemilikan
baris:

```php
if (!$this->mayWriteParticipant($request, $assessment->participant)) { ... }
$assessment->delete();
```

`mayWriteParticipant()` memasukkan **mahasiswa pemilik peserta**. Bandingkan dengan `store()`,
yang secara eksplisit sudah membatasi mahasiswa:

```php
if ($this->isMbkmStudent($request)) {
    if ($this->currentStudent($request)?->id !== $participant->student_id) { return deny; }
    $validated['assessor_type'] = 'self';   // ← mahasiswa hanya boleh menilai dirinya sendiri
}
```

`destroy()` **tidak mencerminkan aturan itu**. Akibatnya seorang mahasiswa dapat menghapus baris
penilaian milik **pembimbing** atau **mitra** untuk komponen apa pun. Karena
`computeFinalScore()` merata-ratakan baris per komponen, menghapus nilai rendah akan **menaikkan
rata-rata baris yang tersisa** — dan dengan itu nilai akhir serta huruf mutu yang mengalir ke KHS.

**Perbaikan:** mahasiswa hanya boleh menghapus baris yang **ia catat sendiri sebagai
`self`** (`assessor_type === 'self'` **dan** `assessor_user_id === user`). Untuk peran
non-mahasiswa tetap memakai `mayManageParticipant()`.

### 20.3 BUG — komponen penilaian tidak terkunci setelah nilai akhir dibekukan

Baik `MbkmAssessmentService::record()` maupun `destroy()` **tidak memeriksa
`score_finalized_at`**. Setelah `finalizeScore()` membekukan nilai, komponen masih dapat
ditambah, ditimpa, atau dihapus:

- menambah/mengubah komponen → `final_score` **tidak lagi sama** dengan komponen yang diklaim
  merangkumnya, dan nilai akhir yang sudah terlanjur mengalir ke KHS menjadi tak dapat dijelaskan;
- menghapus komponen → nilai akhir kehilangan salah satu sumbernya.

**Perbaikan:** keduanya menolak bila `score_finalized_at !== null` → **422** dengan pesan bahwa
nilai akhir sudah difinalisasi.

### 20.4 Temuan yang sengaja **tidak** diubah

**Presensi MBKM dilaporkan sendiri tanpa langkah verifikasi.** `store()` dan `batch()` memakai
`mayWriteParticipant()`, sehingga mahasiswa dapat mencatat presensi sendiri dengan **status dan
tanggal apa pun** — termasuk `present`. `destroy()` juga miliknya, sehingga baris `absent` dapat
dihapus. Karena

```php
'attendance_percentage' => $total > 0 ? round(($attended / $total) * 100, 2) : null,
```

menghapus satu baris `absent` **menurunkan `$total` tanpa mengubah `$attended`** → persentase
naik → syarat `min_attendance_percentage` yang menjadi prasyarat penyelesaian dapat dilewati.

**Mengencangkan `destroy()` saja tidak menutup lubang ini**, karena `store()` sudah mengizinkan
fabrikasi baris `present`. Perbaikan yang benar memerlukan **keputusan produk**: misalnya
menambahkan `verified_by`/`verified_at` pada `mbkm_attendances` dan hanya menghitung baris
terverifikasi sebagai pemenuhan syarat. Saya laporkan apa adanya alih-alih menambal sebagian.

**`MbkmAttendanceService::assertParticipantAcceptsAttendance()` adalah stub yang menyesatkan.**
Docblock-nya berbunyi *"Guard used by controllers: attendance only for valid participants"*,
tetapi isinya hanya memeriksa `$participant->exists` — yang **selalu benar** untuk model yang
di-resolve dari route (`{participant}`). Ia tidak menjaga apa pun, dan pemanggilnya
(`store`, `batch`) bisa menyangka sebaliknya.

### 20.5 Dua koreksi terhadap analisis saya sendiri di ronde ini

Saya dua kali menyimpulkan terlalu cepat sebelum membaca kode lengkap:

| Kesimpulan awal saya | Kenyataan |
|---|---|
| "`store()` memakai `mayManageParticipant` sedangkan `destroy()` memakai `mayWriteParticipant` — ada asimetri" | **Keduanya** memakai `mayWriteParticipant`. Asimetrinya bukan di permission, melainkan di **aturan peran** yang diterapkan setelahnya. |
| "Mahasiswa bisa memalsukan `assessor_type` untuk mencatat nilai atas nama pembimbing" | **Tidak.** `store()` sudah memaksa `assessor_type = 'self'` untuk mahasiswa. |

Bug yang sebenarnya lebih sempit dan lebih tepat: **`destroy()` tidak mencerminkan aturan yang
sudah ada di `store()`**.

### 20.6 Verifikasi ronde 7

| Pemeriksaan | Hasil |
|---|---|
| `php artisan test --filter=MbkmWorkflowTest` | **20 passed (264 assertions)** |
| `php artisan test` (regresi penuh) | **151 passed (996 assertions)** |
| Audit route ↔ controller | **111 route, 0 OPEN, 3 REVIEW** |

### 20.7 Tes baru ronde 7

| Tes | Yang Diverifikasi |
|---|---|
| `student cannot delete another assessors score` | Menghapus nilai pembimbing → **403** dan barisnya **tetap ada**; menghapus self-assessment sendiri → **200** dan barisnya terhapus |
| `assessment is frozen after the score is finalized` | Menimpa komponen setelah finalisasi → **422**; menghapusnya → **422**; nilai baris tetap 90 dan `final_score` tetap 90 |

---

## 21. Ronde 8 — Sweep Pola Scoping Berbasis Cabang Peran

Ronde 5 menemukan satu kebocoran pada `indexWithdrawals()`. Ronde 8 memeriksa apakah itu
kecelakaan atau **pola**. Jawabannya: pola.

### 21.1 Pola akar masalah

Hampir semua listing di modul ini men-scope dengan cabang peran:

```php
if ($this->isMbkmStudent($request)) {
    $query->where('student_id', $this->currentStudent($request)?->id);
} elseif ($this->isMbkmLecturerOnly($request)) {
    $query->whereHas('supervisors', fn ($q) => $q->where('lecturer_id', $lecturerId));
}
```

Konstruksi ini benar untuk **dua peran yang disebutkan** dan **gagal-terbuka untuk semua peran
lainnya**. Yang jatuh ke celah itu bukan hanya peran eksotis:

| Peran | `isMbkmStudent` | `isMbkmLecturerOnly` | Akibatnya (sebelum perbaikan) |
|---|---|---|---|
| `mahasiswa` | ✅ | ✗ | ter-scope (benar) |
| `dosen` biasa | ✗ | ✅ | ter-scope (benar) |
| `admin_akademik` | ✗ | ✗ | **tanpa scope apa pun** |
| Kaprodi (`dosen` + `mbkm.manage_study_program`) | ✗ | ✗ | **tanpa scope apa pun** |

Baris terakhir yang paling mudah terlewat: **kaprodi juga seorang `dosen`**, sehingga
`isMbkmLecturerOnly()` bernilai `false` untuk mereka (karena mereka punya `mbkm.manage`? tidak —
karena cabang `manage_study_program` belum ada saat itu). Artinya cabang "dosen" pun tidak
menangkap mereka.

Perbaikannya bukan menambah cabang ketiga dan keempat — itu hanya memindahkan lubangnya. Yang
dipakai adalah helper tunggal `visibleStudentIds()` dengan kontrak eksplisit:

| Nilai kembali | Arti |
|---|---|
| `null` | tidak dibatasi (module manager) |
| `[]` | **melihat apa pun tidak boleh** → `whereIn('student_id', [])` cocok dengan nol baris |
| `[1,2,…]` | hanya mahasiswa tersebut |

Kuncinya adalah **default-deny**: peran yang tidak dikenal kini melihat nol baris, bukan semua
baris. Urutan cabang di dalamnya juga penting — cabang `mbkm.manage_study_program` harus
dievaluasi **sebelum** cabang dosen biasa, jika tidak kaprodi akan menyusut menjadi "hanya yang
saya bimbing".

### 21.2 BUG (kebocoran) — dashboard admin sebagai fallback untuk semua peran

`MbkmDashboardController::index()` memilih dashboard berdasarkan peran, dan bila pengguna bukan
mahasiswa dan bukan dosen murni, ia **jatuh ke dashboard admin**. Endpoint ini tidak punya
middleware permission, jadi `admin_akademik` — yang tidak memegang satu pun permission `mbkm.*` —
dilayani statistik MBKM seluruh institusi.

Diperbaiki: fallback kini harus membuktikan diri lewat `isMbkmManager()`. Guard yang sama
ditambahkan di `admin()`, yang sebelumnya hanya bergantung pada middleware route.

### 21.3 BUG (kebocoran) — lima listing tanpa default-deny

| Endpoint | Middleware permission | Sebelum |
|---|---|---|
| `GET participants` | **tidak ada** | `admin_akademik` melihat seluruh peserta institusi |
| `GET recognitions` | **tidak ada** | idem |
| `GET logbooks` | `mbkm.logbook.view` (dipegang `dosen`) | kaprodi tanpa scope |
| `GET issues` | `mbkm.participants.view` (dipegang `dosen`) | kaprodi tanpa scope |
| `GET applications` | `mbkm.applications.view` (dipegang `dosen`) | kaprodi tanpa scope |

Dua baris pertama adalah yang paling serius: **tidak ada middleware permission sama sekali**,
sehingga seluruh pertahanan bergantung pada scope di dalam controller — dan scope itu tidak punya
default-deny.

Semua lima kini memakai `visibleStudentIds()`.

### 21.4 BUG (otorisasi, laten) — `verify`/`decide`/`score` tanpa scope per-record

Ketiga endpoint ini **sama sekali tidak memeriksa record mana yang boleh disentuh**; mereka hanya
bertumpu pada permission route (`mbkm.applications.verify`, `mbkm.applications.decide`,
`mbkm.manage`). Padahal permission itu bersifat **module-wide** — persis aturan yang sudah
dituliskan sendiri di docblock `mayManageParticipant()`:

> holding a module-wide permission such as `mbkm.participants.manage` is not on its own enough to
> prove the actor is scoped to *this* participant

Konsekuensinya: begitu sebuah institusi memberikan `mbkm.applications.decide` kepada kaprodi,
kaprodi itu dapat **memverifikasi, menilai, dan memutuskan seleksi pendaftaran prodi lain**.

**Status kejujuran:** ini **laten**, bukan sedang dapat dieksploitasi. Dari seeder bawaan hanya
`super_admin` yang memegang `mbkm.applications.verify`/`decide`; `admin_akademik` tidak punya
permission `mbkm.*` sama sekali dan `dosen` hanya memegang sisi *view*. Saya tetap memperbaikinya
karena niat adanya permission `mbkm.manage_study_program` jelas: kaprodi memang dimaksudkan
memverifikasi pendaftaran prodinya sendiri.

Diperbaiki dengan `mayManageApplication()`, yang menambahkan cabang scope prodi di samping
`isMbkmManager()`. Guard ini sengaja diletakkan **sebelum** `$request->validate()` sehingga
payload tidak valid pun menghasilkan **403**, bukan **422** — urutan itu sendiri bagian dari
kontrak dan diuji.

### 21.5 BUG (fungsional) — kaprodi tidak dapat membuka pendaftaran yang muncul di daftarnya

`mayAccessApplication()` hanya menangani mahasiswa pemilik dan dosen pembimbing, lalu jatuh ke
`return false`. Setelah 21.3 memperbaiki `index()`, seorang kaprodi melihat daftar pendaftaran
prodinya — tetapi **setiap** detail view (`show`, `history`) menjawab **403**. Daftar dan detail
tidak sepakat.

Ini bukan kebocoran (gagal-tertutup), tapi tetap bug: satu layar yang menampilkan baris yang tidak
bisa dibuka. Diperbaiki dengan menyatukan keduanya pada predikat yang sama, `maySeeStudent()` —
scope listing dan scope record kini secara definisi identik.

### 21.6 Refactor pendukung

`managesParticipantStudyProgram()` kini mendelegasikan ke helper generik baru
`managesStudentStudyProgram(Request, ?int $studentId)`, karena pendaftaran ada **sebelum**
penempatan sehingga belum terikat ke `MbkmParticipant`. Versi generik juga mengganti perbandingan
objek-relasi (`$participant->student->study_program_id`) dengan query `exists()`, sehingga tidak
lagi bergantung pada relasi yang sudah dimuat.

### 21.7 Temuan sampingan (dilaporkan, tidak diubah)

- **Tidak ada listing lain yang tersisa tanpa scope.** Setelah ronde ini, seluruh endpoint listing
  di modul MBKM memakai `visibleStudentIds()` atau terikat ke satu peserta.
- Skrip audit route ↔ controller tetap melaporkan **3 REVIEW** (ketiga endpoint notifikasi), dan
  ketiganya memang sudah di-scope ke `$request->user()` di dalam method.

### 21.8 Verifikasi ronde 8

| Verifikasi | Hasil |
|---|---|
| `php artisan test --filter=MbkmWorkflowTest` | **25 passed (306 assertions)** |
| `php artisan test` (regresi penuh) | **156 passed (1038 assertions)** |
| Audit route ↔ controller | **111 route, 0 OPEN, 3 REVIEW** |

### 21.9 Tes baru ronde 8

| Tes | Yang Diverifikasi |
|---|---|
| `dashboard denies a role without mbkm permissions` | `admin_akademik` → **403** pada `dashboard` dan `dashboard/admin` |
| `listings are empty for a role without mbkm permissions` | `participants`/`recognitions`/`withdrawals`/`extensions` semuanya `meta.total = 0` untuk `admin_akademik`, sementara admin melihat 1 |
| `application staff actions are scoped to the study program` | Kaprodi prodi lain **403** pada `verify`/`decide`/`score` (bahkan dengan payload tidak valid), status di DB tetap `submitted`; prodi sendiri **200** |
| `kaprodi can open the applications their list shows` | Prodi lain: list `total = 0` dan `show`/`history` **403**; prodi sendiri: list `total = 1` dan `show`/`history` **200** |
| `kaprodi listings are scoped to their study program` | `participants`/`logbooks`/`issues`/`applications` `total = 0` untuk prodi lain; prodi sendiri `total = 1` |

### 21.10 Bukti bahwa tes regresi ini benar-benar menangkap regresi

Tes yang lulus baik sebelum maupun sesudah perbaikan tidak membuktikan apa pun. Karena itu dua
guard dikembalikan **sementara** ke perilaku lama dan tes dijalankan ulang:

| Guard yang dikembalikan sementara | Hasil |
|---|---|
| `if (!$this->isMbkmManager($request))` di `index()` dihapus | `dashboard denies …` **GAGAL**: `Expected 403 but received 200` |
| `$visible = $this->visibleStudentIds($request)` → `$visible = null` | `listings are empty …` **GAGAL**: `Failed asserting that 1 is identical to 0` |

Keduanya gagal tepat seperti yang diharapkan, lalu guard dikembalikan. Ini memastikan tes tersebut
menguji perbaikan, bukan sekadar menguji bahwa aplikasi bisa dijalankan.

---

## 13. Catatan Lingkungan Pengembangan

- PHP hanya tersedia di `/opt/homebrew/bin/php` (PHP 8.4.11) → perlu `export PATH="/opt/homebrew/bin:$PATH"`
- `vue-tsc` rentan OOM/SIGTERM pada proyek ini → jalankan dengan `NODE_OPTIONS="--max-old-space-size=4096"`
- Lingkungan tes: PHPUnit 12, sqlite `:memory:`, `RefreshDatabase`, `$this->seed()`
- Saat tes berganti identitas: perlu `auth()->forgetGuards()`

---

## 14. Kesimpulan

Modul MBKM **selesai secara end-to-end**, bukan sekadar lapisan DB/CRUD:

- ✅ Rantai dari pendaftaran sampai hasil akademik (KHS/transkrip) benar-benar terhubung
- ✅ Tidak ada duplikasi entitas akademik — semua dipakai ulang
- ✅ KHS/transkrip tetap **derived**, tidak ada tabel bayangan
- ✅ Skala nilai, IPK, dan bobot penilaian mengikuti mekanisme yang sudah ada
- ✅ Otorisasi tidak pernah mempercayai ID dari request
- ✅ Data final terkunci; riwayat tidak pernah dihapus
- ✅ 111 endpoint, 27 tabel, 17 service, 18 controller, 16 halaman frontend
- ✅ 156 tes backend hijau (1038 assertions), frontend type-check bersih dan build sukses

**Delapan ronde audit telah dilakukan.** Total **21 bug nyata** ditemukan dan diperbaiki:

| Ronde | Fokus | Bug | Rincian |
|---|---|---|---|
| 2 | Audit ulang klaim | 2 | otorisasi penilaian mitra; kosakata `assessor_type` ganda |
| 3 | Otorisasi 111 endpoint | 3 | kebocoran `GET /mbkm/documents`; 9 endpoint administratif tanpa scope prodi; urutan cabang `visibleStudentIds()` di kode saya sendiri |
| 4 | State machine + konektivitas frontend | 3 | logbook final bisa dibuka kembali; nilai akhir bisa dibekukan dari penilaian belum lengkap; panel "Komponen & Bobot" selalu kosong karena nama field tidak cocok |
| 5 | Mass assignment, state machine peserta, scope listing | 5 | `PUT participants/{id}` sebagai pintu belakang state machine (tanpa jejak audit); `indexWithdrawals`/`indexExtensions` hanya men-scope mahasiswa; keputusan permohonan tanpa scope & bisa diulang; `GET /mbkm/history` terbuka untuk dosen; `exists:` bukan bukti kepemilikan dokumen |
| 6 | Integritas DB + race condition | 1 | race condition rekognisi: duplikat MK di KHS, batas SKS terlewati, satu rekognisi bisa disetujui dua kali |
| 7 | Operasi hapus & pembekuan nilai | 2 | mahasiswa dapat menghapus nilai yang dicatat pembimbingnya; komponen penilaian tidak terkunci setelah nilai akhir dibekukan |
| 8 | Sweep scoping berbasis cabang peran | 5 | dashboard admin sebagai fallback untuk **semua** peran (termasuk `admin_akademik`); lima listing tanpa default-deny; `verify`/`decide`/`score` tanpa scope per-record (laten, lintas prodi); kaprodi tidak dapat membuka pendaftaran yang muncul di daftarnya |

**Sebelas** di antaranya adalah celah keamanan atau kebocoran data lintas-mahasiswa.

**Empat temuan sengaja tidak diubah** dan dilaporkan apa adanya: dua kekosongan unique index
(bagian 19.3) yang perbaikannya butuh *partial index* yang tidak didukung MySQL dan akan
mematahkan alur sah, satu isu bobot penilaian (bagian 19.3), dan **presensi MBKM yang
self-reported tanpa langkah verifikasi** (bagian 20.4). Keempat temuan tersebut
**memerlukan keputusan pemilik produk** — bukan sesuatu yang pantas saya putuskan sendiri.

### Catatan kejujuran metodologi

Pada ronde 3 saya melaporkan "111 route, **0 endpoint terbuka**" dan mendokumentasikan regex
audit itu sebagai rujukan. Regex tersebut memuat `\$request->user\(\)`, yang **bukan** bukti
otorisasi — method yang hanya mengoper user ke service lolos sebagai "aman". Kesalahan itu
diperbaiki di ronde 5, dan **empat dari lima temuan ronde 5 lolos** audit otomatis karena
memiliki `permission:` middleware atau cek role yang tidak lengkap.

**Kesimpulan metodologis:** skrip audit hanya membuktikan **ada** cek, bukan bahwa cek itu
**lengkap**. Tiga pemeriksaan manual yang kini wajib dijalankan berdampingan dengan skrip
didokumentasikan di bagian 18.7 dan di skill `siakad-module-conventions`.
