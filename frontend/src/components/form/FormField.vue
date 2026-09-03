<script setup lang="ts">
import FormLabel from './FormLabel.vue'
import FormError from './FormError.vue'

interface Props {
  label?: string
  required?: boolean
  error?: string | null
  helpText?: string
  id?: string
}

defineProps<Props>()
</script>

<template>
  <div class="w-full space-y-1">
    <FormLabel v-if="label" :for-id="id" :required="required">
      {{ label }}
    </FormLabel>

    <div>
      <slot :id="id" :error="!!error" />
    </div>

    <FormError v-if="error" :error="error" />

    <p v-else-if="helpText" class="text-2xs text-slate-500 mt-0.5">
      {{ helpText }}
    </p>
  </div>
</template>
