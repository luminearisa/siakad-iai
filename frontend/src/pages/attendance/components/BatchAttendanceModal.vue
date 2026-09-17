<script setup lang="ts">
import { ref, computed, watch, onUnmounted } from 'vue'
import {
  AlertTriangle,
  CheckCircle2,
  RotateCcw,
  Save,
  Search,
  Users,
  Keyboard,
} from 'lucide-vue-next'
import { attendanceService } from '@/services/api/attendance'
import { useToast } from '@/composables/useToast'
import { formatDate, formatTime } from '@/utils/format'
import type {
  AttendanceSheetRow,
  AttendanceStatusCode,
  TeachingSession,
} from '@/types/attendance'
import Modal from '@/components/ui/Modal.vue'
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
 * Ordered by how often a lecturer actually uses them, so "Hadir" is always the
 * first and easiest target.
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

type FilterKey = 'all' | 'unsaved' | AttendanceStatusCode

const rows = ref<AttendanceSheetRow[]>([])
const loading = ref<boolean>(false)
const saving = ref<boolean>(false)
const errorMessage = ref<string | null>(null)
const activeFilter = ref<FilterKey>('all')
const search = ref<string>('')
const focusedIndex = ref<number>(-1)

const filteredRows = computed<AttendanceSheetRow[]>(() => {
  let list = rows.value

  if (activeFilter.value === 'unsaved') {
    list = list.filter((row) => !row.is_recorded)
  } else if (activeFilter.value !== 'all') {
    list = list.filter((row) => row.status === activeFilter.value)
  }

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

const stats = computed(() => {
  const counts = { present: 0, permit: 0, sick: 0, absent: 0 }
  let saved = 0

  for (const row of rows.value) {
    counts[row.status] = (counts[row.status] || 0) + 1
    if (row.is_recorded) saved += 1
  }

  return {
    ...counts,
    saved,
    total: rows.value.length,
    unsaved: rows.value.length - saved,
  }
})

const progressPercent = computed<number>(() => {
  if (stats.value.total === 0) return 0
  return Math.round((stats.value.saved / stats.value.total) * 100)
})

const rosterIsEmpty = computed<boolean>(() => !loading.value && rows.value.length === 0)

function countFor(key: FilterKey): number {
  if (key === 'all') return stats.value.total
  if (key === 'unsaved') return stats.value.unsaved
  return stats.value[key] ?? 0
}

const filterChips = computed<Array<{ key: FilterKey; label: string; class: string; count: number }>>(() => {
  const chips: Array<{ key: FilterKey; label: string; class: string; count: number }> = [
    { key: 'all', label: 'Semua', class: 'bg-slate-100 text-slate-700 border-slate-300', count: countFor('all') },
    { key: 'unsaved', label: 'Belum disimpan', class: 'bg-amber-50 text-amber-800 border-amber-200', count: countFor('unsaved') },
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

async function loadSessionStudents() {
  if (!props.session) return
  loading.value = true
  errorMessage.value = null
  search.value = ''
  activeFilter.value = 'all'
  focusedIndex.value = -1

  try {
    const res = await attendanceService.getSessionStudents(props.session.id)
    rows.value = res.data || []
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || err.message || 'Gagal memuat daftar mahasiswa.'
    rows.value = []
  } finally {
    loading.value = false
  }
}

function setStatus(row: AttendanceSheetRow, status: AttendanceStatusCode) {
  row.status = status
}

function setAllStatus(status: AttendanceStatusCode) {
  // Only touch what the lecturer is looking at, so a filter + bulk action
  // does exactly what it appears to do.
  for (const row of filteredRows.value) {
    row.status = status
  }
}

function resetSheet() {
  loadSessionStudents()
}

async function handleSave() {
  if (!props.session || rows.value.length === 0) return

  saving.value = true
  errorMessage.value = null
  try {
    const payload = {
      attendances: rows.value.map((item) => ({
        student_id: item.student_id,
        status: item.status,
        notes: item.notes || undefined,
      })),
    }

    await attendanceService.recordBatch(props.session.id, payload)
    toast.success(`Presensi Pertemuan ${props.session.meeting_number} berhasil disimpan.`)
    emit('saved')
    emit('update:open', false)
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || err.message || 'Gagal menyimpan presensi.'
  } finally {
    saving.value = false
  }
}

// ---------------------------------------------------------------------------
// Keyboard workflow: mark the row under focus with H/I/S/A, jump with arrows,
// save with Enter. This is what makes marking a full class fast.
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
    handleSave()
    return
  }

  const status = HOTKEYS[key]
  if (!status) return

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
    }
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
      </Alert>

      <!-- ===================== PROGRESS ===================== -->
      <div v-if="!rosterIsEmpty" class="rounded-lg border border-slate-200 bg-slate-50/70 p-3 space-y-2">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div class="flex items-center gap-2">
            <Users class="w-4 h-4 text-slate-500" />
            <span class="text-xs font-bold text-slate-800">
              {{ stats.saved }} <span class="font-normal text-slate-500">dari</span> {{ stats.total }}
              <span class="font-normal text-slate-500">mahasiswa sudah disimpan</span>
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

        <p v-if="stats.unsaved > 0" class="flex items-center gap-1.5 text-2xs text-amber-700 font-medium">
          <AlertTriangle class="w-3.5 h-3.5 shrink-0" />
          {{ stats.unsaved }} mahasiswa belum disimpan — status awal <strong>Hadir</strong>, ubah yang tidak hadir lalu simpan.
        </p>
        <p v-else class="flex items-center gap-1.5 text-2xs text-emerald-700 font-medium">
          <CheckCircle2 class="w-3.5 h-3.5 shrink-0" />
          Semua presensi pada pertemuan ini sudah tersimpan.
        </p>
      </div>

      <!-- ===================== TOOLBAR ===================== -->
      <div v-if="!rosterIsEmpty" class="space-y-2.5">
        <!-- Bulk actions -->
        <div class="flex flex-wrap items-center gap-2">
          <span class="text-2xs font-bold uppercase tracking-wider text-slate-500">Aksi Cepat</span>

          <button
            type="button"
            class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md border border-emerald-300 bg-emerald-50 text-emerald-800 text-2xs font-bold hover:bg-emerald-100 transition-colors"
            @click="setAllStatus('present')"
          >
            <CheckCircle2 class="w-3.5 h-3.5" />
            <span>Hadir Semua</span>
          </button>

          <button
            type="button"
            class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md border border-rose-300 bg-rose-50 text-rose-800 text-2xs font-bold hover:bg-rose-100 transition-colors"
            @click="setAllStatus('absent')"
          >
            <span>Alpa Semua</span>
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
            <span>Tekan <strong class="text-slate-600">H</strong>/<strong class="text-slate-600">I</strong>/<strong class="text-slate-600">S</strong>/<strong class="text-slate-600">A</strong> untuk menandai, <strong class="text-slate-600">Enter</strong> untuk simpan</span>
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
                  !row.is_recorded ? 'border-l-2 border-l-amber-400' : 'border-l-2 border-l-transparent',
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
                    v-else-if="!row.is_recorded"
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
                      class="px-2.5 py-1.5 rounded-md border text-2xs font-bold transition-all min-w-[52px]"
                      :class="row.status === option.value ? option.activeClass : 'bg-white ' + option.idleClass"
                      :title="`${option.label} (tekan ${option.hotkey})`"
                      @click.stop="setStatus(row, option.value)"
                    >
                      {{ option.label }}
                    </button>
                  </div>
                </td>

                <td class="py-2 px-3">
                  <input
                    v-model="row.notes"
                    type="text"
                    placeholder="Catatan..."
                    class="w-full bg-slate-50 border border-slate-200 focus:bg-white focus:border-brand-500 rounded px-2 py-1 text-2xs outline-none"
                  />
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
          <span v-if="stats.unsaved > 0" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 border border-amber-200 text-amber-800 font-bold">
            {{ stats.unsaved }} belum disimpan
          </span>
          <span v-else class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-700 font-bold">
            Semua tersimpan
          </span>
        </div>

        <div class="flex items-center gap-2 sm:ml-auto">
          <Button variant="outline" size="sm" :disabled="saving" @click="emit('update:open', false)">
            Batal
          </Button>
          <Button
            variant="primary"
            size="sm"
            :loading="saving"
            :disabled="rosterIsEmpty"
            @click="handleSave"
          >
            <Save class="w-3.5 h-3.5" />
            <span>Simpan Presensi ({{ stats.total }})</span>
          </Button>
        </div>
      </div>
    </template>
  </Modal>
</template>
