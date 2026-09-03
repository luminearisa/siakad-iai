<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { studentService } from '@/services/api/students'
import { useToast } from '@/composables/useToast'
import type { Student, StudentFamily, StudentEducation } from '@/types/student'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Breadcrumb from '@/components/ui/Breadcrumb.vue'
import Tabs, { type TabItem } from '@/components/ui/Tabs.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import Alert from '@/components/ui/Alert.vue'
import ConfirmModal from '@/components/feedback/ConfirmModal.vue'
import StudentHeader from './components/StudentHeader.vue'
import ChangeStatusModal from './components/ChangeStatusModal.vue'
import AddFamilyModal from './components/AddFamilyModal.vue'
import AddEducationModal from './components/AddEducationModal.vue'
import StudentAccountModal from './components/StudentAccountModal.vue'
import OverviewTab from './tabs/OverviewTab.vue'
import AcademicTab from './tabs/AcademicTab.vue'
import FamilyTab from './tabs/FamilyTab.vue'
import EducationTab from './tabs/EducationTab.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const studentId = route.params.id as string
const student = ref<Student | null>(null)
const loading = ref<boolean>(true)
const errorMessage = ref<string | null>(null)
const activeTab = ref<string>('overview')

// Modals
const statusModalOpen = ref<boolean>(false)
const familyModalOpen = ref<boolean>(false)
const educationModalOpen = ref<boolean>(false)
const deleteModalOpen = ref<boolean>(false)
const deleteLoading = ref<boolean>(false)

// Account Modal
const accountModalOpen = ref<boolean>(false)
const accountModalMode = ref<'create' | 'reset-password'>('create')

const tabs = ref<TabItem[]>([
  { id: 'overview', label: 'Biodata & Identitas' },
  { id: 'academic', label: 'Struktur Akademik' },
  { id: 'family', label: 'Keluarga & Wali', badge: 0 },
  { id: 'education', label: 'Riwayat Pendidikan', badge: 0 },
])

async function loadStudent() {
  loading.value = true
  errorMessage.value = null
  try {
    const res = await studentService.get(studentId)
    student.value = res.data
    updateTabBadges(res.data)
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal memuat data mahasiswa.'
  } finally {
    loading.value = false
  }
}

function updateTabBadges(data: Student) {
  tabs.value[2].badge = data.families?.length || 0
  tabs.value[3].badge = data.educations?.length || 0
}

function handleStatusSuccess(updated: Student) {
  if (student.value) {
    student.value.status = updated.status
  }
}

function handleFamilySuccess(fam: StudentFamily) {
  if (student.value) {
    if (!student.value.families) student.value.families = []
    student.value.families.push(fam)
    updateTabBadges(student.value)
  }
}

function handleEducationSuccess(edu: StudentEducation) {
  if (student.value) {
    if (!student.value.educations) student.value.educations = []
    student.value.educations.push(edu)
    updateTabBadges(student.value)
  }
}

function openCreateAccount() {
  accountModalMode.value = 'create'
  accountModalOpen.value = true
}

function openResetPassword() {
  accountModalMode.value = 'reset-password'
  accountModalOpen.value = true
}

function handleAccountSuccess(updated: Student) {
  student.value = updated
}

async function handleToggleAccountStatus() {
  if (!student.value) return
  try {
    const res = await studentService.toggleAccountStatus(student.value.id)
    student.value = res.data
    toast.success('Status akun mahasiswa berhasil diperbarui.')
  } catch (err: any) {
    toast.error(err.response?.data?.message || err.message || 'Gagal mengubah status akun.')
  }
}

async function handleDelete() {
  if (!student.value) return
  deleteLoading.value = true
  try {
    await studentService.delete(student.value.id)
    toast.success('Data mahasiswa berhasil dihapus.')
    deleteModalOpen.value = false
    router.push('/students')
  } catch (err: any) {
    toast.error(err.message || 'Gagal menghapus data mahasiswa.')
  } finally {
    deleteLoading.value = false
  }
}

onMounted(() => {
  loadStudent()
})
</script>

<template>
  <PageContainer>
    <div class="mb-3">
      <Breadcrumb
        :items="[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Mahasiswa', to: '/students' },
          { label: student ? `${student.full_name} (${student.student_number})` : 'Detail Mahasiswa' },
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

    <!-- Student Detail Content -->
    <div v-else-if="student" class="space-y-5">
      <!-- Profile Header -->
      <StudentHeader
        :student="student"
        @change-status="statusModalOpen = true"
        @delete="deleteModalOpen = true"
        @create-account="openCreateAccount"
        @reset-password="openResetPassword"
      />

      <!-- Tabs Navigation -->
      <Tabs
        :tabs="tabs"
        :model-value="activeTab"
        @update:model-value="activeTab = $event as string"
      />

      <!-- Tab Panels -->
      <div class="pt-1">
        <OverviewTab
          v-if="activeTab === 'overview'"
          :student="student"
          @create-account="openCreateAccount"
          @reset-password="openResetPassword"
          @toggle-account-status="handleToggleAccountStatus"
        />
        <AcademicTab v-else-if="activeTab === 'academic'" :student="student" />
        <FamilyTab
          v-else-if="activeTab === 'family'"
          :student="student"
          @add-family="familyModalOpen = true"
        />
        <EducationTab
          v-else-if="activeTab === 'education'"
          :student="student"
          @add-education="educationModalOpen = true"
        />
      </div>
    </div>

    <!-- Modals -->
    <StudentAccountModal
      v-if="student"
      :open="accountModalOpen"
      :student="student"
      :mode="accountModalMode"
      @update:open="accountModalOpen = $event"
      @success="handleAccountSuccess"
    />

    <ChangeStatusModal
      v-if="student"
      :open="statusModalOpen"
      :student="student"
      @update:open="statusModalOpen = $event"
      @success="handleStatusSuccess"
    />

    <AddFamilyModal
      v-if="student"
      :open="familyModalOpen"
      :student="student"
      @update:open="familyModalOpen = $event"
      @success="handleFamilySuccess"
    />

    <AddEducationModal
      v-if="student"
      :open="educationModalOpen"
      :student="student"
      @update:open="educationModalOpen = $event"
      @success="handleEducationSuccess"
    />

    <ConfirmModal
      :open="deleteModalOpen"
      title="Hapus Data Mahasiswa"
      :message="`Apakah Anda yakin ingin menghapus mahasiswa ${student?.full_name}? Seluruh data terkait akan dihapus secara permanen.`"
      confirm-text="Ya, Hapus Mahasiswa"
      variant="danger"
      :loading="deleteLoading"
      @update:open="deleteModalOpen = $event"
      @confirm="handleDelete"
    />
  </PageContainer>
</template>
