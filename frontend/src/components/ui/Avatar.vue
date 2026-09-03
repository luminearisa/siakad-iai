<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  name?: string
  src?: string
  size?: 'xs' | 'sm' | 'md' | 'lg'
}

const props = withDefaults(defineProps<Props>(), {
  name: '',
  size: 'sm',
})

const initials = computed(() => {
  if (!props.name) return '?'
  const parts = props.name.trim().split(/\s+/)
  if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase()
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
})

const sizeClasses = computed(() => {
  switch (props.size) {
    case 'xs':
      return 'w-6 h-6 text-2xs'
    case 'sm':
      return 'w-8 h-8 text-xs'
    case 'md':
      return 'w-10 h-10 text-sm'
    case 'lg':
      return 'w-12 h-12 text-base'
    default:
      return 'w-8 h-8 text-xs'
  }
})
</script>

<template>
  <div
    :class="[
      'inline-flex items-center justify-center font-medium bg-brand-800 text-brand-50 rounded-full select-none shrink-0 overflow-hidden ring-1 ring-slate-200',
      sizeClasses,
    ]"
  >
    <img v-if="src" :src="src" :alt="name" class="w-full h-full object-cover" />
    <span v-else>{{ initials }}</span>
  </div>
</template>
