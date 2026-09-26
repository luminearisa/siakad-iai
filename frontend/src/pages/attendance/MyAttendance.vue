<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import {
  QrCode,
  BookOpen,
  ChevronDown,
  ChevronUp,
  Award,
  RefreshCw,
  Timer,
  AlertTriangle,
} from 'lucide-vue-next'
import { attendanceService } from '@/services/api/attendance'
import { useToast } from '@/composables/useToast'
import { getErrorMessage } from '@/utils/errors'
import { formatTime } from '@/utils/format'
import {
  UNRECORDED_LABEL,
  type CheckInOption,
  type StudentAttendanceRecap,
} from '@/types/attendance'
import PageContainer from '@/components/data-display/PageContainer.vue'
import PageHeader from '@/components/data-display/PageHeader.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Badge from '@/components/ui/Badge.vue'
import AttendanceStatusBadge from './components/AttendanceStatusBadge.vue'
import ExamEligibilityBadge from './components/ExamEligibilityBadge.vue'
import StudentSelfCheckInModal from './components/StudentSelfCheckInModal.vue'

const toast = useToast()
const recap = ref<StudentAttendanceRecap | null>(null)
const loading = ref<boolean>(false)
const expandedClasses = ref<Record<number, boolean>>({})

// Self check-in modal
const selfCheckInModalOpen = ref<boolean>(false)
const activeSessionForCheckIn = ref<number | null>(null)
/** Inline list of open tokens, shown when the header button cannot act on its own. */
const checkInPickerOpen = ref<boolean>(false)

/**
 * Every meeting the student may still check into by themselves. This is the
 * only place a student learns a token is open: the session endpoints hand out
 * `check_in_code` to lecturers and staff only.
 */
const activeCheckInOptions = computed<CheckInOption[]>(() => {
  const options: CheckInOption[] = []

  for (const course of recap.value?.classes || []) {
    for (const meeting of course.meetings || []) {
      if (!meeting.is_check_in_active) continue
      options.push({
        session_id: meeting.session_id,
        class_id: course.class_id,
        course_label: `${course.course_code ? `${course.course_code} · ` : ''}${
          course.course_name || course.class_name
        }`,
        section: course.section,
        meeting_number: meeting.meeting_number,
        session_date: meeting.session_date,
        start_time: meeting.start_time,
        end_time: meeting.end_time,
        topic: meeting.topic,
      })
    }
  }

  return options
})

/** Meetings still waiting for a record: shown so the student can spot gaps. */
const unrecordedByClass = computed<Record<number, number>>(() => {
  const counts: Record<number, number> = {}
  for (const course of recap.value?.classes || []) {
    counts[course.class_id] = (course.meetings || []).filter((m) => m.status === null).length
  }
  return counts
})

async function loadMyAttendance() {
  loading.value = true
  try {
    const res = await attendanceService.getMyAttendance()
    recap.value = res.data || null

    // Expand first class by default
    if (recap.value && recap.value.classes.length > 0) {
      expandedClasses.value[recap.value.classes[0].class_id] = true
    }
  } catch (err: unknown) {
    toast.error(getErrorMessage(err, 'Gagal memuat rekapitulasi kehadiran mahasiswa.'))
  } finally {
    loading.value = false
  }
}

function toggleExpand(classId: number) {
  expandedClasses.value[classId] = !expandedClasses.value[classId]
}

function openCheckInModal(sessionId: number) {
  activeSessionForCheckIn.value = sessionId
  checkInPickerOpen.value = false
  selfCheckInModalOpen.value = true
}

/**
 * The header button is self-aware: with one open token it goes straight to the
 * modal, with several it asks which one, and with none it explains the situation
 * instead of opening a form that would silently fail.
 */
function handleSelfCheckInClick() {
  const options = activeCheckInOptions.value

  if (options.length === 1) {
    openCheckInModal(options[0].session_id)
    return
  }

  if (options.length > 1) {
    checkInPickerOpen.value = !checkInPickerOpen.value
    if (checkInPickerOpen.value) {
      toast.info('Beberapa token presensi sedang aktif. Pilih pertemuannya.')
    }
    return
  }

  checkInPickerOpen.value = true
  toast.warning('Belum ada token presensi yang aktif. Dosen harus membukanya di kelas.')
}

function thresholdOf(classId: number, fallback: number | undefined): number | null {
  const course = recap.value?.classes?.find((c) => c.class_id === classId)
  const value = course?.min_attendance_percentage ?? fallback
  return typeof value === 'number' ? value : null
}

/** Progress bar colour ramp, anchored on the server threshold instead of 75. */
function barClass(percentage: number, min: number | null): string {
  if (min === null) return 'bg-brand-600'
  if (percentage >= min) return 'bg-emerald-500'
  if (percentage >= min * 0.8) return 'bg-amber-500'
  return 'bg-rose-500'
}

function meetingBorderClass(status: string | null): string {
  if (status === 'present') return 'border-emerald-200'
  if (status === 'absent') return 'border-rose-200'
  if (status === null) return 'border-dashed border-slate-300'
  return 'border-slate-200'
}

function stageLabel(stage: string | undefined): string {
  if (stage === 'uts') return 'UTS'
  if (stage === 'uas') return 'UAS'
  return 'ujian'
}

onMounted(() => {
  loadMyAttendance()
})
</script>

<template>
  <PageContainer>
    <!-- Header -->
    <PageHeader
      title="Rekapitulasi Kehadiran Saya"
      subtitle="Pantau persentase kehadiran seluruh mata kuliah semester aktif, riwayat pertemuan perkuliahan, dan status kelayakan ujian sesuai ambang yang ditetapkan pada semester ini"
      :breadcrumbs="[
        { label: 'Dashboard', to: '/dashboard' },
        { label: 'Kehadiran Saya' },
      ]"
    >
      <template #actions>
        <div class="flex items-center gap-2">
          <Button variant="outline" size="md" :loading="loading" @click="loadMyAttendance">
            <RefreshCw class="w-4 h-4" />
            <span>Segarkan</span>
          </Button>
          <Button
            :variant="activeCheckInOptions.length > 0 ? 'primary' : 'secondary'"
            size="md"
            data-test="header-check-in"
            @click="handleSelfCheckInClick"
          >
            <QrCode class="w-4 h-4" />
            <span>Presensi Mandiri (Input Token)</span>
            <Badge :variant="activeCheckInOptions.length > 0 ? 'success' : 'neutral'" size="xs">
              {{ activeCheckInOptions.length }} sesi buka
            </Badge>
          </Button>
        </div>
      </template>
    </PageHeader>

    <div class="space-y-5">
      <!-- ===================== OPEN TOKENS ===================== -->
      <Card v-if="checkInPickerOpen" class="border-brand-200">
        <div class="space-y-3">
          <div class="flex items-center gap-2">
            <Timer class="w-4 h-4 text-brand-700" />
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">Token Presensi Aktif</h3>
            <button
              type="button"
              class="ml-auto text-2xs font-semibold text-slate-400 hover:text-slate-600"
              @click="checkInPickerOpen = false"
            >
              Tutup
            </button>
          </div>

          <div
            v-if="activeCheckInOptions.length === 0"
            class="rounded-lg border border-dashed border-slate-300 bg-slate-50/70 p-4"
          >
            <p class="flex items-center gap-1.5 text-xs font-bold text-slate-800">
              <AlertTriangle class="w-4 h-4 text-amber-600" />
              Belum ada pertemuan dengan token aktif
            </p>
            <p class="text-2xs text-slate-500 mt-1">
              Kode presensi hanya berlaku saat dosen membuka token pada pertemuan yang sedang berjalan.
              Segarkan halaman ini setelah dosen membuka token, lalu klik <strong>Presensi Mandiri</strong> kembali —
              pertemuan yang bisa Anda isi akan tercentang otomatis.
            </p>
            <Button variant="outline" size="xs" class="mt-2" :loading="loading" @click="loadMyAttendance">
              <RefreshCw class="w-3.5 h-3.5" />
              <span>Segarkan daftar sesi</span>
            </Button>
          </div>

          <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
            <div
              v-for="option in activeCheckInOptions"
              :key="option.session_id"
              class="rounded-lg border border-slate-200 bg-white p-3 flex flex-col gap-2 justify-between"
            >
              <div>
                <span class="font-mono text-2xs font-bold text-brand-900 bg-brand-50 px-1.5 py-0.5 rounded">
                  Pertemuan {{ option.meeting_number }}
                </span>
                <h4 class="font-bold text-xs text-slate-900 mt-1 line-clamp-2">{{ option.course_label }}</h4>
                <p class="text-2xs text-slate-500 mt-0.5">
                  {{ option.session_date || 'hari ini' }}
                  <span v-if="option.start_time"> · {{ formatTime(option.start_time) }}</span>
                  <span v-if="option.section"> · Kelas {{ option.section }}</span>
                </p>
                <p v-if="option.topic" class="text-2xs text-slate-400 truncate mt-0.5">{{ option.topic }}</p>
              </div>
              <Button
                variant="primary"
                size="xs"
                block
                :data-test="`pick-session-${option.session_id}`"
                @click="openCheckInModal(option.session_id)"
              >
                <QrCode class="w-3.5 h-3.5" />
                <span>Isi Presensi</span>
              </Button>
            </div>
          </div>
        </div>
      </Card>

      <!-- 1. Metric Summary Cards -->
      <div v-if="recap" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <!-- Overall Percentage -->
        <div class="col-span-2 sm:col-span-1 lg:col-span-2 bg-gradient-to-br from-brand-900 via-brand-800 to-indigo-950 text-white rounded-xl p-4 shadow-sm border border-brand-700">
          <div class="flex items-center justify-between">
            <span class="text-2xs font-semibold uppercase tracking-wider text-brand-200">Rata-rata Kehadiran</span>
            <Award class="w-4 h-4 text-emerald-400" />
          </div>
          <div class="mt-2 flex items-baseline gap-2">
            <span class="text-3xl font-extrabold font-mono">{{ recap.summary.overall_percentage }}%</span>
            <Badge :variant="recap.summary.is_eligible_overall ? 'success' : 'danger'" size="sm" data-test="overall-eligibility">
              {{ recap.summary.is_eligible_overall ? 'Layak' : 'Belum Layak' }}
              {{ stageLabel(recap.summary.threshold_stage) }}
              <template v-if="recap.summary.min_attendance_percentage !== undefined && recap.summary.min_attendance_percentage !== null">
                · min {{ recap.summary.min_attendance_percentage }}%
              </template>
            </Badge>
          </div>
          <p class="text-2xs text-brand-200 mt-1">Total {{ recap.summary.total_classes }} Kelas Mata Kuliah</p>
        </div>

        <!-- Total Sessions -->
        <div class="bg-white border border-slate-200 rounded-xl p-3.5 shadow-subtle">
          <span class="text-2xs font-bold text-slate-400 uppercase tracking-wider block">Total Pertemuan</span>
          <span class="text-xl font-mono font-extrabold text-slate-900 mt-1 block">{{ recap.summary.total_sessions }}</span>
          <span class="text-2xs text-slate-400 mt-0.5 block">Sesi Terlaksana</span>
        </div>

        <!-- Present (H) -->
        <div class="bg-white border border-slate-200 rounded-xl p-3.5 shadow-subtle">
          <span class="text-2xs font-bold text-emerald-600 uppercase tracking-wider block">Hadir (H)</span>
          <span class="text-xl font-mono font-extrabold text-emerald-700 mt-1 block">{{ recap.summary.total_present }}</span>
          <span class="text-2xs text-slate-400 mt-0.5 block">Pertemuan</span>
        </div>

        <!-- Permit & Sick (I/S) -->
        <div class="bg-white border border-slate-200 rounded-xl p-3.5 shadow-subtle">
          <span class="text-2xs font-bold text-blue-600 uppercase tracking-wider block">Izin / Sakit</span>
          <span class="text-xl font-mono font-extrabold text-blue-700 mt-1 block">{{ recap.summary.total_permit + recap.summary.total_sick }}</span>
          <span class="text-2xs text-slate-400 mt-0.5 block">I: {{ recap.summary.total_permit }} · S: {{ recap.summary.total_sick }}</span>
        </div>

        <!-- Absent (A) -->
        <div class="bg-white border border-slate-200 rounded-xl p-3.5 shadow-subtle">
          <span class="text-2xs font-bold text-rose-600 uppercase tracking-wider block">Alpa (A)</span>
          <span class="text-xl font-mono font-extrabold text-rose-700 mt-1 block">{{ recap.summary.total_absent }}</span>
          <span class="text-2xs text-slate-400 mt-0.5 block">Tanpa Keterangan</span>
        </div>
      </div>

      <!-- 2. Course Attendance Cards & Breakdown -->
      <div v-if="loading" class="text-center py-12 bg-white rounded-xl border border-slate-200">
        <span class="text-xs text-slate-400">Memuat data kehadiran...</span>
      </div>

      <div v-else-if="!recap || recap.classes.length === 0" class="text-center py-12 bg-white rounded-xl border border-slate-200">
        <BookOpen class="w-8 h-8 text-slate-300 mx-auto mb-2" />
        <h4 class="font-bold text-sm text-slate-800">Belum Ada Perkuliahan Terdaftar</h4>
        <p class="text-xs text-slate-500 mt-1">Data presensi akan tampil setelah KRS Anda disetujui dan dosen memulai pertemuan kelas.</p>
      </div>

      <div v-else class="space-y-4">
        <Card
          v-for="c in recap.classes"
          :key="c.class_id"
          class="overflow-hidden"
        >
          <!-- Card Header & Progress Bar -->
          <div class="p-4 bg-white hover:bg-slate-50/40 transition-colors cursor-pointer" @click="toggleExpand(c.class_id)">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
              <div class="space-y-1">
                <div class="flex items-center gap-2">
                  <span class="font-mono font-extrabold text-xs text-brand-900 bg-brand-50 px-2 py-0.5 rounded border border-brand-200">
                    {{ c.course_code }}
                  </span>
                  <h3 class="font-bold text-sm text-slate-900">
                    {{ c.course_name }} (Kelas {{ c.section }})
                  </h3>
                </div>

                <p class="text-2xs text-slate-500">
                  Pengajar: <strong>{{ c.lecturers?.map(l => l.full_name).join(', ') || 'Dosen Pengampu' }}</strong> · {{ c.credits || 0 }} SKS
                </p>

                <p class="text-2xs text-slate-500 flex flex-wrap items-center gap-x-2 gap-y-1">
                  <span
                    data-test="course-min-threshold"
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-100 border border-slate-200 font-semibold text-slate-700"
                  >
                    Ambang kehadiran: <strong>{{ c.min_attendance_percentage }}%</strong>
                    <span class="text-slate-400">({{ stageLabel(recap?.summary.threshold_stage) }})</span>
                  </span>
                  <span
                    v-if="unrecordedByClass[c.class_id]"
                    data-test="course-unrecorded"
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 border border-amber-200 font-semibold text-amber-800"
                  >
                    {{ unrecordedByClass[c.class_id] }} pertemuan belum dicatat
                  </span>
                </p>
              </div>

              <!-- Percentage & Progress -->
              <div class="flex items-center gap-4">
                <div class="w-40 sm:w-56 text-right">
                  <div class="flex items-center justify-between text-2xs mb-1">
                    <span class="text-slate-400">Kehadiran ({{ c.present_count + c.permit_count + c.sick_count }}/{{ c.total_sessions }} Sesi)</span>
                    <span class="font-mono font-bold" :class="c.is_eligible ? 'text-emerald-700' : 'text-rose-700'">
                      {{ c.percentage }}%
                    </span>
                  </div>
                  <!-- Progress Bar -->
                  <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                    <div
                      class="h-2 rounded-full transition-all duration-300"
                      :class="barClass(c.percentage, thresholdOf(c.class_id, recap?.summary.min_attendance_percentage))"
                      :style="{ width: `${Math.min(c.percentage, 100)}%` }"
                    />
                  </div>
                </div>

                <ExamEligibilityBadge
                  :eligible="c.is_eligible"
                  :percentage="c.percentage"
                  :min-attendance-percentage="c.min_attendance_percentage"
                  :stage="recap?.summary.threshold_stage"
                  size="md"
                />

                <button type="button" class="text-slate-400 hover:text-slate-600 p-1">
                  <ChevronUp v-if="expandedClasses[c.class_id]" class="w-4 h-4" />
                  <ChevronDown v-else class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>

          <!-- Expanded Meeting Details -->
          <div v-if="expandedClasses[c.class_id]" class="border-t border-slate-100 bg-slate-50/50 p-4">
            <h4 class="text-2xs font-bold uppercase tracking-wider text-slate-500 mb-3">
              Riwayat Pertemuan & Presensi:
            </h4>

            <div v-if="c.meetings.length === 0" class="text-center py-4 text-xs text-slate-400 italic">
              Belum ada catatan pertemuan yang dibuka untuk kelas ini.
            </div>

            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5">
              <div
                v-for="m in c.meetings"
                :key="m.session_id"
                class="bg-white border rounded-lg p-3 space-y-2 shadow-2xs"
                :class="meetingBorderClass(m.status)"
              >
                <div class="flex items-center justify-between text-2xs">
                  <span class="font-mono font-bold text-slate-800 bg-slate-100 px-1.5 py-0.5 rounded">
                    Pertemuan {{ m.meeting_number }}
                  </span>
                  <AttendanceStatusBadge :status="m.status" size="sm" show-code />
                </div>

                <div>
                  <h5 class="font-bold text-xs text-slate-900 line-clamp-1">
                    {{ m.topic || 'Materi Perkuliahan' }}
                  </h5>
                  <span class="text-2xs text-slate-400 font-mono block mt-0.5">
                    {{ m.session_date || '-' }} · {{ m.start_time || '08:00' }}
                  </span>
                  <span
                    v-if="m.status === null"
                    class="text-2xs text-slate-400 italic block mt-0.5"
                  >
                    {{ UNRECORDED_LABEL }} oleh dosen pengampu.
                  </span>
                </div>

                <!-- Self check-in active button if available -->
                <div v-if="m.is_check_in_active" class="pt-1">
                  <Button
                    variant="primary"
                    size="xs"
                    class="w-full justify-center"
                    :data-test="`check-in-meeting-${m.session_id}`"
                    @click.stop="openCheckInModal(m.session_id)"
                  >
                    <QrCode class="w-3 h-3 mr-1" />
                    <span>Presensi Sekarang</span>
                  </Button>
                </div>
              </div>
            </div>
          </div>
        </Card>
      </div>
    </div>

    <!-- Student Self Check-In Modal -->
    <StudentSelfCheckInModal
      :open="selfCheckInModalOpen"
      :active-sessions="activeCheckInOptions"
      :default-session-id="activeSessionForCheckIn"
      @update:open="selfCheckInModalOpen = $event"
      @refresh="loadMyAttendance"
      @no-active-session="checkInPickerOpen = true"
      @success="loadMyAttendance"
    />
  </PageContainer>
</template>
