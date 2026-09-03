<script setup lang="ts">
import { computed, useSlots } from 'vue'

export interface SelectOption {
  label: string
  value: string | number
  disabled?: boolean
}

interface Props {
  modelValue?: string | number | null
  options?: SelectOption[]
  placeholder?: string
  disabled?: boolean
  required?: boolean
  error?: boolean | string
  size?: 'sm' | 'md' | 'lg'
  id?: string
  name?: string
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: '',
  options: () => [],
  placeholder: '',
  disabled: false,
  required: false,
  error: false,
  size: 'sm',
})

const slots = useSlots()

const emit = defineEmits<{
  (e: 'update:modelValue', value: string | number): void
  (e: 'change', event: Event): void
}>()

const sizeClasses = computed(() => {
  switch (props.size) {
    case 'sm':
      return 'text-xs px-2.5 py-1.5 rounded-md h-8'
    case 'md':
      return 'text-sm px-3 py-2 rounded-md h-9'
    case 'lg':
      return 'text-base px-3.5 py-2.5 rounded-lg h-11'
    default:
      return 'text-xs px-2.5 py-1.5 rounded-md h-8'
  }
})

function handleChange(event: Event) {
  const target = event.target as HTMLSelectElement
  emit('update:modelValue', target.value)
  emit('change', event)
}
</script>

<template>
  <div class="relative w-full">
    <select
      :id="id"
      :name="name"
      :value="modelValue"
      :disabled="disabled"
      :required="required"
      :class="[
        'w-full bg-white text-slate-900 border transition-colors duration-150 outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 disabled:bg-slate-50 disabled:text-slate-500 disabled:cursor-not-allowed appearance-none pr-8 cursor-pointer',
        sizeClasses,
        error ? 'border-rose-500 focus:ring-rose-500 focus:border-rose-500 text-rose-900' : 'border-slate-300',
      ]"
      @change="handleChange"
    >
      <option v-if="placeholder && !slots.default" value="">
        {{ placeholder }}
      </option>
      <option
        v-for="opt in options"
        :key="opt.value"
        :value="opt.value"
        :disabled="opt.disabled"
      >
        {{ opt.label }}
      </option>
      <slot />
    </select>
    <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none text-slate-400">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
      </svg>
    </div>
  </div>
</template>
