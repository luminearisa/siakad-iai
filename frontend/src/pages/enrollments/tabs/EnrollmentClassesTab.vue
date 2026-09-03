<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { classService } from '@/services/api/classes'
import type { AcademicClass } from '@/types/class'
import type { StudentEnrollment, StudentEnrollmentItem } from '@/types/enrollment'
import Card from '@/components/ui/Card.vue'
import EnrollmentSummary from '../components/EnrollmentSummary.vue'
import EnrollmentItemList from '../components/EnrollmentItemList.vue'
import ClassSelectionTable from '../components/ClassSelectionTable.vue'
import ClassSelectionFilters from '../components/ClassSelectionFilters.vue'
import EnrollmentValidationAlert from '../components/EnrollmentValidationAlert.vue'

interface Props {
  enrollment: StudentEnrollment
  submittingClassId?: number | null
  validationErrors?: string[] | Record<string, string[]> | null
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  submittingClassId: null,
  validationErrors: null,
  loading: false,
})

const emit = defineEmits<{
  (e: 'add-class', classItem: AcademicClass): void
  (e: 'remove-item', item: StudentEnrollmentItem): void
  (e: 'dismiss-validation'): void
}>()

const availableClasses = ref<AcademicClass[]>([])
const classLoading = ref<boolean>(false)
const classFilters = ref<{ search?: string; day_of_week?: string }>({
  search: '',
  day_of_week: '',
})

const isEditable = computed(() => {
  return props.enrollment.status === 'draft' || props.enrollment.status === 'revision_required'
})

const filteredClasses = computed(() => {
  let list = availableClasses.value
  if (classFilters.value.search) {
    const q = classFilters.value.search.toLowerCase()
    list = list.filter(
      (c) =>
        (c.name || '').toLowerCase().includes(q) ||
        (c.code || '').toLowerCase().includes(q) ||
        (c.course?.name && c.course.name.toLowerCase().includes(q)) ||
        (c.course?.code && c.course.code.toLowerCase().includes(q))
    )
  }
  if (classFilters.value.day_of_week) {
    list = list.filter((c) =>
      c.schedules?.some((s: any) => s.day_of_week?.toLowerCase() === classFilters.value.day_of_week?.toLowerCase())
    )
  }
  return list
})

async function loadAvailableClasses() {
  if (!props.enrollment.semester_id) return
  classLoading.value = true
  try {
    const res = await classService.list({
      semester_id: props.enrollment.semester_id,
      per_page: 100,
    })
    availableClasses.value = res.data || []
  } catch {
    availableClasses.value = []
  } finally {
    classLoading.value = false
  }
}

onMounted(() => {
  loadAvailableClasses()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Validation Alerts Banner -->
    <EnrollmentValidationAlert
      v-if="validationErrors"
      :errors="validationErrors"
      dismissible
      @dismiss="emit('dismiss-validation')"
    />

    <!-- Top SKS & Course Count Metrics -->
    <EnrollmentSummary :enrollment="enrollment" />

    <!-- Section 1: Daftar Mata Kuliah yang Telah Diambil (KRS) -->
    <Card>
      <template #header>
        <div class="flex items-center justify-between">
          <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
            Daftar Kelas Terdaftar ({{ enrollment.items?.length || 0 }} Mata Kuliah · {{ enrollment.total_credits || 0 }} SKS)
          </h3>
          <span v-if="!isEditable" class="text-2xs font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded">
            Mode Baca Saja (Terkunci)
          </span>
        </div>
      </template>

      <EnrollmentItemList
        v-if="enrollment.items && enrollment.items.length > 0"
        :enrollment="enrollment"
        :items="enrollment.items"
        :loading="loading"
        @remove-item="emit('remove-item', $event)"
      />

      <div v-else class="py-8 text-center text-xs text-slate-400 space-y-1">
        <p class="font-medium">Belum ada mata kuliah yang diambil untuk semester ini.</p>
        <p v-if="isEditable" class="text-slate-500">
          Silakan pilih kelas perkuliahan yang tersedia pada tabel katalog di bawah ini.
        </p>
      </div>
    </Card>

    <!-- Section 2: Katalog Pemilihan Kelas Perkuliahan (Only if editable) -->
    <Card v-if="isEditable">
      <template #header>
        <div class="space-y-1">
          <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
            Katalog Kelas Perkuliahan Tersedia
          </h3>
          <p class="text-2xs text-slate-500">
            Pilih kelas mata kuliah yang dibuka pada semester akademik ini. Sistem akan memvalidasi prasyarat, jadwal, dan batas SKS.
          </p>
        </div>
      </template>

      <div class="space-y-3">
        <!-- Class Search & Day Filters -->
        <ClassSelectionFilters
          v-model="classFilters"
        />

        <!-- Classes Table -->
        <ClassSelectionTable
          :classes="filteredClasses"
          :enrollment="enrollment"
          :loading="classLoading || loading"
          :submitting-class-id="submittingClassId"
          @add-class="emit('add-class', $event)"
        />
      </div>
    </Card>
  </div>
</template>
