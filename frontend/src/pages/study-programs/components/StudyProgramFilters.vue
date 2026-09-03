<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Search, RotateCcw } from 'lucide-vue-next'
import { academicService } from '@/services/api/academic'
import type { Faculty, StudyProgramFilters } from '@/types/academic'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import Button from '@/components/ui/Button.vue'

const props = defineProps<{
  modelValue: StudyProgramFilters
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: StudyProgramFilters): void
  (e: 'filter'): void
  (e: 'reset'): void
}>()

const faculties = ref<Faculty[]>([])

async function loadFaculties() {
  try {
    const res = await academicService.getFaculties({ per_page: 100 })
    faculties.value = res.data || []
  } catch {
    faculties.value = []
  }
}

function handleInput(key: keyof StudyProgramFilters, value: any) {
  emit('update:modelValue', {
    ...props.modelValue,
    [key]: value,
  })
}

function handleSearch() {
  emit('filter')
}

function handleReset() {
  emit('reset')
}

onMounted(() => {
  loadFaculties()
})
</script>

<template>
  <div class="bg-white border border-slate-200 rounded-lg p-3 sm:p-4 shadow-subtle space-y-3">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5">
      <!-- Search Input -->
      <div class="lg:col-span-2">
        <Input
          :model-value="modelValue.search"
          placeholder="Cari kode atau nama program studi..."
          size="sm"
          @update:model-value="handleInput('search', $event)"
          @keyup.enter="handleSearch"
        >
          <template #prefix>
            <Search class="w-3.5 h-3.5 text-slate-400" />
          </template>
        </Input>
      </div>

      <!-- Faculty Filter -->
      <div>
        <Select
          :model-value="modelValue.faculty_id || ''"
          size="sm"
          @update:model-value="handleInput('faculty_id', $event); handleSearch()"
        >
          <option value="">Semua Fakultas</option>
          <option v-for="f in faculties" :key="f.id" :value="f.id">
            {{ f.name }} ({{ f.code }})
          </option>
        </Select>
      </div>

      <!-- Degree Filter -->
      <div>
        <Select
          :model-value="modelValue.degree || ''"
          size="sm"
          @update:model-value="handleInput('degree', $event); handleSearch()"
        >
          <option value="">Semua Jenjang</option>
          <option value="D3">Diploma 3 (D3)</option>
          <option value="D4">Diploma 4 (D4)</option>
          <option value="S1">Sarjana (S1)</option>
          <option value="S2">Magister (S2)</option>
          <option value="S3">Doktor (S3)</option>
        </Select>
      </div>
    </div>

    <!-- Actions -->
    <div class="flex items-center justify-between pt-2 border-t border-slate-100">
      <span class="text-2xs text-slate-400">Tekan Enter pada pencarian atau pilih filter untuk memfilter otomatis</span>
      <div class="flex items-center gap-2">
        <Button variant="ghost" size="xs" :disabled="loading" @click="handleReset">
          <RotateCcw class="w-3 h-3 text-slate-400" />
          <span>Reset Filter</span>
        </Button>
        <Button variant="primary" size="xs" :loading="loading" @click="handleSearch">
          <Search class="w-3 h-3" />
          <span>Terapkan Filter</span>
        </Button>
      </div>
    </div>
  </div>
</template>
