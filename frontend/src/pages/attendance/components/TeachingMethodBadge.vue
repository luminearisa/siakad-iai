<script setup lang="ts">
import { computed } from 'vue'
import type { TeachingMethodType } from '@/types/attendance'
import Badge from '@/components/ui/Badge.vue'

const props = defineProps<{
  method: TeachingMethodType | string
  size?: 'sm' | 'md'
}>()

const config = computed(() => {
  switch (props.method) {
    case 'offline':
      return { variant: 'neutral' as const, label: 'Luring (Tatap Muka)' }
    case 'online':
      return { variant: 'info' as const, label: 'Daring (Online)' }
    case 'hybrid':
      return { variant: 'primary' as const, label: 'Hybrid' }
    default:
      return { variant: 'neutral' as const, label: props.method || '-' }
  }
})
</script>

<template>
  <Badge :variant="config.variant" :size="size || 'sm'">
    {{ config.label }}
  </Badge>
</template>
