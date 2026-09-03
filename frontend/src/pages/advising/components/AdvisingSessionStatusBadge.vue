<script setup lang="ts">
import { computed } from 'vue'
import Badge from '@/components/ui/Badge.vue'
import type { AdvisingSessionStatus } from '@/types/advising'

const props = defineProps<{
  status?: AdvisingSessionStatus | string | null
}>()

const badgeConfig = computed(() => {
  switch (props.status) {
    case 'scheduled':
      return { variant: 'warning' as const, label: 'Dijadwalkan' }
    case 'completed':
      return { variant: 'success' as const, label: 'Selesai' }
    case 'cancelled':
      return { variant: 'danger' as const, label: 'Dibatalkan' }
    default:
      return { variant: 'neutral' as const, label: props.status || '-' }
  }
})
</script>

<template>
  <Badge :variant="badgeConfig.variant" size="xs" dot>
    {{ badgeConfig.label }}
  </Badge>
</template>
