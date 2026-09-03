<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { Search, Filter, RotateCcw } from 'lucide-vue-next'
import { academicService } from '@/services/api/academic'
import { courseService } from '@/services/api/courses'
import type { Semester, StudyProgram } from '@/types/academic'
import type { Course } from '@/types/course'
import type { ClassFilters } from '@/types/class'
import { debounce } from '@/utils/debounce'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import Button from '@/components/ui/Button.vue'

interface Props {
  modelValue: ClassFilters
}

const props = defineProps<Props>()

const emit = defineEmits<{
  (e: 'update:modelValue', val: ClassFilters): void
  (e: 'change'): void
}>()

const localFilters = ref<ClassFilters>({ ...props.modelValue })
const semesters = ref<Semester[]>([])
const studyPrograms = ref<StudyProgram[]>([])
const courses = ref<Course[]>([])
const showMobileFilters = ref<boolean>(false)

const statusOptions = [
  { label: 'Semua Status Kelas', value: '' },
  { label: 'Draft', value: 'draft' },
  { label: 'Buka (Open)', value: 'open' },
  { label: 'Ditutup (Closed)', value: 'closed' },
  { label: 'Dibatalkan (Cancelled)', value: 'cancelled' },
  { label: 'Selesai (Completed)', value: 'completed' },
]

async function loadReferenceData() {
  try {
    const [semRes, spRes, courseRes] = await Promise.all([
      academicService.getSemesters(),
      academicService.getStudyPrograms(),
      courseService.list({ per_page: 100 }),
    ])
    semesters.value = semRes.data || []
    studyPrograms.value = spRes.data || []
    courses.value = courseRes.data || []
  } catch {
    // Graceful fallback
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
    semester_id: '',
    study_program_id: '',
    course_id: '',
    status: '',
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
  loadReferenceData()
})
</script>

<template>
  <div class="bg-white p-3 sm:p-4 rounded-lg border border-slate-200 shadow-subtle space-y-3">
    <!-- Top Search & Mobile Toggle -->
    <div class="flex items-center gap-2">
      <div class="flex-1">
        <Input
          v-model="localFilters.search"
          placeholder="Cari Kode Kelas, Nama Kelas, atau Kode Seksi/Paralel (contoh: A, B)..."
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

    <!-- Filter Dropdowns -->
    <div
      :class="[
        'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5 pt-2 border-t border-slate-100',
        showMobileFilters ? 'block' : 'hidden sm:grid',
      ]"
    >
      <!-- Semester Akademik -->
      <Select
        v-model="localFilters.semester_id"
        size="sm"
        placeholder="Semua Semester Akademik"
        @update:model-value="handleFilterChange"
      >
        <option value="">Semua Semester Akademik</option>
        <option v-for="sem in semesters" :key="sem.id" :value="sem.id">
          {{ sem.name }} ({{ sem.status === 'active' ? 'Aktif' : 'Non-Aktif' }})
        </option>
      </Select>

      <!-- Program Studi -->
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

      <!-- Mata Kuliah -->
      <Select
        v-model="localFilters.course_id"
        size="sm"
        placeholder="Semua Mata Kuliah"
        @update:model-value="handleFilterChange"
      >
        <option value="">Semua Mata Kuliah</option>
        <option v-for="c in courses" :key="c.id" :value="c.id">
          {{ c.code }} — {{ c.name }}
        </option>
      </Select>

      <!-- Status & Reset -->
      <div class="flex items-center gap-2">
        <Select
          v-model="localFilters.status"
          :options="statusOptions"
          size="sm"
          class="flex-1"
          @update:model-value="handleFilterChange"
        />

        <Button
          variant="ghost"
          size="sm"
          title="Reset Filter"
          class="shrink-0 text-slate-500 hover:text-slate-800"
          @click="resetFilters"
        >
          <RotateCcw class="w-3.5 h-3.5" />
        </Button>
      </div>
    </div>
  </div>
</template>
