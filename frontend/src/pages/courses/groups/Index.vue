<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { Plus, BookMarked, Edit, Trash2, X, Check, Search, CalendarDays } from 'lucide-vue-next'
import { courseService } from '@/services/api/courses'
import { useToast } from '@/composables/useToast'
import type { CourseGroupItem } from '@/types/course'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Badge from '@/components/ui/Badge.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import ConfirmModal from '@/components/feedback/ConfirmModal.vue'

const toast = useToast()
const loading = ref<boolean>(false)
const saving = ref<boolean>(false)
const courseGroups = ref<CourseGroupItem[]>([])
const search = ref<string>('')
const perPage = ref<number>(10)

// Drawer / Modal State
const drawerOpen = ref<boolean>(false)
const isEditing = ref<boolean>(false)
const selectedId = ref<number | null>(null)

const form = reactive({
  code: '',
  name: '',
  description: '',
  status: 'active',
})

// Delete Modal State
const deleteModalOpen = ref<boolean>(false)
const deleting = ref<boolean>(false)
const itemToDelete = ref<CourseGroupItem | null>(null)

async function loadCourseGroups() {
  loading.value = true
  try {
    const res = await courseService.listGroups()
    courseGroups.value = res.data || []
  } catch (err: any) {
    toast.error(err.message || 'Gagal memuat data kelompok mata kuliah.')
  } finally {
    loading.value = false
  }
}

const filteredGroups = computed(() => {
  let list = courseGroups.value
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    list = list.filter((g) => g.name.toLowerCase().includes(q) || g.code.toLowerCase().includes(q))
  }
  return list.slice(0, perPage.value)
})

function openCreateDrawer() {
  isEditing.value = false
  selectedId.value = null
  form.code = ''
  form.name = ''
  form.description = ''
  form.status = 'active'
  drawerOpen.value = true
}

function openEditDrawer(item: CourseGroupItem) {
  isEditing.value = true
  selectedId.value = item.id
  form.code = item.code
  form.name = item.name
  form.description = item.description || ''
  form.status = item.status || 'active'
  drawerOpen.value = true
}

async function handleSave() {
  if (!form.code || !form.name) {
    toast.warning('Mohon lengkapi seluruh isian wajib bertanda bintang (*).')
    return
  }

  saving.value = true
  try {
    if (isEditing.value && selectedId.value) {
      await courseService.updateGroup(selectedId.value, form)
      toast.success('Kelompok mata kuliah berhasil diperbarui.')
    } else {
      await courseService.createGroup(form)
      toast.success('Kelompok mata kuliah baru berhasil ditambahkan.')
    }
    drawerOpen.value = false
    await loadCourseGroups()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menyimpan kelompok mata kuliah.')
  } finally {
    saving.value = false
  }
}

function openDeleteModal(item: CourseGroupItem) {
  itemToDelete.value = item
  deleteModalOpen.value = true
}

async function handleDelete() {
  if (!itemToDelete.value) return
  deleting.value = true
  try {
    await courseService.deleteGroup(itemToDelete.value.id)
    toast.success(`Kelompok mata kuliah ${itemToDelete.value.name} berhasil dihapus.`)
    deleteModalOpen.value = false
    itemToDelete.value = null
    await loadCourseGroups()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menghapus kelompok mata kuliah.')
  } finally {
    deleting.value = false
  }
}

onMounted(() => {
  loadCourseGroups()
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
            <span class="text-slate-900 font-semibold">Kelompok Mata Kuliah</span>
          </div>
          <h1 class="text-xl font-bold text-slate-900 tracking-tight">Kelompok Mata Kuliah</h1>
          <p class="text-xs text-slate-500 mt-0.5">Manajemen Kelompok & Klasifikasi Kurikulum (MPK, MKK, MKB, MPB, MBB, dsb.)</p>
        </div>

        <Button
          variant="primary"
          size="sm"
          class="gap-1.5 self-start sm:self-auto bg-brand-700 hover:bg-brand-800 text-white"
          @click="openCreateDrawer"
        >
          <Plus class="w-4 h-4" />
          Tambah Kelompok Mata Kuliah
        </Button>
      </div>

      <!-- Controls & Filter Bar -->
      <Card class="p-3 bg-white border border-slate-200 rounded-xl shadow-2xs">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div class="flex items-center gap-2 text-xs text-slate-600">
            <span>Baris</span>
            <select
              v-model.number="perPage"
              class="border border-slate-300 rounded-md text-xs py-1 px-2.5 bg-white text-slate-800 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none"
            >
              <option :value="10">10</option>
              <option :value="25">25</option>
              <option :value="50">50</option>
              <option :value="100">100</option>
            </select>
          </div>

          <div class="relative w-full sm:w-64">
            <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input
              v-model="search"
              type="text"
              placeholder="Cari kelompok mata kuliah..."
              class="w-full pl-8.5 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg text-slate-800 placeholder:text-slate-400 focus:bg-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none transition-all"
            />
          </div>
        </div>
      </Card>

      <!-- Table Card (Persis Gambar 2) -->
      <Card class="bg-white border border-slate-200 rounded-xl shadow-2xs overflow-hidden">
        <div v-if="loading" class="p-6 space-y-3">
          <Skeleton height="2.5rem" rounded="md" />
          <Skeleton height="2.5rem" rounded="md" />
          <Skeleton height="2.5rem" rounded="md" />
        </div>

        <div v-else-if="filteredGroups.length === 0" class="py-12 text-center text-slate-400 text-xs">
          <BookMarked class="w-8 h-8 text-slate-300 mx-auto mb-2" />
          <p class="font-bold text-slate-700">Belum ada data Kelompok Mata Kuliah</p>
          <p class="text-2xs text-slate-400 mt-0.5">Klik tombol "+ Tambah Kelompok Mata Kuliah" untuk menambahkan data baru.</p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="bg-slate-50/80 border-b border-slate-200 text-3xs font-semibold text-slate-600 uppercase tracking-wider">
                <th class="py-3 px-4 w-28">KODE</th>
                <th class="py-3 px-4 min-w-[280px]">KELOMPOK MATA KULIAH</th>
                <th class="py-3 px-4 min-w-[200px]">KETERANGAN</th>
                <th class="py-3 px-4 w-24 text-center">STATUS</th>
                <th class="py-3 px-4 w-24 text-center">AKSI</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="item in filteredGroups"
                :key="item.id"
                class="hover:bg-slate-50/70 transition-colors"
              >
                <td class="py-3 px-4 font-mono font-bold text-slate-900">
                  {{ item.code }}
                </td>
                <td class="py-3 px-4 font-semibold text-slate-900">
                  {{ item.name }}
                </td>
                <td class="py-3 px-4 text-slate-600 text-2xs">
                  {{ item.description || '-' }}
                </td>
                <td class="py-3 px-4 text-center">
                  <Badge
                    :variant="item.status === 'active' || item.status === 'Aktif' ? 'success' : 'neutral'"
                    size="xs"
                    dot
                  >
                    {{ item.status === 'active' || item.status === 'Aktif' ? 'Aktif' : 'Nonaktif' }}
                  </Badge>
                </td>
                <td class="py-3 px-4 text-center">
                  <div class="flex items-center justify-center gap-1">
                    <button
                      type="button"
                      class="p-1.5 rounded-md text-slate-500 hover:text-brand-600 hover:bg-brand-50 transition-colors"
                      title="Edit Kelompok Mata Kuliah"
                      @click="openEditDrawer(item)"
                    >
                      <Edit class="w-3.5 h-3.5" />
                    </button>
                    <button
                      type="button"
                      class="p-1.5 rounded-md text-slate-500 hover:text-rose-600 hover:bg-rose-50 transition-colors"
                      title="Hapus Kelompok Mata Kuliah"
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
          <span>Menampilkan 1 hingga {{ filteredGroups.length }} dari total {{ courseGroups.length }} data</span>
        </div>
      </Card>
    </div>

    <!-- Side Drawer: Tambah / Edit Kelompok Mata Kuliah (Persis Gambar 2) -->
    <div v-if="drawerOpen" class="fixed inset-0 z-50 overflow-hidden">
      <div
        class="absolute inset-0 bg-slate-900/40 backdrop-blur-xs transition-opacity"
        @click="drawerOpen = false"
      />

      <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
        <div class="w-screen max-w-md bg-white shadow-2xl flex flex-col">
          <div class="p-5 border-b border-slate-200 flex items-center justify-between bg-slate-50/50">
            <div>
              <h2 class="text-sm font-bold text-slate-900">
                {{ isEditing ? 'Edit Kelompok Mata Kuliah' : 'Tambah Kelompok Mata Kuliah' }}
              </h2>
              <p class="text-2xs text-slate-500 mt-0.5">Kelola klasifikasi dan rumpun mata kuliah</p>
            </div>
            <button
              type="button"
              class="p-1.5 rounded-md text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
              @click="drawerOpen = false"
            >
              <X class="w-4 h-4" />
            </button>
          </div>

          <form class="flex-1 overflow-y-auto p-5 space-y-4" @submit.prevent="handleSave">
            <div class="space-y-1.5">
              <label class="block text-xs font-semibold text-slate-800">
                Kode Kelompok Mata Kuliah <span class="text-rose-500">*</span>
              </label>
              <Input
                v-model="form.code"
                type="text"
                placeholder="Masukkan kode kelompok (contoh: A, B, C, MPK, MKK)"
                required
              />
            </div>

            <div class="space-y-1.5">
              <label class="block text-xs font-semibold text-slate-800">
                Nama Kelompok Mata Kuliah <span class="text-rose-500">*</span>
              </label>
              <Input
                v-model="form.name"
                type="text"
                placeholder="Masukkan nama kelompok mata kuliah"
                required
              />
            </div>

            <div class="space-y-1.5">
              <label class="block text-xs font-semibold text-slate-800">
                Keterangan
              </label>
              <textarea
                v-model="form.description"
                rows="3"
                placeholder="Keterangan singkat rumpun mata kuliah..."
                class="w-full text-xs py-2 px-3 border border-slate-300 rounded-lg text-slate-900 bg-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none"
              />
            </div>

            <div class="space-y-1.5">
              <label class="block text-xs font-semibold text-slate-800">
                Status
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
      title="Hapus Kelompok Mata Kuliah"
      :message="`Apakah Anda yakin ingin menghapus ${itemToDelete?.name}? Mata kuliah yang menggunakan kelompok ini dapat terpengaruh.`"
      confirm-text="Ya, Hapus"
      variant="danger"
      :loading="deleting"
      @update:open="deleteModalOpen = $event"
      @confirm="handleDelete"
    />
  </PageContainer>
</template>
