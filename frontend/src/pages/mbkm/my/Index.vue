<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  Plus,
  Send,
  Trash2,
  BookOpen,
  CalendarCheck,
  Award,
  GraduationCap,
  AlertTriangle,
  CheckCircle2,
  MapPin,
  UserCheck,
} from 'lucide-vue-next'
import { mbkmService } from '@/services/api/mbkm'
import { useToast } from '@/composables/useToast'
import {
  APPLICATION_STATUS_LABELS,
  APPLICATION_STATUS_VARIANTS,
  ATTENDANCE_STATUS_LABELS,
  COMPLETION_STATUS_LABELS,
  COMPLETION_STATUS_VARIANTS,
  LOGBOOK_STATUS_LABELS,
  LOGBOOK_STATUS_VARIANTS,
  PARTICIPANT_STATUS_LABELS,
  PARTICIPANT_STATUS_VARIANTS,
  RECOGNITION_STATUS_LABELS,
  RECOGNITION_STATUS_VARIANTS,
  SUPERVISOR_ROLE_LABELS,
  optionsFrom,
} from '@/types/mbkm'
import type {
  MbkmActivityLog,
  MbkmApplication,
  MbkmAssessment,
  MbkmAttendance,
  MbkmParticipant,
  MbkmRecognition,
} from '@/types/mbkm'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Textarea from '@/components/ui/Textarea.vue'
import Modal from '@/components/ui/Modal.vue'
import Tabs, { type TabItem } from '@/components/ui/Tabs.vue'
import MbkmStatusBadge from '@/pages/mbkm/components/MbkmStatusBadge.vue'
import MbkmStatCard from '@/pages/mbkm/components/MbkmStatCard.vue'

const router = useRouter()
const toast = useToast()

const loading = ref(true)
const saving = ref(false)
const applications = ref<MbkmApplication[]>([])
const participant = ref<MbkmParticipant | null>(null)
const attendanceSummary = ref<any>(null)
const logbookSummary = ref<any>(null)
const finalScore = ref<any>(null)
const recognitionSummary = ref<any>(null)
const completion = ref<any>(null)

const logbooks = ref<MbkmActivityLog[]>([])
const attendances = ref<MbkmAttendance[]>([])
const assessments = ref<MbkmAssessment[]>([])
const recognitions = ref<MbkmRecognition[]>([])
const assessmentComponents = ref<Array<{ id: number; name: string; weight: number; max_score: number }>>([])

const activeTab = ref('ringkasan')

const tabs = computed<TabItem[]>(() => {
  const base: TabItem[] = [{ id: 'ringkasan', label: 'Ringkasan' }]
  if (participant.value) {
    base.push(
      { id: 'logbook', label: 'Logbook', badge: logbookSummary.value?.total || undefined },
      { id: 'presensi', label: 'Presensi' },
      { id: 'penilaian', label: 'Penilaian' },
      { id: 'rekognisi', label: 'Rekognisi & Hasil Studi' }
    )
  }
  base.push({ id: 'pendaftaran', label: 'Pendaftaran Saya' })
  return base
})

async function load() {
  loading.value = true
  try {
    const [appRes, partRes] = await Promise.all([
      mbkmService.getApplications({ per_page: 100 }),
      mbkmService.getParticipants({ per_page: 50 }),
    ])
    applications.value = appRes.data || []

    const active = (partRes.data || [])[0] ?? null
    participant.value = active

    if (active) {
      const detail = await mbkmService.getParticipant(active.id)
      participant.value = detail.data.participant
      attendanceSummary.value = detail.data.attendance
      logbookSummary.value = detail.data.logbook
      finalScore.value = detail.data.assessment
      recognitionSummary.value = detail.data.recognition
      completion.value = detail.data.completion

      const [logRes, attRes, assessRes, recRes] = await Promise.allSettled([
        mbkmService.getLogbooks(active.id, { per_page: 100 }),
        mbkmService.getAttendances(active.id, { per_page: 200 }),
        mbkmService.getAssessments(active.id),
        mbkmService.getParticipantRecognitions(active.id),
      ])
      if (logRes.status === 'fulfilled') logbooks.value = logRes.value.data || []
      if (attRes.status === 'fulfilled') attendances.value = attRes.value.data || []
      if (assessRes.status === 'fulfilled') {
        assessments.value = assessRes.value.data.assessments || []
        finalScore.value = assessRes.value.data.final
        assessmentComponents.value = (assessRes.value.data.components || []) as any
      }
      if (recRes.status === 'fulfilled') {
        recognitions.value = recRes.value.data.recognitions || []
        recognitionSummary.value = recRes.value.data.summary
      }
    }
  } catch (err: any) {
    toast.error(err.message || 'Gagal memuat data MBKM Anda')
  } finally {
    loading.value = false
  }
}

// ---- Logbook
const showLogbookModal = ref(false)
const logbookForm = reactive({
  log_date: '',
  period_label: '',
  activity: '',
  description: '',
  duration_hours: null as number | null,
  output: '',
  location: '',
})

function openLogbookModal() {
  Object.assign(logbookForm, {
    log_date: new Date().toISOString().slice(0, 10),
    period_label: '',
    activity: '',
    description: '',
    duration_hours: null,
    output: '',
    location: '',
  })
  showLogbookModal.value = true
}

async function handleSaveLogbook() {
  if (!participant.value) return
  if (!logbookForm.log_date || !logbookForm.activity.trim()) {
    toast.error('Tanggal dan aktivitas wajib diisi')
    return
  }
  saving.value = true
  try {
    await mbkmService.createLogbook(participant.value.id, { ...logbookForm })
    toast.success('Logbook berhasil disimpan sebagai draft')
    showLogbookModal.value = false
    await load()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menyimpan logbook')
  } finally {
    saving.value = false
  }
}

async function handleSubmitLogbook(id: number) {
  try {
    await mbkmService.submitLogbook(id)
    toast.success('Logbook diajukan untuk direview pembimbing')
    await load()
  } catch (err: any) {
    toast.error(err.message || 'Gagal mengajukan logbook')
  }
}

async function handleDeleteLogbook(id: number) {
  if (!confirm('Hapus logbook ini?')) return
  try {
    await mbkmService.deleteLogbook(id)
    toast.success('Logbook dihapus')
    await load()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menghapus logbook')
  }
}

// ---- Attendance
const showAttendanceModal = ref(false)
const attendanceForm = reactive({
  attendance_date: '',
  status: 'present',
  check_in_time: '',
  check_out_time: '',
  duration_hours: null as number | null,
  notes: '',
})

function openAttendanceModal() {
  Object.assign(attendanceForm, {
    attendance_date: new Date().toISOString().slice(0, 10),
    status: 'present',
    check_in_time: '',
    check_out_time: '',
    duration_hours: null,
    notes: '',
  })
  showAttendanceModal.value = true
}

async function handleSaveAttendance() {
  if (!participant.value) return
  if (!attendanceForm.attendance_date) {
    toast.error('Tanggal presensi wajib diisi')
    return
  }
  saving.value = true
  try {
    const payload: Record<string, unknown> = { ...attendanceForm }
    Object.keys(payload).forEach((k) => {
      if (payload[k] === '') payload[k] = null
    })
    await mbkmService.createAttendance(participant.value.id, payload as Partial<MbkmAttendance>)
    toast.success('Presensi berhasil dicatat')
    showAttendanceModal.value = false
    await load()
  } catch (err: any) {
    toast.error(err.message || 'Gagal mencatat presensi')
  } finally {
    saving.value = false
  }
}

// ---- Self assessment
const showSelfAssessModal = ref(false)
const selfAssessForm = reactive({ component_id: null as number | null, score: null as number | null, feedback: '' })

async function handleSaveSelfAssessment() {
  if (!participant.value || !selfAssessForm.component_id || selfAssessForm.score === null) {
    toast.error('Komponen dan nilai wajib diisi')
    return
  }
  saving.value = true
  try {
    await mbkmService.createAssessment(participant.value.id, {
      component_id: selfAssessForm.component_id,
      assessor_type: 'self' as never,
      score: selfAssessForm.score,
      feedback: selfAssessForm.feedback || undefined,
    })
    toast.success('Penilaian diri berhasil disimpan')
    showSelfAssessModal.value = false
    await load()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menyimpan penilaian diri')
  } finally {
    saving.value = false
  }
}

// ---- Withdrawal / extension
const showWithdrawalModal = ref(false)
const withdrawalForm = reactive({ type: 'withdrawal', reason: '', effective_date: '' })

async function handleSaveWithdrawal() {
  if (!participant.value || !withdrawalForm.reason.trim()) {
    toast.error('Alasan wajib diisi')
    return
  }
  saving.value = true
  try {
    await mbkmService.storeWithdrawal(participant.value.id, {
      type: withdrawalForm.type,
      reason: withdrawalForm.reason,
      effective_date: withdrawalForm.effective_date || undefined,
    })
    toast.success('Permohonan berhasil diajukan')
    showWithdrawalModal.value = false
    withdrawalForm.reason = ''
    await load()
  } catch (err: any) {
    toast.error(err.message || 'Gagal mengajukan permohonan')
  } finally {
    saving.value = false
  }
}

async function handleWithdrawApplication(app: MbkmApplication) {
  const reason = prompt('Alasan pembatalan pendaftaran:')
  if (!reason) return
  try {
    await mbkmService.withdrawApplication(app.id, reason)
    toast.success('Pendaftaran dibatalkan')
    await load()
  } catch (err: any) {
    toast.error(err.message || 'Gagal membatalkan pendaftaran')
  }
}

function formatDate(value?: string | null) {
  if (!value) return '-'
  try {
    return new Date(value).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
  } catch {
    return value
  }
}

onMounted(load)
</script>

<template>
  <PageContainer>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div>
        <h1 class="text-xl font-bold text-slate-900 tracking-tight">MBKM Saya</h1>
        <p class="text-xs text-slate-500 mt-1">
          Status pendaftaran, kegiatan, presensi, penilaian, dan rekognisi SKS Anda
        </p>
      </div>
      <Button variant="outline" size="sm" @click="router.push('/mbkm/catalog')">
        <BookOpen class="w-3.5 h-3.5 mr-1" /> Katalog Program
      </Button>
    </div>

    <div v-if="loading" class="py-12 text-center text-sm text-slate-400">Memuat data MBKM Anda...</div>

    <template v-else>
      <div v-if="participant" class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
        <MbkmStatCard
          label="Progress Logbook"
          :value="`${logbookSummary?.progress_percentage ?? 0}%`"
          :hint="`${logbookSummary?.approved ?? 0} disetujui dari ${logbookSummary?.total ?? 0} entri`"
          :icon="BookOpen"
          tone="primary"
        />
        <MbkmStatCard
          label="Kehadiran"
          :value="`${attendanceSummary?.attendance_percentage ?? 0}%`"
          :hint="`${attendanceSummary?.present ?? 0} hari hadir`"
          :icon="CalendarCheck"
          tone="success"
        />
        <MbkmStatCard
          label="Nilai Akhir"
          :value="participant.final_score ?? '—'"
          :hint="participant.letter_grade ? `Grade ${participant.letter_grade}` : 'Belum difinalisasi'"
          :icon="Award"
          tone="warning"
        />
        <MbkmStatCard
          label="SKS Diakui"
          :value="recognitionSummary?.recognized_credits ?? 0"
          :hint="`dari maks ${participant.program?.max_recognized_credits ?? '∞'} SKS`"
          :icon="GraduationCap"
          tone="primary"
        />
      </div>

      <Card class="border border-slate-200/80 shadow-2xs mb-5">
        <Tabs v-model="activeTab" :tabs="tabs" />
      </Card>

      <!-- Ringkasan -->
      <div v-if="activeTab === 'ringkasan'" class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <Card v-if="participant" class="border border-slate-200/80 shadow-2xs lg:col-span-2">
          <template #header>
            <div class="flex items-center gap-2">
              <p class="font-bold text-slate-800 text-sm">Status Kepesertaan</p>
              <MbkmStatusBadge :value="participant.status" :labels="PARTICIPANT_STATUS_LABELS" :variants="PARTICIPANT_STATUS_VARIANTS" />
            </div>
          </template>

          <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3.5 text-xs">
            <div>
              <dt class="text-slate-500">Nomor Peserta</dt>
              <dd class="font-mono font-medium text-slate-900 mt-0.5">{{ participant.participant_number }}</dd>
            </div>
            <div>
              <dt class="text-slate-500">Program</dt>
              <dd class="font-medium text-slate-900 mt-0.5">{{ participant.program?.name }}</dd>
            </div>
            <div>
              <dt class="text-slate-500">Periode Pelaksanaan</dt>
              <dd class="font-medium text-slate-900 mt-0.5">
                {{ formatDate(participant.start_date) }} – {{ formatDate(participant.end_date) }}
              </dd>
            </div>
            <div>
              <dt class="text-slate-500">Mitra / Lokasi</dt>
              <dd class="font-medium text-slate-900 mt-0.5 flex items-center gap-1.5">
                <MapPin class="w-3.5 h-3.5 text-rose-500" />
                {{ participant.placement?.partner?.name ?? 'Belum ditempatkan' }}
                <span v-if="participant.placement?.location" class="text-slate-500">
                  — {{ participant.placement.location.name }}
                </span>
              </dd>
            </div>
            <div class="sm:col-span-2">
              <dt class="text-slate-500">Pembimbing</dt>
              <dd class="mt-0.5 space-y-1">
                <p
                  v-for="s in participant.supervisors ?? []"
                  :key="s.id"
                  class="font-medium text-slate-900 flex items-center gap-1.5"
                >
                  <UserCheck class="w-3.5 h-3.5 text-slate-400" />
                  {{ s.lecturer?.full_name ?? s.external_name ?? '-' }}
                  <span class="text-slate-500 font-normal">({{ SUPERVISOR_ROLE_LABELS[s.role] ?? s.role }})</span>
                </p>
                <p v-if="(participant.supervisors ?? []).length === 0" class="text-slate-400">Belum ditetapkan</p>
              </dd>
            </div>
          </dl>

          <div v-if="participant.status === 'ongoing'" class="mt-4 pt-4 border-t border-slate-100 flex items-center gap-2">
            <Button variant="outline" size="sm" @click="showWithdrawalModal = true">
              <AlertTriangle class="w-3.5 h-3.5 mr-1" /> Ajukan Pengunduran Diri
            </Button>
          </div>
        </Card>

        <Card v-else class="border border-slate-200/80 shadow-2xs lg:col-span-2">
          <div class="py-8 text-center">
            <p class="text-sm text-slate-500">Anda belum menjadi peserta MBKM.</p>
            <Button variant="primary" size="sm" class="bg-brand-900 text-white mt-3" @click="router.push('/mbkm/catalog')">
              Lihat Katalog Program
            </Button>
          </div>
        </Card>

        <Card v-if="completion" class="border border-slate-200/80 shadow-2xs">
          <template #header>
            <div class="flex items-center gap-2">
              <p class="font-bold text-slate-800 text-sm">Penyelesaian</p>
              <MbkmStatusBadge
                :value="completion.status"
                :labels="COMPLETION_STATUS_LABELS"
                :variants="COMPLETION_STATUS_VARIANTS"
              />
            </div>
          </template>
          <div class="space-y-2">
            <div
              v-for="req in completion.requirements ?? []"
              :key="req.code"
              class="flex items-start gap-2 text-xs"
            >
              <CheckCircle2 v-if="req.satisfied" class="w-3.5 h-3.5 text-emerald-600 shrink-0 mt-0.5" />
              <AlertTriangle v-else class="w-3.5 h-3.5 text-amber-600 shrink-0 mt-0.5" />
              <span :class="req.satisfied ? 'text-slate-600' : 'text-slate-900 font-medium'">{{ req.label }}</span>
            </div>
          </div>
        </Card>
      </div>

      <!-- Logbook -->
      <Card v-else-if="activeTab === 'logbook'" class="border border-slate-200/80 shadow-2xs">
        <template #header>
          <div>
            <p class="font-bold text-slate-800 text-sm">Logbook Aktivitas</p>
            <p class="text-2xs text-slate-500 mt-0.5">
              {{ logbookSummary?.approved ?? 0 }} disetujui • {{ logbookSummary?.submitted ?? 0 }} menunggu review •
              {{ logbookSummary?.revision_required ?? 0 }} perlu revisi
            </p>
          </div>
          <Button variant="primary" size="sm" class="bg-brand-900 text-white" @click="openLogbookModal">
            <Plus class="w-3.5 h-3.5 mr-1" /> Entri Baru
          </Button>
        </template>

        <div v-if="logbooks.length === 0" class="py-8 text-center text-xs text-slate-400">
          Belum ada entri logbook. Tambahkan aktivitas harian/mingguan Anda.
        </div>

        <div v-else class="space-y-2.5">
          <div v-for="log in logbooks" :key="log.id" class="p-3.5 border border-slate-200 rounded-lg">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="font-semibold text-slate-900 text-xs">{{ log.activity }}</p>
                <p class="text-2xs text-slate-500 mt-0.5">
                  {{ formatDate(log.log_date) }}
                  <span v-if="log.period_label"> • {{ log.period_label }}</span>
                  <span v-if="log.duration_hours"> • {{ log.duration_hours }} jam</span>
                </p>
                <p v-if="log.description" class="text-2xs text-slate-600 mt-1.5">{{ log.description }}</p>
                <p v-if="log.review_notes" class="text-2xs text-amber-700 mt-1.5">
                  Catatan pembimbing: {{ log.review_notes }}
                </p>
              </div>
              <div class="shrink-0 flex flex-col items-end gap-2">
                <MbkmStatusBadge :value="log.status" :labels="LOGBOOK_STATUS_LABELS" :variants="LOGBOOK_STATUS_VARIANTS" />
                <div class="flex items-center gap-1.5">
                  <button
                    v-if="log.status === 'draft' || log.status === 'revision_required'"
                    type="button"
                    class="px-2 py-1 rounded-lg border border-blue-200 text-blue-600 hover:bg-blue-50 text-2xs font-medium"
                    @click="handleSubmitLogbook(log.id)"
                  >
                    <Send class="w-3 h-3 inline mr-1" />Ajukan
                  </button>
                  <button
                    v-if="!log.locked_at"
                    type="button"
                    class="p-1.5 rounded-lg border border-rose-200 text-rose-600 hover:bg-rose-50"
                    @click="handleDeleteLogbook(log.id)"
                  >
                    <Trash2 class="w-3.5 h-3.5" />
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </Card>

      <!-- Presensi -->
      <Card v-else-if="activeTab === 'presensi'" class="border border-slate-200/80 shadow-2xs">
        <template #header>
          <div>
            <p class="font-bold text-slate-800 text-sm">Presensi Kegiatan MBKM</p>
            <p class="text-2xs text-slate-500 mt-0.5">
              Terpisah dari presensi perkuliahan reguler • Minimum
              {{ attendanceSummary?.minimum_percentage ?? 0 }}%
            </p>
          </div>
          <Button variant="primary" size="sm" class="bg-brand-900 text-white" @click="openAttendanceModal">
            <Plus class="w-3.5 h-3.5 mr-1" /> Catat Presensi
          </Button>
        </template>

        <div class="grid grid-cols-3 sm:grid-cols-6 gap-3 mb-5">
          <div v-for="key in ['present', 'late', 'permission', 'sick', 'absent']" :key="key" class="p-3 bg-slate-50 rounded-lg border border-slate-200 text-center">
            <p class="text-2xs text-slate-500">{{ ATTENDANCE_STATUS_LABELS[key] }}</p>
            <p class="text-lg font-bold text-slate-900 mt-0.5">{{ (attendanceSummary as any)?.[key] ?? 0 }}</p>
          </div>
          <div class="p-3 bg-brand-50 rounded-lg border border-brand-200 text-center">
            <p class="text-2xs text-brand-900">Persentase</p>
            <p class="text-lg font-bold text-brand-900 mt-0.5">{{ attendanceSummary?.attendance_percentage ?? 0 }}%</p>
          </div>
        </div>

        <div v-if="attendances.length === 0" class="py-8 text-center text-xs text-slate-400">Belum ada data presensi.</div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="bg-slate-50/80 text-slate-600 font-semibold border-b border-slate-200 text-3xs uppercase tracking-wider">
                <th class="py-2.5 px-3 w-28">TANGGAL</th>
                <th class="py-2.5 px-3 w-28 text-center">STATUS</th>
                <th class="py-2.5 px-3 w-24 text-center">MASUK</th>
                <th class="py-2.5 px-3 w-24 text-center">KELUAR</th>
                <th class="py-2.5 px-3 w-20 text-center">JAM</th>
                <th class="py-2.5 px-3">CATATAN</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="a in attendances" :key="a.id">
                <td class="py-3 px-3 text-slate-700">{{ formatDate(a.attendance_date) }}</td>
                <td class="py-3 px-3 text-center">
                  <MbkmStatusBadge :value="a.status" :labels="ATTENDANCE_STATUS_LABELS" />
                </td>
                <td class="py-3 px-3 text-center text-slate-700">{{ a.check_in_time ?? '-' }}</td>
                <td class="py-3 px-3 text-center text-slate-700">{{ a.check_out_time ?? '-' }}</td>
                <td class="py-3 px-3 text-center text-slate-700">{{ a.duration_hours ?? '-' }}</td>
                <td class="py-3 px-3 text-slate-600">{{ a.notes ?? '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </Card>

      <!-- Penilaian -->
      <Card v-else-if="activeTab === 'penilaian'" class="border border-slate-200/80 shadow-2xs">
        <template #header>
          <div>
            <p class="font-bold text-slate-800 text-sm">Penilaian MBKM</p>
            <p class="text-2xs text-slate-500 mt-0.5">
              Bobot komponen mengikuti konfigurasi program. Nilai akhir difinalisasi oleh pengelola MBKM.
            </p>
          </div>
          <Button
            v-if="(finalScore?.breakdown ?? []).length > 0"
            variant="outline"
            size="sm"
            @click="showSelfAssessModal = true"
          >
            <Plus class="w-3.5 h-3.5 mr-1" /> Penilaian Diri
          </Button>
        </template>

        <div v-if="(finalScore?.breakdown ?? []).length === 0" class="py-8 text-center text-xs text-slate-400">
          Belum ada komponen penilaian untuk program ini.
        </div>

        <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-5">
          <div class="space-y-2">
            <div
              v-for="c in finalScore.breakdown"
              :key="c.component_id"
              class="p-3 border rounded-lg flex items-center justify-between gap-3"
              :class="c.assessors > 0 ? 'border-slate-200' : 'border-amber-200 bg-amber-50/50'"
            >
              <div class="min-w-0">
                <p class="font-semibold text-slate-900 text-xs">{{ c.component_name }}</p>
                <p class="text-2xs text-slate-500">Bobot {{ c.weight }}%</p>
              </div>
              <span :class="c.assessors > 0 ? 'font-bold text-slate-900' : 'text-slate-400 text-xs'">
                {{ c.assessors > 0 ? c.average_score : 'belum dinilai' }}
              </span>
            </div>
            <div class="p-3 bg-slate-50 border border-slate-200 rounded-lg flex items-center justify-between">
              <span class="font-semibold text-slate-700 text-xs">Nilai Akhir Terhitung</span>
              <span class="font-bold text-slate-900">{{ finalScore?.final_score ?? '—' }}</span>
            </div>
            <div
              v-if="participant?.score_finalized_at"
              class="p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-xs text-emerald-800 flex items-center justify-between"
            >
              <span>Nilai difinalisasi</span>
              <span class="font-bold">{{ participant.final_score }} — {{ participant.letter_grade }}</span>
            </div>
          </div>

          <div>
            <p class="font-bold text-slate-800 text-xs mb-2.5">Umpan Balik Penilai</p>
            <div v-if="assessments.length === 0" class="py-6 text-center text-xs text-slate-400">Belum ada penilaian.</div>
            <div v-else class="space-y-2">
              <div v-for="a in assessments" :key="a.id" class="p-3 border border-slate-200 rounded-lg">
                <p class="font-semibold text-slate-900 text-xs">{{ a.component?.name ?? '-' }}</p>
                <p class="text-2xs text-slate-500 mt-0.5">
                  {{ a.assessor_name || a.assessor?.name || a.assessor_type }}
                </p>
                <p v-if="a.feedback" class="text-2xs text-slate-600 mt-1.5">{{ a.feedback }}</p>
                <p v-if="a.recommendation" class="text-2xs text-slate-600 mt-1">
                  Rekomendasi: {{ a.recommendation }}
                </p>
                <p class="text-xs font-bold text-slate-900 mt-1.5">
                  {{ a.score }}<span class="text-slate-400 font-normal">/{{ a.max_score }}</span>
                </p>
              </div>
            </div>
          </div>
        </div>
      </Card>

      <!-- Rekognisi -->
      <div v-else-if="activeTab === 'rekognisi'" class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <Card class="border border-slate-200/80 shadow-2xs lg:col-span-2">
          <template #header>
            <p class="font-bold text-slate-800 text-sm">Rekognisi / Konversi SKS</p>
            <span class="text-2xs font-bold px-2 py-1 rounded border bg-slate-50 text-slate-700 border-slate-200">
              {{ recognitionSummary?.recognized_credits ?? 0 }} SKS diakui
            </span>
          </template>

          <div v-if="recognitions.length === 0" class="py-8 text-center text-xs text-slate-400">
            Belum ada rekognisi SKS.
          </div>

          <div v-else class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
              <thead>
                <tr class="bg-slate-50/80 text-slate-600 font-semibold border-b border-slate-200 text-3xs uppercase tracking-wider">
                  <th class="py-2.5 px-3">MATA KULIAH</th>
                  <th class="py-2.5 px-3 w-16 text-center">SKS</th>
                  <th class="py-2.5 px-3 w-20 text-center">NILAI</th>
                  <th class="py-2.5 px-3 w-32 text-center">STATUS</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="r in recognitions" :key="r.id">
                  <td class="py-3 px-3">
                    <p class="font-semibold text-slate-900">{{ r.course?.name ?? r.source_label ?? '-' }}</p>
                    <p v-if="r.course" class="text-2xs text-slate-500 font-mono">{{ r.course.code }}</p>
                  </td>
                  <td class="py-3 px-3 text-center font-semibold text-slate-800">{{ r.credits }}</td>
                  <td class="py-3 px-3 text-center text-slate-800">
                    {{ r.score ?? '-' }}
                    <span v-if="r.letter_grade" class="block text-2xs font-bold text-emerald-700">{{ r.letter_grade }}</span>
                  </td>
                  <td class="py-3 px-3 text-center">
                    <MbkmStatusBadge :value="r.status" :labels="RECOGNITION_STATUS_LABELS" :variants="RECOGNITION_STATUS_VARIANTS" />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </Card>

        <Card class="border border-slate-200/80 shadow-2xs">
          <template #header><p class="font-bold text-slate-800 text-sm">Status Hasil Studi</p></template>
          <div class="space-y-3 text-xs">
            <p class="text-slate-600 leading-relaxed">
              Rekognisi yang sudah disetujui otomatis masuk ke KRS, nilai, dan KHS Anda melalui mekanisme akademik
              yang berlaku. IPK tetap dihitung dari KHS — bukan dari tabel MBKM.
            </p>
            <div class="p-3 bg-slate-50 border border-slate-200 rounded-lg space-y-2">
              <div class="flex items-center justify-between">
                <span class="text-slate-500">SKS diakui</span>
                <span class="font-bold text-slate-900">{{ recognitionSummary?.recognized_credits ?? 0 }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-slate-500">Rekognisi disetujui</span>
                <span class="font-bold text-slate-900">{{ recognitionSummary?.approved ?? 0 }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-slate-500">Menunggu proses</span>
                <span class="font-bold text-slate-900">
                  {{ (recognitionSummary?.pending ?? 0) + (recognitionSummary?.draft ?? 0) }}
                </span>
              </div>
            </div>
            <Button variant="outline" size="sm" class="w-full" @click="router.push('/khs')">
              <GraduationCap class="w-3.5 h-3.5 mr-1" /> Lihat KHS Saya
            </Button>
          </div>
        </Card>
      </div>

      <!-- Pendaftaran -->
      <Card v-else-if="activeTab === 'pendaftaran'" class="border border-slate-200/80 shadow-2xs">
        <template #header>
          <p class="font-bold text-slate-800 text-sm">Pendaftaran MBKM Saya</p>
        </template>

        <div v-if="applications.length === 0" class="py-8 text-center text-xs text-slate-400">
          Belum ada pendaftaran MBKM.
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="bg-slate-50/80 text-slate-600 font-semibold border-b border-slate-200 text-3xs uppercase tracking-wider">
                <th class="py-2.5 px-3">NO. PENDAFTARAN</th>
                <th class="py-2.5 px-3">PROGRAM</th>
                <th class="py-2.5 px-3 w-32 text-center">DIAJUKAN</th>
                <th class="py-2.5 px-3 w-36 text-center">STATUS</th>
                <th class="py-2.5 px-3 w-24 text-center">AKSI</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="app in applications" :key="app.id">
                <td class="py-3 px-3 font-mono text-slate-700">{{ app.registration_number }}</td>
                <td class="py-3 px-3">
                  <p class="font-semibold text-slate-900">{{ app.program?.name }}</p>
                  <p class="text-2xs text-slate-500">{{ app.program?.program_type?.name }}</p>
                </td>
                <td class="py-3 px-3 text-center text-slate-600">{{ formatDate(app.submitted_at) }}</td>
                <td class="py-3 px-3 text-center">
                  <MbkmStatusBadge :value="app.status" :labels="APPLICATION_STATUS_LABELS" :variants="APPLICATION_STATUS_VARIANTS" />
                </td>
                <td class="py-3 px-3 text-center">
                  <button
                    v-if="app.status !== 'withdrawn' && app.status !== 'not_selected' && app.status !== 'rejected'"
                    type="button"
                    class="px-2 py-1 rounded-lg border border-rose-200 text-rose-600 hover:bg-rose-50 text-2xs font-medium"
                    @click="handleWithdrawApplication(app)"
                  >
                    Batalkan
                  </button>
                  <span v-else class="text-slate-400">—</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </Card>
    </template>

    <!-- Logbook modal -->
    <Modal v-model:open="showLogbookModal" title="Entri Logbook Baru" size="lg">
      <div class="space-y-4 text-xs">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Tanggal <span class="text-rose-500">*</span></label>
            <Input v-model="logbookForm.log_date" type="date" />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Periode</label>
            <Input v-model="logbookForm.period_label" placeholder="mis. Minggu ke-2" />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Durasi (jam)</label>
            <Input v-model.number="logbookForm.duration_hours" type="number" min="0" max="24" step="0.5" />
          </div>
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Aktivitas <span class="text-rose-500">*</span></label>
          <Input v-model="logbookForm.activity" placeholder="Ringkasan aktivitas" />
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Uraian</label>
          <Textarea v-model="logbookForm.description" :rows="3" />
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Output</label>
            <Textarea v-model="logbookForm.output" :rows="2" />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Lokasi</label>
            <Input v-model="logbookForm.location" />
          </div>
        </div>
      </div>
      <template #footer>
        <div class="flex items-center justify-end gap-2">
          <Button variant="outline" size="sm" @click="showLogbookModal = false">Batal</Button>
          <Button variant="primary" size="sm" :loading="saving" class="bg-brand-900 text-white" @click="handleSaveLogbook">
            Simpan Draft
          </Button>
        </div>
      </template>
    </Modal>

    <!-- Attendance modal -->
    <Modal v-model:open="showAttendanceModal" title="Catat Presensi" size="lg">
      <div class="space-y-4 text-xs">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Tanggal <span class="text-rose-500">*</span></label>
            <Input v-model="attendanceForm.attendance_date" type="date" />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Status</label>
            <select v-model="attendanceForm.status" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none">
              <option v-for="o in optionsFrom(ATTENDANCE_STATUS_LABELS)" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
          </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Jam Masuk</label>
            <Input v-model="attendanceForm.check_in_time" type="time" />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Jam Keluar</label>
            <Input v-model="attendanceForm.check_out_time" type="time" />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Durasi (jam)</label>
            <Input v-model.number="attendanceForm.duration_hours" type="number" min="0" max="24" step="0.5" />
          </div>
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Catatan</label>
          <Textarea v-model="attendanceForm.notes" :rows="2" />
        </div>
      </div>
      <template #footer>
        <div class="flex items-center justify-end gap-2">
          <Button variant="outline" size="sm" @click="showAttendanceModal = false">Batal</Button>
          <Button variant="primary" size="sm" :loading="saving" class="bg-brand-900 text-white" @click="handleSaveAttendance">
            Simpan Presensi
          </Button>
        </div>
      </template>
    </Modal>

    <!-- Self assessment modal -->
    <Modal v-model:open="showSelfAssessModal" title="Penilaian Diri" size="md">
      <div class="space-y-4 text-xs">
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Komponen</label>
          <select v-model="selfAssessForm.component_id" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none">
            <option :value="null">Pilih komponen</option>
            <option v-for="c in assessmentComponents" :key="c.id" :value="c.id">
              {{ c.name }} (bobot {{ c.weight }}%)
            </option>
          </select>
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Nilai</label>
          <Input v-model.number="selfAssessForm.score" type="number" min="0" max="100" step="0.01" />
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Catatan</label>
          <Textarea v-model="selfAssessForm.feedback" :rows="2" />
        </div>
      </div>
      <template #footer>
        <div class="flex items-center justify-end gap-2">
          <Button variant="outline" size="sm" @click="showSelfAssessModal = false">Batal</Button>
          <Button variant="primary" size="sm" :loading="saving" class="bg-brand-900 text-white" @click="handleSaveSelfAssessment">
            Simpan
          </Button>
        </div>
      </template>
    </Modal>

    <!-- Withdrawal modal -->
    <Modal v-model:open="showWithdrawalModal" title="Pengunduran Diri" size="md">
      <div class="space-y-4 text-xs">
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Jenis</label>
          <select v-model="withdrawalForm.type" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none">
            <option value="withdrawal">Pengunduran Diri</option>
            <option value="cancellation">Pembatalan</option>
          </select>
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Tanggal Efektif</label>
          <Input v-model="withdrawalForm.effective_date" type="date" />
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Alasan <span class="text-rose-500">*</span></label>
          <Textarea v-model="withdrawalForm.reason" :rows="3" />
        </div>
      </div>
      <template #footer>
        <div class="flex items-center justify-end gap-2">
          <Button variant="outline" size="sm" @click="showWithdrawalModal = false">Batal</Button>
          <Button variant="primary" size="sm" :loading="saving" class="bg-brand-900 text-white" @click="handleSaveWithdrawal">
            Ajukan
          </Button>
        </div>
      </template>
    </Modal>
  </PageContainer>
</template>
