<script setup lang="ts">
import { computed } from 'vue'
import Badge from '@/components/ui/Badge.vue'
import type { StudentStatus } from '@/types/student'

interface Props {
  status: StudentStatus | string
  size?: 'xs' | 'sm' | 'md'
}

const props = withDefaults(defineProps<Props>(), {
  size: 'sm',
})

const badgeConfig = computed(() => {
  switch (props.status) {
    case 'active':
      return { label: 'Aktif', variant: 'success' as const }
    case 'prospective':
      return { label: 'Calon Mahasiswa', variant: 'info' as const }
    case 'leave':
      return { label: 'Cuti', variant: 'warning' as const }
    case 'inactive':
      return { label: 'Non-Aktif', variant: 'neutral' as const }
    case 'graduated':
      return { label: 'Lulus', variant: 'primary' as const }
    case 'withdrawn':
      return { label: 'Mengundurkan Diri', variant: 'danger' as const }
    case 'dismissed':
      return { label: 'Dikeluarkan (DO)', variant: 'danger' as const }
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
