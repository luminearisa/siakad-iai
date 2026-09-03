<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Plus, Calendar, Edit, Trash2, Search, CalendarDays } from 'lucide-vue-next'
import { academicService } from '@/services/api/academic'
import { useToast } from '@/composables/useToast'
import type { Semester } from '@/types/academic'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Badge from '@/components/ui/Badge.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import ConfirmModal from '@/components/feedback/ConfirmModal.vue'

const toast = useToast()
const loading = ref<boolean>(false)
const semesters = ref<Semester[]>([])
const search = ref<string>('')
const perPage = ref<number>(10)

// Delete Modal State
const deleteModalOpen = ref<boolean>(false)
const deleting = ref<boolean>(false)
const itemToDelete = ref<Semester | null>(null)

async function loadSemesters() {
  loading.value = true
  try {
    const res = await academicService.getSemesters({ per_page: 50 })
    semesters.value = res.data || []
  } catch (err: any) {
    toast.error(err.message || 'Gagal memuat data periode akademik.')
  } finally {
    loading.value = false
  }
}

const filteredSemesters = computed(() => {
  let list = semesters.value
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    list = list.filter((s) => s.name.toLowerCase().includes(q) || s.academic_year?.name.toLowerCase().includes(q))
  }
  return list.slice(0, perPage.value)
})

function formatDate(dateStr?: string): string {
  if (!dateStr) return '-'
  try {
    const d = new Date(dateStr)
    return d.toLocaleDateString('id-ID', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    })
  } catch {
    return dateStr
  }
}

function openDeleteModal(item: Semester) {
  itemToDelete.value = item
  deleteModalOpen.value = true
}

async function handleDelete() {
  if (!itemToDelete.value) return
  deleting.value = true
  try {
    await academicService.deleteSemester(itemToDelete.value.id)
    toast.success(`Periode ${itemToDelete.value.name} berhasil dihapus.`)
    deleteModalOpen.value = false
    itemToDelete.value = null
    await loadSemesters()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menghapus periode akademik.')
  } finally {
    deleting.value = false
  }
}

onMounted(() => {
  loadSemesters()
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
            <span class="text-slate-900 font-semibold">Periode Akademik</span>
          </div>
          <h1 class="text-xl font-bold text-slate-900 tracking-tight">Periode Akademik</h1>
          <p class="text-xs text-slate-500 mt-0.5">Manajemen Semester, Jadwal Perkuliahan, UTS, & UAS</p>
        </div>

        <router-link to="/academic/semesters/create">
          <Button
            variant="primary"
            size="sm"
            class="gap-1.5 bg-brand-700 hover:bg-brand-800 text-white"
          >
            <Plus class="w-4 h-4" />
            Tambah Periode Akademik
          </Button>
        </router-link>
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
              placeholder="Cari periode akademik..."
              class="w-full pl-8.5 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg text-slate-800 placeholder:text-slate-400 focus:bg-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none transition-all"
            />
          </div>
        </div>
      </Card>

      <!-- Table Card -->
      <Card class="bg-white border border-slate-200 rounded-xl shadow-2xs overflow-hidden">
        <div v-if="loading" class="p-6 space-y-3">
          <Skeleton height="2.5rem" rounded="md" />
          <Skeleton height="2.5rem" rounded="md" />
          <Skeleton height="2.5rem" rounded="md" />
        </div>

        <div v-else-if="filteredSemesters.length === 0" class="py-12 text-center text-slate-400 text-xs">
          <Calendar class="w-8 h-8 text-slate-300 mx-auto mb-2" />
          <p class="font-bold text-slate-700">Belum ada data Periode Akademik</p>
          <p class="text-2xs text-slate-400 mt-0.5">Klik tombol "+ Tambah Periode Akademik" untuk membuat periode semester baru.</p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="bg-slate-50/80 border-b border-slate-200 text-3xs font-semibold text-slate-600 uppercase tracking-wider">
                <th class="py-3 px-4 w-12 text-center">NO</th>
                <th class="py-3 px-4 min-w-[200px]">PERIODE AKADEMIK</th>
                <th class="py-3 px-4 w-28">TAHUN AJARAN</th>
                <th class="py-3 px-4 w-24">JENIS</th>
                <th class="py-3 px-4 min-w-[180px]">RENTANG PERIODE</th>
                <th class="py-3 px-4 min-w-[180px]">RENTANG KULIAH</th>
                <th class="py-3 px-4 min-w-[150px]">MIN KEHADIRAN</th>
                <th class="py-3 px-4 w-20 text-center">MINGGU</th>
                <th class="py-3 px-4 w-24 text-center">STATUS</th>
                <th class="py-3 px-4 w-24 text-center">AKSI</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="(item, idx) in filteredSemesters"
                :key="item.id"
                class="hover:bg-slate-50/70 transition-colors"
              >
                <td class="py-3 px-4 text-center font-mono text-3xs text-slate-400">
                  {{ idx + 1 }}
                </td>
                <td class="py-3 px-4 font-bold text-slate-900">
                  {{ item.name }}
                </td>
                <td class="py-3 px-4 font-mono font-medium text-slate-700">
                  {{ item.academic_year?.name || '-' }}
                </td>
                <td class="py-3 px-4">
                  <span class="capitalize text-slate-800 font-medium">
                    {{ item.type }}
                  </span>
                </td>
                <td class="py-3 px-4 text-slate-600 text-2xs">
                  <div>{{ formatDate(item.start_date) }} &ndash;</div>
                  <div>{{ formatDate(item.end_date) }}</div>
                </td>
                <td class="py-3 px-4 text-slate-600 text-2xs">
                  <template v-if="item.lecture_start_date">
                    <div>{{ formatDate(item.lecture_start_date) }} &ndash;</div>
                    <div>{{ formatDate(item.lecture_end_date) }}</div>
                  </template>
                  <span v-else class="text-slate-400">-</span>
                </td>
                <td class="py-3 px-4 text-2xs text-slate-700 font-mono">
                  <div>UTS: <span class="font-bold text-slate-900">{{ item.min_attendance_uts_percentage ?? 50 }}%</span></div>
                  <div>UAS: <span class="font-bold text-slate-900">{{ item.min_attendance_uas_percentage ?? 80 }}%</span></div>
                </td>
                <td class="py-3 px-4 text-center font-mono font-bold text-slate-900">
                  {{ item.total_teaching_weeks ?? 16 }}
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
                    <router-link
                      :to="`/academic/semesters/${item.id}/edit`"
                      class="p-1.5 rounded-md text-slate-500 hover:text-brand-600 hover:bg-brand-50 transition-colors"
                      title="Edit Periode"
                    >
                      <Edit class="w-3.5 h-3.5" />
                    </router-link>
                    <button
                      type="button"
                      class="p-1.5 rounded-md text-slate-500 hover:text-rose-600 hover:bg-rose-50 transition-colors"
                      title="Hapus Periode"
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
          <span>Menampilkan 1 hingga {{ filteredSemesters.length }} dari total {{ semesters.length }} data</span>
        </div>
      </Card>
    </div>

    <!-- Confirm Delete Modal -->
    <ConfirmModal
      :open="deleteModalOpen"
      title="Hapus Periode Akademik"
      :message="`Apakah Anda yakin ingin menghapus ${itemToDelete?.name}? Seluruh jadwal dan perkuliahan di semester ini akan terpengaruh.`"
      confirm-text="Ya, Hapus"
      variant="danger"
      :loading="deleting"
      @update:open="deleteModalOpen = $event"
      @confirm="handleDelete"
    />
  </PageContainer>
</template>
