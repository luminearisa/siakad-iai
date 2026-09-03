<script setup lang="ts">
import { ref, onMounted } from 'vue'
import {
  GraduationCap,
  Users,
  BookOpen,
  Layers,
  School,
  Calendar,
  FileSpreadsheet,
  Building2,
  CheckCircle2,
  ArrowRight,
  ShieldCheck,
  QrCode,
  UserCheck,
  DoorOpen,
} from 'lucide-vue-next'
import { studentService } from '@/services/api/students'
import { lecturerService } from '@/services/api/lecturers'
import { academicService } from '@/services/api/academic'
import { classService } from '@/services/api/classes'
import { roomService } from '@/services/api/rooms'
import { curriculumService } from '@/services/api/curriculum'
import { useAuth } from '@/composables/useAuth'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'

const { user } = useAuth()
const loading = ref<boolean>(true)

const totalStudents = ref<number>(0)
const totalLecturers = ref<number>(0)
const totalStudyPrograms = ref<number>(0)
const totalClasses = ref<number>(0)
const totalRooms = ref<number>(0)
const totalCurriculums = ref<number>(0)
const activeSemesterDisplay = ref<string>('Memuat Periode Aktif...')

async function loadAdminStats() {
  loading.value = true
  try {
    const [stdRes, lecRes, spRes, clsRes, rmRes, curRes, semRes] = await Promise.allSettled([
      studentService.list({ per_page: 1 }),
      lecturerService.list({ per_page: 1 }),
      academicService.getStudyPrograms({ per_page: 1 }),
      classService.list({ per_page: 1 }),
      roomService.list({ per_page: 1 }),
      curriculumService.list({ per_page: 1 }),
      academicService.getSemesters({ status: 'active' }),
    ])

    if (stdRes.status === 'fulfilled' && stdRes.value.meta) {
      totalStudents.value = stdRes.value.meta.total ?? 0
    }
    if (lecRes.status === 'fulfilled' && lecRes.value.meta) {
      totalLecturers.value = lecRes.value.meta.total ?? 0
    }
    if (spRes.status === 'fulfilled' && spRes.value.meta) {
      totalStudyPrograms.value = spRes.value.meta.total ?? 0
    }
    if (clsRes.status === 'fulfilled' && clsRes.value.meta) {
      totalClasses.value = clsRes.value.meta.total ?? 0
    }
    if (rmRes.status === 'fulfilled' && rmRes.value.meta) {
      totalRooms.value = rmRes.value.meta.total ?? 0
    }
    if (curRes.status === 'fulfilled' && curRes.value.meta) {
      totalCurriculums.value = curRes.value.meta.total ?? 0
    }
    if (semRes.status === 'fulfilled' && semRes.value.data && semRes.value.data.length > 0) {
      const sem = semRes.value.data[0]
      activeSemesterDisplay.value = sem.academic_year?.name
        ? `Semester ${sem.name} (${sem.academic_year.name})`
        : `Semester ${sem.name}`
    } else {
      activeSemesterDisplay.value = 'Belum Ada Semester Aktif'
    }
  } finally {
    loading.value = false
  }
}

const adminModules = [
  {
    title: 'Struktur Akademik',
    desc: 'Institusi, Fakultas, Program Studi, & Semester',
    icon: Building2,
    to: '/academic',
    color: 'text-blue-600 bg-blue-50 border-blue-200',
  },
  {
    title: 'Data Mahasiswa',
    desc: 'Manajemen biodata, status, & riwayat mahasiswa',
    icon: GraduationCap,
    to: '/students',
    color: 'text-indigo-600 bg-indigo-50 border-indigo-200',
  },
  {
    title: 'Data Dosen',
    desc: 'Master dosen, NIDN, kepakaran, & homebase',
    icon: Users,
    to: '/lecturers',
    color: 'text-emerald-600 bg-emerald-50 border-emerald-200',
  },
  {
    title: 'Katalog Mata Kuliah',
    desc: 'Daftar MK, SKS Teori/Praktik, & Prasyarat',
    icon: BookOpen,
    to: '/courses',
    color: 'text-cyan-600 bg-cyan-50 border-cyan-200',
  },
  {
    title: 'Kurikulum OBE',
    desc: 'Struktur kurikulum & sebaran semester',
    icon: Layers,
    to: '/curriculum',
    color: 'text-purple-600 bg-purple-50 border-purple-200',
  },
  {
    title: 'Kelas Perkuliahan',
    desc: 'Pembukaan kelas, kapasitas, & penugasan dosen',
    icon: School,
    to: '/classes',
    color: 'text-amber-600 bg-amber-50 border-amber-200',
  },
  {
    title: 'Jadwal & Ruangan',
    desc: 'Alokasi ruang & deteksi bentrok jadwal',
    icon: Calendar,
    to: '/schedules',
    color: 'text-rose-600 bg-rose-50 border-rose-200',
  },
  {
    title: 'KRS / Enrollment',
    desc: 'Rencana studi mahasiswa & workflow persetujuan',
    icon: FileSpreadsheet,
    to: '/enrollments',
    color: 'text-teal-600 bg-teal-50 border-teal-200',
  },
  {
    title: 'Penugasan Dosen PA',
    desc: 'Alokasi dosen pembimbing & monitoring bimbingan',
    icon: UserCheck,
    to: '/advising',
    color: 'text-purple-600 bg-purple-50 border-purple-200',
  },
  {
    title: 'Presensi & Pertemuan Kelas',
    desc: 'Monitoring matriks kehadiran 1–16 & BAP',
    icon: QrCode,
    to: '/attendance',
    color: 'text-brand-600 bg-brand-50 border-brand-200',
  },
]

onMounted(() => {
  loadAdminStats()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Admin Hero Card -->
    <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-brand-950 rounded-2xl p-6 text-white relative overflow-hidden shadow-md">
      <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-brand-500/10 rounded-full blur-3xl pointer-events-none" />

      <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div class="flex items-start gap-4">
          <div class="w-16 h-16 rounded-2xl bg-white/10 backdrop-blur-md border border-white/20 flex items-center justify-center text-white text-2xl font-bold uppercase shrink-0 shadow-inner">
            <ShieldCheck class="w-8 h-8 text-brand-400" />
          </div>
          <div>
            <div class="flex flex-wrap items-center gap-2 mb-1">
              <h1 class="text-xl font-bold text-white tracking-tight">
                Pusat Kontrol Akademik &bull; {{ user?.name || 'Administrator' }}
              </h1>
              <Badge variant="gold-glass" size="xs">
                Super Admin
              </Badge>
            </div>
            <p class="text-xs text-slate-300">
              Sistem Informasi Akademik Terpadu Institut Agama Islam (IAI) &bull; Semester Aktif
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Campus Global Statistics -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
      <Card class="p-4 bg-white border border-slate-200 shadow-2xs">
        <div class="flex items-center justify-between text-slate-500 mb-2">
          <span class="text-2xs uppercase font-semibold tracking-wider">Total Mahasiswa</span>
          <GraduationCap class="w-4 h-4 text-brand-600" />
        </div>
        <div class="text-2xl font-bold text-slate-900 font-mono">
          {{ totalStudents }} <span class="text-xs font-normal text-slate-500">Mahasiswa</span>
        </div>
        <div class="text-2xs text-emerald-600 font-semibold mt-0.5">
          Terdaftar Aktif
        </div>
      </Card>

      <Card class="p-4 bg-white border border-slate-200 shadow-2xs">
        <div class="flex items-center justify-between text-slate-500 mb-2">
          <span class="text-2xs uppercase font-semibold tracking-wider">Total Dosen</span>
          <Users class="w-4 h-4 text-indigo-600" />
        </div>
        <div class="text-2xl font-bold text-slate-900 font-mono">
          {{ totalLecturers }} <span class="text-xs font-normal text-slate-500">Dosen</span>
        </div>
        <div class="text-2xs text-slate-500 mt-0.5">
          Homebase Prodi IAI
        </div>
      </Card>

      <Card class="p-4 bg-white border border-slate-200 shadow-2xs">
        <div class="flex items-center justify-between text-slate-500 mb-2">
          <span class="text-2xs uppercase font-semibold tracking-wider">Program Studi</span>
          <Building2 class="w-4 h-4 text-purple-600" />
        </div>
        <div class="text-2xl font-bold text-slate-900 font-mono">
          {{ totalStudyPrograms }} <span class="text-xs font-normal text-slate-500">Prodi</span>
        </div>
        <div class="text-2xs text-brand-600 font-medium mt-0.5">
          Jenjang S1 & S2
        </div>
      </Card>

      <Card class="p-4 bg-white border border-slate-200 shadow-2xs">
        <div class="flex items-center justify-between text-slate-500 mb-2">
          <span class="text-2xs uppercase font-semibold tracking-wider">Kelas Terbuka</span>
          <School class="w-4 h-4 text-amber-600" />
        </div>
        <div class="text-2xl font-bold text-slate-900 font-mono">
          {{ totalClasses }} <span class="text-xs font-normal text-slate-500">Kelas</span>
        </div>
        <div class="text-2xs text-emerald-600 font-semibold mt-0.5">
          Semester Aktif
        </div>
      </Card>
    </div>

    <!-- System & Operational Status Overview -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <Card dense class="bg-gradient-to-br from-brand-900 to-brand-950 text-white !border-transparent shadow-2xs">
        <div class="flex items-center justify-between text-xs text-brand-200 mb-2">
          <span>Tahun Akademik & Semester</span>
          <CheckCircle2 class="w-4 h-4 text-emerald-400" />
        </div>
        <div class="text-lg font-bold tracking-tight text-white mb-1">
          {{ activeSemesterDisplay }}
        </div>
        <div class="text-2xs text-brand-300">
          Status: Aktif Berjalan
        </div>
      </Card>

      <Card dense class="shadow-2xs">
        <div class="flex items-center justify-between text-xs text-slate-500 mb-2">
          <span>Sarana Ruang Kuliah</span>
          <DoorOpen class="w-4 h-4 text-brand-600" />
        </div>
        <div class="text-base font-bold text-slate-800 mb-1">
          {{ totalRooms }} Ruang Perkuliahan
        </div>
        <div class="text-2xs text-slate-500">
          Termasuk Lab CBT, Microteaching, & Peradilan Semu
        </div>
      </Card>

      <Card dense class="shadow-2xs">
        <div class="flex items-center justify-between text-xs text-slate-500 mb-2">
          <span>Struktur Kurikulum OBE</span>
          <Layers class="w-4 h-4 text-indigo-600" />
        </div>
        <div class="text-base font-bold text-slate-800 mb-1">
          {{ totalCurriculums }} Dokumen Kurikulum
        </div>
        <div class="text-2xs text-slate-500">
          Berbasis Outcome-Based Education (OBE)
        </div>
      </Card>
    </div>

    <!-- Administrative Modules Grid -->
    <div>
      <h2 class="text-sm font-bold text-slate-900 tracking-tight mb-3">
        Modul Master & Manajemen Operasional Akademik
      </h2>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
        <router-link
          v-for="mod in adminModules"
          :key="mod.title"
          :to="mod.to"
          class="p-4 bg-white border border-slate-200 rounded-xl hover:border-brand-400 hover:shadow-subtle transition-all duration-150 group flex items-start gap-3.5"
        >
          <div :class="['w-10 h-10 rounded-xl border flex items-center justify-center shrink-0', mod.color]">
            <component :is="mod.icon" class="w-5 h-5" />
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between">
              <h3 class="text-xs font-bold text-slate-900 group-hover:text-brand-700 transition-colors">
                {{ mod.title }}
              </h3>
              <ArrowRight class="w-3.5 h-3.5 text-slate-300 group-hover:text-brand-600 group-hover:translate-x-0.5 transition-all" />
            </div>
            <p class="text-2xs text-slate-500 mt-0.5">
              {{ mod.desc }}
            </p>
          </div>
        </router-link>
      </div>
    </div>
  </div>
</template>
