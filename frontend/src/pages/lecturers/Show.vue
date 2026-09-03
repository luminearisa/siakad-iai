<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { lecturerService } from '@/services/api/lecturers'
import { useToast } from '@/composables/useToast'
import type { Lecturer } from '@/types/lecturer'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Breadcrumb from '@/components/ui/Breadcrumb.vue'
import Tabs, { type TabItem } from '@/components/ui/Tabs.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import Alert from '@/components/ui/Alert.vue'
import ConfirmModal from '@/components/feedback/ConfirmModal.vue'
import LecturerHeader from './components/LecturerHeader.vue'
import ChangeLecturerStatusModal from './components/ChangeLecturerStatusModal.vue'
import LecturerOverviewTab from './tabs/LecturerOverviewTab.vue'
import LecturerAcademicTab from './tabs/LecturerAcademicTab.vue'
import LecturerEducationTab from './tabs/LecturerEducationTab.vue'
import LecturerExpertiseTab from './tabs/LecturerExpertiseTab.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const lecturerId = route.params.id as string
const lecturer = ref<Lecturer | null>(null)
const loading = ref<boolean>(true)
const errorMessage = ref<string | null>(null)
const activeTab = ref<string>('overview')

// Modals
const statusModalOpen = ref<boolean>(false)
const deleteModalOpen = ref<boolean>(false)
const deleteLoading = ref<boolean>(false)

const tabs = ref<TabItem[]>([
  { id: 'overview', label: 'Biodata & Identitas' },
  { id: 'academic', label: 'Homebase & Jabatan' },
  { id: 'education', label: 'Riwayat Pendidikan', badge: 0 },
  { id: 'expertise', label: 'Bidang Kepakaran', badge: 0 },
])

async function loadLecturer() {
  loading.value = true
  errorMessage.value = null
  try {
    const res = await lecturerService.get(lecturerId)
    lecturer.value = res.data
    updateTabBadges(res.data)
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal memuat data dosen.'
  } finally {
    loading.value = false
  }
}

function updateTabBadges(data: Lecturer) {
  tabs.value[2].badge = data.educations?.length || 0
  tabs.value[3].badge = data.expertises?.length || 0
}

function handleStatusSuccess(updated: Lecturer) {
  if (lecturer.value) {
    lecturer.value.status = updated.status
  }
}

async function handleDelete() {
  if (!lecturer.value) return
  deleteLoading.value = true
  try {
    await lecturerService.delete(lecturer.value.id)
    toast.success('Data dosen berhasil dihapus.')
    deleteModalOpen.value = false
    router.push('/lecturers')
  } catch (err: any) {
    toast.error(err.message || 'Gagal menghapus data dosen.')
  } finally {
    deleteLoading.value = false
  }
}

onMounted(() => {
  loadLecturer()
})
</script>

<template>
  <PageContainer>
    <div class="mb-3">
      <Breadcrumb
        :items="[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Dosen', to: '/lecturers' },
          { label: lecturer ? `${lecturer.full_name}` : 'Detail Dosen' },
        ]"
      />
    </div>

    <!-- Loading Skeleton -->
    <div v-if="loading" class="space-y-4">
      <Skeleton height="5rem" rounded="lg" />
      <Skeleton height="2.5rem" rounded="md" width="40%" />
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <Skeleton height="15rem" rounded="lg" />
        <Skeleton height="15rem" rounded="lg" />
      </div>
    </div>

    <!-- Error State -->
    <Alert v-else-if="errorMessage" variant="danger">
      {{ errorMessage }}
    </Alert>

    <!-- Content -->
    <div v-else-if="lecturer" class="space-y-5">
      <!-- Profile Header -->
      <LecturerHeader
        :lecturer="lecturer"
        @change-status="statusModalOpen = true"
        @delete="deleteModalOpen = true"
      />

      <!-- Tabs Navigation -->
      <Tabs
        :tabs="tabs"
        :model-value="activeTab"
        @update:model-value="activeTab = $event as string"
      />

      <!-- Tab Panels -->
      <div class="pt-1">
        <LecturerOverviewTab v-if="activeTab === 'overview'" :lecturer="lecturer" />
        <LecturerAcademicTab v-else-if="activeTab === 'academic'" :lecturer="lecturer" />
        <LecturerEducationTab v-else-if="activeTab === 'education'" :lecturer="lecturer" />
        <LecturerExpertiseTab v-else-if="activeTab === 'expertise'" :lecturer="lecturer" />
      </div>
    </div>

    <!-- Modals -->
    <ChangeLecturerStatusModal
      v-if="lecturer"
      :open="statusModalOpen"
      :lecturer="lecturer"
      @update:open="statusModalOpen = $event"
      @success="handleStatusSuccess"
    />

    <ConfirmModal
      :open="deleteModalOpen"
      title="Hapus Data Dosen"
      :message="`Apakah Anda yakin ingin menghapus data dosen ${lecturer?.full_name}? Seluruh data terkait akan dihapus secara permanen.`"
      confirm-text="Ya, Hapus Dosen"
      variant="danger"
      :loading="deleteLoading"
      @update:open="deleteModalOpen = $event"
      @confirm="handleDelete"
    />
  </PageContainer>
</template>
