# CHECKPOINT — PHASE 7: COURSE & CURRICULUM MANAGEMENT FRONTEND

**Tanggal Selesai**: 2026-08-21  
**Status**: **COMPLETED & VERIFIED (ALL TESTS & CHECKS PASS)**

---

## 1. Ringkasan Phase 7

Phase 7 telah membangun modul **Course Management (Mata Kuliah)** dan **Curriculum Management (Kurikulum)** secara lengkap, production-ready, modular, responsive, dan terintegrasi langsung dengan Laravel REST API (`/api/v1/courses`, `/api/v1/curricula`, `/api/v1/curriculum-semesters`).

```text
frontend/src/pages/courses/
├── Index.vue                         # Direktori mata kuliah dengan pencarian, filter, sorting & pagination
├── Create.vue                        # Registrasi mata kuliah baru (SKS teori/praktik, tipe, kategori)
├── Show.vue                          # Profil detail mata kuliah & relasi prasyarat (prerequisites/dependents)
├── Edit.vue                          # Form pembaruan konfigurasi mata kuliah
└── components/
    ├── CourseTypeBadge.vue           # Badge semantik tipe perkuliahan (Teori, Praktikum, Mixed)
    ├── CourseFilters.vue             # Filter bar (Pencarian kode/nama, Tipe, Kategori, Status, Bobot SKS)
    ├── CourseHeader.vue              # Header informasi ringkas mata kuliah & action buttons
    ├── CourseForm.vue                # Form modular 3-section untuk Create & Edit dengan 422 error mapping
    ├── CoursePrerequisiteList.vue    # Daftar prasyarat & mata kuliah turunan (dependent courses)
    └── AddPrerequisiteModal.vue      # Modal multi-select prasyarat dan nilai minimal kelulusan

frontend/src/pages/curriculum/
├── Index.vue                         # Direktori kurikulum program studi dengan filter & pagination
├── Create.vue                        # Pembuatan dokumen kurikulum baru
├── Show.vue                          # Detail kurikulum dengan tab Informasi, Struktur, dan Distribusi Mata Kuliah
├── Edit.vue                          # Pembaruan dokumen kurikulum
├── components/
    ├── CurriculumStatusBadge.vue     # Badge status kurikulum (Draft, Aktif, Non-Aktif, Diarsipkan)
    ├── CurriculumFilters.vue         # Filter prodi, status, tahun mulai/selesai
    ├── CurriculumHeader.vue          # Header ringkasan kurikulum, total SKS & tombol aksi siklus
    ├── CurriculumForm.vue            # Form kurikulum (Program studi, kode, nama, versi, periode berlaku)
    ├── CurriculumSubjectTable.vue    # Tabel distribusi mata kuliah per semester dengan SKS override & sifat
    ├── AddCurriculumSemesterModal.vue# Modal penambahan paket semester kurikulum
    └── AddCurriculumSubjectModal.vue # Modal distribusi mata kuliah ke semester kurikulum
└── tabs/
    ├── CurriculumOverviewTab.vue     # Tab dokumen & identitas kurikulum serta periode keberlakuan
    ├── CurriculumStructureTab.vue    # Tab ringkasan kartu struktur semester & beban SKS
    └── CurriculumSubjectsTab.vue     # Tab pemetaan mata kuliah lengkap per semester
```

---

## 2. Integrasi Backend & Fitur yang Diimplementasikan

| Fitur / Submodul | Endpoint Backend | Metode & Keterangan |
| :--- | :--- | :--- |
| **Course Directory** | `GET /api/v1/courses` | Filterable (`type`, `category`, `status`, `credits`), Searchable (`name`, `code`, `short_name`), Server-side sorting & pagination. |
| **Course Detail & CRUD** | `GET, POST, PUT, DELETE /api/v1/courses` | Full validation, 422 error mapping, computed total SKS. |
| **Prerequisites Management** | `GET, POST /api/v1/courses/{id}/prerequisites` | Pemetaan multi-prasyarat dengan `minimum_grade` dan deteksi relasi dependents. |
| **Curriculum Directory** | `GET /api/v1/curricula` | Filterable (`status`, `study_program_id`, `start_year`, `end_year`), Searchable (`name`, `code`, `version`). |
| **Curriculum Detail & CRUD** | `GET, POST, PUT, DELETE /api/v1/curricula` | Eager loaded `studyProgram.faculty`, `semesters.subjects.course`. |
| **Curriculum Lifecycle** | `PATCH /api/v1/curricula/{id}/activate`, `PATCH /api/v1/curricula/{id}/archive` | Transisi status kurikulum dengan modal konfirmasi. |
| **Semester Structure** | `GET, POST /api/v1/curricula/{id}/semesters` | Pembuatan paket semester bertingkat. |
| **Subject Distribution** | `GET, POST, DELETE /api/v1/curriculum-semesters/{semester}/subjects` | Distribusi mata kuliah ke semester (wajib/pilihan, override SKS, nilai minimal). |

---

## 3. Hasil Pengujian & Verifikasi

1. **Automated Unit & Component Testing (Vitest)**:
   ```bash
   npm test
   ```
   * **Hasil**: 35 tests passed (100% pass) mencakup Student, Lecturer, Course, dan Curriculum modules.
2. **Type-Safety & Production Build**:
   ```bash
   npm run build
   ```
   * **Hasil**: `vue-tsc -b && vite build` **PASS (0 Errors, 2.28s build time)**.
3. **Backend Integration Testing**:
   ```bash
   php artisan test
   ```
   * **Hasil**: 68 tests (424 assertions) **PASS (100%)**.
