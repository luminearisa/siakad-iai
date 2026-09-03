<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Settings, Eye, Filter, X, Check } from 'lucide-vue-next'
import { lecturerService } from '@/services/api/lecturers'
import { academicService } from '@/services/api/academic'
import { useToast } from '@/composables/useToast'
import type { Lecturer } from '@/types/lecturer'
import type { StudyProgram } from '@/types/academic'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Pagination from '@/components/data-display/Pagination.vue'

const router = useRouter()
const toast = useToast()

const loading = ref<boolean>(false)
const saving = ref<boolean>(false)
const lecturers = ref<Lecturer[]>([])
const studyPrograms = ref<StudyProgram[]>([])

const search = ref<string>('')
const perPage = ref<number>(10)
const currentPage = ref<number>(1)

// Filter Popover
const filterOpen = ref<boolean>(false)
const filterProdi = ref<string>('')
const filterJabatan = ref<string>('')

// Drawer Edit Kuota
const drawerOpen = ref<boolean>(false)
const editingLecturer = ref<Lecturer | null>(null)

const form = reactive({
  academic_advising_quota: 0,
  thesis_supervisor_quota: 0,
  thesis_examiner_quota: 0,
})

const filteredLecturers = computed(() => {
  let list = lecturers.value

  if (filterProdi.value) {
    list = list.filter((l) => String(l.homebase_study_program_id) === filterProdi.value)
  }
  if (filterJabatan.value) {
    list = list.filter((l) => l.functional_position === filterJabatan.value)
  }

  if (!search.value) return list
  const q = search.value.toLowerCase()
  return list.filter(
    (l) =>
      l.full_name?.toLowerCase().includes(q) ||
      l.nidn?.toLowerCase().includes(q) ||
      l.homebase_study_program?.name?.toLowerCase().includes(q) ||
      l.functional_position?.toLowerCase().includes(q)
  )
})

const totalPages = computed(() => {
  return Math.max(1, Math.ceil(filteredLecturers.value.length / perPage.value))
})

const paginatedLecturers = computed(() => {
  const start = (currentPage.value - 1) * perPage.value
  return filteredLecturers.value.slice(start, start + perPage.value)
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
    const [lecRes, prodiRes] = await Promise.all([
      lecturerService.list({ per_page: 200 }),
      academicService.getStudyPrograms(),
    ])
    lecturers.value = lecRes.data || []
    studyPrograms.value = prodiRes.data || []
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal memuat data kuota pembimbing')
  } finally {
    loading.value = false
  }
}

function openEditDrawer(lec: Lecturer) {
  editingLecturer.value = lec
  form.academic_advising_quota = lec.academic_advising_quota ?? 0
  form.thesis_supervisor_quota = lec.thesis_supervisor_quota ?? 0
  form.thesis_examiner_quota = lec.thesis_examiner_quota ?? 0
  drawerOpen.value = true
}

function closeDrawer() {
  drawerOpen.value = false
}

async function handleSaveDrawer() {
  if (!editingLecturer.value) return

  saving.value = true
  try {
    await lecturerService.updateQuotas(editingLecturer.value.id, {
      academic_advising_quota: Number(form.academic_advising_quota),
      thesis_supervisor_quota: Number(form.thesis_supervisor_quota),
      thesis_examiner_quota: Number(form.thesis_examiner_quota),
    })
    toast.success(`Kuota pembimbing untuk ${editingLecturer.value.full_name} berhasil disimpan`)
    drawerOpen.value = false
    await loadData()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal menyimpan kuota pembimbing')
  } finally {
    saving.value = false
  }
}

function viewDetail(lec: Lecturer) {
  router.push(`/lecturers/${lec.id}`)
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
        <h1 class="text-xl font-bold text-slate-900">Kuota Pembimbing</h1>
        <p class="text-xs text-slate-500 mt-0.5">Manajemen Kuota Pembimbing</p>
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
          <!-- Filter Button -->
          <button
            type="button"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 border rounded-lg text-xs font-semibold transition-colors"
            :class="filterOpen || filterProdi || filterJabatan ? 'border-rose-300 text-rose-700 bg-rose-50' : 'border-slate-300 text-slate-700 bg-white hover:bg-slate-50'"
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
          <label class="font-semibold text-slate-700">Program Studi / Unit Kerja:</label>
          <select
            v-model="filterProdi"
            class="px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-brand-500/20 outline-none"
          >
            <option value="">Semua Unit Kerja</option>
            <option v-for="sp in studyPrograms" :key="sp.id" :value="String(sp.id)">
              {{ sp.degree ? `${sp.degree} - ${sp.name}` : sp.name }}
            </option>
          </select>
        </div>

        <div class="flex items-center gap-2">
          <label class="font-semibold text-slate-700">Jabatan Fungsional:</label>
          <select
            v-model="filterJabatan"
            class="px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-brand-500/20 outline-none"
          >
            <option value="">Semua Jabatan</option>
            <option value="Tenaga Pendidik">Tenaga Pendidik</option>
            <option value="Asisten Ahli">Asisten Ahli</option>
            <option value="Lektor">Lektor</option>
            <option value="Lektor Kepala">Lektor Kepala</option>
            <option value="Guru Besar">Guru Besar</option>
          </select>
        </div>

        <button
          v-if="filterProdi || filterJabatan"
          type="button"
          class="text-xs text-rose-600 hover:underline font-medium"
          @click="filterProdi = ''; filterJabatan = ''"
        >
          Reset Filter
        </button>
      </div>

      <!-- Table (Matching Reference Screenshot) -->
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-100/80 border-b border-slate-200/80 text-slate-600 font-bold uppercase tracking-wider text-3xs">
              <th class="py-3 px-4 w-12 text-center">NO</th>
              <th class="py-3 px-4 w-52">NAMA DOSEN</th>
              <th class="py-3 px-4 w-44">JABATAN FUNGSIONAL</th>
              <th class="py-3 px-4">UNIT KERJA</th>
              <th class="py-3 px-4 text-center w-36">KUOTA PEMBIMBING AKADEMIK</th>
              <th class="py-3 px-4 text-center w-32">KUOTA PEMBIMBING TA</th>
              <th class="py-3 px-4 text-center w-32">KUOTA PENGUJI TA</th>
              <th class="py-3 px-4 w-24 text-center">AKSI</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <tr v-if="loading" class="hover:bg-transparent">
              <td colspan="8" class="py-12 text-center text-slate-400">
                Memuat data kuota pembimbing...
              </td>
            </tr>
            <tr v-else-if="filteredLecturers.length === 0" class="hover:bg-transparent">
              <td colspan="8" class="py-12 text-center text-slate-400">
                Belum ada data kuota pembimbing
              </td>
            </tr>
            <tr
              v-for="(lec, idx) in paginatedLecturers"
              :key="lec.id"
              class="hover:bg-slate-50/80 transition-colors"
            >
              <!-- NO -->
              <td class="py-3.5 px-4 text-center font-medium text-slate-500">
                {{ (currentPage - 1) * perPage + idx + 1 }}
              </td>

              <!-- Nama Dosen & NIDN -->
              <td class="py-3.5 px-4">
                <div class="font-bold text-slate-900">
                  {{ lec.full_name }}
                </div>
                <div class="text-3xs text-slate-500 font-mono mt-0.5">
                  {{ lec.nidn || '-' }}
                </div>
              </td>

              <!-- Jabatan Fungsional -->
              <td class="py-3.5 px-4 text-slate-700 font-medium">
                {{ lec.functional_position || '--' }}
              </td>

              <!-- Unit Kerja -->
              <td class="py-3.5 px-4 text-slate-800">
                {{ lec.homebase_study_program?.degree ? `${lec.homebase_study_program.degree} - ${lec.homebase_study_program.name}` : (lec.homebase_study_program?.name || 'Universitas Demo') }}
              </td>

              <!-- Kuota Pembimbing Akademik (Terisi/Kuota) -->
              <td class="py-3.5 px-4 text-center font-mono font-medium text-slate-800">
                {{ lec.active_advising_count || 0 }}/{{ lec.academic_advising_quota || 0 }}
              </td>

              <!-- Kuota Pembimbing TA -->
              <td class="py-3.5 px-4 text-center font-mono font-medium text-slate-800">
                0/{{ lec.thesis_supervisor_quota || 0 }}
              </td>

              <!-- Kuota Penguji TA -->
              <td class="py-3.5 px-4 text-center font-mono font-medium text-slate-800">
                0/{{ lec.thesis_examiner_quota || 0 }}
              </td>

              <!-- Aksi -->
              <td class="py-3.5 px-4">
                <div class="flex items-center justify-center gap-1.5">
                  <button
                    type="button"
                    class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-md border border-rose-200 transition-colors"
                    title="Edit Kuota Pembimbing"
                    @click="openEditDrawer(lec)"
                  >
                    <Settings class="w-3.5 h-3.5" />
                  </button>
                  <button
                    type="button"
                    class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-md border border-rose-200 transition-colors"
                    title="Lihat Detail Dosen"
                    @click="viewDetail(lec)"
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
        :total="filteredLecturers.length"
        :per-page="perPage"
        :from="filteredLecturers.length === 0 ? 0 : (currentPage - 1) * perPage + 1"
        :to="Math.min(currentPage * perPage, filteredLecturers.length)"
        @page-change="handlePageChange"
      />
    </Card>

    <!-- Slide-over Drawer: Edit Kuota Pembimbing -->
    <div
      v-if="drawerOpen"
      class="fixed inset-0 z-50 flex justify-end bg-slate-900/40 backdrop-blur-xs transition-opacity"
      @click.self="closeDrawer"
    >
      <div class="w-full max-w-md bg-white shadow-2xl h-full flex flex-col animate-slide-left">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
          <div>
            <h3 class="text-sm font-bold text-slate-900">
              Edit Kuota Pembimbing
            </h3>
            <p class="text-3xs text-slate-500 mt-0.5">
              {{ editingLecturer?.full_name }} (NIDN: {{ editingLecturer?.nidn || '-' }})
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
          <!-- Kuota Pembimbing Akademik -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Kuota Pembimbing Akademik (Dosen Wali) <span class="text-rose-500">*</span>
            </label>
            <Input
              v-model.number="form.academic_advising_quota"
              type="number"
              min="0"
              max="100"
              placeholder="0"
              required
            />
          </div>

          <!-- Kuota Pembimbing Tugas Akhir -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Kuota Pembimbing Tugas Akhir (TA) <span class="text-rose-500">*</span>
            </label>
            <Input
              v-model.number="form.thesis_supervisor_quota"
              type="number"
              min="0"
              max="100"
              placeholder="0"
              required
            />
          </div>

          <!-- Kuota Penguji Tugas Akhir -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Kuota Penguji Tugas Akhir (TA) <span class="text-rose-500">*</span>
            </label>
            <Input
              v-model.number="form.thesis_examiner_quota"
              type="number"
              min="0"
              max="100"
              placeholder="0"
              required
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
