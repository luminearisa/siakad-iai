<script setup lang="ts">
import { computed } from 'vue'
import Badge from '@/components/ui/Badge.vue'
import type { RoomStatus } from '@/types/room'

interface Props {
  status: RoomStatus | string
  size?: 'xs' | 'sm' | 'md'
}

const props = withDefaults(defineProps<Props>(), {
  size: 'sm',
})

const badgeConfig = computed(() => {
  switch (props.status) {
    case 'active':
      return { label: 'Tersedia (Aktif)', variant: 'success' as const }
    case 'maintenance':
      return { label: 'Pemeliharaan', variant: 'warning' as const }
    case 'inactive':
      return { label: 'Non-Aktif', variant: 'neutral' as const }
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
