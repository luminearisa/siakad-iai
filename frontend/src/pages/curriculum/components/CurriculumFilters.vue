<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { Search, Filter, RotateCcw } from 'lucide-vue-next'
import { academicService } from '@/services/api/academic'
import type { StudyProgram } from '@/types/academic'
import type { CurriculumFilters } from '@/types/curriculum'
import { debounce } from '@/utils/debounce'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import Button from '@/components/ui/Button.vue'

interface Props {
  modelValue: CurriculumFilters
}

const props = defineProps<Props>()

const emit = defineEmits<{
  (e: 'update:modelValue', val: CurriculumFilters): void
  (e: 'change'): void
}>()

const localFilters = ref<CurriculumFilters>({ ...props.modelValue })
const studyPrograms = ref<StudyProgram[]>([])
const showMobileFilters = ref<boolean>(false)

const statusOptions = [
  { label: 'Semua Status', value: '' },
  { label: 'Draft', value: 'draft' },
  { label: 'Aktif Berlaku', value: 'active' },
  { label: 'Non-Aktif', value: 'inactive' },
  { label: 'Diarsipkan', value: 'archived' },
]

async function loadStudyPrograms() {
  try {
    const res = await academicService.getStudyPrograms()
    studyPrograms.value = res.data || []
  } catch {
    studyPrograms.value = []
  }
}

const debouncedEmit = debounce(() => {
  emit('update:modelValue', { ...localFilters.value, page: 1 })
  emit('change')
}, 350)

function handleFilterChange() {
  emit('update:modelValue', { ...localFilters.value, page: 1 })
  emit('change')
}

function resetFilters() {
  localFilters.value = {
    search: '',
    study_program_id: '',
    status: '',
    start_year: '',
    end_year: '',
    page: 1,
  }
  emit('update:modelValue', { ...localFilters.value })
  emit('change')
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
  <div class="bg-white p-3 sm:p-4 rounded-lg border border-slate-200 shadow-subtle space-y-3">
    <!-- Search & Mobile Toggle -->
    <div class="flex items-center gap-2">
      <div class="flex-1">
        <Input
          v-model="localFilters.search"
          placeholder="Cari Kode Kurikulum, Nama Kurikulum, Versi..."
          size="sm"
          @update:model-value="debouncedEmit"
        >
          <template #prefix>
            <Search class="w-3.5 h-3.5" />
          </template>
        </Input>
      </div>

      <Button
        variant="outline"
        size="sm"
        class="sm:hidden shrink-0"
        @click="showMobileFilters = !showMobileFilters"
      >
        <Filter class="w-3.5 h-3.5" />
        <span>Filter</span>
      </Button>
    </div>

    <!-- Filter Fields -->
    <div
      :class="[
        'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5 pt-2 border-t border-slate-100',
        showMobileFilters ? 'block' : 'hidden sm:grid',
      ]"
    >
      <!-- Study Program -->
      <Select
        v-model="localFilters.study_program_id"
        size="sm"
        placeholder="Semua Program Studi"
        @update:model-value="handleFilterChange"
      >
        <option value="">Semua Program Studi</option>
        <option v-for="sp in studyPrograms" :key="sp.id" :value="sp.id">
          {{ sp.code }} — {{ sp.name }} ({{ sp.degree }})
        </option>
      </Select>

      <!-- Status -->
      <Select
        v-model="localFilters.status"
        :options="statusOptions"
        size="sm"
        @update:model-value="handleFilterChange"
      />

      <!-- Start Year -->
      <Input
        v-model="localFilters.start_year"
        placeholder="Tahun Mulai (contoh: 2024)"
        type="number"
        size="sm"
        @update:model-value="debouncedEmit"
      />

      <!-- Reset -->
      <div class="flex items-center justify-end">
        <Button
          variant="outline"
          size="sm"
          class="w-full sm:w-auto"
          @click="resetFilters"
        >
          <RotateCcw class="w-3.5 h-3.5" />
          <span>Reset Filter</span>
        </Button>
      </div>
    </div>
  </div>
</template>
