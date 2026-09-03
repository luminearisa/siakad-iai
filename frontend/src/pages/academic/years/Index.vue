<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { Plus, Calendar, Edit, Trash2, X, Check, Search, CalendarDays } from 'lucide-vue-next'
import { academicService } from '@/services/api/academic'
import { useToast } from '@/composables/useToast'
import type { AcademicYear } from '@/types/academic'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import ConfirmModal from '@/components/feedback/ConfirmModal.vue'

const toast = useToast()
const loading = ref<boolean>(false)
const saving = ref<boolean>(false)
const academicYears = ref<AcademicYear[]>([])
const search = ref<string>('')
const perPage = ref<number>(10)

// Drawer / Modal State
const drawerOpen = ref<boolean>(false)
const isEditing = ref<boolean>(false)
const selectedYearId = ref<number | null>(null)

const form = reactive({
  name: '',
  start_date: '',
  end_date: '',
  status: 'active',
})

// Delete Modal State
const deleteModalOpen = ref<boolean>(false)
const deleting = ref<boolean>(false)
const itemToDelete = ref<AcademicYear | null>(null)

async function loadAcademicYears() {
  loading.value = true
  try {
    const res = await academicService.getAcademicYears({ per_page: 50 })
    academicYears.value = res.data || []
  } catch (err: any) {
    toast.error(err.message || 'Gagal memuat data tahun ajaran.')
  } finally {
    loading.value = false
  }
}

const filteredYears = computed(() => {
  let list = academicYears.value
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    list = list.filter((y) => y.name.toLowerCase().includes(q))
  }
  return list.slice(0, perPage.value)
})

function extractStartYear(name: string): string {
  const parts = name.split('/')
  return parts[0] || name
}

function formatDate(dateStr?: string): string {
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

function openCreateDrawer() {
  isEditing.value = false
  selectedYearId.value = null
  const currentYear = new Date().getFullYear()
  form.name = `${currentYear}/${currentYear + 1}`
  form.start_date = `${currentYear}-08-01`
  form.end_date = `${currentYear + 1}-07-31`
  form.status = 'active'
  drawerOpen.value = true
}

function openEditDrawer(item: AcademicYear) {
  isEditing.value = true
  selectedYearId.value = item.id
  form.name = item.name
  form.start_date = item.start_date ? item.start_date.substring(0, 10) : ''
  form.end_date = item.end_date ? item.end_date.substring(0, 10) : ''
  form.status = (item.status as string) || 'active'
  drawerOpen.value = true
}

async function handleSave() {
  if (!form.name || !form.start_date || !form.end_date) {
    toast.warning('Mohon lengkapi seluruh isian wajib bertanda bintang (*).')
    return
  }

  saving.value = true
  try {
    if (isEditing.value && selectedYearId.value) {
      await academicService.updateAcademicYear(selectedYearId.value, form)
      toast.success('Tahun ajaran berhasil diperbarui.')
    } else {
      await academicService.createAcademicYear(form)
      toast.success('Tahun ajaran baru berhasil ditambahkan.')
    }
    drawerOpen.value = false
    await loadAcademicYears()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menyimpan tahun ajaran.')
  } finally {
    saving.value = false
  }
}

async function handleSetActive(item: AcademicYear) {
  if (item.status === 'active' || item.status === 'Aktif') return
  
  try {
    const res = await academicService.setActiveAcademicYear(item.id)
    toast.success(res.message || `Tahun ajaran ${item.name} berhasil diaktifkan.`)
    await loadAcademicYears()
  } catch (err: any) {
    toast.error(err.response?.data?.message || err.message || 'Gagal mengaktifkan tahun ajaran.')
  }
}

function openDeleteModal(item: AcademicYear) {
  itemToDelete.value = item
  deleteModalOpen.value = true
}

async function handleDelete() {
  if (!itemToDelete.value) return
  deleting.value = true
  try {
    await academicService.deleteAcademicYear(itemToDelete.value.id)
    toast.success(`Tahun ajaran ${itemToDelete.value.name} berhasil dihapus.`)
    deleteModalOpen.value = false
    itemToDelete.value = null
    await loadAcademicYears()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menghapus tahun ajaran.')
  } finally {
    deleting.value = false
  }
}

onMounted(() => {
  loadAcademicYears()
})
</script>

<template>
  <PageContainer>
    <div class="space-y-4">
      <!-- Breadcrumb Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200/80 pb-4">
        <div>
          <div class="flex items-center gap-2 text-xs text-slate-500 font-medium mb-1">
            <CalendarDays class="w-4 h-4 text-brand-600" />
            <span>Akademik</span>
            <span>&rsaquo;</span>
            <span class="text-slate-900 font-semibold">Tahun Ajaran</span>
          </div>
          <h1 class="text-xl font-bold text-slate-900 tracking-tight">Tahun Ajaran</h1>
          <p class="text-xs text-slate-500 mt-0.5">Manajemen Tahun Ajaran & Periode Kalender Akademik</p>
        </div>

        <Button
          variant="primary"
          size="sm"
          class="gap-1.5 self-start sm:self-auto bg-brand-700 hover:bg-brand-800 text-white"
          @click="openCreateDrawer"
        >
          <Plus class="w-4 h-4" />
          Tambah Tahun Ajaran
        </Button>
      </div>

      <!-- Table Card with Integrated Toolbar -->
      <Card class="bg-white border border-slate-200/80 rounded-xl shadow-2xs overflow-hidden">
        <!-- Integrated Toolbar -->
        <div class="p-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white">
          <!-- Rows per page selector -->
          <div class="flex items-center gap-2 text-xs text-slate-600">
            <span class="font-medium">Baris</span>
            <select
              v-model.number="perPage"
              class="border border-slate-300 rounded-lg text-xs py-1.5 px-3 bg-white text-slate-800 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none font-medium"
            >
              <option :value="10">10</option>
              <option :value="25">25</option>
              <option :value="50">50</option>
              <option :value="100">100</option>
            </select>
          </div>

          <!-- Search Box -->
          <div class="relative w-full sm:w-72">
            <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
            <input
              v-model="search"
              type="text"
              placeholder="Cari tahun ajaran..."
              class="w-full pl-9 pr-3 py-1.5 text-xs bg-white border border-slate-300 rounded-lg text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none transition-all"
            />
          </div>
        </div>
        <div v-if="loading" class="p-6 space-y-3">
          <Skeleton height="2.5rem" rounded="md" />
          <Skeleton height="2.5rem" rounded="md" />
          <Skeleton height="2.5rem" rounded="md" />
        </div>

        <div v-else-if="filteredYears.length === 0" class="py-12 text-center text-slate-400 text-xs">
          <Calendar class="w-8 h-8 text-slate-300 mx-auto mb-2" />
          <p class="font-bold text-slate-700">Belum ada data Tahun Ajaran</p>
          <p class="text-2xs text-slate-400 mt-0.5">Klik tombol "+ Tambah Tahun Ajaran" untuk menambahkan data baru.</p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="bg-slate-50/80 border-b border-slate-200 text-3xs font-semibold text-slate-600 uppercase tracking-wider">
                <th class="py-3 px-4 w-12 text-center">NO</th>
                <th class="py-3 px-4 w-28">TAHUN</th>
                <th class="py-3 px-4 min-w-[150px]">TAHUN AJARAN</th>
                <th class="py-3 px-4 min-w-[180px]">AWAL TAHUN AJARAN</th>
                <th class="py-3 px-4 min-w-[180px]">AKHIR TAHUN AJARAN</th>
                <th class="py-3 px-4 w-28 text-center">STATUS</th>
                <th class="py-3 px-4 w-24 text-center">AKSI</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="(item, idx) in filteredYears"
                :key="item.id"
                class="hover:bg-slate-50/70 transition-colors group"
              >
                <td class="py-3 px-4 text-center font-mono text-3xs text-slate-400">
                  {{ idx + 1 }}
                </td>
                <td class="py-3 px-4 font-mono font-medium text-slate-700">
                  {{ extractStartYear(item.name) }}
                </td>
                <td class="py-3 px-4 font-bold text-slate-900">
                  {{ item.name }}
                </td>
                <td class="py-3 px-4 text-slate-700">
                  {{ formatDate(item.start_date) }}
                </td>
                <td class="py-3 px-4 text-slate-700">
                  {{ formatDate(item.end_date) }}
                </td>
                <td class="py-3 px-4 text-center">
                  <button
                    type="button"
                    :class="[
                      'inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-3xs font-semibold transition-all cursor-pointer border',
                      item.status === 'active' || item.status === 'Aktif'
                        ? 'bg-emerald-50 text-emerald-700 border-emerald-200 shadow-2xs'
                        : 'bg-slate-100 text-slate-600 border-slate-200 hover:bg-emerald-50 hover:text-emerald-700 hover:border-emerald-200'
                    ]"
                    :title="item.status === 'active' || item.status === 'Aktif' ? 'Tahun Ajaran Aktif Saat Ini' : 'Klik untuk jadikan Tahun Ajaran Aktif'"
                    @click="handleSetActive(item)"
                  >
                    <span
                      :class="[
                        'w-1.5 h-1.5 rounded-full',
                        item.status === 'active' || item.status === 'Aktif' ? 'bg-emerald-500' : 'bg-slate-400'
                      ]"
                    />
                    {{ item.status === 'active' || item.status === 'Aktif' ? 'Aktif' : 'Nonaktif' }}
                  </button>
                </td>
                <td class="py-3 px-4 text-center">
                  <div class="flex items-center justify-center gap-1">
                    <button
                      type="button"
                      class="p-1.5 rounded-md text-slate-500 hover:text-brand-600 hover:bg-brand-50 transition-colors"
                      title="Edit Tahun Ajaran"
                      @click="openEditDrawer(item)"
                    >
                      <Edit class="w-3.5 h-3.5" />
                    </button>
                    <button
                      type="button"
                      class="p-1.5 rounded-md text-slate-500 hover:text-rose-600 hover:bg-rose-50 transition-colors"
                      title="Hapus Tahun Ajaran"
                      @click="openDeleteModal(item)"
                    >
                      <Trash2 class="w-3.5 h-3.5" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="p-3 border-t border-slate-100 bg-slate-50/40 text-2xs text-slate-500 flex items-center justify-between">
          <span>Menampilkan 1 hingga {{ filteredYears.length }} dari total {{ academicYears.length }} data</span>
        </div>
      </Card>
    </div>

    <!-- Side Drawer: Tambah / Edit Tahun Ajaran (Persis seperti Gambar 1) -->
    <div v-if="drawerOpen" class="fixed inset-0 z-50 overflow-hidden">
      <!-- Backdrop -->
      <div
        class="absolute inset-0 bg-slate-900/40 backdrop-blur-xs transition-opacity"
        @click="drawerOpen = false"
      />

      <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
        <div class="w-screen max-w-md bg-white shadow-2xl flex flex-col">
          <!-- Drawer Header -->
          <div class="p-5 border-b border-slate-200 flex items-center justify-between bg-slate-50/50">
            <div>
              <h2 class="text-sm font-bold text-slate-900">
                {{ isEditing ? 'Edit Tahun Ajaran' : 'Tambah Tahun Ajaran' }}
              </h2>
              <p class="text-2xs text-slate-500 mt-0.5">Kelola kalender dan rentang waktu tahun ajaran</p>
            </div>
            <button
              type="button"
              class="p-1.5 rounded-md text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
              @click="drawerOpen = false"
            >
              <X class="w-4 h-4" />
            </button>
          </div>

          <!-- Drawer Form Body -->
          <form class="flex-1 overflow-y-auto p-5 space-y-4" @submit.prevent="handleSave">
            <div class="space-y-1.5">
              <label class="block text-xs font-semibold text-slate-800">
                Tahun Ajaran <span class="text-rose-500">*</span>
              </label>
              <Input
                v-model="form.name"
                type="text"
                placeholder="Contoh: 2026/2027"
                required
              />
              <p class="text-3xs text-slate-400">Format format standar tahun ajaran: YYYY/YYYY (contoh: 2026/2027)</p>
            </div>

            <div class="space-y-1.5">
              <label class="block text-xs font-semibold text-slate-800">
                Tanggal Awal Tahun Ajaran <span class="text-rose-500">*</span>
              </label>
              <div class="relative">
                <input
                  v-model="form.start_date"
                  type="date"
                  class="w-full text-xs py-2 px-3 border border-slate-300 rounded-lg text-slate-900 bg-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none"
                  required
                />
              </div>
            </div>

            <div class="space-y-1.5">
              <label class="block text-xs font-semibold text-slate-800">
                Tanggal Akhir Tahun Ajaran <span class="text-rose-500">*</span>
              </label>
              <div class="relative">
                <input
                  v-model="form.end_date"
                  type="date"
                  class="w-full text-xs py-2 px-3 border border-slate-300 rounded-lg text-slate-900 bg-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none"
                  required
                />
              </div>
            </div>

            <div class="space-y-1.5">
              <label class="block text-xs font-semibold text-slate-800">
                Status Keaktifan
              </label>
              <select
                v-model="form.status"
                class="w-full text-xs py-2 px-3 border border-slate-300 rounded-lg text-slate-900 bg-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none"
              >
                <option value="active">Aktif</option>
                <option value="inactive">Nonaktif</option>
              </select>
            </div>
          </form>

          <!-- Drawer Footer -->
          <div class="p-4 border-t border-slate-200 bg-slate-50 flex items-center justify-end gap-2">
            <Button
              type="button"
              variant="secondary"
              size="sm"
              :disabled="saving"
              @click="drawerOpen = false"
            >
              <X class="w-3.5 h-3.5 text-rose-500 mr-1" />
              Batal
            </Button>
            <Button
              type="button"
              variant="primary"
              size="sm"
              class="bg-brand-700 hover:bg-brand-800 text-white gap-1.5"
              :loading="saving"
              @click="handleSave"
            >
              <Check class="w-3.5 h-3.5" />
              Simpan Data
            </Button>
          </div>
        </div>
      </div>
    </div>

    <!-- Confirm Delete Modal -->
    <ConfirmModal
      :open="deleteModalOpen"
      title="Hapus Tahun Ajaran"
      :message="`Apakah Anda yakin ingin menghapus tahun ajaran ${itemToDelete?.name}? Data semester dan perkuliahan yang terikat akan terpengaruh.`"
      confirm-text="Ya, Hapus"
      variant="danger"
      :loading="deleting"
      @update:open="deleteModalOpen = $event"
      @confirm="handleDelete"
    />
  </PageContainer>
</template>
