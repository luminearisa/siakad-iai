<script setup lang="ts">
import type { Component } from 'vue'
import Card from '@/components/ui/Card.vue'

interface Props {
  label: string
  value: number | string | null | undefined
  hint?: string
  icon?: Component | null
  tone?: 'default' | 'primary' | 'success' | 'warning' | 'danger'
}

const props = withDefaults(defineProps<Props>(), {
  hint: '',
  icon: null,
  tone: 'default',
})

const toneClasses: Record<string, string> = {
  default: 'bg-slate-100 text-slate-600',
  primary: 'bg-brand-100 text-brand-900',
  success: 'bg-emerald-50 text-emerald-700',
  warning: 'bg-amber-50 text-amber-700',
  danger: 'bg-rose-50 text-rose-700',
}
</script>

<template>
  <Card class="border border-slate-200/80 shadow-2xs">
    <div class="flex items-start justify-between gap-3">
      <div class="min-w-0">
        <p class="text-2xs font-semibold uppercase tracking-wider text-slate-500">{{ props.label }}</p>
        <p class="mt-1.5 text-2xl font-bold text-slate-900 leading-none">
          {{ props.value ?? 0 }}
        </p>
        <p v-if="props.hint" class="mt-1 text-2xs text-slate-500">{{ props.hint }}</p>
      </div>
      <div
        v-if="props.icon"
        :class="['shrink-0 w-9 h-9 rounded-lg flex items-center justify-center', toneClasses[props.tone]]"
      >
        <component :is="props.icon" class="w-4.5 h-4.5" />
      </div>
    </div>
  </Card>
</template>
