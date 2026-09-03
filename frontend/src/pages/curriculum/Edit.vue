<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { curriculumService } from '@/services/api/curriculum'
import { useToast } from '@/composables/useToast'
import type { Curriculum, CurriculumUpdatePayload } from '@/types/curriculum'
import PageContainer from '@/components/data-display/PageContainer.vue'
import PageHeader from '@/components/data-display/PageHeader.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import Alert from '@/components/ui/Alert.vue'
import CurriculumForm from './components/CurriculumForm.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const curriculumId = route.params.id as string
const curriculum = ref<Curriculum | null>(null)
const pageLoading = ref<boolean>(true)
const saveLoading = ref<boolean>(false)
const errorMessage = ref<string | null>(null)
const serverErrors = ref<Record<string, string[]>>({})

async function loadCurriculum() {
  pageLoading.value = true
  errorMessage.value = null
  try {
    const res = await curriculumService.get(curriculumId)
    curriculum.value = res.data
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal memuat data kurikulum.'
  } finally {
    pageLoading.value = false
  }
}

async function handleSubmit(payload: any) {
  errorMessage.value = null
  serverErrors.value = {}
  saveLoading.value = true

  try {
    const res = await curriculumService.update(curriculumId, payload as CurriculumUpdatePayload)
    toast.success(`Kurikulum ${res.data.name} berhasil diperbarui.`)
    router.push(`/curriculum/${curriculumId}`)
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal memperbarui data kurikulum.'
    if (err.errors) {
      serverErrors.value = err.errors
    }
  } finally {
    saveLoading.value = false
  }
}

onMounted(() => {
  loadCurriculum()
})
</script>

<template>
  <PageContainer>
    <PageHeader
      title="Edit Kurikulum"
      :subtitle="curriculum ? `Memperbarui dokumen ${curriculum.code} — ${curriculum.name}` : 'Memperbarui dokumen kurikulum'"
      :breadcrumbs="[
        { label: 'Dashboard', to: '/dashboard' },
        { label: 'Kurikulum', to: '/curriculum' },
        { label: curriculum ? curriculum.code : 'Detail', to: `/curriculum/${curriculumId}` },
        { label: 'Edit' },
      ]"
    />

    <!-- Skeleton Loading -->
    <div v-if="pageLoading" class="space-y-4">
      <Skeleton height="12rem" rounded="lg" />
      <Skeleton height="8rem" rounded="lg" />
    </div>

    <!-- Error Alert -->
    <Alert v-else-if="errorMessage && !curriculum" variant="danger">
      {{ errorMessage }}
    </Alert>

    <!-- Edit Form -->
    <CurriculumForm
      v-else-if="curriculum"
      :initial-data="curriculum"
      is-edit
      :loading="saveLoading"
      :error-message="errorMessage"
      :server-errors="serverErrors"
      @submit="handleSubmit"
    />
  </PageContainer>
</template>
