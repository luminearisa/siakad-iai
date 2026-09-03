<script setup lang="ts">
import { computed } from 'vue'
import { Plus, Check, MapPin, Users, Clock } from 'lucide-vue-next'
import type { AcademicClass } from '@/types/class'
import type { StudentEnrollment } from '@/types/enrollment'
import Button from '@/components/ui/Button.vue'
import ClassCapacityBadge from '@/pages/classes/components/ClassCapacityBadge.vue'

interface Props {
  classes: AcademicClass[]
  enrollment: StudentEnrollment
  loading?: boolean
  submittingClassId?: number | null
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
  submittingClassId: null,
})

const emit = defineEmits<{
  (e: 'add-class', classItem: AcademicClass): void
}>()

const enrolledClassIds = computed(() => {
  return (props.enrollment.items || []).map((i) => i.class_id)
})

const enrolledCourseIds = computed(() => {
  return (props.enrollment.items || []).map((i) => i.course_id)
})

function formatTime(timeStr?: string): string {
  if (!timeStr) return ''
  return timeStr.slice(0, 5)
}

function getDayLabel(day?: string): string {
  if (!day) return ''
  const map: Record<string, string> = {
    monday: 'Senin',
    tuesday: 'Selasa',
    wednesday: 'Rabu',
    thursday: 'Kamis',
    friday: 'Jumat',
    saturday: 'Sabtu',
    sunday: 'Minggu',
  }
  return map[day.toLowerCase()] || day
}
</script>

<template>
  <div class="space-y-3">
    <!-- Desktop Table -->
    <div class="hidden md:block overflow-x-auto border border-slate-200 rounded-lg shadow-subtle bg-white">
      <table class="w-full text-left text-xs border-collapse">
        <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold uppercase tracking-wider text-3xs">
          <tr>
            <th class="py-2.5 px-3">Mata Kuliah</th>
            <th class="py-2.5 px-2 text-center w-16">SKS</th>
            <th class="py-2.5 px-2 text-center w-16">Seksi</th>
            <th class="py-2.5 px-3">Dosen Pengampu</th>
            <th class="py-2.5 px-3">Hari & Waktu</th>
            <th class="py-2.5 px-3">Ruangan</th>
            <th class="py-2.5 px-3 text-center w-36">Kapasitas</th>
            <th class="py-2.5 px-3 text-right w-28">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr
            v-for="c in classes"
            :key="c.id"
            :class="[
              'hover:bg-slate-50/70 transition-colors',
              enrolledClassIds.includes(c.id) ? 'bg-emerald-50/40' : '',
            ]"
          >
            <!-- Mata Kuliah -->
            <td class="py-2.5 px-3">
              <span class="font-bold text-slate-900 block">{{ c.course?.name || c.name }}</span>
              <span class="font-mono text-3xs text-slate-500">{{ c.course?.code || c.code }}</span>
            </td>

            <!-- SKS -->
            <td class="py-2.5 px-2 text-center font-mono font-bold text-slate-800">
              {{ c.course?.credits || 0 }}
            </td>

            <!-- Seksi -->
            <td class="py-2.5 px-2 text-center">
              <span class="font-bold text-brand-900 bg-brand-50 px-1.5 py-0.2 rounded border border-brand-200 text-3xs">
                {{ c.section }}
              </span>
            </td>

            <!-- Dosen Pengampu -->
            <td class="py-2.5 px-3">
              <div v-if="c.lecturers && c.lecturers.length > 0" class="flex items-center gap-1 text-slate-700 truncate max-w-[180px]">
                <Users class="w-3 h-3 text-slate-400 shrink-0" />
                <span class="truncate">{{ c.lecturers[0].full_name }}</span>
              </div>
              <span v-else class="text-slate-400 italic text-3xs">Belum ada dosen</span>
            </td>

            <!-- Hari & Waktu -->
            <td class="py-2.5 px-3">
              <div v-if="c.schedules && c.schedules.length > 0" class="flex items-center gap-1 font-mono text-3xs text-slate-700">
                <Clock class="w-3 h-3 text-slate-400 shrink-0" />
                <span class="font-sans font-bold text-brand-900">{{ getDayLabel(c.schedules[0].day_of_week) }}</span>
                <span>{{ formatTime(c.schedules[0].start_time) }}–{{ formatTime(c.schedules[0].end_time) }}</span>
              </div>
              <span v-else class="text-slate-400 italic text-3xs">Jadwal TBD</span>
            </td>

            <!-- Ruangan -->
            <td class="py-2.5 px-3">
              <div v-if="c.schedules && c.schedules.length > 0 && c.schedules[0].room" class="flex items-center gap-1 text-slate-700 text-3xs">
                <MapPin class="w-3 h-3 text-slate-400 shrink-0" />
                <span>{{ c.schedules[0].room.code }}</span>
              </div>
              <span v-else class="text-slate-400 italic text-3xs">-</span>
            </td>

            <!-- Kapasitas Kursi -->
            <td class="py-2.5 px-3 text-center">
              <ClassCapacityBadge
                :capacity="c.capacity"
                :enrolled="c.enrolled_count || 0"
                size="xs"
              />
            </td>

            <!-- Aksi Ambil -->
            <td class="py-2.5 px-3 text-right">
              <!-- Case 1: Sudah Diambil -->
              <span
                v-if="enrolledClassIds.includes(c.id)"
                class="inline-flex items-center gap-1 font-bold text-emerald-700 bg-emerald-50 px-2 py-1 rounded text-3xs border border-emerald-200"
              >
                <Check class="w-3 h-3" />
                <span>Diambil</span>
              </span>

              <!-- Case 2: Mata Kuliah Sama Sudah Diambil di Kelas Lain -->
              <span
                v-else-if="enrolledCourseIds.includes(c.course_id)"
                class="inline-flex items-center gap-1 font-medium text-slate-400 bg-slate-100 px-2 py-1 rounded text-3xs"
                title="Mata kuliah ini sudah diambil pada kelas lain"
              >
                MK Terambil
              </span>

              <!-- Case 3: Kuota Kelas Penuh -->
              <span
                v-else-if="(c.enrolled_count || 0) >= c.capacity"
                class="inline-flex items-center gap-1 font-medium text-rose-600 bg-rose-50 px-2 py-1 rounded text-3xs border border-rose-200"
              >
                Penuh
              </span>

              <!-- Case 4: Tombol Ambil Kelas -->
              <Button
                v-else
                variant="primary"
                size="xs"
                :loading="submittingClassId === c.id"
                :disabled="loading || submittingClassId !== null"
                @click="emit('add-class', c)"
              >
                <Plus class="w-3 h-3" />
                <span>Ambil</span>
              </Button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Mobile Cards -->
    <div class="md:hidden space-y-2.5">
      <div
        v-for="c in classes"
        :key="c.id"
        :class="[
          'bg-white border rounded-lg p-3 shadow-subtle space-y-2 text-xs',
          enrolledClassIds.includes(c.id) ? 'border-emerald-300 bg-emerald-50/20' : 'border-slate-200',
        ]"
      >
        <div class="flex items-start justify-between gap-2">
          <div>
            <h5 class="font-bold text-slate-900">{{ c.course?.name || c.name }}</h5>
            <div class="flex items-center gap-1.5 text-2xs text-slate-500 font-mono">
              <span>{{ c.course?.code || c.code }}</span>
              <span>•</span>
              <span class="font-bold text-brand-900">{{ c.course?.credits || 0 }} SKS</span>
              <span>•</span>
              <span class="font-bold text-slate-700 bg-slate-100 px-1 rounded">Kls {{ c.section }}</span>
            </div>
          </div>

          <ClassCapacityBadge
            :capacity="c.capacity"
            :enrolled="c.enrolled_count || 0"
            size="xs"
          />
        </div>

        <div class="grid grid-cols-2 gap-1.5 text-3xs text-slate-600 pt-1.5 border-t border-slate-100">
          <div v-if="c.schedules && c.schedules.length > 0" class="flex items-center gap-1">
            <Clock class="w-3 h-3 text-slate-400 shrink-0" />
            <span>{{ getDayLabel(c.schedules[0].day_of_week) }} {{ formatTime(c.schedules[0].start_time) }}–{{ formatTime(c.schedules[0].end_time) }}</span>
          </div>

          <div v-if="c.schedules && c.schedules.length > 0 && c.schedules[0].room" class="flex items-center gap-1">
            <MapPin class="w-3 h-3 text-slate-400 shrink-0" />
            <span>{{ c.schedules[0].room.code }}</span>
          </div>
        </div>

        <div class="pt-1.5 flex justify-end">
          <span
            v-if="enrolledClassIds.includes(c.id)"
            class="inline-flex items-center gap-1 font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded text-2xs border border-emerald-200"
          >
            <Check class="w-3 h-3" />
            <span>Sudah Diambil</span>
          </span>
          <Button
            v-else
            variant="primary"
            size="xs"
            class="w-full sm:w-auto"
            :loading="submittingClassId === c.id"
            :disabled="loading || (c.enrolled_count || 0) >= c.capacity"
            @click="emit('add-class', c)"
          >
            <Plus class="w-3 h-3" />
            <span>Ambil Kelas</span>
          </Button>
        </div>
      </div>
    </div>
  </div>
</template>
