<script setup lang="ts">
import { useRouter } from 'vue-router'
import { Eye, MessageSquarePlus, RefreshCw } from 'lucide-vue-next'
import type { AcademicAdvisor } from '@/types/advising'
import DataTable, { type Column } from '@/components/data-display/DataTable.vue'
import Button from '@/components/ui/Button.vue'
import Avatar from '@/components/ui/Avatar.vue'
import AdvisorStatusBadge from './AdvisorStatusBadge.vue'
import { formatDate } from '@/utils/format'
import { usePermissions } from '@/composables/usePermissions'

defineProps<{
  advisees: AcademicAdvisor[]
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'create-session', advisor: AcademicAdvisor): void
  (e: 'reassign', advisor: AcademicAdvisor): void
}>()

const router = useRouter()
const { can } = usePermissions()

const columns: Column<AcademicAdvisor>[] = [
  { key: 'student', label: 'Mahasiswa Bimbingan' },
  { key: 'study_program', label: 'Program Studi & Status' },
  { key: 'start_date', label: 'Periode Penugasan', width: '150px' },
  { key: 'status', label: 'Status PA', align: 'center', width: '130px' },
  { key: 'actions', label: 'Aksi', align: 'right', width: '180px' },
]
</script>

<template>
  <div class="space-y-3">
    <!-- Desktop Data Table -->
    <div class="hidden md:block">
      <DataTable
        :columns="columns"
        :rows="advisees"
        :loading="loading"
        empty-title="Belum ada mahasiswa bimbingan"
        empty-description="Dosen ini belum memiliki mahasiswa bimbingan aktif. Klik 'Tambah Mahasiswa Bimbingan' untuk menetapkan mahasiswa."
      >
        <!-- Cell: Student -->
        <template #cell-student="{ row }">
          <div class="flex items-center gap-2.5">
            <Avatar
              :name="row.student?.full_name || 'M'"
              :src="row.student?.photo_path || undefined"
              size="sm"
              class="shrink-0"
            />
            <div class="min-w-0">
              <span class="font-bold text-slate-900 block truncate hover:text-brand-900 cursor-pointer" @click="router?.push(`/students/${row.student_id}`)">
                {{ row.student?.full_name || '-' }}
              </span>
              <span class="text-2xs font-mono text-slate-500 block">
                NIM: {{ row.student?.student_number || '-' }}
              </span>
            </div>
          </div>
        </template>

        <!-- Cell: Study Program -->
        <template #cell-study_program="{ row }">
          <div>
            <span class="font-medium text-slate-800 text-xs block">
              {{ row.student?.study_program?.name || '-' }}
            </span>
            <span class="text-2xs text-slate-500">
              {{ row.student?.study_program?.degree || 'S1' }} · Status {{ row.student?.status || 'Aktif' }}
            </span>
          </div>
        </template>

        <!-- Cell: Start Date -->
        <template #cell-start_date="{ row }">
          <div class="text-xs text-slate-600">
            <span class="block">Mulai: {{ formatDate(row.start_date) }}</span>
            <span v-if="row.end_date" class="text-2xs text-slate-400 block">Selesai: {{ formatDate(row.end_date) }}</span>
          </div>
        </template>

        <!-- Cell: Status -->
        <template #cell-status="{ row }">
          <AdvisorStatusBadge :status="row.status" />
        </template>

        <!-- Cell: Actions -->
        <template #cell-actions="{ row }">
          <div class="flex items-center justify-end gap-1.5">
            <!-- Log Session -->
            <Button
              v-if="can('advising.create_session') && row.status === 'active'"
              variant="ghost"
              size="xs"
              title="Buat Sesi Bimbingan"
              class="text-brand-800 hover:bg-brand-50"
              @click="emit('create-session', row)"
            >
              <MessageSquarePlus class="w-3.5 h-3.5" />
            </Button>

            <!-- Reassign PA -->
            <Button
              v-if="can('advising.assign') && row.status === 'active'"
              variant="ghost"
              size="xs"
              title="Alihkan Dosen PA"
              class="text-amber-700 hover:bg-amber-50"
              @click="emit('reassign', row)"
            >
              <RefreshCw class="w-3.5 h-3.5" />
            </Button>

            <!-- View Student Profile -->
            <Button
              variant="ghost"
              size="xs"
              title="Lihat Profil Mahasiswa"
              class="text-slate-600 hover:bg-slate-100"
              @click="router?.push(`/students/${row.student_id}`)"
            >
              <Eye class="w-3.5 h-3.5" />
            </Button>
          </div>
        </template>
      </DataTable>
    </div>

    <!-- Mobile Card View -->
    <div class="md:hidden space-y-2.5">
      <div
        v-for="row in advisees"
        :key="row.id"
        class="bg-white border border-slate-200 rounded-lg p-3.5 space-y-2.5 shadow-subtle"
      >
        <div class="flex items-start justify-between gap-2">
          <div class="flex items-center gap-2 min-w-0">
            <Avatar
              :name="row.student?.full_name || 'M'"
              :src="row.student?.photo_path || undefined"
              size="sm"
              class="shrink-0"
            />
            <div class="min-w-0">
              <span class="font-bold text-slate-900 text-xs block truncate" @click="router?.push(`/students/${row.student_id}`)">
                {{ row.student?.full_name || '-' }}
              </span>
              <span class="text-2xs font-mono text-slate-500">
                NIM: {{ row.student?.student_number || '-' }}
              </span>
            </div>
          </div>
          <AdvisorStatusBadge :status="row.status" />
        </div>

        <div class="text-xs text-slate-600 space-y-1 pt-2 border-t border-slate-100">
          <div class="flex items-center justify-between">
            <span class="text-slate-400 text-2xs">Prodi:</span>
            <span class="font-medium text-slate-800 text-2xs">{{ row.student?.study_program?.name || '-' }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-slate-400 text-2xs">Mulai Penugasan:</span>
            <span class="text-2xs">{{ formatDate(row.start_date) }}</span>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
          <Button
            v-if="can('advising.create_session') && row.status === 'active'"
            variant="outline"
            size="xs"
            @click="emit('create-session', row)"
          >
            <MessageSquarePlus class="w-3 h-3" />
            <span>Sesi</span>
          </Button>

          <Button
            v-if="can('advising.assign') && row.status === 'active'"
            variant="outline"
            size="xs"
            class="text-amber-700 border-amber-200"
            @click="emit('reassign', row)"
          >
            <RefreshCw class="w-3 h-3" />
            <span>Alihkan</span>
          </Button>

          <Button
            variant="primary"
            size="xs"
            @click="router.push(`/students/${row.student_id}`)"
          >
            <Eye class="w-3 h-3" />
            <span>Detail</span>
          </Button>
        </div>
      </div>
    </div>
  </div>
</template>
