<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeft,
  Plus,
  Trash2,
  Save,
  Send,
  CheckCircle2,
  RotateCcw,
  PlayCircle,
  Award,
  AlertTriangle,
  Upload,
  Lock,
} from 'lucide-vue-next'
import { mbkmService } from '@/services/api/mbkm'
import { courseService } from '@/services/api/courses'
import { lecturerService } from '@/services/api/lecturers'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import {
  ASSESSOR_TYPE_LABELS,
  ATTENDANCE_STATUS_LABELS,
  COMPLETION_STATUS_LABELS,
  COMPLETION_STATUS_VARIANTS,
  LEARNING_AGREEMENT_STATUS_LABELS,
  LEARNING_AGREEMENT_STATUS_VARIANTS,
  LOGBOOK_STATUS_LABELS,
  LOGBOOK_STATUS_VARIANTS,
  PARTICIPANT_STATUS_LABELS,
  PARTICIPANT_STATUS_VARIANTS,
  RECOGNITION_STATUS_LABELS,
  RECOGNITION_STATUS_VARIANTS,
  RECOGNITION_TYPE_LABELS,
  SUPERVISOR_ROLE_LABELS,
  optionsFrom,
} from '@/types/mbkm'
import type {
  MbkmActivityLog,
  MbkmAssessment,
  MbkmAttendance,
  MbkmLearningAgreementItem,
  MbkmParticipant,
  MbkmRecognition,
  MbkmSupervisor,
} from '@/types/mbkm'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Textarea from '@/components/ui/Textarea.vue'
import Modal from '@/components/ui/Modal.vue'
import Tabs, { type TabItem } from '@/components/ui/Tabs.vue'
import MbkmStatusBadge from '@/pages/mbkm/components/MbkmStatusBadge.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const auth = useAuthStore()

const participantId = Number(route.params.id)

const loading = ref(true)
const saving = ref(false)
const participant = ref<MbkmParticipant | null>(null)
const attendanceSummary = ref<any>(null)
const logbookSummary = ref<any>(null)
const finalScore = ref<any>(null)
const recognitionSummary = ref<any>(null)
const completion = ref<any>(null)
const history = ref<any[]>([])

const logbooks = ref<MbkmActivityLog[]>([])
const attendances = ref<MbkmAttendance[]>([])
const assessments = ref<MbkmAssessment[]>([])
const assessmentComponents = ref<any[]>([])
const recognitions = ref<MbkmRecognition[]>([])
const courses = ref<Array<{ id: number; code: string; name: string; credits: number }>>([])
const lecturers = ref<Array<{ id: number; nidn: string; full_name: string }>>([])
const partners = ref<Array<{ id: number; name: string }>>([])

const activeTab = ref('ringkasan')

const tabs = computed<TabItem[]>(() => [
  { id: 'ringkasan', label: 'Ringkasan' },
  { id: 'penempatan', label: 'Penempatan & Pembimbing' },
  { id: 'la', label: 'Learning Agreement' },
  { id: 'logbook', label: 'Logbook', badge: logbookSummary.value?.total || undefined },
  { id: 'presensi', label: 'Presensi' },
  { id: 'penilaian', label: 'Penilaian' },
  { id: 'rekognisi', label: 'Rekognisi SKS', badge: recognitions.value.length || undefined },
  { id: 'penyelesaian', label: 'Penyelesaian' },
])

const isManager = computed(
  () => auth.isSuperAdmin || auth.permissions.some((p: any) => p.name === 'mbkm.manage')
)
const canManageParticipant = computed(
  () => isManager.value || auth.permissions.some((p: any) => p.name === 'mbkm.participants.manage')
)

async function load() {
  loading.value = true
  try {
    const res = await mbkmService.getParticipant(participantId)
    participant.value = res.data.participant
    attendanceSummary.value = res.data.attendance
    logbookSummary.value = res.data.logbook
    finalScore.value = res.data.assessment
    recognitionSummary.value = res.data.recognition
    completion.value = res.data.completion
    history.value = res.data.history || []
    await Promise.all([loadLogbooks(), loadAttendances(), loadAssessments(), loadRecognitions()])
  } catch (err: any) {
    toast.error(err.message || 'Gagal memuat detail peserta MBKM')
  } finally {
    loading.value = false
  }
}

async function loadLogbooks() {
  try {
    const res = await mbkmService.getLogbooks(participantId, { per_page: 100 })
    logbooks.value = res.data || []
  } catch {
    logbooks.value = []
  }
}

async function loadAttendances() {
  try {
    const res = await mbkmService.getAttendances(participantId, { per_page: 200 })
    attendances.value = res.data || []
  } catch {
    attendances.value = []
  }
}

async function loadAssessments() {
  try {
    const res = await mbkmService.getAssessments(participantId)
    assessments.value = res.data.assessments || []
    assessmentComponents.value = res.data.components || []
    finalScore.value = res.data.final
  } catch {
    assessments.value = []
  }
}

async function loadRecognitions() {
  try {
    const res = await mbkmService.getParticipantRecognitions(participantId)
    recognitions.value = res.data.recognitions || []
    recognitionSummary.value = res.data.summary
  } catch {
    recognitions.value = []
  }
}

async function loadDependencies() {
  try {
    const [courseRes, lecRes, partnerRes] = await Promise.all([
      courseService.list({ per_page: 500 } as never),
      lecturerService.list({ per_page: 500 } as never),
      mbkmService.getPartners({ per_page: 300 }),
    ])
    courses.value = (courseRes.data || []) as any
    lecturers.value = (lecRes.data || []) as any
    partners.value = (partnerRes.data || []) as any
  } catch {
    // optional dependencies
  }
}

// ------------------------------------------------------------------
// Placement + supervisors
// ------------------------------------------------------------------
const placementForm = reactive({
  partner_id: null as number | null,
  location_id: null as number | null,
  division: '',
  position: '',
  batch: '',
  field_supervisor_name: '',
  field_supervisor_position: '',
  field_supervisor_email: '',
  field_supervisor_phone: '',
  field_supervisor_organization: '',
  start_date: '',
  end_date: '',
  notes: '',
})

const showSupervisorModal = ref(false)
const supervisorForm = reactive({
  role: 'internal' as 'internal' | 'co_supervisor' | 'field',
  lecturer_id: null as number | null,
  external_name: '',
  external_position: '',
  external_email: '',
  external_phone: '',
  external_organization: '',
  notes: '',
})

function initPlacementForm() {
  const p = participant.value?.placement
  Object.assign(placementForm, {
    partner_id: p?.partner_id ?? null,
    location_id: p?.location_id ?? null,
    division: p?.division ?? '',
    position: p?.position ?? '',
    batch: p?.batch ?? '',
    field_supervisor_name: p?.field_supervisor_name ?? '',
    field_supervisor_position: p?.field_supervisor_position ?? '',
    field_supervisor_email: p?.field_supervisor_email ?? '',
    field_supervisor_phone: p?.field_supervisor_phone ?? '',
    field_supervisor_organization: p?.field_supervisor_organization ?? '',
    start_date: p?.start_date ?? '',
    end_date: p?.end_date ?? '',
    notes: p?.notes ?? '',
  })
}

async function handleSavePlacement() {
  saving.value = true
  try {
    const payload: Record<string, unknown> = { ...placementForm }
    Object.keys(payload).forEach((k) => {
      if (payload[k] === '') payload[k] = null
    })
    await mbkmService.upsertPlacement(participantId, payload)
    toast.success('Penempatan peserta berhasil disimpan')
    await load()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menyimpan penempatan')
  } finally {
    saving.value = false
  }
}

async function handleSaveSupervisor() {
  saving.value = true
  try {
    const payload: Record<string, unknown> = { ...supervisorForm }
    Object.keys(payload).forEach((k) => {
      if (payload[k] === '') payload[k] = null
    })
    if (supervisorForm.role === 'field') delete payload.lecturer_id
    await mbkmService.storeSupervisor(participantId, payload as Partial<MbkmSupervisor>)
    toast.success('Pembimbing berhasil ditetapkan')
    showSupervisorModal.value = false
    Object.assign(supervisorForm, {
      role: 'internal',
      lecturer_id: null,
      external_name: '',
      external_position: '',
      external_email: '',
      external_phone: '',
      external_organization: '',
      notes: '',
    })
    await load()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menetapkan pembimbing')
  } finally {
    saving.value = false
  }
}

async function handleDeleteSupervisor(id: number) {
  if (!confirm('Hapus penugasan pembimbing ini?')) return
  try {
    await mbkmService.deleteSupervisor(participantId, id)
    toast.success('Penugasan pembimbing dihapus')
    await load()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menghapus pembimbing')
  }
}

// ------------------------------------------------------------------
// Learning agreement
// ------------------------------------------------------------------
const laForm = reactive({
  title: '',
  period_start: '',
  period_end: '',
  notes: '',
  items: [] as Array<{ activity_title: string; activity_description: string; course_id: number | null; credits: number; target_grade: string }>,
})

const laTotalCredits = computed(() => laForm.items.reduce((s, i) => s + Number(i.credits || 0), 0))

function initLaForm() {
  const la = participant.value?.learning_agreement
  laForm.title = la?.title ?? 'Rencana Rekognisi MBKM'
  laForm.period_start = la?.period_start ?? ''
  laForm.period_end = la?.period_end ?? ''
  laForm.notes = la?.notes ?? ''
  laForm.items = (la?.items ?? []).map((i: MbkmLearningAgreementItem) => ({
    activity_title: i.activity_title,
    activity_description: i.activity_description ?? '',
    course_id: i.course_id ?? null,
    credits: i.credits,
    target_grade: i.target_grade ?? '',
  }))
  if (laForm.items.length === 0) addLaItem()
}

function addLaItem() {
  laForm.items.push({ activity_title: '', activity_description: '', course_id: null, credits: 0, target_grade: '' })
}

async function handleSaveLa() {
  saving.value = true
  try {
    await mbkmService.upsertLearningAgreement(participantId, {
      title: laForm.title || undefined,
      period_start: laForm.period_start || undefined,
      period_end: laForm.period_end || undefined,
      notes: laForm.notes || undefined,
      items: laForm.items.map((i, index) => ({
        activity_title: i.activity_title,
        activity_description: i.activity_description || undefined,
        course_id: i.course_id,
        credits: Number(i.credits || 0),
        target_grade: i.target_grade || undefined,
        sort_order: index,
      })),
    })
    toast.success('Learning agreement berhasil disimpan')
    await load()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menyimpan learning agreement')
  } finally {
    saving.value = false
  }
}

async function handleLaTransition(status: string) {
  try {
    await mbkmService.transitionLearningAgreement(participantId, status)
    toast.success('Status learning agreement berhasil diperbarui')
    await load()
  } catch (err: any) {
    toast.error(err.message || 'Transisi learning agreement ditolak')
  }
}

async function handleLaReopen() {
  const reason = prompt('Alasan membuka kembali learning agreement untuk revisi:')
  if (!reason) return
  try {
    await mbkmService.reopenLearningAgreement(participantId, reason)
    toast.success('Learning agreement dibuka kembali')
    await load()
  } catch (err: any) {
    toast.error(err.message || 'Gagal membuka kembali learning agreement')
  }
}

// ------------------------------------------------------------------
// Logbook
// ------------------------------------------------------------------
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
  if (!logbookForm.log_date || !logbookForm.activity.trim()) {
    toast.error('Tanggal dan aktivitas wajib diisi')
    return
  }
  saving.value = true
  try {
    await mbkmService.createLogbook(participantId, { ...logbookForm })
    toast.success('Logbook berhasil dibuat')
    showLogbookModal.value = false
    await load()
  } catch (err: any) {
    toast.error(err.message || 'Gagal membuat logbook')
  } finally {
    saving.value = false
  }
}

async function handleSubmitLogbook(id: number) {
  try {
    await mbkmService.submitLogbook(id)
    toast.success('Logbook diajukan untuk review')
    await load()
  } catch (err: any) {
    toast.error(err.message || 'Gagal mengajukan logbook')
  }
}

async function handleReviewLogbook(id: number, decision: string) {
  let notes: string | null = null
  if (decision !== 'approved') {
    notes = prompt('Catatan review:')
    if (!notes) return
  }
  try {
    await mbkmService.reviewLogbook(id, decision, notes ?? undefined)
    toast.success('Review logbook disimpan')
    await load()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menyimpan review logbook')
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

// ------------------------------------------------------------------
// Attendance
// ------------------------------------------------------------------
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
    await mbkmService.createAttendance(participantId, payload as Partial<MbkmAttendance>)
    toast.success('Presensi berhasil dicatat')
    showAttendanceModal.value = false
    await load()
  } catch (err: any) {
    toast.error(err.message || 'Gagal mencatat presensi (tanggal mungkin sudah tercatat)')
  } finally {
    saving.value = false
  }
}

// ------------------------------------------------------------------
// Assessment
// ------------------------------------------------------------------
const showAssessmentModal = ref(false)
const assessmentForm = reactive({
  component_id: null as number | null,
  assessor_type: 'internal_supervisor',
  assessor_name: '',
  score: null as number | null,
  max_score: 100,
  feedback: '',
  recommendation: '',
})

function openAssessmentModal(componentId?: number) {
  Object.assign(assessmentForm, {
    component_id: componentId ?? assessmentComponents.value[0]?.id ?? null,
    assessor_type: 'internal_supervisor',
    assessor_name: '',
    score: null,
    max_score: 100,
    feedback: '',
    recommendation: '',
  })
  showAssessmentModal.value = true
}

async function handleSaveAssessment() {
  if (!assessmentForm.component_id || assessmentForm.score === null) {
    toast.error('Komponen dan nilai wajib diisi')
    return
  }
  saving.value = true
  try {
    await mbkmService.createAssessment(participantId, {
      component_id: assessmentForm.component_id as number,
      assessor_type: assessmentForm.assessor_type as never,
      assessor_name: assessmentForm.assessor_name || undefined,
      score: assessmentForm.score as number,
      max_score: assessmentForm.max_score,
      feedback: assessmentForm.feedback || undefined,
      recommendation: assessmentForm.recommendation || undefined,
    })
    toast.success('Penilaian berhasil disimpan')
    showAssessmentModal.value = false
    await load()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menyimpan penilaian')
  } finally {
    saving.value = false
  }
}

async function handleFinalizeScore() {
  // The backend rejects finalisation while components are unscored, so warn
  // here and make the override explicit instead of letting it fail silently.
  const incomplete = Boolean(finalScore.value?.breakdown?.length) && !finalScore.value?.is_complete

  const message = incomplete
    ? 'Penilaian belum lengkap — masih ada komponen tanpa nilai. Finalisasi paksa akan menyimpan nilai yang belum final dan mengalirkannya ke KHS. Lanjutkan?'
    : 'Finalisasi nilai akhir MBKM? Nilai akan dikonversi ke skala huruf yang berlaku.'

  if (!confirm(message)) return

  try {
    await mbkmService.finalizeParticipantScore(participantId, incomplete)
    toast.success('Nilai akhir MBKM berhasil difinalisasi')
    await load()
  } catch (err: any) {
    toast.error(err.message || 'Gagal finalisasi nilai')
  }
}

// ------------------------------------------------------------------
// Recognition
// ------------------------------------------------------------------
const showRecognitionModal = ref(false)
const recognitionForm = reactive({
  course_id: null as number | null,
  activity_log_id: null as number | null,
  source_label: '',
  credits: 0,
  recognition_type: 'course_conversion',
  score: null as number | null,
  semester_id: null as number | null,
  notes: '',
})

function openRecognitionModal() {
  Object.assign(recognitionForm, {
    course_id: null,
    activity_log_id: null,
    source_label: '',
    credits: 0,
    recognition_type: 'course_conversion',
    score: null,
    semester_id: participant.value?.program?.semester_id ?? null,
    notes: '',
  })
  showRecognitionModal.value = true
}

function onCourseChange(courseId: number | null) {
  const course = courses.value.find((c) => c.id === courseId)
  if (course) recognitionForm.credits = course.credits
}

async function handleSaveRecognition() {
  if (!recognitionForm.course_id) {
    toast.error('Mata kuliah wajib dipilih')
    return
  }
  saving.value = true
  try {
    const payload: Record<string, unknown> = { ...recognitionForm }
    Object.keys(payload).forEach((k) => {
      if (payload[k] === '') payload[k] = null
    })
    await mbkmService.createRecognition(participantId, payload as Partial<MbkmRecognition>)
    toast.success('Rekognisi berhasil dibuat')
    showRecognitionModal.value = false
    await load()
  } catch (err: any) {
    toast.error(err.message || 'Gagal membuat rekognisi')
  } finally {
    saving.value = false
  }
}

async function handleRecognitionTransition(id: number, status: string) {
  try {
    await mbkmService.transitionRecognition(id, status)
    toast.success('Status rekognisi diperbarui')
    await load()
  } catch (err: any) {
    toast.error(err.message || 'Transisi rekognisi ditolak')
  }
}

async function handleRecognitionCorrect(id: number) {
  const reason = prompt('Alasan koreksi rekognisi:')
  if (!reason) return
  try {
    await mbkmService.correctRecognition(id, reason)
    toast.success('Rekognisi dibuka kembali untuk koreksi')
    await load()
  } catch (err: any) {
    toast.error(err.message || 'Gagal mengoreksi rekognisi')
  }
}

async function handleDeleteRecognition(id: number) {
  if (!confirm('Hapus rekognisi ini?')) return
  try {
    await mbkmService.deleteRecognition(id)
    toast.success('Rekognisi dihapus')
    await load()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menghapus rekognisi')
  }
}

// ------------------------------------------------------------------
// Completion / withdrawal / extension
// ------------------------------------------------------------------
const showCertificateModal = ref(false)
const certificateFile = ref<File | null>(null)
const certificateTitle = ref('')

async function handleVerifyCompletion(force = false) {
  if (force && !confirm('Verifikasi paksa meskipun syarat belum lengkap?')) return
  try {
    await mbkmService.verifyCompletion(participantId, force)
    toast.success('Verifikasi penyelesaian dijalankan')
    await load()
  } catch (err: any) {
    toast.error(err.message || 'Syarat penyelesaian belum lengkap')
  }
}

async function handleUploadCertificate() {
  if (!certificateFile.value) {
    toast.error('Pilih berkas terlebih dahulu')
    return
  }
  saving.value = true
  try {
    await mbkmService.attachCertificate(participantId, certificateFile.value, 'certificate', certificateTitle.value || undefined)
    toast.success('Dokumen penyelesaian berhasil diunggah')
    showCertificateModal.value = false
    certificateFile.value = null
    certificateTitle.value = ''
    await load()
  } catch (err: any) {
    toast.error(err.message || 'Gagal mengunggah dokumen')
  } finally {
    saving.value = false
  }
}

function onCertificateSelected(event: Event) {
  const input = event.target as HTMLInputElement
  certificateFile.value = input.files?.[0] ?? null
}

const showWithdrawalModal = ref(false)
const withdrawalForm = reactive({ type: 'withdrawal', reason: '', effective_date: '' })

async function handleSaveWithdrawal() {
  if (!withdrawalForm.reason.trim()) {
    toast.error('Alasan wajib diisi')
    return
  }
  saving.value = true
  try {
    await mbkmService.storeWithdrawal(participantId, {
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

const showExtensionModal = ref(false)
const extensionForm = reactive({ new_end_date: '', reason: '' })

async function handleSaveExtension() {
  if (!extensionForm.new_end_date || !extensionForm.reason.trim()) {
    toast.error('Tanggal baru dan alasan wajib diisi')
    return
  }
  saving.value = true
  try {
    await mbkmService.storeExtension(participantId, { ...extensionForm })
    toast.success('Permohonan perpanjangan diajukan')
    showExtensionModal.value = false
    extensionForm.new_end_date = ''
    extensionForm.reason = ''
    await load()
  } catch (err: any) {
    toast.error(err.message || 'Gagal mengajukan perpanjangan')
  } finally {
    saving.value = false
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

onMounted(async () => {
  await Promise.all([loadDependencies(), load()])
  initPlacementForm()
  initLaForm()
})
</script>

<template>
  <PageContainer>
    <div class="flex items-start gap-3 mb-6">
      <button
        type="button"
        class="p-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 mt-0.5"
        @click="router.push('/mbkm/participants')"
      >
        <ArrowLeft class="w-4 h-4" />
      </button>
      <div class="flex-1 min-w-0">
        <h1 class="text-xl font-bold text-slate-900 tracking-tight truncate">
          {{ participant?.student?.full_name ?? 'Detail Peserta MBKM' }}
        </h1>
        <p class="text-xs text-slate-500 mt-1 flex items-center gap-2 flex-wrap">
          <span class="font-mono">{{ participant?.participant_number }}</span>
          <span>•</span>
          <span>{{ participant?.program?.name }}</span>
          <MbkmStatusBadge
            v-if="participant"
            :value="participant.status"
            :labels="PARTICIPANT_STATUS_LABELS"
            :variants="PARTICIPANT_STATUS_VARIANTS"
          />
        </p>
      </div>
      <div class="flex items-center gap-2">
        <Button
          v-if="participant?.status === 'assigned' && canManageParticipant"
          variant="primary"
          size="sm"
          class="bg-brand-900 text-white"
          @click="mbkmService.startParticipant(participantId).then(() => load()).catch((e: any) => toast.error(e.message))"
        >
          <PlayCircle class="w-3.5 h-3.5 mr-1" /> Mulai Pelaksanaan
        </Button>
        <Button
          v-if="participant?.status === 'ongoing'"
          variant="outline"
          size="sm"
          @click="showWithdrawalModal = true"
        >
          <AlertTriangle class="w-3.5 h-3.5 mr-1" /> Ajukan Penghentian
        </Button>
        <Button
          v-if="participant?.status === 'ongoing'"
          variant="outline"
          size="sm"
          @click="showExtensionModal = true"
        >
          Perpanjang
        </Button>
      </div>
    </div>

    <div v-if="loading" class="py-12 text-center text-sm text-slate-400">Memuat detail peserta...</div>

    <template v-else-if="participant">
      <!-- Summary counters -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
        <Card class="border border-slate-200/80 shadow-2xs">
          <p class="text-2xs font-semibold uppercase tracking-wider text-slate-500">Progress Logbook</p>
          <p class="mt-1.5 text-2xl font-bold text-slate-900">{{ logbookSummary?.progress_percentage ?? 0 }}%</p>
          <p class="text-2xs text-slate-500 mt-0.5">
            {{ logbookSummary?.approved ?? 0 }} disetujui / {{ logbookSummary?.total ?? 0 }} entri
          </p>
        </Card>
        <Card class="border border-slate-200/80 shadow-2xs">
          <p class="text-2xs font-semibold uppercase tracking-wider text-slate-500">Kehadiran</p>
          <p class="mt-1.5 text-2xl font-bold text-slate-900">{{ attendanceSummary?.attendance_percentage ?? 0 }}%</p>
          <p class="text-2xs text-slate-500 mt-0.5">{{ attendanceSummary?.present ?? 0 }} hari hadir</p>
        </Card>
        <Card class="border border-slate-200/80 shadow-2xs">
          <p class="text-2xs font-semibold uppercase tracking-wider text-slate-500">Nilai Akhir</p>
          <p class="mt-1.5 text-2xl font-bold text-slate-900">{{ participant.final_score ?? '—' }}</p>
          <p class="text-2xs text-slate-500 mt-0.5">
            {{ participant.letter_grade ? `Grade ${participant.letter_grade}` : 'Belum difinalisasi' }}
          </p>
        </Card>
        <Card class="border border-slate-200/80 shadow-2xs">
          <p class="text-2xs font-semibold uppercase tracking-wider text-slate-500">SKS Diakui</p>
          <p class="mt-1.5 text-2xl font-bold text-slate-900">{{ recognitionSummary?.recognized_credits ?? 0 }}</p>
          <p class="text-2xs text-slate-500 mt-0.5">
            dari maks {{ participant.program?.max_recognized_credits ?? '∞' }} SKS
          </p>
        </Card>
      </div>

      <Card class="border border-slate-200/80 shadow-2xs mb-5">
        <Tabs v-model="activeTab" :tabs="tabs" />
      </Card>

      <!-- Ringkasan -->
      <div v-if="activeTab === 'ringkasan'" class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <Card class="border border-slate-200/80 shadow-2xs lg:col-span-2">
          <template #header><p class="font-bold text-slate-800 text-sm">Data Peserta</p></template>
          <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3.5 text-xs">
            <div>
              <dt class="text-slate-500">NIM</dt>
              <dd class="font-medium text-slate-900 mt-0.5">{{ participant.student?.student_number ?? '-' }}</dd>
            </div>
            <div>
              <dt class="text-slate-500">Program Studi</dt>
              <dd class="font-medium text-slate-900 mt-0.5">{{ participant.student?.study_program?.name ?? '-' }}</dd>
            </div>
            <div>
              <dt class="text-slate-500">Program MBKM</dt>
              <dd class="font-medium text-slate-900 mt-0.5">{{ participant.program?.name ?? '-' }}</dd>
            </div>
            <div>
              <dt class="text-slate-500">Jenis Program</dt>
              <dd class="font-medium text-slate-900 mt-0.5">{{ participant.program?.program_type?.name ?? '-' }}</dd>
            </div>
            <div>
              <dt class="text-slate-500">Periode Pelaksanaan</dt>
              <dd class="font-medium text-slate-900 mt-0.5">
                {{ formatDate(participant.start_date) }} – {{ formatDate(participant.end_date) }}
              </dd>
            </div>
            <div>
              <dt class="text-slate-500">Periode Akademik</dt>
              <dd class="font-medium text-slate-900 mt-0.5">{{ participant.program?.semester?.name ?? '-' }}</dd>
            </div>
          </dl>
        </Card>

        <Card class="border border-slate-200/80 shadow-2xs">
          <template #header><p class="font-bold text-slate-800 text-sm">Ringkasan Komponen</p></template>
          <div v-if="!finalScore?.breakdown?.length" class="py-4 text-center text-xs text-slate-400">
            Belum ada komponen penilaian.
          </div>
          <div v-else class="space-y-2.5 text-xs">
            <div v-for="c in finalScore.breakdown" :key="c.component_id" class="flex items-center justify-between gap-3">
              <div class="min-w-0">
                <p class="text-slate-800 truncate">{{ c.component_name }}</p>
                <p class="text-2xs text-slate-500">bobot {{ c.weight }}%</p>
              </div>
              <span :class="c.assessors > 0 ? 'font-semibold text-slate-900' : 'text-slate-400'">
                {{ c.assessors > 0 ? c.average_score : '—' }}
              </span>
            </div>
            <div class="pt-2.5 border-t border-slate-100 flex items-center justify-between">
              <span class="font-semibold text-slate-700">Nilai Akhir</span>
              <span class="font-bold text-slate-900">{{ finalScore?.final_score ?? '—' }}</span>
            </div>
          </div>
        </Card>
      </div>

      <!-- Penempatan & Pembimbing -->
      <div v-else-if="activeTab === 'penempatan'" class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <Card class="border border-slate-200/80 shadow-2xs">
          <template #header><p class="font-bold text-slate-800 text-sm">Penempatan</p></template>
          <div class="space-y-4 text-xs">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block font-semibold text-slate-700 mb-1.5">Mitra</label>
                <select
                  v-model="placementForm.partner_id"
                  class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none"
                >
                  <option :value="null">Pilih mitra</option>
                  <option v-for="p in partners" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
              </div>
              <div>
                <label class="block font-semibold text-slate-700 mb-1.5">Lokasi Program</label>
                <select
                  v-model="placementForm.location_id"
                  class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none"
                >
                  <option :value="null">Pilih lokasi</option>
                  <option v-for="l in participant.program?.locations ?? []" :key="l.id" :value="l.id">{{ l.name }}</option>
                </select>
              </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div>
                <label class="block font-semibold text-slate-700 mb-1.5">Divisi</label>
                <Input v-model="placementForm.division" />
              </div>
              <div>
                <label class="block font-semibold text-slate-700 mb-1.5">Posisi</label>
                <Input v-model="placementForm.position" />
              </div>
              <div>
                <label class="block font-semibold text-slate-700 mb-1.5">Batch</label>
                <Input v-model="placementForm.batch" />
              </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block font-semibold text-slate-700 mb-1.5">Mulai</label>
                <Input v-model="placementForm.start_date" type="date" />
              </div>
              <div>
                <label class="block font-semibold text-slate-700 mb-1.5">Selesai</label>
                <Input v-model="placementForm.end_date" type="date" />
              </div>
            </div>
            <div class="pt-3 border-t border-slate-100">
              <p class="font-bold text-slate-800 mb-3">Pembimbing Lapangan (Eksternal)</p>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <Input v-model="placementForm.field_supervisor_name" placeholder="Nama pembimbing lapangan" />
                <Input v-model="placementForm.field_supervisor_position" placeholder="Jabatan" />
                <Input v-model="placementForm.field_supervisor_email" placeholder="Email" />
                <Input v-model="placementForm.field_supervisor_phone" placeholder="Telepon" />
              </div>
              <Input v-model="placementForm.field_supervisor_organization" placeholder="Instansi" class="mt-4" />
            </div>
            <div>
              <label class="block font-semibold text-slate-700 mb-1.5">Catatan</label>
              <Textarea v-model="placementForm.notes" :rows="2" />
            </div>
            <Button
              v-if="canManageParticipant"
              variant="primary"
              size="sm"
              class="bg-brand-900 text-white"
              :loading="saving"
              @click="handleSavePlacement"
            >
              <Save class="w-3.5 h-3.5 mr-1" /> Simpan Penempatan
            </Button>
          </div>
        </Card>

        <Card class="border border-slate-200/80 shadow-2xs">
          <template #header>
            <p class="font-bold text-slate-800 text-sm">Pembimbing</p>
            <Button v-if="canManageParticipant" variant="outline" size="sm" @click="showSupervisorModal = true">
              <Plus class="w-3.5 h-3.5 mr-1" /> Tambah
            </Button>
          </template>

          <div v-if="(participant.supervisors ?? []).length === 0" class="py-8 text-center text-xs text-slate-400">
            Belum ada pembimbing yang ditetapkan.
          </div>

          <div v-else class="space-y-2.5">
            <div
              v-for="s in participant.supervisors"
              :key="s.id"
              class="p-3 border border-slate-200 rounded-lg flex items-start justify-between gap-3"
            >
              <div class="min-w-0">
                <p class="font-semibold text-slate-900 text-xs">
                  {{ s.lecturer?.full_name ?? s.external_name ?? '-' }}
                </p>
                <p class="text-2xs text-slate-500 mt-0.5">
                  {{ SUPERVISOR_ROLE_LABELS[s.role] ?? s.role }}
                  <span v-if="s.lecturer?.nidn"> • NIDN {{ s.lecturer.nidn }}</span>
                </p>
                <p v-if="s.external_organization" class="text-2xs text-slate-500">{{ s.external_organization }}</p>
                <p v-if="s.external_email" class="text-2xs text-slate-400">{{ s.external_email }}</p>
              </div>
              <button
                v-if="canManageParticipant"
                type="button"
                class="p-1.5 rounded-lg border border-rose-200 text-rose-600 hover:bg-rose-50 shrink-0"
                @click="handleDeleteSupervisor(s.id)"
              >
                <Trash2 class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>
        </Card>
      </div>

      <!-- Learning agreement -->
      <Card v-else-if="activeTab === 'la'" class="border border-slate-200/80 shadow-2xs">
        <template #header>
          <div class="flex items-center gap-2">
            <p class="font-bold text-slate-800 text-sm">Learning Agreement / Rencana Rekognisi</p>
            <MbkmStatusBadge
              v-if="participant.learning_agreement"
              :value="participant.learning_agreement.status"
              :labels="LEARNING_AGREEMENT_STATUS_LABELS"
              :variants="LEARNING_AGREEMENT_STATUS_VARIANTS"
            />
          </div>
          <div class="flex items-center gap-2">
            <span class="text-2xs font-bold px-2 py-1 rounded border bg-slate-50 text-slate-700 border-slate-200">
              Total {{ laTotalCredits }} SKS
            </span>
            <template v-if="!participant.learning_agreement?.locked_at">
              <Button variant="outline" size="sm" @click="addLaItem">
                <Plus class="w-3.5 h-3.5 mr-1" /> Item
              </Button>
              <Button variant="primary" size="sm" class="bg-brand-900 text-white" :loading="saving" @click="handleSaveLa">
                <Save class="w-3.5 h-3.5 mr-1" /> Simpan
              </Button>
            </template>
            <Button v-else-if="isManager" variant="outline" size="sm" @click="handleLaReopen">
              <RotateCcw class="w-3.5 h-3.5 mr-1" /> Buka untuk Revisi
            </Button>
          </div>
        </template>

        <div
          v-if="participant.learning_agreement?.locked_at"
          class="mb-4 p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800 flex items-center gap-2"
        >
          <Lock class="w-3.5 h-3.5" />
          Learning agreement sudah terkunci dan tidak dapat diubah melalui form biasa. Gunakan alur revisi formal.
        </div>

        <div class="space-y-4 text-xs">
          <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
            <div class="sm:col-span-2">
              <label class="block font-semibold text-slate-700 mb-1.5">Judul</label>
              <Input v-model="laForm.title" :disabled="!!participant.learning_agreement?.locked_at" />
            </div>
            <div>
              <label class="block font-semibold text-slate-700 mb-1.5">Mulai</label>
              <Input v-model="laForm.period_start" type="date" :disabled="!!participant.learning_agreement?.locked_at" />
            </div>
            <div>
              <label class="block font-semibold text-slate-700 mb-1.5">Selesai</label>
              <Input v-model="laForm.period_end" type="date" :disabled="!!participant.learning_agreement?.locked_at" />
            </div>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
              <thead>
                <tr class="bg-slate-50/80 text-slate-600 font-semibold border-b border-slate-200 text-3xs uppercase tracking-wider">
                  <th class="py-2.5 px-3">AKTIVITAS</th>
                  <th class="py-2.5 px-3 w-64">MATA KULIAH KONVERSI</th>
                  <th class="py-2.5 px-3 w-24 text-center">SKS</th>
                  <th class="py-2.5 px-3 w-24 text-center">TARGET</th>
                  <th class="py-2.5 px-3 w-16"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="(item, index) in laForm.items" :key="index">
                  <td class="py-2.5 px-3">
                    <Input
                      v-model="item.activity_title"
                      placeholder="Nama aktivitas"
                      :disabled="!!participant.learning_agreement?.locked_at"
                    />
                    <Input
                      v-model="item.activity_description"
                      placeholder="Keterangan (opsional)"
                      class="mt-2"
                      :disabled="!!participant.learning_agreement?.locked_at"
                    />
                  </td>
                  <td class="py-2.5 px-3">
                    <select
                      v-model="item.course_id"
                      :disabled="!!participant.learning_agreement?.locked_at"
                      class="w-full px-2.5 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none disabled:bg-slate-50"
                    >
                      <option :value="null">Belum dipetakan</option>
                      <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.code }} — {{ c.name }}</option>
                    </select>
                  </td>
                  <td class="py-2.5 px-3">
                    <Input v-model.number="item.credits" type="number" min="0" max="24" :disabled="!!participant.learning_agreement?.locked_at" />
                  </td>
                  <td class="py-2.5 px-3">
                    <Input v-model="item.target_grade" placeholder="A" :disabled="!!participant.learning_agreement?.locked_at" />
                  </td>
                  <td class="py-2.5 px-3 text-center">
                    <button
                      v-if="!participant.learning_agreement?.locked_at"
                      type="button"
                      class="p-1.5 rounded-lg border border-rose-200 text-rose-600 hover:bg-rose-50"
                      @click="laForm.items.splice(index, 1)"
                    >
                      <Trash2 class="w-3.5 h-3.5" />
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="!participant.learning_agreement?.locked_at" class="flex items-center gap-2 pt-2 border-t border-slate-100">
            <Button variant="outline" size="sm" @click="handleLaTransition('submitted')">
              <Send class="w-3.5 h-3.5 mr-1" /> Ajukan
            </Button>
            <Button v-if="canManageParticipant" variant="outline" size="sm" @click="handleLaTransition('reviewed')">
              <CheckCircle2 class="w-3.5 h-3.5 mr-1" /> Tandai Ditinjau
            </Button>
            <Button v-if="canManageParticipant" variant="primary" size="sm" class="bg-brand-900 text-white" @click="handleLaTransition('approved')">
              Setujui
            </Button>
            <Button v-if="canManageParticipant" variant="primary" size="sm" class="bg-emerald-700 text-white" @click="handleLaTransition('locked')">
              <Lock class="w-3.5 h-3.5 mr-1" /> Kunci
            </Button>
          </div>
        </div>
      </Card>

      <!-- Logbook -->
      <Card v-else-if="activeTab === 'logbook'" class="border border-slate-200/80 shadow-2xs">
        <template #header>
          <div>
            <p class="font-bold text-slate-800 text-sm">Logbook Aktivitas</p>
            <p class="text-2xs text-slate-500 mt-0.5">
              {{ logbookSummary?.approved ?? 0 }} disetujui • {{ logbookSummary?.submitted ?? 0 }} menunggu review •
              {{ logbookSummary?.total_hours ?? 0 }} jam
            </p>
          </div>
          <Button variant="primary" size="sm" class="bg-brand-900 text-white" @click="openLogbookModal">
            <Plus class="w-3.5 h-3.5 mr-1" /> Entri Logbook
          </Button>
        </template>

        <div v-if="logbooks.length === 0" class="py-8 text-center text-xs text-slate-400">Belum ada entri logbook.</div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="bg-slate-50/80 text-slate-600 font-semibold border-b border-slate-200 text-3xs uppercase tracking-wider">
                <th class="py-2.5 px-3 w-24">TANGGAL</th>
                <th class="py-2.5 px-3">AKTIVITAS</th>
                <th class="py-2.5 px-3 w-20 text-center">JAM</th>
                <th class="py-2.5 px-3 w-32 text-center">STATUS</th>
                <th class="py-2.5 px-3 w-52 text-center">AKSI</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="log in logbooks" :key="log.id">
                <td class="py-3 px-3 text-slate-700">{{ formatDate(log.log_date) }}</td>
                <td class="py-3 px-3">
                  <p class="font-semibold text-slate-900">{{ log.activity }}</p>
                  <p v-if="log.description" class="text-2xs text-slate-500 mt-0.5 line-clamp-2">{{ log.description }}</p>
                  <p v-if="log.review_notes" class="text-2xs text-amber-700 mt-1">Catatan: {{ log.review_notes }}</p>
                </td>
                <td class="py-3 px-3 text-center text-slate-700">{{ log.duration_hours ?? '-' }}</td>
                <td class="py-3 px-3 text-center">
                  <MbkmStatusBadge :value="log.status" :labels="LOGBOOK_STATUS_LABELS" :variants="LOGBOOK_STATUS_VARIANTS" />
                </td>
                <td class="py-3 px-3">
                  <div class="flex items-center justify-center gap-1.5 flex-wrap">
                    <button
                      v-if="log.status === 'draft' || log.status === 'revision_required'"
                      type="button"
                      class="px-2 py-1 rounded-lg border border-blue-200 text-blue-600 hover:bg-blue-50 text-2xs font-medium"
                      @click="handleSubmitLogbook(log.id)"
                    >
                      Ajukan
                    </button>
                    <template v-if="canManageParticipant && log.status === 'submitted'">
                      <button
                        type="button"
                        class="px-2 py-1 rounded-lg border border-emerald-200 text-emerald-600 hover:bg-emerald-50 text-2xs font-medium"
                        @click="handleReviewLogbook(log.id, 'approved')"
                      >
                        Setujui
                      </button>
                      <button
                        type="button"
                        class="px-2 py-1 rounded-lg border border-amber-200 text-amber-600 hover:bg-amber-50 text-2xs font-medium"
                        @click="handleReviewLogbook(log.id, 'revision_required')"
                      >
                        Revisi
                      </button>
                    </template>
                    <button
                      v-if="!log.locked_at"
                      type="button"
                      class="p-1.5 rounded-lg border border-rose-200 text-rose-600 hover:bg-rose-50"
                      @click="handleDeleteLogbook(log.id)"
                    >
                      <Trash2 class="w-3.5 h-3.5" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </Card>

      <!-- Presensi -->
      <Card v-else-if="activeTab === 'presensi'" class="border border-slate-200/80 shadow-2xs">
        <template #header>
          <div>
            <p class="font-bold text-slate-800 text-sm">Presensi MBKM</p>
            <p class="text-2xs text-slate-500 mt-0.5">
              Presensi kegiatan MBKM — terpisah dari presensi perkuliahan reguler.
              Minimum {{ attendanceSummary?.minimum_percentage ?? 0 }}%
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
              Bobot tiap komponen dikonfigurasi per program — bukan nilai tetap.
            </p>
          </div>
          <div class="flex items-center gap-2">
            <Button variant="outline" size="sm" @click="openAssessmentModal()">
              <Plus class="w-3.5 h-3.5 mr-1" /> Nilai
            </Button>
            <Button
              v-if="canManageParticipant && !participant.score_finalized_at"
              variant="primary"
              size="sm"
              class="bg-brand-900 text-white"
              @click="handleFinalizeScore"
            >
              <Award class="w-3.5 h-3.5 mr-1" /> Finalisasi Nilai
            </Button>
          </div>
        </template>

        <div v-if="participant.score_finalized_at" class="mb-4 p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-xs text-emerald-800 flex items-center justify-between gap-3">
          <span class="flex items-center gap-2">
            <Lock class="w-3.5 h-3.5" />
            Nilai telah difinalisasi pada {{ formatDate(participant.score_finalized_at) }}.
          </span>
          <span class="font-bold">
            {{ participant.final_score }} — {{ participant.letter_grade }} ({{ participant.grade_point }})
          </span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
          <div>
            <p class="font-bold text-slate-800 text-xs mb-2.5">Komponen &amp; Bobot</p>
            <div v-if="!finalScore?.breakdown?.length" class="py-4 text-center text-xs text-slate-400">
              Belum ada komponen penilaian untuk program ini.
            </div>
            <div v-else class="space-y-2">
              <div
                v-for="c in finalScore.breakdown"
                :key="c.component_id"
                class="p-3 border rounded-lg flex items-center justify-between gap-3"
                :class="c.assessors > 0 ? 'border-slate-200' : 'border-amber-200 bg-amber-50/50'"
              >
                <div class="min-w-0">
                  <p class="font-semibold text-slate-900 text-xs">{{ c.component_name }}</p>
                  <p class="text-2xs text-slate-500">
                    Bobot {{ c.weight }}% • {{ c.assessors > 0 ? `sudah dinilai (${c.assessors} penilai)` : 'belum dinilai' }}
                  </p>
                </div>
                <div class="text-right shrink-0">
                  <p class="font-bold text-slate-900">{{ c.average_score ?? '—' }}</p>
                  <p class="text-2xs text-slate-500">kontribusi {{ c.weighted_score ?? 0 }}</p>
                </div>
              </div>
            </div>
            <div class="mt-3 p-3 bg-slate-50 border border-slate-200 rounded-lg flex items-center justify-between">
              <span class="font-semibold text-slate-700 text-xs">Nilai Akhir Terhitung</span>
              <span class="font-bold text-slate-900">{{ finalScore?.final_score ?? '—' }}</span>
            </div>
            <p
              v-if="finalScore?.breakdown?.length && !finalScore?.is_complete"
              class="mt-2 text-2xs text-amber-700 flex items-start gap-1.5"
            >
              <AlertTriangle class="w-3.5 h-3.5 shrink-0 mt-px" />
              Masih ada komponen yang belum dinilai — finalisasi nilai akan ditolak sampai penilaian lengkap.
            </p>
          </div>

          <div>
            <p class="font-bold text-slate-800 text-xs mb-2.5">Riwayat Penilaian</p>
            <div v-if="assessments.length === 0" class="py-6 text-center text-xs text-slate-400">Belum ada penilaian.</div>
            <div v-else class="space-y-2">
              <div v-for="a in assessments" :key="a.id" class="p-3 border border-slate-200 rounded-lg">
                <div class="flex items-start justify-between gap-3">
                  <div class="min-w-0">
                    <p class="font-semibold text-slate-900 text-xs">{{ a.component?.name ?? '-' }}</p>
                    <p class="text-2xs text-slate-500 mt-0.5">
                      {{ ASSESSOR_TYPE_LABELS[a.assessor_type] ?? a.assessor_type }}
                      <span v-if="a.assessor_name"> • {{ a.assessor_name }}</span>
                      <span v-else-if="a.assessor"> • {{ a.assessor.name }}</span>
                    </p>
                    <p v-if="a.feedback" class="text-2xs text-slate-600 mt-1">{{ a.feedback }}</p>
                  </div>
                  <span class="font-bold text-slate-900 shrink-0">{{ a.score }}<span class="text-slate-400 font-normal">/{{ a.max_score }}</span></span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </Card>

      <!-- Rekognisi -->
      <Card v-else-if="activeTab === 'rekognisi'" class="border border-slate-200/80 shadow-2xs">
        <template #header>
          <div>
            <p class="font-bold text-slate-800 text-sm">Rekognisi / Konversi SKS</p>
            <p class="text-2xs text-slate-500 mt-0.5">
              {{ recognitionSummary?.recognized_credits ?? 0 }} SKS diakui
              <span v-if="recognitionSummary?.max_recognized_credits">
                dari maksimum {{ recognitionSummary.max_recognized_credits }} SKS
              </span>
            </p>
          </div>
          <Button variant="primary" size="sm" class="bg-brand-900 text-white" @click="openRecognitionModal">
            <Plus class="w-3.5 h-3.5 mr-1" /> Tambah Rekognisi
          </Button>
        </template>

        <div v-if="recognitions.length === 0" class="py-8 text-center text-xs text-slate-400">
          Belum ada rekognisi. Rekognisi yang disetujui otomatis masuk ke KRS, nilai, KHS, dan transkrip.
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="bg-slate-50/80 text-slate-600 font-semibold border-b border-slate-200 text-3xs uppercase tracking-wider">
                <th class="py-2.5 px-3">MATA KULIAH</th>
                <th class="py-2.5 px-3 w-20 text-center">SKS</th>
                <th class="py-2.5 px-3 w-32">TIPE</th>
                <th class="py-2.5 px-3 w-20 text-center">NILAI</th>
                <th class="py-2.5 px-3 w-28 text-center">SINKRON</th>
                <th class="py-2.5 px-3 w-32 text-center">STATUS</th>
                <th class="py-2.5 px-3 w-56 text-center">AKSI</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="r in recognitions" :key="r.id">
                <td class="py-3 px-3">
                  <p class="font-semibold text-slate-900">{{ r.course?.name ?? r.source_label ?? '-' }}</p>
                  <p v-if="r.course" class="text-2xs text-slate-500 font-mono">{{ r.course.code }}</p>
                </td>
                <td class="py-3 px-3 text-center font-semibold text-slate-800">{{ r.credits }}</td>
                <td class="py-3 px-3 text-slate-700 text-2xs">{{ RECOGNITION_TYPE_LABELS[r.recognition_type] ?? r.recognition_type }}</td>
                <td class="py-3 px-3 text-center text-slate-800">
                  {{ r.score ?? '-' }}
                  <span v-if="r.letter_grade" class="block text-2xs font-bold text-emerald-700">{{ r.letter_grade }}</span>
                </td>
                <td class="py-3 px-3 text-center">
                  <span
                    :class="[
                      'px-1.5 py-0.5 rounded text-4xs font-bold border',
                      r.sync_status === 'synced'
                        ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                        : 'bg-slate-50 text-slate-500 border-slate-200',
                    ]"
                  >
                    {{ r.sync_status === 'synced' ? 'TERSINKRON' : (r.sync_status ?? 'PENDING').toUpperCase() }}
                  </span>
                </td>
                <td class="py-3 px-3 text-center">
                  <MbkmStatusBadge :value="r.status" :labels="RECOGNITION_STATUS_LABELS" :variants="RECOGNITION_STATUS_VARIANTS" />
                </td>
                <td class="py-3 px-3">
                  <div class="flex items-center justify-center gap-1.5 flex-wrap">
                    <button
                      v-if="r.status === 'draft'"
                      type="button"
                      class="px-2 py-1 rounded-lg border border-blue-200 text-blue-600 hover:bg-blue-50 text-2xs font-medium"
                      @click="handleRecognitionTransition(r.id, 'submitted')"
                    >
                      Ajukan
                    </button>
                    <button
                      v-if="canManageParticipant && r.status === 'submitted'"
                      type="button"
                      class="px-2 py-1 rounded-lg border border-amber-200 text-amber-600 hover:bg-amber-50 text-2xs font-medium"
                      @click="handleRecognitionTransition(r.id, 'reviewed')"
                    >
                      Tinjau
                    </button>
                    <button
                      v-if="canManageParticipant && (r.status === 'submitted' || r.status === 'reviewed')"
                      type="button"
                      class="px-2 py-1 rounded-lg border border-emerald-200 text-emerald-600 hover:bg-emerald-50 text-2xs font-medium"
                      @click="handleRecognitionTransition(r.id, 'approved')"
                    >
                      Setujui
                    </button>
                    <button
                      v-if="isManager && r.status === 'approved'"
                      type="button"
                      class="px-2 py-1 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 text-2xs font-medium"
                      @click="handleRecognitionTransition(r.id, 'locked')"
                    >
                      Kunci
                    </button>
                    <button
                      v-if="isManager && r.locked_at"
                      type="button"
                      class="px-2 py-1 rounded-lg border border-amber-200 text-amber-600 hover:bg-amber-50 text-2xs font-medium"
                      @click="handleRecognitionCorrect(r.id)"
                    >
                      Koreksi
                    </button>
                    <button
                      v-if="!r.locked_at"
                      type="button"
                      class="p-1.5 rounded-lg border border-rose-200 text-rose-600 hover:bg-rose-50"
                      @click="handleDeleteRecognition(r.id)"
                    >
                      <Trash2 class="w-3.5 h-3.5" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </Card>

      <!-- Penyelesaian -->
      <div v-else-if="activeTab === 'penyelesaian'" class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <Card class="border border-slate-200/80 shadow-2xs">
          <template #header>
            <div class="flex items-center gap-2">
              <p class="font-bold text-slate-800 text-sm">Verifikasi Penyelesaian</p>
              <MbkmStatusBadge
                v-if="completion"
                :value="completion.status"
                :labels="COMPLETION_STATUS_LABELS"
                :variants="COMPLETION_STATUS_VARIANTS"
              />
            </div>
          </template>

          <p class="text-xs text-slate-600 mb-3">
            Syarat penyelesaian mengikuti kebijakan program. Berikut status tiap syarat:
          </p>

          <div class="space-y-2">
            <div
              v-for="req in completion?.requirements ?? []"
              :key="req.code"
              class="p-3 rounded-lg border flex items-start gap-2.5"
              :class="req.satisfied ? 'bg-emerald-50/60 border-emerald-200' : 'bg-rose-50/60 border-rose-200'"
            >
              <CheckCircle2 v-if="req.satisfied" class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" />
              <AlertTriangle v-else class="w-4 h-4 text-rose-600 shrink-0 mt-0.5" />
              <div class="min-w-0">
                <p class="font-semibold text-slate-900 text-xs">{{ req.label }}</p>
                <p class="text-2xs text-slate-600 mt-0.5">{{ req.detail }}</p>
              </div>
            </div>
          </div>

          <div v-if="canManageParticipant" class="flex items-center gap-2 mt-4 pt-4 border-t border-slate-100">
            <Button variant="primary" size="sm" class="bg-brand-900 text-white" @click="handleVerifyCompletion(false)">
              <CheckCircle2 class="w-3.5 h-3.5 mr-1" /> Verifikasi
            </Button>
            <Button
              v-if="!completion?.is_complete"
              variant="outline"
              size="sm"
              @click="handleVerifyCompletion(true)"
            >
              Verifikasi Paksa
            </Button>
          </div>
        </Card>

        <div class="space-y-5">
          <Card class="border border-slate-200/80 shadow-2xs">
            <template #header><p class="font-bold text-slate-800 text-sm">Dokumen Penyelesaian</p></template>
            <div v-if="completion?.certificate" class="p-3 border border-slate-200 rounded-lg mb-3 text-xs">
              <p class="font-semibold text-slate-900">{{ completion.certificate.title || completion.certificate.original_name }}</p>
              <p class="text-2xs text-slate-500 mt-0.5">{{ completion.certificate.category }}</p>
            </div>
            <p v-else class="text-xs text-slate-500 mb-3">Belum ada sertifikat/surat keterangan yang diunggah.</p>
            <Button v-if="canManageParticipant" variant="outline" size="sm" @click="showCertificateModal = true">
              <Upload class="w-3.5 h-3.5 mr-1" /> Unggah Sertifikat
            </Button>
          </Card>

          <Card class="border border-slate-200/80 shadow-2xs">
            <template #header><p class="font-bold text-slate-800 text-sm">Riwayat Alur</p></template>
            <div v-if="history.length === 0" class="py-6 text-center text-xs text-slate-400">Belum ada riwayat.</div>
            <ol v-else class="space-y-3">
              <li v-for="h in history" :key="h.id" class="flex gap-3 text-xs">
                <span class="w-1.5 h-1.5 rounded-full bg-brand-700 mt-1.5 shrink-0" />
                <div class="min-w-0">
                  <p class="font-semibold text-slate-800">{{ h.action }}</p>
                  <p class="text-2xs text-slate-500">
                    {{ formatDate(h.created_at) }}
                    <span v-if="h.actor"> • {{ h.actor.name }}</span>
                  </p>
                  <p v-if="h.notes" class="text-2xs text-slate-600 mt-0.5">{{ h.notes }}</p>
                </div>
              </li>
            </ol>
          </Card>
        </div>
      </div>
    </template>

    <!-- Logbook modal -->
    <Modal v-model:open="showLogbookModal" title="Entri Logbook Aktivitas" size="lg">
      <div class="space-y-4 text-xs">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Tanggal <span class="text-rose-500">*</span></label>
            <Input v-model="logbookForm.log_date" type="date" />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Periode</label>
            <Input v-model="logbookForm.period_label" placeholder="mis. Minggu ke-3" />
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
            Simpan Logbook
          </Button>
        </div>
      </template>
    </Modal>

    <!-- Attendance modal -->
    <Modal v-model:open="showAttendanceModal" title="Catat Presensi MBKM" size="lg">
      <div class="space-y-4 text-xs">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Tanggal <span class="text-rose-500">*</span></label>
            <Input v-model="attendanceForm.attendance_date" type="date" />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Status <span class="text-rose-500">*</span></label>
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
        <p class="text-2xs text-slate-500">
          Satu peserta hanya boleh memiliki satu presensi per tanggal — data yang sama akan diperbarui, bukan diduplikasi.
        </p>
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

    <!-- Assessment modal -->
    <Modal v-model:open="showAssessmentModal" title="Input Penilaian" size="lg">
      <div class="space-y-4 text-xs">
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Komponen <span class="text-rose-500">*</span></label>
          <select v-model="assessmentForm.component_id" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none">
            <option :value="null">Pilih komponen</option>
            <option v-for="c in assessmentComponents" :key="c.id" :value="c.id">
              {{ c.name }} (bobot {{ c.weight }}%, maks {{ c.max_score }})
            </option>
          </select>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Tipe Penilai</label>
            <select v-model="assessmentForm.assessor_type" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none">
              <option v-for="o in optionsFrom(ASSESSOR_TYPE_LABELS)" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Nama Penilai</label>
            <Input v-model="assessmentForm.assessor_name" placeholder="Nama lengkap penguji" />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Nilai Maksimum</label>
            <Input v-model.number="assessmentForm.max_score" type="number" min="1" />
          </div>
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Nilai <span class="text-rose-500">*</span></label>
          <Input v-model.number="assessmentForm.score" type="number" min="0" step="0.01" />
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Umpan Balik</label>
          <Textarea v-model="assessmentForm.feedback" :rows="2" />
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Rekomendasi</label>
          <Textarea v-model="assessmentForm.recommendation" :rows="2" />
        </div>
      </div>
      <template #footer>
        <div class="flex items-center justify-end gap-2">
          <Button variant="outline" size="sm" @click="showAssessmentModal = false">Batal</Button>
          <Button variant="primary" size="sm" :loading="saving" class="bg-brand-900 text-white" @click="handleSaveAssessment">
            Simpan Penilaian
          </Button>
        </div>
      </template>
    </Modal>

    <!-- Recognition modal -->
    <Modal v-model:open="showRecognitionModal" title="Tambah Rekognisi SKS" size="lg">
      <div class="space-y-4 text-xs">
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Mata Kuliah Konversi <span class="text-rose-500">*</span></label>
          <select
            :value="recognitionForm.course_id"
            class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none"
            @change="onCourseChange(($event.target as HTMLSelectElement).value ? Number(($event.target as HTMLSelectElement).value) : null)"
          >
            <option :value="null">Pilih mata kuliah</option>
            <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.code }} — {{ c.name }} ({{ c.credits }} SKS)</option>
          </select>
          <p class="mt-1.5 text-2xs text-slate-500">
            Mata kuliah harus berasal dari kurikulum aktif program studi mahasiswa — divalidasi di server.
          </p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">SKS Diakui</label>
            <Input v-model.number="recognitionForm.credits" type="number" min="1" max="24" />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Tipe Rekognisi</label>
            <select v-model="recognitionForm.recognition_type" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none">
              <option v-for="o in optionsFrom(RECOGNITION_TYPE_LABELS)" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Nilai</label>
            <Input v-model.number="recognitionForm.score" type="number" min="0" max="100" step="0.01" />
          </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Sumber / Label Aktivitas</label>
            <Input v-model="recognitionForm.source_label" placeholder="mis. Proyek Magang Batch 3" />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Log Aktivitas Terkait</label>
            <select v-model="recognitionForm.activity_log_id" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none">
              <option :value="null">Tidak ditautkan</option>
              <option v-for="l in logbooks" :key="l.id" :value="l.id">
                {{ formatDate(l.log_date) }} — {{ l.activity }}
              </option>
            </select>
          </div>
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Catatan</label>
          <Textarea v-model="recognitionForm.notes" :rows="2" />
        </div>
      </div>
      <template #footer>
        <div class="flex items-center justify-end gap-2">
          <Button variant="outline" size="sm" @click="showRecognitionModal = false">Batal</Button>
          <Button variant="primary" size="sm" :loading="saving" class="bg-brand-900 text-white" @click="handleSaveRecognition">
            Simpan Rekognisi
          </Button>
        </div>
      </template>
    </Modal>

    <!-- Certificate modal -->
    <Modal v-model:open="showCertificateModal" title="Unggah Dokumen Penyelesaian" size="md">
      <div class="space-y-4 text-xs">
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Judul Dokumen</label>
          <Input v-model="certificateTitle" placeholder="mis. Sertifikat Magang Bersertifikat" />
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Berkas <span class="text-rose-500">*</span></label>
          <input
            type="file"
            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
            class="w-full text-xs file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-brand-900 file:text-white file:text-xs file:font-semibold"
            @change="onCertificateSelected"
          />
          <p class="mt-1.5 text-2xs text-slate-500">Format PDF/gambar/dokumen, maksimum 10 MB.</p>
        </div>
      </div>
      <template #footer>
        <div class="flex items-center justify-end gap-2">
          <Button variant="outline" size="sm" @click="showCertificateModal = false">Batal</Button>
          <Button variant="primary" size="sm" :loading="saving" class="bg-brand-900 text-white" @click="handleUploadCertificate">
            Unggah
          </Button>
        </div>
      </template>
    </Modal>

    <!-- Withdrawal modal -->
    <Modal v-model:open="showWithdrawalModal" title="Pengunduran Diri / Penghentian" size="md">
      <div class="space-y-4 text-xs">
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Jenis</label>
          <select v-model="withdrawalForm.type" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none">
            <option value="withdrawal">Pengunduran Diri</option>
            <option value="cancellation">Pembatalan</option>
            <option value="termination">Penghentian</option>
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
        <p class="text-2xs text-slate-500">
          Riwayat pendaftaran dan kepesertaan tetap tersimpan — tidak ada data historis yang dihapus.
        </p>
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

    <!-- Extension modal -->
    <Modal v-model:open="showExtensionModal" title="Perpanjangan Pelaksanaan" size="md">
      <div class="space-y-4 text-xs">
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Tanggal Selesai Baru <span class="text-rose-500">*</span></label>
          <Input v-model="extensionForm.new_end_date" type="date" />
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Alasan <span class="text-rose-500">*</span></label>
          <Textarea v-model="extensionForm.reason" :rows="3" />
        </div>
      </div>
      <template #footer>
        <div class="flex items-center justify-end gap-2">
          <Button variant="outline" size="sm" @click="showExtensionModal = false">Batal</Button>
          <Button variant="primary" size="sm" :loading="saving" class="bg-brand-900 text-white" @click="handleSaveExtension">
            Ajukan
          </Button>
        </div>
      </template>
    </Modal>

    <!-- Supervisor modal -->
    <Modal v-model:open="showSupervisorModal" title="Tambah Pembimbing" size="lg">
      <div class="space-y-4 text-xs">
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Peran <span class="text-rose-500">*</span></label>
          <select v-model="supervisorForm.role" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none">
            <option value="internal">Pembimbing Internal (Dosen)</option>
            <option value="co_supervisor">Pembimbing Pendamping (Dosen)</option>
            <option value="field">Pembimbing Lapangan (Eksternal)</option>
          </select>
        </div>

        <div v-if="supervisorForm.role !== 'field'">
          <label class="block font-semibold text-slate-700 mb-1.5">Dosen <span class="text-rose-500">*</span></label>
          <select v-model="supervisorForm.lecturer_id" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none">
            <option :value="null">Pilih dosen</option>
            <option v-for="l in lecturers" :key="l.id" :value="l.id">{{ l.full_name }} — {{ l.nidn }}</option>
          </select>
        </div>

        <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Nama <span class="text-rose-500">*</span></label>
            <Input v-model="supervisorForm.external_name" />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Jabatan</label>
            <Input v-model="supervisorForm.external_position" />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Email</label>
            <Input v-model="supervisorForm.external_email" />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Telepon</label>
            <Input v-model="supervisorForm.external_phone" />
          </div>
          <div class="sm:col-span-2">
            <label class="block font-semibold text-slate-700 mb-1.5">Instansi</label>
            <Input v-model="supervisorForm.external_organization" />
          </div>
        </div>
      </div>
      <template #footer>
        <div class="flex items-center justify-end gap-2">
          <Button variant="outline" size="sm" @click="showSupervisorModal = false">Batal</Button>
          <Button variant="primary" size="sm" :loading="saving" class="bg-brand-900 text-white" @click="handleSaveSupervisor">
            Tetapkan
          </Button>
        </div>
      </template>
    </Modal>
  </PageContainer>
</template>
