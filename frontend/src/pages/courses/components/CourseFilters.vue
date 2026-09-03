<script setup lang="ts">
import { ref, watch } from 'vue'
import { Search, Filter, RotateCcw } from 'lucide-vue-next'
import type { CourseFilters } from '@/types/course'
import { debounce } from '@/utils/debounce'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import Button from '@/components/ui/Button.vue'

interface Props {
  modelValue: CourseFilters
}

const props = defineProps<Props>()

const emit = defineEmits<{
  (e: 'update:modelValue', val: CourseFilters): void
  (e: 'change'): void
}>()

const localFilters = ref<CourseFilters>({ ...props.modelValue })
const showMobileFilters = ref<boolean>(false)

const typeOptions = [
  { label: 'Semua Tipe Perkuliahan', value: '' },
  { label: 'Teori', value: 'theory' },
  { label: 'Praktikum', value: 'practical' },
  { label: 'Teori & Praktikum (Mixed)', value: 'mixed' },
]

const statusOptions = [
  { label: 'Semua Status', value: '' },
  { label: 'Aktif', value: 'active' },
  { label: 'Non-Aktif', value: 'inactive' },
]

const categoryOptions = [
  { label: 'Semua Kategori MK', value: '' },
  { label: 'Mata Kuliah Wajib Umum (MKWU)', value: 'MKWU' },
  { label: 'Mata Kuliah Wajib Fakultas (MKWF)', value: 'MKWF' },
  { label: 'Mata Kuliah Wajib Program Studi (MKWPS)', value: 'MKWPS' },
  { label: 'Mata Kuliah Pilihan (MKP)', value: 'MKP' },
  { label: 'Tugas Akhir / Skripsi', value: 'Skripsi' },
]

const creditOptions = [
  { label: 'Semua Bobot SKS', value: '' },
  { label: '1 SKS', value: '1' },
  { label: '2 SKS', value: '2' },
  { label: '3 SKS', value: '3' },
  { label: '4 SKS', value: '4' },
  { label: '6 SKS', value: '6' },
]

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
    type: '',
    category: '',
    status: '',
    credits: '',
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
</script>

<template>
  <div class="bg-white p-3 sm:p-4 rounded-lg border border-slate-200 shadow-subtle space-y-3">
    <!-- Top Search & Mobile Toggle -->
    <div class="flex items-center gap-2">
      <div class="flex-1">
        <Input
          v-model="localFilters.search"
          placeholder="Cari Kode Mata Kuliah, Nama Mata Kuliah..."
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
      <!-- Type -->
      <Select
        v-model="localFilters.type"
        :options="typeOptions"
        size="sm"
        @update:model-value="handleFilterChange"
      />

      <!-- Category -->
      <Select
        v-model="localFilters.category"
        :options="categoryOptions"
        size="sm"
        @update:model-value="handleFilterChange"
      />

      <!-- Credits -->
      <Select
        v-model="localFilters.credits"
        :options="creditOptions"
        size="sm"
        @update:model-value="handleFilterChange"
      />

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
