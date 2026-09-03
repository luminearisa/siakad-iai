<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { studentService } from '@/services/api/students'
import { useToast } from '@/composables/useToast'
import type { StudentCreatePayload } from '@/types/student'
import PageContainer from '@/components/data-display/PageContainer.vue'
import PageHeader from '@/components/data-display/PageHeader.vue'
import StudentForm from './components/StudentForm.vue'

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
    const res = await studentService.create(payload as StudentCreatePayload)
    toast.success(`Data mahasiswa ${res.data.full_name} (${res.data.student_number}) berhasil disimpan.`)
    router.push(`/students/${res.data.id}`)
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal menyimpan data mahasiswa.'
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
      title="Tambah Mahasiswa Baru"
      subtitle="Registrasi data biodata dan profil akademik mahasiswa baru"
      :breadcrumbs="[
        { label: 'Dashboard', to: '/dashboard' },
        { label: 'Mahasiswa', to: '/students' },
        { label: 'Tambah Mahasiswa' },
      ]"
    />

    <StudentForm
      :loading="loading"
      :error-message="errorMessage"
      :server-errors="serverErrors"
      @submit="handleSubmit"
    />
  </PageContainer>
</template>
