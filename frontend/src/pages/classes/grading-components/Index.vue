<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { Plus, Edit2, Trash2, X, Check, Filter } from 'lucide-vue-next'
import { lectureService } from '@/services/api/lecture'
import { useToast } from '@/composables/useToast'
import type { GradingComponent } from '@/types/lecture'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'

const toast = useToast()
const loading = ref<boolean>(false)
const components = ref<GradingComponent[]>([])
const search = ref<string>('')
const perPage = ref<number>(10)
const drawerOpen = ref<boolean>(false)
const editingId = ref<number | null>(null)
const saving = ref<boolean>(false)

const form = reactive({
  name: '',
  short_name: '',
  evaluation_method: 'Aktivitas Partisipatif',
  component_group: '--',
  default_weight: null as number | null,
  status: 'active' as 'active' | 'inactive',
})

const filteredComponents = computed(() => {
  if (!search.value) return components.value
  const q = search.value.toLowerCase()
  return components.value.filter(
    (c) =>
      c.name.toLowerCase().includes(q) ||
      c.short_name.toLowerCase().includes(q) ||
      c.evaluation_method.toLowerCase().includes(q)
  )
})

async function fetchComponents() {
  loading.value = true
  try {
    const res = await lectureService.getGradingComponents()
    components.value = res.data || []
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal memuat unsur nilai')
  } finally {
    loading.value = false
  }
}

function openCreateDrawer() {
  editingId.value = null
  form.name = ''
  form.short_name = ''
  form.evaluation_method = 'Aktivitas Partisipatif'
  form.component_group = '--'
  form.default_weight = null
  form.status = 'active'
  drawerOpen.value = true
}

function openEditDrawer(item: GradingComponent) {
  editingId.value = item.id
  form.name = item.name
  form.short_name = item.short_name
  form.evaluation_method = item.evaluation_method
  form.component_group = item.component_group || '--'
  form.default_weight = item.default_weight || null
  form.status = item.status
  drawerOpen.value = true
}

function closeDrawer() {
  drawerOpen.value = false
}

async function handleSave() {
  if (!form.name || !form.short_name || !form.evaluation_method) {
    toast.error('Mohon lengkapi isian wajib bertanda bintang (*)')
    return
  }

  saving.value = true
  try {
    if (editingId.value) {
      await lectureService.updateGradingComponent(editingId.value, form)
      toast.success('Unsur nilai berhasil diperbarui')
    } else {
      await lectureService.createGradingComponent(form)
      toast.success('Unsur nilai berhasil ditambahkan')
    }
    drawerOpen.value = false
    fetchComponents()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal menyimpan unsur nilai')
  } finally {
    saving.value = false
  }
}

async function handleDelete(item: GradingComponent) {
  if (!confirm(`Apakah Anda yakin ingin menghapus "${item.name}"?`)) return
  try {
    await lectureService.deleteGradingComponent(item.id)
    toast.success('Unsur nilai berhasil dihapus')
    fetchComponents()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal menghapus unsur nilai')
  }
}

onMounted(() => {
  fetchComponents()
})
</script>

<template>
  <PageContainer>
    <!-- Header (Matching Image 3) -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div>
        <nav class="flex items-center gap-1.5 text-xs text-slate-400 mb-1">
          <span>AKADEMIK</span>
        </nav>
        <h1 class="text-xl font-bold text-slate-900">Unsur Nilai</h1>
        <p class="text-xs text-slate-500 mt-0.5">Manajemen Unsur Nilai</p>
      </div>

      <Button
        variant="primary"
        size="sm"
        class="bg-brand-700 hover:bg-brand-800 text-white flex items-center gap-1.5 self-start sm:self-auto shadow-2xs font-semibold"
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

        <div class="flex items-center gap-2 w-full sm:w-auto">
          <Button variant="outline" size="sm" class="text-xs flex items-center gap-1 text-slate-700 bg-white">
            <Filter class="w-3.5 h-3.5 text-slate-500" />
            Filter
          </Button>

          <div class="relative w-48 sm:w-64">
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

      <!-- Table (Matching Image 3) -->
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-100/80 border-b border-slate-200/80 text-slate-600 font-bold uppercase tracking-wider text-3xs">
              <th class="py-3 px-4 w-12 text-center">NO</th>
              <th class="py-3 px-4">NAMA UNSUR</th>
              <th class="py-3 px-4">NAMA SINGKAT</th>
              <th class="py-3 px-4">METODE EVALUASI</th>
              <th class="py-3 px-4">KELOMPOK UNSUR</th>
              <th class="py-3 px-4 w-28 text-center">AKSI</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <tr v-if="loading" class="hover:bg-transparent">
              <td colspan="6" class="py-12 text-center text-slate-400">
                Memuat data unsur nilai...
              </td>
            </tr>
            <tr v-else-if="filteredComponents.length === 0" class="hover:bg-transparent">
              <td colspan="6" class="py-12 text-center text-slate-400">
                Belum ada data unsur nilai
              </td>
            </tr>
            <tr
              v-for="(item, idx) in filteredComponents.slice(0, perPage)"
              :key="item.id"
              class="hover:bg-slate-50/80 transition-colors"
            >
              <td class="py-3.5 px-4 text-center font-medium text-slate-500">
                {{ idx + 1 }}
              </td>
              <td class="py-3.5 px-4 font-bold text-slate-900">
                {{ item.name }}
              </td>
              <td class="py-3.5 px-4 font-medium text-slate-800">
                {{ item.short_name }}
              </td>
              <td class="py-3.5 px-4 text-slate-600">
                {{ item.evaluation_method }}
              </td>
              <td class="py-3.5 px-4 text-slate-600">
                {{ item.component_group || '--' }}
              </td>
              <td class="py-3.5 px-4">
                <div class="flex items-center justify-center gap-1.5">
                  <button
                    type="button"
                    class="p-1.5 text-brand-600 hover:bg-brand-50 rounded-md border border-brand-200 transition-colors"
                    title="Edit Unsur"
                    @click="openEditDrawer(item)"
                  >
                    <Edit2 class="w-3.5 h-3.5 text-rose-600" />
                  </button>
                  <button
                    type="button"
                    class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-md border border-rose-200 transition-colors"
                    title="Hapus Unsur"
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
        <span>Menampilkan 1 hingga {{ Math.min(perPage, filteredComponents.length) }} dari total {{ filteredComponents.length }} data</span>
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
              {{ editingId ? 'Edit Unsur Nilai' : 'Tambah Unsur Nilai' }}
            </h3>
            <p class="text-3xs text-slate-500 mt-0.5">Komponen penilaian OBE & standar rubrik evaluasi</p>
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
              Nama Unsur Nilai <span class="text-rose-500">*</span>
            </label>
            <Input
              v-model="form.name"
              placeholder="Contoh: Praktek 1 / Quiz / Ujian Tengah Semester"
              required
            />
          </div>

          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Nama Singkat <span class="text-rose-500">*</span>
            </label>
            <Input
              v-model="form.short_name"
              placeholder="Contoh: Praktek1 / UTS / UAS"
              required
            />
          </div>

          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Metode Evaluasi <span class="text-rose-500">*</span>
            </label>
            <select
              v-model="form.evaluation_method"
              class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600 outline-none"
            >
              <option value="Aktivitas Partisipatif">Aktivitas Partisipatif</option>
              <option value="Hasil Proyek">Hasil Proyek</option>
              <option value="Kognitif/Pengetahuan - Ujian Tengah Semester">Kognitif/Pengetahuan - Ujian Tengah Semester</option>
              <option value="Kognitif/Pengetahuan - Ujian Akhir Semester">Kognitif/Pengetahuan - Ujian Akhir Semester</option>
              <option value="Tugas Terstruktur">Tugas Terstruktur</option>
              <option value="Unjuk Kerja / Praktik">Unjuk Kerja / Praktik</option>
            </select>
          </div>

          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Kelompok Unsur
            </label>
            <select
              v-model="form.component_group"
              class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600 outline-none"
            >
              <option value="--">-- Tidak Dikelompokkan --</option>
              <option value="Presensi">Presensi</option>
              <option value="Tugas">Tugas</option>
              <option value="Kuis">Kuis</option>
              <option value="Ujian">Ujian</option>
              <option value="Praktikum">Praktikum</option>
            </select>
          </div>

          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Bobot Default (%) <span class="text-slate-400 font-normal">(Opsional)</span>
            </label>
            <Input
              v-model.number="form.default_weight"
              type="number"
              min="0"
              max="100"
              placeholder="Contoh: 20"
            />
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
