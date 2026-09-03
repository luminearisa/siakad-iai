<script setup lang="ts">
import { Eye, Edit3, Trash2, MapPin, Users } from 'lucide-vue-next'
import { usePermissions } from '@/composables/usePermissions'
import type { ClassSchedule } from '@/types/schedule'
import type { ApiMeta } from '@/types/api'
import DataTable, { type Column } from '@/components/data-display/DataTable.vue'
import Pagination from '@/components/data-display/Pagination.vue'
import ScheduleStatusBadge from '../components/ScheduleStatusBadge.vue'
import ScheduleTimeDisplay from '../components/ScheduleTimeDisplay.vue'

interface Props {
  schedules: ClassSchedule[]
  loading?: boolean
  meta: ApiMeta
}

defineProps<Props>()

const emit = defineEmits<{
  (e: 'page-change', page: number): void
  (e: 'delete', schedule: ClassSchedule): void
}>()

const { can } = usePermissions()

const columns: Column<ClassSchedule>[] = [
  { key: 'time', label: 'Hari & Waktu', width: '180px' },
  { key: 'course', label: 'Mata Kuliah & Kelas' },
  { key: 'lecturers', label: 'Dosen Pengampu' },
  { key: 'room', label: 'Ruangan', width: '150px' },
  { key: 'status', label: 'Status', width: '120px', align: 'center' },
]
</script>

<template>
  <DataTable
    :columns="columns"
    :rows="schedules"
    :loading="loading"
    empty-title="Tidak ada jadwal perkuliahan"
    empty-description="Belum ada data jadwal perkuliahan yang cocok dengan filter yang dipilih."
  >
    <!-- Cell: Time -->
    <template #cell-time="{ row }">
      <ScheduleTimeDisplay
        :day-of-week="row.day_of_week"
        :start-time="row.start_time"
        :end-time="row.end_time"
        show-icon
      />
    </template>

    <!-- Cell: Course & Class -->
    <template #cell-course="{ row }">
      <router-link :to="`/schedules/${row.id}`" class="group block">
        <span class="font-bold text-slate-900 group-hover:text-brand-900 transition-colors">
          {{ row.academic_class?.course?.name || row.academic_class?.name || '-' }}
        </span>
        <div class="flex items-center gap-1.5 text-2xs text-slate-500 mt-0.5">
          <span class="font-mono font-semibold text-slate-700">{{ row.academic_class?.course?.code }}</span>
          <span>•</span>
          <span class="font-bold text-brand-900 bg-brand-50 px-1.5 py-0.2 rounded border border-brand-200">
            Kelas {{ row.academic_class?.section }}
          </span>
          <span v-if="row.academic_class?.course?.credits">•</span>
          <span v-if="row.academic_class?.course?.credits">{{ row.academic_class.course.credits }} SKS</span>
        </div>
      </router-link>
    </template>

    <!-- Cell: Lecturers -->
    <template #cell-lecturers="{ row }">
      <div v-if="row.academic_class?.lecturers && row.academic_class.lecturers.length > 0" class="space-y-0.5">
        <div
          v-for="lec in row.academic_class.lecturers"
          :key="lec.id"
          class="text-xs text-slate-800 font-medium truncate flex items-center gap-1"
        >
          <Users class="w-3 h-3 text-slate-400 shrink-0" />
          <span>{{ lec.full_name }}</span>
        </div>
      </div>
      <span v-else class="text-xs text-slate-400 italic">Belum ditentukan</span>
    </template>

    <!-- Cell: Room -->
    <template #cell-room="{ row }">
      <div v-if="row.room" class="flex items-center gap-1.5 text-xs">
        <MapPin class="w-3.5 h-3.5 text-slate-400 shrink-0" />
        <div>
          <span class="font-mono font-bold text-slate-900 block">{{ row.room.code }}</span>
          <span class="text-2xs text-slate-500 block">{{ row.room.name }}</span>
        </div>
      </div>
      <span v-else class="text-xs text-slate-400 italic">TBD</span>
    </template>

    <!-- Cell: Status -->
    <template #cell-status="{ value }">
      <ScheduleStatusBadge :status="value" size="xs" />
    </template>

    <!-- Actions -->
    <template #actions="{ row }">
      <div class="flex items-center justify-end gap-1">
        <router-link :to="`/schedules/${row.id}`">
          <button
            type="button"
            class="p-1 rounded-xs text-slate-400 hover:text-brand-800 hover:bg-slate-100 transition-colors cursor-pointer"
            title="Lihat Detail Jadwal"
          >
            <Eye class="w-3.5 h-3.5" />
          </button>
        </router-link>

        <router-link v-if="can('schedules.update')" :to="`/schedules/${row.id}/edit`">
          <button
            type="button"
            class="p-1 rounded-xs text-slate-400 hover:text-brand-800 hover:bg-slate-100 transition-colors cursor-pointer"
            title="Edit Jadwal"
          >
            <Edit3 class="w-3.5 h-3.5" />
          </button>
        </router-link>

        <button
          v-if="can('schedules.delete')"
          type="button"
          class="p-1 rounded-xs text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"
          title="Hapus Jadwal"
          @click="emit('delete', row)"
        >
          <Trash2 class="w-3.5 h-3.5" />
        </button>
      </div>
    </template>

    <!-- Pagination -->
    <template #pagination>
      <Pagination
        :current-page="meta.current_page"
        :last-page="meta.last_page"
        :total="meta.total"
        :per-page="meta.per_page"
        :from="meta.from"
        :to="meta.to"
        @page-change="emit('page-change', $event)"
      />
    </template>
  </DataTable>
</template>
