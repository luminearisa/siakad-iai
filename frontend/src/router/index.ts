import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import type { Permission, Role } from '@/types/auth'
import AppLayout from '@/layouts/AppLayout.vue'
import AuthLayout from '@/layouts/AuthLayout.vue'
import BlankLayout from '@/layouts/BlankLayout.vue'

const routes: RouteRecordRaw[] = [
  // Auth Routes
  {
    path: '/auth',
    component: AuthLayout,
    children: [
      {
        path: 'login',
        name: 'login',
        component: () => import('@/pages/auth/Login.vue'),
        meta: { guestOnly: true, title: 'Masuk' },
      },
    ],
  },

  // Protected App Routes
  {
    path: '/',
    component: AppLayout,
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        redirect: '/dashboard',
      },
      {
        path: 'dashboard',
        name: 'dashboard',
        component: () => import('@/pages/dashboard/Index.vue'),
        meta: { title: 'Dashboard' },
      },
      {
        path: 'academic',
        name: 'academic',
        component: () => import('@/pages/academic/Index.vue'),
        meta: { title: 'Struktur Akademik', permission: 'faculties.view' },
      },
      {
        path: 'academic/years',
        name: 'academic.years',
        component: () => import('@/pages/academic/years/Index.vue'),
        meta: { title: 'Tahun Ajaran', permission: 'academic_years.view' },
      },
      {
        path: 'academic/semesters',
        name: 'academic.semesters',
        component: () => import('@/pages/academic/semesters/Index.vue'),
        meta: { title: 'Periode Akademik', permission: 'semesters.view' },
      },
      {
        path: 'academic/semesters/create',
        name: 'academic.semesters.create',
        component: () => import('@/pages/academic/semesters/Create.vue'),
        meta: { title: 'Tambah Periode Akademik', permission: 'semesters.create' },
      },
      {
        path: 'academic/semesters/:id/edit',
        name: 'academic.semesters.edit',
        component: () => import('@/pages/academic/semesters/Create.vue'),
        meta: { title: 'Edit Periode Akademik', permission: 'semesters.update' },
      },
      // Unit Kerja
      {
        path: 'academic/institutions',
        name: 'academic.institutions',
        component: () => import('@/pages/academic/institutions/Index.vue'),
        meta: { title: 'Universitas', permission: 'institutions.view' },
      },
      {
        path: 'academic/faculties',
        name: 'academic.faculties',
        component: () => import('@/pages/academic/faculties/Index.vue'),
        meta: { title: 'Fakultas', permission: 'faculties.view' },
      },
      {
        path: 'academic/study-programs',
        name: 'academic.study-programs',
        component: () => import('@/pages/study-programs/Index.vue'),
        meta: { title: 'Program Studi', permission: 'study_programs.view' },
      },
      {
        path: 'study-programs',
        name: 'study-programs',
        component: () => import('@/pages/study-programs/Index.vue'),
        meta: { title: 'Program Studi', permission: 'study_programs.view' },
      },
      {
        path: 'students',
        name: 'students',
        component: () => import('@/pages/students/Index.vue'),
        meta: { title: 'Data Mahasiswa', permission: 'students.view' },
      },
      {
        path: 'students/create',
        name: 'students.create',
        component: () => import('@/pages/students/Create.vue'),
        meta: { title: 'Tambah Mahasiswa', permission: 'students.create' },
      },
      {
        path: 'students/:id',
        name: 'students.show',
        component: () => import('@/pages/students/Show.vue'),
        meta: { title: 'Detail Mahasiswa', permission: 'students.view' },
      },
      {
        path: 'students/:id/edit',
        name: 'students.edit',
        component: () => import('@/pages/students/Edit.vue'),
        meta: { title: 'Edit Mahasiswa', permission: 'students.update' },
      },
      {
        path: 'lecturers',
        name: 'lecturers',
        component: () => import('@/pages/lecturers/Index.vue'),
        meta: { title: 'Data Dosen', permission: 'lecturers.view' },
      },
      {
        path: 'lecturers/create',
        name: 'lecturers.create',
        component: () => import('@/pages/lecturers/Create.vue'),
        meta: { title: 'Tambah Dosen', permission: 'lecturers.create' },
      },
      {
        path: 'lecturers/:id',
        name: 'lecturers.show',
        component: () => import('@/pages/lecturers/Show.vue'),
        meta: { title: 'Detail Dosen', permission: 'lecturers.view' },
      },
      {
        path: 'lecturers/:id/edit',
        name: 'lecturers.edit',
        component: () => import('@/pages/lecturers/Edit.vue'),
        meta: { title: 'Edit Dosen', permission: 'lecturers.update' },
      },
      {
        path: 'courses',
        name: 'courses',
        component: () => import('@/pages/courses/Index.vue'),
        meta: { title: 'Mata Kuliah', permission: 'courses.view' },
      },
      {
        path: 'courses/create',
        name: 'courses.create',
        component: () => import('@/pages/courses/Create.vue'),
        meta: { title: 'Tambah Mata Kuliah', permission: 'courses.create' },
      },
      {
        path: 'courses/types',
        name: 'courses.types',
        component: () => import('@/pages/courses/types/Index.vue'),
        meta: { title: 'Jenis Mata Kuliah', permission: 'courses.view' },
      },
      {
        path: 'courses/groups',
        name: 'courses.groups',
        component: () => import('@/pages/courses/groups/Index.vue'),
        meta: { title: 'Kelompok Mata Kuliah', permission: 'courses.view' },
      },
      {
        path: 'courses/survey-templates',
        name: 'courses.survey-templates',
        component: () => import('@/pages/courses/survey-templates/Index.vue'),
        meta: { title: 'Template Survey Evaluasi', permission: 'courses.view' },
      },
      {
        path: 'courses/survey-templates/:id',
        name: 'courses.survey-templates.show',
        component: () => import('@/pages/courses/survey-templates/Show.vue'),
        meta: { title: 'Builder Template Survey', permission: 'courses.view' },
      },
      {
        path: 'courses/:id',
        name: 'courses.show',
        component: () => import('@/pages/courses/Show.vue'),
        meta: { title: 'Detail Mata Kuliah', permission: 'courses.view' },
      },
      {
        path: 'courses/:id/edit',
        name: 'courses.edit',
        component: () => import('@/pages/courses/Edit.vue'),
        meta: { title: 'Edit Mata Kuliah', permission: 'courses.update' },
      },
      // Capaian Lulusan (OBE)
      {
        path: 'outcomes/pl',
        name: 'outcomes.pl',
        component: () => import('@/pages/outcomes/pl/Index.vue'),
        meta: { title: 'Profil Lulusan (PL)', permission: 'curricula.view' },
      },
      {
        path: 'outcomes/pl/create',
        name: 'outcomes.pl.create',
        component: () => import('@/pages/outcomes/pl/Create.vue'),
        meta: { title: 'Tambah Profil Lulusan', permission: 'curricula.create' },
      },
      {
        path: 'outcomes/pl/:id/edit',
        name: 'outcomes.pl.edit',
        component: () => import('@/pages/outcomes/pl/Create.vue'),
        meta: { title: 'Edit Profil Lulusan', permission: 'curricula.update' },
      },
      {
        path: 'outcomes/cpl',
        name: 'outcomes.cpl',
        component: () => import('@/pages/outcomes/cpl/Index.vue'),
        meta: { title: 'Capaian Pembelajaran Lulusan (CPL)', permission: 'curricula.view' },
      },
      {
        path: 'outcomes/cpl/create',
        name: 'outcomes.cpl.create',
        component: () => import('@/pages/outcomes/cpl/Create.vue'),
        meta: { title: 'Tambah CPL', permission: 'curricula.create' },
      },
      {
        path: 'outcomes/cpl/:id/edit',
        name: 'outcomes.cpl.edit',
        component: () => import('@/pages/outcomes/cpl/Create.vue'),
        meta: { title: 'Edit CPL', permission: 'curricula.update' },
      },
      {
        path: 'outcomes/cpmk',
        name: 'outcomes.cpmk',
        component: () => import('@/pages/outcomes/cpmk/Index.vue'),
        meta: { title: 'Capaian Pembelajaran Mata Kuliah (CPMK)', permission: 'curricula.view' },
      },
      {
        path: 'outcomes/cpmk/create',
        name: 'outcomes.cpmk.create',
        component: () => import('@/pages/outcomes/cpmk/Create.vue'),
        meta: { title: 'Tambah CPMK', permission: 'curricula.create' },
      },
      {
        path: 'outcomes/cpmk/:id/edit',
        name: 'outcomes.cpmk.edit',
        component: () => import('@/pages/outcomes/cpmk/Create.vue'),
        meta: { title: 'Edit CPMK', permission: 'curricula.update' },
      },
      {
        path: 'outcomes/sub-cpmk',
        name: 'outcomes.sub-cpmk',
        component: () => import('@/pages/outcomes/sub-cpmk/Index.vue'),
        meta: { title: 'Sub-CPMK', permission: 'curricula.view' },
      },
      {
        path: 'outcomes/sub-cpmk/create',
        name: 'outcomes.sub-cpmk.create',
        component: () => import('@/pages/outcomes/sub-cpmk/Create.vue'),
        meta: { title: 'Tambah Sub-CPMK', permission: 'curricula.create' },
      },
      {
        path: 'outcomes/sub-cpmk/:id/edit',
        name: 'outcomes.sub-cpmk.edit',
        component: () => import('@/pages/outcomes/sub-cpmk/Create.vue'),
        meta: { title: 'Edit Sub-CPMK', permission: 'curricula.update' },
      },
      // Master Kurikulum
      {
        path: 'curriculum/years',
        name: 'curriculum.years',
        component: () => import('@/pages/curriculum/years/Index.vue'),
        meta: { title: 'Tahun Kurikulum', permission: 'curricula.view' },
      },
      {
        path: 'curriculum/credit-limits',
        name: 'curriculum.credit-limits',
        component: () => import('@/pages/curriculum/credit-limits/Index.vue'),
        meta: { title: 'Batas SKS', permission: 'curricula.view' },
      },
      {
        path: 'curriculum/grade-scales',
        name: 'curriculum.grade-scales',
        component: () => import('@/pages/curriculum/grade-scales/Index.vue'),
        meta: { title: 'Skala Nilai', permission: 'curricula.view' },
      },
      {
        path: 'curriculum/grade-scales/create',
        name: 'curriculum.grade-scales.create',
        component: () => import('@/pages/curriculum/grade-scales/Create.vue'),
        meta: { title: 'Tambah Skala Nilai', permission: 'curricula.create' },
      },
      {
        path: 'curriculum/grade-scales/:id/edit',
        name: 'curriculum.grade-scales.edit',
        component: () => import('@/pages/curriculum/grade-scales/Create.vue'),
        meta: { title: 'Edit Skala Nilai', permission: 'curricula.update' },
      },
      {
        path: 'curriculum/study-programs',
        name: 'curriculum.study-programs',
        component: () => import('@/pages/curriculum/study-programs/Index.vue'),
        meta: { title: 'Kurikulum Program Studi', permission: 'curricula.view' },
      },
      {
        path: 'curriculum/study-programs/:id',
        name: 'curriculum.study-programs.show',
        component: () => import('@/pages/curriculum/study-programs/Show.vue'),
        meta: { title: 'Detail Kurikulum Program Studi', permission: 'curricula.view' },
      },
      {
        path: 'curriculum',
        name: 'curriculum',
        component: () => import('@/pages/curriculum/Index.vue'),
        meta: { title: 'Kurikulum', permission: 'curricula.view' },
      },
      {
        path: 'curriculum/create',
        name: 'curriculum.create',
        component: () => import('@/pages/curriculum/Create.vue'),
        meta: { title: 'Tambah Kurikulum', permission: 'curricula.create' },
      },
      {
        path: 'curriculum/:id',
        name: 'curriculum.show',
        component: () => import('@/pages/curriculum/Show.vue'),
        meta: { title: 'Detail Kurikulum', permission: 'curricula.view' },
      },
      {
        path: 'curriculum/:id/edit',
        name: 'curriculum.edit',
        component: () => import('@/pages/curriculum/Edit.vue'),
        meta: { title: 'Edit Kurikulum', permission: 'curricula.update' },
      },
      // Plural aliases (curricula/...)
      {
        path: 'curricula',
        redirect: '/curriculum/study-programs',
      },
      {
        path: 'curricula/create',
        name: 'curricula.create',
        component: () => import('@/pages/curriculum/Create.vue'),
        meta: { title: 'Tambah Kurikulum', permission: 'curricula.create' },
      },
      {
        path: 'curricula/:id',
        name: 'curricula.show',
        component: () => import('@/pages/curriculum/study-programs/Show.vue'),
        meta: { title: 'Detail Kurikulum Program Studi', permission: 'curricula.view' },
      },
      {
        path: 'curricula/:id/edit',
        name: 'curricula.edit',
        component: () => import('@/pages/curriculum/Edit.vue'),
        meta: { title: 'Edit Kurikulum', permission: 'curricula.update' },
      },
      // Master Perkuliahan
      {
        path: 'classes/session-types',
        name: 'classes.session-types',
        component: () => import('@/pages/classes/session-types/Index.vue'),
        meta: { title: 'Jenis Sesi Perkuliahan', permission: 'classes.view' },
      },
      {
        path: 'classes/student-groups',
        name: 'classes.student-groups',
        component: () => import('@/pages/classes/student-groups/Index.vue'),
        meta: { title: 'Kelompok Mahasiswa', permission: 'classes.view' },
      },
      {
        path: 'classes/programs',
        name: 'classes.programs',
        component: () => import('@/pages/classes/programs/Index.vue'),
        meta: { title: 'Program Kuliah', permission: 'classes.view' },
      },
      {
        path: 'classes/grading-components',
        name: 'classes.grading-components',
        component: () => import('@/pages/classes/grading-components/Index.vue'),
        meta: { title: 'Unsur Nilai', permission: 'classes.view' },
      },
      {
        path: 'classes',
        name: 'classes',
        component: () => import('@/pages/classes/Index.vue'),
        meta: { title: 'Kelas Perkuliahan', permission: 'classes.view' },
      },
      {
        path: 'classes/create',
        name: 'classes.create',
        component: () => import('@/pages/classes/Create.vue'),
        meta: { title: 'Buka Kelas Baru', permission: 'classes.create' },
      },
      {
        path: 'classes/:id',
        name: 'classes.show',
        component: () => import('@/pages/classes/Show.vue'),
        meta: { title: 'Detail Kelas Perkuliahan', permission: 'classes.view' },
      },
      {
        path: 'classes/:id/edit',
        name: 'classes.edit',
        component: () => import('@/pages/classes/Edit.vue'),
        meta: { title: 'Edit Kelas Perkuliahan', permission: 'classes.update' },
      },
      // Konfigurasi
      {
        path: 'settings/study-programs',
        name: 'settings.study-programs',
        component: () => import('@/pages/settings/study-programs/Index.vue'),
        meta: { title: 'Pengaturan Program Studi', permission: 'study_programs.view' },
      },
      {
        path: 'settings/degree-levels',
        name: 'settings.degree-levels',
        component: () => import('@/pages/settings/degree-levels/Index.vue'),
        meta: { title: 'Jenjang Pendidikan', permission: 'study_programs.view' },
      },
      {
        path: 'settings/ktm',
        name: 'settings.ktm',
        component: () => import('@/pages/settings/ktm/Index.vue'),
        meta: { title: 'Pengaturan KTM', permission: 'students.view' },
      },
      // Sarana dan Prasarana
      {
        path: 'facilities/campuses',
        name: 'facilities.campuses',
        component: () => import('@/pages/facilities/campuses/Index.vue'),
        meta: { title: 'Kampus', permission: 'rooms.view' },
      },
      {
        path: 'facilities/buildings',
        name: 'facilities.buildings',
        component: () => import('@/pages/facilities/buildings/Index.vue'),
        meta: { title: 'Gedung', permission: 'rooms.view' },
      },
      {
        path: 'rooms',
        name: 'rooms',
        component: () => import('@/pages/rooms/Index.vue'),
        meta: { title: 'Ruang', permission: 'rooms.view' },
      },
      {
        path: 'rooms/create',
        name: 'rooms.create',
        component: () => import('@/pages/rooms/Create.vue'),
        meta: { title: 'Tambah Ruangan', permission: 'rooms.create' },
      },
      {
        path: 'rooms/:id',
        name: 'rooms.show',
        component: () => import('@/pages/rooms/Show.vue'),
        meta: { title: 'Detail Ruangan', permission: 'rooms.view' },
      },
      {
        path: 'rooms/:id/edit',
        name: 'rooms.edit',
        component: () => import('@/pages/rooms/Edit.vue'),
        meta: { title: 'Edit Ruangan', permission: 'rooms.update' },
      },
      {
        path: 'schedules',
        name: 'schedules',
        component: () => import('@/pages/schedules/Index.vue'),
        meta: { title: 'Jadwal Kuliah', permission: 'schedules.view' },
      },
      {
        path: 'schedules/create',
        name: 'schedules.create',
        component: () => import('@/pages/schedules/Create.vue'),
        meta: { title: 'Tambah Jadwal Kuliah', permission: 'schedules.create' },
      },
      {
        path: 'schedules/:id',
        name: 'schedules.show',
        component: () => import('@/pages/schedules/Show.vue'),
        meta: { title: 'Detail Jadwal Kuliah', permission: 'schedules.view' },
      },
      {
        path: 'schedules/:id/edit',
        name: 'schedules.edit',
        component: () => import('@/pages/schedules/Edit.vue'),
        meta: { title: 'Edit Jadwal Kuliah', permission: 'schedules.update' },
      },
      {
        path: 'exams',
        name: 'exams',
        component: () => import('@/pages/exams/Index.vue'),
        meta: { title: 'Jadwal Ujian', permission: 'schedules.view' },
      },
      {
        path: 'exams/schedules',
        name: 'exams.schedules',
        component: () => import('@/pages/exams/Index.vue'),
        meta: { title: 'Jadwal Ujian', permission: 'schedules.view' },
      },
      {
        path: 'perkuliahan/jadwal-ujian',
        name: 'perkuliahan.jadwal-ujian',
        component: () => import('@/pages/exams/Index.vue'),
        meta: { title: 'Jadwal Ujian', permission: 'schedules.view' },
      },
      // Tugas Akhir
      {
        path: 'thesis',
        name: 'thesis',
        component: () => import('@/pages/thesis/Index.vue'),
        meta: { title: 'Tugas Akhir' },
      },
      {
        path: 'thesis/create',
        name: 'thesis.create',
        component: () => import('@/pages/thesis/Create.vue'),
        meta: { title: 'Tambah Tugas Akhir' },
      },
      {
        path: 'thesis/:id/edit',
        name: 'thesis.edit',
        component: () => import('@/pages/thesis/Edit.vue'),
        meta: { title: 'Edit Tugas Akhir' },
      },
      {
        path: 'aktivitas/tugas-akhir',
        name: 'aktivitas.tugas-akhir',
        component: () => import('@/pages/thesis/Index.vue'),
        meta: { title: 'Tugas Akhir' },
      },
      // Yudisium Submodules
      {
        path: 'graduation/yudisium',
        redirect: '/graduation/yudisium/periods',
      },
      {
        path: 'graduation/yudisium/periods',
        name: 'graduation.yudisium.periods',
        component: () => import('@/pages/yudisium/periods/Index.vue'),
        meta: { title: 'Periode Yudisium' },
      },
      {
        path: 'graduation/yudisium/participants',
        name: 'graduation.yudisium.participants',
        component: () => import('@/pages/yudisium/participants/Index.vue'),
        meta: { title: 'Peserta Yudisium' },
      },
      {
        path: 'graduation/yudisium/approvals',
        name: 'graduation.yudisium.approvals',
        component: () => import('@/pages/yudisium/approvals/Index.vue'),
        meta: { title: 'Persetujuan Yudisium' },
      },
      {
        path: 'graduation/yudisium/eligible',
        name: 'graduation.yudisium.eligible',
        component: () => import('@/pages/yudisium/eligible/Index.vue'),
        meta: { title: 'Eligible Yudisium' },
      },
      {
        path: 'graduation/yudisium/requirements',
        name: 'graduation.yudisium.requirements',
        component: () => import('@/pages/yudisium/requirements/Index.vue'),
        meta: { title: 'Syarat Yudisium' },
      },
      {
        path: 'enrollments',
        name: 'enrollments',
        component: () => import('@/pages/enrollments/Index.vue'),
        meta: { title: 'Monitoring Perwalian', permission: 'enrollments.view' },
      },
      {
        path: 'perwalian/monitoring-krs',
        name: 'perwalian.monitoring-krs',
        component: () => import('@/pages/enrollments/Index.vue'),
        meta: { title: 'Monitoring Perwalian', permission: 'enrollments.view' },
      },
      {
        path: 'enrollments/packages',
        name: 'enrollments.packages',
        component: () => import('@/pages/enrollments/packages/Index.vue'),
        meta: { title: 'Paket KRS', permission: 'enrollments.view' },
      },
      {
        path: 'perwalian/paket-krs',
        name: 'perwalian.paket-krs',
        component: () => import('@/pages/enrollments/packages/Index.vue'),
        meta: { title: 'Paket KRS', permission: 'enrollments.view' },
      },
      {
        path: 'enrollments/create',
        name: 'enrollments.create',
        component: () => import('@/pages/enrollments/Create.vue'),
        meta: { title: 'Buka KRS Baru', permission: 'enrollments.view' },
      },
      {
        path: 'enrollments/:id',
        name: 'enrollments.show',
        component: () => import('@/pages/enrollments/Show.vue'),
        meta: { title: 'Detail Kartu Rencana Studi (KRS)', permission: 'enrollments.view' },
      },
      {
        path: 'perwalian/monitoring-krs/:id',
        name: 'perwalian.monitoring-krs.show',
        component: () => import('@/pages/enrollments/Show.vue'),
        meta: { title: 'Detail Kartu Rencana Studi (KRS)', permission: 'enrollments.view' },
      },
      {
        path: 'advising',
        name: 'advising',
        component: () => import('@/pages/advising/Index.vue'),
        meta: { title: 'Distribusi Pembimbing Akademik', permission: 'advising.view' },
      },
      {
        path: 'advising/distribution',
        name: 'advising.distribution',
        component: () => import('@/pages/advising/Index.vue'),
        meta: { title: 'Distribusi Pembimbing Akademik', permission: 'advising.view' },
      },
      {
        path: 'advising/quotas',
        name: 'advising.quotas',
        component: () => import('@/pages/advising/quotas/Index.vue'),
        meta: { title: 'Kuota Pembimbing Akademik', permission: 'advising.view' },
      },
      {
        path: 'advising/create',
        name: 'advising.create',
        component: () => import('@/pages/advising/Create.vue'),
        meta: { title: 'Penugasan Dosen PA', permission: 'advising.assign' },
      },
      {
        path: 'advising/:id',
        name: 'advising.show',
        component: () => import('@/pages/advising/Show.vue'),
        meta: { title: 'Workspace Dosen PA', permission: 'advising.view' },
      },
      {
        path: 'advising/:id/edit',
        name: 'advising.edit',
        component: () => import('@/pages/advising/Edit.vue'),
        meta: { title: 'Edit Penugasan PA', permission: 'advising.assign' },
      },
      {
        path: 'attendance',
        name: 'attendance',
        component: () => import('@/pages/sessions/Index.vue'),
        meta: { title: 'Monitoring Sesi Perkuliahan', permission: 'attendance.view' },
      },
      {
        path: 'sessions',
        name: 'sessions',
        component: () => import('@/pages/sessions/Index.vue'),
        meta: { title: 'Monitoring Sesi Perkuliahan', permission: 'attendance.view' },
      },
      {
        path: 'perkuliahan/monitoring-sesi',
        name: 'perkuliahan.monitoring-sesi',
        component: () => import('@/pages/sessions/Index.vue'),
        meta: { title: 'Monitoring Sesi Perkuliahan', permission: 'attendance.view' },
      },
      {
        path: 'attendance/classes/:id',
        name: 'attendance.class',
        component: () => import('@/pages/attendance/ClassAttendance.vue'),
        meta: { title: 'Presensi & BAP Kelas', permission: 'attendance.view' },
      },
      {
        path: 'attendance/my',
        name: 'attendance.my',
        component: () => import('@/pages/attendance/MyAttendance.vue'),
        meta: { title: 'Kehadiran Saya', roles: ['mahasiswa'] },
      },
      // Student Portal Dedicated Routes
      {
        path: 'student/profile',
        name: 'student.profile',
        component: () => import('@/pages/student/Profile.vue'),
        meta: { title: 'Profil Saya', roles: ['mahasiswa'] },
      },
      {
        path: 'khs',
        name: 'student.khs',
        component: () => import('@/pages/student/Khs.vue'),
        meta: { title: 'Kartu Hasil Studi (KHS)', roles: ['mahasiswa'] },
      },
      {
        path: 'my-schedule',
        name: 'student.schedule',
        component: () => import('@/pages/student/MySchedule.vue'),
        meta: { title: 'Jadwal Kuliah Saya', roles: ['mahasiswa'] },
      },
    ],
  },

  // Error Routes
  {
    path: '/403',
    name: 'forbidden',
    component: BlankLayout,
    children: [
      {
        path: '',
        component: () => import('@/pages/errors/403.vue'),
        meta: { title: 'Akses Ditolak' },
      },
    ],
  },
  {
    path: '/500',
    name: 'server-error',
    component: BlankLayout,
    children: [
      {
        path: '',
        component: () => import('@/pages/errors/500.vue'),
        meta: { title: 'Gangguan Server' },
      },
    ],
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: BlankLayout,
    children: [
      {
        path: '',
        component: () => import('@/pages/errors/404.vue'),
        meta: { title: 'Halaman Tidak Ditemukan' },
      },
    ],
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior(_to, _from, savedPosition) {
    if (savedPosition) {
      return savedPosition
    }
    return { top: 0 }
  },
})

// Navigation Guards
router.beforeEach(async (to, _from, next) => {
  const authStore = useAuthStore()

  // Initialize auth state if token exists and not initialized
  if (!authStore.isInitialized) {
    await authStore.fetchMe()
  }

  // Update document title
  if (to.meta.title) {
    document.title = `${to.meta.title} — SIAKAD`
  }

  const isAuthenticated = authStore.isAuthenticated

  // 1. Guest only routes (e.g. login)
  if (to.matched.some(record => record.meta.guestOnly)) {
    if (isAuthenticated) {
      return next('/dashboard')
    }
    return next()
  }

  // 2. Protected routes requiring authentication
  if (to.matched.some(record => record.meta.requiresAuth)) {
    if (!isAuthenticated) {
      return next({
        path: '/auth/login',
        query: { redirect: to.fullPath },
      })
    }

    // 3. Permission Checks
    const requiredPermission = to.meta.permission as string | undefined
    if (requiredPermission && !authStore.isSuperAdmin) {
      const hasPermission = authStore.permissions.some((p: Permission) => p.name === requiredPermission)
      if (!hasPermission) {
        return next('/403')
      }
    }

    // 4. Role Checks
    const requiredRoles = to.meta.roles as string[] | undefined
    const requiredRole = to.meta.role as string | undefined
    if (requiredRoles && requiredRoles.length > 0 && !authStore.isSuperAdmin) {
      const hasRole = requiredRoles.some((roleName: string) =>
        authStore.roles.some((r: Role) => r.name === roleName)
      )
      if (!hasRole) {
        return next('/403')
      }
    } else if (requiredRole && !authStore.isSuperAdmin) {
      const hasRole = authStore.roles.some((r: Role) => r.name === requiredRole)
      if (!hasRole) {
        return next('/403')
      }
    }

    return next()
  }

  next()
})

// Listen for global unauthorized events from API client
window.addEventListener('auth:unauthorized', () => {
  if (router.currentRoute.value.path !== '/auth/login') {
    router.push({
      path: '/auth/login',
      query: { redirect: router.currentRoute.value.fullPath },
    })
  }
})

export default router
