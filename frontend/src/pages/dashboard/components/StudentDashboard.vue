<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import {
  GraduationCap,
  BookOpen,
  Award,
  Calendar,
  Clock,
  MapPin,
  UserCheck,
  CheckCircle2,
  FileSpreadsheet,
  ArrowRight,
  Sparkles,
  QrCode,
} from 'lucide-vue-next'
import { studentPortalApi } from '@/services/api/student-portal'
import { attendanceService } from '@/services/api/attendance'
import type { StudentProfileData, StudentScheduleItem } from '@/types/student-portal'
import type { StudentAttendanceRecap } from '@/types/attendance'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'

const loading = ref<boolean>(true)
const profileData = ref<StudentProfileData | null>(null)
const schedules = ref<StudentScheduleItem[]>([])
const attendanceRecap = ref<StudentAttendanceRecap | null>(null)

const dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu']
const currentDayIndex = new Date().getDay()
const currentDayName = dayNames[currentDayIndex]

async function loadDashboardData() {
  loading.value = true
  try {
    const [profRes, schedRes, attRes] = await Promise.allSettled([
      studentPortalApi.getProfile(),
      studentPortalApi.getSchedules(),
      attendanceService.getMyAttendance(),
    ])

    if (profRes.status === 'fulfilled' && profRes.value.data) {
      profileData.value = profRes.value.data
    }
    if (schedRes.status === 'fulfilled' && schedRes.value.data) {
      schedules.value = schedRes.value.data
    }
    if (attRes.status === 'fulfilled' && attRes.value.data) {
      attendanceRecap.value = attRes.value.data
    }
  } finally {
    loading.value = false
  }
}

// Today's classes
const todayClasses = computed(() => {
  const lookupDay = currentDayIndex === 0 ? 1 : currentDayIndex
  return schedules.value.filter((s) => s.day_of_week === lookupDay)
})

const student = computed(() => profileData.value?.student)

const quickActions = [
  {
    title: 'Rencana Studi (KRS)',
    desc: 'Pengisian dan status persetujuan KRS',
    icon: FileSpreadsheet,
    to: '/enrollments',
    color: 'text-brand-600 bg-brand-50 border-brand-200',
  },
  {
    title: 'Hasil Studi (KHS)',
    desc: 'Capaian nilai & indeks prestasi semester',
    icon: Award,
    to: '/khs',
    color: 'text-amber-600 bg-amber-50 border-amber-200',
  },
  {
    title: 'Jadwal Kuliah',
    desc: 'Jadwal mingguan & lokasi ruang kelas',
    icon: Calendar,
    to: '/my-schedule',
    color: 'text-sky-600 bg-sky-50 border-sky-200',
  },
  {
    title: 'Kehadiran Saya',
    desc: 'Presensi mandiri & kelayakan UAS',
    icon: QrCode,
    to: '/attendance/my',
    color: 'text-emerald-600 bg-emerald-50 border-emerald-200',
  },
  {
    title: 'Profil Mahasiswa',
    desc: 'Biodata diri & ajukan perubahan data',
    icon: GraduationCap,
    to: '/student/profile',
    color: 'text-indigo-600 bg-indigo-50 border-indigo-200',
  },
  {
    title: 'Bimbingan PA',
    desc: 'Konsultasi akademik & catatan bimbingan',
    icon: UserCheck,
    to: '/advising',
    color: 'text-purple-600 bg-purple-50 border-purple-200',
  },
]

onMounted(() => {
  loadDashboardData()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Student Hero Welcome Card -->
    <div class="bg-gradient-to-r from-brand-900 via-brand-800 to-indigo-950 rounded-2xl p-6 text-white relative overflow-hidden shadow-md">
      <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-brand-500/10 rounded-full blur-3xl pointer-events-none" />

      <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div class="flex items-start gap-4">
          <div class="w-16 h-16 rounded-2xl bg-white/10 backdrop-blur-md border border-white/20 flex items-center justify-center text-white text-2xl font-bold uppercase shrink-0 shadow-inner">
            {{ (student?.full_name || 'M').substring(0, 2) }}
          </div>
          <div>
            <div class="flex flex-wrap items-center gap-2 mb-1">
              <h1 class="text-xl font-bold text-white tracking-tight">
                Selamat Datang, {{ student?.full_name || 'Mahasiswa' }}
              </h1>
              <Badge variant="gold-glass" size="xs">
                {{ student?.student_number || '-' }}
              </Badge>
            </div>
            <p class="text-xs text-brand-200 flex items-center gap-2">
              <GraduationCap class="w-3.5 h-3.5" />
              <span>{{ student?.study_program?.name || 'Program Studi' }} &bull; Semester {{ student?.academic_summary?.current_semester ?? 1 }}</span>
            </p>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <router-link to="/student/profile">
            <Button variant="secondary" size="sm" class="bg-white/10 hover:bg-white/20 text-white border-white/20 text-xs">
              Lihat Profil Saya
            </Button>
          </router-link>
        </div>
      </div>
    </div>

    <!-- Student Key Metrics Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
      <!-- Metrik 1: IPK -->
      <Card class="p-4 bg-white border border-slate-200 shadow-2xs">
        <div class="flex items-center justify-between text-slate-500 mb-2">
          <span class="text-2xs uppercase font-semibold tracking-wider">IPK Kumulatif</span>
          <Award class="w-4 h-4 text-amber-500" />
        </div>
        <div class="text-2xl font-bold text-slate-900 font-mono">
          {{ Number(student?.academic_summary?.cumulative_gpa ?? 0).toFixed(2) }}
        </div>
        <div class="text-2xs text-slate-500 font-medium mt-0.5">
          {{ student?.academic_summary?.total_credits_passed ?? 0 }} SKS Lulus
        </div>
      </Card>

      <!-- Metrik 2: Beban SKS Semester Ini -->
      <Card class="p-4 bg-white border border-slate-200 shadow-2xs">
        <div class="flex items-center justify-between text-slate-500 mb-2">
          <span class="text-2xs uppercase font-semibold tracking-wider">KRS Semester Ini</span>
          <BookOpen class="w-4 h-4 text-brand-600" />
        </div>
        <div class="text-2xl font-bold text-slate-900 font-mono">
          {{ schedules.reduce((sum, s) => sum + (s.course?.credits || 0), 0) }} <span class="text-xs font-normal text-slate-500">SKS</span>
        </div>
        <div class="text-2xs font-medium mt-0.5 flex items-center gap-1">
          <template v-if="schedules.length > 0">
            <CheckCircle2 class="w-3 h-3 text-emerald-500" />
            <span class="text-emerald-600 font-semibold">KRS Terdaftar</span>
          </template>
          <template v-else>
            <span class="text-amber-600 font-medium">Belum Mengisi KRS</span>
          </template>
        </div>
      </Card>

      <!-- Metrik 3: Kehadiran -->
      <Card class="p-4 bg-white border border-slate-200 shadow-2xs">
        <div class="flex items-center justify-between text-slate-500 mb-2">
          <span class="text-2xs uppercase font-semibold tracking-wider">Kehadiran Kuliah</span>
          <CheckCircle2 class="w-4 h-4 text-emerald-500" />
        </div>
        <div class="text-2xl font-bold text-slate-900 font-mono">
          {{ attendanceRecap?.summary?.overall_percentage ?? 0 }}%
        </div>
        <div
          :class="[
            'text-2xs font-semibold mt-0.5',
            (attendanceRecap?.summary?.overall_percentage ?? 0) >= 75 ? 'text-emerald-600' : 'text-slate-500'
          ]"
        >
          {{ (attendanceRecap?.summary?.overall_percentage ?? 0) >= 75 ? 'Layak UAS (≥75%)' : 'Belum Terisi Presensi' }}
        </div>
      </Card>

      <!-- Metrik 4: Dosen PA -->
      <Card class="p-4 bg-white border border-slate-200 shadow-2xs">
        <div class="flex items-center justify-between text-slate-500 mb-2">
          <span class="text-2xs uppercase font-semibold tracking-wider">Dosen Pembimbing</span>
          <UserCheck class="w-4 h-4 text-purple-600" />
        </div>
        <div class="text-xs font-bold text-slate-900 truncate">
          {{ student?.advisor?.name || 'Dr. Ahmad Dosen, M.Kom' }}
        </div>
        <div class="text-2xs text-slate-500 font-mono mt-0.5">
          NIDN: {{ student?.advisor?.nidn || '0011223301' }}
        </div>
      </Card>
    </div>

    <!-- 2 Column Layout: Jadwal Hari Ini & Timeline Akademik -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Kolom Kiri: Jadwal Kuliah Hari Ini -->
      <div class="lg:col-span-2 space-y-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <Calendar class="w-4 h-4 text-brand-600" />
            <h2 class="text-sm font-bold text-slate-900">
              Jadwal Kuliah Hari Ini &bull; {{ currentDayName }}
            </h2>
          </div>
          <router-link to="/my-schedule" class="text-2xs font-semibold text-brand-600 hover:text-brand-700 flex items-center gap-1">
            Lihat Jadwal Lengkap <ArrowRight class="w-3 h-3" />
          </router-link>
        </div>

        <div v-if="todayClasses.length > 0" class="space-y-3">
          <Card
            v-for="item in todayClasses"
            :key="item.id"
            class="p-4 bg-white border border-slate-200 hover:border-brand-300 rounded-xl transition-all shadow-2xs flex flex-col sm:flex-row sm:items-center justify-between gap-3"
          >
            <div class="space-y-1">
              <div class="flex items-center gap-2">
                <Badge variant="primary" size="sm" class="font-mono">
                  {{ item.course?.code }} &bull; Kelas {{ item.section }}
                </Badge>
                <span class="text-2xs font-bold text-slate-600">{{ item.course?.credits }} SKS</span>
              </div>
              <h3 class="text-sm font-bold text-slate-900">
                {{ item.course?.name }}
              </h3>
              <p class="text-2xs text-slate-500">
                Dosen: {{ item.lecturers?.join(', ') || 'Dosen Pengampu' }}
              </p>
            </div>

            <div class="flex sm:flex-col items-start sm:items-end justify-between sm:justify-center gap-1 shrink-0 pt-2 sm:pt-0 border-t sm:border-t-0 border-slate-100">
              <div class="flex items-center gap-1.5 text-xs font-mono font-bold text-slate-900">
                <Clock class="w-3.5 h-3.5 text-brand-600" />
                {{ item.start_time?.substring(0, 5) }} - {{ item.end_time?.substring(0, 5) }} WIB
              </div>
              <div class="flex items-center gap-1 text-2xs text-slate-500">
                <MapPin class="w-3 h-3 text-sky-600" />
                <span>R. {{ item.room?.code || 'Online' }}</span>
              </div>
            </div>
          </Card>
        </div>

        <Card v-else class="p-8 bg-white border border-slate-200 text-center text-slate-500 rounded-xl">
          <Calendar class="w-8 h-8 text-slate-300 mx-auto mb-2" />
          <p class="text-xs font-semibold text-slate-700">Tidak ada jadwal perkuliahan hari {{ currentDayName }}</p>
          <p class="text-2xs text-slate-400 mt-0.5">Gunakan waktu luang untuk belajar mandiri atau konsultasi tugas.</p>
        </Card>
      </div>

      <!-- Kolom Kanan: Timeline & Pengumuman Akademik -->
      <div class="space-y-4">
        <div class="flex items-center gap-2">
          <Sparkles class="w-4 h-4 text-amber-500" />
          <h2 class="text-sm font-bold text-slate-900">
            Agenda & Kalender Akademik
          </h2>
        </div>

        <Card class="p-4 bg-white border border-slate-200 rounded-xl space-y-3.5 shadow-2xs">
          <div class="flex items-start gap-3 pb-3 border-b border-slate-100">
            <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 font-bold text-xs">
              01
            </div>
            <div class="text-xs">
              <span class="font-bold text-slate-900 block">Masa Perkuliahan Semester Gasal</span>
              <span class="text-2xs text-slate-500">01 Sep 2025 – 31 Jan 2026</span>
            </div>
          </div>

          <div class="flex items-start gap-3 pb-3 border-b border-slate-100">
            <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center shrink-0 font-bold text-xs">
              20
            </div>
            <div class="text-xs">
              <span class="font-bold text-slate-900 block">Ujian Tengah Semester (UTS)</span>
              <span class="text-2xs text-slate-500">20 Okt 2025 – 31 Okt 2025</span>
            </div>
          </div>

          <div class="flex items-start gap-3">
            <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0 font-bold text-xs">
              15
            </div>
            <div class="text-xs">
              <span class="font-bold text-slate-900 block">Ujian Akhir Semester (UAS)</span>
              <span class="text-2xs text-slate-500">15 Des 2025 – 31 Des 2025</span>
            </div>
          </div>
        </Card>
      </div>
    </div>

    <!-- Quick Navigation Modules for Students -->
    <div>
      <h2 class="text-sm font-bold text-slate-900 tracking-tight mb-3">
        Layanan Mandiri Mahasiswa
      </h2>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
        <router-link
          v-for="mod in quickActions"
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
