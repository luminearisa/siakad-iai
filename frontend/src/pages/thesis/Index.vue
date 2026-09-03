<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  GraduationCap,
  CheckCircle2,
  FileText,
  Clock,
  Info,
  Plus,
  Filter,
  FolderX,
  Edit2,
  Trash2,
} from 'lucide-vue-next'
import { thesisService } from '@/services/api/thesis'
import { academicService } from '@/services/api/academic'
import { useToast } from '@/composables/useToast'
import type { Thesis, ThesisStats } from '@/types/thesis'
import type { StudyProgram } from '@/types/academic'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Badge from '@/components/ui/Badge.vue'
import Pagination from '@/components/data-display/Pagination.vue'

const router = useRouter()
const toast = useToast()

const loading = ref<boolean>(false)
const theses = ref<Thesis[]>([])
const studyPrograms = ref<StudyProgram[]>([])

const stats = ref<ThesisStats>({
  completed: 0,
  active: 0,
  inactive: 0,
  pending_approval: 0,
})

// Search & Filter
const search = ref<string>('')
const filterOpen = ref<boolean>(false)
const selectedStatus = ref<string>('')
const selectedProdiId = ref<string>('')

// Pagination
const perPage = ref<number>(10)
const currentPage = ref<number>(1)
const totalItems = ref<number>(0)
const lastPage = ref<number>(1)

async function loadTheses() {
  loading.value = true
  try {
    const res = await thesisService.list({
      page: currentPage.value,
      per_page: perPage.value,
      search: search.value || undefined,
      status: selectedStatus.value || undefined,
      study_program_id: selectedProdiId.value || undefined,
    })

    theses.value = res.data || []
    if (res.meta) {
      totalItems.value = res.meta.total || 0
      lastPage.value = res.meta.last_page || 1
      if ((res.meta as any).stats) {
        stats.value = (res.meta as any).stats
      }
    }
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal memuat data tugas akhir')
  } finally {
    loading.value = false
  }
}

async function loadProdis() {
  try {
    const res = await academicService.getStudyPrograms()
    studyPrograms.value = res.data || []
  } catch {
    studyPrograms.value = []
  }
}

function handlePageChange(page: number) {
  currentPage.value = page
  loadTheses()
}

function handlePerPageChange() {
  currentPage.value = 1
  loadTheses()
}

function handleSearch() {
  currentPage.value = 1
  loadTheses()
}

function filterByStatus(status: string) {
  selectedStatus.value = selectedStatus.value === status ? '' : status
  currentPage.value = 1
  loadTheses()
}

function navigateToCreate() {
  router.push('/thesis/create')
}

function navigateToEdit(id: number) {
  router.push(`/thesis/${id}/edit`)
}

async function handleDelete(id: number) {
  if (!confirm('Apakah Anda yakin ingin menghapus data tugas akhir ini?')) return

  try {
    await thesisService.delete(id)
    toast.success('Data tugas akhir berhasil dihapus')
    loadTheses()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal menghapus data tugas akhir')
  }
}

function formatStatusBadge(status: string) {
  switch (status) {
    case 'completed':
      return { variant: 'success' as const, label: 'Selesai' }
    case 'active':
      return { variant: 'info' as const, label: 'Aktif' }
    case 'inactive':
      return { variant: 'danger' as const, label: 'Tidak Aktif' }
    case 'pending_approval':
    default:
      return { variant: 'warning' as const, label: 'Butuh Persetujuan' }
  }
}

onMounted(() => {
  loadTheses()
  loadProdis()
})
</script>

<template>
  <PageContainer>
    <!-- Header (Matching Reference Screenshot 1) -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div>
        <nav class="flex items-center gap-1.5 text-xs text-slate-400 mb-1">
          <span class="text-brand-600 font-semibold tracking-wider uppercase">AKADEMIK</span>
        </nav>
        <h1 class="text-xl font-bold text-slate-900">Tugas Akhir</h1>
        <p class="text-xs text-slate-500 mt-0.5">Manajemen Tugas Akhir</p>
      </div>

      <!-- Top Action Button: + Tambah Data -->
      <Button
        variant="primary"
        size="sm"
        class="bg-brand-900 hover:bg-brand-950 text-white flex items-center gap-1.5 shadow-2xs font-semibold"
        @click="navigateToCreate"
      >
        <Plus class="w-3.5 h-3.5" />
        <span>Tambah Data</span>
      </Button>
    </div>

    <!-- 4 Metric Cards (Matching Screenshot 1) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <!-- Card 1: Selesai -->
      <div
        class="p-4 bg-white border border-slate-200/80 rounded-xl shadow-2xs flex items-center justify-between cursor-pointer hover:border-slate-300 transition-all"
        :class="{ 'ring-2 ring-emerald-500': selectedStatus === 'completed' }"
        @click="filterByStatus('completed')"
      >
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600">
            <GraduationCap class="w-5 h-5" />
          </div>
          <div>
            <div class="text-lg font-bold text-slate-900 leading-none">
              {{ stats.completed }}
            </div>
            <div class="text-xs font-medium text-slate-500 mt-1">
              Selesai
            </div>
          </div>
        </div>
        <Info class="w-3.5 h-3.5 text-slate-300 hover:text-slate-500" />
      </div>

      <!-- Card 2: Aktif -->
      <div
        class="p-4 bg-white border border-slate-200/80 rounded-xl shadow-2xs flex items-center justify-between cursor-pointer hover:border-slate-300 transition-all"
        :class="{ 'ring-2 ring-cyan-500': selectedStatus === 'active' }"
        @click="filterByStatus('active')"
      >
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-cyan-50 flex items-center justify-center text-cyan-600">
            <CheckCircle2 class="w-5 h-5" />
          </div>
          <div>
            <div class="text-lg font-bold text-slate-900 leading-none">
              {{ stats.active }}
            </div>
            <div class="text-xs font-medium text-slate-500 mt-1">
              Aktif
            </div>
          </div>
        </div>
        <Info class="w-3.5 h-3.5 text-slate-300 hover:text-slate-500" />
      </div>

      <!-- Card 3: Tidak Aktif -->
      <div
        class="p-4 bg-white border border-slate-200/80 rounded-xl shadow-2xs flex items-center justify-between cursor-pointer hover:border-slate-300 transition-all"
        :class="{ 'ring-2 ring-rose-500': selectedStatus === 'inactive' }"
        @click="filterByStatus('inactive')"
      >
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-rose-50 flex items-center justify-center text-rose-600">
            <FileText class="w-5 h-5" />
          </div>
          <div>
            <div class="text-lg font-bold text-slate-900 leading-none">
              {{ stats.inactive }}
            </div>
            <div class="text-xs font-medium text-slate-500 mt-1">
              Tidak Aktif
            </div>
          </div>
        </div>
        <Info class="w-3.5 h-3.5 text-slate-300 hover:text-slate-500" />
      </div>

      <!-- Card 4: Butuh Persetujuan -->
      <div
        class="p-4 bg-white border border-slate-200/80 rounded-xl shadow-2xs flex items-center justify-between cursor-pointer hover:border-slate-300 transition-all"
        :class="{ 'ring-2 ring-amber-500': selectedStatus === 'pending_approval' }"
        @click="filterByStatus('pending_approval')"
      >
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center text-amber-600">
            <Clock class="w-5 h-5" />
          </div>
          <div>
            <div class="text-lg font-bold text-slate-900 leading-none">
              {{ stats.pending_approval }}
            </div>
            <div class="text-xs font-medium text-slate-500 mt-1">
              Butuh Persetujuan
            </div>
          </div>
        </div>
        <Info class="w-3.5 h-3.5 text-slate-300 hover:text-slate-500" />
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
            :class="filterOpen || selectedStatus || selectedProdiId ? 'border-rose-300 text-rose-700 bg-rose-50' : 'border-slate-300 text-slate-700 bg-white hover:bg-slate-50'"
            @click="filterOpen = !filterOpen"
          >
            <Filter class="w-3.5 h-3.5 text-rose-600" />
            <span>Filter</span>
          </button>

          <div class="relative w-48 sm:w-64">
            <Input
              v-model="search"
              placeholder="Cari data..."
              class="w-full text-xs pr-8"
              @keyup.enter="handleSearch"
            />
          </div>
          <Button
            variant="primary"
            size="sm"
            class="bg-brand-700 hover:bg-brand-800 text-white text-xs px-3"
            @click="handleSearch"
          >
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
          <label class="font-semibold text-slate-700">Program Studi:</label>
          <select
            v-model="selectedProdiId"
            class="px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-brand-500/20 outline-none"
            @change="handleSearch"
          >
            <option value="">Semua Program Studi</option>
            <option v-for="sp in studyPrograms" :key="sp.id" :value="String(sp.id)">
              {{ sp.degree ? `${sp.degree} - ${sp.name}` : sp.name }}
            </option>
          </select>
        </div>

        <div class="flex items-center gap-2">
          <label class="font-semibold text-slate-700">Status TA:</label>
          <select
            v-model="selectedStatus"
            class="px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-brand-500/20 outline-none"
            @change="handleSearch"
          >
            <option value="">Semua Status</option>
            <option value="active">Aktif</option>
            <option value="completed">Selesai</option>
            <option value="inactive">Tidak Aktif</option>
            <option value="pending_approval">Butuh Persetujuan</option>
          </select>
        </div>

        <button
          v-if="selectedProdiId || selectedStatus"
          type="button"
          class="text-xs text-rose-600 hover:underline font-medium"
          @click="selectedProdiId = ''; selectedStatus = ''; handleSearch()"
        >
          Reset Filter
        </button>
      </div>

      <!-- Table / Empty State (Matching Screenshot 1) -->
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-100/80 border-b border-slate-200/80 text-slate-600 font-bold uppercase tracking-wider text-3xs">
              <th class="py-3 px-4 w-52">MAHASISWA</th>
              <th class="py-3 px-4 w-44">PROGRAM STUDI</th>
              <th class="py-3 px-4">JUDUL TUGAS AKHIR</th>
              <th class="py-3 px-4 text-center w-28">NILAI AKHIR</th>
              <th class="py-3 px-4 text-center w-32">STATUS</th>
              <th class="py-3 px-4 text-center w-28">AKSI</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <!-- Loading State -->
            <tr v-if="loading" class="hover:bg-transparent">
              <td colspan="6" class="py-16 text-center text-slate-400">
                Memuat data tugas akhir...
              </td>
            </tr>

            <!-- Empty State (Matching Screenshot 1) -->
            <tr v-else-if="theses.length === 0" class="hover:bg-transparent">
              <td colspan="6" class="py-16 text-center">
                <div class="max-w-sm mx-auto flex flex-col items-center justify-center">
                  <div class="w-16 h-16 rounded-2xl bg-amber-50 flex items-center justify-center text-amber-500 mb-3 shadow-xs">
                    <FolderX class="w-8 h-8 text-amber-500" />
                  </div>
                  <h4 class="text-sm font-bold text-slate-900 mb-1">
                    Tidak ada data yang tersedia
                  </h4>
                  <p class="text-xs text-slate-400">
                    Belum ada informasi yang dapat untuk ditampilkan saat ini
                  </p>
                </div>
              </td>
            </tr>

            <!-- Data Rows -->
            <tr
              v-for="item in theses"
              :key="item.id"
              class="hover:bg-slate-50/80 transition-colors"
            >
              <!-- Mahasiswa -->
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
                {{ item.study_program?.degree ? `${item.study_program.degree} - ${item.study_program.name}` : (item.study_program?.name || '-') }}
              </td>

              <!-- Judul Tugas Akhir -->
              <td class="py-3.5 px-4">
                <div class="font-medium text-slate-900 line-clamp-2">
                  {{ item.title_id }}
                </div>
                <div v-if="item.title_en" class="text-3xs text-slate-400 italic mt-0.5 line-clamp-1">
                  {{ item.title_en }}
                </div>
              </td>

              <!-- Nilai Akhir -->
              <td class="py-3.5 px-4 text-center font-bold text-slate-800">
                <span v-if="item.final_grade_letter" class="px-2 py-0.5 bg-emerald-50 text-emerald-700 rounded font-mono text-xs">
                  {{ item.final_grade_letter }} ({{ item.final_grade }})
                </span>
                <span v-else class="text-slate-400">-</span>
              </td>

              <!-- Status -->
              <td class="py-3.5 px-4 text-center">
                <Badge :variant="formatStatusBadge(item.status).variant" size="sm">
                  {{ formatStatusBadge(item.status).label }}
                </Badge>
              </td>

              <!-- Aksi -->
              <td class="py-3.5 px-4">
                <div class="flex items-center justify-center gap-1.5">
                  <button
                    type="button"
                    class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-md border border-rose-200 transition-colors"
                    title="Edit Tugas Akhir"
                    @click="navigateToEdit(item.id)"
                  >
                    <Edit2 class="w-3.5 h-3.5" />
                  </button>
                  <button
                    type="button"
                    class="p-1.5 text-slate-500 hover:bg-slate-100 rounded-md border border-slate-200 transition-colors"
                    title="Hapus Tugas Akhir"
                    @click="handleDelete(item.id)"
                  >
                    <Trash2 class="w-3.5 h-3.5" />
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
        :last-page="lastPage"
        :total="totalItems"
        :per-page="perPage"
        :from="totalItems === 0 ? 0 : (currentPage - 1) * perPage + 1"
        :to="Math.min(currentPage * perPage, totalItems)"
        @page-change="handlePageChange"
      />
    </Card>
  </PageContainer>
</template>
