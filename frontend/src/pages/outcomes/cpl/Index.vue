<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Plus, Sparkles, Edit, Trash2, Search, CalendarDays, Layers } from 'lucide-vue-next'
import { outcomeService } from '@/services/api/outcomes'
import { academicService } from '@/services/api/academic'
import { useToast } from '@/composables/useToast'
import type { LearningOutcome } from '@/types/outcomes'
import type { StudyProgram } from '@/types/academic'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Badge from '@/components/ui/Badge.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import ConfirmModal from '@/components/feedback/ConfirmModal.vue'
import AiOutcomeGeneratorModal from '@/components/outcomes/AiOutcomeGeneratorModal.vue'

const router = useRouter()
const toast = useToast()
const loading = ref<boolean>(false)
const learningOutcomes = ref<LearningOutcome[]>([])
const studyPrograms = ref<StudyProgram[]>([])
const search = ref<string>('')
const perPage = ref<number>(10)
const selectedCategory = ref<string>('')
const selectedProdiFilter = ref<string>('')

// AI Modal State
const aiModalOpen = ref<boolean>(false)

// Delete State
const deleteModalOpen = ref<boolean>(false)
const deleting = ref<boolean>(false)
const itemToDelete = ref<LearningOutcome | null>(null)

async function loadData() {
  loading.value = true
  try {
    const [cplRes, prodiRes] = await Promise.all([
      outcomeService.listLearningOutcomes(),
      academicService.getStudyPrograms({ per_page: 100 }),
    ])
    learningOutcomes.value = cplRes.data || []
    studyPrograms.value = prodiRes.data || []
  } catch (err: any) {
    toast.error(err.message || 'Gagal memuat data Capaian Pembelajaran Lulusan.')
  } finally {
    loading.value = false
  }
}

function formatCategory(cat: string) {
  switch (cat) {
    case 'sikap':
      return 'Sikap'
    case 'pengetahuan':
      return 'Pengetahuan'
    case 'keterampilan_umum':
      return 'Keterampilan Umum'
    case 'keterampilan_khusus':
      return 'Keterampilan Khusus'
    default:
      return cat
  }
}

function getCategoryBadgeVariant(cat: string) {
  switch (cat) {
    case 'sikap':
      return 'primary'
    case 'pengetahuan':
      return 'info'
    case 'keterampilan_umum':
      return 'warning'
    case 'keterampilan_khusus':
      return 'success'
    default:
      return 'neutral'
  }
}

const filteredCpls = computed(() => {
  let list = learningOutcomes.value
  if (selectedCategory.value) {
    list = list.filter((c) => c.category === selectedCategory.value)
  }
  if (selectedProdiFilter.value) {
    list = list.filter((c) => String(c.study_program_id) === selectedProdiFilter.value)
  }
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    list = list.filter(
      (c) =>
        c.name.toLowerCase().includes(q) ||
        c.code.toLowerCase().includes(q) ||
        formatCategory(c.category).toLowerCase().includes(q)
    )
  }
  return list
})

const paginatedCpls = computed(() => {
  return filteredCpls.value.slice(0, perPage.value)
})

function openDeleteModal(item: LearningOutcome) {
  itemToDelete.value = item
  deleteModalOpen.value = true
}

async function handleDelete() {
  if (!itemToDelete.value) return
  deleting.value = true
  try {
    await outcomeService.deleteLearningOutcome(itemToDelete.value.id)
    toast.success(`CPL ${itemToDelete.value.code} berhasil dihapus.`)
    deleteModalOpen.value = false
    itemToDelete.value = null
    await loadData()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menghapus CPL.')
  } finally {
    deleting.value = false
  }
}

function handleAiSelect(suggestion: any) {
  router.push({
    path: '/outcomes/cpl/create',
    query: {
      code: suggestion.code,
      name: suggestion.name,
      category: suggestion.category,
      description: suggestion.description,
    },
  })
}

onMounted(() => {
  loadData()
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
            <span>Capaian Lulusan</span>
            <span>&rsaquo;</span>
            <span class="text-slate-900 font-semibold">Capaian Pembelajaran Lulusan (CPL)</span>
          </div>
          <h1 class="text-xl font-bold text-slate-900 tracking-tight">Capaian Pembelajaran Lulusan (CPL)</h1>
          <p class="text-xs text-slate-500 mt-0.5">Manajemen Capaian Pembelajaran Lulusan (CPL)</p>
        </div>

        <div class="flex items-center gap-2 self-start sm:self-auto">
          <!-- Button: Generate dengan AI -->
          <Button
            variant="outline"
            size="sm"
            class="gap-1.5 border-emerald-300 text-emerald-800 hover:bg-emerald-50"
            @click="aiModalOpen = true"
          >
            <Sparkles class="w-4 h-4 text-amber-500" />
            Generate dengan AI
          </Button>

          <!-- Button: Tambah Data -->
          <Button
            variant="primary"
            size="sm"
            class="gap-1.5 bg-brand-700 hover:bg-brand-800 text-white"
            @click="router.push('/outcomes/cpl/create')"
          >
            <Plus class="w-4 h-4" />
            Tambah Data
          </Button>
        </div>
      </div>

      <!-- Controls & Filter Bar matching Image 3 -->
      <Card class="p-3 bg-white border border-slate-200 rounded-xl shadow-2xs">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div class="flex items-center flex-wrap gap-2 text-xs text-slate-600">
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

            <!-- Kategori SN-Dikti Filter -->
            <select
              v-model="selectedCategory"
              class="border border-slate-300 rounded-md text-xs py-1 px-2.5 bg-white text-slate-800 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none"
            >
              <option value="">Semua Kategori</option>
              <option value="sikap">Sikap</option>
              <option value="pengetahuan">Pengetahuan</option>
              <option value="keterampilan_umum">Keterampilan Umum</option>
              <option value="keterampilan_khusus">Keterampilan Khusus</option>
            </select>

            <!-- Prodi Filter -->
            <select
              v-model="selectedProdiFilter"
              class="border border-slate-300 rounded-md text-xs py-1 px-2.5 bg-white text-slate-800 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none"
            >
              <option value="">Semua Program Studi</option>
              <option v-for="p in studyPrograms" :key="p.id" :value="String(p.id)">
                {{ p.name }}
              </option>
            </select>
          </div>

          <div class="relative w-full sm:w-64">
            <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input
              v-model="search"
              type="text"
              placeholder="Cari data..."
              class="w-full pl-8.5 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg text-slate-800 placeholder:text-slate-400 focus:bg-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none transition-all"
            />
          </div>
        </div>
      </Card>

      <!-- Table Card matching Image 3 -->
      <Card class="bg-white border border-slate-200 rounded-xl shadow-2xs overflow-hidden">
        <div v-if="loading" class="p-6 space-y-3">
          <Skeleton height="2.5rem" rounded="md" />
          <Skeleton height="2.5rem" rounded="md" />
          <Skeleton height="2.5rem" rounded="md" />
        </div>

        <div v-else-if="filteredCpls.length === 0" class="py-12 text-center text-slate-400 text-xs">
          <Layers class="w-8 h-8 text-slate-300 mx-auto mb-2" />
          <p class="font-bold text-slate-700">Belum ada data Capaian Pembelajaran Lulusan (CPL)</p>
          <p class="text-2xs text-slate-400 mt-0.5">Klik tombol "+ Tambah Data" atau "Generate dengan AI" untuk mulai merumuskan.</p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="bg-slate-50/80 border-b border-slate-200 text-3xs font-semibold text-slate-600 uppercase tracking-wider">
                <th class="py-3 px-4 w-12 text-center">NO</th>
                <th class="py-3 px-4 w-28">KODE &uarr;&darr;</th>
                <th class="py-3 px-4 min-w-[320px]">CAPAIAN PEMBELAJARAN &uarr;&darr;</th>
                <th class="py-3 px-4 w-36">KATEGORI</th>
                <th class="py-3 px-4 min-w-[180px]">PROGRAM STUDI</th>
                <th class="py-3 px-4 w-24 text-center">AKSI</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="(item, index) in paginatedCpls"
                :key="item.id"
                class="hover:bg-slate-50/70 transition-colors"
              >
                <td class="py-3 px-4 text-center font-semibold text-slate-500">
                  {{ index + 1 }}
                </td>
                <td class="py-3 px-4 font-mono font-bold text-slate-900">
                  {{ item.code }}
                </td>
                <td class="py-3 px-4 font-semibold text-slate-900 leading-relaxed">
                  {{ item.name }}
                </td>
                <td class="py-3 px-4">
                  <Badge :variant="getCategoryBadgeVariant(item.category)" size="xs">
                    {{ formatCategory(item.category) }}
                  </Badge>
                </td>
                <td class="py-3 px-4 text-slate-700 font-medium">
                  {{ item.study_program?.name || 'Semua Program Studi' }}
                </td>
                <td class="py-3 px-4 text-center">
                  <div class="flex items-center justify-center gap-1">
                    <button
                      type="button"
                      class="p-1.5 rounded-md text-slate-500 hover:text-brand-600 hover:bg-brand-50 transition-colors"
                      title="Edit CPL"
                      @click="router.push(`/outcomes/cpl/${item.id}/edit`)"
                    >
                      <Edit class="w-3.5 h-3.5" />
                    </button>
                    <button
                      type="button"
                      class="p-1.5 rounded-md text-slate-500 hover:text-rose-600 hover:bg-rose-50 transition-colors"
                      title="Hapus CPL"
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
          <span>Menampilkan 1 hingga {{ paginatedCpls.length }} dari total {{ filteredCpls.length }} data</span>
        </div>
      </Card>
    </div>

    <!-- AI Generator Modal -->
    <AiOutcomeGeneratorModal
      v-model:open="aiModalOpen"
      type="cpl"
      :study-programs="studyPrograms"
      @select="handleAiSelect"
    />

    <!-- Confirm Delete Modal -->
    <ConfirmModal
      :open="deleteModalOpen"
      title="Hapus CPL"
      :message="`Apakah Anda yakin ingin menghapus CPL ${itemToDelete?.code}?`"
      confirm-text="Ya, Hapus"
      variant="danger"
      :loading="deleting"
      @update:open="deleteModalOpen = $event"
      @confirm="handleDelete"
    />
  </PageContainer>
</template>
