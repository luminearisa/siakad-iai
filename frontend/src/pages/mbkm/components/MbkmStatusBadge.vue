<script setup lang="ts">
import Badge from '@/components/ui/Badge.vue'

interface Props {
  value?: string | null
  /** Map of status value -> human label (from types/mbkm.ts). */
  labels: Record<string, string>
  /** Map of status value -> badge variant (from types/mbkm.ts). */
  variants?: Record<string, string>
}

const props = withDefaults(defineProps<Props>(), {
  value: null,
  variants: undefined,
})
</script>

<template>
  <Badge :variant="(props.variants?.[props.value ?? ''] ?? 'neutral') as any">
    {{ props.labels[props.value ?? ''] ?? props.value ?? '-' }}
  </Badge>
</template>
