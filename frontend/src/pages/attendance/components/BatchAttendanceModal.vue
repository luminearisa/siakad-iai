<script setup lang="ts">
import { ref, computed, watch, onUnmounted, nextTick } from 'vue'
import {
  AlertTriangle,
  CheckCircle2,
  History,
  Lock,
  RotateCcw,
  Save,
  Search,
  Users,
  Keyboard,
} from 'lucide-vue-next'
import { attendanceService } from '@/services/api/attendance'
import { useToast } from '@/composables/useToast'
import { getErrorMessage } from '@/utils/errors'
import { formatDate, formatTime } from '@/utils/format'
import {
  ATTENDANCE_STATUS_LABELS,
  UNRECORDED_LABEL,
  requiresNotes,
  type AttendanceSheetRow,
  type AttendanceSheetStatus,
  type AttendanceStatusCode,
  type BatchAttendanceItem,
  type TeachingSession,
} from '@/types/attendance'
import Modal from '@/components/ui/Modal.vue'
import Textarea from '@/components/ui/Textarea.vue'
import Button from '@/components/ui/Button.vue'
import Alert from '@/components/ui/Alert.vue'

const props = defineProps<{
  open: boolean
  session: TeachingSession | null
  /** Shown in the sheet header so the lecturer knows which class they are marking. */
  className?: string
}>()

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void
  (e: 'saved'): void
}>()

const toast = useToast()

interface StatusOption {
  value: AttendanceStatusCode
  label: string
  short: string
  hotkey: string
  activeClass: string
  idleClass: string
  chipClass: string
}

/**
 * Ordered by how often a lecturer actually uses them. There is deliberately no
 * pre-selected status: an untouched row means "belum dicatat" and is never sent.
 */
const STATUS_OPTIONS: StatusOption[] = [
  {
    value: 'present',
    label: 'Hadir',
    short: 'H',
    hotkey: 'H',
    activeClass: 'bg-emerald-600 text-white border-emerald-600 shadow-sm',
    idleClass: 'text-emerald-700 border-emerald-200 hover:bg-emerald-50',
    chipClass: 'bg-emerald-50 text-emerald-700 border-emerald-200',
  },
  {
    value: 'permit',
    label: 'Izin',
    short: 'I',
    hotkey: 'I',
    activeClass: 'bg-blue-600 text-white border-blue-600 shadow-sm',
    idleClass: 'text-blue-700 border-blue-200 hover:bg-blue-50',
    chipClass: 'bg-blue-50 text-blue-700 border-blue-200',
  },
  {
    value: 'sick',
    label: 'Sakit',
    short: 'S',
    hotkey: 'S',
    activeClass: 'bg-amber-500 text-white border-amber-500 shadow-sm',
    idleClass: 'text-amber-700 border-amber-200 hover:bg-amber-50',
    chipClass: 'bg-amber-50 text-amber-800 border-amber-200',
  },
  {
    value: 'absent',
    label: 'Alpa',
    short: 'A',
    hotkey: 'A',
    activeClass: 'bg-rose-600 text-white border-rose-600 shadow-sm',
    idleClass: 'text-rose-700 border-rose-200 hover:bg-rose-50',
    chipClass: 'bg-rose-50 text-rose-700 border-rose-200',
  },
]

type FilterKey = 'all' | 'unmarked' | 'pending' | AttendanceStatusCode

/** What the sheet looked like when it was loaded, so we only send real changes. */
interface RowBaseline {
  status: AttendanceSheetStatus
  notes: string
}

const rows = ref<AttendanceSheetRow[]>([])
const baseline = ref<Record<number, RowBaseline>>({})
const loading = ref<boolean>(false)
const saving = ref<boolean>(false)
const errorMessage = ref<string | null>(null)
/** Server-side per-field messages (422) shown verbatim. */
const serverMessages = ref<string[]>([])
const showValidation = ref<boolean>(false)
const activeFilter = ref<FilterKey>('all')
const search = ref<string>('')
const focusedIndex = ref<number>(-1)

// --- Correction-of-a-closed-session dialog -------------------------------
const correctionOpen = ref<boolean>(false)
const correctionReason = ref<string>('')
const correctionError = ref<string | null>(null)

const sessionIsClosed = computed<boolean>(() => props.session?.status === 'closed')

/**
 * `can_manage` is sent to every role; when it is false the sheet is a read-only
 * view (staff monitoring, or a student who should never have reached it).
 */
const isReadOnly = computed<boolean>(() => props.session?.can_manage === false)

function isMarked(row: AttendanceSheetRow): boolean {
  return row.status !== null
}

function isDirty(row: AttendanceSheetRow): boolean {
  const base = baseline.value[row.student_id]
  if (!base) return true
  const notes = (row.notes || '').trim()
  return row.status !== base.status || notes !== base.notes
}

/** Rows actually sent: marked (not "belum dicatat") and changed by the user. */
const pendingRows = computed<AttendanceSheetRow[]>(() =>
  rows.value.filter((row) => isMarked(row) && isDirty(row)),
)

const unmarkedRows = computed<AttendanceSheetRow[]>(() => rows.value.filter((row) => !isMarked(row)))

/**
 * Client-side mirror of the server rule "notes are required for permit/sick/
 * absent", so the lecturer fixes the sheet before a 422 round-trip.
 */
function notesError(row: AttendanceSheetRow): string | null {
  if (!requiresNotes(row.status)) return null
  if ((row.notes || '').trim().length > 0) return null
  const label = row.status ? ATTENDANCE_STATUS_LABELS[row.status] : UNRECORDED_LABEL
  return `Keterangan wajib diisi untuk status ${label}.`
}

const invalidRows = computed<AttendanceSheetRow[]>(() => rows.value.filter((row) => notesError(row)))

const stats = computed(() => {
  const counts: Record<AttendanceStatusCode, number> = { present: 0, permit: 0, sick: 0, absent: 0 }
  let saved = 0

  for (const row of rows.value) {
    if (row.status) counts[row.status] += 1
    if (row.is_recorded) saved += 1
  }

  return {
    ...counts,
    saved,
    total: rows.value.length,
    /** Rows the lecturer marked but has not saved yet. */
    pending: pendingRows.value.length,
    unmarked: unmarkedRows.value.length,
    invalid: invalidRows.value.length,
    invalidShown: showValidation.value ? invalidRows.value.length : 0,
  }
})

const progressPercent = computed<number>(() => {
  if (stats.value.total === 0) return 0
  return Math.round((stats.value.saved / stats.value.total) * 100)
})

const rosterIsEmpty = computed<boolean>(() => !loading.value && rows.value.length === 0)

function countFor(key: FilterKey): number {
  if (key === 'all') return stats.value.total
  if (key === 'unmarked') return stats.value.unmarked
  if (key === 'pending') return stats.value.pending
  return stats.value[key] ?? 0
}

const filterChips = computed<Array<{ key: FilterKey; label: string; class: string; count: number }>>(() => {
  const chips: Array<{ key: FilterKey; label: string; class: string; count: number }> = [
    { key: 'all', label: 'Semua', class: 'bg-slate-100 text-slate-700 border-slate-300', count: countFor('all') },
    {
      key: 'unmarked',
      label: UNRECORDED_LABEL,
      class: 'bg-slate-50 text-slate-600 border-slate-300 border-dashed',
      count: countFor('unmarked'),
    },
    {
      key: 'pending',
      label: 'Perubahan belum disimpan',
      class: 'bg-amber-50 text-amber-800 border-amber-200',
      count: countFor('pending'),
    },
  ]

  for (const option of STATUS_OPTIONS) {
    chips.push({
      key: option.value,
      label: `${option.label} (${option.short})`,
      class: option.chipClass,
      count: countFor(option.value),
    })
  }

  return chips
})

function matchesFilter(row: AttendanceSheetRow, key: FilterKey): boolean {
  if (key === 'all') return true
  if (key === 'unmarked') return !isMarked(row)
  if (key === 'pending') return isMarked(row) && isDirty(row)
  return row.status === key
}

const filteredRows = computed<AttendanceSheetRow[]>(() => {
  let list = rows.value.filter((row) => matchesFilter(row, activeFilter.value))

  const query = search.value.trim().toLowerCase()
  if (query) {
    list = list.filter((row) => {
      const student = row.student
      return (
        student?.full_name?.toLowerCase().includes(query) ||
        student?.student_number?.toLowerCase().includes(query)
      )
    })
  }

  return list
})

async function loadSessionStudents() {
  if (!props.session) return
  loading.value = true
  errorMessage.value = null
  serverMessages.value = []
  showValidation.value = false
  search.value = ''
  activeFilter.value = 'all'
  focusedIndex.value = -1

  try {
    const res = await attendanceService.getSessionStudents(props.session.id)
    rows.value = res.data || []
    baseline.value = {}
    for (const row of rows.value) {
      baseline.value[row.student_id] = {
        status: row.status,
        notes: (row.notes || '').trim(),
      }
    }
  } catch (err: unknown) {
    errorMessage.value = getErrorMessage(err, 'Gagal memuat daftar mahasiswa.')
    rows.value = []
    baseline.value = {}
  } finally {
    loading.value = false
  }
}

function setStatus(row: AttendanceSheetRow, status: AttendanceSheetStatus) {
  row.status = status
}

function setAllStatus(status: AttendanceSheetStatus) {
  // Only touch what the lecturer is looking at, so a filter + bulk action
  // does exactly what it appears to do.
  for (const row of filteredRows.value) {
    row.status = status
  }
}

function resetSheet() {
  loadSessionStudents()
}

function errorFieldMessages(err: unknown): string[] {
  const errors = (err as { errors?: Record<string, string[]> } | null)?.errors
  if (!errors) return []
  return Object.values(errors).flat().filter((message) => Boolean(message))
}

async function submitAttendance(correctionReason?: string) {
  if (!props.session) return

  saving.value = true
  errorMessage.value = null
  serverMessages.value = []

  const attendances: BatchAttendanceItem[] = pendingRows.value.map((row) => ({
    student_id: row.student_id,
    status: row.status as AttendanceStatusCode,
    notes: (row.notes || '').trim() || null,
  }))

  try {
    await attendanceService.recordBatch(props.session.id, {
      attendances,
      ...(correctionReason ? { correction_reason: correctionReason } : {}),
    })

    toast.success(`Presensi Pertemuan ${props.session.meeting_number} berhasil disimpan.`)
    correctionOpen.value = false
    correctionReason.value = ''
    emit('saved')
    emit('update:open', false)
  } catch (err: unknown) {
    errorMessage.value = getErrorMessage(err, 'Gagal menyimpan presensi.')
    serverMessages.value = errorFieldMessages(err)
  } finally {
    saving.value = false
  }
}

/**
 * Save entry point. Blocked locally when a permit/sick/absent row has no note,
 * and rerouted through the correction dialog when the session is closed.
 */
async function handleSave() {
  if (!props.session || isReadOnly.value || rows.value.length === 0) return

  showValidation.value = true
  errorMessage.value = null
  serverMessages.value = []

  if (invalidRows.value.length > 0) {
    errorMessage.value = `${invalidRows.value.length} baris perlu Keterangan sebelum presensi ini disimpan.`
    focusFirstInvalidRow()
    return
  }

  if (pendingRows.value.length === 0) {
    errorMessage.value = 'Tidak ada perubahan presensi untuk disimpan.'
    return
  }

  if (sessionIsClosed.value) {
    correctionError.value = null
    correctionReason.value = ''
    correctionOpen.value = true
    return
  }

  await submitAttendance()
}

async function confirmCorrection() {
  const reason = correctionReason.value.trim()
  if (reason.length < 5) {
    correctionError.value = 'Tuliskan alasan koreksi (minimal 5 karakter).'
    return
  }
  correctionError.value = null
  await submitAttendance(reason)
}

function cancelCorrection() {
  correctionOpen.value = false
  correctionReason.value = ''
  correctionError.value = null
}

/** The nested dialog only closes through its own buttons, so a stray Escape on
 * the sheet underneath cannot dismiss a correction the lecturer is writing. */
function handleCorrectionOpenChange(value: boolean) {
  if (!value) cancelCorrection()
}

function focusFirstInvalidRow() {
  const first = invalidRows.value[0]
  if (!first) return
  activeFilter.value = 'all'
  const index = filteredRows.value.findIndex((row) => row.student_id === first.student_id)
  if (index >= 0) focusedIndex.value = index
  void nextTick(() => {
    document
      .querySelector<HTMLInputElement>(`[data-test="notes-input-${first.student_id}"]`)
      ?.focus()
  })
}

// ---------------------------------------------------------------------------
// Keyboard workflow: mark the row under focus with H/I/S/A, clear it with B,
// jump with arrows, save with Enter. This is what makes marking fast.
// ---------------------------------------------------------------------------
const HOTKEYS: Record<string, AttendanceStatusCode> = {
  h: 'present',
  '1': 'present',
  i: 'permit',
  '2': 'permit',
  s: 'sick',
  '3': 'sick',
  a: 'absent',
  '4': 'absent',
}

const CLEAR_KEYS = ['b', '0', 'backspace']

function focusRow(index: number) {
  if (filteredRows.value.length === 0) return
  const clamped = Math.min(Math.max(index, 0), filteredRows.value.length - 1)
  focusedIndex.value = clamped
  document
    .getElementById(`attendance-row-${clamped}`)
    ?.scrollIntoView({ block: 'nearest' })
}

function handleKeydown(event: KeyboardEvent) {
  if (!props.open) return
  // While the correction dialog is up, its own buttons own the keyboard.
  if (correctionOpen.value) return

  const target = event.target as HTMLElement | null
  const tag = target?.tagName
  if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return

  const key = event.key.toLowerCase()

  if (key === 'arrowdown') {
    event.preventDefault()
    focusRow(focusedIndex.value + 1)
    return
  }

  if (key === 'arrowup') {
    event.preventDefault()
    focusRow(focusedIndex.value - 1)
    return
  }

  if (key === 'enter') {
    event.preventDefault()
    void handleSave()
    return
  }

  const status = HOTKEYS[key]
  if (!status) {
    if (CLEAR_KEYS.includes(key) && !isReadOnly.value) {
      const row = filteredRows.value[focusedIndex.value]
      if (row && !row.is_recorded) {
        event.preventDefault()
        row.status = null
      }
    }
    return
  }

  event.preventDefault()
  const row = filteredRows.value[focusedIndex.value] ?? filteredRows.value[0]
  if (row) {
    if (focusedIndex.value === -1) focusRow(0)
    setStatus(row, status)
  }
}

function attachKeyboard() {
  document.addEventListener('keydown', handleKeydown)
}

function detachKeyboard() {
  document.removeEventListener('keydown', handleKeydown)
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      loadSessionStudents()
      attachKeyboard()
    } else {
      detachKeyboard()
      focusedIndex.value = -1
      cancelCorrection()
    }
  },
)

watch(
  () => invalidRows.value.length,
  (count) => {
    if (count === 0) showValidation.value = false
  },
)

onUnmounted(detachKeyboard)
</script>

<template>
  <Modal
    :open="open"
    size="xl"
    @update:open="emit('update:open', $event)"
  >
    <!-- Header: which class / meeting is being marked -->
    <template #header>
      <div class="flex items-center gap-2.5 min-w-0">
        <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-brand-900 text-white shrink-0">
          <CheckCircle2 class="w-4 h-4" />
        </span>
        <div class="min-w-0">
          <h3 class="text-sm font-semibold text-slate-800 truncate">
            Absen Pertemuan {{ session?.meeting_number ?? '-' }}
            <span v-if="className" class="font-normal text-slate-500">· {{ className }}</span>
          </h3>
          <p v-if="session" class="text-2xs text-slate-500 truncate">
            {{ formatDate(session.session_date) }} · {{ formatTime(session.start_time) }}–{{ formatTime(session.end_time) }}
            <span v-if="session.topic"> · {{ session.topic }}</span>
          </p>
        </div>
      </div>
    </template>

    <div class="space-y-3">
      <Alert v-if="errorMessage" variant="danger">
        {{ errorMessage }}
        <ul v-if="serverMessages.length > 0" class="mt-1 list-disc pl-4 space-y-0.5">
          <li v-for="message in serverMessages" :key="message">{{ message }}</li>
        </ul>
      </Alert>

      <Alert v-if="isReadOnly" variant="info" title="Hanya-baca">
        Anda bukan pengampu sesi ini, sehingga presensi hanya bisa dilihat.
      </Alert>

      <Alert v-if="sessionIsClosed && !isReadOnly" variant="warning" title="Sesi sudah ditutup">
        Menyimpan akan tercatat sebagai <strong>koreksi presensi</strong>: tuliskan alasannya pada dialog
        yang muncul setelah Anda menekan Simpan.
      </Alert>

      <!-- ===================== PROGRESS ===================== -->
      <div v-if="!rosterIsEmpty" class="rounded-lg border border-slate-200 bg-slate-50/70 p-3 space-y-2">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div class="flex items-center gap-2">
            <Users class="w-4 h-4 text-slate-500" />
            <span class="text-xs font-bold text-slate-800">
              {{ stats.saved }} <span class="font-normal text-slate-500">dari</span> {{ stats.total }}
              <span class="font-normal text-slate-500">mahasiswa sudah tersimpan</span>
            </span>
          </div>

          <div class="flex items-center gap-1.5 flex-wrap">
            <span
              v-for="option in STATUS_OPTIONS"
              :key="option.value"
              :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-2xs font-bold', option.chipClass]"
            >
              {{ option.short }}: {{ stats[option.value] }}
            </span>
            <span
              data-test="unrecorded-count"
              class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border border-dashed border-slate-300 bg-white text-slate-500 text-2xs font-bold"
            >
              {{ UNRECORDED_LABEL }}: {{ stats.unmarked }}
            </span>
          </div>
        </div>

        <!-- Progress bar -->
        <div class="h-1.5 w-full rounded-full bg-slate-200 overflow-hidden">
          <div
            class="h-full rounded-full transition-all duration-300"
            :class="progressPercent === 100 ? 'bg-emerald-500' : 'bg-brand-600'"
            :style="{ width: `${progressPercent}%` }"
          />
        </div>

        <p v-if="stats.unmarked > 0" class="flex items-center gap-1.5 text-2xs text-slate-600 font-medium">
          <AlertTriangle class="w-3.5 h-3.5 shrink-0 text-amber-600" />
          {{ stats.unmarked }} mahasiswa <strong>{{ UNRECORDED_LABEL.toLowerCase() }}</strong> — tandai statusnya,
          baris yang belum ditandai tidak ikut tersimpan.
        </p>
        <p v-else-if="stats.pending > 0" class="flex items-center gap-1.5 text-2xs text-amber-700 font-medium">
          <AlertTriangle class="w-3.5 h-3.5 shrink-0" />
          {{ stats.pending }} perubahan belum disimpan.
        </p>
        <p v-else class="flex items-center gap-1.5 text-2xs text-emerald-700 font-medium">
          <CheckCircle2 class="w-3.5 h-3.5 shrink-0" />
          Semua presensi pada pertemuan ini sudah tersimpan.
        </p>

        <p v-if="stats.invalidShown > 0" class="flex items-center gap-1.5 text-2xs text-rose-700 font-bold">
          <AlertTriangle class="w-3.5 h-3.5 shrink-0" />
          {{ stats.invalidShown }} baris wajib diisi Keterangan (Izin / Sakit / Alpa tidak boleh tanpa alasan).
        </p>
      </div>

      <!-- ===================== TOOLBAR ===================== -->
      <div v-if="!rosterIsEmpty" class="space-y-2.5">
        <!-- Bulk actions -->
        <div v-if="!isReadOnly" class="flex flex-wrap items-center gap-2">
          <span class="text-2xs font-bold uppercase tracking-wider text-slate-500">Aksi Cepat</span>

          <button
            type="button"
            data-test="bulk-present"
            class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md border border-emerald-300 bg-emerald-50 text-emerald-800 text-2xs font-bold hover:bg-emerald-100 transition-colors"
            @click="setAllStatus('present')"
          >
            <CheckCircle2 class="w-3.5 h-3.5" />
            <span>Hadir Semua</span>
          </button>

          <button
            type="button"
            data-test="bulk-absent"
            class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md border border-rose-300 bg-rose-50 text-rose-800 text-2xs font-bold hover:bg-rose-100 transition-colors"
            title="Isi Keterangan tiap baris yang dialpa-kan sebelum menyimpan"
            @click="setAllStatus('absent')"
          >
            <span>Alpa Semua (butuh Keterangan)</span>
          </button>

          <button
            type="button"
            class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md border border-slate-300 bg-white text-slate-700 text-2xs font-bold hover:bg-slate-50 transition-colors"
            :disabled="loading"
            @click="resetSheet"
          >
            <RotateCcw class="w-3.5 h-3.5" />
            <span>Muat Ulang</span>
          </button>

          <span class="hidden sm:flex items-center gap-1.5 ml-auto text-2xs text-slate-400">
            <Keyboard class="w-3.5 h-3.5" />
            <span>Tekan <strong class="text-slate-600">H</strong>/<strong class="text-slate-600">I</strong>/<strong class="text-slate-600">S</strong>/<strong class="text-slate-600">A</strong> untuk menandai, <strong class="text-slate-600">B</strong> menghapus tanda, <strong class="text-slate-600">Enter</strong> untuk simpan</span>
          </span>
        </div>

        <!-- Filter chips + search -->
        <div class="flex flex-col sm:flex-row sm:items-center gap-2">
          <div class="flex items-center gap-1.5 flex-wrap">
            <button
              v-for="chip in filterChips"
              :key="chip.key"
              type="button"
              class="inline-flex items-center gap-1 px-2 py-1 rounded-full border text-2xs font-bold transition-all"
              :class="activeFilter === chip.key
                ? 'ring-2 ring-brand-500/30 border-brand-400 ' + chip.class
                : chip.class + ' opacity-70 hover:opacity-100'"
              @click="activeFilter = chip.key"
            >
              <span>{{ chip.label }}</span>
              <span class="px-1 rounded bg-white/70 text-slate-700 font-mono">{{ chip.count }}</span>
            </button>
          </div>

          <div class="relative sm:ml-auto sm:w-56">
            <Search class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2" />
            <input
              v-model="search"
              type="text"
              placeholder="Cari nama atau NIM..."
              class="w-full pl-8 pr-2.5 py-1.5 bg-white border border-slate-300 rounded-md text-2xs focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600 outline-none"
            />
          </div>
        </div>
      </div>

      <!-- ===================== SHEET ===================== -->
      <div class="border border-slate-200 rounded-lg overflow-hidden">
        <!-- Loading -->
        <div v-if="loading" class="py-14 text-center text-xs text-slate-400">
          Memuat daftar mahasiswa...
        </div>

        <!-- No roster at all -->
        <div v-else-if="rosterIsEmpty" class="py-12 px-6 text-center">
          <span class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-amber-50 border border-amber-200 mb-3">
            <AlertTriangle class="w-6 h-6 text-amber-600" />
          </span>
          <p class="text-sm font-bold text-slate-800">Belum ada mahasiswa pada kelas ini</p>
          <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto">
            Presensi diambil dari mahasiswa yang KRS-nya sudah disetujui pada kelas ini.
            Setujui KRS mahasiswa terlebih dahulu, lalu buka kembali halaman ini.
          </p>
        </div>

        <!-- Nothing matches the filter -->
        <div v-else-if="filteredRows.length === 0" class="py-12 text-center text-xs text-slate-400">
          Tidak ada mahasiswa yang cocok dengan filter atau pencarian.
        </div>

        <div v-else class="max-h-[50vh] overflow-y-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead class="bg-slate-100/90 sticky top-0 z-10 border-b border-slate-200 text-2xs uppercase tracking-wider text-slate-600 font-bold">
              <tr>
                <th class="py-2.5 px-3 w-10 text-center">No</th>
                <th class="py-2.5 px-3 w-32">NIM</th>
                <th class="py-2.5 px-3 min-w-[170px]">Nama Mahasiswa</th>
                <th class="py-2.5 px-3 text-center w-[300px]">Status Kehadiran</th>
                <th class="py-2.5 px-3 w-44">Keterangan</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
              <tr
                v-for="(row, idx) in filteredRows"
                :id="`attendance-row-${idx}`"
                :key="row.student_id"
                class="transition-colors cursor-pointer"
                :class="[
                  focusedIndex === idx ? 'bg-brand-50/70 ring-1 ring-inset ring-brand-300' : 'hover:bg-slate-50/70',
                  !isMarked(row) ? 'border-l-2 border-l-slate-300' : (isDirty(row) ? 'border-l-2 border-l-amber-400' : 'border-l-2 border-l-transparent'),
                ]"
                @click="focusedIndex = idx"
              >
                <td class="py-2 px-3 text-center text-slate-400 font-mono text-2xs">
                  {{ idx + 1 }}
                </td>

                <td class="py-2 px-3 font-mono font-bold text-slate-800 text-2xs">
                  {{ row.student?.student_number || '-' }}
                </td>

                <td class="py-2 px-3">
                  <span class="font-semibold text-slate-900 block">
                    {{ row.student?.full_name || '-' }}
                  </span>
                  <span class="text-2xs text-slate-400">
                    {{ row.student?.study_program?.name || 'Mahasiswa' }}
                  </span>
                  <span
                    v-if="!row.is_enrolled"
                    class="ml-1.5 px-1.5 py-0.5 rounded bg-slate-100 text-slate-500 text-3xs font-bold uppercase"
                    title="Mahasiswa ini sudah tidak terdaftar pada kelas, namun riwayat presensinya tetap ditampilkan."
                  >
                    Non-aktif
                  </span>
                  <span
                    v-else-if="!isMarked(row)"
                    data-test="unrecorded-chip"
                    class="ml-1.5 px-1.5 py-0.5 rounded border border-dashed border-slate-300 bg-slate-50 text-slate-500 text-3xs font-bold uppercase"
                  >
                    {{ UNRECORDED_LABEL }}
                  </span>
                  <span
                    v-else-if="isDirty(row)"
                    class="ml-1.5 px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 text-3xs font-bold uppercase"
                  >
                    Belum disimpan
                  </span>
                </td>

                <!-- Status buttons: the primary interaction -->
                <td class="py-2 px-3">
                  <div class="flex items-center justify-center gap-1">
                    <button
                      v-for="option in STATUS_OPTIONS"
                      :key="option.value"
                      type="button"
                      :data-test="`status-button-${row.student_id}-${option.value}`"
                      class="px-2.5 py-1.5 rounded-md border text-2xs font-bold transition-all min-w-[52px]"
                      :class="row.status === option.value ? option.activeClass : 'bg-white ' + option.idleClass"
                      :disabled="isReadOnly"
                      :title="`${option.label} (tekan ${option.hotkey})`"
                      @click.stop="setStatus(row, option.value)"
                    >
                      {{ option.label }}
                    </button>

                    <button
                      v-if="!row.is_recorded && isMarked(row) && !isReadOnly"
                      type="button"
                      data-test="clear-status-button"
                      class="px-1.5 py-1.5 rounded-md border border-dashed border-slate-300 text-slate-400 text-2xs font-bold hover:bg-slate-50 transition-all"
                      title="Kosongkan (belum dicatat)"
                      @click.stop="setStatus(row, null)"
                    >
                      <span class="block w-3 leading-none">✕</span>
                    </button>
                  </div>
                </td>

                <td class="py-2 px-3 align-top">
                  <input
                    :data-test="`notes-input-${row.student_id}`"
                    v-model="row.notes"
                    type="text"
                    :placeholder="requiresNotes(row.status) ? 'Wajib diisi...' : 'Catatan...'"
                    :disabled="isReadOnly"
                    class="w-full border rounded px-2 py-1 text-2xs outline-none focus:bg-white"
                    :class="showValidation && notesError(row)
                      ? 'bg-rose-50 border-rose-400 focus:border-rose-500'
                      : 'bg-slate-50 border-slate-200 focus:border-brand-500'"
                  />
                  <span
                    v-if="showValidation && notesError(row)"
                    :data-test="`notes-error-${row.student_id}`"
                    class="mt-0.5 block text-3xs font-semibold text-rose-600"
                  >
                    {{ notesError(row) }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ===================== FOOTER ===================== -->
    <template #footer>
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 w-full">
        <div v-if="!rosterIsEmpty" class="flex items-center gap-2 flex-wrap text-2xs">
          <span class="text-slate-500 font-medium">
            Total <strong class="text-slate-800">{{ stats.total }}</strong> mahasiswa
          </span>
          <span
            v-if="stats.pending > 0"
            data-test="pending-count"
            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 border border-amber-200 text-amber-800 font-bold"
          >
            {{ stats.pending }} perubahan
          </span>
          <span
            v-if="stats.unmarked > 0"
            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border border-dashed border-slate-300 bg-white text-slate-500 font-bold"
          >
            {{ stats.unmarked }} {{ UNRECORDED_LABEL.toLowerCase() }}
          </span>
          <span
            v-else-if="stats.pending === 0"
            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-700 font-bold"
          >
            Semua tersimpan
          </span>
        </div>

        <div class="flex items-center gap-2 sm:ml-auto">
          <Button variant="outline" size="sm" :disabled="saving" @click="emit('update:open', false)">
            Batal
          </Button>
          <Button
            v-if="!isReadOnly"
            variant="primary"
            size="sm"
            data-test="save-attendance"
            :loading="saving"
            :disabled="rosterIsEmpty || stats.pending === 0"
            @click="handleSave"
          >
            <Save v-if="!sessionIsClosed" class="w-3.5 h-3.5" />
            <Lock v-else class="w-3.5 h-3.5" />
            <span>{{ sessionIsClosed ? 'Koreksi Presensi' : 'Simpan Presensi' }} ({{ stats.pending }})</span>
          </Button>
        </div>
      </div>
    </template>

    <!-- ===================== CORRECTION REASON (closed session) ===================== -->
    <Modal
      :open="correctionOpen"
      title="Alasan Koreksi Presensi"
      size="sm"
      :closable="false"
      @update:open="handleCorrectionOpenChange"
    >
      <div class="space-y-3 text-xs">
        <Alert variant="warning">
          Sesi Pertemuan {{ session?.meeting_number ?? '-' }} sudah <strong>ditutup</strong>. Presensi tetap bisa
          diubah, tetapi server mewajibkan alasan koreksi yang tersimpan pada jejak audit.
        </Alert>

        <Textarea
          v-model="correctionReason"
          data-test="correction-reason"
          :rows="3"
          required
          :error="correctionError || false"
          placeholder="Contoh: koreksi data setelah dosen Pengampu memverifikasi izin sakit mahasiswa A."
          :disabled="saving"
        />

        <span v-if="correctionError" data-test="correction-error" class="block text-2xs font-bold text-rose-600">
          {{ correctionError }}
        </span>

        <p class="text-2xs text-slate-500">
          {{ stats.pending }} baris akan dikirim ke server.
        </p>

        <div class="flex items-center justify-end gap-2 pt-1">
          <Button variant="outline" size="sm" :disabled="saving" @click="cancelCorrection">
            Batal
          </Button>
          <Button
            variant="primary"
            size="sm"
            data-test="confirm-correction"
            :loading="saving"
            @click="confirmCorrection"
          >
            <History class="w-3.5 h-3.5" />
            <span>Simpan Sebagai Koreksi</span>
          </Button>
        </div>
      </div>
    </Modal>
  </Modal>
</template>
