<script setup lang="ts">
import { computed } from 'vue'
import Badge from '@/components/ui/Badge.vue'
import type { CourseType } from '@/types/course'

interface Props {
  type: CourseType | string
  size?: 'xs' | 'sm' | 'md'
}

const props = withDefaults(defineProps<Props>(), {
  size: 'sm',
})

const badgeConfig = computed(() => {
  switch (props.type) {
    case 'theory':
      return { label: 'Teori', variant: 'primary' as const }
    case 'practical':
      return { label: 'Praktikum', variant: 'warning' as const }
    case 'mixed':
      return { label: 'Teori & Praktikum', variant: 'success' as const }
    default:
      return { label: props.type, variant: 'neutral' as const }
  }
})
</script>

<template>
  <Badge :variant="badgeConfig.variant" :size="size">
    {{ badgeConfig.label }}
  </Badge>
</template>
