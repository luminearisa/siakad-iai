<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Plus, Eye, Filter } from 'lucide-vue-next'
import { curriculumService } from '@/services/api/curriculum'
import { useToast } from '@/composables/useToast'
import type { Curriculum } from '@/types/curriculum'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'

const router = useRouter()
const toast = useToast()
const loading = ref<boolean>(false)
const curricula = ref<Curriculum[]>([])
const search = ref<string>('')
const perPage = ref<number>(10)

const filteredCurricula = computed(() => {
  if (!search.value) return curricula.value
  const q = search.value.toLowerCase()
  return curricula.value.filter(
    (c) =>
      c.name.toLowerCase().includes(q) ||
      c.code.toLowerCase().includes(q) ||
      (c.study_program?.name && c.study_program.name.toLowerCase().includes(q))
  )
})

async function fetchCurricula() {
  loading.value = true
  try {
    const res = await curriculumService.getCurricula({ per_page: 50 })
    curricula.value = res.data || []
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal memuat kurikulum program studi')
  } finally {
    loading.value = false
  }
}

function getTotalSubjects(item: Curriculum): number {
  if (!item.semesters) return 0
  return item.semesters.reduce((acc, sem) => acc + (sem.subjects?.length || 0), 0)
}

function getTotalCredits(item: Curriculum): number {
  if (!item.semesters) return item.total_credits || 0
  return item.semesters.reduce((acc, sem) => {
    const semCredits = sem.subjects?.reduce((sAcc, s) => sAcc + (s.effective_credits || s.credits_override || s.course?.credits || 0), 0) || 0
    return acc + semCredits
  }, 0)
}

onMounted(() => {
  fetchCurricula()
})
</script>

<template>
  <PageContainer>
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div>
        <h1 class="text-xl font-bold text-slate-900">Kurikulum Program Studi</h1>
        <p class="text-xs text-slate-500 mt-0.5">Manajemen Kurikulum Program Studi</p>
      </div>

      <div class="flex items-center gap-2 self-start sm:self-auto">
        <Button
          variant="primary"
          size="sm"
          class="bg-brand-700 hover:bg-brand-800 text-white flex items-center gap-1.5 shadow-2xs font-semibold"
          @click="router.push('/curricula/create')"
        >
          <Plus class="w-4 h-4" />
          Tambah Data
        </Button>
      </div>
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

      <!-- Table (Matching Image 4) -->
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-100/80 border-b border-slate-200/80 text-slate-600 font-bold uppercase tracking-wider text-3xs">
              <th class="py-3 px-4 w-12 text-center">NO</th>
              <th class="py-3 px-4">KURIKULUM</th>
              <th class="py-3 px-4">PROGRAM STUDI</th>
              <th class="py-3 px-4 text-center">JUMLAH MATA KULIAH</th>
              <th class="py-3 px-4 text-center">TOTAL SKS</th>
              <th class="py-3 px-4 w-24 text-center">AKSI</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <tr v-if="loading" class="hover:bg-transparent">
              <td colspan="6" class="py-12 text-center text-slate-400">
                Memuat data kurikulum program studi...
              </td>
            </tr>
            <tr v-else-if="filteredCurricula.length === 0" class="hover:bg-transparent">
              <td colspan="6" class="py-12 text-center text-slate-400">
                Belum ada data kurikulum program studi
              </td>
            </tr>
            <tr
              v-for="(item, idx) in filteredCurricula.slice(0, perPage)"
              :key="item.id"
              class="hover:bg-slate-50/80 transition-colors"
            >
              <td class="py-3.5 px-4 text-center font-medium text-slate-500">
                {{ idx + 1 }}
              </td>
              <td class="py-3.5 px-4 font-bold text-slate-900">
                {{ item.start_year || item.version || item.name }}
              </td>
              <td class="py-3.5 px-4 font-medium text-slate-800">
                <span class="font-bold text-slate-900">{{ item.study_program?.degree || 'S1' }} - {{ item.study_program?.name }}</span>
                <span v-if="item.code" class="text-3xs text-slate-400 ml-1.5 font-mono">({{ item.code }})</span>
              </td>
              <td class="py-3.5 px-4 text-center font-semibold text-slate-800">
                {{ getTotalSubjects(item) }}
              </td>
              <td class="py-3.5 px-4 text-center font-bold text-brand-700">
                {{ getTotalCredits(item) }}
              </td>
              <td class="py-3.5 px-4">
                <div class="flex items-center justify-center">
                  <button
                    type="button"
                    class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-md border border-rose-200 transition-colors"
                    title="Lihat Struktur Kurikulum"
                    @click="router.push(`/curriculum/study-programs/${item.id}`)"
                  >
                    <Eye class="w-4 h-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Footer -->
      <div class="p-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500 bg-white">
        <span>Menampilkan 1 hingga {{ Math.min(perPage, filteredCurricula.length) }} dari total {{ filteredCurricula.length }} data</span>
        <div class="flex items-center gap-1">
          <span class="px-2.5 py-1 rounded bg-brand-700 text-white font-bold">1</span>
        </div>
      </div>
    </Card>
  </PageContainer>
</template>
