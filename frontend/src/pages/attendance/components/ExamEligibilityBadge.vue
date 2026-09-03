<script setup lang="ts">
import { computed } from 'vue'
import Badge from '@/components/ui/Badge.vue'

const props = defineProps<{
  eligible: boolean
  percentage?: number
  size?: 'sm' | 'md'
}>()

const badgeConfig = computed(() => {
  if (props.eligible) {
    return {
      variant: 'success' as const,
      label: props.percentage !== undefined ? `Layak Ujian (${props.percentage}%)` : 'Layak Ujian (≥75%)',
    }
  }
  return {
    variant: 'danger' as const,
    label: props.percentage !== undefined ? `Tidak Layak (${props.percentage}%)` : 'Peringatan (<75%)',
  }
})
</script>

<template>
  <Badge :variant="badgeConfig.variant" :size="size || 'sm'">
    {{ badgeConfig.label }}
  </Badge>
</template>
