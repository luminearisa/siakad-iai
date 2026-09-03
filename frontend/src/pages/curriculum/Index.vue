<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Plus, Eye, Edit3, Trash2, CheckCircle2, Archive } from 'lucide-vue-next'
import { curriculumService } from '@/services/api/curriculum'
import { usePermissions } from '@/composables/usePermissions'
import { useToast } from '@/composables/useToast'
import type { Curriculum, CurriculumFilters as CurriculumFiltersType } from '@/types/curriculum'
import type { ApiMeta } from '@/types/api'
import PageContainer from '@/components/data-display/PageContainer.vue'
import PageHeader from '@/components/data-display/PageHeader.vue'
import DataTable, { type Column } from '@/components/data-display/DataTable.vue'
import Pagination from '@/components/data-display/Pagination.vue'
import Button from '@/components/ui/Button.vue'
import ConfirmModal from '@/components/feedback/ConfirmModal.vue'
import CurriculumFilters from './components/CurriculumFilters.vue'
import CurriculumStatusBadge from './components/CurriculumStatusBadge.vue'

const { can } = usePermissions()
const toast = useToast()

const curricula = ref<Curriculum[]>([])
const loading = ref<boolean>(false)
const meta = ref<ApiMeta>({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
  from: 0,
  to: 0,
})

const filters = ref<CurriculumFiltersType>({
  search: '',
  study_program_id: '',
  status: '',
  start_year: '',
  end_year: '',
  sort: 'id',
  direction: 'desc',
  page: 1,
  per_page: 15,
})

// Modals
const deleteModalOpen = ref<boolean>(false)
const curriculumToDelete = ref<Curriculum | null>(null)
const deleteLoading = ref<boolean>(false)

const activateModalOpen = ref<boolean>(false)
const curriculumToActivate = ref<Curriculum | null>(null)
const activateLoading = ref<boolean>(false)

const archiveModalOpen = ref<boolean>(false)
const curriculumToArchive = ref<Curriculum | null>(null)
const archiveLoading = ref<boolean>(false)

const columns: Column<Curriculum>[] = [
  { key: 'code', label: 'Kode Kurikulum', width: '150px', sortable: true },
  { key: 'name', label: 'Nama Kurikulum', sortable: true },
  { key: 'study_program', label: 'Program Studi' },
  { key: 'version', label: 'Versi', width: '90px', align: 'center' },
  { key: 'total_credits', label: 'Total SKS', width: '100px', align: 'center' },
  { key: 'status', label: 'Status', width: '130px', align: 'center', sortable: true },
]

async function loadCurricula() {
  loading.value = true
  try {
    const res = await curriculumService.list(filters.value)
    curricula.value = res.data || []
    if (res.meta) {
      meta.value = res.meta
    }
  } catch {
    curricula.value = []
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
  loadCurricula()
}

function handlePageChange(newPage: number) {
  filters.value.page = newPage
  loadCurricula()
}

function confirmActivate(c: Curriculum) {
  curriculumToActivate.value = c
  activateModalOpen.value = true
}

async function handleActivate() {
  if (!curriculumToActivate.value) return
  activateLoading.value = true
  try {
    await curriculumService.activate(curriculumToActivate.value.id)
    toast.success(`Kurikulum ${curriculumToActivate.value.name} berhasil diaktifkan.`)
    activateModalOpen.value = false
    curriculumToActivate.value = null
    loadCurricula()
  } catch (err: any) {
    toast.error(err.message || 'Gagal mengaktifkan kurikulum.')
  } finally {
    activateLoading.value = false
  }
}

function confirmArchive(c: Curriculum) {
  curriculumToArchive.value = c
  archiveModalOpen.value = true
}

async function handleArchive() {
  if (!curriculumToArchive.value) return
  archiveLoading.value = true
  try {
    await curriculumService.archive(curriculumToArchive.value.id)
    toast.success(`Kurikulum ${curriculumToArchive.value.name} berhasil diarsipkan.`)
    archiveModalOpen.value = false
    curriculumToArchive.value = null
    loadCurricula()
  } catch (err: any) {
    toast.error(err.message || 'Gagal mengarsipkan kurikulum.')
  } finally {
    archiveLoading.value = false
  }
}

function confirmDelete(c: Curriculum) {
  curriculumToDelete.value = c
  deleteModalOpen.value = true
}

async function handleDelete() {
  if (!curriculumToDelete.value) return
  deleteLoading.value = true
  try {
    await curriculumService.delete(curriculumToDelete.value.id)
    toast.success(`Kurikulum ${curriculumToDelete.value.name} berhasil dihapus.`)
    deleteModalOpen.value = false
    curriculumToDelete.value = null
    loadCurricula()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menghapus kurikulum.')
  } finally {
    deleteLoading.value = false
  }
}

onMounted(() => {
  loadCurricula()
})
</script>

<template>
  <PageContainer>
    <PageHeader
      title="Manajemen Kurikulum"
      subtitle="Struktur dokumen kurikulum program studi, paket semester, dan pemetaan mata kuliah"
      :breadcrumbs="[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Kurikulum' }]"
    >
      <template #actions>
        <router-link v-if="can('curricula.create')" to="/curriculum/create">
          <Button variant="primary" size="sm">
            <Plus class="w-3.5 h-3.5" />
            <span>Tambah Kurikulum</span>
          </Button>
        </router-link>
      </template>
    </PageHeader>

    <div class="space-y-4">
      <!-- Search & Filters -->
      <CurriculumFilters v-model="filters" @change="loadCurricula" />

      <!-- Data Table -->
      <DataTable
        :columns="columns"
        :rows="curricula"
        :loading="loading"
        :sort-by="filters.sort"
        :sort-direction="filters.direction"
        empty-title="Tidak ada kurikulum"
        empty-description="Belum ada data kurikulum yang cocok dengan kriteria filter."
        @sort="handleSort"
      >
        <template #emptyAction>
          <router-link v-if="can('curricula.create')" to="/curriculum/create">
            <Button variant="primary" size="sm">
              <Plus class="w-3.5 h-3.5" />
              <span>Buat Kurikulum Baru</span>
            </Button>
          </router-link>
        </template>

        <!-- Custom Cell: Code -->
        <template #cell-code="{ row }">
          <router-link
            :to="`/curriculum/${row.id}`"
            class="font-mono font-bold text-xs text-brand-900 hover:text-brand-700 transition-colors"
          >
            {{ row.code }}
          </router-link>
        </template>

        <!-- Custom Cell: Name -->
        <template #cell-name="{ row }">
          <router-link :to="`/curriculum/${row.id}`" class="group block">
            <span class="font-semibold text-slate-900 group-hover:text-brand-900 transition-colors">
              {{ row.name }}
            </span>
            <span v-if="row.start_year" class="block text-2xs text-slate-400">
              Berlaku: {{ row.start_year }} - {{ row.end_year || 'Seterusnya' }}
            </span>
          </router-link>
        </template>

        <!-- Custom Cell: Study Program -->
        <template #cell-study_program="{ row }">
          <span class="font-medium text-slate-800">{{ row.study_program?.name || '-' }}</span>
          <span v-if="row.study_program?.faculty" class="text-2xs text-slate-400 block font-normal">
            {{ row.study_program.faculty.name }}
          </span>
        </template>

        <!-- Custom Cell: Version -->
        <template #cell-version="{ value }">
          <span class="font-mono text-xs text-slate-600 bg-slate-100 px-1.5 py-0.5 rounded">
            v{{ value || '1.0' }}
          </span>
        </template>

        <!-- Custom Cell: Total Credits -->
        <template #cell-total_credits="{ value }">
          <span class="font-bold text-xs text-slate-900">{{ value || 0 }} SKS</span>
        </template>

        <!-- Custom Cell: Status -->
        <template #cell-status="{ value }">
          <CurriculumStatusBadge :status="value" size="xs" />
        </template>

        <!-- Actions Column -->
        <template #actions="{ row }">
          <div class="flex items-center justify-end gap-1">
            <router-link :to="`/curriculum/${row.id}`">
              <button
                type="button"
                class="p-1 rounded-xs text-slate-400 hover:text-brand-800 hover:bg-slate-100 transition-colors cursor-pointer"
                title="Lihat Detail & Struktur Kurikulum"
              >
                <Eye class="w-3.5 h-3.5" />
              </button>
            </router-link>

            <!-- Activate action button -->
            <button
              v-if="can('curricula.activate') && row.status !== 'active' && row.status !== 'archived'"
              type="button"
              class="p-1 rounded-xs text-slate-400 hover:text-emerald-600 hover:bg-slate-100 transition-colors cursor-pointer"
              title="Aktivasi Kurikulum"
              @click="confirmActivate(row)"
            >
              <CheckCircle2 class="w-3.5 h-3.5" />
            </button>

            <!-- Archive action button -->
            <button
              v-if="can('curricula.archive') && row.status === 'active'"
              type="button"
              class="p-1 rounded-xs text-slate-400 hover:text-amber-600 hover:bg-slate-100 transition-colors cursor-pointer"
              title="Arsipkan Kurikulum"
              @click="confirmArchive(row)"
            >
              <Archive class="w-3.5 h-3.5" />
            </button>

            <router-link v-if="can('curricula.update') && row.status !== 'archived'" :to="`/curriculum/${row.id}/edit`">
              <button
                type="button"
                class="p-1 rounded-xs text-slate-400 hover:text-brand-800 hover:bg-slate-100 transition-colors cursor-pointer"
                title="Edit Kurikulum"
              >
                <Edit3 class="w-3.5 h-3.5" />
              </button>
            </router-link>

            <button
              v-if="can('curricula.delete')"
              type="button"
              class="p-1 rounded-xs text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"
              title="Hapus Kurikulum"
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
    <ConfirmModal
      :open="activateModalOpen"
      title="Aktivasi Kurikulum"
      :message="`Apakah Anda yakin ingin mengaktifkan kurikulum ${curriculumToActivate?.name}? Kurikulum aktif akan dijadikan acuan pengambilan KRS mahasiswa angkatan terkait.`"
      confirm-text="Ya, Aktifkan Kurikulum"
      variant="primary"
      :loading="activateLoading"
      @update:open="activateModalOpen = $event"
      @confirm="handleActivate"
    />

    <ConfirmModal
      :open="archiveModalOpen"
      title="Arsipkan Kurikulum"
      :message="`Apakah Anda yakin ingin mengarsipkan kurikulum ${curriculumToArchive?.name}? Kurikulum yang diarsipkan tidak dapat diubah kembali.`"
      confirm-text="Ya, Arsipkan"
      variant="danger"
      :loading="archiveLoading"
      @update:open="archiveModalOpen = $event"
      @confirm="handleArchive"
    />

    <ConfirmModal
      :open="deleteModalOpen"
      title="Hapus Kurikulum"
      :message="`Apakah Anda yakin ingin menghapus kurikulum ${curriculumToDelete?.name} (${curriculumToDelete?.code})? Seluruh distribusi semester dan mata kuliah terkait akan dihapus.`"
      confirm-text="Ya, Hapus Kurikulum"
      variant="danger"
      :loading="deleteLoading"
      @update:open="deleteModalOpen = $event"
      @confirm="handleDelete"
    />
  </PageContainer>
</template>
