<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Plus, Eye, Edit3, RefreshCw, Trash2 } from 'lucide-vue-next'
import { studentService } from '@/services/api/students'
import { usePermissions } from '@/composables/usePermissions'
import { useToast } from '@/composables/useToast'
import type { Student, StudentFilters as StudentFiltersType } from '@/types/student'
import type { ApiMeta } from '@/types/api'
import PageContainer from '@/components/data-display/PageContainer.vue'
import PageHeader from '@/components/data-display/PageHeader.vue'
import DataTable, { type Column } from '@/components/data-display/DataTable.vue'
import Pagination from '@/components/data-display/Pagination.vue'
import Button from '@/components/ui/Button.vue'
import ConfirmModal from '@/components/feedback/ConfirmModal.vue'
import StudentFilters from './components/StudentFilters.vue'
import StudentStatusBadge from './components/StudentStatusBadge.vue'
import ChangeStatusModal from './components/ChangeStatusModal.vue'

const { can } = usePermissions()
const toast = useToast()

const students = ref<Student[]>([])
const loading = ref<boolean>(false)
const meta = ref<ApiMeta>({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
  from: 0,
  to: 0,
})

const filters = ref<StudentFiltersType>({
  search: '',
  status: '',
  study_program_id: '',
  gender: '',
  admission_year: '',
  sort: 'id',
  direction: 'desc',
  page: 1,
  per_page: 15,
})

// Modal states
const statusModalOpen = ref<boolean>(false)
const selectedStudent = ref<Student | null>(null)

const deleteModalOpen = ref<boolean>(false)
const studentToDelete = ref<Student | null>(null)
const deleteLoading = ref<boolean>(false)

const columns: Column<Student>[] = [
  { key: 'student_number', label: 'NIM', width: '130px', sortable: true },
  { key: 'full_name', label: 'Nama Mahasiswa', sortable: true },
  { key: 'study_program', label: 'Program Studi' },
  { key: 'admission_year', label: 'Angkatan', align: 'center', width: '90px', sortable: true },
  { key: 'gender', label: 'L/P', align: 'center', width: '70px' },
  { key: 'status', label: 'Status', align: 'center', width: '130px', sortable: true },
]

async function loadStudents() {
  loading.value = true
  try {
    const res = await studentService.list(filters.value)
    students.value = res.data || []
    if (res.meta) {
      meta.value = res.meta
    }
  } catch {
    students.value = []
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
  loadStudents()
}

function handlePageChange(newPage: number) {
  filters.value.page = newPage
  loadStudents()
}

function openChangeStatus(st: Student) {
  selectedStudent.value = st
  statusModalOpen.value = true
}

function handleStatusSuccess(updated: Student) {
  const idx = students.value.findIndex(s => s.id === updated.id)
  if (idx !== -1) {
    students.value[idx] = updated
  } else {
    loadStudents()
  }
}

function confirmDelete(st: Student) {
  studentToDelete.value = st
  deleteModalOpen.value = true
}

async function handleDelete() {
  if (!studentToDelete.value) return
  deleteLoading.value = true
  try {
    await studentService.delete(studentToDelete.value.id)
    toast.success(`Data mahasiswa ${studentToDelete.value.full_name} berhasil dihapus.`)
    deleteModalOpen.value = false
    studentToDelete.value = null
    loadStudents()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menghapus data mahasiswa.')
  } finally {
    deleteLoading.value = false
  }
}

onMounted(() => {
  loadStudents()
})
</script>

<template>
  <PageContainer>
    <PageHeader
      title="Data Mahasiswa"
      subtitle="Direktori master mahasiswa, manajemen profil biodata, dan status akademik"
      :breadcrumbs="[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Mahasiswa' }]"
    >
      <template #actions>
        <router-link v-if="can('students.create')" to="/students/create">
          <Button variant="primary" size="sm">
            <Plus class="w-3.5 h-3.5" />
            <span>Tambah Mahasiswa</span>
          </Button>
        </router-link>
      </template>
    </PageHeader>

    <div class="space-y-4">
      <!-- Search & Filters Bar -->
      <StudentFilters v-model="filters" @change="loadStudents" />

      <!-- Data Table -->
      <DataTable
        :columns="columns"
        :rows="students"
        :loading="loading"
        :sort-by="filters.sort"
        :sort-direction="filters.direction"
        empty-title="Tidak ada data mahasiswa"
        empty-description="Belum ada data mahasiswa yang cocok dengan kriteria pencarian / filter."
        @sort="handleSort"
      >
        <template #emptyAction>
          <router-link v-if="can('students.create')" to="/students/create">
            <Button variant="primary" size="sm">
              <Plus class="w-3.5 h-3.5" />
              <span>Tambah Mahasiswa Baru</span>
            </Button>
          </router-link>
        </template>

        <!-- Custom Cell: NIM -->
        <template #cell-student_number="{ row }">
          <router-link
            :to="`/students/${row.id}`"
            class="font-mono font-semibold text-brand-900 hover:text-brand-700 transition-colors"
          >
            {{ row.student_number }}
          </router-link>
        </template>

        <!-- Custom Cell: Nama Lengkap -->
        <template #cell-full_name="{ row }">
          <router-link :to="`/students/${row.id}`" class="group block">
            <span class="font-medium text-slate-900 group-hover:text-brand-900 transition-colors">
              {{ row.full_name }}
            </span>
            <span v-if="row.email" class="block text-2xs text-slate-400">
              {{ row.email }}
            </span>
          </router-link>
        </template>

        <!-- Custom Cell: Prodi -->
        <template #cell-study_program="{ row }">
          <span class="font-medium text-slate-800">{{ row.study_program?.name || '-' }}</span>
          <span v-if="row.study_program?.degree" class="text-2xs text-slate-400 block font-normal">
            {{ row.study_program?.degree }}
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
          <StudentStatusBadge :status="value" size="xs" />
        </template>

        <!-- Actions Column -->
        <template #actions="{ row }">
          <div class="flex items-center justify-end gap-1">
            <router-link :to="`/students/${row.id}`">
              <button
                type="button"
                class="p-1 rounded-xs text-slate-400 hover:text-brand-800 hover:bg-slate-100 transition-colors cursor-pointer"
                title="Lihat Detail Profil"
              >
                <Eye class="w-3.5 h-3.5" />
              </button>
            </router-link>

            <router-link v-if="can('students.update')" :to="`/students/${row.id}/edit`">
              <button
                type="button"
                class="p-1 rounded-xs text-slate-400 hover:text-brand-800 hover:bg-slate-100 transition-colors cursor-pointer"
                title="Edit Data Mahasiswa"
              >
                <Edit3 class="w-3.5 h-3.5" />
              </button>
            </router-link>

            <button
              v-if="can('students.change_status')"
              type="button"
              class="p-1 rounded-xs text-slate-400 hover:text-amber-600 hover:bg-slate-100 transition-colors cursor-pointer"
              title="Ubah Status Akademik"
              @click="openChangeStatus(row)"
            >
              <RefreshCw class="w-3.5 h-3.5" />
            </button>

            <button
              v-if="can('students.delete')"
              type="button"
              class="p-1 rounded-xs text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"
              title="Hapus Mahasiswa"
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
    <ChangeStatusModal
      v-if="selectedStudent"
      :open="statusModalOpen"
      :student="selectedStudent"
      @update:open="statusModalOpen = $event"
      @success="handleStatusSuccess"
    />

    <ConfirmModal
      :open="deleteModalOpen"
      title="Hapus Data Mahasiswa"
      :message="`Apakah Anda yakin ingin menghapus data mahasiswa ${studentToDelete?.full_name} (${studentToDelete?.student_number})? Tindakan ini tidak dapat dibatalkan.`"
      confirm-text="Ya, Hapus Mahasiswa"
      variant="danger"
      :loading="deleteLoading"
      @update:open="deleteModalOpen = $event"
      @confirm="handleDelete"
    />
  </PageContainer>
</template>
