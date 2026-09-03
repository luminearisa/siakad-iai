<script setup lang="ts">
import { computed } from 'vue'
import Badge from '@/components/ui/Badge.vue'
import type { CurriculumStatus } from '@/types/curriculum'

interface Props {
  status: CurriculumStatus | string
  size?: 'xs' | 'sm' | 'md'
}

const props = withDefaults(defineProps<Props>(), {
  size: 'sm',
})

const badgeConfig = computed(() => {
  switch (props.status) {
    case 'draft':
      return { label: 'Draft', variant: 'warning' as const }
    case 'active':
      return { label: 'Aktif Berlaku', variant: 'success' as const }
    case 'inactive':
      return { label: 'Non-Aktif', variant: 'neutral' as const }
    case 'archived':
      return { label: 'Diarsipkan', variant: 'info' as const }
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
