<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { classService } from '@/services/api/classes'
import { useToast } from '@/composables/useToast'
import type { ClassCreatePayload } from '@/types/class'
import PageContainer from '@/components/data-display/PageContainer.vue'
import PageHeader from '@/components/data-display/PageHeader.vue'
import ClassForm from './components/ClassForm.vue'

const router = useRouter()
const toast = useToast()

const loading = ref<boolean>(false)
const errorMessage = ref<string | null>(null)
const serverErrors = ref<Record<string, string[]>>({})

async function handleSubmit(payload: any) {
  errorMessage.value = null
  serverErrors.value = {}
  loading.value = true

  try {
    const res = await classService.create(payload as ClassCreatePayload)
    toast.success(`Kelas perkuliahan ${res.data.code} berhasil dibuka.`)
    router.push(`/classes/${res.data.id}`)
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal membuka kelas perkuliahan.'
    if (err.errors) {
      serverErrors.value = err.errors
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <PageContainer>
    <PageHeader
      title="Buka Kelas Perkuliahan Baru"
      subtitle="Definisikan mata kuliah, seksi/paralel, semester aktif, dan kuota mahasiswa"
      :breadcrumbs="[
        { label: 'Dashboard', to: '/dashboard' },
        { label: 'Kelas', to: '/classes' },
        { label: 'Buka Kelas' },
      ]"
    />

    <ClassForm
      :loading="loading"
      :error-message="errorMessage"
      :server-errors="serverErrors"
      @submit="handleSubmit"
    />
  </PageContainer>
</template>
