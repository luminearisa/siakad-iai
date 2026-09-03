<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { classService } from '@/services/api/classes'
import { useToast } from '@/composables/useToast'
import type { AcademicClass, ClassLecturer } from '@/types/class'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Breadcrumb from '@/components/ui/Breadcrumb.vue'
import Tabs, { type TabItem } from '@/components/ui/Tabs.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import Alert from '@/components/ui/Alert.vue'
import ConfirmModal from '@/components/feedback/ConfirmModal.vue'
import ClassHeader from './components/ClassHeader.vue'
import ClassOverviewTab from './tabs/ClassOverviewTab.vue'
import ClassLecturersTab from './tabs/ClassLecturersTab.vue'
import ClassGradesTab from './tabs/ClassGradesTab.vue'
import ClassSessionsTab from './tabs/ClassSessionsTab.vue'
import AddLecturerModal from './components/AddLecturerModal.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const classId = route.params.id as string
const academicClass = ref<AcademicClass | null>(null)
const classLecturers = ref<ClassLecturer[]>([])
const loading = ref<boolean>(true)
const errorMessage = ref<string | null>(null)
const activeTab = ref<string>('overview')

// Check if tab is requested via query param (e.g. ?tab=grades)
if (route.query.tab) {
  activeTab.value = route.query.tab as string
}

// Modals
const addLecturerModalOpen = ref<boolean>(false)
const removeLecturerModalOpen = ref<boolean>(false)
const lecturerToRemove = ref<{ id: number; name: string } | null>(null)
const removeLecturerLoading = ref<boolean>(false)

const openStatusModalOpen = ref<boolean>(false)
const closeStatusModalOpen = ref<boolean>(false)
const cancelStatusModalOpen = ref<boolean>(false)
const deleteModalOpen = ref<boolean>(false)
const actionLoading = ref<boolean>(false)

const tabs = ref<TabItem[]>([
  { id: 'overview', label: 'Ringkasan Kelas' },
  { id: 'sessions', label: 'Sesi Perkuliahan' },
  { id: 'grades', label: 'Input & Rekap Nilai' },
  { id: 'lecturers', label: 'Dosen Pengampu', badge: 0 },
])

async function loadClassData() {
  loading.value = true
  errorMessage.value = null
  try {
    const [classRes, lecRes] = await Promise.all([
      classService.get(classId),
      classService.getLecturers(classId),
    ])
    academicClass.value = classRes.data
    classLecturers.value = lecRes.data || []
    tabs.value[2].badge = classLecturers.value.length
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal memuat data kelas perkuliahan.'
  } finally {
    loading.value = false
  }
}

function handleAddLecturerSuccess() {
  loadClassData()
}

function openRemoveLecturer(lecturerId: number, lecturerName: string) {
  lecturerToRemove.value = { id: lecturerId, name: lecturerName }
  removeLecturerModalOpen.value = true
}

async function handleRemoveLecturer() {
  if (!lecturerToRemove.value) return
  removeLecturerLoading.value = true
  try {
    await classService.removeLecturer(classId, lecturerToRemove.value.id)
    toast.success(`Dosen ${lecturerToRemove.value.name} dilepas dari tugas kelas.`)
    removeLecturerModalOpen.value = false
    lecturerToRemove.value = null
    loadClassData()
  } catch (err: any) {
    toast.error(err.message || 'Gagal melepas tugas dosen.')
  } finally {
    removeLecturerLoading.value = false
  }
}

async function handleOpenClass() {
  actionLoading.value = true
  try {
    await classService.open(classId)
    toast.success('Kelas perkuliahan dibuka untuk KRS.')
    openStatusModalOpen.value = false
    loadClassData()
  } catch (err: any) {
    toast.error(err.message || 'Gagal membuka kelas.')
  } finally {
    actionLoading.value = false
  }
}

async function handleCloseClass() {
  actionLoading.value = true
  try {
    await classService.close(classId)
    toast.success('Kelas perkuliahan ditutup dari KRS.')
    closeStatusModalOpen.value = false
    loadClassData()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menutup kelas.')
  } finally {
    actionLoading.value = false
  }
}

async function handleCancelClass() {
  actionLoading.value = true
  try {
    await classService.cancel(classId)
    toast.success('Kelas perkuliahan dibatalkan.')
    cancelStatusModalOpen.value = false
    loadClassData()
  } catch (err: any) {
    toast.error(err.message || 'Gagal membatalkan kelas.')
  } finally {
    actionLoading.value = false
  }
}

async function handleDelete() {
  actionLoading.value = true
  try {
    await classService.delete(classId)
    toast.success('Kelas perkuliahan berhasil dihapus.')
    deleteModalOpen.value = false
    router.push('/classes')
  } catch (err: any) {
    toast.error(err.message || 'Gagal menghapus kelas perkuliahan.')
  } finally {
    actionLoading.value = false
  }
}

onMounted(() => {
  loadClassData()
})
</script>

<template>
  <PageContainer>
    <div class="mb-3">
      <Breadcrumb
        :items="[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Kelas', to: '/classes' },
          { label: academicClass ? `${academicClass.code} (${academicClass.section})` : 'Detail Kelas' },
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
    <div v-else-if="academicClass" class="space-y-5">
      <!-- Header -->
      <ClassHeader
        :academic-class="academicClass"
        @open-class="openStatusModalOpen = true"
        @close-class="closeStatusModalOpen = true"
        @cancel-class="cancelStatusModalOpen = true"
        @delete="deleteModalOpen = true"
      />

      <!-- Tabs Navigation -->
      <Tabs
        :tabs="tabs"
        :model-value="activeTab"
        @update:model-value="activeTab = $event as string"
      />

      <!-- Tab 1: Overview -->
      <ClassOverviewTab
        v-if="activeTab === 'overview'"
        :academic-class="academicClass"
      />

      <!-- Tab 2: Sessions -->
      <ClassSessionsTab
        v-else-if="activeTab === 'sessions'"
        :class-id="classId"
      />

      <!-- Tab 3: Grades -->
      <ClassGradesTab
        v-else-if="activeTab === 'grades'"
        :academic-class="academicClass"
        :is-read-only="academicClass.status === 'cancelled'"
      />

      <!-- Tab 4: Lecturers -->
      <ClassLecturersTab
        v-else-if="activeTab === 'lecturers'"
        :academic-class="academicClass"
        :class-lecturers="classLecturers"
        :is-read-only="academicClass.status === 'cancelled' || academicClass.status === 'completed'"
        @add-lecturer="addLecturerModalOpen = true"
        @remove-lecturer="openRemoveLecturer"
      />
    </div>

    <!-- Modals -->
    <AddLecturerModal
      v-if="academicClass"
      :open="addLecturerModalOpen"
      :academic-class="academicClass"
      @update:open="addLecturerModalOpen = $event"
      @success="handleAddLecturerSuccess"
    />

    <ConfirmModal
      :open="removeLecturerModalOpen"
      title="Lepas Penugasan Dosen"
      :message="`Apakah Anda yakin ingin melepas penugasan ${lecturerToRemove?.name} dari kelas ini?`"
      confirm-text="Ya, Lepas Tugas"
      variant="danger"
      :loading="removeLecturerLoading"
      @update:open="removeLecturerModalOpen = $event"
      @confirm="handleRemoveLecturer"
    />

    <ConfirmModal
      :open="openStatusModalOpen"
      title="Buka Kelas Perkuliahan"
      :message="`Apakah Anda yakin ingin membuka kelas ${academicClass?.code} untuk pendaftaran KRS?`"
      confirm-text="Ya, Buka Kelas"
      variant="primary"
      :loading="actionLoading"
      @update:open="openStatusModalOpen = $event"
      @confirm="handleOpenClass"
    />

    <ConfirmModal
      :open="closeStatusModalOpen"
      title="Tutup Kelas Perkuliahan"
      :message="`Apakah Anda yakin ingin menutup kelas ${academicClass?.code}? Mahasiswa tidak dapat memilih kelas ini di KRS lagi.`"
      confirm-text="Ya, Tutup Kelas"
      variant="danger"
      :loading="actionLoading"
      @update:open="closeStatusModalOpen = $event"
      @confirm="handleCloseClass"
    />

    <ConfirmModal
      :open="cancelStatusModalOpen"
      title="Batalkan Kelas Perkuliahan"
      :message="`Apakah Anda yakin ingin membatalkan kelas ${academicClass?.code}?`"
      confirm-text="Ya, Batalkan Kelas"
      variant="danger"
      :loading="actionLoading"
      @update:open="cancelStatusModalOpen = $event"
      @confirm="handleCancelClass"
    />

    <ConfirmModal
      :open="deleteModalOpen"
      title="Hapus Kelas Perkuliahan"
      :message="`Apakah Anda yakin ingin menghapus kelas ${academicClass?.code}?`"
      confirm-text="Ya, Hapus Kelas"
      variant="danger"
      :loading="actionLoading"
      @update:open="deleteModalOpen = $event"
      @confirm="handleDelete"
    />
  </PageContainer>
</template>
