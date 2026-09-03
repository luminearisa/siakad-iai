<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { Filter, Edit2, X, Check } from 'lucide-vue-next'
import { academicService } from '@/services/api/academic'
import { useToast } from '@/composables/useToast'
import type { StudyProgram } from '@/types/academic'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'

const toast = useToast()
const loading = ref<boolean>(false)
const studyPrograms = ref<StudyProgram[]>([])
const search = ref<string>('')
const perPage = ref<number>(10)
const drawerOpen = ref<boolean>(false)
const filterOpen = ref<boolean>(false)
const selectedDegree = ref<string>('')
const editingProdi = ref<StudyProgram | null>(null)
const saving = ref<boolean>(false)

const form = reactive({
  min_gpa_graduation: 0.00,
  min_final_exam_guidance: 0,
  final_exam_advisors_count: 2,
  final_exam_examiners_count: 2,
  is_thesis_required: true,
})

const filteredPrograms = computed(() => {
  let list = studyPrograms.value
  if (selectedDegree.value) {
    list = list.filter((p) => p.degree === selectedDegree.value)
  }
  if (!search.value) return list
  const q = search.value.toLowerCase()
  return list.filter(
    (p) =>
      p.name.toLowerCase().includes(q) ||
      p.code.toLowerCase().includes(q) ||
      `${p.degree} - ${p.name}`.toLowerCase().includes(q)
  )
})

function formatGpa(val: any) {
  if (val === undefined || val === null) return '0,00'
  const num = typeof val === 'number' ? val : parseFloat(val)
  return isNaN(num) ? '0,00' : num.toFixed(2).replace('.', ',')
}

async function fetchStudyPrograms() {
  loading.value = true
  try {
    const res = await academicService.getStudyPrograms({ per_page: 50 })
    studyPrograms.value = res.data || []
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal memuat pengaturan program studi')
  } finally {
    loading.value = false
  }
}

function openEditDrawer(item: StudyProgram) {
  editingProdi.value = item
  form.min_gpa_graduation = item.setting?.min_gpa_graduation ? Number(item.setting.min_gpa_graduation) : 0.00
  form.min_final_exam_guidance = item.setting?.min_final_exam_guidance ?? 0
  form.final_exam_advisors_count = item.setting?.final_exam_advisors_count ?? 2
  form.final_exam_examiners_count = item.setting?.final_exam_examiners_count ?? 2
  form.is_thesis_required = item.setting?.is_thesis_required ?? true
  drawerOpen.value = true
}

function closeDrawer() {
  drawerOpen.value = false
}

async function handleSave() {
  if (!editingProdi.value) return

  saving.value = true
  try {
    await academicService.updateStudyProgramSetting(editingProdi.value.id, form)
    toast.success(`Pengaturan prodi ${editingProdi.value.name} berhasil disimpan`)
    drawerOpen.value = false
    fetchStudyPrograms()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal menyimpan pengaturan program studi')
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  fetchStudyPrograms()
})
</script>

<template>
  <PageContainer>
    <!-- Header (Matching Reference Screenshot) -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div>
        <nav class="flex items-center gap-1.5 text-xs text-slate-400 mb-1">
          <span class="text-brand-600 font-medium">Akademik</span>
        </nav>
        <h1 class="text-xl font-bold text-slate-900">Pengaturan Program Studi</h1>
        <p class="text-xs text-slate-500 mt-0.5">Manajemen Pengaturan Program Studi</p>
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
          <!-- Filter Button -->
          <button
            type="button"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 border rounded-lg text-xs font-semibold transition-colors"
            :class="filterOpen || selectedDegree ? 'border-rose-300 text-rose-700 bg-rose-50' : 'border-slate-300 text-slate-700 bg-white hover:bg-slate-50'"
            @click="filterOpen = !filterOpen"
          >
            <Filter class="w-3.5 h-3.5 text-rose-600" />
            <span>Filter *</span>
          </button>

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

      <!-- Filter Panel -->
      <div
        v-if="filterOpen"
        class="p-4 bg-rose-50/30 border-b border-rose-100 flex flex-wrap items-center gap-4 text-xs"
      >
        <div class="flex items-center gap-2">
          <label class="font-semibold text-slate-700">Jenjang Pendidikan:</label>
          <select
            v-model="selectedDegree"
            class="px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-brand-500/20 outline-none"
          >
            <option value="">Semua Jenjang</option>
            <option value="D3">D3</option>
            <option value="D4">D4</option>
            <option value="S1">S1</option>
            <option value="S2">S2</option>
            <option value="S3">S3</option>
          </select>
        </div>
        <button
          v-if="selectedDegree"
          type="button"
          class="text-xs text-rose-600 hover:underline font-medium"
          @click="selectedDegree = ''"
        >
          Reset Filter
        </button>
      </div>

      <!-- Table (Matching Reference Screenshot) -->
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-100/80 border-b border-slate-200/80 text-slate-600 font-bold uppercase tracking-wider text-3xs">
              <th class="py-3 px-4 w-12 text-center">NO</th>
              <th class="py-3 px-4 w-1/4">PROGRAM STUDI</th>
              <th class="py-3 px-4 text-center">MINIMAL IPK LULUS</th>
              <th class="py-3 px-4 text-center">MINIMAL BIMBINGAN TUGAS AKHIR</th>
              <th class="py-3 px-4 text-center">DOSEN PEMBIMBING TUGAS AKHIR</th>
              <th class="py-3 px-4 text-center">DOSEN PENGUJI TUGAS AKHIR</th>
              <th class="py-3 px-4 text-center w-36">TUGAS AKHIR SYARAT KELULUSAN</th>
              <th class="py-3 px-4 w-20 text-center">AKSI</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <tr v-if="loading" class="hover:bg-transparent">
              <td colspan="8" class="py-12 text-center text-slate-400">
                Memuat data pengaturan program studi...
              </td>
            </tr>
            <tr v-else-if="filteredPrograms.length === 0" class="hover:bg-transparent">
              <td colspan="8" class="py-12 text-center text-slate-400">
                Belum ada data program studi
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
                {{ item.degree }} - {{ item.name }}
              </td>
              <td class="py-3.5 px-4 text-center font-mono font-medium text-slate-800">
                {{ formatGpa(item.setting?.min_gpa_graduation) }}
              </td>
              <td class="py-3.5 px-4 text-center font-mono font-medium text-slate-800">
                {{ item.setting?.min_final_exam_guidance ?? 0 }}
              </td>
              <td class="py-3.5 px-4 text-center font-mono font-medium text-slate-800">
                {{ item.setting?.final_exam_advisors_count ?? 2 }}
              </td>
              <td class="py-3.5 px-4 text-center font-mono font-medium text-slate-800">
                {{ item.setting?.final_exam_examiners_count ?? 2 }}
              </td>
              <td class="py-3.5 px-4 text-center">
                <span
                  class="inline-flex items-center justify-center px-4 py-1 rounded text-3xs font-bold text-white shadow-2xs"
                  :class="(item.setting?.is_thesis_required ?? true) ? 'bg-emerald-600' : 'bg-rose-600'"
                >
                  {{ (item.setting?.is_thesis_required ?? true) ? 'Ya' : 'Tidak' }}
                </span>
              </td>
              <td class="py-3.5 px-4">
                <div class="flex items-center justify-center">
                  <button
                    type="button"
                    class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-md border border-rose-200 transition-colors"
                    title="Edit Pengaturan"
                    @click="openEditDrawer(item)"
                  >
                    <Edit2 class="w-3.5 h-3.5" />
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
              Edit Pengaturan Prodi
            </h3>
            <p class="text-3xs text-slate-500 mt-0.5">{{ editingProdi?.degree }} - {{ editingProdi?.name }}</p>
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
              Minimal IPK Lulus
            </label>
            <Input
              v-model.number="form.min_gpa_graduation"
              type="number"
              step="0.01"
              min="0"
              max="4"
              placeholder="3.50"
            />
          </div>

          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Minimal Bimbingan Tugas Akhir
            </label>
            <Input
              v-model.number="form.min_final_exam_guidance"
              type="number"
              min="0"
              placeholder="0"
            />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-semibold text-slate-700 mb-1.5">
                Dosen Pembimbing
              </label>
              <Input
                v-model.number="form.final_exam_advisors_count"
                type="number"
                min="1"
                placeholder="2"
              />
            </div>
            <div>
              <label class="block font-semibold text-slate-700 mb-1.5">
                Dosen Penguji
              </label>
              <Input
                v-model.number="form.final_exam_examiners_count"
                type="number"
                min="1"
                placeholder="2"
              />
            </div>
          </div>

          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Tugas Akhir Syarat Kelulusan
            </label>
            <select
              v-model="form.is_thesis_required"
              class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600 outline-none"
            >
              <option :value="true">Ya (Wajib)</option>
              <option :value="false">Tidak (Opsional / Non-Skripsi)</option>
            </select>
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
            Simpan Pengaturan
          </Button>
        </div>
      </div>
    </div>
  </PageContainer>
</template>
