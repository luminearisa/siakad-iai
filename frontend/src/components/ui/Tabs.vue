<script setup lang="ts">
export interface TabItem {
  id: string | number
  label: string
  badge?: string | number
}

interface Props {
  tabs: TabItem[]
  modelValue: string | number
}

defineProps<Props>()

const emit = defineEmits<{
  (e: 'update:modelValue', id: string | number): void
}>()
</script>

<template>
  <div class="border-b border-slate-200">
    <nav class="-mb-px flex space-x-4 overflow-x-auto whitespace-nowrap" aria-label="Tabs">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        type="button"
        :class="[
          'py-2 px-1 inline-flex items-center gap-1.5 border-b-2 text-xs font-medium transition-colors cursor-pointer select-none',
          modelValue === tab.id
            ? 'border-brand-800 text-brand-900 font-semibold'
            : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300',
        ]"
        @click="emit('update:modelValue', tab.id)"
      >
        {{ tab.label }}
        <span
          v-if="tab.badge !== undefined"
          :class="[
            'text-2xs px-1.5 py-0.2 rounded-full font-medium',
            modelValue === tab.id ? 'bg-brand-100 text-brand-900' : 'bg-slate-100 text-slate-600'
          ]"
        >
          {{ tab.badge }}
        </span>
      </button>
    </nav>
  </div>
</template>
