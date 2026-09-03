<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { Plus, Edit2, Trash2, X, Check, Filter } from 'lucide-vue-next'
import { lectureService } from '@/services/api/lecture'
import { useToast } from '@/composables/useToast'
import type { LectureSessionType } from '@/types/lecture'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'

const toast = useToast()
const loading = ref<boolean>(false)
const types = ref<LectureSessionType[]>([])
const search = ref<string>('')
const perPage = ref<number>(10)
const drawerOpen = ref<boolean>(false)
const editingId = ref<number | null>(null)
const saving = ref<boolean>(false)

const form = reactive({
  name: '',
  short_name: '',
  category: 'Kuliah',
  credit_type: 'Tatap Muka',
  counts_attendance: true,
  status: 'active' as 'active' | 'inactive',
})

const filteredTypes = computed(() => {
  if (!search.value) return types.value
  const q = search.value.toLowerCase()
  return types.value.filter(
    (t) =>
      t.name.toLowerCase().includes(q) ||
      t.short_name.toLowerCase().includes(q) ||
      t.category.toLowerCase().includes(q)
  )
})

async function fetchTypes() {
  loading.value = true
  try {
    const res = await lectureService.getSessionTypes()
    types.value = res.data || []
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal memuat jenis sesi perkuliahan')
  } finally {
    loading.value = false
  }
}

function openCreateDrawer() {
  editingId.value = null
  form.name = ''
  form.short_name = ''
  form.category = 'Kuliah'
  form.credit_type = 'Tatap Muka'
  form.counts_attendance = true
  form.status = 'active'
  drawerOpen.value = true
}

function openEditDrawer(item: LectureSessionType) {
  editingId.value = item.id
  form.name = item.name
  form.short_name = item.short_name
  form.category = item.category
  form.credit_type = item.credit_type
  form.counts_attendance = item.counts_attendance
  form.status = item.status
  drawerOpen.value = true
}

function closeDrawer() {
  drawerOpen.value = false
}

async function handleSave() {
  if (!form.name || !form.short_name || !form.category) {
    toast.error('Mohon lengkapi isian wajib bertanda bintang (*)')
    return
  }

  saving.value = true
  try {
    if (editingId.value) {
      await lectureService.updateSessionType(editingId.value, form)
      toast.success('Jenis sesi perkuliahan berhasil diperbarui')
    } else {
      await lectureService.createSessionType(form)
      toast.success('Jenis sesi perkuliahan berhasil ditambahkan')
    }
    drawerOpen.value = false
    fetchTypes()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal menyimpan jenis sesi')
  } finally {
    saving.value = false
  }
}

async function handleDelete(item: LectureSessionType) {
  if (!confirm(`Apakah Anda yakin ingin menghapus "${item.name}"?`)) return
  try {
    await lectureService.deleteSessionType(item.id)
    toast.success('Jenis sesi perkuliahan berhasil dihapus')
    fetchTypes()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal menghapus jenis sesi')
  }
}

onMounted(() => {
  fetchTypes()
})
</script>

<template>
  <PageContainer>
    <!-- Header (Matching Image 1) -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div>
        <nav class="flex items-center gap-1.5 text-xs text-slate-400 mb-1">
          <span>AKADEMIK</span>
        </nav>
        <h1 class="text-xl font-bold text-slate-900">Jenis Sesi Perkuliahan</h1>
        <p class="text-xs text-slate-500 mt-0.5">Manajemen Jenis Sesi Perkuliahan</p>
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

      <!-- Table (Matching Image 1) -->
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-100/80 border-b border-slate-200/80 text-slate-600 font-bold uppercase tracking-wider text-3xs">
              <th class="py-3 px-4 w-12 text-center">NO</th>
              <th class="py-3 px-4">NAMA JENIS SESI PERKULIAHAN</th>
              <th class="py-3 px-4">NAMA SINGKAT</th>
              <th class="py-3 px-4">KATEGORI</th>
              <th class="py-3 px-4">JENIS SKS</th>
              <th class="py-3 px-4 text-center">TERHITUNG PRESENSI</th>
              <th class="py-3 px-4 w-28 text-center">AKSI</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <tr v-if="loading" class="hover:bg-transparent">
              <td colspan="7" class="py-12 text-center text-slate-400">
                Memuat data jenis sesi perkuliahan...
              </td>
            </tr>
            <tr v-else-if="filteredTypes.length === 0" class="hover:bg-transparent">
              <td colspan="7" class="py-12 text-center text-slate-400">
                Belum ada data jenis sesi perkuliahan
              </td>
            </tr>
            <tr
              v-for="(item, idx) in filteredTypes.slice(0, perPage)"
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
                {{ item.category }}
              </td>
              <td class="py-3.5 px-4 text-slate-600">
                {{ item.credit_type }}
              </td>
              <td class="py-3.5 px-4 text-center">
                <span
                  class="inline-flex items-center justify-center px-4 py-1 rounded text-3xs font-bold text-white shadow-2xs"
                  :class="item.counts_attendance ? 'bg-emerald-600' : 'bg-slate-400'"
                >
                  {{ item.counts_attendance ? 'Ya' : 'Tidak' }}
                </span>
              </td>
              <td class="py-3.5 px-4">
                <div class="flex items-center justify-center gap-1.5">
                  <button
                    type="button"
                    class="p-1.5 text-brand-600 hover:bg-brand-50 rounded-md border border-brand-200 transition-colors"
                    title="Edit Jenis Sesi"
                    @click="openEditDrawer(item)"
                  >
                    <Edit2 class="w-3.5 h-3.5 text-rose-600" />
                  </button>
                  <button
                    type="button"
                    class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-md border border-rose-200 transition-colors"
                    title="Hapus Jenis Sesi"
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
        <span>Menampilkan 1 hingga {{ Math.min(perPage, filteredTypes.length) }} dari total {{ filteredTypes.length }} data</span>
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
              {{ editingId ? 'Edit Jenis Sesi Perkuliahan' : 'Tambah Jenis Sesi Perkuliahan' }}
            </h3>
            <p class="text-3xs text-slate-500 mt-0.5">Definisi kategori dan sifat presensi sesi perkuliahan</p>
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
              Nama Jenis Sesi Perkuliahan <span class="text-rose-500">*</span>
            </label>
            <Input
              v-model="form.name"
              placeholder="Contoh: Perkuliahan / Praktikum"
              required
            />
          </div>

          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Nama Singkat <span class="text-rose-500">*</span>
            </label>
            <Input
              v-model="form.short_name"
              placeholder="Contoh: Kuliah / UTS / UAS"
              required
            />
          </div>

          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Kategori <span class="text-rose-500">*</span>
            </label>
            <select
              v-model="form.category"
              class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600 outline-none"
            >
              <option value="Kuliah">Kuliah</option>
              <option value="UTS">UTS</option>
              <option value="UAS">UAS</option>
              <option value="Praktikum">Praktikum</option>
              <option value="Seminar">Seminar</option>
              <option value="Responsi">Responsi</option>
            </select>
          </div>

          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Jenis SKS <span class="text-rose-500">*</span>
            </label>
            <select
              v-model="form.credit_type"
              class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600 outline-none"
            >
              <option value="Tatap Muka">Tatap Muka</option>
              <option value="Praktikum">Praktikum</option>
              <option value="Praktik Lapangan">Praktik Lapangan</option>
              <option value="Simulasi">Simulasi</option>
            </select>
          </div>

          <div class="pt-2">
            <label class="block font-semibold text-slate-700 mb-1.5">
              Terhitung Presensi
            </label>
            <label class="inline-flex items-center gap-2 cursor-pointer">
              <input
                v-model="form.counts_attendance"
                type="checkbox"
                class="sr-only peer"
              />
              <div class="w-9 h-5 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-600 relative"></div>
              <span class="text-xs text-slate-600 font-medium">
                {{ form.counts_attendance ? 'Ya (Presensi Mahasiswa Dihitung)' : 'Tidak' }}
              </span>
            </label>
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
