<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  Plus,
  Edit,
  Trash2,
  QrCode,
  CheckCircle,
  ChevronLeft,
} from 'lucide-vue-next'
import { attendanceService } from '@/services/api/attendance'
import { classService } from '@/services/api/classes'
import { useToast } from '@/composables/useToast'
import type { AcademicClass } from '@/types/class'
import type {
  ClassAttendanceRecap,
  TeachingSession,
} from '@/types/attendance'
import PageContainer from '@/components/data-display/PageContainer.vue'
import PageHeader from '@/components/data-display/PageHeader.vue'
import Tabs, { type TabItem } from '@/components/ui/Tabs.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Badge from '@/components/ui/Badge.vue'
import ConfirmModal from '@/components/feedback/ConfirmModal.vue'
import TeachingMethodBadge from './components/TeachingMethodBadge.vue'
import ExamEligibilityBadge from './components/ExamEligibilityBadge.vue'
import SessionModal from './components/SessionModal.vue'
import QuickCheckInModal from './components/QuickCheckInModal.vue'
import BatchAttendanceModal from './components/BatchAttendanceModal.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const classId = computed(() => Number(route.params.id))
const activeTab = ref<string>('matrix')

const academicClass = ref<AcademicClass | null>(null)
const recapData = ref<ClassAttendanceRecap | null>(null)
const sessions = ref<TeachingSession[]>([])
const loading = ref<boolean>(false)

// Modals
const sessionModalOpen = ref<boolean>(false)
const selectedSession = ref<TeachingSession | null>(null)
const quickCheckInModalOpen = ref<boolean>(false)
const batchModalOpen = ref<boolean>(false)
const activeSessionForAction = ref<TeachingSession | null>(null)

const deleteModalOpen = ref<boolean>(false)
const sessionToDelete = ref<TeachingSession | null>(null)
const deleting = ref<boolean>(false)

const tabs: TabItem[] = [
  { id: 'matrix', label: 'Matriks Kehadiran (1–16)' },
  { id: 'bap', label: 'Berita Acara Perkuliahan (BAP)' },
  { id: 'sessions', label: 'Daftar Sesi Pertemuan' },
]

const nextMeetingNumber = computed(() => {
  if (sessions.value.length === 0) return 1
  const maxNumber = Math.max(...sessions.value.map((s) => s.meeting_number))
  return maxNumber + 1
})

async function loadData() {
  if (!classId.value) return
  loading.value = true
  try {
    const [classRes, recapRes, sessionsRes] = await Promise.all([
      classService.get(classId.value),
      attendanceService.getClassRecap(classId.value),
      attendanceService.getSessions({ academic_class_id: classId.value, per_page: 50 }),
    ])

    academicClass.value = classRes.data || null
    recapData.value = recapRes.data || null
    sessions.value = sessionsRes.data || []
  } catch (err: any) {
    toast.error(err.message || 'Gagal memuat data presensi kelas.')
  } finally {
    loading.value = false
  }
}

function openCreateSessionModal() {
  selectedSession.value = null
  sessionModalOpen.value = true
}

function openEditSessionModal(session: TeachingSession) {
  selectedSession.value = session
  sessionModalOpen.value = true
}

function openBatchAttendance(session: TeachingSession) {
  activeSessionForAction.value = session
  batchModalOpen.value = true
}

function openQuickCheckIn(session: TeachingSession) {
  activeSessionForAction.value = session
  quickCheckInModalOpen.value = true
}

function confirmDeleteSession(session: TeachingSession) {
  sessionToDelete.value = session
  deleteModalOpen.value = true
}

async function handleDeleteSession() {
  if (!sessionToDelete.value) return
  deleting.value = true
  try {
    await attendanceService.deleteSession(sessionToDelete.value.id)
    toast.success(`Pertemuan ke-${sessionToDelete.value.meeting_number} berhasil dihapus.`)
    deleteModalOpen.value = false
    sessionToDelete.value = null
    loadData()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menghapus sesi perkuliahan.')
  } finally {
    deleting.value = false
  }
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <PageContainer>
    <!-- Header -->
    <PageHeader
      :title="academicClass ? `Presensi Kelas: ${academicClass.name}` : 'Presensi Kelas'"
      :subtitle="academicClass ? `${academicClass.course?.code} · ${academicClass.course?.name} (${academicClass.course?.credits} SKS) · Kelas ${academicClass.section}` : 'Memuat data...'"
      :breadcrumbs="[
        { label: 'Dashboard', to: '/dashboard' },
        { label: 'Presensi Perkuliahan', to: '/attendance' },
        { label: academicClass?.code || 'Detail Kelas' },
      ]"
    >
      <template #actions>
        <div class="flex items-center gap-2">
          <Button variant="outline" size="sm" @click="router.push('/attendance')">
            <ChevronLeft class="w-4 h-4 mr-1" />
            <span>Kembali</span>
          </Button>
          <Button
            variant="primary"
            size="sm"
            @click="openCreateSessionModal"
          >
            <Plus class="w-4 h-4" />
            <span>Buka Pertemuan Baru</span>
          </Button>
        </div>
      </template>
    </PageHeader>

    <div class="space-y-4">
      <!-- Tabs Navigation -->
      <Tabs
        :tabs="tabs"
        v-model="activeTab"
      />

      <!-- TAB 1: Matriks Kehadiran (Matrix 1-16) -->
      <div v-if="activeTab === 'matrix'" class="space-y-4">
        <Card>
          <template #header>
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
                  Matriks Presensi Mahasiswa (Pertemuan 1 s.d. 16)
                </h3>
                <p class="text-2xs text-slate-500 mt-0.5">
                  Rekapitulasi status kehadiran tiap mahasiswa per pertemuan dan persentase kelayakan mengikuti Ujian Akhir Semester (UAS).
                </p>
              </div>

              <div class="flex items-center gap-2 text-2xs">
                <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-500" /> Hadir (H)</span>
                <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-blue-500" /> Izin (I)</span>
                <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-amber-500" /> Sakit (S)</span>
                <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-rose-500" /> Alpa (A)</span>
              </div>
            </div>
          </template>

          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
              <thead class="bg-slate-100/90 text-2xs uppercase tracking-wider text-slate-700 font-bold border-b border-slate-200">
                <tr>
                  <th class="py-2.5 px-3 w-10 text-center">No</th>
                  <th class="py-2.5 px-3 w-28">NIM</th>
                  <th class="py-2.5 px-3 min-w-[160px]">Nama Mahasiswa</th>
                  <!-- Meeting Columns P1 to P16 -->
                  <th
                    v-for="m in 16"
                    :key="m"
                    class="py-2 px-1 text-center w-8 text-2xs font-mono font-bold"
                    :class="sessions.some(s => s.meeting_number === m) ? 'bg-brand-50/70 text-brand-900' : 'text-slate-400'"
                    :title="`Pertemuan ke-${m}`"
                  >
                    P{{ m }}
                  </th>
                  <th class="py-2.5 px-2 text-center w-20">Kehadiran</th>
                  <th class="py-2.5 px-2 text-center w-28">Status UAS</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 bg-white">
                <tr v-if="loading" class="text-center">
                  <td colspan="22" class="py-8 text-slate-400">
                    Memuat matriks kehadiran...
                  </td>
                </tr>
                <tr v-else-if="!recapData || recapData.recap.length === 0" class="text-center">
                  <td colspan="22" class="py-8 text-slate-400">
                    Belum ada data mahasiswa yang terdaftar di kelas ini.
                  </td>
                </tr>
                <tr
                  v-for="(row, idx) in recapData?.recap || []"
                  :key="row.student.id"
                  class="hover:bg-slate-50/70 transition-colors"
                >
                  <td class="py-2 px-2 text-center text-slate-400 font-mono text-2xs">
                    {{ idx + 1 }}
                  </td>
                  <td class="py-2 px-3 font-mono font-bold text-slate-800 text-2xs">
                    {{ row.student.student_number }}
                  </td>
                  <td class="py-2 px-3">
                    <span class="font-semibold text-slate-900 block">{{ row.student.full_name }}</span>
                    <span class="text-2xs text-slate-400">{{ row.student.study_program || '-' }}</span>
                  </td>

                  <!-- P1 to P16 Cells -->
                  <td
                    v-for="m in 16"
                    :key="m"
                    class="py-2 px-1 text-center font-mono text-2xs"
                  >
                    <span
                      v-if="row.meetings[m]"
                      :class="[
                        'inline-block w-6 h-6 leading-6 rounded-md font-bold text-2xs',
                        row.meetings[m].status === 'present' ? 'bg-emerald-100 text-emerald-800' :
                        row.meetings[m].status === 'permit' ? 'bg-blue-100 text-blue-800' :
                        row.meetings[m].status === 'sick' ? 'bg-amber-100 text-amber-800' :
                        'bg-rose-100 text-rose-800',
                      ]"
                      :title="`P${m}: ${row.meetings[m].status_code} (${row.meetings[m].session_date})`"
                    >
                      {{ row.meetings[m].status_code }}
                    </span>
                    <span v-else class="text-slate-300">-</span>
                  </td>

                  <!-- Percentage & Counts -->
                  <td class="py-2 px-2 text-center">
                    <span class="font-bold text-xs" :class="row.is_eligible ? 'text-emerald-700' : 'text-rose-700'">
                      {{ row.percentage }}%
                    </span>
                    <span class="text-2xs text-slate-400 block">
                      {{ row.present_count + row.permit_count + row.sick_count }}/{{ row.total_meetings }}
                    </span>
                  </td>

                  <!-- Exam Eligibility Badge -->
                  <td class="py-2 px-2 text-center">
                    <ExamEligibilityBadge :eligible="row.is_eligible" size="sm" />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </Card>
      </div>

      <!-- TAB 2: Berita Acara Perkuliahan (BAP) -->
      <div v-if="activeTab === 'bap'" class="space-y-4">
        <div class="grid grid-cols-1 gap-3.5">
          <Card
            v-for="session in sessions"
            :key="session.id"
            class="hover:border-brand-300 transition-colors"
          >
            <div class="p-4 space-y-3">
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-2 border-b border-slate-100">
                <div class="flex items-center gap-2.5">
                  <span class="font-mono font-extrabold text-sm text-brand-900 bg-brand-50 px-2.5 py-1 rounded-lg border border-brand-200">
                    Pertemuan {{ session.meeting_number }}
                  </span>
                  <div>
                    <h4 class="font-bold text-sm text-slate-900">{{ session.topic || 'Belum ada topik bahasan' }}</h4>
                    <span class="text-2xs text-slate-400 font-medium">
                      {{ session.session_date }} · {{ session.start_time || '08:00' }} - {{ session.end_time || '09:40' }}
                    </span>
                  </div>
                </div>

                <div class="flex items-center gap-1.5">
                  <TeachingMethodBadge :method="session.teaching_method" size="sm" />
                  <Badge :variant="session.status === 'open' ? 'success' : (session.status === 'closed' ? 'neutral' : 'info')" size="sm">
                    {{ session.status_label || session.status }}
                  </Badge>
                </div>
              </div>

              <!-- BAP Notes -->
              <div class="bg-slate-50 rounded-lg p-3 border border-slate-200/80 text-xs">
                <span class="font-bold text-2xs uppercase tracking-wider text-slate-500 block mb-1">
                  Catatan Berita Acara (BAP):
                </span>
                <p class="text-slate-700 leading-relaxed whitespace-pre-line">
                  {{ session.notes || 'Belum ada catatan berita acara perkuliahan untuk pertemuan ini.' }}
                </p>
              </div>

              <!-- Footer Details & Actions -->
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pt-2 text-2xs text-slate-500">
                <div>
                  <span>Pengajar: <strong>{{ session.lecturer?.full_name || '-' }}</strong></span>
                  <span v-if="session.room" class="ml-3">Ruangan: <strong>{{ session.room.name }} ({{ session.room.code }})</strong></span>
                </div>

                <div class="flex items-center gap-2">
                  <Button variant="outline" size="xs" @click="openQuickCheckIn(session)">
                    <QrCode class="w-3.5 h-3.5 mr-1" />
                    <span>Presensi Mandiri</span>
                  </Button>
                  <Button variant="primary" size="xs" @click="openBatchAttendance(session)">
                    <CheckCircle class="w-3.5 h-3.5 mr-1" />
                    <span>Input Presensi</span>
                  </Button>
                  <Button variant="ghost" size="xs" @click="openEditSessionModal(session)">
                    <Edit class="w-3.5 h-3.5" />
                  </Button>
                  <Button variant="ghost" size="xs" class="text-rose-600 hover:bg-rose-50" @click="confirmDeleteSession(session)">
                    <Trash2 class="w-3.5 h-3.5" />
                  </Button>
                </div>
              </div>
            </div>
          </Card>
        </div>
      </div>

      <!-- TAB 3: Daftar Sesi Pertemuan -->
      <div v-if="activeTab === 'sessions'" class="space-y-4">
        <div class="flex justify-between items-center bg-white p-3 border border-slate-200 rounded-lg text-xs">
          <span class="text-slate-600 font-medium">Total Sesi Pertemuan: <strong>{{ sessions.length }} / 16</strong></span>
          <Button variant="primary" size="xs" @click="openCreateSessionModal">
            <Plus class="w-3.5 h-3.5 mr-1" />
            <span>Tambah Pertemuan Baru</span>
          </Button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
          <Card
            v-for="session in sessions"
            :key="session.id"
            class="hover:shadow-sm transition-shadow"
          >
            <div class="p-4 space-y-3">
              <div class="flex items-center justify-between">
                <span class="font-mono font-bold text-xs text-brand-900 bg-brand-50 px-2 py-0.5 rounded border border-brand-200">
                  P{{ session.meeting_number }}
                </span>
                <Badge :variant="session.status === 'open' ? 'success' : 'neutral'" size="sm">
                  {{ session.status_label || session.status }}
                </Badge>
              </div>

              <div>
                <h4 class="font-bold text-xs text-slate-900 line-clamp-1">{{ session.topic || 'Pertemuan ' + session.meeting_number }}</h4>
                <span class="text-2xs text-slate-400 font-mono">{{ session.session_date }}</span>
              </div>

              <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-2xs">
                <Button variant="outline" size="xs" @click="openQuickCheckIn(session)">
                  <QrCode class="w-3 h-3 mr-1" />
                  <span>Token</span>
                </Button>
                <Button variant="primary" size="xs" @click="openBatchAttendance(session)">
                  <span>Presensi</span>
                </Button>
              </div>
            </div>
          </Card>
        </div>
      </div>
    </div>

    <!-- Modals -->
    <SessionModal
      :open="sessionModalOpen"
      :academic-class-id="classId"
      :session="selectedSession"
      :next-meeting-number="nextMeetingNumber"
      :default-lecturer-id="academicClass?.lecturers?.[0]?.id"
      :lecturers="academicClass?.lecturers"
      @update:open="sessionModalOpen = $event"
      @saved="loadData"
    />

    <QuickCheckInModal
      :open="quickCheckInModalOpen"
      :session="activeSessionForAction"
      @update:open="quickCheckInModalOpen = $event"
      @updated="loadData"
    />

    <BatchAttendanceModal
      :open="batchModalOpen"
      :session="activeSessionForAction"
      @update:open="batchModalOpen = $event"
      @saved="loadData"
    />

    <ConfirmModal
      :open="deleteModalOpen"
      title="Hapus Sesi Pertemuan"
      :message="`Apakah Anda yakin ingin menghapus Pertemuan ke-${sessionToDelete?.meeting_number}? Riwayat presensi pada pertemuan ini juga akan terhapus.`"
      confirm-text="Ya, Hapus Sesi"
      variant="danger"
      :loading="deleting"
      @update:open="deleteModalOpen = $event"
      @confirm="handleDeleteSession"
    />
  </PageContainer>
</template>
