<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { Plus, Edit2, Trash2, X, Check } from 'lucide-vue-next'
import { curriculumService } from '@/services/api/curriculum'
import { useToast } from '@/composables/useToast'
import type { CurriculumYear } from '@/types/curriculum'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'

const toast = useToast()
const loading = ref<boolean>(false)
const years = ref<CurriculumYear[]>([])
const search = ref<string>('')
const perPage = ref<number>(10)
const drawerOpen = ref<boolean>(false)
const editingId = ref<number | null>(null)
const saving = ref<boolean>(false)

const form = reactive({
  year: new Date().getFullYear(),
  name: '',
  start_date: '',
  end_date: '',
  status: 'active' as 'active' | 'inactive',
})

const filteredYears = computed(() => {
  if (!search.value) return years.value
  const q = search.value.toLowerCase()
  return years.value.filter(
    (y) =>
      y.name.toLowerCase().includes(q) ||
      y.year.toString().includes(q)
  )
})

async function fetchYears() {
  loading.value = true
  try {
    const res = await curriculumService.getYears()
    years.value = res.data || []
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal memuat tahun kurikulum')
  } finally {
    loading.value = false
  }
}

function openCreateDrawer() {
  editingId.value = null
  const currentYear = new Date().getFullYear()
  form.year = currentYear
  form.name = `Kurikulum ${currentYear}`
  form.start_date = `${currentYear}-08-01`
  form.end_date = `${currentYear + 1}-07-31`
  form.status = 'active'
  drawerOpen.value = true
}

function openEditDrawer(item: CurriculumYear) {
  editingId.value = item.id
  form.year = item.year
  form.name = item.name
  form.start_date = item.start_date
  form.end_date = item.end_date
  form.status = item.status
  drawerOpen.value = true
}

function closeDrawer() {
  drawerOpen.value = false
}

async function handleSave() {
  if (!form.year || !form.name || !form.start_date || !form.end_date) {
    toast.error('Mohon lengkapi seluruh field bertanda bintang (*)')
    return
  }

  saving.value = true
  try {
    if (editingId.value) {
      await curriculumService.updateYear(editingId.value, form)
      toast.success('Tahun kurikulum berhasil diperbarui')
    } else {
      await curriculumService.createYear(form)
      toast.success('Tahun kurikulum berhasil ditambahkan')
    }
    drawerOpen.value = false
    fetchYears()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal menyimpan tahun kurikulum')
  } finally {
    saving.value = false
  }
}

async function handleDelete(item: CurriculumYear) {
  if (!confirm(`Apakah Anda yakin ingin menghapus "${item.name}"?`)) return
  try {
    await curriculumService.deleteYear(item.id)
    toast.success('Tahun kurikulum berhasil dihapus')
    fetchYears()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal menghapus tahun kurikulum')
  }
}

function formatDateIndo(dateStr?: string) {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  return d.toLocaleDateString('id-ID', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  })
}

onMounted(() => {
  fetchYears()
})
</script>

<template>
  <PageContainer>
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div>
        <nav class="flex items-center gap-1.5 text-xs text-slate-400 mb-1">
          <span>Akademik</span>
        </nav>
        <h1 class="text-xl font-bold text-slate-900">Tahun Kurikulum</h1>
        <p class="text-xs text-slate-500 mt-0.5">Manajemen Tahun Kurikulum</p>
      </div>

      <Button
        variant="primary"
        size="sm"
        class="bg-brand-700 hover:bg-brand-800 text-white flex items-center gap-1.5 self-start sm:self-auto shadow-2xs"
        @click="openCreateDrawer"
      >
        <Plus class="w-4 h-4" />
        Tambah Data
      </Button>
    </div>

    <!-- Main Card -->
    <Card class="border border-slate-200/80 shadow-2xs overflow-hidden">
      <!-- Toolbar -->
      <div class="p-4 border-b border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 bg-slate-50/50">
        <div class="flex items-center gap-2 w-full sm:w-auto text-xs text-slate-600">
          <span>Baris</span>
          <select
            v-model="perPage"
            class="px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg text-xs font-medium focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600 outline-none"
          >
            <option :value="10">10</option>
            <option :value="25">25</option>
            <option :value="50">50</option>
          </select>
        </div>

        <div class="flex items-center gap-2 w-full sm:w-72">
          <div class="relative flex-1">
            <Input
              v-model="search"
              placeholder="Cari data..."
              class="w-full text-xs pr-8"
            />
          </div>
          <Button variant="primary" size="sm" class="bg-brand-700 hover:bg-brand-800 text-white text-xs px-3">
            Cari
          </Button>
        </div>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-100/80 border-b border-slate-200/80 text-slate-600 font-bold uppercase tracking-wider text-3xs">
              <th class="py-3 px-4 w-12 text-center">NO</th>
              <th class="py-3 px-4">TAHUN</th>
              <th class="py-3 px-4">NAMA TAHUN KURIKULUM</th>
              <th class="py-3 px-4">AWAL TAHUN KURIKULUM</th>
              <th class="py-3 px-4">AKHIR TAHUN KURIKULUM</th>
              <th class="py-3 px-4 w-28 text-center">AKSI</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <tr v-if="loading" class="hover:bg-transparent">
              <td colspan="6" class="py-12 text-center text-slate-400">
                Memuat data tahun kurikulum...
              </td>
            </tr>
            <tr v-else-if="filteredYears.length === 0" class="hover:bg-transparent">
              <td colspan="6" class="py-12 text-center text-slate-400">
                Belum ada data tahun kurikulum
              </td>
            </tr>
            <tr
              v-for="(item, idx) in filteredYears.slice(0, perPage)"
              :key="item.id"
              class="hover:bg-slate-50/80 transition-colors"
            >
              <td class="py-3.5 px-4 text-center font-medium text-slate-500">
                {{ idx + 1 }}
              </td>
              <td class="py-3.5 px-4 font-bold text-slate-900">
                {{ item.year }}
              </td>
              <td class="py-3.5 px-4 font-medium text-slate-800">
                {{ item.name }}
              </td>
              <td class="py-3.5 px-4 text-slate-600">
                {{ formatDateIndo(item.start_date) }}
              </td>
              <td class="py-3.5 px-4 text-slate-600">
                {{ formatDateIndo(item.end_date) }}
              </td>
              <td class="py-3.5 px-4">
                <div class="flex items-center justify-center gap-1.5">
                  <button
                    type="button"
                    class="p-1.5 text-brand-600 hover:bg-brand-50 rounded-md border border-brand-200 transition-colors"
                    title="Edit Tahun Kurikulum"
                    @click="openEditDrawer(item)"
                  >
                    <Edit2 class="w-3.5 h-3.5 text-rose-600" />
                  </button>
                  <button
                    type="button"
                    class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-md border border-rose-200 transition-colors"
                    title="Hapus Tahun Kurikulum"
                    @click="handleDelete(item)"
                  >
                    <Trash2 class="w-3.5 h-3.5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Footer -->
      <div class="p-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500 bg-white">
        <span>Menampilkan 1 hingga {{ Math.min(perPage, filteredYears.length) }} dari total {{ filteredYears.length }} data</span>
        <div class="flex items-center gap-1">
          <span class="px-2.5 py-1 rounded bg-brand-700 text-white font-bold">1</span>
        </div>
      </div>
    </Card>

    <!-- Slide-over Drawer -->
    <div
      v-if="drawerOpen"
      class="fixed inset-0 z-50 flex justify-end bg-slate-900/40 backdrop-blur-xs transition-opacity"
      @click.self="closeDrawer"
    >
      <div class="w-full max-w-md bg-white shadow-2xl h-full flex flex-col animate-slide-left">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
          <div>
            <h3 class="text-sm font-bold text-slate-900">
              {{ editingId ? 'Edit Tahun Kurikulum' : 'Tambah Tahun Kurikulum' }}
            </h3>
            <p class="text-3xs text-slate-500 mt-0.5">Kelola rentang tahun pelaksanaan kurikulum</p>
          </div>
          <button
            type="button"
            class="p-1 rounded-md text-slate-400 hover:text-slate-600 hover:bg-slate-100"
            @click="closeDrawer"
          >
            <X class="w-4 h-4" />
          </button>
        </div>

        <div class="p-5 overflow-y-auto flex-1 space-y-4 text-xs">
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Tahun Kurikulum <span class="text-rose-500">*</span>
            </label>
            <Input
              v-model="form.year"
              type="number"
              placeholder="Contoh: 2026"
              required
            />
          </div>

          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Nama Tahun Kurikulum <span class="text-rose-500">*</span>
            </label>
            <Input
              v-model="form.name"
              placeholder="Contoh: Kurikulum 2026"
              required
            />
          </div>

          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Awal Tahun Kurikulum <span class="text-rose-500">*</span>
            </label>
            <Input
              v-model="form.start_date"
              type="date"
              required
            />
          </div>

          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Akhir Tahun Kurikulum <span class="text-rose-500">*</span>
            </label>
            <Input
              v-model="form.end_date"
              type="date"
              required
            />
          </div>

          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Status</label>
            <select
              v-model="form.status"
              class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600 outline-none"
            >
              <option value="active">Aktif</option>
              <option value="inactive">Nonaktif</option>
            </select>
          </div>
        </div>

        <div class="p-4 border-t border-slate-100 bg-slate-50/50 flex items-center justify-end gap-2">
          <Button variant="outline" size="sm" @click="closeDrawer">
            Batal
          </Button>
          <Button
            variant="primary"
            size="sm"
            :loading="saving"
            class="bg-brand-700 hover:bg-brand-800 text-white flex items-center gap-1.5"
            @click="handleSave"
          >
            <Check class="w-4 h-4" />
            Simpan Data
          </Button>
        </div>
      </div>
    </div>
  </PageContainer>
</template>
