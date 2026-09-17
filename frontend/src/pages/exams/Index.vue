<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue'
import {
  Calendar,
  Volume2,
  UserCheck,
  ChevronDown,
  X,
  Check,
} from 'lucide-vue-next'
import { examService, type ExamClassItem } from '@/services/api/exams'
import { academicService } from '@/services/api/academic'
import { lecturerService } from '@/services/api/lecturers'
import { roomService } from '@/services/api/rooms'
import { useToast } from '@/composables/useToast'
import type { Semester, StudyProgram } from '@/types/academic'
import type { Lecturer } from '@/types/lecturer'
import type { Room } from '@/types/room'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import Pagination from '@/components/data-display/Pagination.vue'

const toast = useToast()

const loading = ref<boolean>(false)
const saving = ref<boolean>(false)
const announcing = ref<boolean>(false)

const examClasses = ref<ExamClassItem[]>([])
const semesters = ref<Semester[]>([])
const studyPrograms = ref<StudyProgram[]>([])
const lecturers = ref<Lecturer[]>([])
const rooms = ref<Room[]>([])

// Daftar ruangan & dosen bisa sangat panjang -> pakai search select.
const roomOptions = computed(() => [
  { value: null as number | null, label: 'Pilih Ruang Ujian' },
  ...rooms.value.map((r) => ({
    value: r.id as number | null,
    label: `${r.name} (${r.code}) - Kapasitas: ${r.capacity}`,
  })),
])

const lecturerOptions = computed(() => [
  { value: null as number | null, label: 'Pilih Dosen Pengawas' },
  ...lecturers.value.map((l) => ({
    value: l.id as number | null,
    label: `${l.full_name} (NIDN: ${l.nidn || '-'})`,
  })),
])

// Exam Type: 'uts' or 'uas'
const examType = ref<'uts' | 'uas'>('uts')

// Filters
const selectedSemesterId = ref<string>('')
const selectedProdiId = ref<string>('')
const selectedCurriculumYear = ref<string>('')
const search = ref<string>('')

// Pagination
const perPage = ref<number>(10)
const currentPage = ref<number>(1)

// Announce Dropdown
const announceDropdownOpen = ref<boolean>(false)

// Drawer Atur Jadwal
const scheduleDrawerOpen = ref<boolean>(false)
const targetClassForSchedule = ref<ExamClassItem | null>(null)
const scheduleForm = reactive({
  exam_date: '',
  start_time: '08:00',
  end_time: '10:00',
  room_id: null as number | null,
  notes: '',
})

// Drawer Tetapkan Pengawas
const proctorDrawerOpen = ref<boolean>(false)
const targetClassForProctor = ref<ExamClassItem | null>(null)
const proctorForm = reactive({
  proctor_lecturer_id: null as number | null,
})

const filteredClasses = computed(() => {
  let list = examClasses.value

  if (selectedProdiId.value) {
    list = list.filter((c) => String(c.study_program?.id) === selectedProdiId.value)
  }
  if (selectedCurriculumYear.value) {
    list = list.filter((c) => String(c.curriculum_year) === selectedCurriculumYear.value)
  }

  if (!search.value) return list
  const q = search.value.toLowerCase()
  return list.filter(
    (c) =>
      c.class_name?.toLowerCase().includes(q) ||
      c.class_code?.toLowerCase().includes(q) ||
      c.course?.name?.toLowerCase().includes(q) ||
      c.course?.code?.toLowerCase().includes(q) ||
      c.study_program?.name?.toLowerCase().includes(q)
  )
})

const totalPages = computed(() => {
  return Math.max(1, Math.ceil(filteredClasses.value.length / perPage.value))
})

const paginatedClasses = computed(() => {
  const start = (currentPage.value - 1) * perPage.value
  return filteredClasses.value.slice(start, start + perPage.value)
})

function handlePageChange(page: number) {
  currentPage.value = page
}

function handlePerPageChange() {
  currentPage.value = 1
}

async function loadData() {
  loading.value = true
  try {
    const [examsRes, semRes, prodiRes, lecRes, roomRes] = await Promise.all([
      examService.list({
        exam_type: examType.value,
        semester_id: selectedSemesterId.value || undefined,
        study_program_id: selectedProdiId.value || undefined,
      }),
      academicService.getSemesters(),
      academicService.getStudyPrograms(),
      lecturerService.list({ per_page: 100 }),
      roomService.list({ per_page: 100 }),
    ])

    examClasses.value = examsRes.data || []
    semesters.value = semRes.data || []
    studyPrograms.value = prodiRes.data || []
    lecturers.value = lecRes.data || []
    rooms.value = roomRes.data || []

    if (!selectedSemesterId.value && semesters.value.length > 0) {
      const activeSem = semesters.value.find((s) => s.is_active) || semesters.value.find((s) => s.name?.includes('2026/2027'))
      if (activeSem) {
        selectedSemesterId.value = String(activeSem.id)
      }
    }
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal memuat jadwal ujian')
  } finally {
    loading.value = false
  }
}

function switchExamType(type: 'uts' | 'uas') {
  examType.value = type
  currentPage.value = 1
  loadData()
}

function openScheduleModal(item: ExamClassItem) {
  targetClassForSchedule.value = item
  scheduleForm.exam_date = item.exam_schedule?.exam_date || '2026-11-10'
  scheduleForm.start_time = item.exam_schedule?.start_time || '08:00'
  scheduleForm.end_time = item.exam_schedule?.end_time || '10:00'
  scheduleForm.room_id = item.exam_schedule?.room_id || null
  scheduleForm.notes = item.exam_schedule?.notes || ''
  scheduleDrawerOpen.value = true
}

function closeScheduleDrawer() {
  scheduleDrawerOpen.value = false
}

async function handleSaveSchedule() {
  if (!targetClassForSchedule.value) return
  if (!scheduleForm.exam_date) {
    toast.error('Tanggal ujian harus diisi')
    return
  }

  saving.value = true
  try {
    await examService.updateSchedule(targetClassForSchedule.value.class_id, {
      exam_type: examType.value,
      exam_date: scheduleForm.exam_date,
      start_time: scheduleForm.start_time,
      end_time: scheduleForm.end_time,
      room_id: scheduleForm.room_id,
      notes: scheduleForm.notes,
    })
    toast.success(`Jadwal ${examType.value.toUpperCase()} untuk kelas ${targetClassForSchedule.value.class_code} berhasil disimpan`)
    scheduleDrawerOpen.value = false
    await loadData()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal menyimpan jadwal ujian')
  } finally {
    saving.value = false
  }
}

function openProctorModal(item: ExamClassItem) {
  targetClassForProctor.value = item
  proctorForm.proctor_lecturer_id = item.exam_schedule?.proctor_lecturer_id || null
  proctorDrawerOpen.value = true
}

function closeProctorDrawer() {
  proctorDrawerOpen.value = false
}

async function handleSaveProctor() {
  if (!targetClassForProctor.value) return
  if (!proctorForm.proctor_lecturer_id) {
    toast.error('Pilih dosen pengawas terlebih dahulu')
    return
  }

  saving.value = true
  try {
    await examService.updateProctor(targetClassForProctor.value.class_id, {
      exam_type: examType.value,
      proctor_lecturer_id: proctorForm.proctor_lecturer_id,
    })
    toast.success(`Pengawas ujian untuk kelas ${targetClassForProctor.value.class_code} berhasil ditetapkan`)
    proctorDrawerOpen.value = false
    await loadData()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal menetapkan pengawas')
  } finally {
    saving.value = false
  }
}

async function handleAnnounce(type: 'uts' | 'uas') {
  announceDropdownOpen.value = false
  if (!confirm(`Umumkan jadwal ${type.toUpperCase()} kepada seluruh mahasiswa dan dosen?`)) return

  announcing.value = true
  try {
    const res = await examService.announce({ exam_type: type })
    toast.success(res.message || `Jadwal ${type.toUpperCase()} berhasil diumumkan!`)
    await loadData()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal mengumumkan jadwal ujian')
  } finally {
    announcing.value = false
  }
}

watch(
  [selectedSemesterId, selectedProdiId, selectedCurriculumYear],
  () => {
    currentPage.value = 1
    loadData()
  }
)

onMounted(() => {
  loadData()
})
</script>

<template>
  <PageContainer>
    <!-- Header (Matching Reference Screenshot) -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div>
        <nav class="flex items-center gap-1.5 text-xs text-slate-400 mb-1">
          <span class="text-brand-600 font-semibold tracking-wider uppercase">AKADEMIK</span>
        </nav>
        <h1 class="text-xl font-bold text-slate-900">Jadwal Ujian</h1>
        <p class="text-xs text-slate-500 mt-0.5">Manajemen Jadwal Ujian</p>
      </div>

      <!-- Action Button: Umumkan UTS / UAS (Dropdown) -->
      <div class="relative">
        <Button
          variant="primary"
          size="sm"
          :loading="announcing"
          class="bg-brand-900 hover:bg-brand-950 text-white flex items-center gap-1.5 shadow-2xs font-semibold"
          @click="announceDropdownOpen = !announceDropdownOpen"
        >
          <Volume2 class="w-3.5 h-3.5" />
          <span>Umumkan {{ examType.toUpperCase() }}</span>
          <ChevronDown class="w-3.5 h-3.5 ml-0.5" />
        </Button>

        <div
          v-if="announceDropdownOpen"
          class="absolute right-0 mt-1 w-52 bg-white border border-slate-200 rounded-lg shadow-xl z-30 py-1 text-xs"
        >
          <button
            type="button"
            class="w-full text-left px-3 py-2 hover:bg-slate-50 text-slate-700 font-medium flex items-center gap-2"
            @click="handleAnnounce('uts')"
          >
            <Volume2 class="w-3.5 h-3.5 text-brand-600" />
            <span>Umumkan Jadwal UTS</span>
          </button>
          <button
            type="button"
            class="w-full text-left px-3 py-2 hover:bg-slate-50 text-slate-700 font-medium flex items-center gap-2"
            @click="handleAnnounce('uas')"
          >
            <Volume2 class="w-3.5 h-3.5 text-brand-600" />
            <span>Umumkan Jadwal UAS</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Exam Type Switcher Cards (Matching Reference Screenshot) -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
      <!-- Jadwal UTS Card -->
      <div
        class="p-4 rounded-xl border-2 transition-all cursor-pointer flex items-start gap-3 bg-white"
        :class="examType === 'uts' ? 'border-brand-600 shadow-xs' : 'border-slate-200 hover:border-slate-300'"
        @click="switchExamType('uts')"
      >
        <div class="mt-0.5">
          <div
            class="w-4 h-4 rounded-full border-2 flex items-center justify-center"
            :class="examType === 'uts' ? 'border-brand-600' : 'border-slate-300'"
          >
            <div
              v-if="examType === 'uts'"
              class="w-2 h-2 rounded-full bg-brand-600"
            />
          </div>
        </div>
        <div>
          <div class="font-bold text-xs text-slate-900">Jadwal UTS</div>
          <div class="text-3xs text-slate-500 mt-0.5">
            Tampilkan jadwal Ujian Tengah Semester
          </div>
        </div>
      </div>

      <!-- Jadwal UAS Card -->
      <div
        class="p-4 rounded-xl border-2 transition-all cursor-pointer flex items-start gap-3 bg-white"
        :class="examType === 'uas' ? 'border-brand-600 shadow-xs' : 'border-slate-200 hover:border-slate-300'"
        @click="switchExamType('uas')"
      >
        <div class="mt-0.5">
          <div
            class="w-4 h-4 rounded-full border-2 flex items-center justify-center"
            :class="examType === 'uas' ? 'border-brand-600' : 'border-slate-300'"
          >
            <div
              v-if="examType === 'uas'"
              class="w-2 h-2 rounded-full bg-brand-600"
            />
          </div>
        </div>
        <div>
          <div class="font-bold text-xs text-slate-900">Jadwal UAS</div>
          <div class="text-3xs text-slate-500 mt-0.5">
            Tampilkan jadwal Ujian Akhir Semester
          </div>
        </div>
      </div>
    </div>

    <!-- Filter Row (Matching Reference Screenshot) -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
      <!-- Periode Akademik -->
      <div>
        <label class="block text-3xs font-semibold text-slate-600 mb-1">Periode Akademik</label>
        <select
          v-model="selectedSemesterId"
          class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs font-medium focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600 outline-none"
        >
          <option value="">Semua Periode Akademik</option>
          <option v-for="s in semesters" :key="s.id" :value="String(s.id)">
            {{ s.name }}
          </option>
        </select>
      </div>

      <!-- Unit Kerja -->
      <div>
        <label class="block text-3xs font-semibold text-slate-600 mb-1">Unit Kerja</label>
        <select
          v-model="selectedProdiId"
          class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs font-medium focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600 outline-none"
        >
          <option value="">Universitas Demo</option>
          <option v-for="p in studyPrograms" :key="p.id" :value="String(p.id)">
            {{ p.degree ? `${p.degree} - ${p.name}` : p.name }}
          </option>
        </select>
      </div>

      <!-- Tahun Kurikulum -->
      <div>
        <label class="block text-3xs font-semibold text-slate-600 mb-1">Tahun Kurikulum</label>
        <select
          v-model="selectedCurriculumYear"
          class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs font-medium focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600 outline-none"
        >
          <option value="">Semua Tahun Kurikulum</option>
          <option value="2025">2025</option>
          <option value="2026">2026</option>
          <option value="2027">2027</option>
        </select>
      </div>
    </div>

    <!-- Main Card & Table -->
    <Card class="border border-slate-200/80 shadow-2xs overflow-hidden">
      <!-- Toolbar -->
      <div class="p-4 border-b border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 bg-slate-50/50">
        <div class="flex items-center gap-2 w-full sm:w-auto text-xs text-slate-600">
          <span>Baris</span>
          <select
            v-model="perPage"
            class="px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg text-xs font-medium focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600 outline-none"
            @change="handlePerPageChange"
          >
            <option :value="10">10</option>
            <option :value="25">25</option>
            <option :value="50">50</option>
          </select>
        </div>

        <div class="flex items-center gap-2 w-full sm:w-auto">
          <div class="relative w-48 sm:w-64">
            <Input
              v-model="search"
              placeholder="Cari data..."
              class="w-full text-xs pr-8"
            />
          </div>
          <Button variant="primary" size="sm" class="bg-brand-700 hover:bg-brand-800 text-white text-xs px-3">
            Cari
          </Button>
        </div>
      </div>

      <!-- Table (Matching Reference Screenshot) -->
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-100/80 border-b border-slate-200/80 text-slate-600 font-bold uppercase tracking-wider text-3xs">
              <th class="py-3 px-4 w-12 text-center">NO</th>
              <th class="py-3 px-4 w-24 text-center">KURIKULUM</th>
              <th class="py-3 px-4 w-44">PROGRAM STUDI</th>
              <th class="py-3 px-4 w-52">MATA KULIAH</th>
              <th class="py-3 px-4 w-28">KELAS</th>
              <th class="py-3 px-4 text-center w-28">JUMLAH MAHASISWA</th>
              <th class="py-3 px-4 w-48">JADWAL UJIAN</th>
              <th class="py-3 px-4 w-24">RUANG</th>
              <th class="py-3 px-4 text-center w-28">DIUMUMKAN</th>
              <th class="py-3 px-4 w-24 text-center">AKSI</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <tr v-if="loading" class="hover:bg-transparent">
              <td colspan="10" class="py-12 text-center text-slate-400">
                Memuat data jadwal ujian...
              </td>
            </tr>
            <tr v-else-if="filteredClasses.length === 0" class="hover:bg-transparent">
              <td colspan="10" class="py-12 text-center text-slate-400">
                Belum ada data jadwal ujian
              </td>
            </tr>
            <tr
              v-for="(item, idx) in paginatedClasses"
              :key="item.class_id"
              class="hover:bg-slate-50/80 transition-colors"
            >
              <!-- NO -->
              <td class="py-3.5 px-4 text-center font-medium text-slate-500">
                {{ (currentPage - 1) * perPage + idx + 1 }}
              </td>

              <!-- Kurikulum -->
              <td class="py-3.5 px-4 text-center font-mono font-medium text-slate-600">
                {{ item.curriculum_year || 2026 }}
              </td>

              <!-- Program Studi -->
              <td class="py-3.5 px-4 text-slate-800">
                {{ item.study_program?.degree ? `${item.study_program.degree} - ${item.study_program.name}` : (item.study_program?.name || 'S1 - Multimedia') }}
              </td>

              <!-- Mata Kuliah & Kode -->
              <td class="py-3.5 px-4">
                <div class="font-bold text-slate-900">
                  {{ item.course?.name || item.class_name }}
                </div>
                <div class="text-3xs text-slate-500 font-mono mt-0.5">
                  {{ item.course?.code || item.class_code }}
                </div>
              </td>

              <!-- Kelas -->
              <td class="py-3.5 px-4 font-mono font-bold text-slate-800">
                {{ item.class_code }}
              </td>

              <!-- Jumlah Mahasiswa -->
              <td class="py-3.5 px-4 text-center font-mono text-slate-800 font-medium">
                {{ item.student_count || 0 }}
              </td>

              <!-- Jadwal Ujian -->
              <td class="py-3.5 px-4">
                <div v-if="item.exam_schedule?.exam_date" class="font-medium text-slate-800">
                  <div>{{ item.exam_schedule.exam_date }}</div>
                  <div class="text-3xs text-slate-500 font-mono">
                    {{ item.exam_schedule.start_time }} - {{ item.exam_schedule.end_time }}
                  </div>
                </div>
                <span v-else class="text-slate-400">
                  Belum Terjadwal
                </span>
              </td>

              <!-- Ruang -->
              <td class="py-3.5 px-4 text-slate-700">
                {{ item.exam_schedule?.room_name || '-' }}
              </td>

              <!-- Diumumkan -->
              <td class="py-3.5 px-4 text-center">
                <span
                  v-if="item.exam_schedule?.is_announced"
                  class="inline-flex items-center px-2 py-0.5 rounded text-3xs font-bold bg-emerald-100 text-emerald-800"
                >
                  Sudah Diumumkan
                </span>
                <span v-else class="text-slate-400">-</span>
              </td>

              <!-- Aksi -->
              <td class="py-3.5 px-4">
                <div class="flex items-center justify-center gap-1.5">
                  <!-- Atur Jadwal Ujian Button -->
                  <button
                    type="button"
                    class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-md border border-rose-200 transition-colors"
                    title="Atur Jadwal Ujian"
                    @click="openScheduleModal(item)"
                  >
                    <Calendar class="w-3.5 h-3.5" />
                  </button>

                  <!-- Atur Pengawas Ujian Button -->
                  <button
                    type="button"
                    class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-md border border-rose-200 transition-colors"
                    title="Tetapkan Pengawas Ujian"
                    @click="openProctorModal(item)"
                  >
                    <UserCheck class="w-3.5 h-3.5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <Pagination
        :current-page="currentPage"
        :last-page="totalPages"
        :total="filteredClasses.length"
        :per-page="perPage"
        :from="filteredClasses.length === 0 ? 0 : (currentPage - 1) * perPage + 1"
        :to="Math.min(currentPage * perPage, filteredClasses.length)"
        @page-change="handlePageChange"
      />
    </Card>

    <!-- Slide-over Drawer: Atur Jadwal Ujian -->
    <div
      v-if="scheduleDrawerOpen"
      class="fixed inset-0 z-50 flex justify-end bg-slate-900/40 backdrop-blur-xs transition-opacity"
      @click.self="closeScheduleDrawer"
    >
      <div class="w-full max-w-md bg-white shadow-2xl h-full flex flex-col animate-slide-left">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
          <div>
            <h3 class="text-sm font-bold text-slate-900">
              Atur Jadwal {{ examType.toUpperCase() }}
            </h3>
            <p class="text-3xs text-slate-500 mt-0.5">
              {{ targetClassForSchedule?.course?.name }} (Kelas {{ targetClassForSchedule?.class_code }})
            </p>
          </div>
          <button
            type="button"
            class="p-1 rounded-md text-slate-400 hover:text-slate-600 hover:bg-slate-100"
            @click="closeScheduleDrawer"
          >
            <X class="w-4 h-4" />
          </button>
        </div>

        <div class="p-5 overflow-y-auto flex-1 space-y-4 text-xs">
          <!-- Tanggal Ujian -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Tanggal Ujian <span class="text-rose-500">*</span>
            </label>
            <Input
              v-model="scheduleForm.exam_date"
              type="date"
              required
            />
          </div>

          <!-- Jam Mulai & Jam Selesai -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-semibold text-slate-700 mb-1.5">
                Jam Mulai <span class="text-rose-500">*</span>
              </label>
              <Input
                v-model="scheduleForm.start_time"
                type="time"
                required
              />
            </div>
            <div>
              <label class="block font-semibold text-slate-700 mb-1.5">
                Jam Selesai <span class="text-rose-500">*</span>
              </label>
              <Input
                v-model="scheduleForm.end_time"
                type="time"
                required
              />
            </div>
          </div>

          <!-- Ruangan Ujian -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Ruang Ujian
            </label>
            <Select
              v-model="scheduleForm.room_id"
              :options="roomOptions"
              placeholder="Pilih Ruang Ujian"
              search-placeholder="Cari ruang ujian..."
            />
          </div>

          <!-- Catatan -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Catatan Ujian
            </label>
            <textarea
              v-model="scheduleForm.notes"
              rows="3"
              class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600 outline-none"
              placeholder="Catatan opsional pelaksanaan ujian..."
            />
          </div>
        </div>

        <!-- Footer Actions -->
        <div class="p-4 border-t border-slate-100 bg-slate-50/50 flex items-center justify-end gap-2">
          <Button
            variant="outline"
            size="sm"
            class="border-rose-300 text-rose-700 hover:bg-rose-50 flex items-center gap-1.5"
            @click="closeScheduleDrawer"
          >
            <X class="w-3.5 h-3.5" />
            <span>Batal</span>
          </Button>
          <Button
            variant="primary"
            size="sm"
            :loading="saving"
            class="bg-brand-700 hover:bg-brand-800 text-white flex items-center gap-1.5 shadow-2xs font-semibold"
            @click="handleSaveSchedule"
          >
            <Check class="w-3.5 h-3.5" />
            <span>Simpan Data</span>
          </Button>
        </div>
      </div>
    </div>

    <!-- Slide-over Drawer: Tetapkan Pengawas Ujian -->
    <div
      v-if="proctorDrawerOpen"
      class="fixed inset-0 z-50 flex justify-end bg-slate-900/40 backdrop-blur-xs transition-opacity"
      @click.self="closeProctorDrawer"
    >
      <div class="w-full max-w-md bg-white shadow-2xl h-full flex flex-col animate-slide-left">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
          <div>
            <h3 class="text-sm font-bold text-slate-900">
              Tetapkan Pengawas {{ examType.toUpperCase() }}
            </h3>
            <p class="text-3xs text-slate-500 mt-0.5">
              {{ targetClassForProctor?.course?.name }} (Kelas {{ targetClassForProctor?.class_code }})
            </p>
          </div>
          <button
            type="button"
            class="p-1 rounded-md text-slate-400 hover:text-slate-600 hover:bg-slate-100"
            @click="closeProctorDrawer"
          >
            <X class="w-4 h-4" />
          </button>
        </div>

        <div class="p-5 overflow-y-auto flex-1 space-y-4 text-xs">
          <!-- Dosen Pengawas -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Dosen Pengawas Ujian <span class="text-rose-500">*</span>
            </label>
            <Select
              v-model="proctorForm.proctor_lecturer_id"
              :options="lecturerOptions"
              placeholder="Pilih Dosen Pengawas"
              search-placeholder="Cari nama dosen..."
            />
          </div>
        </div>

        <!-- Footer Actions -->
        <div class="p-4 border-t border-slate-100 bg-slate-50/50 flex items-center justify-end gap-2">
          <Button
            variant="outline"
            size="sm"
            class="border-rose-300 text-rose-700 hover:bg-rose-50 flex items-center gap-1.5"
            @click="closeProctorDrawer"
          >
            <X class="w-3.5 h-3.5" />
            <span>Batal</span>
          </Button>
          <Button
            variant="primary"
            size="sm"
            :loading="saving"
            class="bg-brand-700 hover:bg-brand-800 text-white flex items-center gap-1.5 shadow-2xs font-semibold"
            @click="handleSaveProctor"
          >
            <Check class="w-3.5 h-3.5" />
            <span>Simpan Pengawas</span>
          </Button>
        </div>
      </div>
    </div>
  </PageContainer>
</template>
