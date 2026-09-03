<script setup lang="ts">
import { computed } from 'vue'
import { AlertCircle, AlertTriangle, CheckCircle2, Info, X } from 'lucide-vue-next'

interface Props {
  variant?: 'info' | 'success' | 'warning' | 'danger'
  title?: string
  dismissible?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'info',
  dismissible: false,
})

const emit = defineEmits<{
  (e: 'dismiss'): void
}>()

const iconComponent = computed(() => {
  switch (props.variant) {
    case 'success':
      return CheckCircle2
    case 'warning':
      return AlertTriangle
    case 'danger':
      return AlertCircle
    case 'info':
    default:
      return Info
  }
})

const variantClasses = computed(() => {
  switch (props.variant) {
    case 'success':
      return 'bg-emerald-50 border-emerald-200 text-emerald-900'
    case 'warning':
      return 'bg-amber-50 border-amber-200 text-amber-900'
    case 'danger':
      return 'bg-rose-50 border-rose-200 text-rose-900'
    case 'info':
    default:
      return 'bg-sky-50 border-sky-200 text-sky-900'
  }
})

const iconClasses = computed(() => {
  switch (props.variant) {
    case 'success':
      return 'text-emerald-600'
    case 'warning':
      return 'text-amber-600'
    case 'danger':
      return 'text-rose-600'
    case 'info':
    default:
      return 'text-sky-600'
  }
})
</script>

<template>
  <div :class="['flex items-start gap-2.5 p-3 rounded-lg border text-xs', variantClasses]">
    <component :is="iconComponent" :class="['w-4 h-4 shrink-0 mt-0.5', iconClasses]" />
    <div class="flex-1">
      <h5 v-if="title" class="font-semibold text-xs mb-0.5">{{ title }}</h5>
      <div class="leading-relaxed">
        <slot />
      </div>
    </div>
    <button
      v-if="dismissible"
      type="button"
      class="text-slate-400 hover:text-slate-700 p-0.5 rounded-xs transition-colors shrink-0"
      @click="emit('dismiss')"
    >
      <X class="w-3.5 h-3.5" />
    </button>
  </div>
</template>
