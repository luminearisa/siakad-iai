<script setup lang="ts">
import { computed, watch, onMounted, onUnmounted } from 'vue'
import { X } from 'lucide-vue-next'

interface Props {
  open: boolean
  title?: string
  size?: 'sm' | 'md' | 'lg' | 'xl'
  closable?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  open: false,
  size: 'md',
  closable: true,
})

const emit = defineEmits<{
  (e: 'update:open', val: boolean): void
  (e: 'close'): void
}>()

const sizeClasses = computed(() => {
  switch (props.size) {
    case 'sm':
      return 'max-w-sm'
    case 'md':
      return 'max-w-lg'
    case 'lg':
      return 'max-w-2xl'
    case 'xl':
      return 'max-w-4xl'
    default:
      return 'max-w-lg'
  }
})

function close() {
  if (props.closable) {
    emit('update:open', false)
    emit('close')
  }
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
    <Transition
      enter-active-class="ease-out duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="ease-in duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="open" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs transition-opacity" @click="close" />

        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
          <div
            :class="[
              'relative transform overflow-hidden rounded-lg bg-white text-left shadow-modal transition-all my-8 w-full border border-slate-200',
              sizeClasses,
            ]"
            @click.stop
          >
            <!-- Header -->
            <div v-if="title || $slots.header || closable" class="px-4 py-3 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
              <slot name="header">
                <h3 class="text-sm font-semibold text-slate-800">{{ title }}</h3>
              </slot>
              <button
                v-if="closable"
                type="button"
                class="rounded-xs p-1 text-slate-400 hover:text-slate-600 transition-colors cursor-pointer"
                @click="close"
              >
                <X class="w-4 h-4" />
              </button>
            </div>

            <!-- Content -->
            <div class="px-4 py-4 sm:p-5 text-xs text-slate-700">
              <slot />
            </div>

            <!-- Footer -->
            <div v-if="$slots.footer" class="px-4 py-3 border-t border-slate-100 bg-slate-50/50 flex items-center justify-end gap-2">
              <slot name="footer" :close="close" />
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
