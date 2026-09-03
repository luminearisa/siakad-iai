<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  RefreshCw,
  ChevronDown,
  Filter,
  Eye,
  Edit2,
  X,
  Check,
} from 'lucide-vue-next'
import { enrollmentService } from '@/services/api/enrollments'
import { lecturerService } from '@/services/api/lecturers'
import { useToast } from '@/composables/useToast'
import { useAuth } from '@/composables/useAuth'
import type { StudentEnrollment } from '@/types/enrollment'
import type { Lecturer } from '@/types/lecturer'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Pagination from '@/components/data-display/Pagination.vue'

const router = useRouter()
const toast = useToast()
const { isStudent } = useAuth()

const loading = ref<boolean>(false)
const generating = ref<boolean>(false)
const enrollments = ref<StudentEnrollment[]>([])
const lecturers = ref<Lecturer[]>([])
const search = ref<string>('')
const perPage = ref<number>(10)
const currentPage = ref<number>(1)
const selectedIds = ref<number[]>([])

// Filter Popover
const filterOpen = ref<boolean>(false)
const filterAngkatan = ref<string>('')
const filterProdi = ref<string>('')
const filterStatusKrs = ref<string>('')

// Dropdown Lainnya
const moreMenuOpen = ref<boolean>(false)

// Drawer Edit Perwalian
const drawerOpen = ref<boolean>(false)
const editingEnrollment = ref<StudentEnrollment | null>(null)
const saving = ref<boolean>(false)

const drawerForm = reactive({
  lecturer_id: null as number | null,
  max_credits: 24,
})

const filteredEnrollments = computed(() => {
  let list = enrollments.value

  if (filterAngkatan.value) {
    list = list.filter((e) => String(e.student?.admission_year) === filterAngkatan.value)
  }
  if (filterProdi.value) {
    list = list.filter((e) => String(e.student?.study_program_id) === filterProdi.value)
  }
  if (filterStatusKrs.value) {
    list = list.filter((e) => e.status === filterStatusKrs.value)
  }

  if (!search.value) return list
  const q = search.value.toLowerCase()
  return list.filter(
    (e) =>
      e.student?.full_name?.toLowerCase().includes(q) ||
      e.student?.student_number?.toLowerCase().includes(q) ||
      e.student?.study_program?.name?.toLowerCase().includes(q) ||
      (e.academic_advisor && e.academic_advisor.toLowerCase().includes(q))
  )
})

const totalPages = computed(() => {
  return Math.max(1, Math.ceil(filteredEnrollments.value.length / perPage.value))
})

const paginatedEnrollments = computed(() => {
  const start = (currentPage.value - 1) * perPage.value
  return filteredEnrollments.value.slice(start, start + perPage.value)
})

const isAllSelected = computed(() => {
  const current = paginatedEnrollments.value
  return current.length > 0 && current.every((e) => selectedIds.value.includes(e.id))
})

function toggleSelectAll() {
  const current = paginatedEnrollments.value
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

async function loadEnrollments() {
  loading.value = true
  try {
    const res = await enrollmentService.list({ per_page: 50 })
    enrollments.value = res.data || []
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal memuat data monitoring perwalian')
  } finally {
    loading.value = false
  }
}

async function loadLecturers() {
  try {
    const res = await lecturerService.list({ per_page: 100 })
    lecturers.value = res.data || []
  } catch {
    lecturers.value = []
  }
}

async function handleGenerate() {
  if (!confirm('Generate data monitoring perwalian untuk seluruh mahasiswa aktif pada semester ini?')) return

  generating.value = true
  try {
    const res = await enrollmentService.generate()
    toast.success(res.message || 'Generate monitoring perwalian berhasil!')
    await loadEnrollments()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal generate monitoring perwalian')
  } finally {
    generating.value = false
  }
}

function openEditDrawer(item: StudentEnrollment) {
  if (isStudent.value) return
  editingEnrollment.value = item
  drawerForm.lecturer_id = item.academic_advisor_id || null
  drawerForm.max_credits = item.max_credits || 24
  drawerOpen.value = true
}

function closeDrawer() {
  drawerOpen.value = false
}

async function handleSaveDrawer() {
  if (!editingEnrollment.value) return

  saving.value = true
  try {
    await enrollmentService.updateAdvisorAndQuota(editingEnrollment.value.id, {
      lecturer_id: drawerForm.lecturer_id,
      max_credits: drawerForm.max_credits,
    })
    toast.success(`Data perwalian untuk ${editingEnrollment.value.student?.full_name} berhasil disimpan`)
    drawerOpen.value = false
    loadEnrollments()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal menyimpan data perwalian')
  } finally {
    saving.value = false
  }
}

function viewDetail(item: StudentEnrollment) {
  router.push(`/enrollments/${item.id}`)
}

onMounted(() => {
  loadEnrollments()
  loadLecturers()
})
</script>

<template>
  <PageContainer>
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div>
        <nav class="flex items-center gap-1.5 text-xs text-slate-400 mb-1">
          <span class="text-brand-600 font-medium">{{ isStudent ? 'Portal Mahasiswa' : 'Akademik' }}</span>
        </nav>
        <h1 class="text-xl font-bold text-slate-900">{{ isStudent ? 'Rencana Studi (KRS)' : 'Monitoring Perwalian' }}</h1>
        <p class="text-xs text-slate-500 mt-0.5">
          {{ isStudent ? 'Daftar Rencana Studi dan Pengambilan Kartu Rencana Studi (KRS) Mahasiswa' : 'Manajemen Perwalian dan Monitoring KRS Mahasiswa' }}
        </p>
      </div>

      <!-- Action Buttons (Only for Admin & Staff) -->
      <div v-if="!isStudent" class="flex items-center gap-2 self-start sm:self-auto">
        <!-- Lainnya Dropdown -->
        <div class="relative">
          <button
            type="button"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-rose-300 text-rose-700 bg-rose-50/50 hover:bg-rose-50 rounded-lg text-xs font-semibold shadow-2xs transition-colors"
            @click="moreMenuOpen = !moreMenuOpen"
          >
            <span>Lainnya</span>
            <ChevronDown class="w-3.5 h-3.5" />
          </button>

          <div
            v-if="moreMenuOpen"
            class="absolute right-0 mt-1 w-48 bg-white border border-slate-200 rounded-lg shadow-lg py-1 z-30 text-xs text-slate-700 animate-in fade-in zoom-in-95"
            @click="moreMenuOpen = false"
          >
            <button class="w-full text-left px-4 py-2 hover:bg-slate-50 flex items-center gap-2">
              <span>Export Data Excel</span>
            </button>
            <button class="w-full text-left px-4 py-2 hover:bg-slate-50 flex items-center gap-2">
              <span>Cetak Rekap Perwalian</span>
            </button>
            <button class="w-full text-left px-4 py-2 hover:bg-slate-50 flex items-center gap-2">
              <span>Tetapkan Dosen Wali Massal</span>
            </button>
          </div>
        </div>

        <!-- Generate Button -->
        <Button
          variant="primary"
          size="sm"
          :loading="generating"
          class="bg-brand-700 hover:bg-brand-800 text-white flex items-center gap-1.5 shadow-2xs font-semibold"
          @click="handleGenerate"
        >
          <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': generating }" />
          <span>Generate</span>
        </Button>
      </div>
    </div>

    <!-- Main Card -->
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
            :class="filterOpen || filterAngkatan || filterProdi ? 'border-rose-300 text-rose-700 bg-rose-50' : 'border-slate-300 text-slate-700 bg-white hover:bg-slate-50'"
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
            <option value="2025">2025</option>
            <option value="2026">2026</option>
            <option value="2027">2027</option>
          </select>
        </div>

        <div class="flex items-center gap-2">
          <label class="font-semibold text-slate-700">Status KRS:</label>
          <select
            v-model="filterStatusKrs"
            class="px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-brand-500/20 outline-none"
          >
            <option value="">Semua Status</option>
            <option value="draft">Belum KRS / Draft</option>
            <option value="submitted">Menunggu Persetujuan</option>
            <option value="approved">Disetujui</option>
          </select>
        </div>

        <button
          v-if="filterAngkatan || filterProdi || filterStatusKrs"
          type="button"
          class="text-xs text-rose-600 hover:underline font-medium"
          @click="filterAngkatan = ''; filterProdi = ''; filterStatusKrs = ''"
        >
          Reset Filter
        </button>
      </div>

      <!-- Table (Matching Screenshot 1) -->
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-100/80 border-b border-slate-200/80 text-slate-600 font-bold uppercase tracking-wider text-3xs">
              <th v-if="!isStudent" class="py-3 px-3 w-10 text-center">
                <input
                  type="checkbox"
                  :checked="isAllSelected"
                  class="rounded text-brand-600 focus:ring-brand-500 cursor-pointer"
                  @change="toggleSelectAll"
                />
              </th>
              <th class="py-3 px-3 w-20 text-center">ANGKATAN</th>
              <th class="py-3 px-4 w-52">NAMA MAHASISWA</th>
              <th class="py-3 px-4">PROGRAM STUDI</th>
              <th class="py-3 px-3 text-center w-24">STATUS</th>
              <th class="py-3 px-3 text-center w-20">SEMESTER</th>
              <th class="py-3 px-3 text-center w-16">IPS</th>
              <th class="py-3 px-3 text-center w-20">SKS</th>
              <th class="py-3 px-3 text-center w-28">STATUS KRS</th>
              <th class="py-3 px-4 w-36">DOSEN WALI</th>
              <th class="py-3 px-3 w-24 text-center">AKSI</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <tr v-if="loading" class="hover:bg-transparent">
              <td :colspan="isStudent ? 10 : 11" class="py-12 text-center text-slate-400">
                Memuat data {{ isStudent ? 'Rencana Studi (KRS)' : 'monitoring perwalian' }}...
              </td>
            </tr>
            <tr v-else-if="filteredEnrollments.length === 0" class="hover:bg-transparent">
              <td :colspan="isStudent ? 10 : 11" class="py-12 text-center text-slate-400">
                Belum ada data {{ isStudent ? 'Rencana Studi' : 'monitoring perwalian' }}
              </td>
            </tr>
            <tr
              v-for="item in paginatedEnrollments"
              :key="item.id"
              class="hover:bg-slate-50/80 transition-colors"
            >
              <!-- Checkbox (Only for Admin) -->
              <td v-if="!isStudent" class="py-3.5 px-3 text-center">
                <input
                  type="checkbox"
                  :checked="selectedIds.includes(item.id)"
                  class="rounded text-brand-600 focus:ring-brand-500 cursor-pointer"
                  @change="toggleSelectOne(item.id)"
                />
              </td>

              <!-- Angkatan -->
              <td class="py-3.5 px-3 text-center font-mono text-slate-600 font-medium">
                {{ item.student?.admission_year || 2025 }}
              </td>

              <!-- Nama Mahasiswa & NIM -->
              <td class="py-3.5 px-4">
                <div class="font-bold text-slate-900">
                  {{ item.student?.full_name }}
                </div>
                <div class="text-3xs text-slate-500 font-mono mt-0.5">
                  {{ item.student?.student_number }}
                </div>
              </td>

              <!-- Program Studi -->
              <td class="py-3.5 px-4 text-slate-800">
                {{ item.student?.study_program?.degree ? `${item.student.study_program.degree} - ${item.student.study_program.name}` : (item.student?.study_program?.name || '--') }}
              </td>

              <!-- Status Mahasiswa -->
              <td class="py-3.5 px-3 text-center text-slate-600">
                {{ item.student?.status === 'active' ? 'Aktif' : 'Non Aktif' }}
              </td>

              <!-- Semester -->
              <td class="py-3.5 px-3 text-center font-mono text-slate-800 font-semibold">
                {{ item.student?.admission_year === 2026 ? 1 : 2 }}
              </td>

              <!-- IPS -->
              <td class="py-3.5 px-3 text-center font-mono text-slate-700">
                0,00
              </td>

              <!-- SKS (diambil/batas) -->
              <td class="py-3.5 px-3 text-center font-mono font-medium text-slate-800">
                {{ item.total_credits || 0 }}/{{ item.max_credits || 24 }}
              </td>

              <!-- Status KRS Badge -->
              <td class="py-3.5 px-3 text-center">
                <span
                  class="inline-flex items-center justify-center px-2.5 py-1 rounded text-3xs font-bold text-white shadow-2xs"
                  :class="{
                    'bg-slate-500': item.status === 'draft' || !item.status,
                    'bg-amber-600': item.status === 'submitted',
                    'bg-emerald-600': item.status === 'approved',
                    'bg-rose-600': item.status === 'rejected',
                  }"
                >
                  {{ item.status === 'approved' ? 'Disetujui' : item.status === 'submitted' ? 'Menunggu Persetujuan' : 'Belum KRS' }}
                </span>
              </td>

              <!-- Dosen Wali -->
              <td class="py-3.5 px-4 text-slate-700 font-medium">
                {{ item.academic_advisor || '--' }}
              </td>

              <!-- Aksi -->
              <td class="py-3.5 px-3">
                <!-- For Student -->
                <div v-if="isStudent" class="flex items-center justify-center">
                  <Button
                    variant="primary"
                    size="sm"
                    class="bg-brand-700 hover:bg-brand-800 text-white font-medium text-2xs h-7 px-2.5 gap-1 shadow-2xs"
                    @click="viewDetail(item)"
                  >
                    <Eye class="w-3.5 h-3.5" />
                    <span>Lihat / Isi KRS</span>
                  </Button>
                </div>

                <!-- For Admin / Staff -->
                <div v-else class="flex items-center justify-center gap-1.5">
                  <button
                    type="button"
                    class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-md border border-rose-200 transition-colors"
                    title="Lihat Detail KRS"
                    @click="viewDetail(item)"
                  >
                    <Eye class="w-3.5 h-3.5" />
                  </button>
                  <button
                    type="button"
                    class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-md border border-rose-200 transition-colors"
                    title="Edit Monitoring Perwalian"
                    @click="openEditDrawer(item)"
                  >
                    <Edit2 class="w-3.5 h-3.5" />
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
        :total="filteredEnrollments.length"
        :per-page="perPage"
        :from="filteredEnrollments.length === 0 ? 0 : (currentPage - 1) * perPage + 1"
        :to="Math.min(currentPage * perPage, filteredEnrollments.length)"
        @page-change="handlePageChange"
      />
    </Card>

    <!-- Slide-over Drawer: Edit Monitoring Perwalian (Matching Screenshot 3) -->
    <div
      v-if="drawerOpen"
      class="fixed inset-0 z-50 flex justify-end bg-slate-900/40 backdrop-blur-xs transition-opacity"
      @click.self="closeDrawer"
    >
      <div class="w-full max-w-md bg-white shadow-2xl h-full flex flex-col animate-slide-left">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
          <div>
            <h3 class="text-sm font-bold text-slate-900">
              Edit Monitoring Perwalian
            </h3>
            <p class="text-3xs text-slate-500 mt-0.5">{{ editingEnrollment?.student?.full_name }} ({{ editingEnrollment?.student?.student_number }})</p>
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
              Dosen Wali <span class="text-rose-500">*</span>
            </label>
            <select
              v-model="drawerForm.lecturer_id"
              class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600 outline-none"
            >
              <option :value="null">Pilih Dosen Wali</option>
              <option v-for="l in lecturers" :key="l.id" :value="l.id">
                {{ l.full_name }}
              </option>
            </select>
          </div>

          <!-- Batas SKS -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Batas SKS <span class="text-rose-500">*</span>
            </label>
            <Input
              v-model.number="drawerForm.max_credits"
              type="number"
              min="1"
              max="36"
              placeholder="12"
              required
            />
          </div>
        </div>

        <!-- Footer Actions (Matching Screenshot 3) -->
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
            class="bg-brand-700 hover:bg-brand-800 text-white flex items-center gap-1.5 shadow-2xs"
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
