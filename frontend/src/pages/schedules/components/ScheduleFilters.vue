<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { Search, Filter, RotateCcw } from 'lucide-vue-next'
import { academicService } from '@/services/api/academic'
import { roomService } from '@/services/api/rooms'
import { classService } from '@/services/api/classes'
import type { Semester } from '@/types/academic'
import type { Room } from '@/types/room'
import type { AcademicClass } from '@/types/class'
import type { ScheduleFilters } from '@/types/schedule'
import { debounce } from '@/utils/debounce'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import Button from '@/components/ui/Button.vue'

interface Props {
  modelValue: ScheduleFilters
}

const props = defineProps<Props>()

const emit = defineEmits<{
  (e: 'update:modelValue', val: ScheduleFilters): void
  (e: 'change'): void
}>()

const localFilters = ref<ScheduleFilters>({ ...props.modelValue })
const semesters = ref<Semester[]>([])
const rooms = ref<Room[]>([])
const classes = ref<AcademicClass[]>([])
const showMobileFilters = ref<boolean>(false)

const dayOptions = [
  { label: 'Semua Hari Kuliah', value: '' },
  { label: 'Senin', value: 'monday' },
  { label: 'Selasa', value: 'tuesday' },
  { label: 'Rabu', value: 'wednesday' },
  { label: 'Kamis', value: 'thursday' },
  { label: 'Jumat', value: 'friday' },
  { label: 'Sabtu', value: 'saturday' },
]

const statusOptions = [
  { label: 'Semua Status Jadwal', value: '' },
  { label: 'Aktif Berjalan', value: 'active' },
  { label: 'Dibatalkan', value: 'cancelled' },
]

async function loadReferenceData() {
  try {
    const [semRes, roomRes, classRes] = await Promise.all([
      academicService.getSemesters(),
      roomService.list({ per_page: 100 }),
      classService.list({ per_page: 100 }),
    ])
    semesters.value = semRes.data || []
    rooms.value = roomRes.data || []
    classes.value = classRes.data || []
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
    day_of_week: '',
    room_id: '',
    class_id: '',
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
    <!-- Top Search & Mobile Filter Toggle -->
    <div class="flex items-center gap-2">
      <div class="flex-1">
        <Input
          v-model="localFilters.search"
          placeholder="Cari catatan jadwal atau kata kunci perkuliahan..."
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
        'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-2.5 pt-2 border-t border-slate-100',
        showMobileFilters ? 'block' : 'hidden sm:grid',
      ]"
    >
      <!-- Semester -->
      <Select
        v-model="localFilters.semester_id"
        size="sm"
        placeholder="Semua Semester"
        @update:model-value="handleFilterChange"
      >
        <option value="">Semua Semester Akademik</option>
        <option v-for="sem in semesters" :key="sem.id" :value="sem.id">
          {{ sem.name }} ({{ sem.status === 'active' ? 'Aktif' : 'Non-Aktif' }})
        </option>
      </Select>

      <!-- Hari -->
      <Select
        v-model="localFilters.day_of_week"
        :options="dayOptions"
        size="sm"
        @update:model-value="handleFilterChange"
      />

      <!-- Ruangan -->
      <Select
        v-model="localFilters.room_id"
        size="sm"
        placeholder="Semua Ruangan"
        @update:model-value="handleFilterChange"
      >
        <option value="">Semua Ruangan</option>
        <option v-for="r in rooms" :key="r.id" :value="r.id">
          {{ r.code }} — {{ r.name }} ({{ r.capacity }} Kursi)
        </option>
      </Select>

      <!-- Kelas Perkuliahan -->
      <Select
        v-model="localFilters.class_id"
        size="sm"
        placeholder="Semua Kelas"
        @update:model-value="handleFilterChange"
      >
        <option value="">Semua Kelas</option>
        <option v-for="c in classes" :key="c.id" :value="c.id">
          {{ c.code }} (Kelas {{ c.section }})
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
