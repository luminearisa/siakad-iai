<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { curriculumService } from '@/services/api/curriculum'
import { useToast } from '@/composables/useToast'
import type { Curriculum, CurriculumSemester, CurriculumSubject } from '@/types/curriculum'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Breadcrumb from '@/components/ui/Breadcrumb.vue'
import Tabs, { type TabItem } from '@/components/ui/Tabs.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import Alert from '@/components/ui/Alert.vue'
import ConfirmModal from '@/components/feedback/ConfirmModal.vue'
import CurriculumHeader from './components/CurriculumHeader.vue'
import CurriculumOverviewTab from './tabs/CurriculumOverviewTab.vue'
import CurriculumStructureTab from './tabs/CurriculumStructureTab.vue'
import CurriculumSubjectsTab from './tabs/CurriculumSubjectsTab.vue'
import AddCurriculumSemesterModal from './components/AddCurriculumSemesterModal.vue'
import AddCurriculumSubjectModal from './components/AddCurriculumSubjectModal.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const curriculumId = route.params.id as string
const curriculum = ref<Curriculum | null>(null)
const loading = ref<boolean>(true)
const errorMessage = ref<string | null>(null)
const activeTab = ref<string>('overview')

// Modals
const addSemesterModalOpen = ref<boolean>(false)
const addSubjectModalOpen = ref<boolean>(false)
const selectedSemesterForSubject = ref<CurriculumSemester | null>(null)

// Action Modals
const activateModalOpen = ref<boolean>(false)
const activateLoading = ref<boolean>(false)

const archiveModalOpen = ref<boolean>(false)
const archiveLoading = ref<boolean>(false)

const deleteModalOpen = ref<boolean>(false)
const deleteLoading = ref<boolean>(false)

// Remove Subject Modal
const removeSubjectModalOpen = ref<boolean>(false)
const subjectToRemove = ref<{ semester: CurriculumSemester; subject: CurriculumSubject } | null>(null)
const removeSubjectLoading = ref<boolean>(false)

const tabs = ref<TabItem[]>([
  { id: 'overview', label: 'Informasi Umum' },
  { id: 'structure', label: 'Struktur Semester', badge: 0 },
  { id: 'subjects', label: 'Distribusi Mata Kuliah' },
])

async function loadCurriculum() {
  loading.value = true
  errorMessage.value = null
  try {
    const res = await curriculumService.get(curriculumId)
    curriculum.value = res.data
    updateTabBadges(res.data)
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal memuat data kurikulum.'
  } finally {
    loading.value = false
  }
}

function updateTabBadges(data: Curriculum) {
  tabs.value[1].badge = data.semesters?.length || 0
}

function handleAddSemesterSuccess() {
  loadCurriculum()
}

function openAddSubject(semester: CurriculumSemester) {
  selectedSemesterForSubject.value = semester
  addSubjectModalOpen.value = true
}

function handleAddSubjectSuccess() {
  loadCurriculum()
}

function openRemoveSubject(semester: CurriculumSemester, subject: CurriculumSubject) {
  subjectToRemove.value = { semester, subject }
  removeSubjectModalOpen.value = true
}

async function handleRemoveSubject() {
  if (!subjectToRemove.value) return
  removeSubjectLoading.value = true
  try {
    await curriculumService.removeSubject(
      subjectToRemove.value.semester.id,
      subjectToRemove.value.subject.id
    )
    toast.success('Mata kuliah berhasil dihapus dari kurikulum.')
    removeSubjectModalOpen.value = false
    subjectToRemove.value = null
    loadCurriculum()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menghapus mata kuliah dari kurikulum.')
  } finally {
    removeSubjectLoading.value = false
  }
}

async function handleActivate() {
  if (!curriculum.value) return
  activateLoading.value = true
  try {
    await curriculumService.activate(curriculum.value.id)
    toast.success('Kurikulum berhasil diaktifkan.')
    activateModalOpen.value = false
    loadCurriculum()
  } catch (err: any) {
    toast.error(err.message || 'Gagal mengaktifkan kurikulum.')
  } finally {
    activateLoading.value = false
  }
}

async function handleArchive() {
  if (!curriculum.value) return
  archiveLoading.value = true
  try {
    await curriculumService.archive(curriculum.value.id)
    toast.success('Kurikulum berhasil diarsipkan.')
    archiveModalOpen.value = false
    loadCurriculum()
  } catch (err: any) {
    toast.error(err.message || 'Gagal mengarsipkan kurikulum.')
  } finally {
    archiveLoading.value = false
  }
}

async function handleDelete() {
  if (!curriculum.value) return
  deleteLoading.value = true
  try {
    await curriculumService.delete(curriculum.value.id)
    toast.success('Kurikulum berhasil dihapus.')
    deleteModalOpen.value = false
    router.push('/curriculum')
  } catch (err: any) {
    toast.error(err.message || 'Gagal menghapus kurikulum.')
  } finally {
    deleteLoading.value = false
  }
}

onMounted(() => {
  loadCurriculum()
})
</script>

<template>
  <PageContainer>
    <div class="mb-3">
      <Breadcrumb
        :items="[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Kurikulum', to: '/curriculum' },
          { label: curriculum ? `${curriculum.code} — ${curriculum.name}` : 'Detail Kurikulum' },
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
    <div v-else-if="curriculum" class="space-y-5">
      <!-- Header -->
      <CurriculumHeader
        :curriculum="curriculum"
        @activate="activateModalOpen = true"
        @archive="archiveModalOpen = true"
        @delete="deleteModalOpen = true"
      />

      <!-- Tabs Navigation -->
      <Tabs
        :tabs="tabs"
        :model-value="activeTab"
        @update:model-value="activeTab = $event as string"
      />

      <!-- Tab 1: Overview -->
      <CurriculumOverviewTab
        v-if="activeTab === 'overview'"
        :curriculum="curriculum"
      />

      <!-- Tab 2: Structure -->
      <CurriculumStructureTab
        v-else-if="activeTab === 'structure'"
        :curriculum="curriculum"
        @add-semester="addSemesterModalOpen = true"
        @select-semester="activeTab = 'subjects'"
      />

      <!-- Tab 3: Subjects -->
      <CurriculumSubjectsTab
        v-else-if="activeTab === 'subjects'"
        :curriculum="curriculum"
        @add-semester="addSemesterModalOpen = true"
        @add-subject="openAddSubject"
        @remove-subject="openRemoveSubject"
      />
    </div>

    <!-- Modals -->
    <AddCurriculumSemesterModal
      v-if="curriculum"
      :open="addSemesterModalOpen"
      :curriculum="curriculum"
      :next-semester-number="(curriculum.semesters?.length || 0) + 1"
      @update:open="addSemesterModalOpen = $event"
      @success="handleAddSemesterSuccess"
    />

    <AddCurriculumSubjectModal
      v-if="selectedSemesterForSubject"
      :open="addSubjectModalOpen"
      :semester="selectedSemesterForSubject"
      @update:open="addSubjectModalOpen = $event"
      @success="handleAddSubjectSuccess"
    />

    <ConfirmModal
      :open="activateModalOpen"
      title="Aktivasi Kurikulum"
      :message="`Apakah Anda yakin ingin mengaktifkan kurikulum ${curriculum?.name}? Kurikulum aktif akan dijadikan acuan utama.`"
      confirm-text="Ya, Aktifkan Kurikulum"
      variant="primary"
      :loading="activateLoading"
      @update:open="activateModalOpen = $event"
      @confirm="handleActivate"
    />

    <ConfirmModal
      :open="archiveModalOpen"
      title="Arsipkan Kurikulum"
      :message="`Apakah Anda yakin ingin mengarsipkan kurikulum ${curriculum?.name}? Kurikulum yang diarsipkan tidak dapat diubah kembali.`"
      confirm-text="Ya, Arsipkan"
      variant="danger"
      :loading="archiveLoading"
      @update:open="archiveModalOpen = $event"
      @confirm="handleArchive"
    />

    <ConfirmModal
      :open="deleteModalOpen"
      title="Hapus Dokumen Kurikulum"
      :message="`Apakah Anda yakin ingin menghapus kurikulum ${curriculum?.name}? Tindakan ini bersifat permanen.`"
      confirm-text="Ya, Hapus Kurikulum"
      variant="danger"
      :loading="deleteLoading"
      @update:open="deleteModalOpen = $event"
      @confirm="handleDelete"
    />

    <ConfirmModal
      :open="removeSubjectModalOpen"
      title="Hapus Mata Kuliah dari Kurikulum"
      :message="`Apakah Anda yakin ingin menghapus mata kuliah ${subjectToRemove?.subject.course?.name} (${subjectToRemove?.subject.course?.code}) dari Semester ${subjectToRemove?.semester.semester_number}?`"
      confirm-text="Ya, Hapus"
      variant="danger"
      :loading="removeSubjectLoading"
      @update:open="removeSubjectModalOpen = $event"
      @confirm="handleRemoveSubject"
    />
  </PageContainer>
</template>
