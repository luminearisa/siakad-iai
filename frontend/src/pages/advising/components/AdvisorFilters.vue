<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { Search, Filter, RotateCcw } from 'lucide-vue-next'
import { academicService } from '@/services/api/academic'
import type { StudyProgram } from '@/types/academic'
import type { AdvisorFilters } from '@/types/advising'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import Button from '@/components/ui/Button.vue'

const props = defineProps<{
  modelValue: AdvisorFilters
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: AdvisorFilters): void
  (e: 'filter'): void
  (e: 'reset'): void
}>()

const localFilters = ref<AdvisorFilters>({ ...props.modelValue })
const studyPrograms = ref<StudyProgram[]>([])
const showMobileFilters = ref<boolean>(false)

const statusOptions = [
  { label: 'Semua Status Penugasan', value: '' },
  { label: 'Aktif Menjabat', value: 'active' },
  { label: 'Non-Aktif', value: 'inactive' },
  { label: 'Dialihkan (Transferred)', value: 'transferred' },
  { label: 'Selesai (Completed)', value: 'completed' },
]

async function loadStudyPrograms() {
  try {
    const res = await academicService.getStudyPrograms()
    studyPrograms.value = res.data || []
  } catch {
    // Fallback
  }
}

function handleSearchInput() {
  emit('update:modelValue', { ...localFilters.value, page: 1 })
  emit('filter')
}

function handleSelectChange() {
  emit('update:modelValue', { ...localFilters.value, page: 1 })
  emit('filter')
}

function handleReset() {
  localFilters.value = {
    search: '',
    study_program_id: '',
    status: '',
    page: 1,
  }
  emit('update:modelValue', { ...localFilters.value })
  emit('reset')
}

watch(
  () => props.modelValue,
  (newVal) => {
    localFilters.value = { ...newVal }
  },
  { deep: true }
)

onMounted(() => {
  loadStudyPrograms()
})
</script>

<template>
  <div class="bg-white border border-slate-200 rounded-lg p-3 sm:p-4 shadow-subtle space-y-3">
    <!-- Main Search & Toggle -->
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
      <div class="relative flex-1">
        <Input
          v-model="localFilters.search"
          placeholder="Cari Dosen PA (Nama/NIDN) atau Mahasiswa (NIM/Nama)..."
          class="w-full pl-9 text-xs"
          :disabled="loading"
          @input="handleSearchInput"
        >
          <template #prefix>
            <Search class="w-3.5 h-3.5 text-slate-400" />
          </template>
        </Input>
      </div>

      <div class="flex items-center gap-2">
        <Button
          variant="outline"
          size="sm"
          class="sm:hidden flex-1 justify-center text-xs"
          @click="showMobileFilters = !showMobileFilters"
        >
          <Filter class="w-3.5 h-3.5 text-slate-500" />
          <span>Filter</span>
        </Button>

        <Button
          variant="outline"
          size="sm"
          class="text-xs text-slate-600 hover:text-slate-900"
          :disabled="loading"
          @click="handleReset"
        >
          <RotateCcw class="w-3.5 h-3.5 text-slate-400" />
          <span class="hidden sm:inline">Reset</span>
        </Button>
      </div>
    </div>

    <!-- Filter Dropdowns (Desktop always, Mobile collapsable) -->
    <div
      :class="[
        'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5 pt-2 border-t border-slate-100',
        showMobileFilters ? 'block' : 'hidden sm:grid',
      ]"
    >
      <!-- Study Program Filter -->
      <Select
        v-model="localFilters.study_program_id"
        class="text-xs"
        :disabled="loading"
        @change="handleSelectChange"
      >
        <option value="">Semua Program Studi</option>
        <option v-for="sp in studyPrograms" :key="sp.id" :value="sp.id">
          {{ sp.name }} ({{ sp.degree }})
        </option>
      </Select>

      <!-- Status Filter -->
      <Select
        v-model="localFilters.status"
        class="text-xs"
        :disabled="loading"
        @change="handleSelectChange"
      >
        <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
          {{ opt.label }}
        </option>
      </Select>
    </div>
  </div>
</template>
