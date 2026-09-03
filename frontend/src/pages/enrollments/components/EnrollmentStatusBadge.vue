<script setup lang="ts">
import { computed } from 'vue'
import Badge from '@/components/ui/Badge.vue'
import type { EnrollmentStatus } from '@/types/enrollment'

interface Props {
  status: EnrollmentStatus | string
  size?: 'xs' | 'sm' | 'md'
}

const props = withDefaults(defineProps<Props>(), {
  size: 'sm',
})

const badgeConfig = computed(() => {
  switch (props.status) {
    case 'draft':
      return { label: 'Draft (Rancangan)', variant: 'neutral' as const }
    case 'submitted':
      return { label: 'Menunggu Persetujuan (Submitted)', variant: 'info' as const }
    case 'approved':
      return { label: 'Disetujui (Approved)', variant: 'success' as const }
    case 'revision_required':
      return { label: 'Perlu Revisi (Revision)', variant: 'warning' as const }
    case 'rejected':
      return { label: 'Ditolak (Rejected)', variant: 'danger' as const }
    case 'locked':
      return { label: 'Terkunci (Locked)', variant: 'neutral' as const }
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
