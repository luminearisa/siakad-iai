<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { Eye, CheckCircle2, Gavel, UserPlus, Star } from 'lucide-vue-next'
import { mbkmService } from '@/services/api/mbkm'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import {
  APPLICATION_STATUS_LABELS,
  APPLICATION_STATUS_VARIANTS,
  optionsFrom,
} from '@/types/mbkm'
import type { MbkmApplication } from '@/types/mbkm'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Modal from '@/components/ui/Modal.vue'
import Textarea from '@/components/ui/Textarea.vue'
import Input from '@/components/ui/Input.vue'
import MbkmStatusBadge from '@/pages/mbkm/components/MbkmStatusBadge.vue'
import MbkmFilterBar, { type MbkmFilters } from '@/pages/mbkm/components/MbkmFilterBar.vue'

const route = useRoute()
const toast = useToast()
const auth = useAuthStore()

const loading = ref(false)
const saving = ref(false)
const applications = ref<MbkmApplication[]>([])
const totalData = ref(0)
const perPage = ref(10)
const currentPage = ref(1)
const searchQuery = ref('')
const filters = ref<MbkmFilters>({})

const showDetail = ref(false)
const selected = ref<MbkmApplication | null>(null)
const selection = ref<any>(null)
const detailLoading = ref(false)

const showVerifyModal = ref(false)
const showDecideModal = ref(false)
const showScoreModal = ref(false)
const actionTarget = ref<MbkmApplication | null>(null)
const decisionValue = ref('')
const decisionNotes = ref('')
const scoreCriteriaId = ref<number | null>(null)
const scoreValue = ref<number | null>(null)
const scoreNotes = ref('')

const canVerify = computed(
  () => auth.isSuperAdmin || auth.permissions.some((p: any) => p.name === 'mbkm.applications.verify' || p.name === 'mbkm.manage')
)
const canDecide = computed(
  () => auth.isSuperAdmin || auth.permissions.some((p: any) => p.name === 'mbkm.applications.decide' || p.name === 'mbkm.manage')
)
const canAssign = computed(
  () => auth.isSuperAdmin || auth.permissions.some((p: any) => p.name === 'mbkm.participants.manage' || p.name === 'mbkm.manage')
)

async function loadData() {
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
    if (route.query.program_id) params.program_id = route.query.program_id

    const res = await mbkmService.getApplications(params)
    applications.value = res.data || []
    totalData.value = res.meta?.total ?? applications.value.length
  } catch (err: any) {
    toast.error(err.message || 'Gagal memuat daftar pendaftar MBKM')
  } finally {
    loading.value = false
  }
}

async function openDetail(app: MbkmApplication) {
  showDetail.value = true
  detailLoading.value = true
  selected.value = app
  selection.value = null
  try {
    const res = await mbkmService.getApplication(app.id)
    selected.value = res.data.application
    selection.value = res.data.selection
  } catch (err: any) {
    toast.error(err.message || 'Gagal memuat detail pendaftaran')
  } finally {
    detailLoading.value = false
  }
}

function openVerify(app: MbkmApplication) {
  actionTarget.value = app
  decisionValue.value = 'verified'
  decisionNotes.value = ''
  showVerifyModal.value = true
}

function openDecide(app: MbkmApplication) {
  actionTarget.value = app
  decisionValue.value = 'selected'
  decisionNotes.value = ''
  showDecideModal.value = true
}

const scoreCriteria = ref<Array<{ id: number; name: string; weight: number; max_score: number }>>([])

async function openScore(app: MbkmApplication) {
  actionTarget.value = app
  scoreCriteria.value = []
  scoreCriteriaId.value = null
  scoreValue.value = null
  scoreNotes.value = ''
  showScoreModal.value = true
  try {
    // The index endpoint does not eager-load criteria; fetch them on demand.
    const res = await mbkmService.getProgram(app.program_id)
    scoreCriteria.value = (res.data.selection_criteria ?? []) as any
    scoreCriteriaId.value = scoreCriteria.value[0]?.id ?? null
  } catch {
    toast.warning('Kriteria seleksi program gagal dimuat')
  }
}

async function handleVerify() {
  if (!actionTarget.value) return
  saving.value = true
  try {
    await mbkmService.verifyApplication(actionTarget.value.id, decisionValue.value, decisionNotes.value || undefined)
    toast.success('Verifikasi pendaftaran berhasil disimpan')
    showVerifyModal.value = false
    await loadData()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menyimpan verifikasi')
  } finally {
    saving.value = false
  }
}

async function handleDecide() {
  if (!actionTarget.value) return
  saving.value = true
  try {
    await mbkmService.decideApplication(actionTarget.value.id, decisionValue.value, decisionNotes.value || undefined)
    toast.success('Keputusan seleksi berhasil disimpan')
    showDecideModal.value = false
    await loadData()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menyimpan keputusan seleksi')
  } finally {
    saving.value = false
  }
}

async function handleScore() {
  if (!actionTarget.value || !scoreCriteriaId.value || scoreValue.value === null) {
    toast.error('Kriteria dan nilai wajib diisi')
    return
  }
  saving.value = true
  try {
    await mbkmService.scoreApplication(actionTarget.value.id, {
      criteria_id: scoreCriteriaId.value,
      score: Number(scoreValue.value),
      notes: scoreNotes.value || undefined,
    })
    toast.success('Nilai kriteria berhasil disimpan')
    showScoreModal.value = false
    await loadData()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menyimpan nilai')
  } finally {
    saving.value = false
  }
}

async function handleAssign(app: MbkmApplication) {
  if (!confirm(`Tetapkan ${app.student?.full_name} sebagai peserta program ini? Kuota program akan diperiksa.`)) return
  try {
    await mbkmService.assignParticipant(app.id)
    toast.success('Peserta MBKM berhasil ditetapkan')
    await loadData()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menetapkan peserta (kemungkinan kuota penuh)')
  }
}

function formatDateTime(value?: string | null) {
  if (!value) return '-'
  try {
    return new Date(value).toLocaleString('id-ID', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch {
    return value
  }
}

function handleFilterChange() {
  currentPage.value = 1
  loadData()
}

onMounted(loadData)
</script>

<template>
  <PageContainer>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div>
        <h1 class="text-xl font-bold text-slate-900 tracking-tight">Pendaftar MBKM</h1>
        <p class="text-xs text-slate-500 mt-1">
          Verifikasi kelayakan, penilaian kriteria seleksi, dan penetapan peserta
        </p>
      </div>
    </div>

    <Card class="border border-slate-200/80 shadow-2xs overflow-visible">
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
            :fields="['program_id', 'study_program_id', 'status']"
            :status-options="optionsFrom(APPLICATION_STATUS_LABELS)"
            @change="handleFilterChange"
          />
          <div class="flex items-center">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Cari no. pendaftaran / NIM / nama..."
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
              <th class="py-3 px-4">NO. PENDAFTARAN</th>
              <th class="py-3 px-4">MAHASISWA</th>
              <th class="py-3 px-4">PROGRAM</th>
              <th class="py-3 px-4 text-center">DOKUMEN</th>
              <th class="py-3 px-4 text-center">NILAI SELEKSI</th>
              <th class="py-3 px-4 text-center">STATUS</th>
              <th class="py-3 px-4 text-center w-40">AKSI</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <tr v-if="loading">
              <td colspan="8" class="py-8 text-center text-slate-400">Memuat data pendaftar...</td>
            </tr>
            <tr v-else-if="applications.length === 0">
              <td colspan="8" class="py-8 text-center text-slate-400">Belum ada pendaftar MBKM.</td>
            </tr>
            <tr v-for="(item, index) in applications" :key="item.id" class="hover:bg-slate-50/80 transition-colors">
              <td class="py-3.5 px-4 text-center text-slate-500 font-medium">{{ index + 1 }}</td>
              <td class="py-3.5 px-4">
                <p class="font-mono font-semibold text-slate-900">{{ item.registration_number }}</p>
                <p class="text-2xs text-slate-400 mt-0.5">{{ formatDateTime(item.submitted_at) }}</p>
              </td>
              <td class="py-3.5 px-4">
                <p class="font-semibold text-slate-900">{{ item.student?.full_name ?? '-' }}</p>
                <p class="text-2xs text-slate-500">
                  {{ item.student?.student_number }} • {{ item.student?.study_program?.name ?? '-' }}
                </p>
              </td>
              <td class="py-3.5 px-4">
                <p class="text-slate-800">{{ item.program?.name ?? '-' }}</p>
                <p class="text-2xs text-slate-400">{{ item.program?.program_type?.name ?? '' }}</p>
              </td>
              <td class="py-3.5 px-4 text-center text-slate-700">
                {{ (item as any).documents_count ?? item.documents?.length ?? 0 }}
              </td>
              <td class="py-3.5 px-4 text-center font-semibold text-slate-900">
                {{ item.selection_score ?? '-' }}
              </td>
              <td class="py-3.5 px-4 text-center">
                <MbkmStatusBadge
                  :value="item.status"
                  :labels="APPLICATION_STATUS_LABELS"
                  :variants="APPLICATION_STATUS_VARIANTS"
                />
              </td>
              <td class="py-3.5 px-4">
                <div class="flex items-center justify-center gap-1.5 flex-wrap">
                  <button
                    type="button"
                    class="p-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50"
                    title="Detail"
                    @click="openDetail(item)"
                  >
                    <Eye class="w-3.5 h-3.5" />
                  </button>
                  <button
                    v-if="canVerify && (item.status === 'submitted' || item.status === 'draft')"
                    type="button"
                    class="p-1.5 rounded-lg border border-blue-200 text-blue-600 hover:bg-blue-50"
                    title="Verifikasi"
                    @click="openVerify(item)"
                  >
                    <CheckCircle2 class="w-3.5 h-3.5" />
                  </button>
                  <button
                    v-if="canDecide && item.status === 'verified'"
                    type="button"
                    class="p-1.5 rounded-lg border border-amber-200 text-amber-600 hover:bg-amber-50"
                    title="Beri Nilai Kriteria"
                    @click="openScore(item)"
                  >
                    <Star class="w-3.5 h-3.5" />
                  </button>
                  <button
                    v-if="canDecide && item.status === 'verified'"
                    type="button"
                    class="p-1.5 rounded-lg border border-emerald-200 text-emerald-600 hover:bg-emerald-50"
                    title="Keputusan Seleksi"
                    @click="openDecide(item)"
                  >
                    <Gavel class="w-3.5 h-3.5" />
                  </button>
                  <button
                    v-if="canAssign && item.status === 'selected' && !item.participant"
                    type="button"
                    class="p-1.5 rounded-lg border border-brand-200 text-brand-800 hover:bg-brand-50"
                    title="Tetapkan sebagai Peserta"
                    @click="handleAssign(item)"
                  >
                    <UserPlus class="w-3.5 h-3.5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="p-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
        <div>Total {{ totalData }} pendaftar</div>
        <div class="flex items-center gap-1">
          <button
            class="w-7 h-7 flex items-center justify-center rounded-lg border border-slate-200 text-slate-400 hover:bg-slate-50 disabled:opacity-40"
            :disabled="currentPage === 1"
            @click="currentPage--; loadData()"
          >
            ‹
          </button>
          <span class="w-7 h-7 flex items-center justify-center rounded-lg bg-brand-900 text-white font-bold text-xs">{{ currentPage }}</span>
          <button
            class="w-7 h-7 flex items-center justify-center rounded-lg border border-slate-200 text-slate-400 hover:bg-slate-50 disabled:opacity-40"
            :disabled="applications.length < perPage"
            @click="currentPage++; loadData()"
          >
            ›
          </button>
        </div>
      </div>
    </Card>

    <!-- Detail -->
    <Modal v-model:open="showDetail" title="Detail Pendaftaran MBKM" size="xl">
      <div v-if="detailLoading" class="py-8 text-center text-xs text-slate-400">Memuat detail...</div>
      <div v-else-if="selected" class="space-y-5 text-xs">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="p-3 bg-slate-50 rounded-lg border border-slate-200">
            <p class="text-2xs text-slate-500">Nomor Pendaftaran</p>
            <p class="font-mono font-bold text-slate-900 mt-0.5">{{ selected.registration_number }}</p>
          </div>
          <div class="p-3 bg-slate-50 rounded-lg border border-slate-200">
            <p class="text-2xs text-slate-500">Status</p>
            <div class="mt-1">
              <MbkmStatusBadge :value="selected.status" :labels="APPLICATION_STATUS_LABELS" :variants="APPLICATION_STATUS_VARIANTS" />
            </div>
          </div>
          <div class="p-3 bg-slate-50 rounded-lg border border-slate-200">
            <p class="text-2xs text-slate-500">Mahasiswa</p>
            <p class="font-semibold text-slate-900 mt-0.5">{{ selected.student?.full_name }}</p>
            <p class="text-2xs text-slate-500">{{ selected.student?.student_number }} • {{ selected.student?.study_program?.name }}</p>
          </div>
          <div class="p-3 bg-slate-50 rounded-lg border border-slate-200">
            <p class="text-2xs text-slate-500">Program</p>
            <p class="font-semibold text-slate-900 mt-0.5">{{ selected.program?.name }}</p>
            <p class="text-2xs text-slate-500">{{ selected.program?.program_type?.name }}</p>
          </div>
        </div>

        <div v-if="selected.academic_snapshot" class="p-3.5 border border-slate-200 rounded-lg">
          <p class="font-bold text-slate-800 mb-2">Snapshot Akademik saat Pengajuan</p>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div>
              <p class="text-2xs text-slate-500">IPK Kumulatif</p>
              <p class="font-semibold text-slate-900">{{ (selected.academic_snapshot as any).cumulative_gpa ?? '-' }}</p>
            </div>
            <div>
              <p class="text-2xs text-slate-500">SKS Lulus</p>
              <p class="font-semibold text-slate-900">{{ (selected.academic_snapshot as any).total_credits_passed ?? '-' }}</p>
            </div>
            <div>
              <p class="text-2xs text-slate-500">Semester</p>
              <p class="font-semibold text-slate-900">{{ (selected.academic_snapshot as any).current_semester ?? '-' }}</p>
            </div>
            <div>
              <p class="text-2xs text-slate-500">IPK Semester Lalu</p>
              <p class="font-semibold text-slate-900">{{ (selected.academic_snapshot as any).last_semester_gpa ?? '-' }}</p>
            </div>
          </div>
        </div>

        <div v-if="selection" class="p-3.5 border border-slate-200 rounded-lg">
          <p class="font-bold text-slate-800 mb-2">Penilaian Seleksi</p>
          <p class="text-2xs text-slate-500 mb-2.5">
            Nilai akhir seleksi: <span class="font-bold text-slate-900">{{ selection.score ?? '-' }}</span>
            <span class="ml-2" :class="selection.is_complete ? 'text-emerald-600' : 'text-amber-600'">
              {{ selection.is_complete ? '(lengkap)' : '(belum semua kriteria dinilai)' }}
            </span>
          </p>
          <div v-if="!(selection.breakdown ?? []).length" class="py-2 text-center text-2xs text-slate-400">
            Belum ada kriteria seleksi untuk program ini.
          </div>
          <div v-else class="space-y-1.5">
            <div
              v-for="c in selection.breakdown"
              :key="c.criteria_id"
              class="flex items-center justify-between gap-3 py-1 border-b border-slate-100 last:border-0"
            >
              <span class="text-slate-700">
                {{ c.criteria_name }} <span class="text-slate-400">(bobot {{ c.weight }}%)</span>
              </span>
              <span class="font-medium" :class="c.reviewers > 0 ? 'text-slate-900' : 'text-slate-400'">
                {{ c.reviewers > 0 ? c.average_score : 'belum dinilai' }}
              </span>
            </div>
          </div>
        </div>

        <div>
          <p class="font-bold text-slate-800 mb-1.5">Pernyataan Motivasi</p>
          <p class="text-slate-700 whitespace-pre-line p-3 bg-slate-50 rounded-lg border border-slate-200">
            {{ selected.motivation_statement || '—' }}
          </p>
        </div>

        <div v-if="selected.verification_notes || selected.decision_notes">
          <p class="font-bold text-slate-800 mb-1.5">Catatan</p>
          <div class="space-y-1.5">
            <p v-if="selected.verification_notes" class="text-slate-700">
              <span class="font-semibold">Verifikasi:</span> {{ selected.verification_notes }}
            </p>
            <p v-if="selected.decision_notes" class="text-slate-700">
              <span class="font-semibold">Keputusan:</span> {{ selected.decision_notes }}
            </p>
          </div>
        </div>

        <div v-if="(selected.documents ?? []).length > 0">
          <p class="font-bold text-slate-800 mb-1.5">Dokumen</p>
          <ul class="space-y-1.5">
            <li v-for="doc in selected.documents" :key="doc.id" class="flex items-center justify-between gap-3 py-1.5 px-3 bg-slate-50 rounded-lg border border-slate-200">
              <span class="text-slate-700 truncate">{{ doc.title || doc.original_name }}</span>
              <span class="text-2xs text-slate-500 shrink-0">{{ doc.category }}</span>
            </li>
          </ul>
        </div>
      </div>

      <template #footer>
        <div class="flex items-center justify-end gap-2">
          <Button variant="outline" size="sm" @click="showDetail = false">Tutup</Button>
          <Button
            v-if="selected && canVerify && (selected.status === 'submitted' || selected.status === 'draft')"
            variant="primary"
            size="sm"
            class="bg-brand-900 text-white"
            @click="showDetail = false; openVerify(selected)"
          >
            Verifikasi
          </Button>
          <Button
            v-if="selected && canDecide && selected.status === 'verified'"
            variant="primary"
            size="sm"
            class="bg-brand-900 text-white"
            @click="showDetail = false; openDecide(selected)"
          >
            Keputusan Seleksi
          </Button>
        </div>
      </template>
    </Modal>

    <!-- Verify -->
    <Modal v-model:open="showVerifyModal" title="Verifikasi Pendaftaran" size="md">
      <div class="space-y-4 text-xs">
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Keputusan</label>
          <select v-model="decisionValue" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none">
            <option value="verified">Terverifikasi — lanjut ke seleksi</option>
            <option value="revision_required">Perlu Revisi</option>
            <option value="rejected">Ditolak</option>
          </select>
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Catatan</label>
          <Textarea v-model="decisionNotes" :rows="3" placeholder="Catatan verifikasi (opsional)" />
        </div>
      </div>
      <template #footer>
        <div class="flex items-center justify-end gap-2">
          <Button variant="outline" size="sm" @click="showVerifyModal = false">Batal</Button>
          <Button variant="primary" size="sm" :loading="saving" class="bg-brand-900 text-white" @click="handleVerify">
            Simpan Verifikasi
          </Button>
        </div>
      </template>
    </Modal>

    <!-- Decide -->
    <Modal v-model:open="showDecideModal" title="Keputusan Seleksi" size="md">
      <div class="space-y-4 text-xs">
        <div class="p-3 bg-slate-50 rounded-lg border border-slate-200">
          <p class="font-semibold text-slate-900">{{ actionTarget?.student?.full_name }}</p>
          <p class="text-2xs text-slate-500 mt-0.5">
            {{ actionTarget?.program?.name }} • Nilai seleksi: {{ actionTarget?.selection_score ?? 'belum dinilai' }}
          </p>
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Keputusan</label>
          <select v-model="decisionValue" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none">
            <option value="selected">Diterima sebagai Peserta</option>
            <option value="not_selected">Tidak Diterima</option>
            <option value="rejected">Ditolak</option>
          </select>
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Catatan</label>
          <Textarea v-model="decisionNotes" :rows="3" />
        </div>
      </div>
      <template #footer>
        <div class="flex items-center justify-end gap-2">
          <Button variant="outline" size="sm" @click="showDecideModal = false">Batal</Button>
          <Button variant="primary" size="sm" :loading="saving" class="bg-brand-900 text-white" @click="handleDecide">
            Simpan Keputusan
          </Button>
        </div>
      </template>
    </Modal>

    <!-- Score -->
    <Modal v-model:open="showScoreModal" title="Nilai Kriteria Seleksi" size="md">
      <div class="space-y-4 text-xs">
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Kriteria</label>
          <select v-model="scoreCriteriaId" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none">
            <option :value="null">Pilih kriteria</option>
            <option v-for="c in scoreCriteria" :key="c.id" :value="c.id">
              {{ c.name }} (bobot {{ c.weight }}%, maks {{ c.max_score }})
            </option>
          </select>
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Nilai</label>
          <Input v-model.number="scoreValue" type="number" min="0" step="0.01" />
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Catatan</label>
          <Textarea v-model="scoreNotes" :rows="2" />
        </div>
      </div>
      <template #footer>
        <div class="flex items-center justify-end gap-2">
          <Button variant="outline" size="sm" @click="showScoreModal = false">Batal</Button>
          <Button variant="primary" size="sm" :loading="saving" class="bg-brand-900 text-white" @click="handleScore">
            Simpan Nilai
          </Button>
        </div>
      </template>
    </Modal>
  </PageContainer>
</template>
