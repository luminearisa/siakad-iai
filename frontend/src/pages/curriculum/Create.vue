<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { curriculumService } from '@/services/api/curriculum'
import { useToast } from '@/composables/useToast'
import type { CurriculumCreatePayload } from '@/types/curriculum'
import PageContainer from '@/components/data-display/PageContainer.vue'
import PageHeader from '@/components/data-display/PageHeader.vue'
import CurriculumForm from './components/CurriculumForm.vue'

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
    const res = await curriculumService.create(payload as CurriculumCreatePayload)
    toast.success(`Kurikulum ${res.data.name} berhasil dibuat.`)
    router.push(`/curriculum/${res.data.id}`)
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal membuat kurikulum.'
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
      title="Buat Kurikulum Baru"
      subtitle="Definisikan dokumen kurikulum program studi, versi, dan periode keberlakuan"
      :breadcrumbs="[
        { label: 'Dashboard', to: '/dashboard' },
        { label: 'Kurikulum', to: '/curriculum' },
        { label: 'Tambah Baru' },
      ]"
    />

    <CurriculumForm
      :loading="loading"
      :error-message="errorMessage"
      :server-errors="serverErrors"
      @submit="handleSubmit"
    />
  </PageContainer>
</template>
