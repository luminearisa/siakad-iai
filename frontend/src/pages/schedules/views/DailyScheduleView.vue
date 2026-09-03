<script setup lang="ts">
import { ref, computed } from 'vue'
import { Calendar, MapPin, Users, Clock } from 'lucide-vue-next'
import type { ClassSchedule, DayOfWeek } from '@/types/schedule'
import DaySelector from '../components/DaySelector.vue'
import EmptyState from '@/components/ui/EmptyState.vue'

interface Props {
  schedules: ClassSchedule[]
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
})

const selectedDay = ref<DayOfWeek>('monday')

const daySchedules = computed(() => {
  return props.schedules
    .filter((s) => s.day_of_week?.toLowerCase() === selectedDay.value.toLowerCase())
    .sort((a, b) => (a.start_time || '').localeCompare(b.start_time || ''))
})

function formatTimeOnly(timeStr: string): string {
  if (!timeStr) return ''
  return timeStr.slice(0, 5)
}
</script>

<template>
  <div class="space-y-4">
    <!-- Day Selector Navigation -->
    <DaySelector
      v-model="selectedDay"
    />

    <!-- Schedules List for Selected Day -->
    <div v-if="daySchedules.length > 0" class="space-y-3">
      <div
        v-for="item in daySchedules"
        :key="item.id"
        class="bg-white border border-slate-200 hover:border-brand-300 rounded-lg p-4 shadow-subtle hover:shadow-sm transition-all"
      >
        <router-link :to="`/schedules/${item.id}`" class="block space-y-2">
          <!-- Top Row: Time & Section -->
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-1.5 font-mono font-bold text-xs text-brand-900 bg-brand-50 px-2 py-0.5 rounded border border-brand-200">
              <Clock class="w-3.5 h-3.5 text-brand-700" />
              <span>{{ formatTimeOnly(item.start_time) }} – {{ formatTimeOnly(item.end_time) }} WIB</span>
            </div>

            <span class="font-bold text-xs text-slate-800 bg-slate-100 px-2 py-0.5 rounded">
              Kelas {{ item.academic_class?.section || 'A' }}
            </span>
          </div>

          <!-- Course Name -->
          <div>
            <h4 class="text-sm font-bold text-slate-900 hover:text-brand-900 transition-colors">
              {{ item.academic_class?.course?.name || item.academic_class?.name }}
            </h4>
            <span class="text-2xs font-mono text-slate-500">
              {{ item.academic_class?.course?.code }} · {{ item.academic_class?.course?.credits || 0 }} SKS · {{ item.academic_class?.semester?.name }}
            </span>
          </div>

          <!-- Room & Lecturer Grid -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-2 border-t border-slate-100 text-xs text-slate-600">
            <div v-if="item.room" class="flex items-center gap-1.5 font-medium text-slate-700">
              <MapPin class="w-3.5 h-3.5 text-slate-400 shrink-0" />
              <span>{{ item.room.code }} ({{ item.room.name }})</span>
            </div>
            <div v-else class="text-slate-400 italic">
              Ruangan belum ditentukan
            </div>

            <div v-if="item.academic_class?.lecturers && item.academic_class.lecturers.length > 0" class="flex items-center gap-1.5">
              <Users class="w-3.5 h-3.5 text-slate-400 shrink-0" />
              <span>{{ item.academic_class.lecturers.map(l => l.full_name).join(', ') }}</span>
            </div>
          </div>
        </router-link>
      </div>
    </div>

    <!-- Empty State -->
    <EmptyState
      v-else
      :icon="Calendar"
      title="Tidak Ada Jadwal Perkuliahan"
      description="Tidak ada kelas perkuliahan yang terjadwal pada hari ini."
    />
  </div>
</template>
