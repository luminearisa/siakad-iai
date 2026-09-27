<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ArrowRight, Eye, EyeOff, Lock, Mail } from 'lucide-vue-next'
import { useAuth } from '@/composables/useAuth'
import { useToast } from '@/composables/useToast'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import FormField from '@/components/form/FormField.vue'
import Alert from '@/components/ui/Alert.vue'
import type { ApiError } from '@/services/api/client'

const router = useRouter()
const route = useRoute()
const { login } = useAuth()
const toast = useToast()

const email = ref<string>('')
const password = ref<string>('')
const loading = ref<boolean>(false)
const errorMessage = ref<string | null>(null)
const fieldErrors = ref<Record<string, string[]>>({})
const showPassword = ref<boolean>(false)

const passwordType = computed(() => (showPassword.value ? 'text' : 'password'))

/**
 * Pintasan akun uji untuk pengembangan lokal.
 *
 * Build produksi tidak memuat blok ini sama sekali (lihat `v-if="isDev"`), sehingga
 * kredensial hasil seeder tidak pernah dipromosikan di halaman login kampus.
 */
const isDev = import.meta.env.DEV
const DEV_PASSWORD = isDev ? 'password123' : ''

// Ditulis sebagai ternary: saat build produksi `import.meta.env.DEV` menjadi `false`,
// sehingga daftar ini dan kata sandinya benar-benar dibuang dari bundel — bukan sekadar
// disembunyikan dari tampilan.
const devAccounts = isDev
  ? [
      { label: 'Super Admin', email: 'admin@siakad.ac.id' },
      { label: 'Akademik', email: 'akademik@siakad.ac.id' },
      { label: 'Dosen', email: 'dosen@siakad.ac.id' },
      { label: 'Mahasiswa', email: 'mahasiswa@siakad.ac.id' },
    ]
  : []

async function handleLogin() {
  errorMessage.value = null
  fieldErrors.value = {}

  if (!email.value.trim()) {
    fieldErrors.value.email = ['Email wajib diisi.']
    return
  }
  if (!password.value) {
    fieldErrors.value.password = ['Password wajib diisi.']
    return
  }

  loading.value = true
  try {
    const user = await login({
      email: email.value.trim(),
      password: password.value,
    })

    toast.success(`Selamat datang kembali, ${user.name}!`)

    const redirectPath = (route.query.redirect as string) || '/dashboard'
    router.push(redirectPath)
  } catch (err: unknown) {
    const apiErr = err as ApiError
    errorMessage.value = apiErr.message || 'Email atau password yang Anda masukkan tidak sesuai.'
    if (apiErr.errors) {
      fieldErrors.value = apiErr.errors
    }
  } finally {
    loading.value = false
  }
}

function fillDevAccount(userEmail: string) {
  email.value = userEmail
  password.value = DEV_PASSWORD
}
</script>

<template>
  <div>
    <header class="mb-6">
      <h2 class="text-lg font-bold tracking-tight text-slate-900">Masuk ke Akun Anda</h2>
      <p class="mt-1 text-xs text-slate-500">
        Gunakan email institusi dan kata sandi yang telah terdaftar.
      </p>
    </header>

    <Alert
      v-if="errorMessage"
      variant="danger"
      dismissible
      class="mb-4"
      @dismiss="errorMessage = null"
    >
      {{ errorMessage }}
    </Alert>

    <form class="space-y-4" @submit.prevent="handleLogin">
      <FormField
        id="email"
        label="Alamat Email"
        required
        :error="fieldErrors.email ? fieldErrors.email[0] : null"
      >
        <Input
          id="email"
          v-model="email"
          type="email"
          placeholder="nama@siakad.ac.id"
          autocomplete="email"
          required
          :disabled="loading"
          size="lg"
        >
          <template #prefix>
            <Mail class="h-4 w-4" />
          </template>
        </Input>
      </FormField>

      <FormField
        id="password"
        label="Kata Sandi"
        required
        :error="fieldErrors.password ? fieldErrors.password[0] : null"
      >
        <Input
          id="password"
          v-model="password"
          :type="passwordType"
          placeholder="••••••••"
          autocomplete="current-password"
          required
          :disabled="loading"
          size="lg"
        >
          <template #prefix>
            <Lock class="h-4 w-4" />
          </template>
          <template #suffix>
            <button
              type="button"
              class="pointer-events-auto -mr-1 rounded-sm p-1 text-slate-400 outline-none transition-colors hover:text-slate-700 focus-visible:ring-2 focus-visible:ring-brand-500"
              :aria-label="showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
              :tabindex="loading ? -1 : 0"
              @click="showPassword = !showPassword"
            >
              <component :is="showPassword ? EyeOff : Eye" class="h-4 w-4" />
            </button>
          </template>
        </Input>
      </FormField>

      <Button type="submit" variant="primary" size="lg" block :loading="loading" class="mt-1">
        <span>Masuk</span>
        <ArrowRight v-if="!loading" class="h-4 w-4" />
      </Button>
    </form>

    <div v-if="isDev" class="mt-6 border-t border-slate-100 pt-5">
      <p class="mb-2.5 text-2xs font-semibold uppercase tracking-wider text-slate-500">
        Akun Uji Lokal (mode pengembangan)
      </p>
      <div class="grid grid-cols-2 gap-1.5 sm:grid-cols-4">
        <button
          v-for="account in devAccounts"
          :key="account.email"
          type="button"
          class="rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5 text-2xs font-medium text-slate-600 transition-colors hover:border-brand-300 hover:bg-brand-50 hover:text-brand-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
          @click="fillDevAccount(account.email)"
        >
          {{ account.label }}
        </button>
      </div>
    </div>
  </div>
</template>
