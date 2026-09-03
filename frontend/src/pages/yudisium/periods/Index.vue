<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import {
  Plus,
  Filter,
  Eye,
  MoreVertical,
  Edit2,
  Trash2,
} from 'lucide-vue-next'
import { yudisiumService } from '@/services/api/yudisium'
import { academicService } from '@/services/api/academic'
import { useToast } from '@/composables/useToast'
import type { YudisiumPeriod } from '@/types/yudisium'
import type { Semester } from '@/types/academic'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Modal from '@/components/ui/Modal.vue'

const toast = useToast()

const loading = ref<boolean>(false)
const periods = ref<YudisiumPeriod[]>([])
const semesters = ref<Semester[]>([])
const totalData = ref<number>(0)
const perPage = ref<number>(10)
const currentPage = ref<number>(1)
const searchQuery = ref<string>('')
const showFilter = ref<boolean>(false)
const filterActive = ref<string>('')

// Modal state
const showModal = ref<boolean>(false)
const isEditing = ref<boolean>(false)
const editingId = ref<number | null>(null)
const modalLoading = ref<boolean>(false)

const form = reactive({
  name: '',
  semester_id: null as number | null,
  registration_start_date: '',
  registration_end_date: '',
  yudisium_date: '',
  quota: null as number | null,
  is_active: true,
  notes: '',
})

// Dropdown action state
const activeDropdownId = ref<number | null>(null)

function toggleDropdown(id: number, event: Event) {
  event.stopPropagation()
  activeDropdownId.value = activeDropdownId.value === id ? null : id
}

function closeDropdown() {
  activeDropdownId.value = null
}

async function loadData() {
  loading.value = true
  try {
    const params: any = {
      page: currentPage.value,
      per_page: perPage.value,
    }
    if (searchQuery.value.trim()) {
      params.search = searchQuery.value.trim()
    }
    if (filterActive.value !== '') {
      params.is_active = filterActive.value
    }

    const res = await yudisiumService.getPeriods(params)
    periods.value = res.data || []
    if (res.meta) {
      totalData.value = res.meta.total || periods.value.length
    } else {
      totalData.value = periods.value.length
    }
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal memuat daftar periode yudisium')
  } finally {
    loading.value = false
  }
}

async function loadDependencies() {
  try {
    const semRes = await academicService.getSemesters()
    semesters.value = semRes.data || []
  } catch (err) {
    console.error(err)
  }
}

function handleSearch() {
  currentPage.value = 1
  loadData()
}

function openCreateModal() {
  isEditing.value = false
  editingId.value = null
  form.name = ''
  form.semester_id = semesters.value.length > 0 ? semesters.value[0].id : null
  form.registration_start_date = '2026-07-03'
  form.registration_end_date = '2026-07-31'
  form.yudisium_date = '2026-08-07'
  form.quota = null
  form.is_active = true
  form.notes = ''
  showModal.value = true
}

function openEditModal(period: YudisiumPeriod) {
  isEditing.value = true
  editingId.value = period.id
  form.name = period.name
  form.semester_id = period.semester_id || null
  form.registration_start_date = period.registration_start_date
  form.registration_end_date = period.registration_end_date
  form.yudisium_date = period.yudisium_date
  form.quota = period.quota || null
  form.is_active = period.is_active
  form.notes = period.notes || ''
  showModal.value = true
  closeDropdown()
}

async function handleSave() {
  if (!form.name.trim()) {
    toast.error('Nama periode yudisium wajib diisi')
    return
  }
  if (!form.registration_start_date || !form.registration_end_date || !form.yudisium_date) {
    toast.error('Semua tanggal wajib diisi')
    return
  }

  modalLoading.value = true
  try {
    if (isEditing.value && editingId.value) {
      await yudisiumService.updatePeriod(editingId.value, form)
      toast.success('Periode yudisium berhasil diperbarui')
    } else {
      await yudisiumService.createPeriod(form)
      toast.success('Periode yudisium berhasil ditambahkan')
    }
    showModal.value = false
    loadData()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal menyimpan periode yudisium')
  } finally {
    modalLoading.value = false
  }
}

async function handleDelete(period: YudisiumPeriod) {
  closeDropdown()
  if (!confirm(`Hapus periode yudisium "${period.name}"?`)) return

  try {
    await yudisiumService.deletePeriod(period.id)
    toast.success('Periode yudisium berhasil dihapus')
    loadData()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal menghapus periode yudisium')
  }
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
  loadData()
  loadDependencies()
  document.addEventListener('click', closeDropdown)
})
</script>

<template>
  <PageContainer>
    <!-- Header (Matching Screenshot 1) -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div>
        <h1 class="text-xl font-bold text-slate-900 tracking-tight">Periode Yudisium</h1>
        <p class="text-xs text-slate-500 mt-1">Manajemen Periode Yudisium</p>
      </div>

      <div>
        <Button
          variant="primary"
          size="sm"
          class="bg-brand-900 hover:bg-brand-950 text-white flex items-center gap-1.5 shadow-2xs font-semibold px-4 py-2"
          @click="openCreateModal"
        >
          <Plus class="w-4 h-4" />
          <span>Tambah Data</span>
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
            class="border-slate-300 text-slate-700 hover:bg-slate-50 flex items-center gap-1.5 font-medium relative"
            @click="showFilter = !showFilter"
          >
            <Filter class="w-3.5 h-3.5 text-rose-600" />
            <span>Filter</span>
            <span class="w-1.5 h-1.5 rounded-full bg-rose-500 absolute top-1 right-1" />
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
          <label class="block font-semibold text-slate-700 mb-1">Status Periode</label>
          <select
            v-model="filterActive"
            class="w-full px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs outline-none"
            @change="loadData"
          >
            <option value="">Semua Status</option>
            <option value="true">Aktif</option>
            <option value="false">Non-Aktif</option>
          </select>
        </div>
      </div>

      <!-- Table (Matching Screenshot 1) -->
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
          <thead>
            <tr class="bg-slate-50/80 text-slate-600 font-semibold border-b border-slate-200 text-3xs uppercase tracking-wider">
              <th class="py-3 px-4 w-12 text-center">NO</th>
              <th class="py-3 px-4">NAMA PERIODE</th>
              <th class="py-3 px-4">MULAI PENDAFTARAN</th>
              <th class="py-3 px-4">SELESAI PENDAFTARAN</th>
              <th class="py-3 px-4">TANGGAL YUDISIUM</th>
              <th class="py-3 px-4 text-center w-24">AKSI</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <!-- Loading Row -->
            <tr v-if="loading">
              <td colspan="6" class="py-8 text-center text-slate-400">
                Memuat data periode yudisium...
              </td>
            </tr>

            <!-- Empty Row -->
            <tr v-else-if="periods.length === 0">
              <td colspan="6" class="py-8 text-center text-slate-400">
                Belum ada data periode yudisium.
              </td>
            </tr>

            <!-- Data Rows -->
            <tr
              v-for="(item, index) in periods"
              :key="item.id"
              class="hover:bg-slate-50/80 transition-colors"
            >
              <td class="py-3.5 px-4 text-center text-slate-500 font-medium">
                {{ index + 1 }}
              </td>
              <td class="py-3.5 px-4 font-semibold text-slate-900">
                {{ item.name }}
                <span
                  v-if="item.is_active"
                  class="ml-2 px-1.5 py-0.5 rounded text-4xs bg-emerald-50 text-emerald-700 font-bold border border-emerald-200"
                >
                  Aktif
                </span>
              </td>
              <td class="py-3.5 px-4 text-slate-800">
                {{ formatDate(item.registration_start_date) }}
              </td>
              <td class="py-3.5 px-4 text-slate-800">
                {{ formatDate(item.registration_end_date) }}
              </td>
              <td class="py-3.5 px-4 text-slate-800 font-medium">
                {{ formatDate(item.yudisium_date) }}
              </td>
              <td class="py-3.5 px-4 text-center relative">
                <div class="flex items-center justify-center gap-1.5">
                  <!-- 3-Dots Menu Button (Screenshot 1) -->
                  <button
                    type="button"
                    class="p-1.5 rounded-lg border border-rose-300 text-rose-700 hover:bg-rose-50 transition-colors"
                    @click="toggleDropdown(item.id, $event)"
                  >
                    <MoreVertical class="w-3.5 h-3.5" />
                  </button>

                  <!-- Eye Action Button (Screenshot 1) -->
                  <button
                    type="button"
                    class="p-1.5 rounded-lg border border-rose-300 text-rose-700 hover:bg-rose-50 transition-colors"
                    title="Lihat Peserta"
                    @click="$router.push({ path: '/graduation/yudisium/participants', query: { yudisium_period_id: item.id } })"
                  >
                    <Eye class="w-3.5 h-3.5" />
                  </button>
                </div>

                <!-- Dropdown Popover -->
                <div
                  v-if="activeDropdownId === item.id"
                  class="absolute right-4 mt-1 w-32 bg-white border border-slate-200 rounded-xl shadow-xl z-50 py-1 text-left animate-fade-in"
                  @click.stop
                >
                  <button
                    type="button"
                    class="w-full px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50 flex items-center gap-2"
                    @click="openEditModal(item)"
                  >
                    <Edit2 class="w-3.5 h-3.5 text-blue-600" />
                    <span>Edit</span>
                  </button>
                  <button
                    type="button"
                    class="w-full px-3 py-1.5 text-xs text-rose-600 hover:bg-rose-50 flex items-center gap-2"
                    @click="handleDelete(item)"
                  >
                    <Trash2 class="w-3.5 h-3.5" />
                    <span>Hapus</span>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Footer Pagination Info (Matching Screenshot 1) -->
      <div class="p-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
        <div>
          Menampilkan 1 hingga {{ periods.length }} dari total {{ totalData }} data
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
            :disabled="periods.length < perPage"
            @click="currentPage++; loadData()"
          >
            ›
          </button>
          <button
            class="w-7 h-7 flex items-center justify-center rounded-lg border border-slate-200 text-slate-400 hover:bg-slate-50 text-xs disabled:opacity-40"
            :disabled="periods.length < perPage"
            @click="currentPage++; loadData()"
          >
            »
          </button>
        </div>
      </div>
    </Card>

    <!-- Modal Tambah / Edit Periode Yudisium -->
    <Modal
      v-model:open="showModal"
      :title="isEditing ? 'Edit Periode Yudisium' : 'Tambah Periode Yudisium'"
      size="md"
    >
      <form @submit.prevent="handleSave" class="space-y-4 text-xs">
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">
            Nama Periode <span class="text-rose-500">*</span>
          </label>
          <Input
            v-model="form.name"
            placeholder="Contoh: Yudisium 75 / Yudisium Ganjil 2026"
            required
          />
        </div>

        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">
            Semester Terkait
          </label>
          <select
            v-model="form.semester_id"
            class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600"
          >
            <option :value="null">Pilih Semester</option>
            <option v-for="sem in semesters" :key="sem.id" :value="sem.id">
              {{ sem.name }}
            </option>
          </select>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Mulai Pendaftaran <span class="text-rose-500">*</span>
            </label>
            <Input
              v-model="form.registration_start_date"
              type="date"
              required
            />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Selesai Pendaftaran <span class="text-rose-500">*</span>
            </label>
            <Input
              v-model="form.registration_end_date"
              type="date"
              required
            />
          </div>
        </div>

        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">
            Tanggal Pelaksanaan Yudisium <span class="text-rose-500">*</span>
          </label>
          <Input
            v-model="form.yudisium_date"
            type="date"
            required
          />
        </div>

        <div class="flex items-center gap-2 pt-2">
          <input
            id="is_active_check"
            v-model="form.is_active"
            type="checkbox"
            class="w-4 h-4 rounded text-brand-900 border-slate-300 focus:ring-brand-500"
          />
          <label for="is_active_check" class="font-medium text-slate-800 select-none cursor-pointer">
            Periode Aktif (Menerima Pendaftaran)
          </label>
        </div>
      </form>

      <template #footer>
        <div class="flex items-center justify-end gap-2">
          <Button
            variant="outline"
            size="sm"
            @click="showModal = false"
          >
            Batal
          </Button>
          <Button
            variant="primary"
            size="sm"
            :loading="modalLoading"
            class="bg-brand-900 hover:bg-brand-950 text-white"
            @click="handleSave"
          >
            {{ isEditing ? 'Simpan Perubahan' : 'Simpan Data' }}
          </Button>
        </div>
      </template>
    </Modal>
  </PageContainer>
</template>
