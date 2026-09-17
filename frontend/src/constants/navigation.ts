export interface NavigationSubItem {
  id: string
  label: string
  to: string
  permission?: string | null
  roles?: string[]
  badge?: string | number
}

export interface NavigationItem {
  id: string
  label: string
  to?: string
  icon: string
  permission?: string | null
  roles?: string[]
  badge?: string | number
  children?: NavigationItem[]
}

export interface NavigationSection {
  title?: string
  roles?: string[]
  items: NavigationItem[]
}

/**
 * Struktur menu sidebar.
 *
 * Aturan penyusunan:
 * - Urutan section: Portal (per role) -> Operasional Akademik -> Perwalian -> Data Master -> Unit & Sarana -> Pengaturan.
 * - Maksimal 2 tingkat di bawah section (section -> grup -> item) agar mudah dipindai.
 * - Satu halaman hanya boleh muncul satu kali. Halaman yang sama tapi punya
 *   beberapa URL (mis. /attendance, /sessions, /perkuliahan/monitoring-sesi
 *   semuanya merender pages/sessions/Index.vue) hanya didaftarkan sekali.
 * - Section diberi `roles`, item operasional diberi `permission` sebagai lapis kedua.
 */

const ADMIN_ROLES = ['super_admin', 'admin_akademik']

export const NAVIGATION_CONFIG: NavigationSection[] = [
  {
    items: [
      {
        id: 'dashboard',
        label: 'Dashboard',
        to: '/dashboard',
        icon: 'LayoutDashboard',
      },
    ],
  },
  {
    title: 'Portal Mahasiswa',
    roles: ['mahasiswa'],
    items: [
      {
        id: 'student-profile',
        label: 'Profil Saya',
        to: '/student/profile',
        icon: 'User',
        roles: ['mahasiswa'],
      },
      {
        id: 'student-krs',
        label: 'Rencana Studi (KRS)',
        to: '/enrollments',
        icon: 'FileSpreadsheet',
        roles: ['mahasiswa'],
      },
      {
        id: 'student-khs',
        label: 'Hasil Studi (KHS)',
        to: '/khs',
        icon: 'Award',
        roles: ['mahasiswa'],
      },
      {
        id: 'student-schedule',
        label: 'Jadwal Kuliah Saya',
        to: '/my-schedule',
        icon: 'Calendar',
        roles: ['mahasiswa'],
      },
      {
        id: 'student-attendance',
        label: 'Kehadiran Saya',
        to: '/attendance/my',
        icon: 'CalendarCheck',
        roles: ['mahasiswa'],
      },
      {
        id: 'student-advising',
        label: 'Bimbingan PA',
        to: '/advising',
        icon: 'UserCheck',
        roles: ['mahasiswa'],
      },
    ],
  },
  {
    title: 'Portal Dosen',
    roles: ['dosen'],
    items: [
      {
        id: 'lecturer-attendance',
        label: 'Presensi & BAP Kuliah',
        to: '/attendance',
        icon: 'CalendarCheck',
        permission: 'attendance.view',
        roles: ['dosen'],
      },
      {
        id: 'lecturer-classes',
        label: 'Kelas Pengampu',
        to: '/classes',
        icon: 'School',
        permission: 'classes.view',
        roles: ['dosen'],
      },
      {
        id: 'lecturer-schedules',
        label: 'Jadwal Mengajar',
        to: '/schedules',
        icon: 'Calendar',
        permission: 'schedules.view',
        roles: ['dosen'],
      },
      {
        id: 'lecturer-advising',
        label: 'Bimbingan PA & KRS',
        to: '/advising',
        icon: 'UserCheck',
        permission: 'advising.view',
        roles: ['dosen'],
      },
      {
        id: 'lecturer-enrollments',
        label: 'Rekapitulasi KRS',
        to: '/enrollments',
        icon: 'FileSpreadsheet',
        permission: 'enrollments.view',
        roles: ['dosen'],
      },
      {
        id: 'lecturer-courses',
        label: 'Mata Kuliah',
        to: '/courses',
        icon: 'BookOpen',
        permission: 'courses.view',
        roles: ['dosen'],
      },
    ],
  },
  {
    title: 'Akademik',
    roles: ADMIN_ROLES,
    items: [
      {
        id: 'perkuliahan-group',
        label: 'Perkuliahan',
        icon: 'BookOpen',
        roles: ADMIN_ROLES,
        children: [
          {
            id: 'monitoring-sesi',
            label: 'Monitoring Sesi',
            to: '/sessions',
            icon: 'CalendarCheck',
            permission: 'attendance.view',
            roles: ADMIN_ROLES,
          },
          {
            id: 'kelas-kuliah',
            label: 'Kelas Kuliah',
            to: '/classes',
            icon: 'School',
            permission: 'classes.view',
            roles: ADMIN_ROLES,
          },
          {
            id: 'schedules',
            label: 'Jadwal Kuliah',
            to: '/schedules',
            icon: 'Calendar',
            permission: 'schedules.view',
            roles: ADMIN_ROLES,
          },
          {
            id: 'jadwal-ujian',
            label: 'Jadwal Ujian',
            to: '/exams/schedules',
            icon: 'CalendarDays',
            roles: ADMIN_ROLES,
          },
        ],
      },
      {
        id: 'kelulusan-group',
        label: 'Kelulusan',
        icon: 'GraduationCap',
        roles: ADMIN_ROLES,
        children: [
          {
            id: 'tugas-akhir',
            label: 'Tugas Akhir',
            to: '/thesis',
            icon: 'FileText',
            roles: ADMIN_ROLES,
          },
          {
            id: 'yudisium',
            label: 'Yudisium',
            icon: 'BadgeCheck',
            roles: ADMIN_ROLES,
            children: [
              {
                id: 'yudisium-eligible',
                label: 'Mahasiswa Eligible',
                to: '/graduation/yudisium/eligible',
                icon: 'ListChecks',
                roles: ADMIN_ROLES,
              },
              {
                id: 'yudisium-approvals',
                label: 'Persetujuan Yudisium',
                to: '/graduation/yudisium/approvals',
                icon: 'BadgeCheck',
                roles: ADMIN_ROLES,
              },
              {
                id: 'yudisium-participants',
                label: 'Peserta Yudisium',
                to: '/graduation/yudisium/participants',
                icon: 'Users',
                roles: ADMIN_ROLES,
              },
              {
                id: 'yudisium-periods',
                label: 'Periode Yudisium',
                to: '/graduation/yudisium/periods',
                icon: 'CalendarDays',
                roles: ADMIN_ROLES,
              },
              {
                id: 'yudisium-requirements',
                label: 'Syarat Yudisium',
                to: '/graduation/yudisium/requirements',
                icon: 'ClipboardCheck',
                roles: ADMIN_ROLES,
              },
            ],
          },
          {
            id: 'wisuda',
            label: 'Wisuda',
            to: '/graduation/wisuda',
            icon: 'Award',
            roles: ADMIN_ROLES,
          },
        ],
      },
      {
        id: 'kemahasiswaan-group',
        label: 'Kemahasiswaan',
        icon: 'UserX',
        roles: ADMIN_ROLES,
        children: [
          {
            id: 'berhenti-studi',
            label: 'Berhenti Studi',
            to: '/students/dropout',
            icon: 'UserX',
            roles: ADMIN_ROLES,
          },
          {
            id: 'validasi-cuti',
            label: 'Validasi Cuti',
            to: '/students/leave-validation',
            icon: 'ClipboardCheck',
            roles: ADMIN_ROLES,
          },
        ],
      },
    ],
  },
  {
    title: 'Perwalian & KRS',
    roles: ADMIN_ROLES,
    items: [
      {
        id: 'krs-group',
        label: 'KRS',
        icon: 'FileSpreadsheet',
        roles: ADMIN_ROLES,
        children: [
          {
            id: 'krs-monitoring',
            label: 'Monitoring KRS',
            to: '/enrollments',
            icon: 'FileText',
            permission: 'enrollments.view',
            roles: ADMIN_ROLES,
          },
          {
            id: 'krs-packages',
            label: 'Paket KRS',
            to: '/enrollments/packages',
            icon: 'Layers',
            permission: 'enrollments.view',
            roles: ADMIN_ROLES,
          },
        ],
      },
      {
        id: 'pembimbing-group',
        label: 'Pembimbing Akademik',
        icon: 'UserCheck',
        roles: ADMIN_ROLES,
        children: [
          {
            id: 'pembimbing-distribution',
            label: 'Distribusi Pembimbing',
            to: '/advising',
            icon: 'Users',
            permission: 'advising.view',
            roles: ADMIN_ROLES,
          },
          {
            id: 'pembimbing-quotas',
            label: 'Kuota Pembimbing',
            to: '/advising/quotas',
            icon: 'PieChart',
            permission: 'advising.view',
            roles: ADMIN_ROLES,
          },
        ],
      },
    ],
  },
  {
    title: 'Data Master',
    roles: ADMIN_ROLES,
    items: [
      {
        id: 'periode-group',
        label: 'Periode Akademik',
        icon: 'CalendarDays',
        roles: ADMIN_ROLES,
        children: [
          {
            id: 'academic-years',
            label: 'Tahun Ajaran',
            to: '/academic/years',
            icon: 'Calendar',
            permission: 'academic_years.view',
            roles: ADMIN_ROLES,
          },
          {
            id: 'academic-semesters',
            label: 'Periode Akademik',
            to: '/academic/semesters',
            icon: 'CalendarDays',
            permission: 'semesters.view',
            roles: ADMIN_ROLES,
          },
        ],
      },
      {
        id: 'students',
        label: 'Data Mahasiswa',
        to: '/students',
        icon: 'GraduationCap',
        permission: 'students.view',
        roles: ADMIN_ROLES,
      },
      {
        id: 'lecturers',
        label: 'Data Dosen',
        to: '/lecturers',
        icon: 'Users',
        permission: 'lecturers.view',
        roles: ADMIN_ROLES,
      },
      {
        id: 'courses-group',
        label: 'Mata Kuliah',
        icon: 'BookOpen',
        roles: ADMIN_ROLES,
        children: [
          {
            id: 'courses-list',
            label: 'Daftar Mata Kuliah',
            to: '/courses',
            icon: 'BookOpen',
            permission: 'courses.view',
            roles: ADMIN_ROLES,
          },
          {
            id: 'courses-types',
            label: 'Jenis Mata Kuliah',
            to: '/courses/types',
            icon: 'BookMarked',
            permission: 'courses.view',
            roles: ADMIN_ROLES,
          },
          {
            id: 'courses-groups',
            label: 'Kelompok Mata Kuliah',
            to: '/courses/groups',
            icon: 'Layers',
            permission: 'courses.view',
            roles: ADMIN_ROLES,
          },
          {
            id: 'courses-survey-templates',
            label: 'Template Survey',
            to: '/courses/survey-templates',
            icon: 'ClipboardCheck',
            permission: 'courses.view',
            roles: ADMIN_ROLES,
          },
        ],
      },
      {
        id: 'curriculum-group',
        label: 'Kurikulum',
        icon: 'Layers',
        roles: ADMIN_ROLES,
        children: [
          {
            id: 'curriculum-years',
            label: 'Tahun Kurikulum',
            to: '/curriculum/years',
            icon: 'Calendar',
            permission: 'curricula.view',
            roles: ADMIN_ROLES,
          },
          {
            id: 'curriculum-credit-limits',
            label: 'Batas SKS',
            to: '/curriculum/credit-limits',
            icon: 'Sliders',
            permission: 'curricula.view',
            roles: ADMIN_ROLES,
          },
          {
            id: 'curriculum-grade-scales',
            label: 'Skala Nilai',
            to: '/curriculum/grade-scales',
            icon: 'Award',
            permission: 'curricula.view',
            roles: ADMIN_ROLES,
          },
          {
            id: 'curriculum-study-programs',
            label: 'Kurikulum Program Studi',
            to: '/curriculum/study-programs',
            icon: 'BookOpen',
            permission: 'curricula.view',
            roles: ADMIN_ROLES,
          },
        ],
      },
      {
        id: 'capaian-lulusan-group',
        label: 'Capaian Lulusan',
        icon: 'Award',
        roles: ADMIN_ROLES,
        children: [
          {
            id: 'outcomes-pl',
            label: 'Profil Lulusan (PL)',
            to: '/outcomes/pl',
            icon: 'Award',
            roles: ADMIN_ROLES,
          },
          {
            id: 'outcomes-cpl',
            label: 'Capaian Pembelajaran Lulusan (CPL)',
            to: '/outcomes/cpl',
            icon: 'Layers',
            roles: ADMIN_ROLES,
          },
          {
            id: 'outcomes-cpmk',
            label: 'Capaian Pembelajaran Mata Kuliah (CPMK)',
            to: '/outcomes/cpmk',
            icon: 'BookOpen',
            roles: ADMIN_ROLES,
          },
          {
            id: 'outcomes-sub-cpmk',
            label: 'Sub-CPMK',
            to: '/outcomes/sub-cpmk',
            icon: 'CheckCircle2',
            roles: ADMIN_ROLES,
          },
        ],
      },
      {
        id: 'lecture-group',
        label: 'Referensi Perkuliahan',
        icon: 'School',
        roles: ADMIN_ROLES,
        children: [
          {
            id: 'classes-session-types',
            label: 'Jenis Sesi Perkuliahan',
            to: '/classes/session-types',
            icon: 'CalendarDays',
            permission: 'classes.view',
            roles: ADMIN_ROLES,
          },
          {
            id: 'classes-student-groups',
            label: 'Kelompok Mahasiswa',
            to: '/classes/student-groups',
            icon: 'Users',
            permission: 'classes.view',
            roles: ADMIN_ROLES,
          },
          {
            id: 'classes-programs',
            label: 'Program Kuliah',
            to: '/classes/programs',
            icon: 'BookOpen',
            permission: 'classes.view',
            roles: ADMIN_ROLES,
          },
          {
            id: 'classes-grading-components',
            label: 'Unsur Nilai',
            to: '/classes/grading-components',
            icon: 'Award',
            permission: 'classes.view',
            roles: ADMIN_ROLES,
          },
        ],
      },
    ],
  },
  {
    title: 'Unit & Sarana',
    roles: ADMIN_ROLES,
    items: [
      {
        id: 'unit-kerja-group',
        label: 'Unit Kerja',
        icon: 'Building',
        roles: ADMIN_ROLES,
        children: [
          {
            id: 'unit-universitas',
            label: 'Universitas',
            to: '/academic/institutions',
            icon: 'School',
            permission: 'institutions.view',
            roles: ADMIN_ROLES,
          },
          {
            id: 'unit-fakultas',
            label: 'Fakultas',
            to: '/academic/faculties',
            icon: 'BookOpen',
            permission: 'faculties.view',
            roles: ADMIN_ROLES,
          },
          {
            id: 'unit-prodi',
            label: 'Program Studi',
            to: '/study-programs',
            icon: 'GraduationCap',
            permission: 'study_programs.view',
            roles: ADMIN_ROLES,
          },
        ],
      },
      {
        id: 'facilities-group',
        label: 'Sarana & Prasarana',
        icon: 'Building2',
        roles: ADMIN_ROLES,
        children: [
          {
            id: 'facilities-campuses',
            label: 'Kampus',
            to: '/facilities/campuses',
            icon: 'School',
            permission: 'rooms.view',
            roles: ADMIN_ROLES,
          },
          {
            id: 'facilities-buildings',
            label: 'Gedung',
            to: '/facilities/buildings',
            icon: 'Building',
            permission: 'rooms.view',
            roles: ADMIN_ROLES,
          },
          {
            id: 'facilities-rooms',
            label: 'Ruang',
            to: '/rooms',
            icon: 'DoorOpen',
            permission: 'rooms.view',
            roles: ADMIN_ROLES,
          },
        ],
      },
    ],
  },
  {
    title: 'Pengaturan',
    roles: ADMIN_ROLES,
    items: [
      {
        id: 'config-study-programs',
        label: 'Program Studi',
        to: '/settings/study-programs',
        icon: 'GraduationCap',
        permission: 'study_programs.view',
        roles: ADMIN_ROLES,
      },
      {
        id: 'config-degree-levels',
        label: 'Jenjang Pendidikan',
        to: '/settings/degree-levels',
        icon: 'Award',
        permission: 'study_programs.view',
        roles: ADMIN_ROLES,
      },
      {
        id: 'config-ktm',
        label: 'Kartu Tanda Mahasiswa (KTM)',
        to: '/settings/ktm',
        icon: 'CreditCard',
        permission: 'students.view',
        roles: ADMIN_ROLES,
      },
    ],
  },
]
