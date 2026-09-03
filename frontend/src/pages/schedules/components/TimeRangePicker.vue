<script setup lang="ts">
import { computed } from 'vue'
import Input from '@/components/ui/Input.vue'
import FormField from '@/components/form/FormField.vue'

interface Props {
  startTime: string
  endTime: string
  disabled?: boolean
  error?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  disabled: false,
  error: null,
})

const emit = defineEmits<{
  (e: 'update:startTime', val: string): void
  (e: 'update:endTime', val: string): void
  (e: 'change'): void
}>()

const presets = [
  { label: 'Sesi 1 (08:00–09:40)', start: '08:00', end: '09:40' },
  { label: 'Sesi 2 (10:00–11:40)', start: '10:00', end: '11:40' },
  { label: 'Sesi 3 (13:00–14:40)', start: '13:00', end: '14:40' },
  { label: 'Sesi 4 (15:00–16:40)', start: '15:00', end: '16:40' },
  { label: 'Sesi 5 (16:45–18:25)', start: '16:45', end: '18:25' },
]

const durationMinutes = computed(() => {
  if (!props.startTime || !props.endTime) return null
  const [sh, sm] = props.startTime.split(':').map(Number)
  const [eh, em] = props.endTime.split(':').map(Number)
  if (isNaN(sh) || isNaN(sm) || isNaN(eh) || isNaN(em)) return null
  const startTotal = sh * 60 + sm
  const endTotal = eh * 60 + em
  const diff = endTotal - startTotal
  return diff > 0 ? diff : null
})

function applyPreset(p: { start: string; end: string }) {
  if (props.disabled) return
  emit('update:startTime', p.start)
  emit('update:endTime', p.end)
  emit('change')
}
</script>

<template>
  <div class="space-y-3">
    <!-- Manual Time Pickers -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
      <FormField label="Jam Mulai Kuliah" required>
        <Input
          :model-value="startTime"
          type="time"
          required
          :disabled="disabled"
          @update:model-value="emit('update:startTime', $event as string); emit('change')"
        />
      </FormField>

      <FormField label="Jam Selesai Kuliah" required>
        <Input
          :model-value="endTime"
          type="time"
          required
          :disabled="disabled"
          @update:model-value="emit('update:endTime', $event as string); emit('change')"
        />
      </FormField>
    </div>

    <!-- Duration & Preset Shortcuts -->
    <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
      <div class="flex flex-wrap items-center gap-1.5">
        <span class="text-2xs font-semibold text-slate-500 mr-1">Slot Standar:</span>
        <button
          v-for="(p, idx) in presets"
          :key="idx"
          type="button"
          :disabled="disabled"
          class="text-2xs font-mono px-2 py-0.5 rounded bg-slate-100 text-slate-700 hover:bg-brand-50 hover:text-brand-900 transition-colors border border-slate-200 cursor-pointer disabled:opacity-50"
          @click="applyPreset(p)"
        >
          {{ p.start }}–{{ p.end }}
        </button>
      </div>

      <span v-if="durationMinutes" class="font-mono text-2xs font-bold text-slate-600 bg-slate-50 px-2 py-0.5 rounded border border-slate-200">
        Durasi: {{ durationMinutes }} Menit ({{ (durationMinutes / 50).toFixed(1) }} SKS tatap muka)
      </span>
    </div>

    <p v-if="error" class="text-xs text-rose-600 font-medium">
      {{ error }}
    </p>
  </div>
</template>
