<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { Lock, Mail } from 'lucide-vue-next'
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

const email = ref<string>('admin@siakad.ac.id')
const password = ref<string>('password123')
const loading = ref<boolean>(false)
const errorMessage = ref<string | null>(null)
const fieldErrors = ref<Record<string, string[]>>({})

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

function setDemoAccount(userEmail: string) {
  email.value = userEmail
  password.value = 'password123'
}
</script>

<template>
  <div class="space-y-4">
    <div class="text-center mb-5">
      <h3 class="text-base font-bold text-slate-900 tracking-tight">
        Masuk ke Portal Akademik
      </h3>
      <p class="text-xs text-slate-500 mt-0.5">
        Silakan masukkan email dan password akun Anda
      </p>
    </div>

    <!-- Error Alert -->
    <Alert v-if="errorMessage" variant="danger" dismissible @dismiss="errorMessage = null">
      {{ errorMessage }}
    </Alert>

    <form class="space-y-3.5" @submit.prevent="handleLogin">
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
          size="md"
        >
          <template #prefix>
            <Mail class="w-3.5 h-3.5" />
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
          type="password"
          placeholder="••••••••"
          autocomplete="current-password"
          required
          :disabled="loading"
          size="md"
        >
          <template #prefix>
            <Lock class="w-3.5 h-3.5" />
          </template>
        </Input>
      </FormField>

      <Button
        type="submit"
        variant="primary"
        size="md"
        block
        :loading="loading"
        class="mt-4"
      >
        Masuk ke Akun
      </Button>
    </form>

    <!-- Demo Accounts Quick Switcher -->
    <div class="pt-4 border-t border-slate-100 mt-4 text-center">
      <p class="text-2xs font-semibold text-slate-400 uppercase tracking-wider mb-2">
        Kredensial Demo Cepat:
      </p>
      <div class="flex flex-wrap items-center justify-center gap-1.5">
        <button
          type="button"
          class="text-2xs px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-md font-medium transition-colors cursor-pointer"
          @click="setDemoAccount('admin@siakad.ac.id')"
        >
          Super Admin
        </button>
        <button
          type="button"
          class="text-2xs px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-md font-medium transition-colors cursor-pointer"
          @click="setDemoAccount('akademik@siakad.ac.id')"
        >
          Admin Akademik
        </button>
        <button
          type="button"
          class="text-2xs px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-md font-medium transition-colors cursor-pointer"
          @click="setDemoAccount('dosen@siakad.ac.id')"
        >
          Dosen
        </button>
        <button
          type="button"
          class="text-2xs px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-md font-medium transition-colors cursor-pointer"
          @click="setDemoAccount('mahasiswa@siakad.ac.id')"
        >
          Mahasiswa
        </button>
      </div>
    </div>
  </div>
</template>
