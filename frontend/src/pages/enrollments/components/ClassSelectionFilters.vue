<script setup lang="ts">
import { ref } from 'vue'
import { Search, RotateCcw } from 'lucide-vue-next'
import { debounce } from '@/utils/debounce'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import Button from '@/components/ui/Button.vue'

interface Filters {
  search?: string
  day_of_week?: string
}

interface Props {
  modelValue: Filters
}

const props = defineProps<Props>()

const emit = defineEmits<{
  (e: 'update:modelValue', val: Filters): void
  (e: 'change'): void
}>()

const localFilters = ref<Filters>({ ...props.modelValue })

const dayOptions = [
  { label: 'Semua Hari', value: '' },
  { label: 'Senin', value: 'monday' },
  { label: 'Selasa', value: 'tuesday' },
  { label: 'Rabu', value: 'wednesday' },
  { label: 'Kamis', value: 'thursday' },
  { label: 'Jumat', value: 'friday' },
  { label: 'Sabtu', value: 'saturday' },
]

const debouncedEmit = debounce(() => {
  emit('update:modelValue', { ...localFilters.value })
  emit('change')
}, 300)

function handleSelectChange() {
  emit('update:modelValue', { ...localFilters.value })
  emit('change')
}

function resetFilters() {
  localFilters.value = { search: '', day_of_week: '' }
  emit('update:modelValue', { ...localFilters.value })
  emit('change')
}
</script>

<template>
  <div class="flex flex-wrap items-center gap-2 p-2.5 bg-slate-50 border border-slate-200 rounded-lg">
    <div class="flex-1 min-w-[200px]">
      <Input
        v-model="localFilters.search"
        placeholder="Cari kode atau nama mata kuliah..."
        size="sm"
        @update:model-value="debouncedEmit"
      >
        <template #prefix>
          <Search class="w-3.5 h-3.5" />
        </template>
      </Input>
    </div>

    <div class="w-40">
      <Select
        v-model="localFilters.day_of_week"
        :options="dayOptions"
        size="sm"
        @update:model-value="handleSelectChange"
      />
    </div>

    <Button
      variant="ghost"
      size="sm"
      title="Reset Filter"
      class="text-slate-500 hover:text-slate-800 shrink-0"
      @click="resetFilters"
    >
      <RotateCcw class="w-3.5 h-3.5" />
    </Button>
  </div>
</template>
