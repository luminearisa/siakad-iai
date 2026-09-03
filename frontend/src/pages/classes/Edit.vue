<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { classService } from '@/services/api/classes'
import { useToast } from '@/composables/useToast'
import type { AcademicClass, ClassUpdatePayload } from '@/types/class'
import PageContainer from '@/components/data-display/PageContainer.vue'
import PageHeader from '@/components/data-display/PageHeader.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import Alert from '@/components/ui/Alert.vue'
import ClassForm from './components/ClassForm.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const classId = route.params.id as string
const academicClass = ref<AcademicClass | null>(null)
const pageLoading = ref<boolean>(true)
const saveLoading = ref<boolean>(false)
const errorMessage = ref<string | null>(null)
const serverErrors = ref<Record<string, string[]>>({})

async function loadClass() {
  pageLoading.value = true
  errorMessage.value = null
  try {
    const res = await classService.get(classId)
    academicClass.value = res.data
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal memuat data kelas perkuliahan.'
  } finally {
    pageLoading.value = false
  }
}

async function handleSubmit(payload: any) {
  errorMessage.value = null
  serverErrors.value = {}
  saveLoading.value = true

  try {
    const res = await classService.update(classId, payload as ClassUpdatePayload)
    toast.success(`Kelas ${res.data.code} berhasil diperbarui.`)
    router.push(`/classes/${classId}`)
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal memperbarui data kelas perkuliahan.'
    if (err.errors) {
      serverErrors.value = err.errors
    }
  } finally {
    saveLoading.value = false
  }
}

onMounted(() => {
  loadClass()
})
</script>

<template>
  <PageContainer>
    <PageHeader
      title="Edit Kelas Perkuliahan"
      :subtitle="academicClass ? `Memperbarui konfigurasi ${academicClass.code} (${academicClass.section})` : 'Memperbarui data kelas'"
      :breadcrumbs="[
        { label: 'Dashboard', to: '/dashboard' },
        { label: 'Kelas', to: '/classes' },
        { label: academicClass ? academicClass.code : 'Detail', to: `/classes/${classId}` },
        { label: 'Edit' },
      ]"
    />

    <!-- Skeleton Loading -->
    <div v-if="pageLoading" class="space-y-4">
      <Skeleton height="12rem" rounded="lg" />
      <Skeleton height="8rem" rounded="lg" />
    </div>

    <!-- Error Alert -->
    <Alert v-else-if="errorMessage && !academicClass" variant="danger">
      {{ errorMessage }}
    </Alert>

    <!-- Edit Form -->
    <ClassForm
      v-else-if="academicClass"
      :initial-data="academicClass"
      is-edit
      :loading="saveLoading"
      :error-message="errorMessage"
      :server-errors="serverErrors"
      @submit="handleSubmit"
    />
  </PageContainer>
</template>
