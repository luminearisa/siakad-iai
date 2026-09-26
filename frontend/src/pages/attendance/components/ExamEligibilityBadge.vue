<script setup lang="ts">
import { computed } from 'vue'
import Badge from '@/components/ui/Badge.vue'
import type { AttendanceThresholdStage } from '@/types/attendance'

/**
 * Exam-eligibility chip.
 *
 * Every number here comes from props: the server owns the attendance threshold
 * (`min_attendance_percentage`) and which stage it applies to (`threshold_stage`),
 * because the value is configured per semester (UTS vs UAS). Nothing in this
 * component may fall back to a hardcoded 75%.
 */
const props = defineProps<{
  /** Eligibility as computed by the server. */
  eligible: boolean
  /** Attendance percentage of this student / course. */
  percentage?: number | null
  /** Threshold the server compared `percentage` against. */
  minAttendancePercentage?: number | null
  /** Which exam the threshold gates. Drives the label only. */
  stage?: AttendanceThresholdStage
  size?: 'sm' | 'md'
}>()

function trimNumber(value: number): string {
  return Number.isInteger(value) ? String(value) : String(Number(value.toFixed(1)))
}

const stageLabel = computed<string>(() => {
  if (props.stage === 'uts') return 'UTS'
  if (props.stage === 'uas') return 'UAS'
  return 'Ujian'
})

const mainLabel = computed<string>(() => {
  const head = props.eligible ? `Layak ${stageLabel.value}` : `Tidak Layak ${stageLabel.value}`
  const percent =
    props.percentage !== undefined && props.percentage !== null
      ? ` (${trimNumber(props.percentage)}%)`
      : ''
  return `${head}${percent}`
})

const thresholdLabel = computed<string>(() =>
  props.minAttendancePercentage === undefined || props.minAttendancePercentage === null
    ? ''
    : `min ${trimNumber(props.minAttendancePercentage)}%`,
)

const badgeConfig = computed(() => ({
  variant: props.eligible ? ('success' as const) : ('danger' as const),
  label: thresholdLabel.value ? `${mainLabel.value} · ${thresholdLabel.value}` : mainLabel.value,
}))

const tooltip = computed<string>(() => {
  const percent =
    props.percentage !== undefined && props.percentage !== null ? `${trimNumber(props.percentage)}%` : '-'
  const threshold = thresholdLabel.value || 'ambang dari server'
  return `Kehadiran ${percent} dari ${threshold} untuk ${stageLabel.value}.`
})
</script>

<template>
  <span :title="tooltip" class="inline-flex">
    <Badge :variant="badgeConfig.variant" :size="size || 'sm'" data-test="exam-eligibility-badge">
      {{ badgeConfig.label }}
    </Badge>
  </span>
</template>
