<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  Users,
  BookOpen,
  Award,
  AlertTriangle,
  Eye,
  CheckCircle2,
  MessageSquare,
  MapPin,
} from 'lucide-vue-next'
import { mbkmService } from '@/services/api/mbkm'
import { useToast } from '@/composables/useToast'
import {
  ISSUE_SEVERITY_LABELS,
  ISSUE_SEVERITY_VARIANTS,
  ISSUE_STATUS_LABELS,
  ISSUE_STATUS_VARIANTS,
  PARTICIPANT_STATUS_LABELS,
  PARTICIPANT_STATUS_VARIANTS,
  RECOGNITION_STATUS_LABELS,
  RECOGNITION_STATUS_VARIANTS,
} from '@/types/mbkm'
import type { MbkmActivityLog, MbkmIssue, MbkmParticipant, MbkmRecognition } from '@/types/mbkm'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Textarea from '@/components/ui/Textarea.vue'
import Modal from '@/components/ui/Modal.vue'
import Tabs, { type TabItem } from '@/components/ui/Tabs.vue'
import MbkmStatusBadge from '@/pages/mbkm/components/MbkmStatusBadge.vue'
import MbkmStatCard from '@/pages/mbkm/components/MbkmStatCard.vue'

const router = useRouter()
const toast = useToast()

const loading = ref(true)
const dashboard = ref<any>(null)
const participants = ref<MbkmParticipant[]>([])
const pendingLogbooks = ref<MbkmActivityLog[]>([])
const pendingRecognitions = ref<MbkmRecognition[]>([])
const issues = ref<MbkmIssue[]>([])

const activeTab = ref('bimbingan')
const tabs: TabItem[] = [
  { id: 'bimbingan', label: 'Peserta Bimbingan' },
  { id: 'logbook', label: 'Logbook Menunggu Review' },
  { id: 'rekognisi', label: 'Rekognisi' },
  { id: 'masalah', label: 'Permasalahan' },
]

const showReviewModal = ref(false)
const reviewTarget = ref<MbkmActivityLog | null>(null)
const reviewDecision = ref('approved')
const reviewNotes = ref('')
const saving = ref(false)

async function load() {
  loading.value = true
  try {
    const [dashRes, partRes, logRes, recRes, issueRes] = await Promise.allSettled([
      mbkmService.lecturerDashboard(),
      mbkmService.getParticipants({ per_page: 100 }),
      mbkmService.getAllLogbooks({ status: 'submitted', per_page: 100 }),
      mbkmService.getRecognitions({ per_page: 100 }),
      mbkmService.getIssues({ per_page: 100 }),
    ])

    if (dashRes.status === 'fulfilled') dashboard.value = dashRes.value.data
    if (partRes.status === 'fulfilled') participants.value = partRes.value.data || []
    if (logRes.status === 'fulfilled') pendingLogbooks.value = logRes.value.data || []
    if (recRes.status === 'fulfilled') {
      pendingRecognitions.value = (recRes.value.data || []).filter(
        (r) => r.status === 'submitted' || r.status === 'reviewed'
      )
    }
    if (issueRes.status === 'fulfilled') {
      issues.value = (issueRes.value.data || []).filter((i) => i.status !== 'closed')
    }
  } catch (err: any) {
    toast.error(err.message || 'Gagal memuat data bimbingan MBKM')
  } finally {
    loading.value = false
  }
}

function openReview(log: MbkmActivityLog, decision: string) {
  reviewTarget.value = log
  reviewDecision.value = decision
  reviewNotes.value = ''
  showReviewModal.value = true
}

async function handleReview() {
  if (!reviewTarget.value) return
  if (reviewDecision.value !== 'approved' && !reviewNotes.value.trim()) {
    toast.error('Catatan wajib diisi untuk revisi/penolakan')
    return
  }
  saving.value = true
  try {
    await mbkmService.reviewLogbook(reviewTarget.value.id, reviewDecision.value, reviewNotes.value || undefined)
    toast.success('Review logbook berhasil disimpan')
    showReviewModal.value = false
    await load()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menyimpan review')
  } finally {
    saving.value = false
  }
}

async function handleResolveIssue(issue: MbkmIssue) {
  const resolution = prompt('Uraian penyelesaian permasalahan:')
  if (!resolution) return
  try {
    await mbkmService.updateIssue(issue.id, { status: 'resolved', resolution })
    toast.success('Permasalahan ditandai selesai')
    await load()
  } catch (err: any) {
    toast.error(err.message || 'Gagal memperbarui permasalahan')
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
    <div class="mb-6">
      <h1 class="text-xl font-bold text-slate-900 tracking-tight">Monitoring Bimbingan MBKM</h1>
      <p class="text-xs text-slate-500 mt-1">
        Peserta yang Anda bimbing, logbook yang menunggu review, rekognisi, dan permasalahan
      </p>
    </div>

    <div v-if="loading" class="py-12 text-center text-sm text-slate-400">Memuat data bimbingan...</div>

    <template v-else>
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
        <MbkmStatCard
          label="Peserta Bimbingan"
          :value="participants.length"
          :icon="Users"
          tone="primary"
        />
        <MbkmStatCard
          label="Logbook Menunggu Review"
          :value="pendingLogbooks.length"
          :icon="BookOpen"
          tone="warning"
        />
        <MbkmStatCard
          label="Rekognisi Menunggu"
          :value="pendingRecognitions.length"
          :icon="Award"
          tone="primary"
        />
        <MbkmStatCard
          label="Permasalahan Aktif"
          :value="issues.length"
          :icon="AlertTriangle"
          tone="danger"
        />
      </div>

      <Card class="border border-slate-200/80 shadow-2xs mb-5">
        <Tabs v-model="activeTab" :tabs="tabs" />
      </Card>

      <!-- Peserta bimbingan -->
      <Card v-if="activeTab === 'bimbingan'" class="border border-slate-200/80 shadow-2xs">
        <template #header><p class="font-bold text-slate-800 text-sm">Peserta Bimbingan</p></template>

        <div v-if="participants.length === 0" class="py-8 text-center text-xs text-slate-400">
          Belum ada peserta MBKM yang dibimbing.
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="bg-slate-50/80 text-slate-600 font-semibold border-b border-slate-200 text-3xs uppercase tracking-wider">
                <th class="py-2.5 px-3 w-16 text-center">NO</th>
                <th class="py-2.5 px-3">MAHASISWA</th>
                <th class="py-2.5 px-3">PROGRAM</th>
                <th class="py-2.5 px-3">PENEMPATAN</th>
                <th class="py-2.5 px-3 w-28 text-center">NILAI</th>
                <th class="py-2.5 px-3 w-28 text-center">SKS</th>
                <th class="py-2.5 px-3 w-32 text-center">STATUS</th>
                <th class="py-2.5 px-3 w-16 text-center">AKSI</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="(p, index) in participants" :key="p.id">
                <td class="py-3 px-3 text-center text-slate-500">{{ index + 1 }}</td>
                <td class="py-3 px-3">
                  <p class="font-semibold text-slate-900">{{ p.student?.full_name }}</p>
                  <p class="text-2xs text-slate-500">{{ p.student?.student_number }}</p>
                </td>
                <td class="py-3 px-3">
                  <p class="text-slate-800">{{ p.program?.name }}</p>
                  <p class="text-2xs text-slate-400">{{ formatDate(p.start_date) }} – {{ formatDate(p.end_date) }}</p>
                </td>
                <td class="py-3 px-3 text-slate-700 text-2xs">
                  <span class="flex items-center gap-1">
                    <MapPin class="w-3 h-3 text-rose-500" />
                    {{ p.placement?.partner?.name ?? 'Belum ditempatkan' }}
                  </span>
                </td>
                <td class="py-3 px-3 text-center font-semibold text-slate-800">{{ p.final_score ?? '—' }}</td>
                <td class="py-3 px-3 text-center text-slate-800">{{ p.recognized_credits ?? 0 }}</td>
                <td class="py-3 px-3 text-center">
                  <MbkmStatusBadge :value="p.status" :labels="PARTICIPANT_STATUS_LABELS" :variants="PARTICIPANT_STATUS_VARIANTS" />
                </td>
                <td class="py-3 px-3 text-center">
                  <button
                    type="button"
                    class="p-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50"
                    title="Detail"
                    @click="router.push(`/mbkm/participants/${p.id}`)"
                  >
                    <Eye class="w-3.5 h-3.5" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </Card>

      <!-- Logbook -->
      <Card v-else-if="activeTab === 'logbook'" class="border border-slate-200/80 shadow-2xs">
        <template #header><p class="font-bold text-slate-800 text-sm">Logbook Menunggu Review</p></template>

        <div v-if="pendingLogbooks.length === 0" class="py-8 text-center text-xs text-slate-400">
          Tidak ada logbook yang menunggu review.
        </div>

        <div v-else class="space-y-2.5">
          <div v-for="log in pendingLogbooks" :key="log.id" class="p-3.5 border border-slate-200 rounded-lg">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="font-semibold text-slate-900 text-xs">{{ log.activity }}</p>
                <p class="text-2xs text-slate-500 mt-0.5">
                  {{ log.participant?.student?.full_name }} •
                  {{ log.participant?.program?.name }} •
                  {{ formatDate(log.log_date) }}
                  <span v-if="log.duration_hours"> • {{ log.duration_hours }} jam</span>
                </p>
                <p v-if="log.description" class="text-2xs text-slate-600 mt-1.5">{{ log.description }}</p>
              </div>
              <div class="shrink-0 flex items-center gap-1.5">
                <button
                  type="button"
                  class="px-2 py-1 rounded-lg border border-emerald-200 text-emerald-600 hover:bg-emerald-50 text-2xs font-medium flex items-center gap-1"
                  @click="openReview(log, 'approved')"
                >
                  <CheckCircle2 class="w-3 h-3" /> Setujui
                </button>
                <button
                  type="button"
                  class="px-2 py-1 rounded-lg border border-amber-200 text-amber-600 hover:bg-amber-50 text-2xs font-medium flex items-center gap-1"
                  @click="openReview(log, 'revision_required')"
                >
                  <MessageSquare class="w-3 h-3" /> Revisi
                </button>
              </div>
            </div>
          </div>
        </div>
      </Card>

      <!-- Rekognisi -->
      <Card v-else-if="activeTab === 'rekognisi'" class="border border-slate-200/80 shadow-2xs">
        <template #header><p class="font-bold text-slate-800 text-sm">Rekognisi Menunggu Tindak Lanjut</p></template>

        <div v-if="pendingRecognitions.length === 0" class="py-8 text-center text-xs text-slate-400">
          Tidak ada rekognisi yang menunggu.
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="bg-slate-50/80 text-slate-600 font-semibold border-b border-slate-200 text-3xs uppercase tracking-wider">
                <th class="py-2.5 px-3">PESERTA</th>
                <th class="py-2.5 px-3">MATA KULIAH</th>
                <th class="py-2.5 px-3 w-16 text-center">SKS</th>
                <th class="py-2.5 px-3 w-32 text-center">STATUS</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="r in pendingRecognitions" :key="r.id">
                <td class="py-3 px-3">
                  <p class="font-semibold text-slate-900">{{ r.participant?.student?.full_name ?? '-' }}</p>
                  <p class="text-2xs text-slate-500">{{ r.participant?.participant_number }}</p>
                </td>
                <td class="py-3 px-3 text-slate-800">{{ r.course?.name ?? r.source_label ?? '-' }}</td>
                <td class="py-3 px-3 text-center font-semibold text-slate-800">{{ r.credits }}</td>
                <td class="py-3 px-3 text-center">
                  <MbkmStatusBadge :value="r.status" :labels="RECOGNITION_STATUS_LABELS" :variants="RECOGNITION_STATUS_VARIANTS" />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </Card>

      <!-- Permasalahan -->
      <Card v-else-if="activeTab === 'masalah'" class="border border-slate-200/80 shadow-2xs">
        <template #header><p class="font-bold text-slate-800 text-sm">Permasalahan Peserta</p></template>

        <div v-if="issues.length === 0" class="py-8 text-center text-xs text-slate-400">
          Tidak ada permasalahan aktif.
        </div>

        <div v-else class="space-y-2.5">
          <div v-for="issue in issues" :key="issue.id" class="p-3.5 border border-slate-200 rounded-lg">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="font-semibold text-slate-900 text-xs">{{ issue.title }}</p>
                <p class="text-2xs text-slate-500 mt-0.5">
                  {{ issue.participant?.student?.full_name ?? '-' }} • {{ issue.category }} •
                  {{ formatDate(issue.created_at) }}
                </p>
                <p v-if="issue.description" class="text-2xs text-slate-600 mt-1.5">{{ issue.description }}</p>
                <p v-if="issue.resolution" class="text-2xs text-emerald-700 mt-1.5">Penyelesaian: {{ issue.resolution }}</p>
              </div>
              <div class="shrink-0 flex flex-col items-end gap-2">
                <div class="flex items-center gap-1.5">
                  <MbkmStatusBadge :value="issue.severity" :labels="ISSUE_SEVERITY_LABELS" :variants="ISSUE_SEVERITY_VARIANTS" />
                  <MbkmStatusBadge :value="issue.status" :labels="ISSUE_STATUS_LABELS" :variants="ISSUE_STATUS_VARIANTS" />
                </div>
                <button
                  v-if="issue.status !== 'resolved'"
                  type="button"
                  class="px-2 py-1 rounded-lg border border-emerald-200 text-emerald-600 hover:bg-emerald-50 text-2xs font-medium"
                  @click="handleResolveIssue(issue)"
                >
                  Tandai Selesai
                </button>
              </div>
            </div>
          </div>
        </div>
      </Card>
    </template>

    <!-- Review modal -->
    <Modal v-model:open="showReviewModal" title="Review Logbook" size="md">
      <div class="space-y-4 text-xs">
        <div class="p-3 bg-slate-50 rounded-lg border border-slate-200">
          <p class="font-semibold text-slate-900">{{ reviewTarget?.activity }}</p>
          <p class="text-2xs text-slate-500 mt-0.5">
            {{ reviewTarget?.participant?.student?.full_name }} • {{ formatDate(reviewTarget?.log_date) }}
          </p>
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Keputusan</label>
          <select v-model="reviewDecision" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none">
            <option value="approved">Setujui</option>
            <option value="revision_required">Minta Revisi</option>
            <option value="rejected">Tolak</option>
          </select>
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">
            Catatan <span v-if="reviewDecision !== 'approved'" class="text-rose-500">*</span>
          </label>
          <Textarea v-model="reviewNotes" :rows="3" />
        </div>
        <p class="text-2xs text-slate-500">
          Logbook yang disetujui akan terkunci dan tidak dapat diubah lagi.
        </p>
      </div>
      <template #footer>
        <div class="flex items-center justify-end gap-2">
          <Button variant="outline" size="sm" @click="showReviewModal = false">Batal</Button>
          <Button variant="primary" size="sm" :loading="saving" class="bg-brand-900 text-white" @click="handleReview">
            Simpan Review
          </Button>
        </div>
      </template>
    </Modal>
  </PageContainer>
</template>
