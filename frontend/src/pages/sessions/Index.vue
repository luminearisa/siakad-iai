<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  CalendarCheck,
  CalendarDays,
  CalendarOff,
  ChevronRight,
  Clock,
  DoorOpen,
  Layers,
  Search,
  Users,
} from 'lucide-vue-next'
import { classService } from '@/services/api/classes'
import { attendanceService } from '@/services/api/attendance'
import { usePermissions } from '@/composables/usePermissions'
import { useAuth } from '@/composables/useAuth'
import { useToast } from '@/composables/useToast'
import { formatDate, formatTime } from '@/utils/format'
import type { AcademicClass } from '@/types/class'
import type { TeachingSession } from '@/types/attendance'
import type { ApiMeta } from '@/types/api'
import PageContainer from '@/components/data-display/PageContainer.vue'
import PageHeader from '@/components/data-display/PageHeader.vue'
import DataTable, { type Column } from '@/components/data-display/DataTable.vue'
import Pagination from '@/components/data-display/Pagination.vue'
import Card from '@/components/ui/Card.vue'
import Input from '@/components/ui/Input.vue'
import Button from '@/components/ui/Button.vue'
import Badge from '@/components/ui/Badge.vue'
import PersonalScopeNotice from '@/components/feedback/PersonalScopeNotice.vue'

const router = useRouter()
const toast = useToast()
const { hasRole } = usePermissions()
const { isLecturer } = useAuth()

/**
 * A lecturer lands here to answer one question: "what do I teach today, and let
 * me mark it". Staff use the same page as a monitoring overview.
 */
const isLecturerView = computed<boolean>(() => isLecturer.value)

const classes = ref<AcademicClass[]>([])
const loading = ref<boolean>(false)
const search = ref<string>('')

const todaySessions = ref<TeachingSession[]>([])
const todayLoading = ref<boolean>(false)

/** Recent sessions: powers the per-class progress and the staff monitoring table. */
const recentSessions = ref<TeachingSession[]>([])
const sessionStatusFilter = ref<string>('')

const meta = ref<ApiMeta>({
  current_page: 1,
  last_page: 1,
  per_page: 25,
  total: 0,
  from: 0,
  to: 0,
})

const TARGET_MEETINGS = 16

const columns = computed<Column<AcademicClass>[]>(() => {
  const base: Column<AcademicClass>[] = [
    { key: 'code', label: 'Kode Kelas', width: '150px' },
    { key: 'name', label: 'Nama Kelas / Mata Kuliah' },
    { key: 'study_program', label: 'Program Studi' },
  ]

  // A lecturer's own name in every row is noise, so swap it for progress.
  if (isLecturerView.value) {
    base.push({ key: 'meetings', label: 'Pertemuan', width: '150px', align: 'center' })
  } else {
    base.push({ key: 'lecturers', label: 'Dosen Pengampu' })
  }

  base.push(
    { key: 'status', label: 'Status Kelas', width: '110px', align: 'center' },
    { key: 'actions', label: 'Aksi', align: 'right', width: '175px' },
  )

  return base
})

/** Local (not UTC) YYYY-MM-DD so "today" matches the user's calendar. */
function todayIso(): string {
  const now = new Date()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')
  return `${now.getFullYear()}-${month}-${day}`
}

const todayLabel = computed<string>(() =>
  new Intl.DateTimeFormat('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  }).format(new Date()),
)

const todaySessionClassIds = computed<number[]>(() =>
  todaySessions.value.map((s) => s.academic_class_id),
)

/** class_id -> number of sessions already created for that class. */
const sessionCountByClass = computed<Record<number, number>>(() => {
  const counts: Record<number, number> = {}
  for (const session of recentSessions.value) {
    counts[session.academic_class_id] = (counts[session.academic_class_id] || 0) + 1
  }
  return counts
})

const monitoringSessions = computed<TeachingSession[]>(() => {
  const list = sessionStatusFilter.value
    ? recentSessions.value.filter((s) => s.status === sessionStatusFilter.value)
    : recentSessions.value

  const query = search.value.trim().toLowerCase()
  if (!query) return list.slice(0, 30)

  return list
    .filter((s) =>
      s.academic_class?.course?.name?.toLowerCase().includes(query) ||
      s.academic_class?.name?.toLowerCase().includes(query) ||
      s.academic_class?.code?.toLowerCase().includes(query) ||
      s.lecturer?.full_name?.toLowerCase().includes(query) ||
      s.topic?.toLowerCase().includes(query),
    )
    .slice(0, 30)
})

async function loadClasses() {
  loading.value = true
  try {
    const res = await classService.list({
      search: search.value || undefined,
      page: meta.value.current_page,
      per_page: meta.value.per_page,
    })
    classes.value = res.data || []
    if (res.meta) {
      meta.value = res.meta
    }
  } catch (err: any) {
    classes.value = []
    toast.error(err.response?.data?.message || 'Gagal memuat daftar kelas')
  } finally {
    loading.value = false
  }
}

async function loadTodaySessions() {
  todayLoading.value = true
  try {
    const res = await attendanceService.getSessions({ date: todayIso(), per_page: 100 })
    // Earliest first — the lecturer walks through their day in order.
    todaySessions.value = [...(res.data || [])].sort((a, b) =>
      String(a.start_time || '').localeCompare(String(b.start_time || '')),
    )
  } catch {
    todaySessions.value = []
  } finally {
    todayLoading.value = false
  }
}

async function loadRecentSessions() {
  try {
    const res = await attendanceService.getSessions({ per_page: 100 })
    recentSessions.value = res.data || []
  } catch {
    recentSessions.value = []
  }
}

function handleSearch() {
  meta.value.current_page = 1
  loadClasses()
}

function handlePageChange(page: number) {
  meta.value.current_page = page
  loadClasses()
}

function meetingCount(classId: number): number {
  return sessionCountByClass.value[classId] || 0
}

function isTodayClass(classId: number): boolean {
  return todaySessionClassIds.value.includes(classId)
}

function sessionClassName(session: TeachingSession): string {
  return (
    session.academic_class?.course?.name ||
    session.academic_class?.name ||
    session.academic_class?.code ||
    'Mata kuliah'
  )
}

function sessionClassCode(session: TeachingSession): string {
  return (
    session.academic_class?.code ||
    session.academic_class?.name ||
    `Kelas #${session.academic_class_id}`
  )
}

function statusVariant(status?: string): 'success' | 'neutral' | 'info' {
  if (status === 'open') return 'success'
  if (status === 'closed') return 'neutral'
  return 'info'
}

function statusLabel(status?: string): string {
  if (status === 'open') return 'Berlangsung'
  if (status === 'closed') return 'Selesai'
  if (status === 'cancelled') return 'Dibatalkan'
  return 'Terjadwal'
}

/** Open the class page with the session preselected so the absen sheet opens directly. */
function openSessionAttendance(session: TeachingSession) {
  router.push({
    path: `/attendance/classes/${session.academic_class_id}`,
    query: { session: String(session.id) },
  })
}

function openClassAttendance(classId: number) {
  router.push(`/attendance/classes/${classId}`)
}

function navigateToMyAttendance() {
  router.push('/attendance/my')
}

onMounted(() => {
  loadClasses()
  loadTodaySessions()
  loadRecentSessions()
})
</script>

<template>
  <PageContainer>
    <PageHeader
      :title="isLecturerView ? 'Presensi Mengajar Saya' : 'Presensi & Sesi Perkuliahan'"
      :subtitle="isLecturerView
        ? 'Pilih kelas atau pertemuan hari ini, lalu tandai kehadiran mahasiswa dalam sekali simpan'
        : 'Pantau sesi perkuliahan, Berita Acara (BAP), dan presensi mahasiswa per pertemuan'"
      :breadcrumbs="[
        { label: 'Dashboard', to: '/dashboard' },
        { label: 'Operasional Perkuliahan' },
        { label: 'Presensi Perkuliahan' },
      ]"
    >
      <template #actions>
        <Button
          v-if="hasRole('mahasiswa')"
          variant="primary"
          size="md"
          @click="navigateToMyAttendance"
        >
          <CalendarCheck class="w-4 h-4" />
          <span>Kehadiran Saya (Mahasiswa)</span>
        </Button>
      </template>
    </PageHeader>

    <div class="space-y-4">
      <PersonalScopeNotice
        v-if="isLecturerView"
        subject="kelas yang Anda ampu"
      />

      <!-- ============================ TODAY ============================ -->
      <Card>
        <template #header>
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2.5">
              <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-brand-50 border border-brand-200 shrink-0">
                <CalendarDays class="w-4 h-4 text-brand-800" />
              </span>
              <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
                  {{ isLecturerView ? 'Jadwal Mengajar Hari Ini' : 'Sesi Perkuliahan Hari Ini' }}
                </h3>
                <p class="text-2xs text-slate-500 mt-0.5">{{ todayLabel }}</p>
              </div>
            </div>

            <Badge :variant="todaySessions.length > 0 ? 'success' : 'neutral'" size="sm">
              {{ todaySessions.length }} sesi
            </Badge>
          </div>
        </template>

        <div v-if="todayLoading" class="py-10 text-center text-xs text-slate-400">
          Memuat jadwal hari ini...
        </div>

        <div v-else-if="todaySessions.length === 0" class="py-10 text-center">
          <CalendarOff class="w-9 h-9 text-slate-300 mx-auto mb-2" />
          <p class="text-xs font-semibold text-slate-700">
            {{ isLecturerView ? 'Tidak ada jadwal mengajar hari ini' : 'Tidak ada sesi perkuliahan hari ini' }}
          </p>
          <p class="text-2xs text-slate-400 mt-1">
            Buka salah satu kelas di bawah untuk membuka atau mengisi presensi pertemuan lainnya.
          </p>
        </div>

        <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-3">
          <div
            v-for="session in todaySessions"
            :key="session.id"
            class="rounded-lg border border-slate-200 hover:border-brand-300 hover:shadow-subtle transition-all p-3.5 flex flex-col gap-3"
          >
            <div class="flex items-start justify-between gap-2">
              <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-slate-900 text-white font-mono text-2xs font-bold">
                  <Clock class="w-3 h-3" />
                  {{ formatTime(session.start_time) }}–{{ formatTime(session.end_time) }}
                </span>
                <span class="px-2 py-1 rounded-md bg-brand-50 border border-brand-200 text-brand-900 font-mono text-2xs font-bold">
                  P{{ session.meeting_number }}
                </span>
              </div>

              <Badge :variant="statusVariant(session.status)" size="xs">
                {{ statusLabel(session.status) }}
              </Badge>
            </div>

            <div class="min-w-0">
              <div class="flex items-center gap-1.5 flex-wrap">
                <span class="font-mono text-2xs font-bold text-slate-700 bg-slate-100 px-1.5 py-0.5 rounded">
                  {{ sessionClassCode(session) }}
                </span>
                <span v-if="session.room" class="inline-flex items-center gap-1 text-2xs text-slate-500">
                  <DoorOpen class="w-3 h-3" />
                  {{ session.room.code || session.room.name }}
                </span>
              </div>
              <h4 class="font-bold text-sm text-slate-900 mt-1 truncate">
                {{ sessionClassName(session) }}
              </h4>
              <p class="text-2xs text-slate-500 truncate mt-0.5">
                {{ session.topic || 'Topik belum diisi' }}
              </p>
            </div>

            <div class="flex items-center justify-between gap-2 pt-2 border-t border-slate-100">
              <span class="inline-flex items-center gap-1 text-2xs text-slate-500 font-medium">
                <Users class="w-3.5 h-3.5" />
                {{ session.attendances_count ?? 0 }} presensi tercatat
              </span>

              <Button variant="primary" size="sm" @click="openSessionAttendance(session)">
                <CalendarCheck class="w-3.5 h-3.5" />
                <span>Absen Sekarang</span>
                <ChevronRight class="w-3.5 h-3.5" />
              </Button>
            </div>
          </div>
        </div>
      </Card>

      <!-- ============================ CLASS LIST ============================ -->
      <Card>
        <template #header>
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
              <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 border border-slate-200 shrink-0">
                <Layers class="w-4 h-4 text-slate-700" />
              </span>
              <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
                  {{ isLecturerView ? 'Kelas Yang Saya Ampu' : 'Daftar Kelas Perkuliahan' }}
                </h3>
                <p class="text-2xs text-slate-500 mt-0.5">
                  Buka kelas untuk mengelola BAP, presensi per pertemuan, dan matriks 1–16
                </p>
              </div>
            </div>

            <div class="flex items-center gap-2 w-full sm:w-auto">
              <div class="relative flex-1 sm:w-72">
                <Input
                  v-model="search"
                  placeholder="Cari kode kelas, mata kuliah, atau dosen..."
                  size="sm"
                  @keyup.enter="handleSearch"
                >
                  <template #prefix>
                    <Search class="w-3.5 h-3.5 text-slate-400" />
                  </template>
                </Input>
              </div>
              <Button variant="outline" size="sm" :loading="loading" @click="handleSearch">
                <Search class="w-3.5 h-3.5" />
                <span>Cari</span>
              </Button>
            </div>
          </div>
        </template>

        <DataTable
          :columns="columns"
          :rows="classes"
          :loading="loading"
          empty-title="Belum ada kelas perkuliahan"
          empty-description="Kelas perkuliahan akan muncul di sini setelah dibuat pada modul Kelas."
        >
          <template #cell-code="{ value, row }">
            <div class="flex items-center gap-1.5 flex-wrap">
              <span class="font-mono font-bold text-xs text-brand-900 bg-brand-50 px-2 py-0.5 rounded border border-brand-200">
                {{ value }}
              </span>
              <span
                v-if="isTodayClass(row.id)"
                class="px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 text-3xs font-bold uppercase tracking-wide"
                title="Ada sesi perkuliahan hari ini"
              >
                Hari ini
              </span>
            </div>
          </template>

          <template #cell-name="{ row }">
            <div>
              <span class="font-bold text-slate-900 text-xs block">
                {{ row.name }}
              </span>
              <span class="text-2xs text-slate-400">
                Mata Kuliah: {{ row.course?.code }} — {{ row.course?.name }} ({{ row.course?.credits || 0 }} SKS)
              </span>
            </div>
          </template>

          <template #cell-study_program="{ row }">
            <span class="text-xs text-slate-700">
              {{ row.study_program?.name || 'Bersama / Umum' }}
            </span>
          </template>

          <template #cell-lecturers="{ row }">
            <div class="text-xs text-slate-700">
              <span v-if="row.lecturers && row.lecturers.length > 0">
                {{ row.lecturers.map((l: any) => l.full_name).join(', ') }}
              </span>
              <span v-else class="text-slate-400 italic">Belum ditentukan</span>
            </div>
          </template>

          <template #cell-meetings="{ row }">
            <div class="flex flex-col items-center gap-1">
              <span class="font-mono text-xs font-bold text-slate-800">
                {{ meetingCount(row.id) }}<span class="text-slate-400 font-normal"> / {{ TARGET_MEETINGS }}</span>
              </span>
              <div class="w-24 h-1.5 rounded-full bg-slate-100 overflow-hidden">
                <div
                  class="h-full rounded-full transition-all"
                  :class="meetingCount(row.id) >= TARGET_MEETINGS ? 'bg-emerald-500' : 'bg-brand-600'"
                  :style="{ width: `${Math.min(100, (meetingCount(row.id) / TARGET_MEETINGS) * 100)}%` }"
                />
              </div>
            </div>
          </template>

          <template #cell-status="{ row }">
            <Badge
              :variant="row.status === 'open' ? 'success' : (row.status === 'cancelled' ? 'danger' : 'neutral')"
              size="sm"
            >
              {{ row.status === 'open' ? 'Buka' : (row.status === 'closed' ? 'Tutup' : 'Draft') }}
            </Badge>
          </template>

          <template #cell-actions="{ row }">
            <div class="flex items-center justify-end">
              <Button
                variant="outline"
                size="xs"
                class="border-brand-300 text-brand-800 hover:bg-brand-50"
                @click="openClassAttendance(row.id)"
              >
                <CalendarCheck class="w-3.5 h-3.5" />
                <span>{{ isLecturerView ? 'Absen / Kelola' : 'Kelola Presensi' }}</span>
              </Button>
            </div>
          </template>
        </DataTable>

        <Pagination
          :current-page="meta.current_page"
          :last-page="meta.last_page"
          :total="meta.total"
          :per-page="meta.per_page"
          :from="meta.from"
          :to="meta.to"
          @page-change="handlePageChange"
        />
      </Card>

      <!-- ==================== SESSION MONITORING (staff) ==================== -->
      <Card v-if="!isLecturerView">
        <template #header>
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
                Sesi Perkuliahan Terbaru
              </h3>
              <p class="text-2xs text-slate-500 mt-0.5">
                {{ monitoringSessions.length }} sesi terakhir — klik Absen untuk membuka lembar presensi
              </p>
            </div>

            <select
              v-model="sessionStatusFilter"
              class="px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg text-2xs font-medium focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600 outline-none"
            >
              <option value="">Semua Status</option>
              <option value="scheduled">Terjadwal</option>
              <option value="open">Berlangsung</option>
              <option value="closed">Selesai</option>
            </select>
          </div>
        </template>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead class="bg-slate-100/90 text-2xs uppercase tracking-wider text-slate-700 font-bold border-b border-slate-200">
              <tr>
                <th class="py-2.5 px-3 min-w-[200px]">Kelas</th>
                <th class="py-2.5 px-3 w-16 text-center">Sesi</th>
                <th class="py-2.5 px-3 w-40">Tanggal</th>
                <th class="py-2.5 px-3 min-w-[160px]">Pengajar</th>
                <th class="py-2.5 px-3 w-32 text-center">Presensi</th>
                <th class="py-2.5 px-3 w-32 text-center">Status</th>
                <th class="py-2.5 px-3 w-28 text-center">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="monitoringSessions.length === 0">
                <td colspan="7" class="py-10 text-center text-slate-400">
                  Belum ada sesi perkuliahan yang cocok dengan filter.
                </td>
              </tr>
              <tr
                v-for="session in monitoringSessions"
                :key="session.id"
                class="hover:bg-slate-50/70 transition-colors"
              >
                <td class="py-2.5 px-3">
                  <span class="font-bold text-slate-900 block">{{ sessionClassName(session) }}</span>
                  <span class="font-mono text-2xs text-slate-400">{{ sessionClassCode(session) }}</span>
                </td>
                <td class="py-2.5 px-3 text-center font-mono font-bold text-slate-800">
                  {{ session.meeting_number }}
                </td>
                <td class="py-2.5 px-3">
                  <span class="text-slate-800 font-medium block">{{ formatDate(session.session_date) }}</span>
                  <span class="text-2xs text-slate-400 font-mono">
                    {{ formatTime(session.start_time) }}–{{ formatTime(session.end_time) }}
                  </span>
                </td>
                <td class="py-2.5 px-3 text-slate-700">{{ session.lecturer?.full_name || '--' }}</td>
                <td class="py-2.5 px-3 text-center">
                  <span class="font-mono text-2xs font-bold text-slate-700">
                    {{ session.attendances_count ?? 0 }} tercatat
                  </span>
                </td>
                <td class="py-2.5 px-3 text-center">
                  <Badge :variant="statusVariant(session.status)" size="xs">
                    {{ statusLabel(session.status) }}
                  </Badge>
                </td>
                <td class="py-2.5 px-3 text-center">
                  <Button variant="outline" size="xs" @click="openSessionAttendance(session)">
                    <CalendarCheck class="w-3.5 h-3.5" />
                    <span>Absen</span>
                  </Button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  </PageContainer>
</template>
