<script setup lang="ts">
import { ref, watch } from 'vue'
import { Camera, Trash2, UploadCloud } from 'lucide-vue-next'
import Avatar from '@/components/ui/Avatar.vue'
import Button from '@/components/ui/Button.vue'

interface Props {
  modelValue?: string | null
  name?: string
  label?: string
  helpText?: string
  aspectRatio?: 'square' | 'portrait'
  disabled?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: null,
  name: 'User',
  label: 'Pas Foto / Foto Profil',
  helpText: 'Format JPG, PNG, atau WebP. Maksimal 2MB.',
  aspectRatio: 'portrait',
  disabled: false,
})

const emit = defineEmits<{
  (e: 'update:modelValue', val: string | null): void
}>()

const fileInputRef = ref<HTMLInputElement | null>(null)
const previewUrl = ref<string | null>(props.modelValue || null)
const isDragging = ref<boolean>(false)
const errorMessage = ref<string | null>(null)

watch(
  () => props.modelValue,
  (val) => {
    previewUrl.value = val || null
  }
)

function triggerFileInput() {
  if (props.disabled) return
  fileInputRef.value?.click()
}

function handleFileChange(event: Event) {
  const target = event.target as HTMLInputElement
  if (target.files && target.files[0]) {
    processFile(target.files[0])
  }
}

function handleDrop(event: DragEvent) {
  isDragging.value = false
  if (props.disabled) return
  if (event.dataTransfer?.files && event.dataTransfer.files[0]) {
    processFile(event.dataTransfer.files[0])
  }
}

function processFile(file: File) {
  errorMessage.value = null

  if (!file.type.startsWith('image/')) {
    errorMessage.value = 'File yang dipilih harus berupa gambar (JPG, PNG, WebP).'
    return
  }

  // Check size (2MB)
  if (file.size > 2 * 1024 * 1024) {
    errorMessage.value = 'Ukuran file gambar maksimal 2MB.'
    return
  }

  const reader = new FileReader()
  reader.onload = (e) => {
    const rawDataUrl = e.target?.result as string
    compressImage(rawDataUrl, (compressedDataUrl) => {
      previewUrl.value = compressedDataUrl
      emit('update:modelValue', compressedDataUrl)
    })
  }
  reader.readAsDataURL(file)
}

function compressImage(dataUrl: string, callback: (result: string) => void) {
  const img = new Image()
  img.onload = () => {
    const canvas = document.createElement('canvas')
    const maxDim = 600
    let width = img.width
    let height = img.height

    if (width > height) {
      if (width > maxDim) {
        height = Math.round((height * maxDim) / width)
        width = maxDim
      }
    } else {
      if (height > maxDim) {
        width = Math.round((width * maxDim) / height)
        height = maxDim
      }
    }

    canvas.width = width
    canvas.height = height
    const ctx = canvas.getContext('2d')
    if (ctx) {
      ctx.drawImage(img, 0, 0, width, height)
      const compressed = canvas.toDataURL('image/jpeg', 0.85)
      callback(compressed)
    } else {
      callback(dataUrl)
    }
  }
  img.src = dataUrl
}

function removePhoto() {
  if (props.disabled) return
  previewUrl.value = null
  emit('update:modelValue', null)
  if (fileInputRef.value) {
    fileInputRef.value.value = ''
  }
}
</script>

<template>
  <div class="space-y-2">
    <label class="block text-xs font-semibold text-slate-700 select-none">
      {{ label }}
    </label>

    <div class="flex items-start gap-4">
      <!-- Hidden File Input -->
      <input
        ref="fileInputRef"
        type="file"
        accept="image/jpeg,image/png,image/webp"
        class="hidden"
        :disabled="disabled"
        @change="handleFileChange"
      />

      <!-- Photo Preview Box -->
      <div
        :class="[
          'relative group border-2 border-dashed rounded-lg overflow-hidden flex flex-col items-center justify-center transition-all duration-150 shrink-0 bg-slate-50',
          aspectRatio === 'portrait' ? 'w-24 h-32 sm:w-28 sm:h-36' : 'w-24 h-24 sm:w-28 sm:h-28',
          isDragging ? 'border-brand-500 bg-brand-50/50 ring-2 ring-brand-200' : 'border-slate-300 hover:border-brand-400',
          disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer',
        ]"
        @click="triggerFileInput"
        @dragover.prevent="isDragging = true"
        @dragleave.prevent="isDragging = false"
        @drop.prevent="handleDrop"
      >
        <template v-if="previewUrl">
          <img
            :src="previewUrl"
            :alt="name"
            class="w-full h-full object-cover"
          />
          <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white">
            <Camera class="w-5 h-5" />
          </div>
        </template>

        <template v-else>
          <Avatar :name="name" size="lg" class="mb-2" />
          <span class="text-2xs text-slate-400 font-medium text-center px-1">Unggah Pas Foto</span>
        </template>
      </div>

      <!-- Upload Actions & Instructions -->
      <div class="flex-1 space-y-2">
        <div class="flex flex-wrap items-center gap-2">
          <Button
            type="button"
            variant="outline"
            size="sm"
            :disabled="disabled"
            @click="triggerFileInput"
          >
            <UploadCloud class="w-3.5 h-3.5" />
            <span>{{ previewUrl ? 'Ganti Foto' : 'Pilih Foto' }}</span>
          </Button>

          <Button
            v-if="previewUrl"
            type="button"
            variant="ghost"
            size="sm"
            class="text-rose-600 hover:bg-rose-50"
            :disabled="disabled"
            @click="removePhoto"
          >
            <Trash2 class="w-3.5 h-3.5" />
            <span>Hapus</span>
          </Button>
        </div>

        <p class="text-2xs text-slate-500 leading-normal">
          {{ helpText }}
        </p>

        <p v-if="errorMessage" class="text-2xs text-rose-600 font-medium">
          {{ errorMessage }}
        </p>
      </div>
    </div>
  </div>
</template>
