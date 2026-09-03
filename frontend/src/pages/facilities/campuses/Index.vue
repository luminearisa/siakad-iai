<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { Plus, Edit2, Trash2, X, Check } from 'lucide-vue-next'
import { roomService } from '@/services/api/rooms'
import { useToast } from '@/composables/useToast'
import type { Campus } from '@/types/room'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Textarea from '@/components/ui/Textarea.vue'

const toast = useToast()
const loading = ref<boolean>(false)
const campuses = ref<Campus[]>([])
const search = ref<string>('')
const perPage = ref<number>(10)
const drawerOpen = ref<boolean>(false)
const editingId = ref<number | null>(null)
const saving = ref<boolean>(false)

const form = reactive({
  code: '',
  name: '',
  address: '',
  phone: '',
  status: 'active' as 'active' | 'inactive',
})

const filteredCampuses = computed(() => {
  if (!search.value) return campuses.value
  const q = search.value.toLowerCase()
  return campuses.value.filter(
    (c) =>
      c.name.toLowerCase().includes(q) ||
      c.code.toLowerCase().includes(q) ||
      (c.address && c.address.toLowerCase().includes(q))
  )
})

async function fetchCampuses() {
  loading.value = true
  try {
    const res = await roomService.getCampuses()
    campuses.value = res.data || []
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal memuat data kampus')
  } finally {
    loading.value = false
  }
}

function openCreateDrawer() {
  editingId.value = null
  form.code = ''
  form.name = ''
  form.address = ''
  form.phone = ''
  form.status = 'active'
  drawerOpen.value = true
}

function openEditDrawer(item: Campus) {
  editingId.value = item.id
  form.code = item.code
  form.name = item.name
  form.address = item.address || ''
  form.phone = item.phone || ''
  form.status = item.status || 'active'
  drawerOpen.value = true
}

function closeDrawer() {
  drawerOpen.value = false
}

async function handleSave() {
  if (!form.code || !form.name) {
    toast.error('Kode Kampus dan Nama Kampus wajib diisi (*)')
    return
  }

  saving.value = true
  try {
    if (editingId.value) {
      await roomService.updateCampus(editingId.value, form)
      toast.success('Data kampus berhasil diperbarui')
    } else {
      await roomService.createCampus(form)
      toast.success('Data kampus berhasil ditambahkan')
    }
    drawerOpen.value = false
    fetchCampuses()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal menyimpan data kampus')
  } finally {
    saving.value = false
  }
}

async function handleDelete(item: Campus) {
  if (!confirm(`Apakah Anda yakin ingin menghapus kampus "${item.name}"?`)) return
  try {
    await roomService.deleteCampus(item.id)
    toast.success('Kampus berhasil dihapus')
    fetchCampuses()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal menghapus kampus')
  }
}

onMounted(() => {
  fetchCampuses()
})
</script>

<template>
  <PageContainer>
    <!-- Header (Matching Image 1) -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div>
        <nav class="flex items-center gap-1.5 text-xs text-slate-400 mb-1">
          <span class="text-brand-600 font-medium">Akademik</span>
        </nav>
        <h1 class="text-xl font-bold text-slate-900">Kampus</h1>
        <p class="text-xs text-slate-500 mt-0.5">Referensi Kampus</p>
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

      <!-- Table (Matching Image 1) -->
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-100/80 border-b border-slate-200/80 text-slate-600 font-bold uppercase tracking-wider text-3xs">
              <th class="py-3 px-4 w-12 text-center">NO</th>
              <th class="py-3 px-4 w-40">KODE KAMPUS</th>
              <th class="py-3 px-4 w-1/3">NAMA KAMPUS</th>
              <th class="py-3 px-4">ALAMAT</th>
              <th class="py-3 px-4 w-40">TELEPON</th>
              <th class="py-3 px-4 w-28 text-center">AKSI</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <tr v-if="loading" class="hover:bg-transparent">
              <td colspan="6" class="py-12 text-center text-slate-400">
                Memuat data kampus...
              </td>
            </tr>
            <tr v-else-if="filteredCampuses.length === 0" class="hover:bg-transparent">
              <td colspan="6" class="py-12 text-center text-slate-400">
                Belum ada data kampus
              </td>
            </tr>
            <tr
              v-for="(item, idx) in filteredCampuses.slice(0, perPage)"
              :key="item.id"
              class="hover:bg-slate-50/80 transition-colors"
            >
              <td class="py-3.5 px-4 text-center font-medium text-slate-500">
                {{ idx + 1 }}
              </td>
              <td class="py-3.5 px-4 font-mono font-medium text-slate-900">
                {{ item.code }}
              </td>
              <td class="py-3.5 px-4 font-bold text-slate-900">
                {{ item.name }}
              </td>
              <td class="py-3.5 px-4 text-slate-600">
                {{ item.address || '--' }}
              </td>
              <td class="py-3.5 px-4 text-slate-600 font-mono">
                {{ item.phone || '--' }}
              </td>
              <td class="py-3.5 px-4">
                <div class="flex items-center justify-center gap-1.5">
                  <button
                    type="button"
                    class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-md border border-rose-200 transition-colors"
                    title="Edit Kampus"
                    @click="openEditDrawer(item)"
                  >
                    <Edit2 class="w-3.5 h-3.5" />
                  </button>
                  <button
                    type="button"
                    class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-md border border-rose-200 transition-colors"
                    title="Hapus Kampus"
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
        <span>Menampilkan 1 hingga {{ Math.min(perPage, filteredCampuses.length) }} dari total {{ filteredCampuses.length }} data</span>
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
              {{ editingId ? 'Edit Data Kampus' : 'Tambah Data Kampus' }}
            </h3>
            <p class="text-3xs text-slate-500 mt-0.5">Kelola lokasi kampus induk dan cabang perkuliahan</p>
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
              Kode Kampus <span class="text-rose-500">*</span>
            </label>
            <Input
              v-model="form.code"
              placeholder="Contoh: KMP-001 / KMP-03"
              required
            />
          </div>

          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Nama Kampus <span class="text-rose-500">*</span>
            </label>
            <Input
              v-model="form.name"
              placeholder="Contoh: Kampus Nusantara / Kampus Harmoni"
              required
            />
          </div>

          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Alamat Kampus
            </label>
            <Textarea
              v-model="form.address"
              placeholder="Alamat lengkap lokasi kampus..."
              :rows="3"
            />
          </div>

          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Nomor Telepon
            </label>
            <Input
              v-model="form.phone"
              placeholder="Contoh: 021-5551234"
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
