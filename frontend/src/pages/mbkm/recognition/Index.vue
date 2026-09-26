<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { CheckCircle2, XCircle, Lock, RotateCcw, Link2, AlertTriangle } from 'lucide-vue-next'
import { mbkmService } from '@/services/api/mbkm'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import {
  RECOGNITION_STATUS_LABELS,
  RECOGNITION_STATUS_VARIANTS,
  RECOGNITION_TYPE_LABELS,
  optionsFrom,
} from '@/types/mbkm'
import type { MbkmRecognition } from '@/types/mbkm'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Modal from '@/components/ui/Modal.vue'
import Textarea from '@/components/ui/Textarea.vue'
import MbkmStatusBadge from '@/pages/mbkm/components/MbkmStatusBadge.vue'
import MbkmFilterBar, { type MbkmFilters } from '@/pages/mbkm/components/MbkmFilterBar.vue'

const router = useRouter()
const toast = useToast()
const auth = useAuthStore()

const loading = ref(false)
const saving = ref(false)
const recognitions = ref<MbkmRecognition[]>([])
const totalData = ref(0)
const perPage = ref(10)
const currentPage = ref(1)
const searchQuery = ref('')
const filters = ref<MbkmFilters>({})

const showCorrectModal = ref(false)
const correctTarget = ref<MbkmRecognition | null>(null)
const correctReason = ref('')

const isManager = computed(
  () => auth.isSuperAdmin || auth.permissions.some((p: any) => p.name === 'mbkm.manage')
)
const canApprove = computed(
  () =>
    isManager.value ||
    auth.permissions.some((p: any) => p.name === 'mbkm.recognition.approve' || p.name === 'mbkm.recognition.manage')
)

const stats = computed(() => {
  const rows = recognitions.value
  return {
    total: totalData.value,
    draft: rows.filter((r) => r.status === 'draft').length,
    pending: rows.filter((r) => r.status === 'submitted' || r.status === 'reviewed').length,
    approved: rows.filter((r) => r.status === 'approved' || r.status === 'locked').length,
    credits: rows
      .filter((r) => r.status === 'approved' || r.status === 'locked')
      .reduce((s, r) => s + Number(r.credits || 0), 0),
    unsynced: rows.filter((r) => r.status === 'approved' && r.sync_status !== 'synced').length,
  }
})

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

    const res = await mbkmService.getRecognitions(params)
    recognitions.value = res.data || []
    totalData.value = res.meta?.total ?? recognitions.value.length
  } catch (err: any) {
    toast.error(err.message || 'Gagal memuat data rekognisi MBKM')
  } finally {
    loading.value = false
  }
}

async function handleTransition(row: MbkmRecognition, status: string) {
  try {
    await mbkmService.transitionRecognition(row.id, status)
    toast.success(
      status === 'approved'
        ? 'Rekognisi disetujui dan disinkronkan ke KRS/nilai akademik'
        : 'Status rekognisi diperbarui'
    )
    await loadData()
  } catch (err: any) {
    toast.error(err.message || 'Transisi rekognisi ditolak')
  }
}

function openCorrect(row: MbkmRecognition) {
  correctTarget.value = row
  correctReason.value = ''
  showCorrectModal.value = true
}

async function handleCorrect() {
  if (!correctTarget.value || !correctReason.value.trim()) {
    toast.error('Alasan koreksi wajib diisi')
    return
  }
  saving.value = true
  try {
    await mbkmService.correctRecognition(correctTarget.value.id, correctReason.value)
    toast.success('Rekognisi dibuka kembali untuk koreksi (riwayat tetap tersimpan)')
    showCorrectModal.value = false
    await loadData()
  } catch (err: any) {
    toast.error(err.message || 'Gagal mengoreksi rekognisi')
  } finally {
    saving.value = false
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
    <div class="mb-6">
      <h1 class="text-xl font-bold text-slate-900 tracking-tight">Rekognisi / Konversi SKS MBKM</h1>
      <p class="text-xs text-slate-500 mt-1">
        Alur Draft → Diajukan → Ditinjau → Disetujui → Terkunci. Persetujuan otomatis mendorong nilai ke KRS, KHS,
        dan transkrip melalui mekanisme akademik yang sudah ada.
      </p>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-5">
      <Card class="border border-slate-200/80 shadow-2xs">
        <p class="text-2xs font-semibold uppercase tracking-wider text-slate-500">Total</p>
        <p class="mt-1.5 text-2xl font-bold text-slate-900">{{ stats.total }}</p>
      </Card>
      <Card class="border border-slate-200/80 shadow-2xs">
        <p class="text-2xs font-semibold uppercase tracking-wider text-slate-500">Draft</p>
        <p class="mt-1.5 text-2xl font-bold text-slate-900">{{ stats.draft }}</p>
      </Card>
      <Card class="border border-slate-200/80 shadow-2xs">
        <p class="text-2xs font-semibold uppercase tracking-wider text-amber-600">Menunggu</p>
        <p class="mt-1.5 text-2xl font-bold text-amber-700">{{ stats.pending }}</p>
      </Card>
      <Card class="border border-slate-200/80 shadow-2xs">
        <p class="text-2xs font-semibold uppercase tracking-wider text-emerald-600">Disetujui</p>
        <p class="mt-1.5 text-2xl font-bold text-emerald-700">{{ stats.approved }}</p>
      </Card>
      <Card class="border border-slate-200/80 shadow-2xs">
        <p class="text-2xs font-semibold uppercase tracking-wider text-brand-800">SKS Diakui</p>
        <p class="mt-1.5 text-2xl font-bold text-brand-900">{{ stats.credits }}</p>
      </Card>
    </div>

    <div
      v-if="stats.unsynced > 0"
      class="mb-5 p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800 flex items-center gap-2"
    >
      <AlertTriangle class="w-3.5 h-3.5" />
      {{ stats.unsynced }} rekognisi disetujui namun belum tersinkron ke sistem akademik. Periksa detail masing-masing.
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
            :fields="['program_id', 'status']"
            :status-options="optionsFrom(RECOGNITION_STATUS_LABELS)"
            @change="handleFilterChange"
          />
          <div class="flex items-center">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Cari label sumber..."
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
              <th class="py-3 px-4">MATA KULIAH</th>
              <th class="py-3 px-4 w-24">TIPE</th>
              <th class="py-3 px-4 w-16 text-center">SKS</th>
              <th class="py-3 px-4 w-20 text-center">NILAI</th>
              <th class="py-3 px-4 w-28 text-center">SINKRONISASI</th>
              <th class="py-3 px-4 w-28 text-center">STATUS</th>
              <th class="py-3 px-4 w-56 text-center">AKSI</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <tr v-if="loading">
              <td colspan="9" class="py-8 text-center text-slate-400">Memuat data rekognisi...</td>
            </tr>
            <tr v-else-if="recognitions.length === 0">
              <td colspan="9" class="py-8 text-center text-slate-400">Belum ada data rekognisi MBKM.</td>
            </tr>
            <tr v-for="(row, index) in recognitions" :key="row.id" class="hover:bg-slate-50/80 transition-colors">
              <td class="py-3.5 px-4 text-center text-slate-500">{{ index + 1 }}</td>
              <td class="py-3.5 px-4">
                <p class="font-semibold text-slate-900">{{ row.participant?.student?.full_name ?? '-' }}</p>
                <p class="text-2xs text-slate-500 font-mono">{{ row.participant?.participant_number }}</p>
                <p class="text-2xs text-slate-400">{{ row.participant?.program?.name ?? '' }}</p>
              </td>
              <td class="py-3.5 px-4">
                <p class="font-semibold text-slate-900">{{ row.course?.name ?? row.source_label ?? '-' }}</p>
                <p v-if="row.course" class="text-2xs text-slate-500 font-mono">
                  {{ row.course.code }} • {{ row.course.credits }} SKS
                </p>
                <p v-if="row.curriculum" class="text-2xs text-slate-400">Kurikulum: {{ row.curriculum.name }}</p>
              </td>
              <td class="py-3.5 px-4 text-slate-700 text-2xs">
                {{ RECOGNITION_TYPE_LABELS[row.recognition_type] ?? row.recognition_type }}
              </td>
              <td class="py-3.5 px-4 text-center font-semibold text-slate-800">{{ row.credits }}</td>
              <td class="py-3.5 px-4 text-center text-slate-800">
                {{ row.score ?? '-' }}
                <span v-if="row.letter_grade" class="block text-2xs font-bold text-emerald-700">{{ row.letter_grade }}</span>
              </td>
              <td class="py-3.5 px-4 text-center">
                <span
                  :class="[
                    'px-1.5 py-0.5 rounded text-4xs font-bold border inline-flex items-center gap-1',
                    row.sync_status === 'synced'
                      ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                      : 'bg-slate-50 text-slate-500 border-slate-200',
                  ]"
                >
                  <Link2 class="w-2.5 h-2.5" />
                  {{ row.sync_status === 'synced' ? 'TERSINKRON' : (row.sync_status ?? 'PENDING').toUpperCase() }}
                </span>
                <p v-if="row.sync_message" class="text-4xs text-rose-600 mt-1">{{ row.sync_message }}</p>
              </td>
              <td class="py-3.5 px-4 text-center">
                <MbkmStatusBadge :value="row.status" :labels="RECOGNITION_STATUS_LABELS" :variants="RECOGNITION_STATUS_VARIANTS" />
              </td>
              <td class="py-3.5 px-4">
                <div class="flex items-center justify-center gap-1.5 flex-wrap">
                  <button
                    v-if="row.status === 'draft'"
                    type="button"
                    class="px-2 py-1 rounded-lg border border-blue-200 text-blue-600 hover:bg-blue-50 text-2xs font-medium"
                    @click="handleTransition(row, 'submitted')"
                  >
                    Ajukan
                  </button>
                  <button
                    v-if="canApprove && row.status === 'submitted'"
                    type="button"
                    class="px-2 py-1 rounded-lg border border-amber-200 text-amber-600 hover:bg-amber-50 text-2xs font-medium"
                    @click="handleTransition(row, 'reviewed')"
                  >
                    Tinjau
                  </button>
                  <button
                    v-if="canApprove && (row.status === 'submitted' || row.status === 'reviewed')"
                    type="button"
                    class="px-2 py-1 rounded-lg border border-emerald-200 text-emerald-600 hover:bg-emerald-50 text-2xs font-medium flex items-center gap-1"
                    @click="handleTransition(row, 'approved')"
                  >
                    <CheckCircle2 class="w-3 h-3" /> Setujui
                  </button>
                  <button
                    v-if="canApprove && (row.status === 'submitted' || row.status === 'reviewed')"
                    type="button"
                    class="px-2 py-1 rounded-lg border border-rose-200 text-rose-600 hover:bg-rose-50 text-2xs font-medium flex items-center gap-1"
                    @click="handleTransition(row, 'rejected')"
                  >
                    <XCircle class="w-3 h-3" /> Tolak
                  </button>
                  <button
                    v-if="isManager && row.status === 'approved'"
                    type="button"
                    class="px-2 py-1 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 text-2xs font-medium flex items-center gap-1"
                    @click="handleTransition(row, 'locked')"
                  >
                    <Lock class="w-3 h-3" /> Kunci
                  </button>
                  <button
                    v-if="isManager && row.locked_at"
                    type="button"
                    class="px-2 py-1 rounded-lg border border-amber-200 text-amber-600 hover:bg-amber-50 text-2xs font-medium flex items-center gap-1"
                    @click="openCorrect(row)"
                  >
                    <RotateCcw class="w-3 h-3" /> Koreksi
                  </button>
                  <button
                    type="button"
                    class="px-2 py-1 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 text-2xs font-medium"
                    @click="router.push(`/mbkm/participants/${row.participant_id}`)"
                  >
                    Peserta
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="p-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
        <div>Total {{ totalData }} rekognisi</div>
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
            :disabled="recognitions.length < perPage"
            @click="currentPage++; loadData()"
          >
            ›
          </button>
        </div>
      </div>
    </Card>

    <!-- Correct modal -->
    <Modal v-model:open="showCorrectModal" title="Koreksi Rekognisi" size="md">
      <div class="space-y-4 text-xs">
        <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-amber-800">
          <p class="font-semibold">Rekognisi terkunci tidak dapat diubah melalui form biasa.</p>
          <p class="text-2xs mt-0.5">
            Koreksi akan mengembalikan rekognisi ke status Draft dan membuka alur persetujuan ulang. Riwayat
            perubahan tetap tercatat pada audit trail.
          </p>
        </div>
        <div class="p-3 bg-slate-50 rounded-lg border border-slate-200">
          <p class="font-semibold text-slate-900">{{ correctTarget?.course?.name ?? correctTarget?.source_label }}</p>
          <p class="text-2xs text-slate-500 mt-0.5">
            {{ correctTarget?.participant?.student?.full_name }} • {{ correctTarget?.credits }} SKS
          </p>
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Alasan Koreksi <span class="text-rose-500">*</span></label>
          <Textarea v-model="correctReason" :rows="3" />
        </div>
      </div>
      <template #footer>
        <div class="flex items-center justify-end gap-2">
          <Button variant="outline" size="sm" @click="showCorrectModal = false">Batal</Button>
          <Button variant="primary" size="sm" :loading="saving" class="bg-brand-900 text-white" @click="handleCorrect">
            Buka untuk Koreksi
          </Button>
        </div>
      </template>
    </Modal>
  </PageContainer>
</template>
