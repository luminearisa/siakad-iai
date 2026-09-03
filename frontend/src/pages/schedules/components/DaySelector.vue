<script setup lang="ts">
import type { DayOfWeek } from '@/types/schedule'

interface Props {
  modelValue: DayOfWeek | string
  disabled?: boolean
  includeSunday?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  disabled: false,
  includeSunday: false,
})

const emit = defineEmits<{
  (e: 'update:modelValue', val: DayOfWeek): void
  (e: 'change', val: DayOfWeek): void
}>()

const days: { id: DayOfWeek; label: string; short: string }[] = [
  { id: 'monday', label: 'Senin', short: 'Sen' },
  { id: 'tuesday', label: 'Selasa', short: 'Sel' },
  { id: 'wednesday', label: 'Rabu', short: 'Rab' },
  { id: 'thursday', label: 'Kamis', short: 'Kam' },
  { id: 'friday', label: 'Jumat', short: 'Jum' },
  { id: 'saturday', label: 'Sabtu', short: 'Sab' },
]

if (props.includeSunday) {
  days.push({ id: 'sunday', label: 'Minggu', short: 'Min' })
}

function selectDay(day: DayOfWeek) {
  if (props.disabled) return
  emit('update:modelValue', day)
  emit('change', day)
}
</script>

<template>
  <div class="grid grid-cols-3 sm:grid-cols-6 gap-1.5 p-1 bg-slate-100/80 rounded-lg border border-slate-200">
    <button
      v-for="d in days"
      :key="d.id"
      type="button"
      :disabled="disabled"
      :class="[
        'py-2 px-2.5 rounded-md text-xs font-semibold transition-all text-center cursor-pointer disabled:cursor-not-allowed disabled:opacity-50',
        modelValue === d.id
          ? 'bg-brand-900 text-white shadow-subtle'
          : 'text-slate-700 hover:text-slate-900 hover:bg-white/70',
      ]"
      @click="selectDay(d.id)"
    >
      <span class="sm:hidden">{{ d.short }}</span>
      <span class="hidden sm:inline">{{ d.label }}</span>
    </button>
  </div>
</template>
