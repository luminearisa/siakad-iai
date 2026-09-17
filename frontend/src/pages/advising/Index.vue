<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  UserPlus,
  RefreshCw,
  Filter,
  Edit2,
  Eye,
  X,
  Check,
} from 'lucide-vue-next'
import { advisingService } from '@/services/api/advising'
import { lecturerService } from '@/services/api/lecturers'
import { academicService } from '@/services/api/academic'
import { useToast } from '@/composables/useToast'
import { usePermissions } from '@/composables/usePermissions'
import { useAuth } from '@/composables/useAuth'
import type { Lecturer } from '@/types/lecturer'
import type { StudyProgram } from '@/types/academic'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import Pagination from '@/components/data-display/Pagination.vue'
import PersonalScopeNotice from '@/components/feedback/PersonalScopeNotice.vue'

const router = useRouter()
const toast = useToast()
const { can } = usePermissions()
const { isLecturer } = useAuth()

// A plain lecturer (no advising management rights) only ever sees the students
// they advise, because the API scopes the listing to their lecturer profile.
const showLecturerScope = computed<boolean>(() => isLecturer.value && !can('advising.assign'))
const columnCount = computed<number>(() => (showLecturerScope.value ? 6 : 7))

const loading = ref<boolean>(false)
const generating = ref<boolean>(false)
const saving = ref<boolean>(false)
const students = ref<any[]>([])
const lecturers = ref<Lecturer[]>([])

// Daftar dosen bisa sangat panjang -> pakai search select.
const lecturerOptions = computed(() => [
  { value: null as number | null, label: 'Pilih Dosen Wali' },
  ...lecturers.value.map((l) => ({ value: l.id as number | null, label: l.full_name })),
])
const studyPrograms = ref<StudyProgram[]>([])

const search = ref<string>('')
const perPage = ref<number>(10)
const currentPage = ref<number>(1)
const selectedIds = ref<number[]>([])

// Filter Popover
const filterOpen = ref<boolean>(false)
const filterAngkatan = ref<string>('')
const filterProdi = ref<string>('')
const filterStatus = ref<string>('')

// Drawer Assign Dosen Wali
const drawerOpen = ref<boolean>(false)
const isBulk = ref<boolean>(false)
const targetStudent = ref<any | null>(null)

const drawerForm = reactive({
  lecturer_id: null as number | null,
  notes: '',
})

const filteredStudents = computed(() => {
  let list = students.value

  if (filterAngkatan.value) {
    list = list.filter((s) => String(s.admission_year) === filterAngkatan.value)
  }
  if (filterProdi.value) {
    list = list.filter((s) => String(s.study_program_id) === filterProdi.value)
  }
  if (filterStatus.value) {
    list = list.filter((s) => s.status === filterStatus.value)
  }

  if (!search.value) return list
  const q = search.value.toLowerCase()
  return list.filter(
    (s) =>
      s.full_name?.toLowerCase().includes(q) ||
      s.student_number?.toLowerCase().includes(q) ||
      s.study_program?.name?.toLowerCase().includes(q) ||
      (s.academic_advisor && s.academic_advisor.toLowerCase().includes(q))
  )
})

const totalPages = computed(() => {
  return Math.max(1, Math.ceil(filteredStudents.value.length / perPage.value))
})

// Derived from the loaded rows so the filter keeps working for any cohort,
// instead of the previously hardcoded 2025/2026/2027 options.
const availableYears = computed<string[]>(() => {
  const years = new Set<string>()
  students.value.forEach((s) => {
    if (s.admission_year) years.add(String(s.admission_year))
  })
  return Array.from(years).sort((a, b) => b.localeCompare(a))
})

const paginatedStudents = computed(() => {
  const start = (currentPage.value - 1) * perPage.value
  return filteredStudents.value.slice(start, start + perPage.value)
})

const isAllSelected = computed(() => {
  const current = paginatedStudents.value
  return current.length > 0 && current.every((s) => selectedIds.value.includes(s.id))
})

function toggleSelectAll() {
  const current = paginatedStudents.value
  if (isAllSelected.value) {
    selectedIds.value = selectedIds.value.filter((id) => !current.some((c) => c.id === id))
  } else {
    const idsToAdd = current.map((c) => c.id).filter((id) => !selectedIds.value.includes(id))
    selectedIds.value.push(...idsToAdd)
  }
}

function toggleSelectOne(id: number) {
  const idx = selectedIds.value.indexOf(id)
  if (idx > -1) {
    selectedIds.value.splice(idx, 1)
  } else {
    selectedIds.value.push(id)
  }
}

function handlePageChange(page: number) {
  currentPage.value = page
}

function handlePerPageChange() {
  currentPage.value = 1
}

function formatStatus(status: string) {
  switch (status) {
    case 'active':
      return 'Aktif'
    case 'withdrawn':
      return 'Mengundurkan Diri / Keluar'
    case 'leave':
      return 'Cuti'
    case 'inactive':
      return 'Non Aktif'
    case 'graduated':
      return 'Lulus'
    default:
      return status || 'Aktif'
  }
}

async function loadData() {
  loading.value = true
  try {
    const [distRes, prodiRes] = await Promise.all([
      advisingService.getDistribution(),
      academicService.getStudyPrograms(),
    ])
    students.value = distRes.data || []
    studyPrograms.value = prodiRes.data || []

    // Only staff who may assign advisors need the lecturer picker.
    if (showLecturerScope.value) {
      lecturers.value = []
    } else {
      const lecRes = await lecturerService.list({ per_page: 100 })
      lecturers.value = lecRes.data || []
    }
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal memuat data distribusi pembimbing')
  } finally {
    loading.value = false
  }
}

async function handleGenerate() {
  if (showLecturerScope.value) {
    toast.error('Anda tidak memiliki hak untuk generate distribusi dosen wali')
    return
  }
  if (!confirm('Otomatis distribusikan dosen wali untuk mahasiswa aktif yang belum memiliki pembimbing?')) return

  generating.value = true
  try {
    const res = await advisingService.generateDistribution()
    toast.success(res.message || 'Distribusi pembimbing akademik berhasil digenerate!')
    await loadData()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal generate distribusi pembimbing')
  } finally {
    generating.value = false
  }
}

function openAssignBulk() {
  if (showLecturerScope.value) {
    toast.error('Anda tidak memiliki hak untuk menetapkan dosen wali')
    return
  }
  if (selectedIds.value.length === 0) {
    toast.error('Pilih minimal satu mahasiswa dari tabel terlebih dahulu')
    return
  }
  isBulk.value = true
  targetStudent.value = null
  drawerForm.lecturer_id = null
  drawerForm.notes = ''
  drawerOpen.value = true
}

function openEditSingle(student: any) {
  if (showLecturerScope.value) {
    toast.error('Anda tidak memiliki hak untuk mengubah dosen wali')
    return
  }
  isBulk.value = false
  targetStudent.value = student
  drawerForm.lecturer_id = student.academic_advisor_id || null
  drawerForm.notes = ''
  drawerOpen.value = true
}

function closeDrawer() {
  drawerOpen.value = false
}

async function handleSaveDrawer() {
  if (!drawerForm.lecturer_id) {
    toast.error('Pilih dosen wali terlebih dahulu')
    return
  }

  saving.value = true
  try {
    const ids = isBulk.value ? selectedIds.value : [targetStudent.value.id]
    await advisingService.batchAssign({
      student_ids: ids,
      lecturer_id: drawerForm.lecturer_id,
      notes: drawerForm.notes,
    })
    toast.success(`Dosen wali berhasil ditetapkan untuk ${ids.length} mahasiswa`)
    drawerOpen.value = false
    selectedIds.value = []
    await loadData()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal menyimpan dosen wali')
  } finally {
    saving.value = false
  }
}

function viewDetail(student: any) {
  router.push(`/students/${student.id}`)
}

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
        <h1 class="text-xl font-bold text-slate-900">
          {{ showLecturerScope ? 'Mahasiswa Bimbingan Saya' : 'Distribusi Pembimbing Akademik' }}
        </h1>
        <p class="text-xs text-slate-500 mt-0.5">
          {{ showLecturerScope
            ? 'Daftar mahasiswa yang Anda bimbing sebagai Dosen Pembimbing Akademik'
            : 'Manajemen Distribusi Pembimbing Akademik' }}
        </p>
      </div>

      <!-- Action Buttons -->
      <div v-if="!showLecturerScope" class="flex items-center gap-2 self-start sm:self-auto">
        <!-- + Dosen Wali Button (Dark Red/Brown) -->
        <Button
          variant="primary"
          size="sm"
          class="bg-brand-900 hover:bg-brand-950 text-white flex items-center gap-1.5 shadow-2xs font-semibold"
          @click="openAssignBulk"
        >
          <UserPlus class="w-3.5 h-3.5" />
          <span>Dosen Wali</span>
        </Button>

        <!-- Generate Button (White with Red outline) -->
        <Button
          variant="outline"
          size="sm"
          :loading="generating"
          class="border-rose-300 text-rose-700 hover:bg-rose-50 flex items-center gap-1.5 shadow-2xs font-semibold"
          @click="handleGenerate"
        >
          <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': generating }" />
          <span>Generate</span>
        </Button>
      </div>
    </div>

    <!-- Lecturer scope notice -->
    <PersonalScopeNotice
      v-if="showLecturerScope"
      subject="mahasiswa bimbingan akademik"
      class="mb-4"
    />

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
          <!-- Filter Button -->
          <button
            type="button"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 border rounded-lg text-xs font-semibold transition-colors"
            :class="filterOpen || filterAngkatan || filterProdi || filterStatus ? 'border-rose-300 text-rose-700 bg-rose-50' : 'border-slate-300 text-slate-700 bg-white hover:bg-slate-50'"
            @click="filterOpen = !filterOpen"
          >
            <Filter class="w-3.5 h-3.5 text-rose-600" />
            <span>Filter *</span>
          </button>

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

      <!-- Filter Panel (Collapsible) -->
      <div
        v-if="filterOpen"
        class="p-4 bg-rose-50/30 border-b border-rose-100 flex flex-wrap items-center gap-4 text-xs"
      >
        <div class="flex items-center gap-2">
          <label class="font-semibold text-slate-700">Angkatan:</label>
          <select
            v-model="filterAngkatan"
            class="px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-brand-500/20 outline-none"
          >
            <option value="">Semua Angkatan</option>
            <option v-for="year in availableYears" :key="year" :value="year">
              {{ year }}
            </option>
          </select>
        </div>

        <div class="flex items-center gap-2">
          <label class="font-semibold text-slate-700">Program Studi:</label>
          <select
            v-model="filterProdi"
            class="px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-brand-500/20 outline-none"
          >
            <option value="">Semua Program Studi</option>
            <option v-for="sp in studyPrograms" :key="sp.id" :value="String(sp.id)">
              {{ sp.degree ? `${sp.degree} - ${sp.name}` : sp.name }}
            </option>
          </select>
        </div>

        <div class="flex items-center gap-2">
          <label class="font-semibold text-slate-700">Status Mahasiswa:</label>
          <select
            v-model="filterStatus"
            class="px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-brand-500/20 outline-none"
          >
            <option value="">Semua Status</option>
            <option value="active">Aktif</option>
            <option value="withdrawn">Mengundurkan Diri / Keluar</option>
            <option value="leave">Cuti</option>
            <option value="inactive">Non Aktif</option>
          </select>
        </div>

        <button
          v-if="filterAngkatan || filterProdi || filterStatus"
          type="button"
          class="text-xs text-rose-600 hover:underline font-medium"
          @click="filterAngkatan = ''; filterProdi = ''; filterStatus = ''"
        >
          Reset Filter
        </button>
      </div>

      <!-- Table (Matching Screenshot) -->
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-100/80 border-b border-slate-200/80 text-slate-600 font-bold uppercase tracking-wider text-3xs">
              <th v-if="!showLecturerScope" class="py-3 px-3 w-10 text-center">
                <input
                  type="checkbox"
                  :checked="isAllSelected"
                  class="rounded text-brand-600 focus:ring-brand-500 cursor-pointer"
                  @change="toggleSelectAll"
                />
              </th>
              <th class="py-3 px-4 w-24 text-center">ANGKATAN</th>
              <th class="py-3 px-4 w-60">NAMA MAHASISWA</th>
              <th class="py-3 px-4">PROGRAM STUDI</th>
              <th class="py-3 px-4 w-44">STATUS MAHASISWA</th>
              <th class="py-3 px-4 w-48">DOSEN WALI</th>
              <th class="py-3 px-4 w-24 text-center">AKSI</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <tr v-if="loading" class="hover:bg-transparent">
              <td :colspan="columnCount" class="py-12 text-center text-slate-400">
                Memuat data distribusi pembimbing akademik...
              </td>
            </tr>
            <tr v-else-if="filteredStudents.length === 0" class="hover:bg-transparent">
              <td :colspan="columnCount" class="py-12 text-center text-slate-400">
                {{ showLecturerScope
                  ? 'Belum ada mahasiswa yang Anda bimbing sebagai Dosen Pembimbing Akademik'
                  : 'Belum ada data distribusi pembimbing' }}
              </td>
            </tr>
            <tr
              v-for="item in paginatedStudents"
              :key="item.id"
              class="hover:bg-slate-50/80 transition-colors"
            >
              <!-- Checkbox -->
              <td v-if="!showLecturerScope" class="py-3.5 px-3 text-center">
                <input
                  type="checkbox"
                  :checked="selectedIds.includes(item.id)"
                  class="rounded text-brand-600 focus:ring-brand-500 cursor-pointer"
                  @change="toggleSelectOne(item.id)"
                />
              </td>

              <!-- Angkatan -->
              <td class="py-3.5 px-4 text-center font-mono text-slate-600 font-medium">
                {{ item.admission_year || 2026 }}
              </td>

              <!-- Nama Mahasiswa & NIM -->
              <td class="py-3.5 px-4">
                <div class="font-bold text-slate-900 uppercase">
                  {{ item.full_name }}
                </div>
                <div class="text-3xs text-slate-500 font-mono mt-0.5">
                  {{ item.student_number }}
                </div>
              </td>

              <!-- Program Studi -->
              <td class="py-3.5 px-4 text-slate-800">
                {{ item.study_program?.degree ? `${item.study_program.degree} - ${item.study_program.name}` : (item.study_program?.name || '--') }}
              </td>

              <!-- Status Mahasiswa -->
              <td class="py-3.5 px-4 text-slate-600">
                {{ formatStatus(item.status) }}
              </td>

              <!-- Dosen Wali -->
              <td class="py-3.5 px-4 text-slate-700 font-medium">
                {{ item.academic_advisor || '--' }}
              </td>

              <!-- Aksi -->
              <td class="py-3.5 px-4">
                <div class="flex items-center justify-center gap-1.5">
                  <button
                    v-if="!showLecturerScope"
                    type="button"
                    class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-md border border-rose-200 transition-colors"
                    title="Edit Dosen Wali"
                    @click="openEditSingle(item)"
                  >
                    <Edit2 class="w-3.5 h-3.5" />
                  </button>
                  <button
                    type="button"
                    class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-md border border-rose-200 transition-colors"
                    title="Lihat Detail"
                    @click="viewDetail(item)"
                  >
                    <Eye class="w-3.5 h-3.5" />
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
        :total="filteredStudents.length"
        :per-page="perPage"
        :from="filteredStudents.length === 0 ? 0 : (currentPage - 1) * perPage + 1"
        :to="Math.min(currentPage * perPage, filteredStudents.length)"
        @page-change="handlePageChange"
      />
    </Card>

    <!-- Slide-over Drawer: Tetapkan Dosen Wali -->
    <div
      v-if="drawerOpen"
      class="fixed inset-0 z-50 flex justify-end bg-slate-900/40 backdrop-blur-xs transition-opacity"
      @click.self="closeDrawer"
    >
      <div class="w-full max-w-md bg-white shadow-2xl h-full flex flex-col animate-slide-left">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
          <div>
            <h3 class="text-sm font-bold text-slate-900">
              {{ isBulk ? 'Tetapkan Dosen Wali Massal' : 'Edit Dosen Wali' }}
            </h3>
            <p class="text-3xs text-slate-500 mt-0.5">
              {{ isBulk ? `${selectedIds.length} mahasiswa terpilih` : `${targetStudent?.full_name} (${targetStudent?.student_number})` }}
            </p>
          </div>
          <button
            type="button"
            class="p-1 rounded-md text-slate-400 hover:text-slate-600 hover:bg-slate-100"
            @click="closeDrawer"
          >
            <X class="w-4 h-4" />
          </button>
        </div>

        <div class="p-5 overflow-y-auto flex-1 space-y-4 text-xs">
          <!-- Dosen Wali -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Dosen Pembimbing Akademik / Wali <span class="text-rose-500">*</span>
            </label>
            <Select
              v-model="drawerForm.lecturer_id"
              :options="lecturerOptions"
              placeholder="Pilih Dosen Wali"
              search-placeholder="Cari nama dosen..."
            />
          </div>

          <!-- Catatan -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Catatan Penugasan
            </label>
            <textarea
              v-model="drawerForm.notes"
              rows="3"
              class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600 outline-none"
              placeholder="Catatan opsional..."
            />
          </div>
        </div>

        <!-- Footer Actions -->
        <div class="p-4 border-t border-slate-100 bg-slate-50/50 flex items-center justify-end gap-2">
          <Button
            variant="outline"
            size="sm"
            class="border-rose-300 text-rose-700 hover:bg-rose-50 flex items-center gap-1.5"
            @click="closeDrawer"
          >
            <X class="w-3.5 h-3.5" />
            <span>Batal</span>
          </Button>
          <Button
            variant="primary"
            size="sm"
            :loading="saving"
            class="bg-brand-700 hover:bg-brand-800 text-white flex items-center gap-1.5 shadow-2xs font-semibold"
            @click="handleSaveDrawer"
          >
            <Check class="w-3.5 h-3.5" />
            <span>Simpan Data</span>
          </Button>
        </div>
      </div>
    </div>
  </PageContainer>
</template>
