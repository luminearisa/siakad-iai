<script setup lang="ts">
import { computed } from 'vue'
import Badge from '@/components/ui/Badge.vue'
import type { AdvisorStatus } from '@/types/advising'

const props = defineProps<{
  status?: AdvisorStatus | string | null
}>()

const badgeConfig = computed(() => {
  switch (props.status) {
    case 'active':
      return { variant: 'success' as const, label: 'Aktif Menjabat' }
    case 'inactive':
      return { variant: 'neutral' as const, label: 'Non-Aktif' }
    case 'transferred':
      return { variant: 'warning' as const, label: 'Dialihkan' }
    case 'completed':
      return { variant: 'info' as const, label: 'Selesai' }
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
