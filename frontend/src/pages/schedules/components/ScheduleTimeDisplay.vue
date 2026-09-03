<script setup lang="ts">
import { computed } from 'vue'
import { Clock } from 'lucide-vue-next'
import type { DayOfWeek } from '@/types/schedule'

interface Props {
  dayOfWeek?: DayOfWeek | string
  startTime: string
  endTime: string
  showIcon?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  showIcon: false,
})

const dayLabelMap: Record<string, string> = {
  monday: 'Senin',
  tuesday: 'Selasa',
  wednesday: 'Rabu',
  thursday: 'Kamis',
  friday: 'Jumat',
  saturday: 'Sabtu',
  sunday: 'Minggu',
}

const formattedDay = computed(() => {
  if (!props.dayOfWeek) return ''
  return dayLabelMap[props.dayOfWeek.toLowerCase()] || props.dayOfWeek
})

function formatTimeOnly(timeStr: string): string {
  if (!timeStr) return ''
  return timeStr.slice(0, 5)
}
</script>

<template>
  <div class="inline-flex items-center gap-1.5 font-mono text-xs text-slate-800 font-medium">
    <Clock v-if="showIcon" class="w-3.5 h-3.5 text-slate-400 shrink-0" />
    <span v-if="formattedDay" class="font-sans font-bold text-brand-900 bg-brand-50 px-1.5 py-0.5 rounded text-2xs uppercase tracking-wider">
      {{ formattedDay }}
    </span>
    <span>{{ formatTimeOnly(startTime) }} – {{ formatTimeOnly(endTime) }}</span>
  </div>
</template>
