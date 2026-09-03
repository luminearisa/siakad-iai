<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { Plus, Edit2, Trash2, X, Check } from 'lucide-vue-next'
import { lectureService } from '@/services/api/lecture'
import { useToast } from '@/composables/useToast'
import type { LectureProgram } from '@/types/lecture'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Textarea from '@/components/ui/Textarea.vue'

const toast = useToast()
const loading = ref<boolean>(false)
const programs = ref<LectureProgram[]>([])
const search = ref<string>('')
const perPage = ref<number>(10)
const drawerOpen = ref<boolean>(false)
const editingId = ref<number | null>(null)
const saving = ref<boolean>(false)

const form = reactive({
  name: '',
  description: '',
  status: 'active' as 'active' | 'inactive',
})

const filteredPrograms = computed(() => {
  if (!search.value) return programs.value
  const q = search.value.toLowerCase()
  return programs.value.filter(
    (p) =>
      p.name.toLowerCase().includes(q) ||
      (p.description && p.description.toLowerCase().includes(q))
  )
})

async function fetchPrograms() {
  loading.value = true
  try {
    const res = await lectureService.getPrograms()
    programs.value = res.data || []
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal memuat program kuliah')
  } finally {
    loading.value = false
  }
}

function openCreateDrawer() {
  editingId.value = null
  form.name = ''
  form.description = ''
  form.status = 'active'
  drawerOpen.value = true
}

function openEditDrawer(item: LectureProgram) {
  editingId.value = item.id
  form.name = item.name
  form.description = item.description || ''
  form.status = item.status
  drawerOpen.value = true
}

function closeDrawer() {
  drawerOpen.value = false
}

async function handleSave() {
  if (!form.name) {
    toast.error('Nama Program Kuliah wajib diisi (*)')
    return
  }

  saving.value = true
  try {
    if (editingId.value) {
      await lectureService.updateProgram(editingId.value, form)
      toast.success('Program kuliah berhasil diperbarui')
    } else {
      await lectureService.createProgram(form)
      toast.success('Program kuliah berhasil ditambahkan')
    }
    drawerOpen.value = false
    fetchPrograms()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal menyimpan program kuliah')
  } finally {
    saving.value = false
  }
}

async function handleDelete(item: LectureProgram) {
  if (!confirm(`Apakah Anda yakin ingin menghapus "${item.name}"?`)) return
  try {
    await lectureService.deleteProgram(item.id)
    toast.success('Program kuliah berhasil dihapus')
    fetchPrograms()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal menghapus program kuliah')
  }
}

onMounted(() => {
  fetchPrograms()
})
</script>

<template>
  <PageContainer>
    <!-- Header (Matching Image 2) -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div>
        <nav class="flex items-center gap-1.5 text-xs text-slate-400 mb-1">
          <span>AKADEMIK</span>
        </nav>
        <h1 class="text-xl font-bold text-slate-900">Program Kuliah</h1>
        <p class="text-xs text-slate-500 mt-0.5">Kelola program kuliah di akademik Anda</p>
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

      <!-- Table (Matching Image 2) -->
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-100/80 border-b border-slate-200/80 text-slate-600 font-bold uppercase tracking-wider text-3xs">
              <th class="py-3 px-4 w-12 text-center">NO</th>
              <th class="py-3 px-4 w-1/3">NAMA PROGRAM KULIAH</th>
              <th class="py-3 px-4">KETERANGAN</th>
              <th class="py-3 px-4 w-28 text-center">AKSI</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <tr v-if="loading" class="hover:bg-transparent">
              <td colspan="4" class="py-12 text-center text-slate-400">
                Memuat data program kuliah...
              </td>
            </tr>
            <tr v-else-if="filteredPrograms.length === 0" class="hover:bg-transparent">
              <td colspan="4" class="py-12 text-center text-slate-400">
                Belum ada data program kuliah
              </td>
            </tr>
            <tr
              v-for="(item, idx) in filteredPrograms.slice(0, perPage)"
              :key="item.id"
              class="hover:bg-slate-50/80 transition-colors"
            >
              <td class="py-3.5 px-4 text-center font-medium text-slate-500">
                {{ idx + 1 }}
              </td>
              <td class="py-3.5 px-4 font-bold text-slate-900">
                {{ item.name }}
              </td>
              <td class="py-3.5 px-4 text-slate-600">
                {{ item.description || '--' }}
              </td>
              <td class="py-3.5 px-4">
                <div class="flex items-center justify-center gap-1.5">
                  <button
                    type="button"
                    class="p-1.5 text-brand-600 hover:bg-brand-50 rounded-md border border-brand-200 transition-colors"
                    title="Edit Program"
                    @click="openEditDrawer(item)"
                  >
                    <Edit2 class="w-3.5 h-3.5 text-rose-600" />
                  </button>
                  <button
                    type="button"
                    class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-md border border-rose-200 transition-colors"
                    title="Hapus Program"
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
        <span>Menampilkan 1 hingga {{ Math.min(perPage, filteredPrograms.length) }} dari total {{ filteredPrograms.length }} data</span>
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
              {{ editingId ? 'Edit Program Kuliah' : 'Tambah Program Kuliah' }}
            </h3>
            <p class="text-3xs text-slate-500 mt-0.5">Kelola jalur / ragam program perkuliahan mahasiswa</p>
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
              Nama Program Kuliah <span class="text-rose-500">*</span>
            </label>
            <Input
              v-model="form.name"
              placeholder="Contoh: Reguler Siang / Kelas Karyawan"
              required
            />
          </div>

          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Keterangan
            </label>
            <Textarea
              v-model="form.description"
              placeholder="Deskripsi peruntukan jalur program kuliah..."
              :rows="4"
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
