<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import {
  Plus,
  Filter,
  Eye,
  XCircle,
} from 'lucide-vue-next'
import { yudisiumService } from '@/services/api/yudisium'
import { academicService } from '@/services/api/academic'
import { useToast } from '@/composables/useToast'
import type { EligibleStudentAudit, YudisiumPeriod } from '@/types/yudisium'
import type { StudyProgram } from '@/types/academic'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Modal from '@/components/ui/Modal.vue'

const toast = useToast()

const loading = ref<boolean>(false)
const auditedStudents = ref<EligibleStudentAudit[]>([])
const periods = ref<YudisiumPeriod[]>([])
const studyPrograms = ref<StudyProgram[]>([])
const totalData = ref<number>(0)
const perPage = ref<number>(10)
const currentPage = ref<number>(1)
const searchQuery = ref<string>('')
const showFilter = ref<boolean>(false)
const filterProdiId = ref<number | null>(null)
const filterEligible = ref<string>('')

// Selection state for bulk registration
const selectedStudentIds = ref<number[]>([])

// Register Modal
const showRegisterModal = ref<boolean>(false)
const registerPeriodId = ref<number | null>(null)
const registerLoading = ref<boolean>(false)

// Detail Modal
const showDetailModal = ref<boolean>(false)
const selectedAudit = ref<EligibleStudentAudit | null>(null)

const isAllSelected = computed(() => {
  if (auditedStudents.value.length === 0) return false
  return auditedStudents.value.every((item) => selectedStudentIds.value.includes(item.student_id))
})

function toggleSelectAll() {
  if (isAllSelected.value) {
    selectedStudentIds.value = []
  } else {
    selectedStudentIds.value = auditedStudents.value.map((item) => item.student_id)
  }
}

function toggleSelectStudent(id: number) {
  const idx = selectedStudentIds.value.indexOf(id)
  if (idx > -1) {
    selectedStudentIds.value.splice(idx, 1)
  } else {
    selectedStudentIds.value.push(id)
  }
}

async function loadData() {
  loading.value = true
  try {
    const params: any = {
      page: currentPage.value,
      per_page: perPage.value,
    }
    if (filterProdiId.value) {
      params.study_program_id = filterProdiId.value
    }
    if (filterEligible.value !== '') {
      params.is_eligible = filterEligible.value
    }
    if (searchQuery.value.trim()) {
      params.search = searchQuery.value.trim()
    }

    const res = await yudisiumService.getEligibleStudents(params)
    auditedStudents.value = res.data || []
    if (res.meta) {
      totalData.value = res.meta.total || auditedStudents.value.length
    } else {
      totalData.value = auditedStudents.value.length
    }
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal memuat kelayakan yudisium mahasiswa')
  } finally {
    loading.value = false
  }
}

async function loadDependencies() {
  try {
    const [pRes, spRes] = await Promise.all([
      yudisiumService.getPeriods({ is_active: true }),
      academicService.getStudyPrograms(),
    ])
    periods.value = pRes.data || []
    studyPrograms.value = spRes.data || []
    if (periods.value.length > 0) {
      registerPeriodId.value = periods.value[0].id
    }
  } catch (err) {
    console.error(err)
  }
}

function handleSearch() {
  currentPage.value = 1
  loadData()
}

function openRegisterModal(singleStudentId?: number) {
  if (singleStudentId) {
    selectedStudentIds.value = [singleStudentId]
  }

  if (selectedStudentIds.value.length === 0) {
    toast.error('Pilih setidaknya satu mahasiswa untuk didaftarkan')
    return
  }

  if (periods.value.length === 0) {
    toast.error('Belum ada periode yudisium aktif')
    return
  }

  registerPeriodId.value = periods.value[0].id
  showRegisterModal.value = true
}

async function handleConfirmRegister() {
  if (!registerPeriodId.value) {
    toast.error('Pilih periode yudisium')
    return
  }

  registerLoading.value = true
  try {
    const res = await yudisiumService.registerEligibleStudents(
      registerPeriodId.value,
      selectedStudentIds.value
    )
    toast.success(res.data.message)
    showRegisterModal.value = false
    selectedStudentIds.value = []
    loadData()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal mendaftarkan mahasiswa ke yudisium')
  } finally {
    registerLoading.value = false
  }
}

function openDetail(audit: EligibleStudentAudit) {
  selectedAudit.value = audit
  showDetailModal.value = true
}

onMounted(() => {
  loadData()
  loadDependencies()
})
</script>

<template>
  <PageContainer>
    <!-- Header (Matching Screenshot 5) -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div>
        <h1 class="text-xl font-bold text-slate-900 tracking-tight">Eligible Yudisium</h1>
        <p class="text-xs text-slate-500 mt-1">Daftar mahasiswa yang eligible untuk mengikuti yudisium</p>
      </div>

      <div>
        <!-- Button Daftarkan Yudisium (Screenshot 5: Muted/Disabled unless selected) -->
        <Button
          variant="primary"
          size="sm"
          :disabled="selectedStudentIds.length === 0"
          class="bg-rose-700/80 hover:bg-rose-800 text-white flex items-center gap-1.5 shadow-2xs font-semibold px-4 py-2 disabled:opacity-50 disabled:bg-rose-400 disabled:cursor-not-allowed"
          @click="openRegisterModal()"
        >
          <Plus class="w-4 h-4" />
          <span>Daftarkan Yudisium {{ selectedStudentIds.length > 0 ? `(${selectedStudentIds.length})` : '' }}</span>
        </Button>
      </div>
    </div>

    <!-- Main Table Card -->
    <Card class="border border-slate-200/80 shadow-2xs overflow-visible">
      <!-- Toolbar (Matching Screenshot 5: Filter & Cari data... [Cari]) -->
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

        <!-- Search Input & Button -->
        <div class="flex items-center">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cari data..."
            class="w-48 sm:w-64 px-3 py-1.5 bg-white border border-slate-300 rounded-l-lg text-xs outline-none focus:ring-1 focus:ring-brand-500 focus:border-brand-600"
            @keyup.enter="handleSearch"
          />
          <button
            type="button"
            class="px-3.5 py-1.5 bg-white hover:bg-slate-50 border border-l-0 border-slate-300 rounded-r-lg text-xs font-semibold text-rose-700 transition-colors"
            @click="handleSearch"
          >
            Cari
          </button>
        </div>
      </div>

      <!-- Collapsible Filter Panel -->
      <div v-if="showFilter" class="p-4 bg-slate-50 border-b border-slate-200 grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs animate-fade-in">
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Program Studi</label>
          <select
            v-model="filterProdiId"
            class="w-full px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs outline-none"
            @change="loadData"
          >
            <option :value="null">Semua Program Studi</option>
            <option v-for="sp in studyPrograms" :key="sp.id" :value="sp.id">
              {{ sp.name }}
            </option>
          </select>
        </div>

        <div>
          <label class="block font-semibold text-slate-700 mb-1">Status Kelayakan</label>
          <select
            v-model="filterEligible"
            class="w-full px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs outline-none"
            @change="loadData"
          >
            <option value="">Semua Status</option>
            <option value="true">Eligible</option>
            <option value="false">Tidak Eligible</option>
          </select>
        </div>
      </div>

      <!-- Table (Matching Screenshot 5) -->
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
          <thead>
            <tr class="bg-slate-50/80 text-slate-600 font-semibold border-b border-slate-200 text-3xs uppercase tracking-wider">
              <th class="py-3 px-3 w-10 text-center">
                <input
                  type="checkbox"
                  :checked="isAllSelected"
                  class="w-4 h-4 rounded text-rose-600 border-slate-300 focus:ring-rose-500 cursor-pointer"
                  @change="toggleSelectAll"
                />
              </th>
              <th class="py-3 px-4">NAMA MAHASISWA</th>
              <th class="py-3 px-4">PROGRAM STUDI</th>
              <th class="py-3 px-4">MASA STUDI</th>
              <th class="py-3 px-4 text-center">SKS LULUS</th>
              <th class="py-3 px-4 text-center">IPK LULUS</th>
              <th class="py-3 px-4">TUGAS AKHIR</th>
              <th class="py-3 px-4 text-center">STATUS ELIGIBLE</th>
              <th class="py-3 px-4">KETERANGAN</th>
              <th class="py-3 px-4 text-center w-24">AKSI</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <!-- Loading Row -->
            <tr v-if="loading">
              <td colspan="10" class="py-8 text-center text-slate-400">
                Mengaudit kelayakan yudisium mahasiswa...
              </td>
            </tr>

            <!-- Empty Row -->
            <tr v-else-if="auditedStudents.length === 0">
              <td colspan="10" class="py-8 text-center text-slate-400">
                Tidak ada data mahasiswa ditemukan.
              </td>
            </tr>

            <!-- Data Rows (Matching Screenshot 5) -->
            <tr
              v-for="item in auditedStudents"
              :key="item.student_id"
              class="hover:bg-slate-50/80 transition-colors"
            >
              <!-- Checkbox -->
              <td class="py-3.5 px-3 text-center">
                <input
                  type="checkbox"
                  :checked="selectedStudentIds.includes(item.student_id)"
                  class="w-4 h-4 rounded text-rose-600 border-slate-300 focus:ring-rose-500 cursor-pointer"
                  @change="toggleSelectStudent(item.student_id)"
                />
              </td>

              <!-- Nama Mahasiswa & NIM (Screenshot 5: e.g. Alexander Pochinki / 25410100002) -->
              <td class="py-3.5 px-4">
                <div class="font-bold text-slate-900">
                  {{ item.student.full_name }}
                </div>
                <div class="text-3xs text-slate-500 font-mono mt-0.5">
                  {{ item.student.student_number }}
                </div>
              </td>

              <!-- Program Studi (Screenshot 5: e.g. S1-Multimedia) -->
              <td class="py-3.5 px-4 text-slate-800 font-medium">
                {{ item.study_program?.degree ? `${item.study_program.degree}-${item.study_program.name}` : (item.study_program?.name || '-') }}
              </td>

              <!-- Masa Studi (Screenshot 5: e.g. 1 Tahun 3 Bulan 21 Hari) -->
              <td class="py-3.5 px-4 text-slate-700">
                {{ item.study_duration }}
              </td>

              <!-- SKS Lulus (Screenshot 5: e.g. 20, 0, 13) -->
              <td class="py-3.5 px-4 text-center font-bold text-slate-900">
                {{ item.passed_credits }}
              </td>

              <!-- IPK Lulus (Screenshot 5: e.g. 3, 0, 4) -->
              <td class="py-3.5 px-4 text-center font-bold text-slate-900">
                {{ item.passed_gpa }}
              </td>

              <!-- Tugas Akhir (Screenshot 5: Belum Mengambil / Lulus) -->
              <td class="py-3.5 px-4 text-slate-700">
                {{ item.thesis_status }}
              </td>

              <!-- Status Eligible Badge (Screenshot 5: "Tidak Eligible" [light red] / "Eligible" [green]) -->
              <td class="py-3.5 px-4 text-center">
                <span
                  class="px-2.5 py-1 rounded-md text-3xs font-bold"
                  :class="item.is_eligible ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-600 border border-rose-200'"
                >
                  {{ item.is_eligible ? 'Eligible' : 'Tidak Eligible' }}
                </span>
              </td>

              <!-- Keterangan (Screenshot 5: Bulleted reasons) -->
              <td class="py-3.5 px-4">
                <ul v-if="item.reasons.length > 0" class="space-y-0.5 text-3xs text-slate-600">
                  <li v-for="(reason, rIdx) in item.reasons" :key="rIdx" class="flex items-start gap-1">
                    <span class="text-rose-500 font-bold">•</span>
                    <span>{{ reason }}</span>
                  </li>
                </ul>
                <span v-else class="text-3xs text-emerald-600 font-medium">
                  Memenuhi seluruh syarat yudisium
                </span>
              </td>

              <!-- Aksi (Screenshot 5: Eye icon + Plus icon) -->
              <td class="py-3.5 px-4 text-center">
                <div class="flex items-center justify-center gap-1.5">
                  <!-- Eye Button -->
                  <button
                    type="button"
                    class="p-1.5 rounded-lg border border-rose-300 text-rose-700 hover:bg-rose-50 transition-colors"
                    title="Lihat Detail Transkrip & Kelayakan"
                    @click="openDetail(item)"
                  >
                    <Eye class="w-3.5 h-3.5" />
                  </button>

                  <!-- Plus Button (Daftarkan satuan) -->
                  <button
                    type="button"
                    class="p-1.5 rounded-lg border border-rose-300 text-rose-700 hover:bg-rose-50 transition-colors"
                    title="Daftarkan ke Yudisium"
                    @click="openRegisterModal(item.student_id)"
                  >
                    <Plus class="w-3.5 h-3.5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </Card>

    <!-- Modal Daftarkan Mahasiswa ke Yudisium -->
    <Modal
      v-model:open="showRegisterModal"
      title="Daftarkan Peserta ke Yudisium"
      size="md"
    >
      <div class="space-y-4 text-xs">
        <p class="text-slate-600">
          Anda akan mendaftarkan <strong class="text-slate-900">{{ selectedStudentIds.length }} mahasiswa</strong> ke dalam periode yudisium berikut:
        </p>

        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">
            Pilih Periode Yudisium <span class="text-rose-500">*</span>
          </label>
          <select
            v-model="registerPeriodId"
            required
            class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none focus:ring-2 focus:ring-brand-500"
          >
            <option v-for="p in periods" :key="p.id" :value="p.id">
              {{ p.name }} (Pelaksanaan: {{ p.yudisium_date }})
            </option>
          </select>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end gap-2">
          <Button variant="outline" size="sm" @click="showRegisterModal = false">
            Batal
          </Button>
          <Button
            variant="primary"
            size="sm"
            :loading="registerLoading"
            class="bg-brand-900 text-white"
            @click="handleConfirmRegister"
          >
            Daftarkan Sekarang
          </Button>
        </div>
      </template>
    </Modal>

    <!-- Modal Detail Kelayakan & Transkrip -->
    <Modal
      v-model:open="showDetailModal"
      title="Detail Audit Kelayakan Yudisium"
      size="lg"
    >
      <div v-if="selectedAudit" class="space-y-4 text-xs">
        <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 grid grid-cols-2 sm:grid-cols-4 gap-4">
          <div>
            <span class="text-slate-400 block text-3xs">Mahasiswa</span>
            <span class="font-bold text-slate-900">{{ selectedAudit.student.full_name }}</span>
            <span class="text-3xs text-slate-500 block font-mono">{{ selectedAudit.student.student_number }}</span>
          </div>
          <div>
            <span class="text-slate-400 block text-3xs">Program Studi</span>
            <span class="font-semibold text-slate-800">{{ selectedAudit.study_program?.name || '-' }}</span>
          </div>
          <div>
            <span class="text-slate-400 block text-3xs">Masa Studi</span>
            <span class="font-semibold text-slate-800">{{ selectedAudit.study_duration }}</span>
          </div>
          <div>
            <span class="text-slate-400 block text-3xs">Status Kelayakan</span>
            <span
              class="inline-block mt-0.5 px-2 py-0.5 rounded text-3xs font-bold"
              :class="selectedAudit.is_eligible ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
            >
              {{ selectedAudit.is_eligible ? 'Eligible' : 'Tidak Eligible' }}
            </span>
          </div>
        </div>

        <div class="p-4 bg-white rounded-xl border border-slate-200 space-y-3">
          <h4 class="font-bold text-slate-800 text-xs">Pemeriksaan Syarat Akademik:</h4>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="p-3 bg-slate-50 rounded-lg border border-slate-100">
              <span class="text-3xs text-slate-500 block">SKS Lulus</span>
              <span class="text-sm font-bold text-slate-900">{{ selectedAudit.passed_credits }} SKS</span>
              <span class="text-3xs text-slate-400 block mt-0.5">Minimal 144 SKS</span>
            </div>
            <div class="p-3 bg-slate-50 rounded-lg border border-slate-100">
              <span class="text-3xs text-slate-500 block">IPK Kelulusan</span>
              <span class="text-sm font-bold text-slate-900">{{ selectedAudit.passed_gpa }}</span>
              <span class="text-3xs text-slate-400 block mt-0.5">Minimal 2.75</span>
            </div>
            <div class="p-3 bg-slate-50 rounded-lg border border-slate-100">
              <span class="text-3xs text-slate-500 block">Status Tugas Akhir</span>
              <span class="text-sm font-bold text-slate-900">{{ selectedAudit.thesis_status }}</span>
              <span class="text-3xs text-slate-400 block mt-0.5">Wajib Lulus Sidang TA</span>
            </div>
          </div>

          <div v-if="selectedAudit.reasons.length > 0" class="pt-2">
            <h5 class="font-semibold text-rose-600 text-3xs uppercase tracking-wider mb-1">Catatan Kekurangan:</h5>
            <ul class="space-y-1 text-xs text-slate-700 bg-rose-50/50 p-3 rounded-lg border border-rose-100">
              <li v-for="(reason, rIdx) in selectedAudit.reasons" :key="rIdx" class="flex items-center gap-2">
                <XCircle class="w-3.5 h-3.5 text-rose-500 shrink-0" />
                <span>{{ reason }}</span>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end">
          <Button variant="outline" size="sm" @click="showDetailModal = false">
            Tutup
          </Button>
        </div>
      </template>
    </Modal>
  </PageContainer>
</template>
