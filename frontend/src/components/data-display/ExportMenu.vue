<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import { ChevronDown, Download, Loader2 } from 'lucide-vue-next'
import Button from '@/components/ui/Button.vue'
import { useToast } from '@/composables/useToast'

/**
 * Tombol "Export as…" untuk halaman yang memuat data pelaporan PDDikti / Neo Feeder.
 *
 * Halaman cukup menyiapkan daftar opsi; komponen ini menangani buka/tutup menu,
 * indikator proses, dan notifikasi hasil. Unduhan sendiri dikerjakan service
 * (`integratorService.export*`) agar nama berkas & endpoint terkumpul di satu tempat.
 */
export interface ExportOption {
  /** Teks menu, mis. "Log akses — CSV (Excel)". Sekaligus nama berkas pada notifikasi. */
  label: string
  /** Keterangan kecil di bawah label (opsional). */
  description?: string
  /** Dijalankan saat menu dipilih. */
  run: () => Promise<void>
}

const props = withDefaults(
  defineProps<{
    options: ExportOption[]
    label?: string
    disabled?: boolean
  }>(),
  {
    label: 'Export as…',
    disabled: false,
  }
)

const toast = useToast()
const isOpen = ref(false)
const running = ref<string | null>(null)
const container = ref<HTMLElement | null>(null)

function toggle(): void {
  if (!props.disabled) isOpen.value = !isOpen.value
}

function close(): void {
  isOpen.value = false
}

async function pick(option: ExportOption): Promise<void> {
  close()
  running.value = option.label

  try {
    await option.run()
    toast.success(`Berkas "${option.label}" sedang diunduh.`)
  } catch (error) {
    toast.error(messageFrom(error))
  } finally {
    running.value = null
  }
}

function messageFrom(error: unknown): string {
  const response = (error as { response?: { data?: { message?: string } } })?.response
  if (response?.data?.message) return response.data.message
  if (error instanceof Error && error.message) return error.message

  return 'Unduhan gagal disiapkan.'
}

function handleClickOutside(event: MouseEvent): void {
  if (container.value && !container.value.contains(event.target as Node)) {
    close()
  }
}

function handleEscape(event: KeyboardEvent): void {
  if (event.key === 'Escape') close()
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
  document.addEventListener('keydown', handleEscape)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
  document.removeEventListener('keydown', handleEscape)
})
</script>

<template>
  <div ref="container" class="relative inline-block text-left">
    <Button
      variant="outline"
      size="sm"
      :disabled="disabled || running !== null"
      aria-haspopup="menu"
      :aria-expanded="isOpen"
      @click="toggle"
    >
      <Loader2 v-if="running" class="w-4 h-4 mr-1 animate-spin" />
      <Download v-else class="w-4 h-4 mr-1" />
      {{ label }}
      <ChevronDown class="w-3.5 h-3.5 ml-1" />
    </Button>

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
        role="menu"
        class="absolute right-0 z-50 mt-1.5 w-72 rounded-md bg-white border border-slate-200 shadow-elevated p-1"
      >
        <button
          v-for="option in options"
          :key="option.label"
          type="button"
          role="menuitem"
          class="w-full text-left px-3 py-2 rounded-md hover:bg-slate-50 focus:bg-slate-50 focus:outline-none"
          @click="pick(option)"
        >
          <span class="block text-sm text-slate-800">{{ option.label }}</span>
          <span v-if="option.description" class="block text-2xs text-slate-500">{{ option.description }}</span>
        </button>
      </div>
    </Transition>
  </div>
</template>
