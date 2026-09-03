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
        id: 'lecturer-advising',
        label: 'Bimbingan PA & KRS',
        to: '/advising',
        icon: 'UserCheck',
        permission: 'advising.view',
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
        id: 'lecturer-courses',
        label: 'Mata Kuliah',
        to: '/courses',
        icon: 'BookOpen',
        permission: 'courses.view',
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
    ],
  },
  {
    title: 'AKTIVITAS',
    roles: ['super_admin', 'admin_akademik'],
    items: [
      {
        id: 'perkuliahan-group',
        label: 'Perkuliahan',
        icon: 'BookOpen',
        roles: ['super_admin', 'admin_akademik'],
        children: [
          {
            id: 'monitoring-sesi',
            label: 'Monitoring Sesi',
            to: '/sessions',
            icon: 'CalendarCheck',
            permission: 'attendance.view',
            roles: ['super_admin', 'admin_akademik'],
          },
          {
            id: 'kelas-kuliah',
            label: 'Kelas Kuliah',
            to: '/classes',
            icon: 'School',
            permission: 'classes.view',
            roles: ['super_admin', 'admin_akademik'],
          },
          {
            id: 'jadwal-ujian',
            label: 'Jadwal Ujian',
            to: '/exams/schedules',
            icon: 'Calendar',
            roles: ['super_admin', 'admin_akademik'],
          },
        ],
      },
      {
        id: 'tugas-akhir',
        label: 'Tugas Akhir',
        to: '/thesis',
        icon: 'FileText',
        roles: ['super_admin', 'admin_akademik'],
      },
      {
        id: 'yudisium',
        label: 'Yudisium',
        icon: 'GraduationCap',
        roles: ['super_admin', 'admin_akademik'],
        children: [
          {
            id: 'yudisium-eligible',
            label: 'Eligible Yudisium',
            to: '/graduation/yudisium/eligible',
            icon: 'Circle',
            roles: ['super_admin', 'admin_akademik'],
          },
          {
            id: 'yudisium-approvals',
            label: 'Persetujuan Yudisium',
            to: '/graduation/yudisium/approvals',
            icon: 'Circle',
            roles: ['super_admin', 'admin_akademik'],
          },
          {
            id: 'yudisium-participants',
            label: 'Peserta Yudisium',
            to: '/graduation/yudisium/participants',
            icon: 'Circle',
            roles: ['super_admin', 'admin_akademik'],
          },
          {
            id: 'yudisium-periods',
            label: 'Periode Yudisium',
            to: '/graduation/yudisium/periods',
            icon: 'Circle',
            roles: ['super_admin', 'admin_akademik'],
          },
          {
            id: 'yudisium-requirements',
            label: 'Syarat Yudisium',
            to: '/graduation/yudisium/requirements',
            icon: 'Circle',
            roles: ['super_admin', 'admin_akademik'],
          },
        ],
      },
      {
        id: 'wisuda',
        label: 'Wisuda',
        to: '/graduation/wisuda',
        icon: 'Award',
        roles: ['super_admin', 'admin_akademik'],
      },
      {
        id: 'berhenti-studi',
        label: 'Berhenti Studi',
        to: '/students/dropout',
        icon: 'UserX',
        roles: ['super_admin', 'admin_akademik'],
      },
      {
        id: 'validasi-cuti',
        label: 'Validasi Cuti',
        to: '/students/leave-validation',
        icon: 'ClipboardCheck',
        roles: ['super_admin', 'admin_akademik'],
      },
    ],
  },
  {
    title: 'PERWALIAN',
    roles: ['super_admin', 'admin_akademik'],
    items: [
      {
        id: 'krs-group',
        label: 'KRS',
        icon: 'FileSpreadsheet',
        roles: ['super_admin', 'admin_akademik'],
        children: [
          {
            id: 'krs-monitoring',
            label: 'Monitoring KRS',
            to: '/enrollments',
            icon: 'FileText',
            permission: 'enrollments.view',
            roles: ['super_admin', 'admin_akademik'],
          },
          {
            id: 'krs-packages',
            label: 'Paket KRS',
            to: '/enrollments/packages',
            icon: 'Layers',
            permission: 'enrollments.view',
            roles: ['super_admin', 'admin_akademik'],
          },
        ],
      },
      {
        id: 'pembimbing-group',
        label: 'Pembimbing',
        icon: 'UserCheck',
        roles: ['super_admin', 'admin_akademik'],
        children: [
          {
            id: 'pembimbing-distribution',
            label: 'Distribusi Pembimbing',
            to: '/advising',
            icon: 'Users',
            permission: 'advising.view',
            roles: ['super_admin', 'admin_akademik'],
          },
          {
            id: 'pembimbing-quotas',
            label: 'Kuota Pembimbing',
            to: '/advising/quotas',
            icon: 'PieChart',
            permission: 'advising.view',
            roles: ['super_admin', 'admin_akademik'],
          },
        ],
      },
    ],
  },
  {
    title: 'DATA',
    roles: ['super_admin', 'admin_akademik'],
    items: [
      {
        id: 'master-data-group',
        label: 'Master Data',
        icon: 'Database',
        roles: ['super_admin', 'admin_akademik'],
        children: [
          {
            id: 'periode-group',
            label: 'Periode',
            icon: 'CalendarDays',
            roles: ['super_admin', 'admin_akademik'],
            children: [
              {
                id: 'academic-years',
                label: 'Tahun Ajaran',
                to: '/academic/years',
                icon: 'Calendar',
                permission: 'academic_years.view',
                roles: ['super_admin', 'admin_akademik'],
              },
              {
                id: 'academic-semesters',
                label: 'Periode Akademik',
                to: '/academic/semesters',
                icon: 'CalendarDays',
                permission: 'semesters.view',
                roles: ['super_admin', 'admin_akademik'],
              },
            ],
          },
          {
            id: 'students',
            label: 'Data Mahasiswa',
            to: '/students',
            icon: 'GraduationCap',
            permission: 'students.view',
            roles: ['super_admin', 'admin_akademik'],
          },
          {
            id: 'lecturers',
            label: 'Data Dosen',
            to: '/lecturers',
            icon: 'Users',
            permission: 'lecturers.view',
            roles: ['super_admin', 'admin_akademik'],
          },
          {
            id: 'courses-group',
            label: 'Mata Kuliah',
            icon: 'BookOpen',
            roles: ['super_admin', 'admin_akademik'],
            children: [
              {
                id: 'courses-list',
                label: 'Mata Kuliah',
                to: '/courses',
                icon: 'BookOpen',
                permission: 'courses.view',
                roles: ['super_admin', 'admin_akademik'],
              },
              {
                id: 'courses-types',
                label: 'Jenis Mata Kuliah',
                to: '/courses/types',
                icon: 'BookMarked',
                permission: 'courses.view',
                roles: ['super_admin', 'admin_akademik'],
              },
              {
                id: 'courses-groups',
                label: 'Kelompok Mata Kuliah',
                to: '/courses/groups',
                icon: 'Layers',
                permission: 'courses.view',
                roles: ['super_admin', 'admin_akademik'],
              },
            ],
          },
          {
            id: 'capaian-lulusan-group',
            label: 'Capaian Lulusan',
            icon: 'Award',
            roles: ['super_admin', 'admin_akademik'],
            children: [
              {
                id: 'outcomes-pl',
                label: 'Profil Lulusan (PL)',
                to: '/outcomes/pl',
                icon: 'Award',
                roles: ['super_admin', 'admin_akademik'],
              },
              {
                id: 'outcomes-cpl',
                label: 'Capaian Pembelajaran Lulusan (CPL)',
                to: '/outcomes/cpl',
                icon: 'Layers',
                roles: ['super_admin', 'admin_akademik'],
              },
              {
                id: 'outcomes-cpmk',
                label: 'Capaian Pembelajaran Mata Kuliah (CPMK)',
                to: '/outcomes/cpmk',
                icon: 'BookOpen',
                roles: ['super_admin', 'admin_akademik'],
              },
              {
                id: 'outcomes-sub-cpmk',
                label: 'Sub-CPMK',
                to: '/outcomes/sub-cpmk',
                icon: 'CheckCircle2',
                roles: ['super_admin', 'admin_akademik'],
              },
            ],
          },
          {
            id: 'curriculum-group',
            label: 'Kurikulum',
            icon: 'Layers',
            roles: ['super_admin', 'admin_akademik'],
            children: [
              {
                id: 'curriculum-years',
                label: 'Tahun Kurikulum',
                to: '/curriculum/years',
                icon: 'Calendar',
                permission: 'curricula.view',
                roles: ['super_admin', 'admin_akademik'],
              },
              {
                id: 'curriculum-credit-limits',
                label: 'Batas SKS',
                to: '/curriculum/credit-limits',
                icon: 'Sliders',
                permission: 'curricula.view',
                roles: ['super_admin', 'admin_akademik'],
              },
              {
                id: 'curriculum-grade-scales',
                label: 'Skala Nilai',
                to: '/curriculum/grade-scales',
                icon: 'Award',
                permission: 'curricula.view',
                roles: ['super_admin', 'admin_akademik'],
              },
              {
                id: 'curriculum-study-programs',
                label: 'Kurikulum Program Studi',
                to: '/curriculum/study-programs',
                icon: 'BookOpen',
                permission: 'curricula.view',
                roles: ['super_admin', 'admin_akademik'],
              },
            ],
          },
          {
            id: 'lecture-group',
            label: 'Perkuliahan',
            icon: 'School',
            roles: ['super_admin', 'admin_akademik'],
            children: [
              {
                id: 'classes-session-types',
                label: 'Jenis Sesi Perkuliahan',
                to: '/classes/session-types',
                icon: 'Calendar',
                permission: 'classes.view',
                roles: ['super_admin', 'admin_akademik'],
              },
              {
                id: 'classes-student-groups',
                label: 'Kelompok Mahasiswa',
                to: '/classes/student-groups',
                icon: 'Users',
                permission: 'classes.view',
                roles: ['super_admin', 'admin_akademik'],
              },
              {
                id: 'classes-programs',
                label: 'Program Kuliah',
                to: '/classes/programs',
                icon: 'BookOpen',
                permission: 'classes.view',
                roles: ['super_admin', 'admin_akademik'],
              },
              {
                id: 'classes-grading-components',
                label: 'Unsur Nilai',
                to: '/classes/grading-components',
                icon: 'Award',
                permission: 'classes.view',
                roles: ['super_admin', 'admin_akademik'],
              },
              {
                id: 'classes-list',
                label: 'Daftar Kelas Perkuliahan',
                to: '/classes',
                icon: 'Layers',
                permission: 'classes.view',
                roles: ['super_admin', 'admin_akademik'],
              },
            ],
          },
          {
            id: 'attendance',
            label: 'Presensi Perkuliahan',
            to: '/attendance',
            icon: 'CalendarCheck',
            permission: 'attendance.view',
            roles: ['super_admin', 'admin_akademik'],
          },
          {
            id: 'schedules',
            label: 'Jadwal Kuliah',
            to: '/schedules',
            icon: 'Calendar',
            permission: 'schedules.view',
            roles: ['super_admin', 'admin_akademik'],
          },
          {
            id: 'unit-kerja-group',
            label: 'Unit Kerja',
            icon: 'Building',
            roles: ['super_admin', 'admin_akademik'],
            children: [
              {
                id: 'unit-universitas',
                label: 'Universitas',
                to: '/academic/institutions',
                icon: 'School',
                permission: 'institutions.view',
                roles: ['super_admin', 'admin_akademik'],
              },
              {
                id: 'unit-fakultas',
                label: 'Fakultas',
                to: '/academic/faculties',
                icon: 'BookOpen',
                permission: 'faculties.view',
                roles: ['super_admin', 'admin_akademik'],
              },
              {
                id: 'unit-prodi',
                label: 'Program Studi',
                to: '/study-programs',
                icon: 'GraduationCap',
                permission: 'study_programs.view',
                roles: ['super_admin', 'admin_akademik'],
              },
            ],
          },
          {
            id: 'facilities-group',
            label: 'Sarana dan Prasarana',
            icon: 'Building2',
            roles: ['super_admin', 'admin_akademik'],
            children: [
              {
                id: 'facilities-campuses',
                label: 'Kampus',
                to: '/facilities/campuses',
                icon: 'School',
                permission: 'rooms.view',
                roles: ['super_admin', 'admin_akademik'],
              },
              {
                id: 'facilities-buildings',
                label: 'Gedung',
                to: '/facilities/buildings',
                icon: 'Building',
                permission: 'rooms.view',
                roles: ['super_admin', 'admin_akademik'],
              },
              {
                id: 'facilities-rooms',
                label: 'Ruang',
                to: '/rooms',
                icon: 'DoorOpen',
                permission: 'rooms.view',
                roles: ['super_admin', 'admin_akademik'],
              },
            ],
          },
        ],
      },
      {
        id: 'configuration-group',
        label: 'Konfigurasi',
        icon: 'Settings',
        roles: ['super_admin', 'admin_akademik'],
        children: [
          {
            id: 'config-study-programs',
            label: 'Program Studi',
            to: '/settings/study-programs',
            icon: 'GraduationCap',
            permission: 'study_programs.view',
            roles: ['super_admin', 'admin_akademik'],
          },
          {
            id: 'config-degree-levels',
            label: 'Jenjang Pendidikan',
            to: '/settings/degree-levels',
            icon: 'Award',
            permission: 'study_programs.view',
            roles: ['super_admin', 'admin_akademik'],
          },
          {
            id: 'config-ktm',
            label: 'KTM',
            to: '/settings/ktm',
            icon: 'CreditCard',
            permission: 'students.view',
            roles: ['super_admin', 'admin_akademik'],
          },
        ],
      },
    ],
  },
]
