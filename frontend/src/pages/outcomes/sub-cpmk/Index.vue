<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Plus, Sparkles, Edit, Trash2, Search, CalendarDays, CheckCircle2 } from 'lucide-vue-next'
import { outcomeService } from '@/services/api/outcomes'
import { useToast } from '@/composables/useToast'
import type { SubCourseLearningOutcome, CourseLearningOutcome } from '@/types/outcomes'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import ConfirmModal from '@/components/feedback/ConfirmModal.vue'
import AiOutcomeGeneratorModal from '@/components/outcomes/AiOutcomeGeneratorModal.vue'

const router = useRouter()
const toast = useToast()
const loading = ref<boolean>(false)
const subCpmks = ref<SubCourseLearningOutcome[]>([])
const cpmks = ref<CourseLearningOutcome[]>([])
const search = ref<string>('')
const perPage = ref<number>(10)
const selectedCpmkFilter = ref<string>('')

// AI Modal State
const aiModalOpen = ref<boolean>(false)

// Delete State
const deleteModalOpen = ref<boolean>(false)
const deleting = ref<boolean>(false)
const itemToDelete = ref<SubCourseLearningOutcome | null>(null)

async function loadData() {
  loading.value = true
  try {
    const [subRes, cpmkRes] = await Promise.all([
      outcomeService.listSubCpmk(),
      outcomeService.listCpmk(),
    ])
    subCpmks.value = subRes.data || []
    cpmks.value = cpmkRes.data || []
  } catch (err: any) {
    toast.error(err.message || 'Gagal memuat data Sub-CPMK.')
  } finally {
    loading.value = false
  }
}

const filteredSubCpmks = computed(() => {
  let list = subCpmks.value
  if (selectedCpmkFilter.value) {
    list = list.filter((s) => String(s.course_learning_outcome_id) === selectedCpmkFilter.value)
  }
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    list = list.filter(
      (s) =>
        s.name.toLowerCase().includes(q) ||
        s.code.toLowerCase().includes(q) ||
        (s.course_outcome && s.course_outcome.name.toLowerCase().includes(q))
    )
  }
  return list
})

const paginatedSubCpmks = computed(() => {
  return filteredSubCpmks.value.slice(0, perPage.value)
})

function openDeleteModal(item: SubCourseLearningOutcome) {
  itemToDelete.value = item
  deleteModalOpen.value = true
}

async function handleDelete() {
  if (!itemToDelete.value) return
  deleting.value = true
  try {
    await outcomeService.deleteSubCpmk(itemToDelete.value.id)
    toast.success(`Sub-CPMK ${itemToDelete.value.code} berhasil dihapus.`)
    deleteModalOpen.value = false
    itemToDelete.value = null
    await loadData()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menghapus Sub-CPMK.')
  } finally {
    deleting.value = false
  }
}

function handleAiSelect(suggestion: any) {
  router.push({
    path: '/outcomes/sub-cpmk/create',
    query: {
      code: suggestion.code,
      name: suggestion.name,
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
            <span class="text-slate-900 font-semibold">Sub-CPMK</span>
          </div>
          <h1 class="text-xl font-bold text-slate-900 tracking-tight">Sub-CPMK</h1>
          <p class="text-xs text-slate-500 mt-0.5">Kemampuan Akhir Tiap Tahapan Pembelajaran & Indikator Penilaian</p>
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
            @click="router.push('/outcomes/sub-cpmk/create')"
          >
            <Plus class="w-4 h-4" />
            Tambah Data
          </Button>
        </div>
      </div>

      <!-- Controls & Filter Bar -->
      <Card class="p-3 bg-white border border-slate-200 rounded-xl shadow-2xs">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div class="flex items-center gap-3">
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

            <!-- Filter CPMK Induk -->
            <select
              v-model="selectedCpmkFilter"
              class="border border-slate-300 rounded-md text-xs py-1 px-2.5 bg-white text-slate-800 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none"
            >
              <option value="">Semua CPMK Induk</option>
              <option v-for="c in cpmks" :key="c.id" :value="String(c.id)">
                {{ c.code }} - {{ c.name.slice(0, 40) }}...
              </option>
            </select>
          </div>

          <div class="relative w-full sm:w-64">
            <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input
              v-model="search"
              type="text"
              placeholder="Cari sub-cpmk..."
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

        <div v-else-if="filteredSubCpmks.length === 0" class="py-12 text-center text-slate-400 text-xs">
          <CheckCircle2 class="w-8 h-8 text-slate-300 mx-auto mb-2" />
          <p class="font-bold text-slate-700">Belum ada data Sub-CPMK</p>
          <p class="text-2xs text-slate-400 mt-0.5">Klik tombol "+ Tambah Data" untuk menambahkan tahapan pembelajaran.</p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="bg-slate-50/80 border-b border-slate-200 text-3xs font-semibold text-slate-600 uppercase tracking-wider">
                <th class="py-3 px-4 w-12 text-center">NO</th>
                <th class="py-3 px-4 w-28">KODE &uarr;&darr;</th>
                <th class="py-3 px-4 min-w-[280px]">SUB-CPMK &uarr;&darr;</th>
                <th class="py-3 px-4 min-w-[220px]">CPMK INDUK</th>
                <th class="py-3 px-4 w-24 text-center">AKSI</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="(item, index) in paginatedSubCpmks"
                :key="item.id"
                class="hover:bg-slate-50/70 transition-colors"
              >
                <td class="py-3 px-4 text-center font-semibold text-slate-500">
                  {{ index + 1 }}
                </td>
                <td class="py-3 px-4 font-mono font-bold text-slate-900">
                  {{ item.code }}
                </td>
                <td class="py-3 px-4">
                  <div class="font-semibold text-slate-900 leading-relaxed">{{ item.name }}</div>
                  <div v-if="item.description" class="text-2xs text-slate-500 mt-0.5">{{ item.description }}</div>
                </td>
                <td class="py-3 px-4 text-slate-700 font-medium text-2xs">
                  {{ item.course_outcome ? `${item.course_outcome.code} - ${item.course_outcome.name.slice(0, 45)}...` : '-' }}
                </td>
                <td class="py-3 px-4 text-center">
                  <div class="flex items-center justify-center gap-1">
                    <button
                      type="button"
                      class="p-1.5 rounded-md text-slate-500 hover:text-brand-600 hover:bg-brand-50 transition-colors"
                      title="Edit Sub-CPMK"
                      @click="router.push(`/outcomes/sub-cpmk/${item.id}/edit`)"
                    >
                      <Edit class="w-3.5 h-3.5" />
                    </button>
                    <button
                      type="button"
                      class="p-1.5 rounded-md text-slate-500 hover:text-rose-600 hover:bg-rose-50 transition-colors"
                      title="Hapus Sub-CPMK"
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
          <span>Menampilkan 1 hingga {{ paginatedSubCpmks.length }} dari total {{ filteredSubCpmks.length }} data</span>
        </div>
      </Card>
    </div>

    <!-- AI Generator Modal -->
    <AiOutcomeGeneratorModal
      v-model:open="aiModalOpen"
      type="sub_cpmk"
      @select="handleAiSelect"
    />

    <!-- Confirm Delete Modal -->
    <ConfirmModal
      :open="deleteModalOpen"
      title="Hapus Sub-CPMK"
      :message="`Apakah Anda yakin ingin menghapus Sub-CPMK ${itemToDelete?.code}?`"
      confirm-text="Ya, Hapus"
      variant="danger"
      :loading="deleting"
      @update:open="deleteModalOpen = $event"
      @confirm="handleDelete"
    />
  </PageContainer>
</template>
