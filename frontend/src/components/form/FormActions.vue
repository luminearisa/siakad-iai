<script setup lang="ts">
interface Props {
  align?: 'left' | 'center' | 'right' | 'between'
}

withDefaults(defineProps<Props>(), {
  align: 'right',
})
</script>

<template>
  <div
    :class="[
      'flex items-center gap-2 pt-3 border-t border-slate-100',
      align === 'right' ? 'justify-end' : '',
      align === 'left' ? 'justify-start' : '',
      align === 'center' ? 'justify-center' : '',
      align === 'between' ? 'justify-between' : '',
    ]"
  >
    <slot />
  </div>
</template>
