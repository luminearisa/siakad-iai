<script setup lang="ts">
interface Props {
  width?: string
  height?: string
  rounded?: 'xs' | 'sm' | 'md' | 'lg' | 'full'
  count?: number
}

withDefaults(defineProps<Props>(), {
  width: '100%',
  height: '1rem',
  rounded: 'md',
  count: 1,
})
</script>

<template>
  <div class="space-y-2">
    <div
      v-for="i in count"
      :key="i"
      :style="{ width, height }"
      :class="[
        'animate-pulse bg-slate-200/80',
        rounded === 'full' ? 'rounded-full' : '',
        rounded === 'xs' ? 'rounded-xs' : '',
        rounded === 'sm' ? 'rounded-sm' : '',
        rounded === 'md' ? 'rounded-md' : '',
        rounded === 'lg' ? 'rounded-lg' : '',
      ]"
    />
  </div>
</template>
