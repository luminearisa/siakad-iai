<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { studentService } from '@/services/api/students'
import { useToast } from '@/composables/useToast'
import type { Student, StudentUpdatePayload } from '@/types/student'
import PageContainer from '@/components/data-display/PageContainer.vue'
import PageHeader from '@/components/data-display/PageHeader.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import Alert from '@/components/ui/Alert.vue'
import StudentForm from './components/StudentForm.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const studentId = route.params.id as string
const student = ref<Student | null>(null)
const pageLoading = ref<boolean>(true)
const saveLoading = ref<boolean>(false)
const errorMessage = ref<string | null>(null)
const serverErrors = ref<Record<string, string[]>>({})

async function loadStudent() {
  pageLoading.value = true
  errorMessage.value = null
  try {
    const res = await studentService.get(studentId)
    student.value = res.data
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal memuat data mahasiswa.'
  } finally {
    pageLoading.value = false
  }
}

async function handleSubmit(payload: any) {
  errorMessage.value = null
  serverErrors.value = {}
  saveLoading.value = true

  try {
    const res = await studentService.update(studentId, payload as StudentUpdatePayload)
    toast.success(`Data mahasiswa ${res.data.full_name} berhasil diperbarui.`)
    router.push(`/students/${studentId}`)
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal memperbarui data mahasiswa.'
    if (err.errors) {
      serverErrors.value = err.errors
    }
  } finally {
    saveLoading.value = false
  }
}

onMounted(() => {
  loadStudent()
})
</script>

<template>
  <PageContainer>
    <PageHeader
      title="Edit Data Mahasiswa"
      :subtitle="student ? `Memperbarui biodata ${student.full_name} (${student.student_number})` : 'Memperbarui data mahasiswa'"
      :breadcrumbs="[
        { label: 'Dashboard', to: '/dashboard' },
        { label: 'Mahasiswa', to: '/students' },
        { label: student ? student.full_name : 'Detail', to: `/students/${studentId}` },
        { label: 'Edit' },
      ]"
    />

    <!-- Skeleton Loading -->
    <div v-if="pageLoading" class="space-y-4">
      <Skeleton height="12rem" rounded="lg" />
      <Skeleton height="12rem" rounded="lg" />
    </div>

    <!-- Error Alert -->
    <Alert v-else-if="errorMessage && !student" variant="danger">
      {{ errorMessage }}
    </Alert>

    <!-- Edit Form -->
    <StudentForm
      v-else-if="student"
      :initial-data="student"
      is-edit
      :loading="saveLoading"
      :error-message="errorMessage"
      :server-errors="serverErrors"
      @submit="handleSubmit"
    />
  </PageContainer>
</template>
