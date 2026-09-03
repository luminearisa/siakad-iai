<script setup lang="ts">
import Modal from '../ui/Modal.vue'
import Button from '../ui/Button.vue'
import { AlertTriangle } from 'lucide-vue-next'

interface Props {
  open: boolean
  title?: string
  message: string
  confirmText?: string
  cancelText?: string
  variant?: 'danger' | 'primary'
  loading?: boolean
}

withDefaults(defineProps<Props>(), {
  open: false,
  title: 'Konfirmasi Tindakan',
  confirmText: 'Ya, Lanjutkan',
  cancelText: 'Batal',
  variant: 'danger',
  loading: false,
})

const emit = defineEmits<{
  (e: 'update:open', val: boolean): void
  (e: 'confirm'): void
  (e: 'cancel'): void
}>()

function handleConfirm() {
  emit('confirm')
}

function handleCancel() {
  emit('update:open', false)
  emit('cancel')
}
</script>

<template>
  <Modal :open="open" size="sm" @update:open="emit('update:open', $event)">
    <div class="flex items-start gap-3">
      <div
        :class="[
          'w-8 h-8 rounded-full flex items-center justify-center shrink-0 mt-0.5',
          variant === 'danger' ? 'bg-rose-100 text-rose-600' : 'bg-brand-100 text-brand-700',
        ]"
      >
        <AlertTriangle class="w-4 h-4" />
      </div>

      <div class="flex-1">
        <h4 class="text-sm font-semibold text-slate-900 mb-1">
          {{ title }}
        </h4>
        <p class="text-xs text-slate-600 leading-relaxed">
          {{ message }}
        </p>
      </div>
    </div>

    <template #footer>
      <Button variant="outline" size="sm" :disabled="loading" @click="handleCancel">
        {{ cancelText }}
      </Button>
      <Button :variant="variant" size="sm" :loading="loading" @click="handleConfirm">
        {{ confirmText }}
      </Button>
    </template>
  </Modal>
</template>
