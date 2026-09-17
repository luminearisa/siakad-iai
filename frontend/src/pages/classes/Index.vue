<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Plus, Eye, Edit3, Trash2, CheckCircle, XCircle, Slash, UserPlus } from 'lucide-vue-next'
import { classService } from '@/services/api/classes'
import { usePermissions } from '@/composables/usePermissions'
import { useAuth } from '@/composables/useAuth'
import { useToast } from '@/composables/useToast'
import type { AcademicClass, ClassFilters as ClassFiltersType } from '@/types/class'
import type { ApiMeta } from '@/types/api'
import PageContainer from '@/components/data-display/PageContainer.vue'
import PageHeader from '@/components/data-display/PageHeader.vue'
import DataTable, { type Column } from '@/components/data-display/DataTable.vue'
import Pagination from '@/components/data-display/Pagination.vue'
import Button from '@/components/ui/Button.vue'
import ConfirmModal from '@/components/feedback/ConfirmModal.vue'
import PersonalScopeNotice from '@/components/feedback/PersonalScopeNotice.vue'
import ClassFilters from './components/ClassFilters.vue'
import ClassStatusBadge from './components/ClassStatusBadge.vue'
import ClassCapacityBadge from './components/ClassCapacityBadge.vue'
import AddLecturerModal from './components/AddLecturerModal.vue'

const { can } = usePermissions()
const { isLecturer } = useAuth()
const toast = useToast()

// A plain lecturer (no class management rights) only ever sees their own classes,
// because the API scopes the listing to their lecturer profile.
const showLecturerScope = computed<boolean>(() => isLecturer.value && !can('classes.create'))

const classes = ref<AcademicClass[]>([])
const loading = ref<boolean>(false)
const meta = ref<ApiMeta>({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
  from: 0,
  to: 0,
})

const filters = ref<ClassFiltersType>({
  search: '',
  semester_id: '',
  study_program_id: '',
  course_id: '',
  status: '',
  sort: 'id',
  direction: 'desc',
  page: 1,
  per_page: 15,
})

// Modals
const addLecturerModalOpen = ref<boolean>(false)
const selectedClassForLecturer = ref<AcademicClass | null>(null)

const deleteModalOpen = ref<boolean>(false)
const classToDelete = ref<AcademicClass | null>(null)
const deleteLoading = ref<boolean>(false)

const openStatusModalOpen = ref<boolean>(false)
const closeStatusModalOpen = ref<boolean>(false)
const cancelStatusModalOpen = ref<boolean>(false)
const targetClass = ref<AcademicClass | null>(null)
const actionLoading = ref<boolean>(false)

const columns: Column<AcademicClass>[] = [
  { key: 'code', label: 'Kode Kelas', width: '130px', sortable: true },
  { key: 'course', label: 'Mata Kuliah' },
  { key: 'section', label: 'Seksi', width: '80px', align: 'center', sortable: true },
  { key: 'lecturers', label: 'Dosen Pengampu' },
  { key: 'capacity', label: 'Kapasitas Kursi', width: '150px', align: 'center' },
  { key: 'status', label: 'Status', width: '130px', align: 'center', sortable: true },
]

async function loadClasses() {
  loading.value = true
  try {
    const res = await classService.list(filters.value)
    classes.value = res.data || []
    if (res.meta) {
      meta.value = res.meta
    }
  } catch {
    classes.value = []
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
  loadClasses()
}

function handlePageChange(newPage: number) {
  filters.value.page = newPage
  loadClasses()
}

function openAddLecturer(c: AcademicClass) {
  selectedClassForLecturer.value = c
  addLecturerModalOpen.value = true
}

function handleLecturerSuccess() {
  loadClasses()
}

function confirmOpenClass(c: AcademicClass) {
  targetClass.value = c
  openStatusModalOpen.value = true
}

async function handleOpenClass() {
  if (!targetClass.value) return
  actionLoading.value = true
  try {
    await classService.open(targetClass.value.id)
    toast.success(`Kelas ${targetClass.value.code} dibuka untuk KRS.`)
    openStatusModalOpen.value = false
    targetClass.value = null
    loadClasses()
  } catch (err: any) {
    toast.error(err.message || 'Gagal membuka kelas.')
  } finally {
    actionLoading.value = false
  }
}

function confirmCloseClass(c: AcademicClass) {
  targetClass.value = c
  closeStatusModalOpen.value = true
}

async function handleCloseClass() {
  if (!targetClass.value) return
  actionLoading.value = true
  try {
    await classService.close(targetClass.value.id)
    toast.success(`Kelas ${targetClass.value.code} ditutup dari pendaftaran KRS.`)
    closeStatusModalOpen.value = false
    targetClass.value = null
    loadClasses()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menutup kelas.')
  } finally {
    actionLoading.value = false
  }
}

function confirmCancelClass(c: AcademicClass) {
  targetClass.value = c
  cancelStatusModalOpen.value = true
}

async function handleCancelClass() {
  if (!targetClass.value) return
  actionLoading.value = true
  try {
    await classService.cancel(targetClass.value.id)
    toast.success(`Kelas ${targetClass.value.code} dibatalkan.`)
    cancelStatusModalOpen.value = false
    targetClass.value = null
    loadClasses()
  } catch (err: any) {
    toast.error(err.message || 'Gagal membatalkan kelas.')
  } finally {
    actionLoading.value = false
  }
}

function confirmDelete(c: AcademicClass) {
  classToDelete.value = c
  deleteModalOpen.value = true
}

async function handleDelete() {
  if (!classToDelete.value) return
  deleteLoading.value = true
  try {
    await classService.delete(classToDelete.value.id)
    toast.success(`Kelas ${classToDelete.value.code} berhasil dihapus.`)
    deleteModalOpen.value = false
    classToDelete.value = null
    loadClasses()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menghapus kelas perkuliahan.')
  } finally {
    deleteLoading.value = false
  }
}

onMounted(() => {
  loadClasses()
})
</script>

<template>
  <PageContainer>
    <PageHeader
      title="Kelas Perkuliahan"
      :subtitle="showLecturerScope
        ? 'Daftar kelas perkuliahan yang Anda ampu pada semester berjalan'
        : 'Manajemen rombel kelas perkuliahan semester, penugasan dosen pengampu, dan kuota mahasiswa'"
      :breadcrumbs="[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Kelas' }]"
    >
      <template #actions>
        <router-link v-if="can('classes.create')" to="/classes/create">
          <Button variant="primary" size="sm">
            <Plus class="w-3.5 h-3.5" />
            <span>Buka Kelas Baru</span>
          </Button>
        </router-link>
      </template>
    </PageHeader>

    <div class="space-y-4">
      <!-- Lecturer personal scope notice -->
      <PersonalScopeNotice v-if="showLecturerScope" subject="kelas perkuliahan" />

      <!-- Search & Filters -->
      <ClassFilters v-model="filters" @change="loadClasses" />

      <!-- Data Table -->
      <DataTable
        :columns="columns"
        :rows="classes"
        :loading="loading"
        :sort-by="filters.sort"
        :sort-direction="filters.direction"
        empty-title="Tidak ada kelas perkuliahan"
        empty-description="Belum ada kelas perkuliahan yang cocok dengan kriteria filter."
        @sort="handleSort"
      >
        <template #emptyAction>
          <router-link v-if="can('classes.create')" to="/classes/create">
            <Button variant="primary" size="sm">
              <Plus class="w-3.5 h-3.5" />
              <span>Buka Kelas Baru</span>
            </Button>
          </router-link>
        </template>

        <!-- Custom Cell: Code -->
        <template #cell-code="{ row }">
          <router-link
            :to="`/classes/${row.id}`"
            class="font-mono font-bold text-xs text-brand-900 hover:text-brand-700 transition-colors"
          >
            {{ row.code }}
          </router-link>
        </template>

        <!-- Custom Cell: Course -->
        <template #cell-course="{ row }">
          <router-link :to="`/classes/${row.id}`" class="group block">
            <span class="font-semibold text-slate-900 group-hover:text-brand-900 transition-colors">
              {{ row.course?.name || row.name }}
            </span>
            <span class="block text-2xs text-slate-400">
              {{ row.course?.code }} · {{ row.course?.credits || 0 }} SKS · {{ row.semester?.name }}
            </span>
          </router-link>
        </template>

        <!-- Custom Cell: Section -->
        <template #cell-section="{ value }">
          <span class="font-bold text-xs text-brand-900 bg-brand-50 px-2 py-0.5 rounded border border-brand-200">
            {{ value }}
          </span>
        </template>

        <!-- Custom Cell: Lecturers -->
        <template #cell-lecturers="{ row }">
          <div v-if="row.lecturers && row.lecturers.length > 0" class="space-y-0.5">
            <div v-for="lec in row.lecturers" :key="lec.id" class="text-xs font-medium text-slate-800 truncate">
              {{ lec.full_name }}
            </div>
          </div>
          <span v-else class="text-xs text-slate-400 italic">Belum ditentukan</span>
        </template>

        <!-- Custom Cell: Capacity -->
        <template #cell-capacity="{ row }">
          <ClassCapacityBadge
            :capacity="row.capacity"
            :enrolled="row.enrolled_count || 0"
          />
        </template>

        <!-- Custom Cell: Status -->
        <template #cell-status="{ value }">
          <ClassStatusBadge :status="value" size="xs" />
        </template>

        <!-- Actions Column -->
        <template #actions="{ row }">
          <div class="flex items-center justify-end gap-1">
            <router-link :to="`/classes/${row.id}`">
              <button
                type="button"
                class="p-1 rounded-xs text-slate-400 hover:text-brand-800 hover:bg-slate-100 transition-colors cursor-pointer"
                title="Lihat Detail Kelas"
              >
                <Eye class="w-3.5 h-3.5" />
              </button>
            </router-link>

            <!-- Status Open -->
            <button
              v-if="can('classes.open') && (row.status === 'draft' || row.status === 'closed')"
              type="button"
              class="p-1 rounded-xs text-slate-400 hover:text-emerald-600 hover:bg-slate-100 transition-colors cursor-pointer"
              title="Buka Kelas untuk KRS"
              @click="confirmOpenClass(row)"
            >
              <CheckCircle class="w-3.5 h-3.5" />
            </button>

            <!-- Status Close -->
            <button
              v-if="can('classes.close') && row.status === 'open'"
              type="button"
              class="p-1 rounded-xs text-slate-400 hover:text-amber-600 hover:bg-slate-100 transition-colors cursor-pointer"
              title="Tutup Kelas dari KRS"
              @click="confirmCloseClass(row)"
            >
              <XCircle class="w-3.5 h-3.5" />
            </button>

            <!-- Status Cancel -->
            <button
              v-if="can('classes.cancel') && (row.status === 'open' || row.status === 'draft')"
              type="button"
              class="p-1 rounded-xs text-slate-400 hover:text-rose-600 hover:bg-slate-100 transition-colors cursor-pointer"
              title="Batalkan Kelas"
              @click="confirmCancelClass(row)"
            >
              <Slash class="w-3.5 h-3.5" />
            </button>

            <!-- Assign Lecturer -->
            <button
              v-if="can('classes.assign_lecturer')"
              type="button"
              class="p-1 rounded-xs text-slate-400 hover:text-indigo-600 hover:bg-slate-100 transition-colors cursor-pointer"
              title="Tugaskan Dosen"
              @click="openAddLecturer(row)"
            >
              <UserPlus class="w-3.5 h-3.5" />
            </button>

            <!-- Edit -->
            <router-link
              v-if="can('classes.update') && row.status !== 'cancelled' && row.status !== 'completed'"
              :to="`/classes/${row.id}/edit`"
            >
              <button
                type="button"
                class="p-1 rounded-xs text-slate-400 hover:text-brand-800 hover:bg-slate-100 transition-colors cursor-pointer"
                title="Edit Kelas"
              >
                <Edit3 class="w-3.5 h-3.5" />
              </button>
            </router-link>

            <!-- Delete -->
            <button
              v-if="can('classes.delete')"
              type="button"
              class="p-1 rounded-xs text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"
              title="Hapus Kelas"
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
    <AddLecturerModal
      v-if="selectedClassForLecturer"
      :open="addLecturerModalOpen"
      :academic-class="selectedClassForLecturer"
      @update:open="addLecturerModalOpen = $event"
      @success="handleLecturerSuccess"
    />

    <ConfirmModal
      :open="openStatusModalOpen"
      title="Buka Kelas Perkuliahan"
      :message="`Apakah Anda yakin ingin membuka kelas ${targetClass?.code} untuk pendaftaran KRS mahasiswa?`"
      confirm-text="Ya, Buka Kelas"
      variant="primary"
      :loading="actionLoading"
      @update:open="openStatusModalOpen = $event"
      @confirm="handleOpenClass"
    />

    <ConfirmModal
      :open="closeStatusModalOpen"
      title="Tutup Kelas Perkuliahan"
      :message="`Apakah Anda yakin ingin menutup kelas ${targetClass?.code}? Mahasiswa tidak dapat memilih kelas ini di KRS lagi.`"
      confirm-text="Ya, Tutup Kelas"
      variant="danger"
      :loading="actionLoading"
      @update:open="closeStatusModalOpen = $event"
      @confirm="handleCloseClass"
    />

    <ConfirmModal
      :open="cancelStatusModalOpen"
      title="Batalkan Kelas Perkuliahan"
      :message="`Apakah Anda yakin ingin membatalkan kelas ${targetClass?.code}?`"
      confirm-text="Ya, Batalkan Kelas"
      variant="danger"
      :loading="actionLoading"
      @update:open="cancelStatusModalOpen = $event"
      @confirm="handleCancelClass"
    />

    <ConfirmModal
      :open="deleteModalOpen"
      title="Hapus Kelas Perkuliahan"
      :message="`Apakah Anda yakin ingin menghapus kelas ${classToDelete?.code}? Kelas yang sudah memiliki mahasiswa terdaftar tidak dapat dihapus.`"
      confirm-text="Ya, Hapus Kelas"
      variant="danger"
      :loading="deleteLoading"
      @update:open="deleteModalOpen = $event"
      @confirm="handleDelete"
    />
  </PageContainer>
</template>
