<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { Search, Filter, RotateCcw } from 'lucide-vue-next'
import { academicService } from '@/services/api/academic'
import type { StudyProgram } from '@/types/academic'
import type { StudentFilters } from '@/types/student'
import { debounce } from '@/utils/debounce'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import Button from '@/components/ui/Button.vue'

interface Props {
  modelValue: StudentFilters
}

const props = defineProps<Props>()

const emit = defineEmits<{
  (e: 'update:modelValue', val: StudentFilters): void
  (e: 'change'): void
}>()

const localFilters = ref<StudentFilters>({ ...props.modelValue })
const studyPrograms = ref<StudyProgram[]>([])
const showMobileFilters = ref<boolean>(false)

const statusOptions = [
  { label: 'Semua Status', value: '' },
  { label: 'Aktif', value: 'active' },
  { label: 'Calon Mahasiswa', value: 'prospective' },
  { label: 'Cuti', value: 'leave' },
  { label: 'Non-Aktif', value: 'inactive' },
  { label: 'Lulus', value: 'graduated' },
  { label: 'Mengundurkan Diri', value: 'withdrawn' },
  { label: 'Dikeluarkan (DO)', value: 'dismissed' },
  { label: 'Wafat', value: 'deceased' },
]

const genderOptions = [
  { label: 'Semua Jenis Kelamin', value: '' },
  { label: 'Laki-Laki', value: 'male' },
  { label: 'Perempuan', value: 'female' },
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
    status: '',
    study_program_id: '',
    gender: '',
    admission_year: '',
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
    <!-- Top Row: Search & Mobile Toggle -->
    <div class="flex items-center gap-2">
      <div class="flex-1">
        <Input
          v-model="localFilters.search"
          placeholder="Cari NIM, Nama Mahasiswa, atau Email..."
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

    <!-- Filter Fields: Always visible on desktop, toggleable on mobile -->
    <div
      :class="[
        'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5 pt-2 border-t border-slate-100',
        showMobileFilters ? 'block' : 'hidden sm:grid',
      ]"
    >
      <!-- Study Program Filter -->
      <Select
        v-model="localFilters.study_program_id"
        size="sm"
        placeholder="Semua Program Studi"
        @update:model-value="handleFilterChange"
      >
        <option value="">Semua Program Studi</option>
        <option v-for="sp in studyPrograms" :key="sp.id" :value="sp.id">
          {{ sp.code }} — {{ sp.name }}
        </option>
      </Select>

      <!-- Status Filter -->
      <Select
        v-model="localFilters.status"
        :options="statusOptions"
        size="sm"
        @update:model-value="handleFilterChange"
      />

      <!-- Gender Filter -->
      <Select
        v-model="localFilters.gender"
        :options="genderOptions"
        size="sm"
        @update:model-value="handleFilterChange"
      />

      <!-- Admission Year & Reset -->
      <div class="flex items-center gap-2">
        <Input
          v-model="localFilters.admission_year"
          type="number"
          placeholder="Angkatan (e.g. 2025)"
          size="sm"
          @update:model-value="debouncedEmit"
        />

        <Button
          variant="ghost"
          size="sm"
          title="Reset Semua Filter"
          class="shrink-0 text-slate-500 hover:text-slate-800"
          @click="resetFilters"
        >
          <RotateCcw class="w-3.5 h-3.5" />
        </Button>
      </div>
    </div>
  </div>
</template>
