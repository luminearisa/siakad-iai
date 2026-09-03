<script setup lang="ts">
import { ref, reactive, watch } from 'vue'
import { KeyRound, UserPlus, Eye, EyeOff, Sparkles, Check, Copy } from 'lucide-vue-next'
import { studentService } from '@/services/api/students'
import { useToast } from '@/composables/useToast'
import type { Student } from '@/types/student'
import Modal from '@/components/ui/Modal.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'

interface Props {
  open: boolean
  student: Student
  mode: 'create' | 'reset-password'
}

const props = defineProps<Props>()

const emit = defineEmits<{
  (e: 'update:open', val: boolean): void
  (e: 'success', updatedStudent: Student): void
}>()

const toast = useToast()
const loading = ref<boolean>(false)
const showPassword = ref<boolean>(false)
const copied = ref<boolean>(false)

const form = reactive({
  email: '',
  password: '',
  confirmPassword: '',
})

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      form.email = props.student.user?.email || props.student.email || `${props.student.student_number}@student.ac.id`
      form.password = `${props.student.student_number}123`
      form.confirmPassword = `${props.student.student_number}123`
      showPassword.value = true
      copied.value = false
    }
  },
  { immediate: true }
)

function generateRandomPassword() {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789'
  let pass = ''
  for (let i = 0; i < 8; i++) {
    pass += chars.charAt(Math.floor(Math.random() * chars.length))
  }
  form.password = pass
  form.confirmPassword = pass
  toast.info('Password acak dibuat.')
}

function useDefaultNimPassword() {
  form.password = `${props.student.student_number}123`
  form.confirmPassword = `${props.student.student_number}123`
}

function copyPassword() {
  navigator.clipboard.writeText(form.password)
  copied.value = true
  toast.success('Password berhasil disalin ke clipboard.')
  setTimeout(() => {
    copied.value = false
  }, 2000)
}

async function handleSubmit() {
  if (props.mode === 'create' && !form.email.trim()) {
    toast.error('Email login wajib diisi.')
    return
  }

  if (!form.password || form.password.length < 6) {
    toast.error('Password minimal 6 karakter.')
    return
  }

  if (form.password !== form.confirmPassword) {
    toast.error('Konfirmasi password tidak cocok.')
    return
  }

  loading.value = true
  try {
    let res
    if (props.mode === 'create') {
      res = await studentService.createAccount(props.student.id, {
        email: form.email.trim(),
        password: form.password,
      })
      toast.success('Akun login mahasiswa berhasil dibuat!')
    } else {
      res = await studentService.resetPassword(props.student.id, {
        password: form.password,
      })
      toast.success('Password akun mahasiswa berhasil direset!')
    }

    emit('success', res.data)
    emit('update:open', false)
  } catch (err: any) {
    toast.error(err.response?.data?.message || err.message || 'Gagal memproses akun mahasiswa.')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <Modal
    :open="open"
    :title="mode === 'create' ? 'Buat Akun Portal Mahasiswa' : 'Ganti / Reset Password Mahasiswa'"
    size="md"
    @update:open="emit('update:open', $event)"
  >
    <form @submit.prevent="handleSubmit" class="space-y-4 text-xs">
      <!-- Target Student Info Box -->
      <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-1">
        <div class="flex items-center justify-between">
          <span class="text-3xs font-semibold uppercase tracking-wider text-slate-500">Mahasiswa</span>
          <span class="font-mono text-3xs font-bold text-slate-700 bg-slate-200 px-1.5 py-0.5 rounded">
            NIM: {{ student.student_number }}
          </span>
        </div>
        <div class="font-bold text-slate-900 text-sm">
          {{ student.full_name }}
        </div>
        <div class="text-2xs text-slate-500">
          {{ student.study_program?.name || 'Program Studi' }}
        </div>
      </div>

      <!-- Mode Create: Email Login -->
      <div v-if="mode === 'create'">
        <label class="block font-semibold text-slate-700 mb-1.5">
          Email Login (Username) <span class="text-rose-500">*</span>
        </label>
        <Input
          v-model="form.email"
          type="email"
          placeholder="Contoh: nama@student.ac.id"
          required
        />
        <span class="text-3xs text-slate-500 mt-1 block">
          Email ini akan digunakan oleh mahasiswa untuk masuk ke portal SIAKAD.
        </span>
      </div>

      <!-- Mode Reset: Show Current Account Info -->
      <div v-else class="text-slate-600">
        Email akun login terdaftar: <strong class="text-slate-900 font-mono">{{ student.user?.email || student.email }}</strong>
      </div>

      <!-- Password Input -->
      <div>
        <div class="flex items-center justify-between mb-1.5">
          <label class="block font-semibold text-slate-700">
            {{ mode === 'create' ? 'Password Awal' : 'Password Baru' }} <span class="text-rose-500">*</span>
          </label>
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="text-3xs text-emerald-700 hover:underline flex items-center gap-1 cursor-pointer font-medium"
              @click="useDefaultNimPassword"
            >
              NIM+123
            </button>
            <span class="text-slate-300">|</span>
            <button
              type="button"
              class="text-3xs text-brand-600 hover:underline flex items-center gap-1 cursor-pointer font-medium"
              @click="generateRandomPassword"
            >
              <Sparkles class="w-3 h-3" />
              Acak
            </button>
          </div>
        </div>

        <div class="relative">
          <input
            v-model="form.password"
            :type="showPassword ? 'text' : 'password'"
            required
            placeholder="Minimal 6 karakter"
            class="w-full pl-3 pr-16 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 font-mono"
          />
          <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-1">
            <button
              type="button"
              class="p-1 text-slate-400 hover:text-slate-600 cursor-pointer"
              title="Salin Password"
              @click="copyPassword"
            >
              <Check v-if="copied" class="w-3.5 h-3.5 text-emerald-600" />
              <Copy v-else class="w-3.5 h-3.5" />
            </button>
            <button
              type="button"
              class="p-1 text-slate-400 hover:text-slate-600 cursor-pointer"
              @click="showPassword = !showPassword"
            >
              <EyeOff v-if="showPassword" class="w-3.5 h-3.5" />
              <Eye v-else class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>
      </div>

      <!-- Confirm Password -->
      <div>
        <label class="block font-semibold text-slate-700 mb-1.5">
          Konfirmasi Password <span class="text-rose-500">*</span>
        </label>
        <input
          v-model="form.confirmPassword"
          :type="showPassword ? 'text' : 'password'"
          required
          placeholder="Ketik ulang password"
          class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 font-mono"
        />
      </div>
    </form>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button
          variant="outline"
          size="sm"
          @click="emit('update:open', false)"
        >
          Batal
        </Button>

        <Button
          variant="primary"
          size="sm"
          :loading="loading"
          class="bg-emerald-700 hover:bg-emerald-800 text-white font-semibold gap-1.5 px-4"
          @click="handleSubmit"
        >
          <UserPlus v-if="mode === 'create'" class="w-3.5 h-3.5" />
          <KeyRound v-else class="w-3.5 h-3.5" />
          {{ mode === 'create' ? 'Buat Akun Sekarang' : 'Simpan Password Baru' }}
        </Button>
      </div>
    </template>
  </Modal>
</template>
