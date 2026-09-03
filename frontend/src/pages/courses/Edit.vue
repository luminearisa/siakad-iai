<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { courseService } from '@/services/api/courses'
import { useToast } from '@/composables/useToast'
import type { Course, CourseUpdatePayload } from '@/types/course'
import PageContainer from '@/components/data-display/PageContainer.vue'
import PageHeader from '@/components/data-display/PageHeader.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import Alert from '@/components/ui/Alert.vue'
import CourseForm from './components/CourseForm.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const courseId = route.params.id as string
const course = ref<Course | null>(null)
const pageLoading = ref<boolean>(true)
const saveLoading = ref<boolean>(false)
const errorMessage = ref<string | null>(null)
const serverErrors = ref<Record<string, string[]>>({})

async function loadCourse() {
  pageLoading.value = true
  errorMessage.value = null
  try {
    const res = await courseService.get(courseId)
    course.value = res.data
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal memuat data mata kuliah.'
  } finally {
    pageLoading.value = false
  }
}

async function handleSubmit(payload: any) {
  errorMessage.value = null
  serverErrors.value = {}
  saveLoading.value = true

  try {
    const res = await courseService.update(courseId, payload as CourseUpdatePayload)
    toast.success(`Mata kuliah ${res.data.name} berhasil diperbarui.`)
    router.push(`/courses/${courseId}`)
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal memperbarui data mata kuliah.'
    if (err.errors) {
      serverErrors.value = err.errors
    }
  } finally {
    saveLoading.value = false
  }
}

onMounted(() => {
  loadCourse()
})
</script>

<template>
  <PageContainer>
    <PageHeader
      title="Edit Mata Kuliah"
      :subtitle="course ? `Memperbarui konfigurasi ${course.code} — ${course.name}` : 'Memperbarui data mata kuliah'"
      :breadcrumbs="[
        { label: 'Dashboard', to: '/dashboard' },
        { label: 'Mata Kuliah', to: '/courses' },
        { label: course ? course.code : 'Detail', to: `/courses/${courseId}` },
        { label: 'Edit' },
      ]"
    />

    <!-- Skeleton Loading -->
    <div v-if="pageLoading" class="space-y-4">
      <Skeleton height="12rem" rounded="lg" />
      <Skeleton height="8rem" rounded="lg" />
    </div>

    <!-- Error Alert -->
    <Alert v-else-if="errorMessage && !course" variant="danger">
      {{ errorMessage }}
    </Alert>

    <!-- Edit Form -->
    <CourseForm
      v-else-if="course"
      :initial-data="course"
      is-edit
      :loading="saveLoading"
      :error-message="errorMessage"
      :server-errors="serverErrors"
      @submit="handleSubmit"
    />
  </PageContainer>
</template>
