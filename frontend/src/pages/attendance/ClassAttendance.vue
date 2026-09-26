<script setup lang="ts">
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  Plus,
  Edit,
  Trash2,
  QrCode,
  CheckCircle2,
  ChevronLeft,
  CalendarDays,
  Clock,
  DoorOpen,
  Users,
  AlertTriangle,
  ListChecks,
  RefreshCw,
  Unlock,
} from 'lucide-vue-next'
import { attendanceService } from '@/services/api/attendance'
import { classService } from '@/services/api/classes'
import { useToast } from '@/composables/useToast'
import { getErrorMessage } from '@/utils/errors'
import { formatDate, formatTime } from '@/utils/format'
import type { AcademicClass } from '@/types/class'
import type {
  AttendanceThresholdStage,
  ClassAttendanceRecap,
  TeachingSession,
} from '@/types/attendance'
import PageContainer from '@/components/data-display/PageContainer.vue'
import PageHeader from '@/components/data-display/PageHeader.vue'
import Tabs, { type TabItem } from '@/components/ui/Tabs.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Badge from '@/components/ui/Badge.vue'
import Alert from '@/components/ui/Alert.vue'
import Modal from '@/components/ui/Modal.vue'
import FormField from '@/components/form/FormField.vue'
import Textarea from '@/components/ui/Textarea.vue'
import ConfirmModal from '@/components/feedback/ConfirmModal.vue'
import TeachingMethodBadge from './components/TeachingMethodBadge.vue'
import ExamEligibilityBadge from './components/ExamEligibilityBadge.vue'
import SessionModal from './components/SessionModal.vue'
import QuickCheckInModal from './components/QuickCheckInModal.vue'
import BatchAttendanceModal from './components/BatchAttendanceModal.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const classId = computed(() => Number(route.params.id))

// Attendance comes first: a lecturer lands here to mark students, not to read
// a matrix of every meeting the class has.
const activeTab = ref<string>('sessions')

const academicClass = ref<AcademicClass | null>(null)
const recapData = ref<ClassAttendanceRecap | null>(null)
const sessions = ref<TeachingSession[]>([])
const loading = ref<boolean>(false)

// Modals
const sessionModalOpen = ref<boolean>(false)
const selectedSession = ref<TeachingSession | null>(null)
const quickCheckInModalOpen = ref<boolean>(false)
const batchModalOpen = ref<boolean>(false)
const activeSessionForAction = ref<TeachingSession | null>(null)

const deleteModalOpen = ref<boolean>(false)
const sessionToDelete = ref<TeachingSession | null>(null)
const deleting = ref<boolean>(false)

// Reopen a closed session (server requires a reason) and schedule sync.
const reopenModalOpen = ref<boolean>(false)
const sessionToReopen = ref<TeachingSession | null>(null)
const reopenReason = ref<string>('')
const reopenError = ref<string | null>(null)
const reopening = ref<boolean>(false)
const syncing = ref<boolean>(false)

const tabs = computed<TabItem[]>(() => [
  { id: 'sessions', label: 'Pertemuan & Presensi' },
  { id: 'bap', label: 'Berita Acara (BAP)' },
  { id: 'matrix', label: `Matriks Kehadiran (${matrixMeetingNumbers.value.length || 0} pertemuan)` },
])

/** Local (not UTC) YYYY-MM-DD so "today" matches the user's calendar. */
function todayIso(): string {
  const now = new Date()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')
  return `${now.getFullYear()}-${month}-${day}`
}

const enrolledCount = computed<number>(() => recapData.value?.recap?.length ?? 0)

const sessionsByMeeting = computed<TeachingSession[]>(() =>
  [...sessions.value].sort((a, b) => a.meeting_number - b.meeting_number),
)

/**
 * Matrix columns come from the meetings that actually exist. The old fixed
 * 1-16 grid showed empty columns for short semesters and hid meetings 17+.
 */
const matrixMeetingNumbers = computed<number[]>(() =>
  sessionsByMeeting.value.map((session) => session.meeting_number),
)

/** Meetings that really took place, as counted by the server. */
const heldSessions = computed<number>(() => {
  const fromRecap = recapData.value?.held_sessions
  if (typeof fromRecap === 'number') return fromRecap
  return sessions.value.filter((s) => s.status === 'open' || s.status === 'closed').length
})

/** Planning target for the class: teaching weeks, else the sessions created. */
const meetingTarget = computed<number>(() => {
  const weeks = recapData.value?.class?.semester?.total_teaching_weeks
  if (typeof weeks === 'number' && weeks > 0) return weeks
  return recapData.value?.total_sessions ?? sessions.value.length
})

/**
 * The exam-eligibility threshold is owned by the server (it is configured per
 * semester and differs between UTS and UAS); this page only displays it.
 */
const minAttendancePercentage = computed<number | null>(() => {
  const value = recapData.value?.min_attendance_percentage
  return typeof value === 'number' ? value : null
})

const thresholdStage = computed<AttendanceThresholdStage | undefined>(
  () => recapData.value?.threshold_stage,
)

const stageLabel = computed<string>(() => (thresholdStage.value === 'uts' ? 'UTS' : 'UAS'))

/** Rows of the recap whose attendance is still incomplete. */
const unrecordedTotal = computed<number>(() =>
  (recapData.value?.recap || []).reduce((sum, row) => sum + (row.unrecorded_count || 0), 0),
)

/**
 * `can_manage` is the server's answer to "may this user write attendance here",
 * so the edit affordances follow it instead of a role guess.
 */
const canManage = computed<boolean>(() => {
  if (sessions.value.length === 0) return true
  return sessions.value.some((session) => session.can_manage === true)
})

const todaySession = computed<TeachingSession | null>(() =>
  sessions.value.find((s) => s.session_date === todayIso()) ?? null,
)

/** The meeting a lecturer most likely wants next: today's, else the latest one. */
const focusSession = computed<TeachingSession | null>(() => {
  if (todaySession.value) return todaySession.value
  if (sessions.value.length === 0) return null
  return sessionsByMeeting.value[sessionsByMeeting.value.length - 1]
})

const nextMeetingNumber = computed(() => {
  if (sessions.value.length === 0) return 1
  return Math.max(...sessions.value.map((s) => s.meeting_number)) + 1
})

/** How complete is the attendance for one meeting? Drives the per-row CTA. */
function attendanceState(session: TeachingSession) {
  const recorded = session.attendances_count ?? 0
  const total = enrolledCount.value

  if (recorded === 0) {
    return { key: 'empty', label: 'Belum diabsen', variant: 'warning' as const, cta: 'Absen Sekarang' }
  }
  if (total > 0 && recorded >= total) {
    return { key: 'full', label: 'Presensi lengkap', variant: 'success' as const, cta: 'Ubah Presensi' }
  }
  return { key: 'partial', label: 'Sebagian terisi', variant: 'info' as const, cta: 'Lanjutkan Absen' }
}

function attendanceCountLabel(session: TeachingSession): string {
  const recorded = session.attendances_count ?? 0
  return enrolledCount.value > 0 ? `${recorded} / ${enrolledCount.value} mahasiswa` : `${recorded} presensi tercatat`
}

async function loadData() {
  if (!classId.value) return
  loading.value = true
  try {
    const [classRes, recapRes, sessionsRes] = await Promise.all([
      classService.get(classId.value),
      attendanceService.getClassRecap(classId.value),
      attendanceService.getSessions({ academic_class_id: classId.value, per_page: 50 }),
    ])

    academicClass.value = classRes.data || null
    recapData.value = recapRes.data || null
    sessions.value = sessionsRes.data || []
  } catch (err: unknown) {
    toast.error(getErrorMessage(err, 'Gagal memuat data presensi kelas.'))
  } finally {
    loading.value = false
  }
}

/** Deep link from the attendance dashboard: /attendance/classes/:id?session=123 */
async function openSessionFromQuery() {
  const raw = route.query.session
  const sessionId = Array.isArray(raw) ? raw[0] : raw
  if (!sessionId) return

  const session = sessions.value.find((s) => String(s.id) === String(sessionId))
  if (!session) {
    toast.error('Sesi pertemuan tidak ditemukan pada kelas ini.')
    return
  }

  activeTab.value = 'sessions'
  openBatchAttendance(session)

  // Drop the query so a refresh or back-navigation does not reopen the sheet.
  const rest = { ...route.query }
  delete rest.session
  router.replace({ path: route.path, query: rest })
}

function openCreateSessionModal() {
  selectedSession.value = null
  sessionModalOpen.value = true
}

function openEditSessionModal(session: TeachingSession) {
  selectedSession.value = session
  sessionModalOpen.value = true
}

function openBatchAttendance(session: TeachingSession) {
  activeSessionForAction.value = session
  batchModalOpen.value = true
}

function openQuickCheckIn(session: TeachingSession) {
  activeSessionForAction.value = session
  quickCheckInModalOpen.value = true
}

function confirmDeleteSession(session: TeachingSession) {
  sessionToDelete.value = session
  deleteModalOpen.value = true
}

async function handleDeleteSession() {
  if (!sessionToDelete.value) return
  deleting.value = true
  try {
    await attendanceService.deleteSession(sessionToDelete.value.id)
    toast.success(`Pertemuan ke-${sessionToDelete.value.meeting_number} berhasil dihapus.`)
    deleteModalOpen.value = false
    sessionToDelete.value = null
    loadData()
  } catch (err: unknown) {
    toast.error(getErrorMessage(err, 'Gagal menghapus sesi perkuliahan.'))
  } finally {
    deleting.value = false
  }
}

function openReopenModal(session: TeachingSession) {
  sessionToReopen.value = session
  reopenReason.value = ''
  reopenError.value = null
  reopenModalOpen.value = true
}

/** Closed sessions are locked server-side; reopening always states a reason. */
async function handleReopenSession() {
  if (!sessionToReopen.value) return

  const reason = reopenReason.value.trim()
  if (reason.length < 5) {
    reopenError.value = 'Alasan reopen wajib diisi (minimal 5 karakter).'
    return
  }

  reopening.value = true
  try {
    await attendanceService.reopenSession(sessionToReopen.value.id, reason)
    toast.success(`Pertemuan ke-${sessionToReopen.value.meeting_number} dibuka kembali untuk koreksi presensi.`)
    reopenModalOpen.value = false
    sessionToReopen.value = null
    reopenReason.value = ''
    await loadData()
  } catch (err: unknown) {
    reopenError.value = getErrorMessage(err, 'Gagal membuka kembali sesi presensi.')
  } finally {
    reopening.value = false
  }
}

/** Regenerate this class' sessions from its schedule (missing / moved meetings). */
async function handleSyncSessions() {
  if (!classId.value) return
  syncing.value = true
  try {
    const res = await attendanceService.syncClassSessions(classId.value)
    toast.success(res.message || 'Sesi perkuliahan disinkronkan dari jadwal.')
    await loadData()
  } catch (err: unknown) {
    toast.error(getErrorMessage(err, 'Gagal menyinkronkan sesi dari jadwal.'))
  } finally {
    syncing.value = false
  }
}

onMounted(async () => {
  await loadData()
  await nextTick()
  openSessionFromQuery()
})

// Keep the sheet in sync when the same route is reused with a different session.
watch(() => route.query.session, () => openSessionFromQuery())
</script>

<template>
  <PageContainer>
    <!-- Header -->
    <PageHeader
      :title="academicClass ? `Presensi Kelas: ${academicClass.name}` : 'Presensi Kelas'"
      :subtitle="academicClass ? `${academicClass.course?.code} · ${academicClass.course?.name} (${academicClass.course?.credits} SKS) · Kelas ${academicClass.section}` : 'Memuat data...'"
      :breadcrumbs="[
        { label: 'Dashboard', to: '/dashboard' },
        { label: 'Presensi Perkuliahan', to: '/attendance' },
        { label: academicClass?.code || 'Detail Kelas' },
      ]"
    >
      <template #actions>
        <div class="flex items-center gap-2">
          <Button variant="outline" size="sm" @click="router.push('/attendance')">
            <ChevronLeft class="w-4 h-4" />
            <span>Kembali</span>
          </Button>
          <Button
            v-if="canManage"
            variant="outline"
            size="sm"
            data-test="sync-sessions"
            :loading="syncing"
            title="Buat sesi yang belum ada dari jadwal kelas ini"
            @click="handleSyncSessions"
          >
            <RefreshCw class="w-4 h-4" />
            <span>Sinkron dari Jadwal</span>
          </Button>
          <Button v-if="canManage" variant="primary" size="sm" @click="openCreateSessionModal">
            <Plus class="w-4 h-4" />
            <span>Buka Pertemuan Baru</span>
          </Button>
        </div>
      </template>
    </PageHeader>

    <div class="space-y-4">
      <!-- ===================== FOCUS: TAKE ATTENDANCE NOW ===================== -->
      <Card v-if="focusSession" class="overflow-hidden border-brand-200">
        <div class="p-4 sm:p-5">
          <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div class="flex items-start gap-3 min-w-0">
              <span class="flex items-center justify-center w-11 h-11 rounded-xl bg-brand-900 text-white shrink-0">
                <ListChecks class="w-5 h-5" />
              </span>

              <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="text-2xs font-bold uppercase tracking-wider text-brand-800">
                    {{ todaySession ? 'Pertemuan Hari Ini' : 'Pertemuan Terakhir' }}
                  </span>
                  <Badge :variant="attendanceState(focusSession).variant" size="xs">
                    {{ attendanceState(focusSession).label }}
                  </Badge>
                </div>

                <h3 class="text-sm font-bold text-slate-900 mt-1 truncate">
                  Pertemuan {{ focusSession.meeting_number }} · {{ focusSession.topic || 'Topik belum diisi' }}
                </h3>

                <div class="flex items-center gap-3 flex-wrap mt-1.5 text-2xs text-slate-500">
                  <span class="inline-flex items-center gap-1">
                    <CalendarDays class="w-3.5 h-3.5" />
                    {{ formatDate(focusSession.session_date) }}
                  </span>
                  <span class="inline-flex items-center gap-1">
                    <Clock class="w-3.5 h-3.5" />
                    {{ formatTime(focusSession.start_time) }}–{{ formatTime(focusSession.end_time) }}
                  </span>
                  <span v-if="focusSession.room" class="inline-flex items-center gap-1">
                    <DoorOpen class="w-3.5 h-3.5" />
                    {{ focusSession.room.code || focusSession.room.name }}
                  </span>
                  <span class="inline-flex items-center gap-1 font-medium text-slate-700">
                    <Users class="w-3.5 h-3.5" />
                    {{ attendanceCountLabel(focusSession) }}
                  </span>
                </div>
              </div>
            </div>

            <div class="flex items-center gap-2 shrink-0">
              <Button variant="outline" size="sm" @click="openQuickCheckIn(focusSession)">
                <QrCode class="w-3.5 h-3.5" />
                <span>Token Mandiri</span>
              </Button>
              <Button variant="primary" size="md" @click="openBatchAttendance(focusSession)">
                <CheckCircle2 class="w-4 h-4" />
                <span>{{ attendanceState(focusSession).cta }}</span>
              </Button>
            </div>
          </div>
        </div>
      </Card>

      <!-- Empty state when the class has no meetings yet -->
      <Card v-else-if="!loading" class="border-dashed">
        <div class="py-8 text-center">
          <span class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-amber-50 border border-amber-200 mb-3">
            <AlertTriangle class="w-6 h-6 text-amber-600" />
          </span>
          <h3 class="text-sm font-bold text-slate-800">Belum ada pertemuan untuk kelas ini</h3>
          <p class="text-xs text-slate-500 mt-1 mb-4">
            Buka pertemuan pertama untuk mulai mencatat Berita Acara dan presensi mahasiswa.
          </p>
          <Button variant="primary" size="md" @click="openCreateSessionModal">
            <Plus class="w-4 h-4" />
            <span>Buka Pertemuan ke-{{ nextMeetingNumber }}</span>
          </Button>
        </div>
      </Card>

      <!-- Tabs Navigation -->
      <Tabs :tabs="tabs" v-model="activeTab" />

      <!-- ============ TAB 1: Pertemuan & Presensi (primary action surface) ============ -->
      <div v-if="activeTab === 'sessions'" class="space-y-4">
        <!-- Recap facts, all from the server: nothing here is a guessed constant -->
        <div class="flex flex-wrap items-center justify-between gap-2 bg-white p-3 border border-slate-200 rounded-lg text-xs">
          <div class="flex items-center gap-3 flex-wrap">
            <span class="text-slate-600 font-medium">
              Pertemuan terlaksana: <strong data-test="held-sessions">{{ heldSessions }}</strong>
              <span class="text-slate-400">/ {{ meetingTarget }} terjadwal</span>
            </span>
            <span v-if="enrolledCount > 0" class="text-slate-500">
              Mahasiswa terdaftar: <strong>{{ enrolledCount }}</strong>
            </span>
            <span
              v-if="minAttendancePercentage !== null"
              data-test="recap-threshold"
              class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-100 border border-slate-200 text-2xs font-semibold text-slate-700"
            >
              Ambang layak {{ stageLabel }}: <strong>{{ minAttendancePercentage }}%</strong>
            </span>
            <span
              v-if="unrecordedTotal > 0"
              data-test="recap-unrecorded"
              class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 border border-amber-200 text-2xs font-semibold text-amber-800"
            >
              <AlertTriangle class="w-3 h-3" />
              {{ unrecordedTotal }} catatan belum lengkap
            </span>
          </div>
          <Button v-if="canManage" variant="primary" size="xs" @click="openCreateSessionModal">
            <Plus class="w-3.5 h-3.5" />
            <span>Tambah Pertemuan</span>
          </Button>
        </div>

        <Alert v-if="!canManage && sessions.length > 0" variant="info">
          Anda bukan pengampu sesi pada kelas ini, jadi presensi hanya dapat dibaca.
        </Alert>

        <div v-if="loading" class="text-center py-10 text-xs text-slate-400 bg-white border border-slate-200 rounded-lg">
          Memuat daftar pertemuan...
        </div>

        <div v-else-if="sessions.length === 0" class="text-center py-10 bg-white border border-dashed border-slate-300 rounded-lg">
          <p class="text-xs font-semibold text-slate-700">Belum ada pertemuan</p>
          <p class="text-2xs text-slate-400 mt-1">Buka pertemuan baru untuk mulai mengabsen mahasiswa.</p>
        </div>

        <div v-else class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
          <div
            v-for="session in sessionsByMeeting"
            :key="session.id"
            class="bg-white border rounded-lg p-3.5 flex flex-col gap-3 transition-all"
            :class="session.id === todaySession?.id
              ? 'border-brand-300 ring-1 ring-brand-200 shadow-subtle'
              : 'border-slate-200 hover:border-brand-300 hover:shadow-subtle'"
          >
            <!-- Header row -->
            <div class="flex items-start justify-between gap-2">
              <div class="flex items-center gap-1.5">
                <span class="font-mono font-extrabold text-xs text-brand-900 bg-brand-50 px-2 py-1 rounded-md border border-brand-200">
                  P{{ session.meeting_number }}
                </span>
                <span
                  v-if="session.id === todaySession?.id"
                  class="px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 text-3xs font-bold uppercase tracking-wide"
                >
                  Hari ini
                </span>
              </div>

              <div class="flex items-center gap-1">
                <button
                  v-if="canManage"
                  type="button"
                  class="p-1 rounded-md text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors"
                  title="Ubah pertemuan"
                  @click="openEditSessionModal(session)"
                >
                  <Edit class="w-3.5 h-3.5" />
                </button>
                <button
                  v-if="canManage"
                  type="button"
                  class="p-1 rounded-md text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors"
                  title="Hapus pertemuan"
                  @click="confirmDeleteSession(session)"
                >
                  <Trash2 class="w-3.5 h-3.5" />
                </button>
              </div>
            </div>

            <!-- Topic & schedule -->
            <div class="min-w-0">
              <h4 class="font-bold text-xs text-slate-900 line-clamp-2">
                {{ session.topic || `Pertemuan ${session.meeting_number}` }}
              </h4>
              <div class="flex items-center gap-2 flex-wrap mt-1 text-2xs text-slate-500">
                <span class="inline-flex items-center gap-1">
                  <CalendarDays class="w-3 h-3" />
                  {{ formatDate(session.session_date) }}
                </span>
                <span class="inline-flex items-center gap-1">
                  <Clock class="w-3 h-3" />
                  {{ formatTime(session.start_time) }}
                </span>
                <Badge v-if="session.status === 'closed'" variant="neutral" size="xs">Terkunci</Badge>
                <!-- Every role gets this flag; the code itself is never sent to students -->
                <Badge v-else-if="session.is_check_in_active" variant="success" size="xs">Token aktif</Badge>
              </div>
            </div>

            <!-- Attendance progress -->
            <div class="flex items-center justify-between gap-2">
              <div class="flex items-center gap-1.5">
                <Badge :variant="attendanceState(session).variant" size="xs">
                  {{ attendanceState(session).label }}
                </Badge>
              </div>
              <span class="text-2xs text-slate-500 font-medium">
                {{ attendanceCountLabel(session) }}
              </span>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-2 pt-2.5 border-t border-slate-100">
              <Button
                v-if="session.status !== 'closed' && canManage"
                variant="primary"
                size="sm"
                block
                :data-test="`open-sheet-${session.id}`"
                @click="openBatchAttendance(session)"
              >
                <CheckCircle2 class="w-3.5 h-3.5" />
                <span>{{ attendanceState(session).cta }}</span>
              </Button>
              <Button
                v-else-if="session.status === 'closed' && canManage"
                variant="primary"
                size="sm"
                block
                @click="openBatchAttendance(session)"
              >
                <CheckCircle2 class="w-3.5 h-3.5" />
                <span>Koreksi Presensi</span>
              </Button>
              <Button
                v-else
                variant="outline"
                size="sm"
                block
                @click="openBatchAttendance(session)"
              >
                <CheckCircle2 class="w-3.5 h-3.5" />
                <span>Lihat Presensi</span>
              </Button>
              <Button
                v-if="session.can_manage !== false && session.status !== 'closed'"
                variant="outline"
                size="sm"
                class="shrink-0"
                title="Buka token presensi mandiri mahasiswa"
                @click="openQuickCheckIn(session)"
              >
                <QrCode class="w-3.5 h-3.5" />
              </Button>
              <Button
                v-if="session.status === 'closed' && canManage"
                variant="outline"
                size="sm"
                class="shrink-0"
                data-test="reopen-session"
                title="Buka kembali sesi terkunci (wajib alasan)"
                @click="openReopenModal(session)"
              >
                <Unlock class="w-3.5 h-3.5" />
              </Button>
            </div>
          </div>
        </div>
      </div>

      <!-- ============ TAB 2: Berita Acara Perkuliahan (BAP) ============ -->
      <div v-if="activeTab === 'bap'" class="space-y-4">
        <div v-if="sessions.length === 0" class="text-center py-10 bg-white border border-dashed border-slate-300 rounded-lg">
          <p class="text-xs font-semibold text-slate-700">Belum ada Berita Acara</p>
          <p class="text-2xs text-slate-400 mt-1">BAP akan muncul setelah pertemuan dibuat.</p>
        </div>

        <div v-else class="grid grid-cols-1 gap-3.5">
          <Card
            v-for="session in sessionsByMeeting"
            :key="session.id"
            class="hover:border-brand-300 transition-colors"
          >
            <div class="space-y-3">
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-2 border-b border-slate-100">
                <div class="flex items-center gap-2.5">
                  <span class="font-mono font-extrabold text-sm text-brand-900 bg-brand-50 px-2.5 py-1 rounded-lg border border-brand-200">
                    Pertemuan {{ session.meeting_number }}
                  </span>
                  <div>
                    <h4 class="font-bold text-sm text-slate-900">{{ session.topic || 'Belum ada topik bahasan' }}</h4>
                    <span class="text-2xs text-slate-400 font-medium">
                      {{ formatDate(session.session_date) }} · {{ formatTime(session.start_time) }} - {{ formatTime(session.end_time) }}
                    </span>
                  </div>
                </div>

                <div class="flex items-center gap-1.5">
                  <TeachingMethodBadge :method="session.teaching_method" size="sm" />
                  <Badge :variant="session.status === 'open' ? 'success' : (session.status === 'closed' ? 'neutral' : 'info')" size="sm">
                    {{ session.status_label || session.status }}
                  </Badge>
                </div>
              </div>

              <!-- BAP Notes -->
              <div class="bg-slate-50 rounded-lg p-3 border border-slate-200/80 text-xs">
                <span class="font-bold text-2xs uppercase tracking-wider text-slate-500 block mb-1">
                  Catatan Berita Acara (BAP):
                </span>
                <p class="text-slate-700 leading-relaxed whitespace-pre-line">
                  {{ session.notes || 'Belum ada catatan berita acara perkuliahan untuk pertemuan ini.' }}
                </p>
              </div>

              <!-- Footer Details & Actions -->
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pt-2 text-2xs text-slate-500">
                <div class="flex items-center gap-3 flex-wrap">
                  <span>Pengajar: <strong>{{ session.lecturer?.full_name || '-' }}</strong></span>
                  <span v-if="session.room">Ruangan: <strong>{{ session.room.name }} ({{ session.room.code }})</strong></span>
                  <Badge :variant="attendanceState(session).variant" size="xs">
                    {{ attendanceCountLabel(session) }}
                  </Badge>
                </div>

                <div class="flex items-center gap-2">
                  <Button variant="outline" size="xs" @click="openQuickCheckIn(session)">
                    <QrCode class="w-3.5 h-3.5" />
                    <span>Presensi Mandiri</span>
                  </Button>
                  <Button variant="primary" size="xs" @click="openBatchAttendance(session)">
                    <CheckCircle2 class="w-3.5 h-3.5" />
                    <span>{{ attendanceState(session).cta }}</span>
                  </Button>
                  <Button variant="ghost" size="xs" @click="openEditSessionModal(session)">
                    <Edit class="w-3.5 h-3.5" />
                  </Button>
                  <Button variant="ghost" size="xs" class="text-rose-600 hover:bg-rose-50" @click="confirmDeleteSession(session)">
                    <Trash2 class="w-3.5 h-3.5" />
                  </Button>
                </div>
              </div>
            </div>
          </Card>
        </div>
      </div>

      <!-- ============ TAB 3: Matriks Kehadiran ============ -->
      <div v-if="activeTab === 'matrix'" class="space-y-4">
        <Card>
          <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
              <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
                  Matriks Presensi Mahasiswa ({{ matrixMeetingNumbers.length }} pertemuan)
                </h3>
                <p class="text-2xs text-slate-500 mt-0.5">
                  Rekapitulasi status kehadiran tiap mahasiswa per pertemuan dan persentase kelayakan
                  {{ stageLabel }} sesuai ambang
                  <strong v-if="minAttendancePercentage !== null">{{ minAttendancePercentage }}%</strong>
                  <strong v-else>yang ditetapkan server</strong>.
                </p>
              </div>

              <div class="flex items-center gap-2 text-2xs flex-wrap">
                <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-500" /> Hadir (H)</span>
                <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-blue-500" /> Izin (I)</span>
                <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-amber-500" /> Sakit (S)</span>
                <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-rose-500" /> Alpa (A)</span>
                <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full border border-dashed border-slate-400" /> Belum dicatat</span>
              </div>
            </div>
          </template>

          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
              <thead class="bg-slate-100/90 text-2xs uppercase tracking-wider text-slate-700 font-bold border-b border-slate-200">
                <tr>
                  <th class="py-2.5 px-3 w-10 text-center">No</th>
                  <th class="py-2.5 px-3 w-28">NIM</th>
                  <th class="py-2.5 px-3 min-w-[160px]">Nama Mahasiswa</th>
                  <!-- One column per meeting that exists on this class -->
                  <th
                    v-for="meetingNumber in matrixMeetingNumbers"
                    :key="meetingNumber"
                    class="py-2 px-1 text-center w-8 text-2xs font-mono font-bold bg-brand-50/70 text-brand-900"
                    :title="`Pertemuan ke-${meetingNumber}`"
                  >
                    P{{ meetingNumber }}
                  </th>
                  <th class="py-2.5 px-2 text-center w-24">Kehadiran</th>
                  <th class="py-2.5 px-2 text-center w-28">Status {{ stageLabel }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 bg-white">
                <tr v-if="loading" class="text-center">
                  <td :colspan="matrixMeetingNumbers.length + 6" class="py-8 text-slate-400">
                    Memuat matriks kehadiran...
                  </td>
                </tr>
                <tr v-else-if="!recapData || recapData.recap.length === 0" class="text-center">
                  <td :colspan="matrixMeetingNumbers.length + 6" class="py-8 text-slate-400">
                    Belum ada data mahasiswa yang terdaftar di kelas ini.
                  </td>
                </tr>
                <tr
                  v-for="(row, idx) in recapData?.recap || []"
                  :key="row.student.id"
                  class="hover:bg-slate-50/70 transition-colors"
                >
                  <td class="py-2 px-2 text-center text-slate-400 font-mono text-2xs">
                    {{ idx + 1 }}
                  </td>
                  <td class="py-2 px-3 font-mono font-bold text-slate-800 text-2xs">
                    {{ row.student.student_number }}
                  </td>
                  <td class="py-2 px-3">
                    <span class="font-semibold text-slate-900 block">{{ row.student.full_name }}</span>
                    <span class="text-2xs text-slate-400">{{ row.student.study_program || '-' }}</span>
                  </td>

                  <!-- One cell per meeting: a null status is not the same as Alpa -->
                  <td
                    v-for="meetingNumber in matrixMeetingNumbers"
                    :key="meetingNumber"
                    class="py-2 px-1 text-center font-mono text-2xs"
                  >
                    <span
                      v-if="row.meetings[meetingNumber]"
                      :data-test="`matrix-cell-${row.student.id}-${meetingNumber}`"
                      :class="[
                        'inline-block w-6 h-6 leading-6 rounded-md font-bold text-2xs',
                        matrixCellClass(row.meetings[meetingNumber].status),
                      ]"
                      :title="row.meetings[meetingNumber].status
                        ? `P${meetingNumber}: ${row.meetings[meetingNumber].status_code} (${row.meetings[meetingNumber].session_date})`
                        : `P${meetingNumber}: belum dicatat (${row.meetings[meetingNumber].session_date})`"
                    >
                      {{ row.meetings[meetingNumber].status ? row.meetings[meetingNumber].status_code : '·' }}
                    </span>
                    <span v-else class="text-slate-300">-</span>
                  </td>

                  <!-- Percentage & Counts -->
                  <td class="py-2 px-2 text-center">
                    <span class="font-bold text-xs" :class="row.is_eligible ? 'text-emerald-700' : 'text-rose-700'">
                      {{ row.percentage }}%
                    </span>
                    <span class="text-2xs text-slate-400 block">
                      {{ row.present_count + row.permit_count + row.sick_count }}/{{ row.total_meetings }}
                    </span>
                    <span
                      v-if="row.unrecorded_count > 0"
                      data-test="row-unrecorded"
                      class="text-2xs text-amber-700 font-semibold block"
                      :title="`${row.unrecorded_count} pertemuan belum dicatat sama sekali`"
                    >
                      {{ row.unrecorded_count }} belum dicatat
                    </span>
                  </td>

                  <!-- Exam Eligibility Badge -->
                  <td class="py-2 px-2 text-center">
                    <ExamEligibilityBadge
                      :eligible="row.is_eligible"
                      :percentage="row.percentage"
                      :min-attendance-percentage="minAttendancePercentage"
                      :stage="thresholdStage"
                      size="sm"
                    />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </Card>
      </div>
    </div>

    <!-- Modals -->
    <SessionModal
      :open="sessionModalOpen"
      :academic-class-id="classId"
      :session="selectedSession"
      :next-meeting-number="nextMeetingNumber"
      :default-lecturer-id="academicClass?.lecturers?.[0]?.id"
      :lecturers="academicClass?.lecturers"
      @update:open="sessionModalOpen = $event"
      @saved="loadData"
    />

    <QuickCheckInModal
      :open="quickCheckInModalOpen"
      :session="activeSessionForAction"
      @update:open="quickCheckInModalOpen = $event"
      @updated="loadData"
    />

    <BatchAttendanceModal
      :open="batchModalOpen"
      :session="activeSessionForAction"
      :class-name="academicClass?.name || undefined"
      @update:open="batchModalOpen = $event"
      @saved="loadData"
    />

    <ConfirmModal
      :open="deleteModalOpen"
      title="Hapus Sesi Pertemuan"
      :message="`Apakah Anda yakin ingin menghapus Pertemuan ke-${sessionToDelete?.meeting_number}? Riwayat presensi pada pertemuan ini juga akan terhapus.`"
      confirm-text="Ya, Hapus Sesi"
      variant="danger"
      :loading="deleting"
      @update:open="deleteModalOpen = $event"
      @confirm="handleDeleteSession"
    />
  </PageContainer>
</template>
