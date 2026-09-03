<script setup lang="ts">
import { ref } from 'vue'

interface Props {
  text: string
  position?: 'top' | 'bottom' | 'left' | 'right'
}

withDefaults(defineProps<Props>(), {
  position: 'top',
})

const visible = ref(false)
</script>

<template>
  <div class="relative inline-flex" @mouseenter="visible = true" @mouseleave="visible = false">
    <slot />
    <div
      v-if="visible"
      :class="[
        'absolute z-40 px-2 py-1 text-2xs font-medium text-white bg-slate-900 rounded-sm shadow-subtle whitespace-nowrap pointer-events-none transition-opacity duration-150',
        position === 'top' ? 'bottom-full left-1/2 -translate-x-1/2 mb-1.5' : '',
        position === 'bottom' ? 'top-full left-1/2 -translate-x-1/2 mt-1.5' : '',
        position === 'left' ? 'right-full top-1/2 -translate-y-1/2 mr-1.5' : '',
        position === 'right' ? 'left-full top-1/2 -translate-y-1/2 ml-1.5' : '',
      ]"
    >
      {{ text }}
    </div>
  </div>
</template>
