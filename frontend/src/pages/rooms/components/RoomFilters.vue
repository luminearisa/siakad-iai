<script setup lang="ts">
import { ref, watch } from 'vue'
import { Search, Filter, RotateCcw } from 'lucide-vue-next'
import type { RoomFilters } from '@/types/room'
import { debounce } from '@/utils/debounce'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import Button from '@/components/ui/Button.vue'

interface Props {
  modelValue: RoomFilters
}

const props = defineProps<Props>()

const emit = defineEmits<{
  (e: 'update:modelValue', val: RoomFilters): void
  (e: 'change'): void
}>()

const localFilters = ref<RoomFilters>({ ...props.modelValue })
const showMobileFilters = ref<boolean>(false)

const typeOptions = [
  { label: 'Semua Tipe Ruangan', value: '' },
  { label: 'Ruang Kelas Teori', value: 'classroom' },
  { label: 'Laboratorium Komputer/Sains', value: 'laboratory' },
  { label: 'Auditorium / Aula', value: 'auditorium' },
]

const statusOptions = [
  { label: 'Semua Status Ruangan', value: '' },
  { label: 'Tersedia (Aktif)', value: 'active' },
  { label: 'Pemeliharaan (Maintenance)', value: 'maintenance' },
  { label: 'Non-Aktif', value: 'inactive' },
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
    building: '',
    floor: '',
    room_type: '',
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
</script>

<template>
  <div class="bg-white p-3 sm:p-4 rounded-lg border border-slate-200 shadow-subtle space-y-3">
    <!-- Top Search & Mobile Toggle -->
    <div class="flex items-center gap-2">
      <div class="flex-1">
        <Input
          v-model="localFilters.search"
          placeholder="Cari Kode Ruangan (R.101), Nama Ruangan, Gedung..."
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
      <!-- Gedung -->
      <Input
        v-model="localFilters.building"
        placeholder="Gedung (contoh: Gedung A)"
        size="sm"
        @update:model-value="debouncedEmit"
      />

      <!-- Tipe Ruangan -->
      <Select
        v-model="localFilters.room_type"
        :options="typeOptions"
        size="sm"
        @update:model-value="handleFilterChange"
      />

      <!-- Status -->
      <Select
        v-model="localFilters.status"
        :options="statusOptions"
        size="sm"
        @update:model-value="handleFilterChange"
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
