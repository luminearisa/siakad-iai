<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { Plus, BookOpen, Edit, Trash2, X, Check, Search, CalendarDays } from 'lucide-vue-next'
import { courseService } from '@/services/api/courses'
import { useToast } from '@/composables/useToast'
import type { CourseTypeItem } from '@/types/course'
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
const courseTypes = ref<CourseTypeItem[]>([])
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
const itemToDelete = ref<CourseTypeItem | null>(null)

async function loadCourseTypes() {
  loading.value = true
  try {
    const res = await courseService.listTypes()
    courseTypes.value = res.data || []
  } catch (err: any) {
    toast.error(err.message || 'Gagal memuat data jenis mata kuliah.')
  } finally {
    loading.value = false
  }
}

const filteredTypes = computed(() => {
  let list = courseTypes.value
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    list = list.filter((t) => t.name.toLowerCase().includes(q) || t.code.toLowerCase().includes(q))
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

function openEditDrawer(item: CourseTypeItem) {
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
      await courseService.updateType(selectedId.value, form)
      toast.success('Jenis mata kuliah berhasil diperbarui.')
    } else {
      await courseService.createType(form)
      toast.success('Jenis mata kuliah baru berhasil ditambahkan.')
    }
    drawerOpen.value = false
    await loadCourseTypes()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menyimpan jenis mata kuliah.')
  } finally {
    saving.value = false
  }
}

function openDeleteModal(item: CourseTypeItem) {
  itemToDelete.value = item
  deleteModalOpen.value = true
}

async function handleDelete() {
  if (!itemToDelete.value) return
  deleting.value = true
  try {
    await courseService.deleteType(itemToDelete.value.id)
    toast.success(`Jenis mata kuliah ${itemToDelete.value.name} berhasil dihapus.`)
    deleteModalOpen.value = false
    itemToDelete.value = null
    await loadCourseTypes()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menghapus jenis mata kuliah.')
  } finally {
    deleting.value = false
  }
}

onMounted(() => {
  loadCourseTypes()
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
            <span class="text-slate-900 font-semibold">Jenis Mata Kuliah</span>
          </div>
          <h1 class="text-xl font-bold text-slate-900 tracking-tight">Jenis Mata Kuliah</h1>
          <p class="text-xs text-slate-500 mt-0.5">Manajemen Jenis & Bentuk Perkuliahan (Tatap Muka, Praktikum, Hybrid, dsb.)</p>
        </div>

        <Button
          variant="primary"
          size="sm"
          class="gap-1.5 self-start sm:self-auto bg-brand-700 hover:bg-brand-800 text-white"
          @click="openCreateDrawer"
        >
          <Plus class="w-4 h-4" />
          Tambah Jenis Mata Kuliah
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
              placeholder="Cari jenis mata kuliah..."
              class="w-full pl-8.5 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg text-slate-800 placeholder:text-slate-400 focus:bg-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none transition-all"
            />
          </div>
        </div>
      </Card>

      <!-- Table Card (Persis Gambar 1) -->
      <Card class="bg-white border border-slate-200 rounded-xl shadow-2xs overflow-hidden">
        <div v-if="loading" class="p-6 space-y-3">
          <Skeleton height="2.5rem" rounded="md" />
          <Skeleton height="2.5rem" rounded="md" />
          <Skeleton height="2.5rem" rounded="md" />
        </div>

        <div v-else-if="filteredTypes.length === 0" class="py-12 text-center text-slate-400 text-xs">
          <BookOpen class="w-8 h-8 text-slate-300 mx-auto mb-2" />
          <p class="font-bold text-slate-700">Belum ada data Jenis Mata Kuliah</p>
          <p class="text-2xs text-slate-400 mt-0.5">Klik tombol "+ Tambah Jenis Mata Kuliah" untuk menambahkan data baru.</p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="bg-slate-50/80 border-b border-slate-200 text-3xs font-semibold text-slate-600 uppercase tracking-wider">
                <th class="py-3 px-4 w-28">KODE</th>
                <th class="py-3 px-4 min-w-[220px]">JENIS MATA KULIAH</th>
                <th class="py-3 px-4 min-w-[200px]">KETERANGAN</th>
                <th class="py-3 px-4 w-24 text-center">STATUS</th>
                <th class="py-3 px-4 w-24 text-center">AKSI</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="item in filteredTypes"
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
                      title="Edit Jenis Mata Kuliah"
                      @click="openEditDrawer(item)"
                    >
                      <Edit class="w-3.5 h-3.5" />
                    </button>
                    <button
                      type="button"
                      class="p-1.5 rounded-md text-slate-500 hover:text-rose-600 hover:bg-rose-50 transition-colors"
                      title="Hapus Jenis Mata Kuliah"
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
          <span>Menampilkan 1 hingga {{ filteredTypes.length }} dari total {{ courseTypes.length }} data</span>
        </div>
      </Card>
    </div>

    <!-- Side Drawer: Tambah / Edit Jenis Mata Kuliah (Persis Gambar 1) -->
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
                {{ isEditing ? 'Edit Jenis Mata Kuliah' : 'Tambah Jenis Mata Kuliah' }}
              </h2>
              <p class="text-2xs text-slate-500 mt-0.5">Kelola tipe dan format pelaksanaan perkuliahan</p>
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
                Kode Jenis Mata Kuliah <span class="text-rose-500">*</span>
              </label>
              <Input
                v-model="form.code"
                type="text"
                placeholder="Masukkan kode jenis mata kuliah (contoh: H, K, P, S)"
                required
              />
            </div>

            <div class="space-y-1.5">
              <label class="block text-xs font-semibold text-slate-800">
                Nama Jenis Mata Kuliah <span class="text-rose-500">*</span>
              </label>
              <Input
                v-model="form.name"
                type="text"
                placeholder="Masukkan nama jenis mata kuliah"
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
                placeholder="Keterangan singkat jenis perkuliahan..."
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
      title="Hapus Jenis Mata Kuliah"
      :message="`Apakah Anda yakin ingin menghapus ${itemToDelete?.name}? Mata kuliah yang menggunakan jenis ini dapat terpengaruh.`"
      confirm-text="Ya, Hapus"
      variant="danger"
      :loading="deleting"
      @update:open="deleteModalOpen = $event"
      @confirm="handleDelete"
    />
  </PageContainer>
</template>
