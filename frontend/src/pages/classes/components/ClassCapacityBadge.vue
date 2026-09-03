<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  enrolled?: number
  capacity: number
}

const props = withDefaults(defineProps<Props>(), {
  enrolled: 0,
})

const percentage = computed(() => {
  if (!props.capacity || props.capacity <= 0) return 0
  return Math.min(100, Math.round((props.enrolled / props.capacity) * 100))
})

const remaining = computed(() => {
  return Math.max(0, props.capacity - props.enrolled)
})

const stateConfig = computed(() => {
  if (remaining.value === 0 && props.enrolled > 0) {
    return {
      textClass: 'text-rose-700 bg-rose-50 border-rose-200',
      label: 'Penuh',
    }
  }
  if (percentage.value >= 85) {
    return {
      textClass: 'text-amber-700 bg-amber-50 border-amber-200',
      label: `Sisa ${remaining.value}`,
    }
  }
  return {
    textClass: 'text-emerald-700 bg-emerald-50 border-emerald-200',
    label: `Sisa ${remaining.value}`,
  }
})
</script>

<template>
  <div class="inline-flex items-center gap-1.5 font-mono text-2xs px-2 py-0.5 rounded border" :class="stateConfig.textClass">
    <span class="font-bold">{{ enrolled }} / {{ capacity }}</span>
    <span class="text-3xs opacity-80">({{ stateConfig.label }})</span>
  </div>
</template>
