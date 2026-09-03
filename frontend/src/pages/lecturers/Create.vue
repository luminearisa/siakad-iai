<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { lecturerService } from '@/services/api/lecturers'
import { useToast } from '@/composables/useToast'
import type { LecturerCreatePayload } from '@/types/lecturer'
import PageContainer from '@/components/data-display/PageContainer.vue'
import PageHeader from '@/components/data-display/PageHeader.vue'
import LecturerForm from './components/LecturerForm.vue'

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
    const res = await lecturerService.create(payload as LecturerCreatePayload)
    toast.success(`Data dosen ${res.data.full_name} berhasil ditambahkan.`)
    router.push(`/lecturers/${res.data.id}`)
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal menyimpan data dosen.'
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
      title="Tambah Dosen Baru"
      subtitle="Registrasi data profil, NIDN, dan homebase pengajar baru"
      :breadcrumbs="[
        { label: 'Dashboard', to: '/dashboard' },
        { label: 'Dosen', to: '/lecturers' },
        { label: 'Tambah Dosen' },
      ]"
    />

    <LecturerForm
      :loading="loading"
      :error-message="errorMessage"
      :server-errors="serverErrors"
      @submit="handleSubmit"
    />
  </PageContainer>
</template>
