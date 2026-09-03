<script setup lang="ts">
import { computed, watch, onMounted, onUnmounted } from 'vue'
import { X } from 'lucide-vue-next'

interface Props {
  open: boolean
  title?: string
  position?: 'left' | 'right'
  width?: string
}

const props = withDefaults(defineProps<Props>(), {
  open: false,
  position: 'right',
  width: 'max-w-md',
})

const emit = defineEmits<{
  (e: 'update:open', val: boolean): void
  (e: 'close'): void
}>()

const slideClasses = computed(() => {
  if (props.position === 'left') {
    return {
      enterFrom: '-translate-x-full',
      enterTo: 'translate-x-0',
      leaveFrom: 'translate-x-0',
      leaveTo: '-translate-x-full',
      positionClass: 'left-0',
    }
  }
  return {
    enterFrom: 'translate-x-full',
    enterTo: 'translate-x-0',
    leaveFrom: 'translate-x-0',
    leaveTo: 'translate-x-full',
    positionClass: 'right-0',
  }
})

function close() {
  emit('update:open', false)
  emit('close')
}

function handleKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape' && props.open) {
    close()
  }
}

watch(() => props.open, (val) => {
  if (val) {
    document.body.style.overflow = 'hidden'
  } else {
    document.body.style.overflow = ''
  }
})

onMounted(() => {
  document.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  document.body.style.overflow = ''
  document.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-50 overflow-hidden">
      <!-- Backdrop -->
      <Transition
        enter-active-class="ease-in-out duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="ease-in-out duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs transition-opacity" @click="close" />
      </Transition>

      <div class="fixed inset-y-0 flex max-w-full" :class="position === 'left' ? 'left-0' : 'right-0'">
        <Transition
          enter-active-class="transform transition ease-in-out duration-300"
          :enter-from-class="slideClasses.enterFrom"
          :enter-to-class="slideClasses.enterTo"
          leave-active-class="transform transition ease-in-out duration-300"
          :leave-from-class="slideClasses.leaveFrom"
          :leave-to-class="slideClasses.leaveTo"
        >
          <div :class="['w-screen bg-white shadow-modal flex flex-col', width]">
            <!-- Header -->
            <div class="px-4 py-3.5 border-b border-slate-200 flex items-center justify-between bg-slate-50">
              <slot name="header">
                <h3 class="text-sm font-semibold text-slate-800">{{ title }}</h3>
              </slot>
              <button
                type="button"
                class="rounded-xs p-1 text-slate-400 hover:text-slate-600 transition-colors cursor-pointer"
                @click="close"
              >
                <X class="w-4 h-4" />
              </button>
            </div>

            <!-- Body -->
            <div class="flex-1 overflow-y-auto p-4 sm:p-5 text-xs text-slate-700">
              <slot />
            </div>

            <!-- Footer -->
            <div v-if="$slots.footer" class="px-4 py-3 border-t border-slate-200 bg-slate-50 flex items-center justify-end gap-2">
              <slot name="footer" :close="close" />
            </div>
          </div>
        </Transition>
      </div>
    </div>
  </Teleport>
</template>
