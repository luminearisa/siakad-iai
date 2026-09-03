<script setup lang="ts">
import { computed } from 'vue'
import Spinner from './Spinner.vue'

interface Props {
  variant?: 'primary' | 'secondary' | 'outline' | 'danger' | 'ghost' | 'link'
  size?: 'xs' | 'sm' | 'md' | 'lg'
  type?: 'button' | 'submit' | 'reset'
  disabled?: boolean
  loading?: boolean
  block?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'primary',
  size: 'sm',
  type: 'button',
  disabled: false,
  loading: false,
  block: false,
})

const variantClasses = computed(() => {
  switch (props.variant) {
    case 'primary':
      return 'bg-brand-900 text-white hover:bg-brand-800 active:bg-brand-950 focus-visible:ring-brand-500 border border-transparent shadow-subtle'
    case 'secondary':
      return 'bg-slate-100 text-slate-700 hover:bg-slate-200 active:bg-slate-300 focus-visible:ring-slate-400 border border-slate-200'
    case 'outline':
      return 'bg-white text-slate-700 hover:bg-slate-50 active:bg-slate-100 border border-slate-300 focus-visible:ring-brand-500 shadow-subtle'
    case 'danger':
      return 'bg-rose-600 text-white hover:bg-rose-700 active:bg-rose-800 focus-visible:ring-rose-500 border border-transparent shadow-subtle'
    case 'ghost':
      return 'bg-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-900 active:bg-slate-200 focus-visible:ring-slate-400 border border-transparent'
    case 'link':
      return 'bg-transparent text-brand-700 hover:text-brand-900 underline underline-offset-4 focus-visible:ring-brand-500 p-0 h-auto'
    default:
      return 'bg-brand-900 text-white hover:bg-brand-800'
  }
})

const sizeClasses = computed(() => {
  switch (props.size) {
    case 'xs':
      return 'text-2xs px-2 py-1 gap-1 rounded-xs'
    case 'sm':
      return 'text-xs px-2.5 py-1.5 gap-1.5 rounded-md font-medium'
    case 'md':
      return 'text-sm px-3.5 py-2 gap-2 rounded-md font-medium'
    case 'lg':
      return 'text-base px-4 py-2.5 gap-2.5 rounded-lg font-medium'
    default:
      return 'text-xs px-2.5 py-1.5 gap-1.5 rounded-md'
  }
})
</script>

<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    :class="[
      'inline-flex items-center justify-center transition-colors duration-150 select-none outline-none focus-visible:ring-2 focus-visible:ring-offset-1 disabled:opacity-50 disabled:cursor-not-allowed disabled:pointer-events-none cursor-pointer',
      variantClasses,
      sizeClasses,
      block ? 'w-full' : '',
    ]"
  >
    <Spinner v-if="loading" size="xs" :class="variant === 'outline' || variant === 'ghost' || variant === 'secondary' ? 'text-slate-600' : 'text-white'" />
    <slot />
  </button>
</template>
