<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  variant?: 'neutral' | 'primary' | 'success' | 'warning' | 'danger' | 'info' | 'gold' | 'glass' | 'gold-glass' | 'outline'
  size?: 'xs' | 'sm' | 'md'
  dot?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'neutral',
  size: 'sm',
  dot: false,
})

const variantClasses = computed(() => {
  switch (props.variant) {
    case 'primary':
      return 'bg-emerald-50 text-emerald-700 border-emerald-200/90'
    case 'success':
      return 'bg-emerald-50 text-emerald-700 border-emerald-200/90'
    case 'warning':
      return 'bg-amber-50 text-amber-800 border-amber-200/90'
    case 'danger':
      return 'bg-rose-50 text-rose-700 border-rose-200/90'
    case 'info':
      return 'bg-sky-50 text-sky-700 border-sky-200/90'
    case 'gold':
      return 'bg-amber-50 text-amber-800 border-amber-300'
    case 'glass':
      return 'bg-white/15 text-white border-white/30 backdrop-blur-xs shadow-2xs'
    case 'gold-glass':
      return 'bg-gold-400/20 text-gold-300 border-gold-400/40 backdrop-blur-xs shadow-2xs'
    case 'outline':
      return 'bg-transparent text-slate-700 border-slate-300'
    case 'neutral':
    default:
      return 'bg-slate-100/80 text-slate-700 border-slate-200'
  }
})

const dotClasses = computed(() => {
  switch (props.variant) {
    case 'primary':
    case 'success':
      return 'bg-emerald-500'
    case 'warning':
    case 'gold':
      return 'bg-amber-500'
    case 'danger':
      return 'bg-rose-500'
    case 'info':
      return 'bg-sky-500'
    case 'glass':
      return 'bg-white'
    case 'gold-glass':
      return 'bg-gold-400'
    default:
      return 'bg-slate-400'
  }
})

const sizeClasses = computed(() => {
  switch (props.size) {
    case 'xs':
      return 'text-3xs px-2 py-0.5 gap-1 rounded-full'
    case 'sm':
      return 'text-2xs px-2.5 py-0.5 gap-1.5 rounded-full font-medium'
    case 'md':
      return 'text-xs px-3 py-1 gap-1.5 rounded-full font-medium'
    default:
      return 'text-2xs px-2.5 py-0.5 gap-1.5 rounded-full'
  }
})
</script>

<template>
  <span
    :class="[
      'inline-flex items-center border select-none tracking-tight font-medium shadow-2xs transition-colors',
      variantClasses,
      sizeClasses,
    ]"
  >
    <span v-if="dot" :class="['w-1.5 h-1.5 rounded-full shrink-0 animate-pulse', dotClasses]" />
    <slot />
  </span>
</template>
