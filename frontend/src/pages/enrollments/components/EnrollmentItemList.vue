<script setup lang="ts">
import { computed } from 'vue'
import { Trash2, MapPin, Users, Clock } from 'lucide-vue-next'
import type { StudentEnrollment, StudentEnrollmentItem } from '@/types/enrollment'
import Badge from '@/components/ui/Badge.vue'

interface Props {
  enrollment: StudentEnrollment
  items: StudentEnrollmentItem[]
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
})

const emit = defineEmits<{
  (e: 'remove-item', item: StudentEnrollmentItem): void
}>()

const isEditable = computed(() => {
  return props.enrollment.status === 'draft' || props.enrollment.status === 'revision_required'
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
            <th class="py-2.5 px-3 w-10 text-center">No</th>
            <th class="py-2.5 px-3">Mata Kuliah & Kode</th>
            <th class="py-2.5 px-2 text-center w-16">SKS</th>
            <th class="py-2.5 px-2 text-center w-16">Seksi</th>
            <th class="py-2.5 px-3">Dosen Pengampu</th>
            <th class="py-2.5 px-3">Jadwal & Ruangan</th>
            <th class="py-2.5 px-3 text-center w-28">Status</th>
            <th v-if="isEditable" class="py-2.5 px-3 text-right w-16">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr
            v-for="(item, idx) in items"
            :key="item.id"
            class="hover:bg-slate-50/70 transition-colors"
          >
            <!-- No -->
            <td class="py-2.5 px-3 text-center font-mono text-slate-400">
              {{ idx + 1 }}
            </td>

            <!-- Mata Kuliah -->
            <td class="py-2.5 px-3">
              <span class="font-bold text-slate-900 block">
                {{ item.academic_class?.course?.name || item.course?.name || item.academic_class?.name }}
              </span>
              <span class="font-mono text-3xs text-slate-500">
                {{ item.academic_class?.course?.code || item.course?.code || item.academic_class?.code }}
              </span>
            </td>

            <!-- SKS -->
            <td class="py-2.5 px-2 text-center font-mono font-bold text-slate-900">
              {{ item.credits || item.academic_class?.course?.credits || 0 }}
            </td>

            <!-- Seksi -->
            <td class="py-2.5 px-2 text-center">
              <span class="font-bold text-brand-900 bg-brand-50 px-1.5 py-0.2 rounded border border-brand-200 text-3xs">
                {{ item.academic_class?.section || 'A' }}
              </span>
            </td>

            <!-- Dosen Pengampu -->
            <td class="py-2.5 px-3">
              <div
                v-if="item.academic_class?.lecturers && item.academic_class.lecturers.length > 0"
                class="flex items-center gap-1 text-slate-700 truncate max-w-[180px]"
              >
                <Users class="w-3 h-3 text-slate-400 shrink-0" />
                <span class="truncate">{{ item.academic_class.lecturers[0].full_name }}</span>
              </div>
              <span v-else class="text-slate-400 italic text-3xs">-</span>
            </td>

            <!-- Jadwal & Ruangan -->
            <td class="py-2.5 px-3">
              <div
                v-if="item.academic_class?.schedules && item.academic_class.schedules.length > 0"
                class="space-y-0.5"
              >
                <div class="flex items-center gap-1 font-mono text-3xs text-slate-700">
                  <Clock class="w-3 h-3 text-slate-400 shrink-0" />
                  <span class="font-sans font-bold text-brand-900">
                    {{ getDayLabel(item.academic_class.schedules[0].day_of_week) }}
                  </span>
                  <span>{{ formatTime(item.academic_class.schedules[0].start_time) }}–{{ formatTime(item.academic_class.schedules[0].end_time) }}</span>
                </div>
                <div v-if="item.academic_class.schedules[0].room" class="flex items-center gap-1 text-3xs text-slate-500">
                  <MapPin class="w-3 h-3 text-slate-400 shrink-0" />
                  <span>{{ item.academic_class.schedules[0].room.code }} ({{ item.academic_class.schedules[0].room.name }})</span>
                </div>
              </div>
              <span v-else class="text-slate-400 italic text-3xs">Jadwal TBD</span>
            </td>

            <!-- Status -->
            <td class="py-2.5 px-3 text-center">
              <Badge variant="success" size="xs" dot>
                Terdaftar
              </Badge>
            </td>

            <!-- Aksi Hapus -->
            <td v-if="isEditable" class="py-2.5 px-3 text-right">
              <button
                type="button"
                :disabled="loading"
                class="p-1 rounded text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer disabled:opacity-50"
                title="Hapus dari KRS"
                @click="emit('remove-item', item)"
              >
                <Trash2 class="w-3.5 h-3.5" />
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Mobile Cards -->
    <div class="md:hidden space-y-2.5">
      <div
        v-for="(item, idx) in items"
        :key="item.id"
        class="bg-white border border-slate-200 rounded-lg p-3 shadow-subtle space-y-2 text-xs"
      >
        <div class="flex items-start justify-between gap-2">
          <div>
            <span class="text-3xs font-mono font-bold text-slate-400 block">#{{ idx + 1 }}</span>
            <h5 class="font-bold text-slate-900">
              {{ item.academic_class?.course?.name || item.course?.name || item.academic_class?.name }}
            </h5>
            <div class="flex items-center gap-1.5 text-2xs text-slate-500 font-mono">
              <span>{{ item.academic_class?.course?.code || item.course?.code }}</span>
              <span>•</span>
              <span class="font-bold text-brand-900">{{ item.credits || 0 }} SKS</span>
              <span>•</span>
              <span class="font-bold text-slate-700 bg-slate-100 px-1 rounded">Kls {{ item.academic_class?.section || 'A' }}</span>
            </div>
          </div>

          <button
            v-if="isEditable"
            type="button"
            :disabled="loading"
            class="p-1.5 rounded text-rose-500 hover:bg-rose-50"
            title="Hapus dari KRS"
            @click="emit('remove-item', item)"
          >
            <Trash2 class="w-4 h-4" />
          </button>
        </div>

        <div class="pt-1.5 border-t border-slate-100 grid grid-cols-2 gap-1.5 text-3xs text-slate-600">
          <div v-if="item.academic_class?.schedules && item.academic_class.schedules.length > 0" class="flex items-center gap-1">
            <Clock class="w-3 h-3 text-slate-400 shrink-0" />
            <span>{{ getDayLabel(item.academic_class.schedules[0].day_of_week) }} {{ formatTime(item.academic_class.schedules[0].start_time) }}–{{ formatTime(item.academic_class.schedules[0].end_time) }}</span>
          </div>

          <div v-if="item.academic_class?.schedules && item.academic_class.schedules.length > 0 && item.academic_class.schedules[0].room" class="flex items-center gap-1">
            <MapPin class="w-3 h-3 text-slate-400 shrink-0" />
            <span>{{ item.academic_class.schedules[0].room.code }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
