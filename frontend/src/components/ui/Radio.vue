<script setup lang="ts">
interface Props {
  modelValue?: string | number | boolean
  value: string | number | boolean
  label?: string
  description?: string
  disabled?: boolean
  id?: string
  name?: string
}

const props = withDefaults(defineProps<Props>(), {
  disabled: false,
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: string | number | boolean): void
}>()

function handleChange() {
  emit('update:modelValue', props.value)
}
</script>

<template>
  <label :class="['inline-flex items-start gap-2 select-none cursor-pointer', disabled ? 'opacity-50 cursor-not-allowed' : '']">
    <input
      :id="id"
      :name="name"
      type="radio"
      :value="value"
      :checked="modelValue === value"
      :disabled="disabled"
      class="mt-0.5 w-3.5 h-3.5 text-brand-900 border-slate-300 focus:ring-brand-500 focus:ring-offset-1 focus:ring-2 cursor-pointer"
      @change="handleChange"
    />
    <div v-if="label || $slots.default" class="text-xs">
      <span class="font-medium text-slate-700">
        <slot>{{ label }}</slot>
      </span>
      <p v-if="description" class="text-slate-500 text-2xs mt-0.5">{{ description }}</p>
    </div>
  </label>
</template>
