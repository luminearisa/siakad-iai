<script setup lang="ts">
import { computed } from 'vue'
import Badge from '@/components/ui/Badge.vue'

interface Props {
  type?: string | null
  size?: 'xs' | 'sm' | 'md'
}

const props = withDefaults(defineProps<Props>(), {
  type: 'classroom',
  size: 'sm',
})

const badgeConfig = computed(() => {
  const t = (props.type || '').toLowerCase()
  if (t.includes('lab') || t === 'laboratory') {
    return { label: 'Laboratorium', variant: 'info' as const }
  }
  if (t.includes('audit') || t === 'auditorium' || t.includes('aula')) {
    return { label: 'Auditorium / Aula', variant: 'warning' as const }
  }
  if (t.includes('class') || t === 'classroom' || t.includes('teori') || t.includes('kuliah')) {
    return { label: 'Ruang Kelas Teori', variant: 'primary' as const }
  }
  return { label: props.type || 'Ruangan', variant: 'neutral' as const }
})
</script>

<template>
  <Badge :variant="badgeConfig.variant" :size="size">
    {{ badgeConfig.label }}
  </Badge>
</template>
