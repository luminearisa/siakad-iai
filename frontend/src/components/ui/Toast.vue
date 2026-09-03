<script setup lang="ts">
import { computed } from 'vue'
import { AlertCircle, AlertTriangle, CheckCircle2, Info, X } from 'lucide-vue-next'
import type { ToastType } from '@/composables/useToast'

interface Props {
  type?: ToastType
  title?: string
  message: string
}

const props = withDefaults(defineProps<Props>(), {
  type: 'info',
})

const emit = defineEmits<{
  (e: 'close'): void
}>()

const iconComponent = computed(() => {
  switch (props.type) {
    case 'success':
      return CheckCircle2
    case 'warning':
      return AlertTriangle
    case 'error':
      return AlertCircle
    case 'info':
    default:
      return Info
  }
})

const typeClasses = computed(() => {
  switch (props.type) {
    case 'success':
      return 'border-emerald-200 bg-white text-slate-800'
    case 'warning':
      return 'border-amber-200 bg-white text-slate-800'
    case 'error':
      return 'border-rose-200 bg-white text-slate-800'
    case 'info':
    default:
      return 'border-sky-200 bg-white text-slate-800'
  }
})

const iconClasses = computed(() => {
  switch (props.type) {
    case 'success':
      return 'text-emerald-600'
    case 'warning':
      return 'text-amber-600'
    case 'error':
      return 'text-rose-600'
    case 'info':
    default:
      return 'text-sky-600'
  }
})
</script>

<template>
  <div
    :class="[
      'flex items-start gap-2.5 p-3 rounded-lg border shadow-elevated transition-all duration-200 max-w-sm w-full pointer-events-auto',
      typeClasses,
    ]"
  >
    <component :is="iconComponent" :class="['w-4 h-4 shrink-0 mt-0.5', iconClasses]" />
    <div class="flex-1 min-w-0">
      <h6 v-if="title" class="text-xs font-semibold text-slate-900 mb-0.5">{{ title }}</h6>
      <p class="text-xs text-slate-600 leading-normal break-words">{{ message }}</p>
    </div>
    <button
      type="button"
      class="text-slate-400 hover:text-slate-700 p-0.5 rounded-xs transition-colors shrink-0 cursor-pointer"
      @click="emit('close')"
    >
      <X class="w-3.5 h-3.5" />
    </button>
  </div>
</template>
