<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { lecturerService } from '@/services/api/lecturers'
import { advisingService } from '@/services/api/advising'
import { enrollmentService } from '@/services/api/enrollments'
import { useToast } from '@/composables/useToast'
import type { AcademicAdvisor, AdvisingSession } from '@/types/advising'
import type { Lecturer } from '@/types/lecturer'
import type { StudentEnrollment } from '@/types/enrollment'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Breadcrumb from '@/components/ui/Breadcrumb.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import Alert from '@/components/ui/Alert.vue'
import Tabs, { type TabItem } from '@/components/ui/Tabs.vue'
import ConfirmModal from '@/components/feedback/ConfirmModal.vue'
import AdvisorHeader from './components/AdvisorHeader.vue'
import AssignStudentModal from './components/AssignStudentModal.vue'
import ReassignStudentModal from './components/ReassignStudentModal.vue'
import AdvisingSessionModal from './components/AdvisingSessionModal.vue'
import AdvisorOverviewTab from './tabs/AdvisorOverviewTab.vue'
import AdviseesTab from './tabs/AdviseesTab.vue'
import SessionsTab from './tabs/SessionsTab.vue'
import KrsReviewTab from './tabs/KrsReviewTab.vue'
import HistoryTab from './tabs/HistoryTab.vue'

const route = useRoute()
const toast = useToast()

const lecturerId = route.params.id as string

const lecturer = ref<Lecturer | null>(null)
const advisees = ref<AcademicAdvisor[]>([])
const sessions = ref<AdvisingSession[]>([])
const enrollments = ref<StudentEnrollment[]>([])

const pageLoading = ref<boolean>(true)
const actionLoading = ref<boolean>(false)
const errorMessage = ref<string | null>(null)

const activeTab = ref<string>((route.query.tab as string) || 'overview')

// Modals
const assignModalOpen = ref<boolean>(false)
const reassignModalOpen = ref<boolean>(false)
const selectedAdvisorForReassign = ref<AcademicAdvisor | null>(null)

const sessionModalOpen = ref<boolean>(false)
const sessionToEdit = ref<AdvisingSession | null>(null)
const presetAdvisorForSession = ref<AcademicAdvisor | null>(null)

const deleteSessionModalOpen = ref<boolean>(false)
const sessionToDelete = ref<AdvisingSession | null>(null)
const deletingSession = ref<boolean>(false)

const activeAdvisees = computed(() => advisees.value.filter((a) => a.status === 'active'))
const pendingKrsList = computed(() =>
  enrollments.value.filter((e) => e.status === 'submitted')
)

const tabs = computed<TabItem[]>(() => [
  { id: 'overview', label: 'Ringkasan & Statistik' },
  { id: 'advisees', label: 'Mahasiswa Bimbingan', badge: activeAdvisees.value.length },
  { id: 'sessions', label: 'Catatan Sesi Bimbingan', badge: sessions.value.length },
  { id: 'krs_review', label: 'Review KRS Mahasiswa', badge: pendingKrsList.value.length > 0 ? pendingKrsList.value.length : undefined },
  { id: 'history', label: 'Riwayat Penugasan' },
])

async function loadAdvisorData() {
  pageLoading.value = true
  errorMessage.value = null
  try {
    const [lecRes, advRes, sessRes] = await Promise.all([
      lecturerService.get(lecturerId),
      advisingService.listAdvisors({ lecturer_id: lecturerId, per_page: 100 }),
      advisingService.listSessions({ lecturer_id: lecturerId, per_page: 100 }),
    ])

    lecturer.value = lecRes.data
    advisees.value = advRes.data || []
    sessions.value = sessRes.data || []

    // Load enrollments for advisees
    try {
      const enrRes = await enrollmentService.list({ per_page: 100 })
      const allEnrollments = enrRes.data || []
      const adviseeStudentIds = new Set(advisees.value.map((a) => a.student_id))
      enrollments.value = allEnrollments.filter((e) => adviseeStudentIds.has(e.student_id))
    } catch {
      enrollments.value = []
    }
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal memuat data Dosen Pembimbing Akademik.'
  } finally {
    pageLoading.value = false
  }
}

// Modal Handlers
function openAssignModal() {
  assignModalOpen.value = true
}

function openReassignModal(advisor: AcademicAdvisor) {
  selectedAdvisorForReassign.value = advisor
  reassignModalOpen.value = true
}

function openCreateSession(advisor?: AcademicAdvisor) {
  sessionToEdit.value = null
  presetAdvisorForSession.value = advisor || null
  sessionModalOpen.value = true
}

function openEditSession(session: AdvisingSession) {
  sessionToEdit.value = session
  presetAdvisorForSession.value = null
  sessionModalOpen.value = true
}

function confirmDeleteSession(session: AdvisingSession) {
  sessionToDelete.value = session
  deleteSessionModalOpen.value = true
}

async function handleDeleteSession() {
  if (!sessionToDelete.value) return
  deletingSession.value = true
  try {
    await advisingService.deleteSession(sessionToDelete.value.id)
    toast.success('Sesi bimbingan berhasil dihapus.')
    deleteSessionModalOpen.value = false
    sessionToDelete.value = null
    await loadAdvisorData()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menghapus sesi bimbingan.')
  } finally {
    deletingSession.value = false
  }
}

// KRS Review Handlers
async function handleApproveKrs(payload: { enrollment: StudentEnrollment; notes?: string }) {
  actionLoading.value = true
  try {
    await enrollmentService.approve(payload.enrollment.id, payload.notes)
    toast.success(`KRS ${payload.enrollment.student?.full_name} berhasil disetujui.`)
    await loadAdvisorData()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menyetujui KRS.')
  } finally {
    actionLoading.value = false
  }
}

async function handleRequestRevisionKrs(payload: { enrollment: StudentEnrollment; notes: string }) {
  actionLoading.value = true
  try {
    await enrollmentService.requestRevision(payload.enrollment.id, payload.notes)
    toast.success(`Permintaan revisi KRS untuk ${payload.enrollment.student?.full_name} berhasil dikirim.`)
    await loadAdvisorData()
  } catch (err: any) {
    toast.error(err.message || 'Gagal meminta revisi KRS.')
  } finally {
    actionLoading.value = false
  }
}

async function handleRejectKrs(payload: { enrollment: StudentEnrollment; reason: string }) {
  actionLoading.value = true
  try {
    await enrollmentService.reject(payload.enrollment.id, payload.reason)
    toast.success(`KRS ${payload.enrollment.student?.full_name} berhasil ditolak.`)
    await loadAdvisorData()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menolak KRS.')
  } finally {
    actionLoading.value = false
  }
}

onMounted(() => {
  loadAdvisorData()
})
</script>

<template>
  <PageContainer>
    <div class="mb-3">
      <Breadcrumb
        :items="[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Bimbingan Akademik', to: '/advising' },
          { label: lecturer ? `PA - ${lecturer.full_name}` : 'Workspace Dosen PA' },
        ]"
      />
    </div>

    <!-- Loading Skeleton -->
    <div v-if="pageLoading" class="space-y-4">
      <Skeleton height="6rem" rounded="lg" />
      <Skeleton height="3rem" rounded="lg" />
      <Skeleton height="18rem" rounded="lg" />
    </div>

    <!-- Error Alert -->
    <Alert v-else-if="errorMessage && !lecturer" variant="danger">
      {{ errorMessage }}
    </Alert>

    <!-- Workspace Content -->
    <div v-else-if="lecturer" class="space-y-5">
      <!-- Header -->
      <AdvisorHeader
        :lecturer="lecturer"
        :active-advisees-count="activeAdvisees.length"
        :loading="actionLoading"
        @assign-student="openAssignModal"
        @create-session="openCreateSession()"
      />

      <!-- Navigation Tabs -->
      <Tabs
        v-model="activeTab"
        :tabs="tabs"
      />

      <!-- Tab 1: Overview -->
      <AdvisorOverviewTab
        v-if="activeTab === 'overview'"
        :lecturer="lecturer"
        :advisees="advisees"
        :sessions="sessions"
        :pending-krs-count="pendingKrsList.length"
        :loading="pageLoading"
        @switch-tab="activeTab = $event"
        @create-session="openCreateSession()"
        @assign-student="openAssignModal"
      />

      <!-- Tab 2: Advisees -->
      <AdviseesTab
        v-else-if="activeTab === 'advisees'"
        :lecturer="lecturer"
        :advisees="advisees"
        :loading="pageLoading"
        @assign-student="openAssignModal"
        @create-session="openCreateSession($event)"
        @reassign="openReassignModal($event)"
      />

      <!-- Tab 3: Sessions -->
      <SessionsTab
        v-else-if="activeTab === 'sessions'"
        :lecturer="lecturer"
        :advisees="activeAdvisees"
        :sessions="sessions"
        :loading="pageLoading"
        @create-session="openCreateSession()"
        @edit-session="openEditSession($event)"
        @delete-session="confirmDeleteSession($event)"
      />

      <!-- Tab 4: KRS Review -->
      <KrsReviewTab
        v-else-if="activeTab === 'krs_review'"
        :lecturer="lecturer"
        :enrollments="enrollments"
        :loading="actionLoading"
        @approve="handleApproveKrs"
        @request-revision="handleRequestRevisionKrs"
        @reject="handleRejectKrs"
        @refresh="loadAdvisorData"
      />

      <!-- Tab 5: History -->
      <HistoryTab
        v-else-if="activeTab === 'history'"
        :history="advisees"
        :loading="pageLoading"
      />
    </div>

    <!-- MODAL: Assign Student -->
    <AssignStudentModal
      v-if="lecturer"
      :open="assignModalOpen"
      :lecturer="lecturer"
      @update:open="assignModalOpen = $event"
      @assigned="loadAdvisorData"
    />

    <!-- MODAL: Reassign Student -->
    <ReassignStudentModal
      :open="reassignModalOpen"
      :advisor="selectedAdvisorForReassign"
      @update:open="reassignModalOpen = $event"
      @reassigned="loadAdvisorData"
    />

    <!-- MODAL: Log / Edit Advising Session -->
    <AdvisingSessionModal
      v-if="lecturer"
      :open="sessionModalOpen"
      :lecturer="lecturer"
      :advisees="activeAdvisees"
      :session-to-edit="sessionToEdit"
      :preset-advisor="presetAdvisorForSession"
      @update:open="sessionModalOpen = $event"
      @saved="loadAdvisorData"
    />

    <!-- MODAL: Delete Session Confirmation -->
    <ConfirmModal
      :open="deleteSessionModalOpen"
      title="Hapus Sesi Bimbingan"
      message="Apakah Anda yakin ingin menghapus catatan sesi bimbingan akademik ini? Data yang dihapus tidak dapat dipulihkan kembali."
      confirm-text="Ya, Hapus Sesi"
      variant="danger"
      :loading="deletingSession"
      @update:open="deleteSessionModalOpen = $event"
      @confirm="handleDeleteSession"
    />
  </PageContainer>
</template>
