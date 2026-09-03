<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Plus, Eye, Edit3, RefreshCw, Trash2 } from 'lucide-vue-next'
import { lecturerService } from '@/services/api/lecturers'
import { usePermissions } from '@/composables/usePermissions'
import { useToast } from '@/composables/useToast'
import type { Lecturer, LecturerFilters as LecturerFiltersType } from '@/types/lecturer'
import type { ApiMeta } from '@/types/api'
import PageContainer from '@/components/data-display/PageContainer.vue'
import PageHeader from '@/components/data-display/PageHeader.vue'
import DataTable, { type Column } from '@/components/data-display/DataTable.vue'
import Pagination from '@/components/data-display/Pagination.vue'
import Button from '@/components/ui/Button.vue'
import ConfirmModal from '@/components/feedback/ConfirmModal.vue'
import LecturerFilters from './components/LecturerFilters.vue'
import LecturerStatusBadge from './components/LecturerStatusBadge.vue'
import ChangeLecturerStatusModal from './components/ChangeLecturerStatusModal.vue'

const { can } = usePermissions()
const toast = useToast()

const lecturers = ref<Lecturer[]>([])
const loading = ref<boolean>(false)
const meta = ref<ApiMeta>({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
  from: 0,
  to: 0,
})

const filters = ref<LecturerFiltersType>({
  search: '',
  status: '',
  homebase_study_program_id: '',
  gender: '',
  functional_position: '',
  sort: 'id',
  direction: 'desc',
  page: 1,
  per_page: 15,
})

// Modal states
const statusModalOpen = ref<boolean>(false)
const selectedLecturer = ref<Lecturer | null>(null)

const deleteModalOpen = ref<boolean>(false)
const lecturerToDelete = ref<Lecturer | null>(null)
const deleteLoading = ref<boolean>(false)

const columns: Column<Lecturer>[] = [
  { key: 'nidn', label: 'NIDN / NIP', width: '140px', sortable: true },
  { key: 'full_name', label: 'Nama Lengkap Dosen', sortable: true },
  { key: 'homebase_study_program', label: 'Homebase Prodi' },
  { key: 'functional_position', label: 'Jabatan Fungsional', width: '150px' },
  { key: 'gender', label: 'L/P', align: 'center', width: '70px' },
  { key: 'status', label: 'Status', align: 'center', width: '130px', sortable: true },
]

async function loadLecturers() {
  loading.value = true
  try {
    const res = await lecturerService.list(filters.value)
    lecturers.value = res.data || []
    if (res.meta) {
      meta.value = res.meta
    }
  } catch {
    lecturers.value = []
  } finally {
    loading.value = false
  }
}

function handleSort(columnKey: string) {
  if (filters.value.sort === columnKey) {
    filters.value.direction = filters.value.direction === 'asc' ? 'desc' : 'asc'
  } else {
    filters.value.sort = columnKey
    filters.value.direction = 'asc'
  }
  loadLecturers()
}

function handlePageChange(newPage: number) {
  filters.value.page = newPage
  loadLecturers()
}

function openChangeStatus(lec: Lecturer) {
  selectedLecturer.value = lec
  statusModalOpen.value = true
}

function handleStatusSuccess(updated: Lecturer) {
  const idx = lecturers.value.findIndex(l => l.id === updated.id)
  if (idx !== -1) {
    lecturers.value[idx] = updated
  } else {
    loadLecturers()
  }
}

function confirmDelete(lec: Lecturer) {
  lecturerToDelete.value = lec
  deleteModalOpen.value = true
}

async function handleDelete() {
  if (!lecturerToDelete.value) return
  deleteLoading.value = true
  try {
    await lecturerService.delete(lecturerToDelete.value.id)
    toast.success(`Data dosen ${lecturerToDelete.value.full_name} berhasil dihapus.`)
    deleteModalOpen.value = false
    lecturerToDelete.value = null
    loadLecturers()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menghapus data dosen.')
  } finally {
    deleteLoading.value = false
  }
}

onMounted(() => {
  loadLecturers()
})
</script>

<template>
  <PageContainer>
    <PageHeader
      title="Data Dosen & Tenaga Pengajar"
      subtitle="Direktori master dosen pengajar, kepakaran akademik, dan homebase program studi"
      :breadcrumbs="[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Dosen' }]"
    >
      <template #actions>
        <router-link v-if="can('lecturers.create')" to="/lecturers/create">
          <Button variant="primary" size="sm">
            <Plus class="w-3.5 h-3.5" />
            <span>Tambah Dosen</span>
          </Button>
        </router-link>
      </template>
    </PageHeader>

    <div class="space-y-4">
      <!-- Search & Filters -->
      <LecturerFilters v-model="filters" @change="loadLecturers" />

      <!-- Data Table -->
      <DataTable
        :columns="columns"
        :rows="lecturers"
        :loading="loading"
        :sort-by="filters.sort"
        :sort-direction="filters.direction"
        empty-title="Tidak ada data dosen"
        empty-description="Belum ada data dosen yang cocok dengan kriteria pencarian / filter."
        @sort="handleSort"
      >
        <template #emptyAction>
          <router-link v-if="can('lecturers.create')" to="/lecturers/create">
            <Button variant="primary" size="sm">
              <Plus class="w-3.5 h-3.5" />
              <span>Tambah Dosen Baru</span>
            </Button>
          </router-link>
        </template>

        <!-- Custom Cell: NIDN / NIP -->
        <template #cell-nidn="{ row }">
          <router-link
            :to="`/lecturers/${row.id}`"
            class="font-mono font-semibold text-brand-900 hover:text-brand-700 transition-colors"
          >
            {{ row.nidn || row.nip || row.lecturer_number || '-' }}
          </router-link>
        </template>

        <!-- Custom Cell: Nama Lengkap -->
        <template #cell-full_name="{ row }">
          <router-link :to="`/lecturers/${row.id}`" class="group block">
            <span class="font-medium text-slate-900 group-hover:text-brand-900 transition-colors">
              {{ row.full_name }}<span v-if="row.academic_degree">, {{ row.academic_degree }}</span>
            </span>
            <span v-if="row.email" class="block text-2xs text-slate-400">
              {{ row.email }}
            </span>
          </router-link>
        </template>

        <!-- Custom Cell: Homebase -->
        <template #cell-homebase_study_program="{ row }">
          <span class="font-medium text-slate-800">{{ row.homebase_study_program?.name || '-' }}</span>
          <span v-if="row.homebase_study_program?.faculty" class="text-2xs text-slate-400 block font-normal">
            {{ row.homebase_study_program.faculty.name }}
          </span>
        </template>

        <!-- Custom Cell: Gender -->
        <template #cell-gender="{ value }">
          <span :class="value === 'male' ? 'text-blue-600 font-semibold' : 'text-rose-600 font-semibold'">
            {{ value === 'male' ? 'L' : 'P' }}
          </span>
        </template>

        <!-- Custom Cell: Status -->
        <template #cell-status="{ value }">
          <LecturerStatusBadge :status="value" size="xs" />
        </template>

        <!-- Actions Column -->
        <template #actions="{ row }">
          <div class="flex items-center justify-end gap-1">
            <router-link :to="`/lecturers/${row.id}`">
              <button
                type="button"
                class="p-1 rounded-xs text-slate-400 hover:text-brand-800 hover:bg-slate-100 transition-colors cursor-pointer"
                title="Lihat Detail Profil Dosen"
              >
                <Eye class="w-3.5 h-3.5" />
              </button>
            </router-link>

            <router-link v-if="can('lecturers.update')" :to="`/lecturers/${row.id}/edit`">
              <button
                type="button"
                class="p-1 rounded-xs text-slate-400 hover:text-brand-800 hover:bg-slate-100 transition-colors cursor-pointer"
                title="Edit Data Dosen"
              >
                <Edit3 class="w-3.5 h-3.5" />
              </button>
            </router-link>

            <button
              v-if="can('lecturers.change_status')"
              type="button"
              class="p-1 rounded-xs text-slate-400 hover:text-amber-600 hover:bg-slate-100 transition-colors cursor-pointer"
              title="Ubah Status Dosen"
              @click="openChangeStatus(row)"
            >
              <RefreshCw class="w-3.5 h-3.5" />
            </button>

            <button
              v-if="can('lecturers.delete')"
              type="button"
              class="p-1 rounded-xs text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"
              title="Hapus Dosen"
              @click="confirmDelete(row)"
            >
              <Trash2 class="w-3.5 h-3.5" />
            </button>
          </div>
        </template>

        <!-- Pagination Footer -->
        <template #pagination>
          <Pagination
            :current-page="meta.current_page"
            :last-page="meta.last_page"
            :total="meta.total"
            :per-page="meta.per_page"
            :from="meta.from"
            :to="meta.to"
            @page-change="handlePageChange"
          />
        </template>
      </DataTable>
    </div>

    <!-- Modals -->
    <ChangeLecturerStatusModal
      v-if="selectedLecturer"
      :open="statusModalOpen"
      :lecturer="selectedLecturer"
      @update:open="statusModalOpen = $event"
      @success="handleStatusSuccess"
    />

    <ConfirmModal
      :open="deleteModalOpen"
      title="Hapus Data Dosen"
      :message="`Apakah Anda yakin ingin menghapus data dosen ${lecturerToDelete?.full_name}? Seluruh data terkait akan dihapus secara permanen.`"
      confirm-text="Ya, Hapus Dosen"
      variant="danger"
      :loading="deleteLoading"
      @update:open="deleteModalOpen = $event"
      @confirm="handleDelete"
    />
  </PageContainer>
</template>
