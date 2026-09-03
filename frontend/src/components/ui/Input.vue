<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  modelValue?: string | number | null
  type?: string
  placeholder?: string
  disabled?: boolean
  readonly?: boolean
  required?: boolean
  error?: boolean | string
  size?: 'sm' | 'md' | 'lg'
  id?: string
  name?: string
  autocomplete?: string
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: '',
  type: 'text',
  placeholder: '',
  disabled: false,
  readonly: false,
  required: false,
  error: false,
  size: 'sm',
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: string | number): void
  (e: 'blur', event: FocusEvent): void
  (e: 'focus', event: FocusEvent): void
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

function handleInput(event: Event) {
  const target = event.target as HTMLInputElement
  emit('update:modelValue', target.value)
}
</script>

<template>
  <div class="relative w-full">
    <div v-if="$slots.prefix" class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400 text-xs">
      <slot name="prefix" />
    </div>

    <input
      :id="id"
      :name="name"
      :type="type"
      :value="modelValue"
      :placeholder="placeholder"
      :disabled="disabled"
      :readonly="readonly"
      :required="required"
      :autocomplete="autocomplete"
      :class="[
        'w-full bg-white text-slate-900 border transition-colors duration-150 outline-none placeholder:text-slate-400 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 disabled:bg-slate-50 disabled:text-slate-500 disabled:cursor-not-allowed',
        sizeClasses,
        error ? 'border-rose-500 focus:ring-rose-500 focus:border-rose-500 text-rose-900' : 'border-slate-300',
        $slots.prefix ? 'pl-8' : '',
        $slots.suffix ? 'pr-8' : '',
      ]"
      @input="handleInput"
      @blur="emit('blur', $event)"
      @focus="emit('focus', $event)"
    />

    <div v-if="$slots.suffix" class="absolute inset-y-0 right-0 pr-2.5 flex items-center pointer-events-none text-slate-400 text-xs">
      <slot name="suffix" />
    </div>
  </div>
</template>
