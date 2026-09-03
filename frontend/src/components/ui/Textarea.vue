<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  modelValue?: string | null
  placeholder?: string
  rows?: number
  disabled?: boolean
  readonly?: boolean
  required?: boolean
  error?: boolean | string
  id?: string
  name?: string
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: '',
  placeholder: '',
  rows: 3,
  disabled: false,
  readonly: false,
  required: false,
  error: false,
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
  (e: 'blur', event: FocusEvent): void
}>()

const errorClass = computed(() =>
  props.error
    ? 'border-rose-500 focus:ring-rose-500 focus:border-rose-500 text-rose-900'
    : 'border-slate-300'
)

function handleInput(event: Event) {
  const target = event.target as HTMLTextAreaElement
  emit('update:modelValue', target.value)
}
</script>

<template>
  <textarea
    :id="id"
    :name="name"
    :rows="rows"
    :value="modelValue"
    :placeholder="placeholder"
    :disabled="disabled"
    :readonly="readonly"
    :required="required"
    :class="[
      'w-full text-xs px-3 py-2 bg-white text-slate-900 border rounded-md transition-colors duration-150 outline-none placeholder:text-slate-400 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 disabled:bg-slate-50 disabled:text-slate-500 disabled:cursor-not-allowed resize-y',
      errorClass,
    ]"
    @input="handleInput"
    @blur="emit('blur', $event)"
  />
</template>
