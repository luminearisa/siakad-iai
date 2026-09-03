<script setup lang="ts">
interface Props {
  error?: string | null
}

defineProps<Props>()
</script>

<template>
  <p v-if="error || $slots.default" class="text-2xs text-rose-600 font-medium mt-1">
    <slot>{{ error }}</slot>
  </p>
</template>
