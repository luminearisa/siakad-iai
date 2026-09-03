<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { CalendarCheck, Eye, Search } from 'lucide-vue-next'
import { classService } from '@/services/api/classes'
import { usePermissions } from '@/composables/usePermissions'
import type { AcademicClass } from '@/types/class'
import type { ApiMeta } from '@/types/api'
import PageContainer from '@/components/data-display/PageContainer.vue'
import PageHeader from '@/components/data-display/PageHeader.vue'
import DataTable, { type Column } from '@/components/data-display/DataTable.vue'
import Pagination from '@/components/data-display/Pagination.vue'
import Input from '@/components/ui/Input.vue'
import Button from '@/components/ui/Button.vue'
import Badge from '@/components/ui/Badge.vue'

const router = useRouter()
const { hasRole } = usePermissions()

const classes = ref<AcademicClass[]>([])
const loading = ref<boolean>(false)
const search = ref<string>('')

const meta = ref<ApiMeta>({
  current_page: 1,
  last_page: 1,
  per_page: 25,
  total: 0,
  from: 0,
  to: 0,
})

const columns: Column<AcademicClass>[] = [
  { key: 'code', label: 'Kode Kelas', width: '130px' },
  { key: 'name', label: 'Nama Kelas / Mata Kuliah' },
  { key: 'study_program', label: 'Program Studi' },
  { key: 'lecturers', label: 'Dosen Pengampu' },
  { key: 'status', label: 'Status Kelas', width: '110px', align: 'center' },
  { key: 'actions', label: 'Aksi', align: 'right', width: '140px' },
]

async function loadClasses() {
  loading.value = true
  try {
    const res = await classService.list({
      search: search.value || undefined,
      page: meta.value.current_page,
      per_page: meta.value.per_page,
    })
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

function handleSearch() {
  meta.value.current_page = 1
  loadClasses()
}

function handlePageChange(page: number) {
  meta.value.current_page = page
  loadClasses()
}

function navigateToClassAttendance(classId: number) {
  router.push(`/attendance/classes/${classId}`)
}

function navigateToMyAttendance() {
  router.push('/attendance/my')
}

onMounted(() => {
  loadClasses()
})
</script>

<template>
  <PageContainer>
    <PageHeader
      title="Presensi & Pertemuan Perkuliahan"
      subtitle="Kelola Berita Acara Perkuliahan (BAP), presensi mahasiswa per pertemuan, dan matriks kehadiran 1–16"
      :breadcrumbs="[
        { label: 'Dashboard', to: '/dashboard' },
        { label: 'Operasional Perkuliahan' },
        { label: 'Presensi Perkuliahan' },
      ]"
    >
      <template #actions>
        <Button
          v-if="hasRole('mahasiswa')"
          variant="primary"
          size="md"
          @click="navigateToMyAttendance"
        >
          <CalendarCheck class="w-4 h-4" />
          <span>Kehadiran Saya (Mahasiswa)</span>
        </Button>
      </template>
    </PageHeader>

    <div class="space-y-4">
      <!-- Search Box -->
      <div class="bg-white border border-slate-200 rounded-lg p-3 sm:p-4 shadow-subtle flex flex-col sm:flex-row items-center justify-between gap-3">
        <div class="w-full sm:max-w-md">
          <Input
            v-model="search"
            placeholder="Cari kode kelas, nama mata kuliah, atau dosen..."
            size="sm"
            @keyup.enter="handleSearch"
          >
            <template #prefix>
              <Search class="w-3.5 h-3.5 text-slate-400" />
            </template>
          </Input>
        </div>

        <Button variant="primary" size="xs" :loading="loading" @click="handleSearch">
          <Search class="w-3 h-3" />
          <span>Cari Kelas</span>
        </Button>
      </div>

      <!-- Class Table -->
      <DataTable
        :columns="columns"
        :rows="classes"
        :loading="loading"
        empty-title="Belum ada kelas perkuliahan"
        empty-description="Kelas perkuliahan akan muncul di sini setelah dibuat pada modul Kelas."
      >
        <!-- Cell: Code -->
        <template #cell-code="{ value }">
          <span class="font-mono font-bold text-xs text-brand-900 bg-brand-50 px-2 py-0.5 rounded border border-brand-200">
            {{ value }}
          </span>
        </template>

        <!-- Cell: Name -->
        <template #cell-name="{ row }">
          <div>
            <span class="font-bold text-slate-900 text-xs block">
              {{ row.name }}
            </span>
            <span class="text-2xs text-slate-400">
              Mata Kuliah: {{ row.course?.code }} — {{ row.course?.name }} ({{ row.course?.credits || 0 }} SKS)
            </span>
          </div>
        </template>

        <!-- Cell: Study Program -->
        <template #cell-study_program="{ row }">
          <span class="text-xs text-slate-700">
            {{ row.study_program?.name || 'Bersama / Umum' }}
          </span>
        </template>

        <!-- Cell: Lecturers -->
        <template #cell-lecturers="{ row }">
          <div class="text-xs text-slate-700">
            <span v-if="row.lecturers && row.lecturers.length > 0">
              {{ row.lecturers.map((l: any) => l.full_name).join(', ') }}
            </span>
            <span v-else class="text-slate-400 italic">
              Belum ditentukan
            </span>
          </div>
        </template>

        <!-- Cell: Status -->
        <template #cell-status="{ row }">
          <Badge
            :variant="row.status === 'open' ? 'success' : (row.status === 'cancelled' ? 'danger' : 'neutral')"
            size="sm"
          >
            {{ row.status === 'open' ? 'Buka' : (row.status === 'closed' ? 'Tutup' : 'Draft') }}
          </Badge>
        </template>

        <!-- Cell: Actions -->
        <template #cell-actions="{ row }">
          <div class="flex items-center justify-end gap-1">
            <Button
              variant="outline"
              size="xs"
              class="border-brand-300 text-brand-800 hover:bg-brand-50"
              @click="navigateToClassAttendance(row.id)"
            >
              <Eye class="w-3.5 h-3.5 mr-1" />
              <span>Kelola Presensi</span>
            </Button>
          </div>
        </template>
      </DataTable>

      <!-- Pagination -->
      <Pagination
        :current-page="meta.current_page"
        :last-page="meta.last_page"
        :total="meta.total"
        :per-page="meta.per_page"
        :from="meta.from"
        :to="meta.to"
        @page-change="handlePageChange"
      />
    </div>
  </PageContainer>
</template>
