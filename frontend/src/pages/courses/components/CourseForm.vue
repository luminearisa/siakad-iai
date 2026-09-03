<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { courseService } from '@/services/api/courses'
import { academicService } from '@/services/api/academic'
import type { Course, CourseCreatePayload, CourseUpdatePayload, CourseTypeItem, CourseGroupItem } from '@/types/course'
import type { StudyProgram, Faculty } from '@/types/academic'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Card from '@/components/ui/Card.vue'
import Alert from '@/components/ui/Alert.vue'
import { X, Check } from 'lucide-vue-next'

interface Props {
  initialData?: Partial<Course | CourseCreatePayload | CourseUpdatePayload>
  isEdit?: boolean
  loading?: boolean
  serverErrors?: Record<string, string[]>
  errorMessage?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  initialData: () => ({}),
  isEdit: false,
  loading: false,
  serverErrors: () => ({}),
  errorMessage: null,
})

const emit = defineEmits<{
  (e: 'submit', payload: CourseCreatePayload | CourseUpdatePayload): void
  (e: 'cancel'): void
}>()

const router = useRouter()

// Dependencies
const courseTypes = ref<CourseTypeItem[]>([])
const courseGroups = ref<CourseGroupItem[]>([])
const faculties = ref<Faculty[]>([])
const studyPrograms = ref<StudyProgram[]>([])

const form = ref<CourseCreatePayload>({
  study_program_id: (props.initialData as any).study_program_id ?? null,
  course_type_id: (props.initialData as any).course_type_id ?? null,
  course_group_id: (props.initialData as any).course_group_id ?? null,
  code: props.initialData.code || '',
  name: props.initialData.name || '',
  short_name: props.initialData.short_name || '',
  description: props.initialData.description || '',
  theory_credits: props.initialData.theory_credits ?? 2,
  practical_credits: props.initialData.practical_credits ?? 0,
  field_practical_credits: (props.initialData as any).field_practical_credits ?? 0,
  simulation_credits: (props.initialData as any).simulation_credits ?? 0,
  seminar_credits: (props.initialData as any).seminar_credits ?? 0,
  credits: props.initialData.credits ?? 2,
  type: props.initialData.type || 'theory',
  category: props.initialData.category || 'MKWPS',
  status: props.initialData.status || 'active',
})

// Auto calculate Total SKS (Live Sum)
function recalculateTotalSks() {
  const theory = Number(form.value.theory_credits) || 0
  const practical = Number(form.value.practical_credits) || 0
  const field = Number(form.value.field_practical_credits) || 0
  const simulation = Number(form.value.simulation_credits) || 0
  const seminar = Number(form.value.seminar_credits) || 0

  form.value.credits = theory + practical + field + simulation + seminar

  if (theory > 0 && practical > 0) {
    form.value.type = 'mixed'
  } else if (practical > 0 && theory === 0) {
    form.value.type = 'practical'
  } else {
    form.value.type = 'theory'
  }
}

async function loadDependencies() {
  try {
    const [typesRes, groupsRes, prodiRes, facRes] = await Promise.all([
      courseService.listTypes(),
      courseService.listGroups(),
      academicService.getStudyPrograms({ per_page: 100 }),
      academicService.getFaculties(),
    ])
    courseTypes.value = typesRes.data || []
    courseGroups.value = groupsRes.data || []
    studyPrograms.value = prodiRes.data || []
    faculties.value = facRes.data || []

    // Set default course_type if not selected
    if (!form.value.course_type_id && courseTypes.value.length > 0) {
      form.value.course_type_id = courseTypes.value[0].id
    }
  } catch (err) {
    console.error('Failed to load dependencies:', err)
  }
}

function handleSubmit() {
  emit('submit', { ...form.value })
}

function handleCancel() {
  emit('cancel')
  router.push('/courses')
}

onMounted(() => {
  loadDependencies()
  recalculateTotalSks()
})
</script>

<template>
  <form class="space-y-4" @submit.prevent="handleSubmit">
    <!-- Top Action Bar matching Image 3 -->
    <div class="flex items-center justify-end gap-2">
      <Button
        type="button"
        variant="secondary"
        size="sm"
        :disabled="loading"
        @click="handleCancel"
      >
        <X class="w-4 h-4 text-rose-500 mr-1" />
        Batal
      </Button>

      <Button
        type="submit"
        variant="primary"
        size="sm"
        class="bg-brand-700 hover:bg-brand-800 text-white gap-1.5"
        :loading="loading"
      >
        <Check class="w-4 h-4" />
        Simpan Data
      </Button>
    </div>

    <!-- Server Error Alert -->
    <Alert v-if="errorMessage" variant="danger">
      {{ errorMessage }}
    </Alert>

    <!-- Main 2-Column Form matching Image 3 -->
    <Card class="p-6 bg-white border border-slate-200 rounded-xl shadow-2xs">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Kolom Kiri: Identitas & Pengampu -->
        <div class="space-y-4">
          <!-- Kode Mata Kuliah -->
          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-800">
              Kode Mata Kuliah <span class="text-rose-500">*</span>
            </label>
            <Input
              v-model="form.code"
              type="text"
              placeholder="Masukkan Kode Mata Kuliah"
              required
              :disabled="loading"
            />
          </div>

          <!-- Nama Mata Kuliah -->
          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-800">
              Nama Mata Kuliah <span class="text-rose-500">*</span>
            </label>
            <Input
              v-model="form.name"
              type="text"
              placeholder="Masukkan Nama Mata Kuliah"
              required
              :disabled="loading"
            />
          </div>

          <!-- Jenis Mata Kuliah -->
          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-800">
              Jenis Mata Kuliah <span class="text-rose-500">*</span>
            </label>
            <select
              v-model="form.course_type_id"
              class="w-full text-xs py-2 px-3 border border-slate-300 rounded-lg text-slate-900 bg-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none"
              required
              :disabled="loading"
            >
              <option :value="null" disabled>Pilih Jenis Mata Kuliah</option>
              <option v-for="t in courseTypes" :key="t.id" :value="t.id">
                {{ t.name }} ({{ t.code }})
              </option>
            </select>
          </div>

          <!-- Kelompok Mata Kuliah -->
          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-800">
              Kelompok Mata Kuliah
            </label>
            <select
              v-model="form.course_group_id"
              class="w-full text-xs py-2 px-3 border border-slate-300 rounded-lg text-slate-900 bg-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none"
              :disabled="loading"
            >
              <option :value="null">Pilih Kelompok Mata Kuliah</option>
              <option v-for="g in courseGroups" :key="g.id" :value="g.id">
                {{ g.name }}
              </option>
            </select>
          </div>

          <!-- Unit Pengampu (Hierarki Prodi) -->
          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-800">
              Unit Pengampu <span class="text-rose-500">*</span>
            </label>
            <select
              v-model="form.study_program_id"
              class="w-full text-xs py-2 px-3 border border-slate-300 rounded-lg text-slate-900 bg-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none"
              required
              :disabled="loading"
            >
              <option :value="null" disabled>Pilih Unit Pengampu</option>
              <option v-for="p in studyPrograms" :key="p.id" :value="p.id">
                {{ p.faculty?.name ? `${p.faculty.name} &mdash; ` : '' }}{{ p.name }} ({{ p.degree || 'S1' }})
              </option>
            </select>
          </div>

          <!-- Status Keaktifan -->
          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-800">
              Status Mata Kuliah
            </label>
            <select
              v-model="form.status"
              class="w-full text-xs py-2 px-3 border border-slate-300 rounded-lg text-slate-900 bg-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none"
              :disabled="loading"
            >
              <option value="active">Aktif</option>
              <option value="inactive">Nonaktif</option>
            </select>
          </div>
        </div>

        <!-- Kolom Kanan: Rincian SKS Lengkap (Persis Gambar 3) -->
        <div class="space-y-4">
          <!-- SKS Tatap Muka -->
          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-800">
              SKS Tatap Muka <span class="text-rose-500">*</span>
            </label>
            <input
              v-model.number="form.theory_credits"
              type="number"
              min="0"
              max="20"
              placeholder="Masukkan SKS Tatap Muka"
              class="w-full text-xs py-2 px-3 border border-slate-300 rounded-lg text-slate-900 bg-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none font-mono"
              required
              :disabled="loading"
              @input="recalculateTotalSks"
            />
          </div>

          <!-- SKS Praktikum -->
          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-800">
              SKS Praktikum <span class="text-rose-500">*</span>
            </label>
            <input
              v-model.number="form.practical_credits"
              type="number"
              min="0"
              max="20"
              placeholder="0"
              class="w-full text-xs py-2 px-3 border border-slate-300 rounded-lg text-slate-900 bg-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none font-mono"
              required
              :disabled="loading"
              @input="recalculateTotalSks"
            />
          </div>

          <!-- SKS Praktek Lapangan -->
          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-800">
              SKS Praktek Lapangan <span class="text-rose-500">*</span>
            </label>
            <input
              v-model.number="form.field_practical_credits"
              type="number"
              min="0"
              max="20"
              placeholder="0"
              class="w-full text-xs py-2 px-3 border border-slate-300 rounded-lg text-slate-900 bg-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none font-mono"
              required
              :disabled="loading"
              @input="recalculateTotalSks"
            />
          </div>

          <!-- SKS Simulasi -->
          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-800">
              SKS Simulasi <span class="text-rose-500">*</span>
            </label>
            <input
              v-model.number="form.simulation_credits"
              type="number"
              min="0"
              max="20"
              placeholder="0"
              class="w-full text-xs py-2 px-3 border border-slate-300 rounded-lg text-slate-900 bg-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none font-mono"
              required
              :disabled="loading"
              @input="recalculateTotalSks"
            />
          </div>

          <!-- SKS Seminar -->
          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-800">
              SKS Seminar <span class="text-rose-500">*</span>
            </label>
            <input
              v-model.number="form.seminar_credits"
              type="number"
              min="0"
              max="20"
              placeholder="0"
              class="w-full text-xs py-2 px-3 border border-slate-300 rounded-lg text-slate-900 bg-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none font-mono"
              required
              :disabled="loading"
              @input="recalculateTotalSks"
            />
          </div>

          <!-- Total SKS (Live Sum / Readonly) -->
          <div class="space-y-1.5">
            <label class="block text-xs font-bold text-slate-900">
              Total SKS <span class="text-rose-500">*</span>
            </label>
            <input
              :value="form.credits"
              type="number"
              readonly
              class="w-full text-xs py-2 px-3 border border-slate-300 bg-slate-100 rounded-lg text-slate-900 font-bold font-mono outline-none cursor-not-allowed"
            />
          </div>
        </div>
      </div>
    </Card>
  </form>
</template>
