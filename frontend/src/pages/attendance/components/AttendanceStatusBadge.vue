<script setup lang="ts">
import { computed } from 'vue'
import {
  ATTENDANCE_STATUS_LABELS,
  UNRECORDED_LABEL,
  type AttendanceSheetStatus,
} from '@/types/attendance'
import Badge from '@/components/ui/Badge.vue'

/**
 * `status` may be null: the backend sends null for a meeting that has not been
 * recorded yet, and the chip has to say so instead of borrowing a real status.
 */
const props = defineProps<{
  status: AttendanceSheetStatus | string | null | undefined
  size?: 'sm' | 'md'
  showCode?: boolean
}>()

const badgeConfig = computed(() => {
  switch (props.status) {
    case 'present':
    case 'permit':
    case 'sick':
    case 'absent':
      return {
        variant:
          props.status === 'present'
            ? ('success' as const)
            : props.status === 'permit'
              ? ('info' as const)
              : props.status === 'sick'
                ? ('warning' as const)
                : ('danger' as const),
        label: ATTENDANCE_STATUS_LABELS[props.status],
        code: props.status === 'present' ? 'H' : props.status === 'permit' ? 'I' : props.status === 'sick' ? 'S' : 'A',
      }
    case null:
    case undefined:
    case '':
      return { variant: 'neutral' as const, label: UNRECORDED_LABEL, code: '-' }
    default:
      return { variant: 'neutral' as const, label: String(props.status), code: '-' }
  }
})
</script>

<template>
  <Badge :variant="badgeConfig.variant" :size="size || 'sm'" :data-status="status ?? 'unrecorded'">
    <span v-if="showCode" class="font-bold mr-0.5">[{{ badgeConfig.code }}]</span>
    <span data-test="attendance-status-label">{{ badgeConfig.label }}</span>
  </Badge>
</template>
