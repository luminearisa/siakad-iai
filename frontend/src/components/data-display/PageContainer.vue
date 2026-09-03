<script setup lang="ts">
interface Props {
  maxWidth?: 'sm' | 'md' | 'lg' | 'xl' | '2xl' | '7xl' | 'full'
}

withDefaults(defineProps<Props>(), {
  maxWidth: 'full',
})
</script>

<template>
  <div
    :class="[
      'w-full mx-auto px-4 sm:px-6 lg:px-8 py-5 sm:py-6',
      maxWidth === '7xl' ? 'max-w-7xl' : '',
      maxWidth === '2xl' ? 'max-w-2xl' : '',
      maxWidth === 'xl' ? 'max-w-xl' : '',
      maxWidth === 'lg' ? 'max-w-lg' : '',
      maxWidth === 'md' ? 'max-w-md' : '',
      maxWidth === 'sm' ? 'max-w-sm' : '',
      maxWidth === 'full' ? 'max-w-full' : '',
    ]"
  >
    <slot />
  </div>
</template>
