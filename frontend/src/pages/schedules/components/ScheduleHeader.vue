<script setup lang="ts">
import { ArrowLeft, Edit3, Trash2, Calendar, MapPin, Users } from 'lucide-vue-next'
import { usePermissions } from '@/composables/usePermissions'
import type { ClassSchedule } from '@/types/schedule'
import Button from '@/components/ui/Button.vue'
import ScheduleStatusBadge from './ScheduleStatusBadge.vue'
import ScheduleTimeDisplay from './ScheduleTimeDisplay.vue'

interface Props {
  schedule: ClassSchedule
}

defineProps<Props>()

const emit = defineEmits<{
  (e: 'delete'): void
}>()

const { can } = usePermissions()
</script>

<template>
  <div class="bg-white border border-slate-200 rounded-lg p-4 sm:p-5 shadow-subtle mb-5">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <!-- Left Info -->
      <div class="flex items-start sm:items-center gap-3.5">
        <div class="w-12 h-12 rounded-lg bg-brand-50 text-brand-900 flex items-center justify-center shrink-0 border border-brand-100">
          <Calendar class="w-6 h-6" />
        </div>

        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-2 mb-1">
            <ScheduleTimeDisplay
              :day-of-week="schedule.day_of_week"
              :start-time="schedule.start_time"
              :end-time="schedule.end_time"
            />
            <ScheduleStatusBadge :status="schedule.status" size="xs" />
          </div>

          <h1 class="text-base sm:text-lg font-bold text-slate-900 tracking-tight truncate">
            {{ schedule.academic_class?.course?.name || schedule.academic_class?.name || 'Jadwal Kuliah' }}
          </h1>

          <div class="flex flex-wrap items-center gap-y-1 gap-x-2.5 text-xs text-slate-500 mt-0.5">
            <span class="font-bold text-brand-900 bg-brand-50 px-1.5 py-0.2 rounded border border-brand-200">
              Kelas {{ schedule.academic_class?.section }}
            </span>
            <span>•</span>
            <span v-if="schedule.room" class="inline-flex items-center gap-1 font-medium text-slate-700">
              <MapPin class="w-3 h-3 text-slate-400" />
              {{ schedule.room.name }} ({{ schedule.room.code }})
            </span>
            <span v-else class="text-slate-400 italic">Ruangan belum dialokasikan</span>
            <span>•</span>
            <span v-if="schedule.academic_class?.lecturers && schedule.academic_class.lecturers.length > 0" class="inline-flex items-center gap-1 text-slate-600">
              <Users class="w-3 h-3 text-slate-400" />
              {{ schedule.academic_class.lecturers.map(l => l.full_name).join(', ') }}
            </span>
          </div>
        </div>
      </div>

      <!-- Right Actions -->
      <div class="flex flex-wrap items-center gap-2 pt-3 md:pt-0 border-t md:border-t-0 border-slate-100 shrink-0">
        <router-link to="/schedules">
          <Button variant="outline" size="sm">
            <ArrowLeft class="w-3.5 h-3.5" />
            <span class="hidden sm:inline">Daftar Jadwal</span>
          </Button>
        </router-link>

        <router-link v-if="can('schedules.update')" :to="`/schedules/${schedule.id}/edit`">
          <Button variant="outline" size="sm">
            <Edit3 class="w-3.5 h-3.5 text-slate-500" />
            <span>Edit Jadwal</span>
          </Button>
        </router-link>

        <Button
          v-if="can('schedules.delete')"
          variant="ghost"
          size="sm"
          class="text-rose-600 hover:bg-rose-50"
          @click="emit('delete')"
        >
          <Trash2 class="w-3.5 h-3.5" />
        </Button>
      </div>
    </div>
  </div>
</template>
