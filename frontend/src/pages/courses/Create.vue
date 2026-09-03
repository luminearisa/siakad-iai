<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { courseService } from '@/services/api/courses'
import { useToast } from '@/composables/useToast'
import type { CourseCreatePayload } from '@/types/course'
import PageContainer from '@/components/data-display/PageContainer.vue'
import PageHeader from '@/components/data-display/PageHeader.vue'
import CourseForm from './components/CourseForm.vue'

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
    const res = await courseService.create(payload as CourseCreatePayload)
    toast.success(`Mata kuliah ${res.data.name} (${res.data.code}) berhasil ditambahkan.`)
    router.push(`/courses/${res.data.id}`)
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal menyimpan mata kuliah.'
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
      title="Tambah Mata Kuliah"
      subtitle="Definisikan kode mata kuliah, unit pengampu, dan komposisi bobot SKS perkuliahan"
      :breadcrumbs="[
        { label: 'Akademik', to: '/dashboard' },
        { label: 'Mata Kuliah', to: '/courses' },
        { label: 'Tambah' },
      ]"
    />

    <CourseForm
      :loading="loading"
      :error-message="errorMessage"
      :server-errors="serverErrors"
      @submit="handleSubmit"
    />
  </PageContainer>
</template>
