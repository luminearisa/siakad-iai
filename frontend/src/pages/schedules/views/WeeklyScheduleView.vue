<script setup lang="ts">
import { computed } from 'vue'
import { Calendar, MapPin, Users, Clock } from 'lucide-vue-next'
import type { ClassSchedule, DayOfWeek } from '@/types/schedule'

interface Props {
  schedules: ClassSchedule[]
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
})

const days: { id: DayOfWeek; label: string; short: string }[] = [
  { id: 'monday', label: 'Senin', short: 'Sen' },
  { id: 'tuesday', label: 'Selasa', short: 'Sel' },
  { id: 'wednesday', label: 'Rabu', short: 'Rab' },
  { id: 'thursday', label: 'Kamis', short: 'Kam' },
  { id: 'friday', label: 'Jumat', short: 'Jum' },
  { id: 'saturday', label: 'Sabtu', short: 'Sab' },
]

const schedulesByDay = computed(() => {
  const map: Record<string, ClassSchedule[]> = {
    monday: [],
    tuesday: [],
    wednesday: [],
    thursday: [],
    friday: [],
    saturday: [],
    sunday: [],
  }

  props.schedules.forEach((s) => {
    const day = s.day_of_week?.toLowerCase() || 'monday'
    if (!map[day]) map[day] = []
    map[day].push(s)
  })

  // Sort each day's schedules by start_time
  Object.keys(map).forEach((d) => {
    map[d].sort((a, b) => (a.start_time || '').localeCompare(b.start_time || ''))
  })

  return map
})

function formatTimeOnly(timeStr: string): string {
  if (!timeStr) return ''
  return timeStr.slice(0, 5)
}
</script>

<template>
  <div class="space-y-4">
    <!-- Desktop Weekly Timetable Grid -->
    <div class="overflow-x-auto pb-4 custom-scrollbar">
      <div class="min-w-[960px] grid grid-cols-6 gap-3">
        <!-- Day Columns -->
        <div
          v-for="day in days"
          :key="day.id"
          class="flex flex-col bg-slate-50/70 border border-slate-200 rounded-lg overflow-hidden min-h-[520px]"
        >
          <!-- Column Header -->
          <div class="p-2.5 bg-white border-b border-slate-200 text-center">
            <h4 class="font-bold text-xs text-slate-900 uppercase tracking-wider">
              {{ day.label }}
            </h4>
            <span class="text-3xs font-mono font-semibold text-slate-500">
              {{ (schedulesByDay[day.id] || []).length }} Kelas
            </span>
          </div>

          <!-- Schedules on this day -->
          <div class="p-2 space-y-2 flex-1 overflow-y-auto">
            <div
              v-for="item in schedulesByDay[day.id]"
              :key="item.id"
              class="group relative bg-white border border-slate-200 hover:border-brand-300 rounded-lg p-2.5 shadow-subtle hover:shadow-sm transition-all text-left"
            >
              <router-link :to="`/schedules/${item.id}`" class="block space-y-1.5">
                <!-- Time & Section -->
                <div class="flex items-center justify-between text-2xs">
                  <span class="font-mono font-bold text-brand-900 bg-brand-50 px-1.5 py-0.2 rounded flex items-center gap-1 border border-brand-200">
                    <Clock class="w-2.5 h-2.5 text-brand-700" />
                    {{ formatTimeOnly(item.start_time) }}–{{ formatTimeOnly(item.end_time) }}
                  </span>
                  <span class="font-bold text-slate-700 bg-slate-100 px-1.5 py-0.2 rounded text-3xs">
                    Kls {{ item.academic_class?.section || 'A' }}
                  </span>
                </div>

                <!-- Course Title -->
                <div>
                  <h5 class="text-xs font-bold text-slate-900 group-hover:text-brand-900 transition-colors line-clamp-2 leading-tight">
                    {{ item.academic_class?.course?.name || item.academic_class?.name || 'Mata Kuliah' }}
                  </h5>
                  <span class="text-3xs font-mono text-slate-500 block">
                    {{ item.academic_class?.course?.code }} · {{ item.academic_class?.course?.credits || 0 }} SKS
                  </span>
                </div>

                <!-- Room & Lecturer -->
                <div class="pt-1 border-t border-slate-100 space-y-0.5 text-3xs text-slate-600">
                  <div v-if="item.room" class="flex items-center gap-1 font-medium truncate text-slate-700">
                    <MapPin class="w-3 h-3 text-slate-400 shrink-0" />
                    <span class="truncate">{{ item.room.code }} ({{ item.room.name }})</span>
                  </div>
                  <div v-else class="text-slate-400 italic">
                    Ruangan: TBD
                  </div>

                  <div v-if="item.academic_class?.lecturers && item.academic_class.lecturers.length > 0" class="flex items-center gap-1 truncate">
                    <Users class="w-3 h-3 text-slate-400 shrink-0" />
                    <span class="truncate">{{ item.academic_class.lecturers[0].full_name }}</span>
                  </div>
                </div>
              </router-link>
            </div>

            <!-- Empty day placeholder -->
            <div
              v-if="!schedulesByDay[day.id] || schedulesByDay[day.id].length === 0"
              class="h-full flex flex-col items-center justify-center p-4 text-center text-slate-400 text-2xs space-y-1 my-auto"
            >
              <Calendar class="w-6 h-6 text-slate-300 stroke-[1.5]" />
              <span>Tidak ada jadwal</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
