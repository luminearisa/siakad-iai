# MEMORY.md — Konvensi Proyek `siakad_iai`

Catatan jangka panjang. Detail operasional ada di skill `.workbuddy-ai/skills/siakad-module-conventions/`.

## Stack & lingkungan
- Backend Laravel 13 modular monolith di `backend/modules/*`; route di `modules/*/Routes/api.php`, prefix `api/v1`. Frontend Vue 3 + Vite + TS + Tailwind + Pinia di `frontend`.
- Dev: backend `http://localhost:8001`, frontend `http://localhost:5173`.
- PHP hanya di `/opt/homebrew/bin/php` → **selalu** `export PATH="/opt/homebrew/bin:$PATH"`.
- `vue-tsc` rentan OOM → `NODE_OPTIONS="--max-old-space-size=4096"`.
- Tes: PHPUnit 12, sqlite `:memory:`, `RefreshDatabase`, `$this->seed()`. Berganti identitas dalam satu tes → `auth()->forgetGuards()`.
- **SQLite mengabaikan `lockForUpdate()`** → race condition tidak akan tertangkap tes; verifikasi penjaganya, bukan race-nya.

## Otorisasi (sumber bug terbanyak)
- **Permission hanya diberikan lewat ROLE.** Tidak ada pivot user↔permission; `User::givePermissionTo()` **tidak ada**. `super_admin` selalu lolos.
- `admin_akademik` menerima permission **per group** dan **grup `mbkm` tidak termasuk** → secara bawaan hanya `super_admin` yang memegang permission `mbkm.*` tingkat staf.
- Role `dosen` = sisi *view* MBKM saja. Role `mahasiswa` **tidak punya satu pun** permission `mbkm.*` (endpoint mahasiswa bergantung pada cek kepemilikan di dalam method).
- **Cabang peran gagal-terbuka.** `if (isMbkmStudent) … elseif (isMbkmLecturerOnly) …` tidak men-scope peran lain — terutama `admin_akademik` dan **kaprodi** (kaprodi juga `dosen`). Pakai `visibleStudentIds()`: `null` = tanpa batas, `[]` = **nol baris** (default-deny). Cabang `mbkm.manage_study_program` harus sebelum cabang dosen biasa.
- **Middleware `permission:` bukan bukti scope.** Permission bersifat module-wide.
- Scope listing dan scope record harus **predikat yang sama** (`index()` ↔ `mayAccessApplication()`), kalau tidak daftar menampilkan baris yang detailnya 403.
- Route `GET participants` & `GET recognitions` **tanpa middleware permission sama sekali** → satu-satunya penjaga ada di controller.

## Integritas data
- **KHS & transkrip tidak punya tabel.** Diturunkan `StudentPortalService::getStudentKHS()` dari `student_enrollments` → `student_enrollment_items` → `academic_classes` → `GradeCalculationService`. Fitur yang ingin "muncul di KHS" harus **menulis ke tabel akademik**.
- Jangan hardcode skala nilai/IPK — pakai `GradeScale`/`GradeScaleItem` + `GradeCalculationService::convertScoreToGrade()`.
- **Jangan percaya `student_id`/`participant_id`/`user_id` dari request.**
- `exists:tabel,id` **bukan** bukti kepemilikan.
- Baca-lalu-tulis (cek duplikat, akumulasi batas) wajib `DB::transaction` **+ lock baris induk yang pasti ada**.
- Status "final" harus mengunci **semua** sumber datanya, bukan hanya kolom ringkasannya.

## Cara kerja yang diminta user
- **Jawab pertanyaan dulu, baru sentuh kode.** Jangan melebar dari yang diminta.
- Selesai → **langsung lapor** (file apa + apa yang berubah). Jangan verifikasi berlapis-lapis.
- Perbaiki **akar masalah**, bukan gejala. Sebutkan temuan sampingan meski tidak diminta.
- **Dilarang mutlak memakai `git`** terhadap berkas user (pernah menghapus pekerjaan permanen). Kembalikan perubahan lewat Edit/Write.
- Bahasa: Indonesia; istilah teknis tetap Inggris.
