<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import { Search, Filter, RotateCcw } from 'lucide-vue-next'
import { academicService } from '@/services/api/academic'
import { useAuthStore } from '@/stores/auth'
import type { Semester } from '@/types/academic'
import type { EnrollmentFilters } from '@/types/enrollment'
import { debounce } from '@/utils/debounce'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import Button from '@/components/ui/Button.vue'

interface Props {
  modelValue: EnrollmentFilters
}

const props = defineProps<Props>()

const emit = defineEmits<{
  (e: 'update:modelValue', val: EnrollmentFilters): void
  (e: 'change'): void
}>()

const authStore = useAuthStore()
const localFilters = ref<EnrollmentFilters>({ ...props.modelValue })
const semesters = ref<Semester[]>([])
const showMobileFilters = ref<boolean>(false)

const isStudent = computed(() => authStore.isStudent)

const statusOptions = [
  { label: 'Semua Status KRS', value: '' },
  { label: 'Draft (Rancangan)', value: 'draft' },
  { label: 'Submitted (Menunggu Persetujuan)', value: 'submitted' },
  { label: 'Approved (Disetujui)', value: 'approved' },
  { label: 'Revision Required (Perlu Revisi)', value: 'revision_required' },
  { label: 'Rejected (Ditolak)', value: 'rejected' },
  { label: 'Locked (Terkunci)', value: 'locked' },
]

async function loadSemesters() {
  try {
    const res = await academicService.getSemesters()
    semesters.value = res.data || []
  } catch {
    // Fallback
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
  loadSemesters()
})
</script>

<template>
  <div class="bg-white p-3 sm:p-4 rounded-lg border border-slate-200 shadow-subtle space-y-3">
    <!-- Top Search & Mobile Filter Toggle -->
    <div class="flex items-center gap-2">
      <div class="flex-1">
        <Input
          v-if="!isStudent"
          v-model="localFilters.search"
          placeholder="Cari berdasarkan nama mahasiswa, NIM, atau catatan KRS..."
          size="sm"
          @update:model-value="debouncedEmit"
        >
          <template #prefix>
            <Search class="w-3.5 h-3.5" />
          </template>
        </Input>
        <span v-else class="text-xs font-semibold text-slate-700">
          Filter Riwayat KRS Saya
        </span>
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
        'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5 pt-2 border-t border-slate-100',
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
          class="text-slate-600 hover:text-slate-900"
          @click="resetFilters"
        >
          <RotateCcw class="w-3.5 h-3.5" />
          <span>Reset Filter</span>
        </Button>
      </div>
    </div>
  </div>
</template>
