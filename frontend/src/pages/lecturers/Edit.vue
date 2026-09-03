<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { lecturerService } from '@/services/api/lecturers'
import { useToast } from '@/composables/useToast'
import type { Lecturer, LecturerUpdatePayload } from '@/types/lecturer'
import PageContainer from '@/components/data-display/PageContainer.vue'
import PageHeader from '@/components/data-display/PageHeader.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import Alert from '@/components/ui/Alert.vue'
import LecturerForm from './components/LecturerForm.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const lecturerId = route.params.id as string
const lecturer = ref<Lecturer | null>(null)
const pageLoading = ref<boolean>(true)
const saveLoading = ref<boolean>(false)
const errorMessage = ref<string | null>(null)
const serverErrors = ref<Record<string, string[]>>({})

async function loadLecturer() {
  pageLoading.value = true
  errorMessage.value = null
  try {
    const res = await lecturerService.get(lecturerId)
    lecturer.value = res.data
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal memuat data dosen.'
  } finally {
    pageLoading.value = false
  }
}

async function handleSubmit(payload: any) {
  errorMessage.value = null
  serverErrors.value = {}
  saveLoading.value = true

  try {
    const res = await lecturerService.update(lecturerId, payload as LecturerUpdatePayload)
    toast.success(`Data dosen ${res.data.full_name} berhasil diperbarui.`)
    router.push(`/lecturers/${lecturerId}`)
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal memperbarui data dosen.'
    if (err.errors) {
      serverErrors.value = err.errors
    }
  } finally {
    saveLoading.value = false
  }
}

onMounted(() => {
  loadLecturer()
})
</script>

<template>
  <PageContainer>
    <PageHeader
      title="Edit Data Dosen"
      :subtitle="lecturer ? `Memperbarui data ${lecturer.full_name}` : 'Memperbarui profil dosen'"
      :breadcrumbs="[
        { label: 'Dashboard', to: '/dashboard' },
        { label: 'Dosen', to: '/lecturers' },
        { label: lecturer ? lecturer.full_name : 'Detail', to: `/lecturers/${lecturerId}` },
        { label: 'Edit' },
      ]"
    />

    <!-- Skeleton Loading -->
    <div v-if="pageLoading" class="space-y-4">
      <Skeleton height="12rem" rounded="lg" />
      <Skeleton height="12rem" rounded="lg" />
    </div>

    <!-- Error Alert -->
    <Alert v-else-if="errorMessage && !lecturer" variant="danger">
      {{ errorMessage }}
    </Alert>

    <!-- Edit Form -->
    <LecturerForm
      v-else-if="lecturer"
      :initial-data="lecturer"
      is-edit
      :loading="saveLoading"
      :error-message="errorMessage"
      :server-errors="serverErrors"
      @submit="handleSubmit"
    />
  </PageContainer>
</template>
