<script setup lang="ts">
import { computed } from 'vue'
import { AlertCircle, AlertTriangle, Info, CheckCircle } from 'lucide-vue-next'

const props = withDefaults(
  defineProps<{
    type?: 'danger' | 'warning' | 'info' | 'success'
    title?: string
    message: string
    compact?: boolean
  }>(),
  {
    type: 'warning',
    compact: false,
  }
)

const icon = computed(() => {
  switch (props.type) {
    case 'danger':
      return AlertCircle
    case 'warning':
      return AlertTriangle
    case 'info':
      return Info
    case 'success':
      return CheckCircle
    default:
      return AlertTriangle
  }
})

const colorStyles = computed(() => {
  switch (props.type) {
    case 'danger':
      return 'bg-rose-50 border-rose-200 text-rose-800 icon-rose'
    case 'warning':
      return 'bg-amber-50 border-amber-200 text-amber-800 icon-amber'
    case 'info':
      return 'bg-sky-50 border-sky-200 text-sky-800 icon-sky'
    case 'success':
      return 'bg-emerald-50 border-emerald-200 text-emerald-800 icon-emerald'
    default:
      return 'bg-amber-50 border-amber-200 text-amber-800'
  }
})
</script>

<template>
  <div
    :class="[
      'rounded-md border p-2.5 flex items-start gap-2 text-xs transition-colors',
      colorStyles,
      compact ? 'py-1.5 px-2 text-2xs' : '',
    ]"
  >
    <component :is="icon" class="w-4 h-4 shrink-0 mt-0.5" />
    <div class="space-y-0.5 flex-1 min-w-0">
      <div v-if="title" class="font-bold tracking-tight">
        {{ title }}
      </div>
      <p class="leading-relaxed">
        {{ message }}
      </p>
    </div>
  </div>
</template>
