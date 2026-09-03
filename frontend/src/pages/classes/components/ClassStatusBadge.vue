<script setup lang="ts">
import { computed } from 'vue'
import Badge from '@/components/ui/Badge.vue'
import type { ClassStatus } from '@/types/class'

interface Props {
  status: ClassStatus | string
  size?: 'xs' | 'sm' | 'md'
}

const props = withDefaults(defineProps<Props>(), {
  size: 'sm',
})

const badgeConfig = computed(() => {
  switch (props.status) {
    case 'draft':
      return { label: 'Draft', variant: 'neutral' as const }
    case 'open':
      return { label: 'Buka (Open)', variant: 'success' as const }
    case 'closed':
      return { label: 'Ditutup (Closed)', variant: 'warning' as const }
    case 'cancelled':
      return { label: 'Dibatalkan', variant: 'danger' as const }
    case 'completed':
      return { label: 'Selesai Perkuliahan', variant: 'info' as const }
    default:
      return { label: props.status, variant: 'neutral' as const }
  }
})
</script>

<template>
  <Badge :variant="badgeConfig.variant" :size="size" dot>
    {{ badgeConfig.label }}
  </Badge>
</template>
