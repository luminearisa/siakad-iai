<script setup lang="ts">
import { useToast } from '@/composables/useToast'
import Toast from '@/components/ui/Toast.vue'

const { toasts, remove } = useToast()
</script>

<template>
  <Teleport to="body">
    <div
      aria-live="polite"
      class="fixed inset-0 flex flex-col items-end justify-start px-4 py-4 pointer-events-none space-y-2.5 z-50 sm:p-6"
    >
      <TransitionGroup
        enter-active-class="transform ease-out duration-200 transition"
        enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
        enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <Toast
          v-for="toast in toasts"
          :key="toast.id"
          :type="toast.type"
          :title="toast.title"
          :message="toast.message"
          @close="remove(toast.id)"
        />
      </TransitionGroup>
    </div>
  </Teleport>
</template>
