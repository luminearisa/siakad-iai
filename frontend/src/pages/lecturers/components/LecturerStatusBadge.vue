<script setup lang="ts">
import { computed } from 'vue'
import Badge from '@/components/ui/Badge.vue'
import type { LecturerStatus } from '@/types/lecturer'

interface Props {
  status: LecturerStatus | string
  size?: 'xs' | 'sm' | 'md'
}

const props = withDefaults(defineProps<Props>(), {
  size: 'sm',
})

const badgeConfig = computed(() => {
  switch (props.status) {
    case 'active':
      return { label: 'Aktif Mengajar', variant: 'success' as const }
    case 'inactive':
      return { label: 'Non-Aktif', variant: 'neutral' as const }
    case 'retired':
      return { label: 'Purnatugas / Pensiun', variant: 'info' as const }
    case 'resigned':
      return { label: 'Mengundurkan Diri', variant: 'warning' as const }
    case 'deceased':
      return { label: 'Wafat', variant: 'neutral' as const }
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
