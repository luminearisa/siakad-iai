<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { CheckCircle2, AlertTriangle, Upload, Eye, XCircle } from 'lucide-vue-next'
import { mbkmService } from '@/services/api/mbkm'
import { useToast } from '@/composables/useToast'
import {
  COMPLETION_STATUS_LABELS,
  COMPLETION_STATUS_VARIANTS,
  WITHDRAWAL_TYPE_LABELS,
} from '@/types/mbkm'
import type { MbkmExtensionRequest, MbkmParticipant, MbkmWithdrawalRequest } from '@/types/mbkm'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Modal from '@/components/ui/Modal.vue'
import Tabs, { type TabItem } from '@/components/ui/Tabs.vue'
import MbkmStatusBadge from '@/pages/mbkm/components/MbkmStatusBadge.vue'
import MbkmFilterBar, { type MbkmFilters } from '@/pages/mbkm/components/MbkmFilterBar.vue'

const router = useRouter()
const toast = useToast()

const activeTab = ref('peserta')
const tabs: TabItem[] = [
  { id: 'peserta', label: 'Verifikasi Penyelesaian' },
  { id: 'pengunduran', label: 'Pengunduran Diri' },
  { id: 'perpanjangan', label: 'Perpanjangan' },
]

const loading = ref(false)
const saving = ref(false)
const participants = ref<MbkmParticipant[]>([])
const withdrawals = ref<MbkmWithdrawalRequest[]>([])
const extensions = ref<MbkmExtensionRequest[]>([])
const totalData = ref(0)
const perPage = ref(10)
const currentPage = ref(1)
const searchQuery = ref('')
const filters = ref<MbkmFilters>({})

const showEvalModal = ref(false)
const evalTarget = ref<MbkmParticipant | null>(null)
const evalResult = ref<any>(null)
const evalLoading = ref(false)

const showCertModal = ref(false)
const certTarget = ref<MbkmParticipant | null>(null)
const certFile = ref<File | null>(null)
const certTitle = ref('')

const stats = computed(() => ({
  total: totalData.value,
  complete: participants.value.filter((p) => p.completion?.status === 'complete').length,
  verified: participants.value.filter((p) => p.completion?.status === 'verified').length,
  incomplete: participants.value.filter((p) => p.completion?.status === 'incomplete').length,
}))

async function loadParticipants() {
  loading.value = true
  try {
    const params: Record<string, unknown> = {
      page: currentPage.value,
      per_page: perPage.value,
      ...Object.fromEntries(
        Object.entries(filters.value).filter(([, v]) => v !== null && v !== undefined && v !== '')
      ),
    }
    if (searchQuery.value.trim()) params.search = searchQuery.value.trim()

    const res = await mbkmService.getParticipants(params)
    participants.value = res.data || []
    totalData.value = res.meta?.total ?? participants.value.length
  } catch (err: any) {
    toast.error(err.message || 'Gagal memuat data peserta MBKM')
  } finally {
    loading.value = false
  }
}

async function loadRequests() {
  try {
    const [wRes, eRes] = await Promise.all([
      mbkmService.getWithdrawals({ per_page: 200 }),
      mbkmService.getExtensions({ per_page: 200 }),
    ])
    withdrawals.value = wRes.data || []
    extensions.value = eRes.data || []
  } catch (err: any) {
    toast.error(err.message || 'Gagal memuat permohonan MBKM')
  }
}

async function openEvaluation(p: MbkmParticipant) {
  evalTarget.value = p
  evalResult.value = null
  showEvalModal.value = true
  evalLoading.value = true
  try {
    const res = await mbkmService.evaluateCompletion(p.id)
    evalResult.value = res.data
  } catch (err: any) {
    toast.error(err.message || 'Gagal menghitung syarat penyelesaian')
  } finally {
    evalLoading.value = false
  }
}

async function handleVerify(force = false) {
  if (!evalTarget.value) return
  if (force && !confirm('Verifikasi paksa meskipun syarat belum lengkap terpenuhi?')) return
  saving.value = true
  try {
    await mbkmService.verifyCompletion(evalTarget.value.id, force)
    toast.success('Verifikasi penyelesaian selesai dijalankan')
    showEvalModal.value = false
    await loadParticipants()
  } catch (err: any) {
    toast.error(err.message || 'Syarat penyelesaian belum lengkap')
  } finally {
    saving.value = false
  }
}

function openCertModal(p: MbkmParticipant) {
  certTarget.value = p
  certFile.value = null
  certTitle.value = ''
  showCertModal.value = true
}

function onFileSelected(event: Event) {
  const input = event.target as HTMLInputElement
  certFile.value = input.files?.[0] ?? null
}

async function handleUploadCert() {
  if (!certTarget.value || !certFile.value) {
    toast.error('Pilih berkas terlebih dahulu')
    return
  }
  saving.value = true
  try {
    await mbkmService.attachCertificate(certTarget.value.id, certFile.value, 'certificate', certTitle.value || undefined)
    toast.success('Dokumen penyelesaian berhasil diunggah')
    showCertModal.value = false
    await loadParticipants()
  } catch (err: any) {
    toast.error(err.message || 'Gagal mengunggah dokumen')
  } finally {
    saving.value = false
  }
}

async function handleDecideWithdrawal(row: MbkmWithdrawalRequest, decision: 'approved' | 'rejected') {
  const notes = decision === 'rejected' ? prompt('Alasan penolakan:') : null
  if (decision === 'rejected' && !notes) return
  try {
    await mbkmService.decideWithdrawal(row.id, decision, notes ?? undefined)
    toast.success('Keputusan berhasil disimpan')
    await loadRequests()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menyimpan keputusan')
  }
}

async function handleDecideExtension(row: MbkmExtensionRequest, decision: 'approved' | 'rejected') {
  const notes = decision === 'rejected' ? prompt('Alasan penolakan:') : null
  if (decision === 'rejected' && !notes) return
  try {
    await mbkmService.decideExtension(row.id, decision, notes ?? undefined)
    toast.success('Keputusan berhasil disimpan')
    await loadRequests()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menyimpan keputusan')
  }
}

function handleFilterChange() {
  currentPage.value = 1
  loadParticipants()
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
  await Promise.all([loadParticipants(), loadRequests()])
})
</script>

<template>
  <PageContainer>
    <div class="mb-6">
      <h1 class="text-xl font-bold text-slate-900 tracking-tight">Penyelesaian Program MBKM</h1>
      <p class="text-xs text-slate-500 mt-1">
        Verifikasi syarat penyelesaian, dokumen sertifikat, pengunduran diri, dan perpanjangan
      </p>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
      <Card class="border border-slate-200/80 shadow-2xs">
        <p class="text-2xs font-semibold uppercase tracking-wider text-slate-500">Total Peserta</p>
        <p class="mt-1.5 text-2xl font-bold text-slate-900">{{ stats.total }}</p>
      </Card>
      <Card class="border border-slate-200/80 shadow-2xs">
        <p class="text-2xs font-semibold uppercase tracking-wider text-slate-500">Syarat Lengkap</p>
        <p class="mt-1.5 text-2xl font-bold text-blue-700">{{ stats.complete }}</p>
      </Card>
      <Card class="border border-slate-200/80 shadow-2xs">
        <p class="text-2xs font-semibold uppercase tracking-wider text-emerald-600">Terverifikasi</p>
        <p class="mt-1.5 text-2xl font-bold text-emerald-700">{{ stats.verified }}</p>
      </Card>
      <Card class="border border-slate-200/80 shadow-2xs">
        <p class="text-2xs font-semibold uppercase tracking-wider text-amber-600">Belum Lengkap</p>
        <p class="mt-1.5 text-2xl font-bold text-amber-700">{{ stats.incomplete }}</p>
      </Card>
    </div>

    <Card class="border border-slate-200/80 shadow-2xs mb-5">
      <Tabs v-model="activeTab" :tabs="tabs" />
    </Card>

    <!-- Participants -->
    <Card v-if="activeTab === 'peserta'" class="border border-slate-200/80 shadow-2xs overflow-visible">
      <div class="p-4 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-2 text-xs text-slate-600">
          <span>Baris</span>
          <select v-model="perPage" class="px-2.5 py-1 bg-white border border-slate-300 rounded-lg text-xs outline-none" @change="handleFilterChange">
            <option :value="10">10</option>
            <option :value="25">25</option>
            <option :value="50">50</option>
          </select>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
          <MbkmFilterBar
            v-model="filters"
            :fields="['program_id', 'study_program_id', 'partner_id']"
            @change="handleFilterChange"
          />
          <div class="flex items-center">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Cari no. peserta / NIM / nama..."
              class="w-48 sm:w-64 px-3 py-1.5 bg-white border border-slate-300 rounded-l-lg text-xs outline-none focus:ring-1 focus:ring-brand-500"
              @keyup.enter="handleFilterChange"
            />
            <button type="button" class="px-3.5 py-1.5 bg-white hover:bg-slate-50 border border-l-0 border-slate-300 rounded-r-lg text-xs font-semibold text-rose-700" @click="handleFilterChange">
              Cari
            </button>
          </div>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
          <thead>
            <tr class="bg-slate-50/80 text-slate-600 font-semibold border-b border-slate-200 text-3xs uppercase tracking-wider">
              <th class="py-3 px-4 w-10 text-center">NO</th>
              <th class="py-3 px-4">PESERTA</th>
              <th class="py-3 px-4">PROGRAM</th>
              <th class="py-3 px-4 w-28 text-center">NILAI AKHIR</th>
              <th class="py-3 px-4 w-20 text-center">SKS</th>
              <th class="py-3 px-4 w-32 text-center">PENYELESAIAN</th>
              <th class="py-3 px-4 w-44 text-center">AKSI</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <tr v-if="loading">
              <td colspan="7" class="py-8 text-center text-slate-400">Memuat data peserta...</td>
            </tr>
            <tr v-else-if="participants.length === 0">
              <td colspan="7" class="py-8 text-center text-slate-400">Belum ada peserta MBKM.</td>
            </tr>
            <tr v-for="(p, index) in participants" :key="p.id" class="hover:bg-slate-50/80 transition-colors">
              <td class="py-3.5 px-4 text-center text-slate-500">{{ index + 1 }}</td>
              <td class="py-3.5 px-4">
                <p class="font-semibold text-slate-900">{{ p.student?.full_name }}</p>
                <p class="text-2xs text-slate-500 font-mono">{{ p.participant_number }}</p>
              </td>
              <td class="py-3.5 px-4">
                <p class="text-slate-800">{{ p.program?.name }}</p>
                <p class="text-2xs text-slate-400">
                  {{ formatDate(p.start_date) }} – {{ formatDate(p.end_date) }}
                </p>
              </td>
              <td class="py-3.5 px-4 text-center">
                <span v-if="p.final_score != null" class="font-bold text-slate-900">{{ p.final_score }}</span>
                <span v-else class="text-slate-400">—</span>
                <p v-if="p.letter_grade" class="text-2xs font-semibold text-emerald-700">{{ p.letter_grade }}</p>
              </td>
              <td class="py-3.5 px-4 text-center font-semibold text-slate-800">{{ p.recognized_credits ?? 0 }}</td>
              <td class="py-3.5 px-4 text-center">
                <MbkmStatusBadge
                  :value="p.completion?.status ?? 'pending'"
                  :labels="COMPLETION_STATUS_LABELS"
                  :variants="COMPLETION_STATUS_VARIANTS"
                />
              </td>
              <td class="py-3.5 px-4">
                <div class="flex items-center justify-center gap-1.5 flex-wrap">
                  <button
                    type="button"
                    class="px-2 py-1 rounded-lg border border-brand-200 text-brand-800 hover:bg-brand-50 text-2xs font-medium"
                    @click="openEvaluation(p)"
                  >
                    Cek Syarat
                  </button>
                  <button
                    type="button"
                    class="px-2 py-1 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 text-2xs font-medium flex items-center gap-1"
                    @click="openCertModal(p)"
                  >
                    <Upload class="w-3 h-3" /> Sertifikat
                  </button>
                  <button
                    type="button"
                    class="p-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50"
                    title="Detail Peserta"
                    @click="router.push(`/mbkm/participants/${p.id}`)"
                  >
                    <Eye class="w-3.5 h-3.5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="p-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
        <div>Total {{ totalData }} peserta</div>
        <div class="flex items-center gap-1">
          <button
            class="w-7 h-7 flex items-center justify-center rounded-lg border border-slate-200 text-slate-400 hover:bg-slate-50 disabled:opacity-40"
            :disabled="currentPage === 1"
            @click="currentPage--; loadParticipants()"
          >
            ‹
          </button>
          <span class="w-7 h-7 flex items-center justify-center rounded-lg bg-brand-900 text-white font-bold text-xs">{{ currentPage }}</span>
          <button
            class="w-7 h-7 flex items-center justify-center rounded-lg border border-slate-200 text-slate-400 hover:bg-slate-50 disabled:opacity-40"
            :disabled="participants.length < perPage"
            @click="currentPage++; loadParticipants()"
          >
            ›
          </button>
        </div>
      </div>
    </Card>

    <!-- Withdrawals -->
    <Card v-else-if="activeTab === 'pengunduran'" class="border border-slate-200/80 shadow-2xs">
      <template #header><p class="font-bold text-slate-800 text-sm">Permohonan Pengunduran Diri / Penghentian</p></template>

      <div v-if="withdrawals.length === 0" class="py-8 text-center text-xs text-slate-400">
        Tidak ada permohonan pengunduran diri.
      </div>

      <div v-else class="space-y-2.5">
        <div v-for="row in withdrawals" :key="row.id" class="p-3.5 border border-slate-200 rounded-lg">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="font-semibold text-slate-900 text-xs">
                {{ row.participant?.student?.full_name ?? '-' }}
                <span class="ml-2 text-2xs font-normal text-slate-500">
                  {{ WITHDRAWAL_TYPE_LABELS[row.type] ?? row.type }}
                </span>
              </p>
              <p class="text-2xs text-slate-500 mt-0.5">
                {{ row.participant?.program?.name }} • Diajukan {{ formatDate(row.created_at) }}
              </p>
              <p class="text-2xs text-slate-700 mt-1.5">{{ row.reason }}</p>
              <p v-if="row.decision_notes" class="text-2xs text-slate-600 mt-1">
                Keputusan: {{ row.decision_notes }}
              </p>
            </div>
            <div class="shrink-0 flex flex-col items-end gap-2">
              <span
                :class="[
                  'px-2 py-1 rounded text-2xs font-bold border',
                  row.status === 'pending'
                    ? 'bg-amber-50 text-amber-700 border-amber-200'
                    : row.status === 'approved'
                      ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                      : 'bg-rose-50 text-rose-700 border-rose-200',
                ]"
              >
                {{ row.status.toUpperCase() }}
              </span>
              <div v-if="row.status === 'pending'" class="flex items-center gap-1.5">
                <button
                  type="button"
                  class="px-2 py-1 rounded-lg border border-emerald-200 text-emerald-600 hover:bg-emerald-50 text-2xs font-medium flex items-center gap-1"
                  @click="handleDecideWithdrawal(row, 'approved')"
                >
                  <CheckCircle2 class="w-3 h-3" /> Setujui
                </button>
                <button
                  type="button"
                  class="px-2 py-1 rounded-lg border border-rose-200 text-rose-600 hover:bg-rose-50 text-2xs font-medium flex items-center gap-1"
                  @click="handleDecideWithdrawal(row, 'rejected')"
                >
                  <XCircle class="w-3 h-3" /> Tolak
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Card>

    <!-- Extensions -->
    <Card v-else-if="activeTab === 'perpanjangan'" class="border border-slate-200/80 shadow-2xs">
      <template #header><p class="font-bold text-slate-800 text-sm">Permohonan Perpanjangan</p></template>

      <div v-if="extensions.length === 0" class="py-8 text-center text-xs text-slate-400">
        Tidak ada permohonan perpanjangan.
      </div>

      <div v-else class="space-y-2.5">
        <div v-for="row in extensions" :key="row.id" class="p-3.5 border border-slate-200 rounded-lg">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="font-semibold text-slate-900 text-xs">{{ row.participant?.student?.full_name ?? '-' }}</p>
              <p class="text-2xs text-slate-500 mt-0.5">
                {{ row.participant?.program?.name }} • {{ formatDate(row.old_end_date) }} →
                <span class="font-semibold text-slate-800">{{ formatDate(row.new_end_date) }}</span>
              </p>
              <p class="text-2xs text-slate-700 mt-1.5">{{ row.reason }}</p>
            </div>
            <div class="shrink-0 flex flex-col items-end gap-2">
              <span
                :class="[
                  'px-2 py-1 rounded text-2xs font-bold border',
                  row.status === 'pending'
                    ? 'bg-amber-50 text-amber-700 border-amber-200'
                    : row.status === 'approved'
                      ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                      : 'bg-rose-50 text-rose-700 border-rose-200',
                ]"
              >
                {{ row.status.toUpperCase() }}
              </span>
              <div v-if="row.status === 'pending'" class="flex items-center gap-1.5">
                <button
                  type="button"
                  class="px-2 py-1 rounded-lg border border-emerald-200 text-emerald-600 hover:bg-emerald-50 text-2xs font-medium flex items-center gap-1"
                  @click="handleDecideExtension(row, 'approved')"
                >
                  <CheckCircle2 class="w-3 h-3" /> Setujui
                </button>
                <button
                  type="button"
                  class="px-2 py-1 rounded-lg border border-rose-200 text-rose-600 hover:bg-rose-50 text-2xs font-medium flex items-center gap-1"
                  @click="handleDecideExtension(row, 'rejected')"
                >
                  <XCircle class="w-3 h-3" /> Tolak
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Card>

    <!-- Evaluation modal -->
    <Modal v-model:open="showEvalModal" title="Verifikasi Syarat Penyelesaian" size="lg">
      <div v-if="evalLoading" class="py-8 text-center text-xs text-slate-400">Menghitung syarat penyelesaian...</div>
      <div v-else-if="evalResult" class="space-y-4 text-xs">
        <div class="p-3 bg-slate-50 rounded-lg border border-slate-200">
          <p class="font-semibold text-slate-900">{{ evalTarget?.student?.full_name }}</p>
          <p class="text-2xs text-slate-500 mt-0.5">{{ evalTarget?.program?.name }}</p>
        </div>

        <div
          class="p-3 rounded-lg border flex items-center gap-2"
          :class="evalResult.is_complete ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-amber-50 border-amber-200 text-amber-800'"
        >
          <CheckCircle2 v-if="evalResult.is_complete" class="w-4 h-4" />
          <AlertTriangle v-else class="w-4 h-4" />
          <span class="font-semibold">
            {{ evalResult.is_complete ? 'Semua syarat terpenuhi' : `${evalResult.unmet.length} syarat belum terpenuhi` }}
          </span>
        </div>

        <div class="space-y-2">
          <div
            v-for="req in evalResult.requirements"
            :key="req.code"
            class="p-2.5 rounded-lg border flex items-start gap-2"
            :class="req.satisfied ? 'bg-emerald-50/60 border-emerald-200' : 'bg-rose-50/60 border-rose-200'"
          >
            <CheckCircle2 v-if="req.satisfied" class="w-3.5 h-3.5 text-emerald-600 shrink-0 mt-0.5" />
            <AlertTriangle v-else class="w-3.5 h-3.5 text-rose-600 shrink-0 mt-0.5" />
            <div class="min-w-0">
              <p class="font-semibold text-slate-900">{{ req.label }}</p>
              <p class="text-2xs text-slate-600 mt-0.5">{{ req.detail }}</p>
            </div>
          </div>
        </div>
      </div>

      <template #footer>
        <div class="flex items-center justify-end gap-2">
          <Button variant="outline" size="sm" @click="showEvalModal = false">Tutup</Button>
          <Button
            v-if="evalResult && !evalResult.is_complete"
            variant="outline"
            size="sm"
            :loading="saving"
            @click="handleVerify(true)"
          >
            Verifikasi Paksa
          </Button>
          <Button
            v-if="evalResult"
            variant="primary"
            size="sm"
            class="bg-brand-900 text-white"
            :loading="saving"
            @click="handleVerify(false)"
          >
            <CheckCircle2 class="w-3.5 h-3.5 mr-1" /> Verifikasi
          </Button>
        </div>
      </template>
    </Modal>

    <!-- Certificate modal -->
    <Modal v-model:open="showCertModal" title="Unggah Sertifikat / Surat Keterangan" size="md">
      <div class="space-y-4 text-xs">
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Judul Dokumen</label>
          <Input v-model="certTitle" placeholder="mis. Sertifikat Magang Bersertifikat" />
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Berkas <span class="text-rose-500">*</span></label>
          <input
            type="file"
            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
            class="w-full text-xs file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-brand-900 file:text-white file:text-xs file:font-semibold"
            @change="onFileSelected"
          />
          <p class="mt-1.5 text-2xs text-slate-500">Maksimum 10 MB.</p>
        </div>
      </div>
      <template #footer>
        <div class="flex items-center justify-end gap-2">
          <Button variant="outline" size="sm" @click="showCertModal = false">Batal</Button>
          <Button variant="primary" size="sm" :loading="saving" class="bg-brand-900 text-white" @click="handleUploadCert">
            Unggah
          </Button>
        </div>
      </template>
    </Modal>
  </PageContainer>
</template>
