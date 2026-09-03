<script setup lang="ts">
interface Props {
  forId?: string
  required?: boolean
}

defineProps<Props>()
</script>

<template>
  <label :for="forId" class="block text-xs font-semibold text-slate-700 select-none mb-1">
    <slot />
    <span v-if="required" class="text-rose-600 ml-0.5">*</span>
  </label>
</template>
