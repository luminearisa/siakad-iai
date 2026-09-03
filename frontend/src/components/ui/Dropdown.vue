<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'

interface Props {
  align?: 'left' | 'right'
  width?: string
}

withDefaults(defineProps<Props>(), {
  align: 'right',
  width: 'w-48',
})

const isOpen = ref(false)
const dropdownRef = ref<HTMLElement | null>(null)

function toggle() {
  isOpen.value = !isOpen.value
}

function close() {
  isOpen.value = false
}

function handleClickOutside(event: MouseEvent) {
  if (dropdownRef.value && !dropdownRef.value.contains(event.target as Node)) {
    close()
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
  <div ref="dropdownRef" class="relative inline-block text-left">
    <div @click="toggle">
      <slot name="trigger" :is-open="isOpen" />
    </div>

    <Transition
      enter-active-class="transition ease-out duration-100"
      enter-from-class="transform opacity-0 scale-95"
      enter-to-class="transform opacity-100 scale-100"
      leave-active-class="transition ease-in duration-75"
      leave-from-class="transform opacity-100 scale-100"
      leave-to-class="transform opacity-0 scale-95"
    >
      <div
        v-if="isOpen"
        :class="[
          'absolute z-50 mt-1.5 rounded-md bg-white border border-slate-200 shadow-elevated focus:outline-none p-1',
          width,
          align === 'right' ? 'right-0 origin-top-right' : 'left-0 origin-top-left',
        ]"
        @click="close"
      >
        <slot name="content" :close="close" />
      </div>
    </Transition>
  </div>
</template>
