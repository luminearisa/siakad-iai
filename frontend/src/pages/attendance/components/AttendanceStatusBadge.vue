<script setup lang="ts">
import { computed } from 'vue'
import type { AttendanceStatusCode } from '@/types/attendance'
import Badge from '@/components/ui/Badge.vue'

const props = defineProps<{
  status: AttendanceStatusCode | string
  size?: 'sm' | 'md'
  showCode?: boolean
}>()

const badgeConfig = computed(() => {
  switch (props.status) {
    case 'present':
      return { variant: 'success' as const, label: 'Hadir', code: 'H' }
    case 'permit':
      return { variant: 'info' as const, label: 'Izin', code: 'I' }
    case 'sick':
      return { variant: 'warning' as const, label: 'Sakit', code: 'S' }
    case 'absent':
      return { variant: 'danger' as const, label: 'Alpa', code: 'A' }
    default:
      return { variant: 'neutral' as const, label: props.status || '-', code: '-' }
  }
})
</script>

<template>
  <Badge :variant="badgeConfig.variant" :size="size || 'sm'">
    <span v-if="showCode" class="font-bold mr-0.5">[{{ badgeConfig.code }}]</span>
    <span>{{ badgeConfig.label }}</span>
  </Badge>
</template>
