<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  Plus,
  Eye,
  MoreVertical,
  Edit2,
  Trash2,
  ArrowRightLeft,
  Users,
  FileText,
  CalendarRange,
} from 'lucide-vue-next'
import { mbkmService } from '@/services/api/mbkm'
import { academicService } from '@/services/api/academic'
import { useToast } from '@/composables/useToast'
import {
  LOCATION_MODE_LABELS,
  ORGANIZER_TYPE_LABELS,
  PROGRAM_STATUS_LABELS,
  PROGRAM_STATUS_VARIANTS,
  labelOf,
  optionsFrom,
} from '@/types/mbkm'
import type { MbkmProgram, MbkmProgramType, ProgramStatus } from '@/types/mbkm'
import type { Semester } from '@/types/academic'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Textarea from '@/components/ui/Textarea.vue'
import Modal from '@/components/ui/Modal.vue'
import MbkmStatusBadge from '@/pages/mbkm/components/MbkmStatusBadge.vue'
import MbkmFilterBar, { type MbkmFilters } from '@/pages/mbkm/components/MbkmFilterBar.vue'

const router = useRouter()
const toast = useToast()

const loading = ref(false)
const saving = ref(false)
const programs = ref<MbkmProgram[]>([])
const programTypes = ref<MbkmProgramType[]>([])
const semesters = ref<Semester[]>([])
const studyPrograms = ref<Array<{ id: number; name: string }>>([])
const faculties = ref<Array<{ id: number; name: string }>>([])

const totalData = ref(0)
const perPage = ref(10)
const currentPage = ref(1)
const searchQuery = ref('')
const filters = ref<MbkmFilters>({})

const showModal = ref(false)
const showTransitionModal = ref(false)
const isEditing = ref(false)
const editingId = ref<number | null>(null)
const transitionTarget = ref<MbkmProgram | null>(null)
const transitionStatus = ref('')
const transitionNotes = ref('')
const activeDropdownId = ref<number | null>(null)

/** Allowed lifecycle graph — mirrors ProgramStatus::transitions() on the server. */
const PROGRAM_TRANSITIONS: Record<ProgramStatus, ProgramStatus[]> = {
  draft: ['published', 'cancelled'],
  published: ['registration_open', 'cancelled'],
  registration_open: ['registration_closed', 'cancelled'],
  registration_closed: ['selection', 'registration_open', 'cancelled'],
  selection: ['ongoing', 'cancelled'],
  ongoing: ['completed', 'cancelled'],
  completed: ['archived'],
  cancelled: ['archived'],
  archived: [],
}

const form = reactive({
  program_type_id: null as number | null,
  code: '',
  name: '',
  description: '',
  organizer_type: 'study_program',
  organizer_name: '',
  faculty_id: null as number | null,
  study_program_id: null as number | null,
  semester_id: null as number | null,
  registration_start_date: '',
  registration_end_date: '',
  start_date: '',
  end_date: '',
  quota: null as number | null,
  min_semester: null as number | null,
  max_semester: null as number | null,
  min_gpa: null as number | null,
  min_credits: null as number | null,
  max_recognized_credits: null as number | null,
  participation_limit: null as number | null,
  location_mode: 'off_campus',
  requires_documents: false,
  requires_learning_agreement: false,
  requires_attendance: false,
  requires_logbook: true,
  logbook_period: 'weekly',
  requires_assessment: true,
  requires_final_report: false,
  requires_recognition: true,
  min_attendance_percentage: null as number | null,
  requirements_text: '',
  notes: '',
})

const availableTransitions = computed<ProgramStatus[]>(() => {
  if (!transitionTarget.value) return []
  return PROGRAM_TRANSITIONS[transitionTarget.value.status] ?? []
})

function toggleDropdown(id: number, event: Event) {
  event.stopPropagation()
  activeDropdownId.value = activeDropdownId.value === id ? null : id
}

function closeDropdown() {
  activeDropdownId.value = null
}

async function loadData() {
  loading.value = true
  try {
    const params: Record<string, unknown> = {
      page: currentPage.value,
      per_page: perPage.value,
      ...Object.fromEntries(Object.entries(filters.value).filter(([, v]) => v !== null && v !== undefined && v !== '')),
    }
    if (searchQuery.value.trim()) params.search = searchQuery.value.trim()

    const res = await mbkmService.getPrograms(params)
    programs.value = res.data || []
    totalData.value = res.meta?.total ?? programs.value.length
  } catch (err: any) {
    toast.error(err.message || 'Gagal memuat daftar program MBKM')
  } finally {
    loading.value = false
  }
}

async function loadDependencies() {
  try {
    const [typeRes, semRes, spRes, facRes] = await Promise.all([
      mbkmService.getProgramTypes({ per_page: 100 }),
      academicService.getSemesters(),
      academicService.getStudyPrograms(),
      academicService.getFaculties(),
    ])
    programTypes.value = typeRes.data || []
    semesters.value = semRes.data || []
    studyPrograms.value = (spRes.data || []) as any
    faculties.value = (facRes.data || []) as any
  } catch {
    toast.warning('Sebagian data referensi gagal dimuat')
  }
}

function resetForm() {
  Object.assign(form, {
    program_type_id: programTypes.value[0]?.id ?? null,
    code: '',
    name: '',
    description: '',
    organizer_type: 'study_program',
    organizer_name: '',
    faculty_id: null,
    study_program_id: null,
    semester_id: semesters.value[0]?.id ?? null,
    registration_start_date: '',
    registration_end_date: '',
    start_date: '',
    end_date: '',
    quota: null,
    min_semester: null,
    max_semester: null,
    min_gpa: null,
    min_credits: null,
    max_recognized_credits: null,
    participation_limit: null,
    location_mode: 'off_campus',
    requires_documents: false,
    requires_learning_agreement: false,
    requires_attendance: false,
    requires_logbook: true,
    logbook_period: 'weekly',
    requires_assessment: true,
    requires_final_report: false,
    requires_recognition: true,
    min_attendance_percentage: null,
    requirements_text: '',
    notes: '',
  })
}

function openCreate() {
  isEditing.value = false
  editingId.value = null
  resetForm()
  showModal.value = true
}

function openEdit(program: MbkmProgram) {
  isEditing.value = true
  editingId.value = program.id
  Object.assign(form, {
    program_type_id: program.program_type_id,
    code: program.code,
    name: program.name,
    description: program.description ?? '',
    organizer_type: program.organizer_type,
    organizer_name: program.organizer_name ?? '',
    faculty_id: program.faculty_id ?? null,
    study_program_id: program.study_program_id ?? null,
    semester_id: program.semester_id ?? null,
    registration_start_date: program.registration_start_date ?? '',
    registration_end_date: program.registration_end_date ?? '',
    start_date: program.start_date ?? '',
    end_date: program.end_date ?? '',
    quota: program.quota ?? null,
    min_semester: program.min_semester ?? null,
    max_semester: program.max_semester ?? null,
    min_gpa: program.min_gpa != null ? Number(program.min_gpa) : null,
    min_credits: program.min_credits ?? null,
    max_recognized_credits: program.max_recognized_credits ?? null,
    participation_limit: program.participation_limit ?? null,
    location_mode: program.location_mode,
    requires_documents: program.requires_documents,
    requires_learning_agreement: program.requires_learning_agreement,
    requires_attendance: program.requires_attendance,
    requires_logbook: program.requires_logbook,
    logbook_period: program.logbook_period,
    requires_assessment: program.requires_assessment,
    requires_final_report: program.requires_final_report,
    requires_recognition: program.requires_recognition,
    min_attendance_percentage:
      program.min_attendance_percentage != null ? Number(program.min_attendance_percentage) : null,
    requirements_text: program.requirements_text ?? '',
    notes: program.notes ?? '',
  })
  showModal.value = true
  closeDropdown()
}

async function handleSave() {
  if (!form.code.trim() || !form.name.trim()) {
    toast.error('Kode dan nama program wajib diisi')
    return
  }
  if (!form.program_type_id) {
    toast.error('Jenis program wajib dipilih')
    return
  }

  saving.value = true
  try {
    const payload = { ...form } as Partial<MbkmProgram>
    // Empty date strings must not be sent as ''.
    ;(['registration_start_date', 'registration_end_date', 'start_date', 'end_date'] as const).forEach((key) => {
      if (!payload[key]) delete payload[key]
    })

    if (isEditing.value && editingId.value) {
      await mbkmService.updateProgram(editingId.value, payload)
      toast.success('Program MBKM berhasil diperbarui')
    } else {
      await mbkmService.createProgram(payload)
      toast.success('Program MBKM berhasil dibuat')
    }
    showModal.value = false
    await loadData()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menyimpan program MBKM')
  } finally {
    saving.value = false
  }
}

function openTransition(program: MbkmProgram) {
  transitionTarget.value = program
  transitionStatus.value = ''
  transitionNotes.value = ''
  showTransitionModal.value = true
  closeDropdown()
}

async function handleTransition() {
  if (!transitionTarget.value || !transitionStatus.value) {
    toast.error('Pilih status tujuan terlebih dahulu')
    return
  }
  saving.value = true
  try {
    await mbkmService.transitionProgram(transitionTarget.value.id, transitionStatus.value, transitionNotes.value || undefined)
    toast.success('Status program berhasil diperbarui')
    showTransitionModal.value = false
    await loadData()
  } catch (err: any) {
    toast.error(err.message || 'Transisi status program ditolak')
  } finally {
    saving.value = false
  }
}

async function handleDelete(program: MbkmProgram) {
  closeDropdown()
  if (!confirm(`Hapus program MBKM "${program.name}"? Program yang sudah memiliki peserta tidak dapat dihapus.`)) return

  try {
    await mbkmService.deleteProgram(program.id)
    toast.success('Program MBKM berhasil dihapus')
    await loadData()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menghapus program MBKM')
  }
}

function formatDate(value?: string | null) {
  if (!value) return '-'
  try {
    return new Date(value).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
  } catch {
    return value
  }
}

function handleSearch() {
  currentPage.value = 1
  loadData()
}

function handleFilterChange() {
  currentPage.value = 1
  loadData()
}

onMounted(async () => {
  await loadDependencies()
  await loadData()
  document.addEventListener('click', closeDropdown)
})
</script>

<template>
  <PageContainer>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div>
        <h1 class="text-xl font-bold text-slate-900 tracking-tight">Program MBKM</h1>
        <p class="text-xs text-slate-500 mt-1">
          Master program Merdeka Belajar Kampus Merdeka beserta siklus hidupnya
        </p>
      </div>
      <Button
        variant="primary"
        size="sm"
        class="bg-brand-900 hover:bg-brand-950 text-white flex items-center gap-1.5 font-semibold px-4 py-2"
        @click="openCreate"
      >
        <Plus class="w-4 h-4" />
        <span>Tambah Program</span>
      </Button>
    </div>

    <Card class="border border-slate-200/80 shadow-2xs overflow-visible">
      <div class="p-4 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-2 text-xs text-slate-600">
          <span>Baris</span>
          <select
            v-model="perPage"
            class="px-2.5 py-1 bg-white border border-slate-300 rounded-lg text-xs outline-none"
            @change="handleFilterChange"
          >
            <option :value="10">10</option>
            <option :value="25">25</option>
            <option :value="50">50</option>
          </select>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
          <MbkmFilterBar
            v-model="filters"
            :fields="['semester_id', 'program_type_id', 'study_program_id', 'status']"
            :status-options="optionsFrom(PROGRAM_STATUS_LABELS)"
            @change="handleFilterChange"
          />

          <div class="flex items-center">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Cari kode / nama program..."
              class="w-48 sm:w-64 px-3 py-1.5 bg-white border border-slate-300 rounded-l-lg text-xs outline-none focus:ring-1 focus:ring-brand-500"
              @keyup.enter="handleSearch"
            />
            <button
              type="button"
              class="px-3.5 py-1.5 bg-white hover:bg-slate-50 border border-l-0 border-slate-300 rounded-r-lg text-xs font-semibold text-rose-700"
              @click="handleSearch"
            >
              Cari
            </button>
          </div>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
          <thead>
            <tr class="bg-slate-50/80 text-slate-600 font-semibold border-b border-slate-200 text-3xs uppercase tracking-wider">
              <th class="py-3 px-4 w-10 text-center">NO</th>
              <th class="py-3 px-4">PROGRAM</th>
              <th class="py-3 px-4">JENIS</th>
              <th class="py-3 px-4">PERIODE</th>
              <th class="py-3 px-4 text-center">PENDAFTAR</th>
              <th class="py-3 px-4 text-center">PESERTA / KUOTA</th>
              <th class="py-3 px-4 text-center">STATUS</th>
              <th class="py-3 px-4 text-center w-24">AKSI</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <tr v-if="loading">
              <td colspan="8" class="py-8 text-center text-slate-400">Memuat data program MBKM...</td>
            </tr>
            <tr v-else-if="programs.length === 0">
              <td colspan="8" class="py-8 text-center text-slate-400">Belum ada program MBKM.</td>
            </tr>
            <tr v-for="(item, index) in programs" :key="item.id" class="hover:bg-slate-50/80 transition-colors">
              <td class="py-3.5 px-4 text-center text-slate-500 font-medium">{{ index + 1 }}</td>
              <td class="py-3.5 px-4">
                <p class="font-semibold text-slate-900">{{ item.name }}</p>
                <p class="text-2xs text-slate-500 font-mono">{{ item.code }}</p>
                <p v-if="item.organizer_name" class="text-2xs text-slate-400 mt-0.5">
                  {{ item.organizer_name }}
                </p>
              </td>
              <td class="py-3.5 px-4">
                <span class="text-slate-800">{{ item.program_type?.name ?? '-' }}</span>
                <p class="text-2xs text-slate-400">{{ labelOf(LOCATION_MODE_LABELS, item.location_mode) }}</p>
              </td>
              <td class="py-3.5 px-4">
                <p class="text-slate-800">{{ item.semester?.name ?? '-' }}</p>
                <p class="text-2xs text-slate-400 flex items-center gap-1 mt-0.5">
                  <CalendarRange class="w-3 h-3" />
                  {{ formatDate(item.start_date) }} – {{ formatDate(item.end_date) }}
                </p>
              </td>
              <td class="py-3.5 px-4 text-center">
                <span class="inline-flex items-center gap-1 text-slate-700 font-medium">
                  <FileText class="w-3 h-3 text-slate-400" />
                  {{ item.applications_count ?? 0 }}
                </span>
              </td>
              <td class="py-3.5 px-4 text-center">
                <span class="inline-flex items-center gap-1 font-medium text-slate-800">
                  <Users class="w-3 h-3 text-slate-400" />
                  {{ item.quota_used ?? item.participants_count ?? 0 }}
                  <span class="text-slate-400">/ {{ item.quota ?? '∞' }}</span>
                </span>
              </td>
              <td class="py-3.5 px-4 text-center">
                <MbkmStatusBadge
                  :value="item.status"
                  :labels="PROGRAM_STATUS_LABELS"
                  :variants="PROGRAM_STATUS_VARIANTS"
                />
              </td>
              <td class="py-3.5 px-4 text-center relative">
                <div class="flex items-center justify-center gap-1.5">
                  <button
                    type="button"
                    class="p-1.5 rounded-lg border border-rose-300 text-rose-700 hover:bg-rose-50"
                    title="Kelola Program"
                    @click="router.push(`/mbkm/programs/${item.id}`)"
                  >
                    <Eye class="w-3.5 h-3.5" />
                  </button>
                  <button
                    type="button"
                    class="p-1.5 rounded-lg border border-rose-300 text-rose-700 hover:bg-rose-50"
                    @click="toggleDropdown(item.id, $event)"
                  >
                    <MoreVertical class="w-3.5 h-3.5" />
                  </button>
                </div>

                <div
                  v-if="activeDropdownId === item.id"
                  class="absolute right-4 mt-1 w-44 bg-white border border-slate-200 rounded-xl shadow-xl z-50 py-1 text-left"
                  @click.stop
                >
                  <button
                    type="button"
                    class="w-full px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50 flex items-center gap-2"
                    @click="openEdit(item)"
                  >
                    <Edit2 class="w-3.5 h-3.5 text-blue-600" />
                    <span>Edit Program</span>
                  </button>
                  <button
                    type="button"
                    class="w-full px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50 flex items-center gap-2"
                    @click="router.push(`/mbkm/programs/${item.id}`)"
                  >
                    <FileText class="w-3.5 h-3.5 text-emerald-600" />
                    <span>Konfigurasi</span>
                  </button>
                  <button
                    type="button"
                    class="w-full px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50 flex items-center gap-2"
                    @click="openTransition(item)"
                  >
                    <ArrowRightLeft class="w-3.5 h-3.5 text-amber-600" />
                    <span>Ubah Status</span>
                  </button>
                  <button
                    type="button"
                    class="w-full px-3 py-1.5 text-xs text-rose-600 hover:bg-rose-50 flex items-center gap-2"
                    @click="handleDelete(item)"
                  >
                    <Trash2 class="w-3.5 h-3.5" />
                    <span>Hapus</span>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="p-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
        <div>Total {{ totalData }} program MBKM</div>
        <div class="flex items-center gap-1">
          <button
            class="w-7 h-7 flex items-center justify-center rounded-lg border border-slate-200 text-slate-400 hover:bg-slate-50 disabled:opacity-40"
            :disabled="currentPage === 1"
            @click="currentPage--; loadData()"
          >
            ‹
          </button>
          <span class="w-7 h-7 flex items-center justify-center rounded-lg bg-brand-900 text-white font-bold text-xs">
            {{ currentPage }}
          </span>
          <button
            class="w-7 h-7 flex items-center justify-center rounded-lg border border-slate-200 text-slate-400 hover:bg-slate-50 disabled:opacity-40"
            :disabled="programs.length < perPage"
            @click="currentPage++; loadData()"
          >
            ›
          </button>
        </div>
      </div>
    </Card>

    <!-- Create / Edit -->
    <Modal
      v-model:open="showModal"
      :title="isEditing ? 'Edit Program MBKM' : 'Tambah Program MBKM'"
      size="xl"
    >
      <form class="space-y-5 text-xs" @submit.prevent="handleSave">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Jenis Program <span class="text-rose-500">*</span></label>
            <select
              v-model="form.program_type_id"
              class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none focus:ring-2 focus:ring-brand-500/20"
            >
              <option :value="null">Pilih jenis program</option>
              <option v-for="t in programTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Kode Program <span class="text-rose-500">*</span></label>
            <Input v-model="form.code" placeholder="mis. MBKM-MAGANG-2026" />
          </div>
        </div>

        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Nama Program <span class="text-rose-500">*</span></label>
          <Input v-model="form.name" placeholder="mis. Magang Bersertifikat Batch 3" />
        </div>

        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Deskripsi</label>
          <Textarea v-model="form.description" :rows="3" placeholder="Ringkasan program MBKM" />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Penyelenggara</label>
            <select
              v-model="form.organizer_type"
              class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none"
            >
              <option v-for="o in optionsFrom(ORGANIZER_TYPE_LABELS)" :key="o.value" :value="o.value">
                {{ o.label }}
              </option>
            </select>
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Nama Penyelenggara</label>
            <Input v-model="form.organizer_name" placeholder="mis. Kemdikbudristek" />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Periode Akademik</label>
            <select
              v-model="form.semester_id"
              class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none"
            >
              <option :value="null">Pilih periode</option>
              <option v-for="s in semesters" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Fakultas (opsional)</label>
            <select
              v-model="form.faculty_id"
              class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none"
            >
              <option :value="null">Semua Fakultas</option>
              <option v-for="f in faculties" :key="f.id" :value="f.id">{{ f.name }}</option>
            </select>
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Program Studi (opsional)</label>
            <select
              v-model="form.study_program_id"
              class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none"
            >
              <option :value="null">Semua Prodi</option>
              <option v-for="p in studyPrograms" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Mode Lokasi</label>
            <select
              v-model="form.location_mode"
              class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none"
            >
              <option v-for="o in optionsFrom(LOCATION_MODE_LABELS)" :key="o.value" :value="o.value">
                {{ o.label }}
              </option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Buka Pendaftaran</label>
            <Input v-model="form.registration_start_date" type="date" />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Tutup Pendaftaran</label>
            <Input v-model="form.registration_end_date" type="date" />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Mulai Pelaksanaan</label>
            <Input v-model="form.start_date" type="date" />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Selesai Pelaksanaan</label>
            <Input v-model="form.end_date" type="date" />
          </div>
        </div>

        <div class="pt-2 border-t border-slate-100">
          <p class="font-bold text-slate-800 mb-3">Kapasitas &amp; Kelayakan</p>
          <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
            <div>
              <label class="block font-semibold text-slate-700 mb-1.5">Kuota</label>
              <Input v-model.number="form.quota" type="number" min="0" placeholder="Kosong = tanpa batas" />
            </div>
            <div>
              <label class="block font-semibold text-slate-700 mb-1.5">Semester Min</label>
              <Input v-model.number="form.min_semester" type="number" min="1" max="14" />
            </div>
            <div>
              <label class="block font-semibold text-slate-700 mb-1.5">Semester Maks</label>
              <Input v-model.number="form.max_semester" type="number" min="1" max="14" />
            </div>
            <div>
              <label class="block font-semibold text-slate-700 mb-1.5">IPK Minimum</label>
              <Input v-model.number="form.min_gpa" type="number" step="0.01" min="0" max="4" />
            </div>
            <div>
              <label class="block font-semibold text-slate-700 mb-1.5">SKS Lulus Minimum</label>
              <Input v-model.number="form.min_credits" type="number" min="0" />
            </div>
            <div>
              <label class="block font-semibold text-slate-700 mb-1.5">Maks SKS Diakui</label>
              <Input v-model.number="form.max_recognized_credits" type="number" min="0" />
            </div>
            <div>
              <label class="block font-semibold text-slate-700 mb-1.5">Batas Keikutsertaan</label>
              <Input v-model.number="form.participation_limit" type="number" min="0" />
            </div>
            <div>
              <label class="block font-semibold text-slate-700 mb-1.5">Min % Kehadiran</label>
              <Input v-model.number="form.min_attendance_percentage" type="number" step="0.01" min="0" max="100" />
            </div>
          </div>
        </div>

        <div class="pt-2 border-t border-slate-100">
          <p class="font-bold text-slate-800 mb-3">Kebijakan Tahapan (menentukan langkah wajib)</p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
            <label class="flex items-center gap-2 cursor-pointer">
              <input v-model="form.requires_documents" type="checkbox" class="w-4 h-4 rounded border-slate-300 text-brand-900" />
              <span class="text-slate-800">Wajib unggah dokumen pendaftaran</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input v-model="form.requires_learning_agreement" type="checkbox" class="w-4 h-4 rounded border-slate-300 text-brand-900" />
              <span class="text-slate-800">Wajib learning agreement</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input v-model="form.requires_attendance" type="checkbox" class="w-4 h-4 rounded border-slate-300 text-brand-900" />
              <span class="text-slate-800">Wajib presensi MBKM</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input v-model="form.requires_logbook" type="checkbox" class="w-4 h-4 rounded border-slate-300 text-brand-900" />
              <span class="text-slate-800">Wajib logbook aktivitas</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input v-model="form.requires_assessment" type="checkbox" class="w-4 h-4 rounded border-slate-300 text-brand-900" />
              <span class="text-slate-800">Wajib penilaian</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input v-model="form.requires_final_report" type="checkbox" class="w-4 h-4 rounded border-slate-300 text-brand-900" />
              <span class="text-slate-800">Wajib laporan akhir</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input v-model="form.requires_recognition" type="checkbox" class="w-4 h-4 rounded border-slate-300 text-brand-900" />
              <span class="text-slate-800">Wajib rekognisi SKS</span>
            </label>
            <div class="flex items-center gap-2">
              <span class="text-slate-700 font-medium">Periode logbook</span>
              <select
                v-model="form.logbook_period"
                class="flex-1 px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg text-xs outline-none"
              >
                <option value="daily">Harian</option>
                <option value="weekly">Mingguan</option>
                <option value="monthly">Bulanan</option>
                <option value="periodic">Periodik</option>
              </select>
            </div>
          </div>
        </div>

        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Catatan Persyaratan</label>
          <Textarea v-model="form.requirements_text" :rows="2" placeholder="Persyaratan tambahan yang bersifat naratif" />
        </div>
      </form>

      <template #footer>
        <div class="flex items-center justify-end gap-2">
          <Button variant="outline" size="sm" @click="showModal = false">Batal</Button>
          <Button
            variant="primary"
            size="sm"
            :loading="saving"
            class="bg-brand-900 hover:bg-brand-950 text-white"
            @click="handleSave"
          >
            {{ isEditing ? 'Simpan Perubahan' : 'Simpan Program' }}
          </Button>
        </div>
      </template>
    </Modal>

    <!-- Lifecycle transition -->
    <Modal v-model:open="showTransitionModal" title="Ubah Status Program" size="md">
      <div class="space-y-4 text-xs">
        <div class="p-3 bg-slate-50 rounded-lg border border-slate-200">
          <p class="font-semibold text-slate-900">{{ transitionTarget?.name }}</p>
          <p class="text-2xs text-slate-500 mt-0.5">
            Status saat ini:
            <MbkmStatusBadge
              :value="transitionTarget?.status"
              :labels="PROGRAM_STATUS_LABELS"
              :variants="PROGRAM_STATUS_VARIANTS"
            />
          </p>
        </div>

        <div v-if="availableTransitions.length === 0" class="text-slate-500">
          Tidak ada transisi status yang tersedia dari status saat ini.
        </div>

        <div v-else>
          <label class="block font-semibold text-slate-700 mb-1.5">Status Tujuan</label>
          <select
            v-model="transitionStatus"
            class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none"
          >
            <option value="">Pilih status tujuan</option>
            <option v-for="s in availableTransitions" :key="s" :value="s">
              {{ PROGRAM_STATUS_LABELS[s] ?? s }}
            </option>
          </select>
          <p class="mt-1.5 text-2xs text-slate-500">
            Transisi divalidasi di server — hanya perpindahan yang diizinkan pada siklus hidup program.
          </p>
        </div>

        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Catatan</label>
          <Textarea v-model="transitionNotes" :rows="2" />
        </div>
      </div>

      <template #footer>
        <div class="flex items-center justify-end gap-2">
          <Button variant="outline" size="sm" @click="showTransitionModal = false">Batal</Button>
          <Button
            variant="primary"
            size="sm"
            :loading="saving"
            :disabled="availableTransitions.length === 0"
            class="bg-brand-900 hover:bg-brand-950 text-white"
            @click="handleTransition"
          >
            Ubah Status
          </Button>
        </div>
      </template>
    </Modal>
  </PageContainer>
</template>
