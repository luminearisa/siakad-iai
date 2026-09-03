<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Plus, Eye, Edit3, Link2, Trash2 } from 'lucide-vue-next'
import { courseService } from '@/services/api/courses'
import { usePermissions } from '@/composables/usePermissions'
import { useToast } from '@/composables/useToast'
import type { Course, CourseFilters as CourseFiltersType } from '@/types/course'
import type { ApiMeta } from '@/types/api'
import PageContainer from '@/components/data-display/PageContainer.vue'
import PageHeader from '@/components/data-display/PageHeader.vue'
import DataTable, { type Column } from '@/components/data-display/DataTable.vue'
import Pagination from '@/components/data-display/Pagination.vue'
import Button from '@/components/ui/Button.vue'
import Badge from '@/components/ui/Badge.vue'
import ConfirmModal from '@/components/feedback/ConfirmModal.vue'
import CourseFilters from './components/CourseFilters.vue'
import CourseTypeBadge from './components/CourseTypeBadge.vue'
import AddPrerequisiteModal from './components/AddPrerequisiteModal.vue'

const { can } = usePermissions()
const toast = useToast()

const courses = ref<Course[]>([])
const loading = ref<boolean>(false)
const meta = ref<ApiMeta>({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
  from: 0,
  to: 0,
})

const filters = ref<CourseFiltersType>({
  search: '',
  type: '',
  category: '',
  status: '',
  credits: '',
  sort: 'code',
  direction: 'asc',
  page: 1,
  per_page: 15,
})

// Prerequisite Modal state
const prereqModalOpen = ref<boolean>(false)
const selectedCourse = ref<Course | null>(null)

// Delete Modal state
const deleteModalOpen = ref<boolean>(false)
const courseToDelete = ref<Course | null>(null)
const deleteLoading = ref<boolean>(false)

const columns: Column<Course>[] = [
  { key: 'code', label: 'Kode MK', width: '130px', sortable: true },
  { key: 'name', label: 'Nama Mata Kuliah', sortable: true },
  { key: 'credits', label: 'Bobot SKS', width: '120px', align: 'center', sortable: true },
  { key: 'type', label: 'Tipe', width: '130px', align: 'center' },
  { key: 'category', label: 'Kategori', width: '120px' },
  { key: 'status', label: 'Status', width: '110px', align: 'center', sortable: true },
]

async function loadCourses() {
  loading.value = true
  try {
    const res = await courseService.list(filters.value)
    courses.value = res.data || []
    if (res.meta) {
      meta.value = res.meta
    }
  } catch {
    courses.value = []
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
  loadCourses()
}

function handlePageChange(newPage: number) {
  filters.value.page = newPage
  loadCourses()
}

function openPrerequisites(c: Course) {
  selectedCourse.value = c
  prereqModalOpen.value = true
}

function handlePrereqSuccess() {
  loadCourses()
}

function confirmDelete(c: Course) {
  courseToDelete.value = c
  deleteModalOpen.value = true
}

async function handleDelete() {
  if (!courseToDelete.value) return
  deleteLoading.value = true
  try {
    await courseService.delete(courseToDelete.value.id)
    toast.success(`Mata kuliah ${courseToDelete.value.name} (${courseToDelete.value.code}) berhasil dihapus.`)
    deleteModalOpen.value = false
    courseToDelete.value = null
    loadCourses()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menghapus mata kuliah.')
  } finally {
    deleteLoading.value = false
  }
}

onMounted(() => {
  loadCourses()
})
</script>

<template>
  <PageContainer>
    <PageHeader
      title="Master Mata Kuliah"
      subtitle="Katalog kurikuler mata kuliah, bobot SKS teori/praktik, dan relasi prasyarat"
      :breadcrumbs="[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Mata Kuliah' }]"
    >
      <template #actions>
        <router-link v-if="can('courses.create')" to="/courses/create">
          <Button variant="primary" size="sm">
            <Plus class="w-3.5 h-3.5" />
            <span>Tambah Mata Kuliah</span>
          </Button>
        </router-link>
      </template>
    </PageHeader>

    <div class="space-y-4">
      <!-- Search & Filters -->
      <CourseFilters v-model="filters" @change="loadCourses" />

      <!-- Data Table -->
      <DataTable
        :columns="columns"
        :rows="courses"
        :loading="loading"
        :sort-by="filters.sort"
        :sort-direction="filters.direction"
        empty-title="Tidak ada mata kuliah"
        empty-description="Belum ada data mata kuliah yang sesuai dengan filter atau pencarian."
        @sort="handleSort"
      >
        <template #emptyAction>
          <router-link v-if="can('courses.create')" to="/courses/create">
            <Button variant="primary" size="sm">
              <Plus class="w-3.5 h-3.5" />
              <span>Tambah Mata Kuliah Baru</span>
            </Button>
          </router-link>
        </template>

        <!-- Custom Cell: Code -->
        <template #cell-code="{ row }">
          <router-link
            :to="`/courses/${row.id}`"
            class="font-mono font-bold text-xs text-brand-900 hover:text-brand-700 transition-colors"
          >
            {{ row.code }}
          </router-link>
        </template>

        <!-- Custom Cell: Name -->
        <template #cell-name="{ row }">
          <router-link :to="`/courses/${row.id}`" class="group block">
            <span class="font-semibold text-slate-900 group-hover:text-brand-900 transition-colors">
              {{ row.name }}
            </span>
            <span v-if="row.short_name" class="block text-2xs text-slate-400">
              Alias: {{ row.short_name }}
            </span>
          </router-link>
        </template>

        <!-- Custom Cell: Credits -->
        <template #cell-credits="{ row }">
          <div class="text-center">
            <span class="font-bold text-xs text-slate-900">{{ row.credits }} SKS</span>
            <span class="block text-2xs text-slate-400">
              {{ row.theory_credits }}T · {{ row.practical_credits }}P
            </span>
          </div>
        </template>

        <!-- Custom Cell: Type -->
        <template #cell-type="{ value }">
          <CourseTypeBadge :type="value" size="xs" />
        </template>

        <!-- Custom Cell: Category -->
        <template #cell-category="{ value }">
          <span class="text-xs font-medium text-slate-700">{{ value || '-' }}</span>
        </template>

        <!-- Custom Cell: Status -->
        <template #cell-status="{ value }">
          <Badge :variant="value === 'active' ? 'success' : 'neutral'" size="xs" dot>
            {{ value === 'active' ? 'Aktif' : 'Non-Aktif' }}
          </Badge>
        </template>

        <!-- Actions Column -->
        <template #actions="{ row }">
          <div class="flex items-center justify-end gap-1">
            <router-link :to="`/courses/${row.id}`">
              <button
                type="button"
                class="p-1 rounded-xs text-slate-400 hover:text-brand-800 hover:bg-slate-100 transition-colors cursor-pointer"
                title="Lihat Detail Mata Kuliah"
              >
                <Eye class="w-3.5 h-3.5" />
              </button>
            </router-link>

            <router-link v-if="can('courses.update')" :to="`/courses/${row.id}/edit`">
              <button
                type="button"
                class="p-1 rounded-xs text-slate-400 hover:text-brand-800 hover:bg-slate-100 transition-colors cursor-pointer"
                title="Edit Mata Kuliah"
              >
                <Edit3 class="w-3.5 h-3.5" />
              </button>
            </router-link>

            <button
              v-if="can('courses.update')"
              type="button"
              class="p-1 rounded-xs text-slate-400 hover:text-indigo-600 hover:bg-slate-100 transition-colors cursor-pointer"
              title="Atur Prasyarat"
              @click="openPrerequisites(row)"
            >
              <Link2 class="w-3.5 h-3.5" />
            </button>

            <button
              v-if="can('courses.delete')"
              type="button"
              class="p-1 rounded-xs text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"
              title="Hapus Mata Kuliah"
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
    <AddPrerequisiteModal
      v-if="selectedCourse"
      :open="prereqModalOpen"
      :course="selectedCourse"
      @update:open="prereqModalOpen = $event"
      @success="handlePrereqSuccess"
    />

    <ConfirmModal
      :open="deleteModalOpen"
      title="Hapus Mata Kuliah"
      :message="`Apakah Anda yakin ingin menghapus mata kuliah ${courseToDelete?.name} (${courseToDelete?.code})? Mata kuliah yang sudah terdistribusi dalam kurikulum atau jadwal kuliah tidak dapat dihapus.`"
      confirm-text="Ya, Hapus Mata Kuliah"
      variant="danger"
      :loading="deleteLoading"
      @update:open="deleteModalOpen = $event"
      @confirm="handleDelete"
    />
  </PageContainer>
</template>
