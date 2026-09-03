<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import {
  Users,
  School,
  FileSpreadsheet,
  Calendar,
  CheckCircle2,
  ArrowRight,
  UserCheck,
  QrCode,
  BookOpen,
  Award,
} from 'lucide-vue-next'
import { advisingService } from '@/services/api/advising'
import { enrollmentService } from '@/services/api/enrollments'
import { classService } from '@/services/api/classes'
import { attendanceService } from '@/services/api/attendance'
import { useAuth } from '@/composables/useAuth'
import type { AcademicAdvisor } from '@/types/advising'
import type { StudentEnrollment } from '@/types/enrollment'
import type { AcademicClass } from '@/types/class'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'

const { user } = useAuth()
const loading = ref<boolean>(true)

const advisees = ref<AcademicAdvisor[]>([])
const pendingKrs = ref<StudentEnrollment[]>([])
const classes = ref<AcademicClass[]>([])
const completedSessionsCount = ref<number>(0)

async function loadLecturerDashboard() {
  loading.value = true
  try {
    const [advRes, enrRes, clsRes, sesRes] = await Promise.allSettled([
      advisingService.listAdvisors({ per_page: 50 }),
      enrollmentService.list({ status: 'submitted', per_page: 50 }),
      classService.list({ per_page: 50 }),
      attendanceService.getSessions({ status: 'closed', per_page: 100 }),
    ])

    if (advRes.status === 'fulfilled' && advRes.value.data) {
      advisees.value = advRes.value.data
    }
    if (enrRes.status === 'fulfilled' && enrRes.value.data) {
      pendingKrs.value = enrRes.value.data
    }
    if (clsRes.status === 'fulfilled' && clsRes.value.data) {
      classes.value = clsRes.value.data
    }
    if (sesRes.status === 'fulfilled' && sesRes.value.data) {
      completedSessionsCount.value = sesRes.value.data.length
    }
  } finally {
    loading.value = false
  }
}

const activeAdviseesCount = computed(() => advisees.value.filter((a) => a.status === 'active').length)
const pendingKrsCount = computed(() => pendingKrs.value.length)

const lecturerQuickModules = [
  {
    title: 'Presensi & BAP Kelas',
    desc: 'Buka token presensi mandiri & input presensi',
    icon: QrCode,
    to: '/attendance',
    color: 'text-brand-600 bg-brand-50 border-brand-200',
  },
  {
    title: 'Bimbingan PA & Review KRS',
    desc: 'Validasi rencana studi & catat konsultasi',
    icon: UserCheck,
    to: '/advising',
    color: 'text-indigo-600 bg-indigo-50 border-indigo-200',
  },
  {
    title: 'Input & Rekap Nilai Kelas',
    desc: 'Input nilai tugas, UTS, UAS, & finalisasi nilai',
    icon: Award,
    to: '/classes',
    color: 'text-amber-600 bg-amber-50 border-amber-200',
  },
  {
    title: 'Jadwal Mengajar',
    desc: 'Jadwal mengajar mingguan dan alokasi ruang',
    icon: Calendar,
    to: '/schedules',
    color: 'text-sky-600 bg-sky-50 border-sky-200',
  },
  {
    title: 'Katalog Mata Kuliah',
    desc: 'Struktur kurikulum OBE & prasyarat MK',
    icon: BookOpen,
    to: '/courses',
    color: 'text-purple-600 bg-purple-50 border-purple-200',
  },
  {
    title: 'Rekapitulasi KRS',
    desc: 'Monitoring distribusi SKS mahasiswa',
    icon: FileSpreadsheet,
    to: '/enrollments',
    color: 'text-emerald-600 bg-emerald-50 border-emerald-200',
  },
]

onMounted(() => {
  loadLecturerDashboard()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Lecturer Hero Welcome Card -->
    <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-brand-950 rounded-2xl p-6 text-white relative overflow-hidden shadow-md">
      <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none" />

      <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div class="flex items-start gap-4">
          <div class="w-16 h-16 rounded-2xl bg-white/10 backdrop-blur-md border border-white/20 flex items-center justify-center text-white text-2xl font-bold uppercase shrink-0 shadow-inner">
            {{ (user?.name || 'D').substring(0, 2) }}
          </div>
          <div>
            <div class="flex flex-wrap items-center gap-2 mb-1">
              <h1 class="text-xl font-bold text-white tracking-tight">
                Selamat Datang, {{ user?.name || 'Dosen Pengampu' }}
              </h1>
              <Badge variant="gold-glass" size="xs">
                Dosen Akademik
              </Badge>
            </div>
            <p class="text-xs text-slate-300 flex items-center gap-2">
              <School class="w-3.5 h-3.5 text-brand-400" />
              <span>Institut Agama Islam (IAI) &bull; Semester Aktif</span>
            </p>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <router-link to="/attendance">
            <Button variant="primary" size="sm" class="gap-1.5 text-xs bg-brand-600 hover:bg-brand-500">
              <QrCode class="w-3.5 h-3.5" />
              Buka Presensi Kuliah
            </Button>
          </router-link>
        </div>
      </div>
    </div>

    <!-- Key Metrics Grid for Lecturer -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
      <Card class="p-4 bg-white border border-slate-200 shadow-2xs">
        <div class="flex items-center justify-between text-slate-500 mb-2">
          <span class="text-2xs uppercase font-semibold tracking-wider">Kelas Pengampu</span>
          <School class="w-4 h-4 text-brand-600" />
        </div>
        <div class="text-2xl font-bold text-slate-900 font-mono">
          {{ classes.length }} <span class="text-xs font-normal text-slate-500">Kelas</span>
        </div>
        <div class="text-2xs text-brand-600 font-medium mt-0.5">
          Semester Aktif
        </div>
      </Card>

      <Card class="p-4 bg-white border border-slate-200 shadow-2xs">
        <div class="flex items-center justify-between text-slate-500 mb-2">
          <span class="text-2xs uppercase font-semibold tracking-wider">Mahasiswa PA</span>
          <Users class="w-4 h-4 text-indigo-600" />
        </div>
        <div class="text-2xl font-bold text-slate-900 font-mono">
          {{ activeAdviseesCount }} <span class="text-xs font-normal text-slate-500">Mahasiswa</span>
        </div>
        <div class="text-2xs text-emerald-600 font-semibold mt-0.5">
          Bimbingan Aktif
        </div>
      </Card>

      <Card class="p-4 bg-white border border-slate-200 shadow-2xs">
        <div class="flex items-center justify-between text-slate-500 mb-2">
          <span class="text-2xs uppercase font-semibold tracking-wider">KRS Perlu Review</span>
          <FileSpreadsheet class="w-4 h-4 text-amber-600" />
        </div>
        <div class="text-2xl font-bold text-slate-900 font-mono">
          {{ pendingKrsCount }} <span class="text-xs font-normal text-slate-500">Pengajuan</span>
        </div>
        <div class="text-2xs text-amber-600 font-medium mt-0.5">
          {{ pendingKrsCount > 0 ? 'Menunggu Persetujuan' : 'Semua KRS Terverifikasi' }}
        </div>
      </Card>

      <Card class="p-4 bg-white border border-slate-200 shadow-2xs">
        <div class="flex items-center justify-between text-slate-500 mb-2">
          <span class="text-2xs uppercase font-semibold tracking-wider">Pertemuan Selesai</span>
          <CheckCircle2 class="w-4 h-4 text-emerald-600" />
        </div>
        <div class="text-2xl font-bold text-slate-900 font-mono">
          {{ completedSessionsCount }} <span class="text-xs font-normal text-slate-500">Sesi BAP</span>
        </div>
        <div class="text-2xs text-slate-500 mt-0.5">
          {{ completedSessionsCount > 0 ? 'Tersimpan & Tervalidasi' : 'Belum Ada Sesi BAP' }}
        </div>
      </Card>
    </div>

    <!-- Action Center & Pending KRS Queue -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 space-y-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <School class="w-4 h-4 text-brand-600" />
            <h2 class="text-sm font-bold text-slate-900">
              Kelas Pengampu Semester Aktif
            </h2>
          </div>
          <router-link to="/attendance" class="text-2xs font-semibold text-brand-600 hover:text-brand-700 flex items-center gap-1">
            Lihat Semua Presensi <ArrowRight class="w-3 h-3" />
          </router-link>
        </div>

        <div v-if="classes.length > 0" class="space-y-3">
          <Card
            v-for="cls in classes.slice(0, 3)"
            :key="cls.id"
            class="p-4 bg-white border border-slate-200 hover:border-brand-300 rounded-xl transition-all shadow-2xs flex flex-col sm:flex-row sm:items-center justify-between gap-3"
          >
            <div class="space-y-1">
              <div class="flex items-center gap-2">
                <Badge variant="primary" size="sm" class="font-mono">
                  {{ cls.course?.code || cls.code }} &bull; Kelas {{ cls.section }}
                </Badge>
                <span class="text-2xs font-bold text-slate-600">{{ cls.course?.credits || 0 }} SKS</span>
              </div>
              <h3 class="text-sm font-bold text-slate-900">
                {{ cls.name || cls.course?.name }}
              </h3>
              <p class="text-2xs text-slate-500">
                Kapasitas: {{ cls.capacity }} Mahasiswa &bull; Status: {{ cls.status }}
              </p>
            </div>

            <div class="flex items-center gap-2 shrink-0">
              <router-link :to="`/attendance/classes/${cls.id}`">
                <Button variant="secondary" size="sm" class="gap-1 text-xs">
                  <QrCode class="w-3 h-3 text-brand-600" />
                  Presensi
                </Button>
              </router-link>

              <router-link :to="`/classes/${cls.id}?tab=grades`">
                <Button variant="primary" size="sm" class="gap-1 text-xs bg-emerald-600 hover:bg-emerald-700 text-white">
                  <Award class="w-3 h-3" />
                  Input Nilai
                </Button>
              </router-link>
            </div>
          </Card>
        </div>

        <Card v-else class="p-8 bg-white border border-slate-200 text-center text-slate-500 rounded-xl">
          <School class="w-8 h-8 text-slate-300 mx-auto mb-2" />
          <p class="text-xs font-semibold text-slate-700">Belum ada kelas perkuliahan terdaftar.</p>
        </Card>
      </div>

      <!-- Kolom Kanan: Pengajuan KRS Mahasiswa Bimbingan -->
      <div class="space-y-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <UserCheck class="w-4 h-4 text-purple-600" />
            <h2 class="text-sm font-bold text-slate-900">
              Antrean Verifikasi KRS
            </h2>
          </div>
          <router-link to="/advising" class="text-2xs font-semibold text-brand-600 hover:text-brand-700">
            Lihat Workspace
          </router-link>
        </div>

        <Card class="p-4 bg-white border border-slate-200 rounded-xl shadow-2xs">
          <div v-if="pendingKrs.length > 0" class="space-y-3">
            <div
              v-for="enr in pendingKrs.slice(0, 3)"
              :key="enr.id"
              class="p-3 bg-slate-50 rounded-lg border border-slate-200 text-xs space-y-1.5"
            >
              <div class="flex items-center justify-between">
                <span class="font-bold text-slate-900">{{ enr.student?.full_name }}</span>
                <Badge variant="warning" size="xs">Submitted</Badge>
              </div>
              <div class="text-2xs text-slate-500 font-mono">
                NIM: {{ enr.student?.student_number }} &bull; {{ enr.total_credits }} SKS
              </div>
              <router-link :to="`/enrollments/${enr.id}`" class="block pt-1 text-2xs font-bold text-brand-600 hover:text-brand-700">
                Review & Setujui KRS &rarr;
              </router-link>
            </div>
          </div>
          <div v-else class="text-center py-6 text-slate-400 text-xs">
            <CheckCircle2 class="w-6 h-6 text-emerald-400 mx-auto mb-1.5" />
            <span>Tidak ada pengajuan KRS yang pending.</span>
          </div>
        </Card>
      </div>
    </div>

    <!-- Quick Navigation Modules for Lecturers -->
    <div>
      <h2 class="text-sm font-bold text-slate-900 tracking-tight mb-3">
        Layanan Akademik Dosen
      </h2>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
        <router-link
          v-for="mod in lecturerQuickModules"
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
