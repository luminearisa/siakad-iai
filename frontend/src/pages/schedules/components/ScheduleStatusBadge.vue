<script setup lang="ts">
import { computed } from 'vue'
import Badge from '@/components/ui/Badge.vue'
import type { ScheduleStatus } from '@/types/schedule'

interface Props {
  status: ScheduleStatus | string
  size?: 'xs' | 'sm' | 'md'
}

const props = withDefaults(defineProps<Props>(), {
  size: 'sm',
})

const badgeConfig = computed(() => {
  switch (props.status) {
    case 'active':
      return { label: 'Aktif Berjalan', variant: 'success' as const }
    case 'cancelled':
      return { label: 'Dibatalkan', variant: 'danger' as const }
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
