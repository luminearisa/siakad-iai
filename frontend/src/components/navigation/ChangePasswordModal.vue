<script setup lang="ts">
import { ref, reactive } from 'vue'
import { Eye, EyeOff, Lock } from 'lucide-vue-next'
import { authService } from '@/services/api/auth'
import { useToast } from '@/composables/useToast'
import Modal from '@/components/ui/Modal.vue'
import Input from '@/components/ui/Input.vue'
import Button from '@/components/ui/Button.vue'
import Alert from '@/components/ui/Alert.vue'

interface Props {
  open: boolean
}

interface Emits {
  (e: 'update:open', val: boolean): void
}

defineProps<Props>()
const emit = defineEmits<Emits>()

const toast = useToast()
const loading = ref<boolean>(false)
const errorMessage = ref<string | null>(null)

const showCurrent = ref<boolean>(false)
const showNew = ref<boolean>(false)
const showConfirm = ref<boolean>(false)

const form = reactive({
  current_password: '',
  new_password: '',
  new_password_confirmation: '',
})

function handleClose() {
  form.current_password = ''
  form.new_password = ''
  form.new_password_confirmation = ''
  errorMessage.value = null
  emit('update:open', false)
}

async function handleSubmit() {
  errorMessage.value = null

  if (!form.current_password) {
    errorMessage.value = 'Password saat ini wajib diisi.'
    return
  }

  if (form.new_password.length < 8) {
    errorMessage.value = 'Password baru minimal harus 8 karakter.'
    return
  }

  if (form.new_password !== form.new_password_confirmation) {
    errorMessage.value = 'Konfirmasi password baru tidak cocok.'
    return
  }

  if (form.new_password === form.current_password) {
    errorMessage.value = 'Password baru harus berbeda dengan password saat ini.'
    return
  }

  loading.value = true
  try {
    await authService.changePassword({
      current_password: form.current_password,
      new_password: form.new_password,
      new_password_confirmation: form.new_password_confirmation,
    })

    toast.success('Password berhasil diperbarui.')
    handleClose()
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal mengubah password. Pastikan password saat ini benar.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <Modal
    :open="open"
    title="Ubah Password Akun"
    max-width="max-w-md"
    @update:open="$emit('update:open', $event)"
  >
    <form @submit.prevent="handleSubmit" class="space-y-4">
      <Alert v-if="errorMessage" variant="danger" :title="errorMessage" />

      <div class="space-y-3">
        <!-- Current Password -->
        <div class="space-y-1">
          <label class="block text-xs font-medium text-slate-700">
            Password Saat Ini <span class="text-rose-500">*</span>
          </label>
          <div class="relative">
            <Input
              v-model="form.current_password"
              :type="showCurrent ? 'text' : 'password'"
              placeholder="Masukkan password saat ini"
              required
              class="pr-10"
            />
            <button
              type="button"
              class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 p-1"
              @click="showCurrent = !showCurrent"
            >
              <EyeOff v-if="showCurrent" class="w-4 h-4" />
              <Eye v-else class="w-4 h-4" />
            </button>
          </div>
        </div>

        <!-- New Password -->
        <div class="space-y-1">
          <label class="block text-xs font-medium text-slate-700">
            Password Baru <span class="text-rose-500">*</span>
          </label>
          <div class="relative">
            <Input
              v-model="form.new_password"
              :type="showNew ? 'text' : 'password'"
              placeholder="Minimal 8 karakter"
              required
              class="pr-10"
            />
            <button
              type="button"
              class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 p-1"
              @click="showNew = !showNew"
            >
              <EyeOff v-if="showNew" class="w-4 h-4" />
              <Eye v-else class="w-4 h-4" />
            </button>
          </div>
          <p class="text-3xs text-slate-400">Gunakan kombinasi huruf, angka, dan simbol untuk keamanan maksimal.</p>
        </div>

        <!-- Confirm Password -->
        <div class="space-y-1">
          <label class="block text-xs font-medium text-slate-700">
            Konfirmasi Password Baru <span class="text-rose-500">*</span>
          </label>
          <div class="relative">
            <Input
              v-model="form.new_password_confirmation"
              :type="showConfirm ? 'text' : 'password'"
              placeholder="Ulangi password baru"
              required
              class="pr-10"
            />
            <button
              type="button"
              class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 p-1"
              @click="showConfirm = !showConfirm"
            >
              <EyeOff v-if="showConfirm" class="w-4 h-4" />
              <Eye v-else class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>

      <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
        <Button
          type="button"
          variant="secondary"
          size="sm"
          :disabled="loading"
          @click="handleClose"
        >
          Batal
        </Button>
        <Button
          type="submit"
          variant="primary"
          size="sm"
          :loading="loading"
          class="gap-1.5"
        >
          <Lock class="w-3.5 h-3.5" />
          Simpan Password Baru
        </Button>
      </div>
    </form>
  </Modal>
</template>
