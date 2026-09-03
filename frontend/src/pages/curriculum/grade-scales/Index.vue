<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Plus, Edit2, Trash2 } from 'lucide-vue-next'
import { curriculumService } from '@/services/api/curriculum'
import { useToast } from '@/composables/useToast'
import type { GradeScale } from '@/types/curriculum'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'

const router = useRouter()
const toast = useToast()
const loading = ref<boolean>(false)
const scales = ref<GradeScale[]>([])
const search = ref<string>('')
const perPage = ref<number>(10)

const filteredScales = computed(() => {
  if (!search.value) return scales.value
  const q = search.value.toLowerCase()
  return scales.value.filter(
    (s) =>
      s.name.toLowerCase().includes(q) ||
      (s.description && s.description.toLowerCase().includes(q))
  )
})

async function fetchScales() {
  loading.value = true
  try {
    const res = await curriculumService.getGradeScales()
    scales.value = res.data || []
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal memuat data skala nilai')
  } finally {
    loading.value = false
  }
}

async function handleDelete(item: GradeScale) {
  if (!confirm(`Apakah Anda yakin ingin menghapus "${item.name}"?`)) return
  try {
    await curriculumService.deleteGradeScale(item.id)
    toast.success('Skala nilai berhasil dihapus')
    fetchScales()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal menghapus skala nilai')
  }
}

onMounted(() => {
  fetchScales()
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
        <h1 class="text-xl font-bold text-slate-900">Skala Nilai</h1>
        <p class="text-xs text-slate-500 mt-0.5">Manajemen Skala Nilai & Standar Bobot Huruf</p>
      </div>

      <Button
        variant="primary"
        size="sm"
        class="bg-brand-700 hover:bg-brand-800 text-white flex items-center gap-1.5 self-start sm:self-auto shadow-2xs font-semibold"
        @click="router.push('/curriculum/grade-scales/create')"
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
              <th class="py-3 px-4">NAMA SKALA NILAI</th>
              <th class="py-3 px-4">KETERANGAN</th>
              <th class="py-3 px-4">DISTRIBUSI NILAI (HURUF - BOBOT)</th>
              <th class="py-3 px-4 text-center">KURIKULUM PENGGUNA</th>
              <th class="py-3 px-4 w-28 text-center">AKSI</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <tr v-if="loading" class="hover:bg-transparent">
              <td colspan="6" class="py-12 text-center text-slate-400">
                Memuat data skala nilai...
              </td>
            </tr>
            <tr v-else-if="filteredScales.length === 0" class="hover:bg-transparent">
              <td colspan="6" class="py-12 text-center text-slate-400">
                Belum ada data skala nilai
              </td>
            </tr>
            <tr
              v-for="(item, idx) in filteredScales.slice(0, perPage)"
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
                <div class="flex flex-wrap gap-1">
                  <span
                    v-for="sub in item.items || []"
                    :key="sub.id || sub.grade_letter"
                    class="inline-flex items-center px-2 py-0.5 rounded text-3xs font-bold bg-slate-100 text-slate-700 border border-slate-200"
                  >
                    {{ sub.grade_letter }} ({{ Number(sub.grade_point).toFixed(2) }})
                  </span>
                </div>
              </td>
              <td class="py-3.5 px-4 text-center font-semibold text-slate-800">
                {{ item.curricula_count || 0 }}
              </td>
              <td class="py-3.5 px-4">
                <div class="flex items-center justify-center gap-1.5">
                  <button
                    type="button"
                    class="p-1.5 text-brand-600 hover:bg-brand-50 rounded-md border border-brand-200 transition-colors"
                    title="Edit Skala Nilai"
                    @click="router.push(`/curriculum/grade-scales/${item.id}/edit`)"
                  >
                    <Edit2 class="w-3.5 h-3.5 text-rose-600" />
                  </button>
                  <button
                    type="button"
                    class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-md border border-rose-200 transition-colors"
                    title="Hapus Skala Nilai"
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
        <span>Menampilkan 1 hingga {{ Math.min(perPage, filteredScales.length) }} dari total {{ filteredScales.length }} data</span>
        <div class="flex items-center gap-1">
          <span class="px-2.5 py-1 rounded bg-brand-700 text-white font-bold">1</span>
        </div>
      </div>
    </Card>
  </PageContainer>
</template>
