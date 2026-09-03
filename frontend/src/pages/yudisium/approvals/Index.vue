<script setup lang="ts">
import { ref, onMounted } from 'vue'
import {
  Send,
  XCircle,
  FileText,
  FileSpreadsheet,
  Search,
  Filter,
  Info,
  X,
} from 'lucide-vue-next'
import { yudisiumService } from '@/services/api/yudisium'
import { useToast } from '@/composables/useToast'
import type { YudisiumParticipant, ApprovalStats } from '@/types/yudisium'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Modal from '@/components/ui/Modal.vue'

const toast = useToast()

const loading = ref<boolean>(false)
const applications = ref<YudisiumParticipant[]>([])
const selectedStatusFilter = ref<string>('')
const searchQuery = ref<string>('')
const showFilter = ref<boolean>(false)
const finalizing = ref<boolean>(false)

const stats = ref<ApprovalStats>({
  ready: 0,
  needs_revision: 0,
  submitted: 0,
  re_review: 0,
  under_review: 0,
})

// Review Modal
const showReviewModal = ref<boolean>(false)
const selectedApp = ref<YudisiumParticipant | null>(null)
const reviewStatus = ref<string>('ready')
const reviewNotes = ref<string>('')
const rejectionReason = ref<string>('')
const reviewLoading = ref<boolean>(false)

async function loadData() {
  loading.value = true
  try {
    const params: any = {}
    if (selectedStatusFilter.value) {
      params.status = selectedStatusFilter.value
    }
    if (searchQuery.value.trim()) {
      params.search = searchQuery.value.trim()
    }

    const res = await yudisiumService.getApprovals(params)
    applications.value = res.data || []
    if (res.meta && (res.meta as any).stats) {
      stats.value = (res.meta as any).stats
    }
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal memuat persetujuan yudisium')
  } finally {
    loading.value = false
  }
}

function filterByStatus(status: string) {
  selectedStatusFilter.value = selectedStatusFilter.value === status ? '' : status
  loadData()
}

async function handleFinalize() {
  if (stats.value.ready === 0) {
    toast.error('Belum ada mahasiswa berstatus Siap Ditetapkan')
    return
  }

  if (!confirm(`Tetapkan ${stats.value.ready} mahasiswa yang siap menjadi Peserta Yudisium resmi?`)) {
    return
  }

  finalizing.value = true
  try {
    const res = await yudisiumService.finalizeParticipants()
    toast.success(res.message || 'Berhasil menetapkan peserta yudisium')
    loadData()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal menetapkan peserta yudisium')
  } finally {
    finalizing.value = false
  }
}

function openReview(app: YudisiumParticipant) {
  selectedApp.value = app
  reviewStatus.value = app.status
  reviewNotes.value = app.notes || ''
  rejectionReason.value = app.rejection_reason || ''
  showReviewModal.value = true
}

async function handleSaveReview() {
  if (!selectedApp.value) return
  reviewLoading.value = true
  try {
    await yudisiumService.updateApprovalStatus(
      selectedApp.value.id,
      reviewStatus.value,
      reviewNotes.value,
      rejectionReason.value
    )
    toast.success('Status pengajuan yudisium berhasil diperbarui')
    showReviewModal.value = false
    loadData()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal memperbarui status')
  } finally {
    reviewLoading.value = false
  }
}

function getStatusBadgeClass(status: string) {
  switch (status) {
    case 'ready':
      return 'bg-emerald-50 text-emerald-700 border-emerald-200'
    case 'needs_revision':
      return 'bg-rose-50 text-rose-700 border-rose-200'
    case 'submitted':
      return 'bg-slate-100 text-slate-700 border-slate-200'
    case 're_review':
      return 'bg-amber-50 text-amber-700 border-amber-200'
    case 'under_review':
      return 'bg-cyan-50 text-cyan-700 border-cyan-200'
    case 'passed':
      return 'bg-emerald-100 text-emerald-800 border-emerald-300'
    default:
      return 'bg-slate-50 text-slate-600 border-slate-200'
  }
}

function getStatusLabel(status: string) {
  switch (status) {
    case 'ready':
      return 'Siap Ditetapkan'
    case 'needs_revision':
      return 'Perlu Perbaikan'
    case 'submitted':
      return 'Belum Diperiksa'
    case 're_review':
      return 'Periksa Ulang'
    case 'under_review':
      return 'Proses Pemeriksaan'
    case 'passed':
      return 'Peserta Resmi'
    default:
      return status
  }
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <PageContainer>
    <!-- Header (Matching Screenshot 4) -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div>
        <h1 class="text-xl font-bold text-slate-900 tracking-tight">Persetujuan Yudisium</h1>
        <p class="text-xs text-slate-500 mt-1">
          Daftar mahasiswa <strong class="text-slate-700">eligible yudisium</strong> yang mendaftar sebagai peserta yudisium
        </p>
      </div>

      <div>
        <!-- Tetapkan Peserta Yudisium Button (Screenshot 4) -->
        <Button
          variant="primary"
          size="sm"
          :loading="finalizing"
          class="bg-[#0f766e] hover:bg-[#115e59] text-white flex items-center gap-1.5 shadow-2xs font-semibold px-4 py-2"
          @click="handleFinalize"
        >
          <span>Tetapkan Peserta Yudisium</span>
          <Send class="w-3.5 h-3.5" />
        </Button>
      </div>
    </div>

    <!-- 5 Metric Cards (Matching Screenshot 4) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
      <!-- 1. Siap Ditetapkan (Teal/Cyan) -->
      <div
        class="bg-white rounded-xl border p-4 shadow-2xs flex items-center justify-between cursor-pointer transition-all hover:shadow-xs"
        :class="selectedStatusFilter === 'ready' ? 'ring-2 ring-teal-500 border-teal-400 bg-teal-50/20' : 'border-slate-200/80'"
        @click="filterByStatus('ready')"
      >
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-lg bg-teal-50 border border-teal-200/60 flex items-center justify-center text-teal-600">
            <Send class="w-5 h-5" />
          </div>
          <div>
            <div class="text-lg font-bold text-slate-900 leading-tight">
              {{ stats.ready }}
            </div>
            <div class="text-3xs font-medium text-slate-500 mt-0.5">
              Siap Ditetapkan
            </div>
          </div>
        </div>
        <Info class="w-3.5 h-3.5 text-slate-400" />
      </div>

      <!-- 2. Perlu Perbaikan (Rose/Red) -->
      <div
        class="bg-white rounded-xl border p-4 shadow-2xs flex items-center justify-between cursor-pointer transition-all hover:shadow-xs"
        :class="selectedStatusFilter === 'needs_revision' ? 'ring-2 ring-rose-500 border-rose-400 bg-rose-50/20' : 'border-slate-200/80'"
        @click="filterByStatus('needs_revision')"
      >
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-lg bg-rose-50 border border-rose-200/60 flex items-center justify-center text-rose-600">
            <XCircle class="w-5 h-5" />
          </div>
          <div>
            <div class="text-lg font-bold text-slate-900 leading-tight">
              {{ stats.needs_revision }}
            </div>
            <div class="text-3xs font-medium text-slate-500 mt-0.5">
              Perlu Perbaikan
            </div>
          </div>
        </div>
        <Info class="w-3.5 h-3.5 text-slate-400" />
      </div>

      <!-- 3. Belum Diperiksa (Slate) -->
      <div
        class="bg-white rounded-xl border p-4 shadow-2xs flex items-center justify-between cursor-pointer transition-all hover:shadow-xs"
        :class="selectedStatusFilter === 'submitted' ? 'ring-2 ring-slate-500 border-slate-400 bg-slate-50/50' : 'border-slate-200/80'"
        @click="filterByStatus('submitted')"
      >
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-lg bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-600">
            <FileText class="w-5 h-5" />
          </div>
          <div>
            <div class="text-lg font-bold text-slate-900 leading-tight">
              {{ stats.submitted }}
            </div>
            <div class="text-3xs font-medium text-slate-500 mt-0.5">
              Belum Diperiksa
            </div>
          </div>
        </div>
        <Info class="w-3.5 h-3.5 text-slate-400" />
      </div>

      <!-- 4. Periksa Ulang (Orange/Amber) -->
      <div
        class="bg-white rounded-xl border p-4 shadow-2xs flex items-center justify-between cursor-pointer transition-all hover:shadow-xs"
        :class="selectedStatusFilter === 're_review' ? 'ring-2 ring-amber-500 border-amber-400 bg-amber-50/20' : 'border-slate-200/80'"
        @click="filterByStatus('re_review')"
      >
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-lg bg-amber-50 border border-amber-200/60 flex items-center justify-center text-amber-600">
            <FileSpreadsheet class="w-5 h-5" />
          </div>
          <div>
            <div class="text-lg font-bold text-slate-900 leading-tight">
              {{ stats.re_review }}
            </div>
            <div class="text-3xs font-medium text-slate-500 mt-0.5">
              Periksa Ulang
            </div>
          </div>
        </div>
        <Info class="w-3.5 h-3.5 text-slate-400" />
      </div>

      <!-- 5. Proses Pemeriksaan (Blue/Cyan) -->
      <div
        class="bg-white rounded-xl border p-4 shadow-2xs flex items-center justify-between cursor-pointer transition-all hover:shadow-xs"
        :class="selectedStatusFilter === 'under_review' ? 'ring-2 ring-sky-500 border-sky-400 bg-sky-50/20' : 'border-slate-200/80'"
        @click="filterByStatus('under_review')"
      >
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-lg bg-sky-50 border border-sky-200/60 flex items-center justify-center text-sky-600">
            <Search class="w-5 h-5" />
          </div>
          <div>
            <div class="text-lg font-bold text-slate-900 leading-tight">
              {{ stats.under_review }}
            </div>
            <div class="text-3xs font-medium text-slate-500 mt-0.5">
              Proses Pemeriksaan
            </div>
          </div>
        </div>
        <Info class="w-3.5 h-3.5 text-slate-400" />
      </div>
    </div>

    <!-- Main Content Card -->
    <Card class="border border-slate-200/80 shadow-2xs overflow-visible">
      <!-- Toolbar -->
      <div class="p-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-end gap-3">
        <!-- Filter Button -->
        <Button
          variant="outline"
          size="sm"
          class="border-slate-300 text-slate-700 hover:bg-slate-50 flex items-center gap-1.5 font-medium"
          @click="showFilter = !showFilter"
        >
          <Filter class="w-3.5 h-3.5 text-rose-600" />
          <span>Filter</span>
        </Button>

        <!-- Search Input -->
        <div class="relative">
          <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cari..."
            class="w-48 sm:w-64 pl-8 pr-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs outline-none focus:ring-1 focus:ring-brand-500 focus:border-brand-600"
            @keyup.enter="loadData"
          />
        </div>
      </div>

      <!-- If Applications Exist: Table -->
      <div v-if="applications.length > 0" class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
          <thead>
            <tr class="bg-slate-50/80 text-slate-600 font-semibold border-b border-slate-200 text-3xs uppercase tracking-wider">
              <th class="py-3 px-4">MAHASISWA</th>
              <th class="py-3 px-4">PROGRAM STUDI</th>
              <th class="py-3 px-4">SKS / IPK</th>
              <th class="py-3 px-4">TUGAS AKHIR</th>
              <th class="py-3 px-4">STATUS</th>
              <th class="py-3 px-4 text-center w-24">AKSI</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <tr
              v-for="app in applications"
              :key="app.id"
              class="hover:bg-slate-50/80 transition-colors"
            >
              <td class="py-3.5 px-4">
                <div class="font-bold text-slate-900">{{ app.student?.full_name }}</div>
                <div class="text-3xs text-slate-500 font-mono mt-0.5">{{ app.student?.student_number }}</div>
              </td>
              <td class="py-3.5 px-4 text-slate-800">
                {{ app.student?.study_program?.degree ? `${app.student.study_program.degree}-${app.student.study_program.name}` : (app.student?.study_program?.name || '-') }}
              </td>
              <td class="py-3.5 px-4 font-semibold text-slate-900">
                {{ app.total_credits }} SKS / IPK {{ app.gpa }}
              </td>
              <td class="py-3.5 px-4 text-slate-700">
                {{ app.thesis ? 'Lulus Sidang' : 'Belum Ada TA' }}
              </td>
              <td class="py-3.5 px-4">
                <span
                  class="px-2.5 py-1 rounded-full text-3xs font-bold border"
                  :class="getStatusBadgeClass(app.status)"
                >
                  {{ getStatusLabel(app.status) }}
                </span>
              </td>
              <td class="py-3.5 px-4 text-center">
                <Button
                  variant="outline"
                  size="sm"
                  class="border-rose-300 text-rose-700 hover:bg-rose-50 text-xs px-2.5 py-1 font-medium"
                  @click="openReview(app)"
                >
                  Periksa
                </Button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Empty State (Matching Screenshot 4) -->
      <div v-else class="py-16 px-4 text-center flex flex-col items-center justify-center">
        <!-- Yellow Folder Illustration with Red Cross -->
        <div class="relative w-28 h-28 mb-4 flex items-center justify-center">
          <div class="w-24 h-20 bg-amber-400 rounded-xl shadow-md flex items-center justify-center transform -rotate-3">
            <div class="w-16 h-12 bg-white/90 rounded-lg shadow-inner flex flex-col p-1.5 gap-1">
              <div class="w-full h-1.5 bg-blue-300 rounded" />
              <div class="w-3/4 h-1.5 bg-slate-300 rounded" />
              <div class="w-1/2 h-1.5 bg-slate-200 rounded" />
            </div>
          </div>
          <div class="absolute -bottom-1 -right-1 w-9 h-9 rounded-full bg-rose-600 border-2 border-white flex items-center justify-center shadow-lg">
            <X class="w-5 h-5 text-white stroke-[3]" />
          </div>
        </div>

        <h3 class="text-sm font-bold text-slate-800">
          Belum ada data pengajuan yudisium
        </h3>
        <p class="text-xs text-slate-400 mt-1 max-w-sm">
          Mahasiswa yang mengajukan yudisium akan muncul di sini.
        </p>
      </div>
    </Card>

    <!-- Modal Periksa Pengajuan -->
    <Modal
      v-model:open="showReviewModal"
      title="Verifikasi Pengajuan Yudisium"
      size="md"
    >
      <div v-if="selectedApp" class="space-y-4 text-xs">
        <div class="p-3 bg-slate-50 rounded-lg border border-slate-200">
          <div class="font-bold text-slate-900">{{ selectedApp.student?.full_name }}</div>
          <div class="text-slate-500 text-3xs font-mono">{{ selectedApp.student?.student_number }} - {{ selectedApp.student?.study_program?.name }}</div>
        </div>

        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Status Verifikasi <span class="text-rose-500">*</span></label>
          <select
            v-model="reviewStatus"
            class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none focus:ring-2 focus:ring-brand-500"
          >
            <option value="submitted">Belum Diperiksa</option>
            <option value="under_review">Proses Pemeriksaan</option>
            <option value="needs_revision">Perlu Perbaikan</option>
            <option value="re_review">Periksa Ulang</option>
            <option value="ready">Siap Ditetapkan</option>
            <option value="rejected">Tolak Pengajuan</option>
          </select>
        </div>

        <div v-if="reviewStatus === 'needs_revision' || reviewStatus === 'rejected'">
          <label class="block font-semibold text-slate-700 mb-1.5">Alasan Perbaikan / Penolakan</label>
          <textarea
            v-model="rejectionReason"
            rows="3"
            class="w-full p-2.5 border border-slate-300 rounded-lg text-xs outline-none focus:ring-2 focus:ring-brand-500"
            placeholder="Tuliskan alasan atau bagian yang perlu diperbaiki..."
          />
        </div>

        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Catatan Verifikator</label>
          <textarea
            v-model="reviewNotes"
            rows="2"
            class="w-full p-2.5 border border-slate-300 rounded-lg text-xs outline-none focus:ring-2 focus:ring-brand-500"
            placeholder="Catatan internal..."
          />
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end gap-2">
          <Button variant="outline" size="sm" @click="showReviewModal = false">
            Batal
          </Button>
          <Button
            variant="primary"
            size="sm"
            :loading="reviewLoading"
            class="bg-brand-900 text-white"
            @click="handleSaveReview"
          >
            Simpan Status
          </Button>
        </div>
      </template>
    </Modal>
  </PageContainer>
</template>
