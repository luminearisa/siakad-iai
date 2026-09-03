<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import {
  Plus,
  Filter,
  Eye,
  MoreVertical,
} from 'lucide-vue-next'
import { yudisiumService } from '@/services/api/yudisium'
import { academicService } from '@/services/api/academic'
import { useToast } from '@/composables/useToast'
import type { YudisiumParticipant, YudisiumPeriod } from '@/types/yudisium'
import type { StudyProgram } from '@/types/academic'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Modal from '@/components/ui/Modal.vue'

const route = useRoute()
const toast = useToast()

const loading = ref<boolean>(false)
const participants = ref<YudisiumParticipant[]>([])
const periods = ref<YudisiumPeriod[]>([])
const studyPrograms = ref<StudyProgram[]>([])
const totalData = ref<number>(0)
const perPage = ref<number>(10)
const currentPage = ref<number>(1)
const searchQuery = ref<string>('')
const showFilter = ref<boolean>(false)
const filterPeriodId = ref<number | null>(null)
const filterProdiId = ref<number | null>(null)
const filterCertificateTaken = ref<string>('')

// Modal Input SK Yudisium (Matching Screenshot 3)
const showSkModal = ref<boolean>(false)
const skModalLoading = ref<boolean>(false)

const skForm = reactive({
  yudisium_period_id: null as number | null,
  study_program_id: null as number | null,
  sk_number: '',
  sk_date: '2026-08-21',
})

// Modal Detail Peserta
const showDetailModal = ref<boolean>(false)
const selectedParticipant = ref<YudisiumParticipant | null>(null)

async function loadData() {
  loading.value = true
  try {
    const params: any = {
      page: currentPage.value,
      per_page: perPage.value,
    }
    if (filterPeriodId.value) {
      params.yudisium_period_id = filterPeriodId.value
    }
    if (filterProdiId.value) {
      params.study_program_id = filterProdiId.value
    }
    if (filterCertificateTaken.value !== '') {
      params.is_certificate_taken = filterCertificateTaken.value
    }
    if (searchQuery.value.trim()) {
      params.search = searchQuery.value.trim()
    }

    const res = await yudisiumService.getParticipants(params)
    participants.value = res.data || []
    if (res.meta) {
      totalData.value = res.meta.total || participants.value.length
    } else {
      totalData.value = participants.value.length
    }
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal memuat peserta yudisium')
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

    if (route.query.yudisium_period_id) {
      filterPeriodId.value = Number(route.query.yudisium_period_id)
    }
  } catch (err) {
    console.error(err)
  }
}

function handleSearch() {
  currentPage.value = 1
  loadData()
}

function openSkModal() {
  skForm.yudisium_period_id = periods.value.length > 0 ? periods.value[0].id : null
  skForm.study_program_id = studyPrograms.value.length > 0 ? studyPrograms.value[0].id : null
  skForm.sk_number = ''
  skForm.sk_date = new Date().toISOString().substring(0, 10)
  showSkModal.value = true
}

async function handleSaveSk() {
  if (!skForm.yudisium_period_id) {
    toast.error('Pilih Periode Yudisium')
    return
  }
  if (!skForm.study_program_id) {
    toast.error('Pilih Unit Kerja / Program Studi')
    return
  }
  if (!skForm.sk_number.trim()) {
    toast.error('Nomor SK Yudisium wajib diisi')
    return
  }
  if (!skForm.sk_date) {
    toast.error('Tanggal SK Yudisium wajib diisi')
    return
  }

  skModalLoading.value = true
  try {
    await yudisiumService.inputSkBatch({
      yudisium_period_id: skForm.yudisium_period_id,
      study_program_id: skForm.study_program_id,
      sk_number: skForm.sk_number,
      sk_date: skForm.sk_date,
    })
    toast.success('SK Yudisium berhasil disimpan')
    showSkModal.value = false
    loadData()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal menyimpan SK Yudisium')
  } finally {
    skModalLoading.value = false
  }
}

async function handleToggleCertificate(item: YudisiumParticipant) {
  try {
    const res = await yudisiumService.toggleCertificate(item.id)
    item.is_certificate_taken = res.data.is_certificate_taken
    toast.success(res.message)
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal memperbarui status ijazah')
  }
}

function openDetail(item: YudisiumParticipant) {
  selectedParticipant.value = item
  showDetailModal.value = true
}

function formatDate(dateStr?: string) {
  if (!dateStr) return '-'
  try {
    const d = new Date(dateStr)
    return d.toLocaleDateString('id-ID', {
      day: '2-digit',
      month: 'long',
      year: 'numeric',
    })
  } catch {
    return dateStr
  }
}

onMounted(() => {
  loadDependencies()
  loadData()
})
</script>

<template>
  <PageContainer>
    <!-- Header (Matching Screenshot 2) -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div>
        <h1 class="text-xl font-bold text-slate-900 tracking-tight">Peserta Yudisium</h1>
        <p class="text-xs text-slate-500 mt-1">Daftar mahasiswa yang telah terdaftar di Yudisium</p>
      </div>

      <div>
        <!-- Button Input SK Yudisium (Screenshot 2) -->
        <Button
          variant="primary"
          size="sm"
          class="bg-brand-900 hover:bg-brand-950 text-white flex items-center gap-1.5 shadow-2xs font-semibold px-4 py-2"
          @click="openSkModal"
        >
          <Plus class="w-4 h-4" />
          <span>Input SK Yudisium</span>
        </Button>
      </div>
    </div>

    <!-- Main Table Card -->
    <Card class="border border-slate-200/80 shadow-2xs overflow-visible">
      <!-- Toolbar -->
      <div class="p-4 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <!-- Rows per page selector -->
        <div class="flex items-center gap-2 text-xs text-slate-600">
          <span>Baris</span>
          <select
            v-model="perPage"
            class="px-2.5 py-1 bg-white border border-slate-300 rounded-lg text-xs outline-none focus:ring-1 focus:ring-brand-500 font-medium"
            @change="loadData"
          >
            <option :value="10">10</option>
            <option :value="25">25</option>
            <option :value="50">50</option>
          </select>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="flex items-center gap-3">
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
      </div>

      <!-- Collapsible Filter Panel -->
      <div v-if="showFilter" class="p-4 bg-slate-50 border-b border-slate-200 grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs animate-fade-in">
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Periode Yudisium</label>
          <select
            v-model="filterPeriodId"
            class="w-full px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs outline-none"
            @change="loadData"
          >
            <option :value="null">Semua Periode</option>
            <option v-for="p in periods" :key="p.id" :value="p.id">
              {{ p.name }}
            </option>
          </select>
        </div>

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
          <label class="block font-semibold text-slate-700 mb-1">Status Pengambilan Ijazah</label>
          <select
            v-model="filterCertificateTaken"
            class="w-full px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs outline-none"
            @change="loadData"
          >
            <option value="">Semua</option>
            <option value="true">Sudah Diambil (Ya)</option>
            <option value="false">Belum Diambil (Tidak)</option>
          </select>
        </div>
      </div>

      <!-- Table (Matching Screenshot 2) -->
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
          <thead>
            <tr class="bg-slate-50/80 text-slate-600 font-semibold border-b border-slate-200 text-3xs uppercase tracking-wider">
              <th class="py-3 px-4 w-12 text-center">NO</th>
              <th class="py-3 px-4">NAMA MAHASISWA</th>
              <th class="py-3 px-4">PROGRAM STUDI</th>
              <th class="py-3 px-4">PERIODE YUDISIUM</th>
              <th class="py-3 px-4">SK YUDISIUM</th>
              <th class="py-3 px-4 text-center">IJAZAH SUDAH DIAMBIL</th>
              <th class="py-3 px-4 text-center w-24">AKSI</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <!-- Loading Row -->
            <tr v-if="loading">
              <td colspan="7" class="py-8 text-center text-slate-400">
                Memuat data peserta yudisium...
              </td>
            </tr>

            <!-- Empty Row -->
            <tr v-else-if="participants.length === 0">
              <td colspan="7" class="py-8 text-center text-slate-400">
                Belum ada peserta yudisium terdaftar.
              </td>
            </tr>

            <!-- Data Rows (Matching Screenshot 2) -->
            <tr
              v-for="(item, index) in participants"
              :key="item.id"
              class="hover:bg-slate-50/80 transition-colors"
            >
              <td class="py-3.5 px-4 text-center text-slate-500 font-medium">
                {{ index + 1 }}
              </td>

              <!-- Nama Mahasiswa & NIM -->
              <td class="py-3.5 px-4">
                <div class="font-bold text-slate-900">
                  {{ item.student?.full_name }}
                </div>
                <div class="text-3xs text-slate-500 font-mono mt-0.5">
                  {{ item.student?.student_number }}
                </div>
              </td>

              <!-- Program Studi -->
              <td class="py-3.5 px-4 text-slate-800 font-medium">
                {{ item.student?.study_program?.degree ? `${item.student.study_program.degree}-${item.student.study_program.name}` : (item.student?.study_program?.name || '-') }}
              </td>

              <!-- Periode Yudisium -->
              <td class="py-3.5 px-4 text-slate-800">
                {{ item.period?.semester?.name || item.period?.name || '2026/2027 Ganjil' }}
              </td>

              <!-- SK Yudisium (Nomor & Tanggal) -->
              <td class="py-3.5 px-4">
                <div class="font-semibold text-slate-900">
                  {{ item.sk_number || '-' }}
                </div>
                <div v-if="item.sk_date" class="text-3xs text-slate-500 mt-0.5">
                  {{ formatDate(item.sk_date) }}
                </div>
              </td>

              <!-- Ijazah Sudah Diambil Badge (Screenshot 2: Red button 'Tidak' or Green 'Ya') -->
              <td class="py-3.5 px-4 text-center">
                <button
                  type="button"
                  class="px-4 py-1 rounded-md text-xs font-semibold shadow-2xs transition-all"
                  :class="item.is_certificate_taken ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'bg-rose-500 text-white hover:bg-rose-600'"
                  title="Klik untuk mengubah status pengambilan ijazah"
                  @click="handleToggleCertificate(item)"
                >
                  {{ item.is_certificate_taken ? 'Ya' : 'Tidak' }}
                </button>
              </td>

              <!-- Aksi -->
              <td class="py-3.5 px-4 text-center">
                <div class="flex items-center justify-center gap-1.5">
                  <!-- 3-Dots Button (Screenshot 2) -->
                  <button
                    type="button"
                    class="p-1.5 rounded-lg border border-rose-300 text-rose-700 hover:bg-rose-50 transition-colors"
                    title="Menu Opsi"
                    @click="openDetail(item)"
                  >
                    <MoreVertical class="w-3.5 h-3.5" />
                  </button>

                  <!-- Eye Button (Screenshot 2) -->
                  <button
                    type="button"
                    class="p-1.5 rounded-lg border border-rose-300 text-rose-700 hover:bg-rose-50 transition-colors"
                    title="Lihat Detail Peserta"
                    @click="openDetail(item)"
                  >
                    <Eye class="w-3.5 h-3.5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Footer Pagination Info (Matching Screenshot 2) -->
      <div class="p-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
        <div>
          Menampilkan 1 hingga {{ participants.length }} dari total {{ totalData }} data
        </div>

        <div class="flex items-center gap-1">
          <button
            class="w-7 h-7 flex items-center justify-center rounded-lg border border-slate-200 text-slate-400 hover:bg-slate-50 text-xs disabled:opacity-40"
            :disabled="currentPage === 1"
            @click="currentPage--; loadData()"
          >
            «
          </button>
          <button
            class="w-7 h-7 flex items-center justify-center rounded-lg border border-slate-200 text-slate-400 hover:bg-slate-50 text-xs disabled:opacity-40"
            :disabled="currentPage === 1"
            @click="currentPage--; loadData()"
          >
            ‹
          </button>
          <button
            class="w-7 h-7 flex items-center justify-center rounded-lg bg-brand-900 text-white font-bold text-xs"
          >
            {{ currentPage }}
          </button>
          <button
            class="w-7 h-7 flex items-center justify-center rounded-lg border border-slate-200 text-slate-400 hover:bg-slate-50 text-xs disabled:opacity-40"
            :disabled="participants.length < perPage"
            @click="currentPage++; loadData()"
          >
            ›
          </button>
          <button
            class="w-7 h-7 flex items-center justify-center rounded-lg border border-slate-200 text-slate-400 hover:bg-slate-50 text-xs disabled:opacity-40"
            :disabled="participants.length < perPage"
            @click="currentPage++; loadData()"
          >
            »
          </button>
        </div>
      </div>
    </Card>

    <!-- Modal Input SK Yudisium (Matching Screenshot 3) -->
    <Modal
      v-model:open="showSkModal"
      title="Input SK Yudisium"
      size="md"
    >
      <div class="text-xs text-slate-600 mb-4 pb-2 border-b border-slate-100">
        Silakan isi informasi SK yudisium untuk mahasiswa dalam periode dan unit kerja yang dipilih.
      </div>

      <form @submit.prevent="handleSaveSk" class="space-y-4 text-xs">
        <!-- Periode Yudisium -->
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">
            Periode Yudisium <span class="text-rose-500">*</span>
          </label>
          <select
            v-model="skForm.yudisium_period_id"
            required
            class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600"
          >
            <option :value="null">Pilih Periode Yudisium</option>
            <option v-for="p in periods" :key="p.id" :value="p.id">
              {{ p.name }}
            </option>
          </select>
        </div>

        <!-- Unit Kerja (Program Studi) -->
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">
            Unit Kerja <span class="text-rose-500">*</span>
          </label>
          <select
            v-model="skForm.study_program_id"
            required
            class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600"
          >
            <option :value="null">Pilih Program Studi</option>
            <option v-for="sp in studyPrograms" :key="sp.id" :value="sp.id">
              {{ sp.degree ? `${sp.degree} - ${sp.name}` : sp.name }}
            </option>
          </select>
        </div>

        <!-- Nomor SK Yudisium -->
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">
            Nomor SK Yudisium <span class="text-rose-500">*</span>
          </label>
          <Input
            v-model="skForm.sk_number"
            placeholder="Masukkan Nomor SK Yudisium"
            required
          />
        </div>

        <!-- Tanggal SK Yudisium -->
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">
            Tanggal SK Yudisium <span class="text-rose-500">*</span>
          </label>
          <Input
            v-model="skForm.sk_date"
            type="date"
            placeholder="Masukkan Tanggal SK Yudisium"
            required
          />
        </div>
      </form>

      <template #footer>
        <div class="flex items-center justify-end gap-2">
          <!-- Batal Button (Screenshot 3) -->
          <Button
            variant="outline"
            size="sm"
            class="border-slate-300 text-slate-700 hover:bg-slate-50 px-4"
            @click="showSkModal = false"
          >
            Batal
          </Button>

          <!-- Simpan Data Button (Screenshot 3) -->
          <Button
            variant="primary"
            size="sm"
            :loading="skModalLoading"
            class="bg-brand-900 hover:bg-brand-950 text-white font-semibold px-4"
            @click="handleSaveSk"
          >
            Simpan Data
          </Button>
        </div>
      </template>
    </Modal>

    <!-- Modal Detail Peserta -->
    <Modal
      v-model:open="showDetailModal"
      title="Detail Peserta Yudisium"
      size="lg"
    >
      <div v-if="selectedParticipant" class="space-y-4 text-xs">
        <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 grid grid-cols-2 sm:grid-cols-4 gap-4">
          <div>
            <span class="text-slate-500 block text-3xs">Mahasiswa</span>
            <span class="font-bold text-slate-900">{{ selectedParticipant.student?.full_name }}</span>
            <span class="text-3xs text-slate-400 block font-mono">{{ selectedParticipant.student?.student_number }}</span>
          </div>
          <div>
            <span class="text-slate-500 block text-3xs">Program Studi</span>
            <span class="font-semibold text-slate-800">{{ selectedParticipant.student?.study_program?.name || '-' }}</span>
          </div>
          <div>
            <span class="text-slate-500 block text-3xs">SKS Lulus / IPK</span>
            <span class="font-bold text-slate-900">{{ selectedParticipant.total_credits }} SKS / IPK {{ selectedParticipant.gpa }}</span>
          </div>
          <div>
            <span class="text-slate-500 block text-3xs">Nomor & Tanggal SK</span>
            <span class="font-bold text-slate-900">{{ selectedParticipant.sk_number || 'Belum ada SK' }}</span>
            <span class="text-3xs text-slate-500 block">{{ formatDate(selectedParticipant.sk_date || '') }}</span>
          </div>
        </div>

        <div v-if="selectedParticipant.thesis" class="p-4 bg-white rounded-xl border border-slate-200">
          <span class="text-3xs uppercase tracking-wider font-bold text-slate-400 block mb-1">Tugas Akhir</span>
          <div class="font-semibold text-slate-900 text-xs" v-html="selectedParticipant.thesis.title_id" />
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
