<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { academicService } from '@/services/api/academic'
import { courseService } from '@/services/api/courses'
import type { Semester, StudyProgram } from '@/types/academic'
import type { Course } from '@/types/course'
import type { AcademicClass, ClassCreatePayload, ClassUpdatePayload } from '@/types/class'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import Textarea from '@/components/ui/Textarea.vue'
import Card from '@/components/ui/Card.vue'
import FormField from '@/components/form/FormField.vue'
import FormActions from '@/components/form/FormActions.vue'
import Alert from '@/components/ui/Alert.vue'

interface Props {
  initialData?: Partial<AcademicClass | ClassCreatePayload | ClassUpdatePayload>
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
  (e: 'submit', payload: ClassCreatePayload | ClassUpdatePayload): void
  (e: 'cancel'): void
}>()

const router = useRouter()
const semesters = ref<Semester[]>([])
const studyPrograms = ref<StudyProgram[]>([])
const courses = ref<Course[]>([])

const form = ref<ClassCreatePayload>({
  semester_id: props.initialData.semester_id || ('' as any),
  course_id: props.initialData.course_id || ('' as any),
  study_program_id: props.initialData.study_program_id || ('' as any),
  code: props.initialData.code || '',
  name: props.initialData.name || '',
  section: props.initialData.section || 'A',
  capacity: props.initialData.capacity ?? 40,
  status: props.initialData.status || 'draft',
  notes: props.initialData.notes || '',
})

const statusOptions = [
  { label: 'Draft (Penyusunan)', value: 'draft' },
  { label: 'Buka untuk KRS (Open)', value: 'open' },
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

    if (!form.value.semester_id) {
      const activeSem = semesters.value.find(s => s.status === 'active')
      if (activeSem) form.value.semester_id = activeSem.id
      else if (semesters.value.length > 0) form.value.semester_id = semesters.value[0].id
    }
  } catch {
    // Fallback
  }
}

function handleCourseChange() {
  const selected = courses.value.find(c => c.id === Number(form.value.course_id))
  if (selected) {
    if (!props.isEdit) {
      form.value.code = `${selected.code}-${form.value.section}`
      form.value.name = `Kelas ${selected.name} (${form.value.section})`
    }
  }
}

function handleSectionChange() {
  const selected = courses.value.find(c => c.id === Number(form.value.course_id))
  if (selected && !props.isEdit) {
    form.value.code = `${selected.code}-${form.value.section}`
    form.value.name = `Kelas ${selected.name} (${form.value.section})`
  }
}

function getError(field: string): string | null {
  if (props.serverErrors && props.serverErrors[field] && props.serverErrors[field].length > 0) {
    return props.serverErrors[field][0]
  }
  return null
}

function handleSubmit() {
  emit('submit', { ...form.value })
}

function handleCancel() {
  emit('cancel')
  router.back()
}

onMounted(() => {
  loadReferenceData()
})
</script>

<template>
  <form class="space-y-5" @submit.prevent="handleSubmit">
    <!-- Server Error Alert -->
    <Alert v-if="errorMessage" variant="danger">
      {{ errorMessage }}
    </Alert>

    <!-- 1. Konteks Akademik & Mata Kuliah -->
    <Card>
      <template #header>
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
          1. Konteks Akademik & Mata Kuliah
        </h3>
      </template>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
        <!-- Semester Akademik -->
        <FormField label="Semester Akademik" required :error="getError('semester_id')">
          <Select
            v-model="form.semester_id"
            required
            :disabled="loading || isEdit"
          >
            <option v-for="sem in semesters" :key="sem.id" :value="sem.id">
              {{ sem.name }} ({{ sem.status === 'active' ? 'Aktif' : 'Non-Aktif' }})
            </option>
          </Select>
        </FormField>

        <!-- Mata Kuliah -->
        <FormField label="Mata Kuliah" required :error="getError('course_id')">
          <Select
            v-model="form.course_id"
            required
            :disabled="loading || isEdit"
            @update:model-value="handleCourseChange"
          >
            <option value="">-- Pilih Mata Kuliah --</option>
            <option v-for="c in courses" :key="c.id" :value="c.id">
              {{ c.code }} — {{ c.name }} ({{ c.credits }} SKS)
            </option>
          </Select>
        </FormField>

        <!-- Program Studi -->
        <FormField label="Program Studi (Pengampu/Homebase)" :error="getError('study_program_id')">
          <Select
            v-model="form.study_program_id"
            :disabled="loading"
          >
            <option value="">-- Bersama / Umum --</option>
            <option v-for="sp in studyPrograms" :key="sp.id" :value="sp.id">
              {{ sp.code }} — {{ sp.name }} ({{ sp.degree }})
            </option>
          </Select>
        </FormField>
      </div>
    </Card>

    <!-- 2. Konfigurasi Kelas & Kuota -->
    <Card>
      <template #header>
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
          2. Konfigurasi Seksi & Kapasitas Kursi
        </h3>
      </template>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
        <!-- Seksi / Paralel -->
        <FormField label="Seksi / Kelas Paralel" required :error="getError('section')">
          <Input
            v-model="form.section"
            placeholder="Contoh: A, B, atau PAI-1A"
            required
            maxlength="20"
            :disabled="loading"
            @input="handleSectionChange"
          />
        </FormField>

        <!-- Kode Kelas -->
        <FormField label="Kode Kelas" required :error="getError('code')">
          <Input
            v-model="form.code"
            placeholder="Contoh: PAI-101-A"
            required
            maxlength="50"
            :disabled="loading"
          />
        </FormField>

        <!-- Kapasitas Kursi -->
        <FormField label="Kapasitas Kursi (Kuota)" required :error="getError('capacity')">
          <Input
            v-model.number="form.capacity"
            type="number"
            min="1"
            max="500"
            required
            :disabled="loading"
          />
        </FormField>

        <!-- Status -->
        <FormField label="Status Kelas" :error="getError('status')">
          <Select
            v-model="form.status"
            :options="statusOptions"
            :disabled="loading"
          />
        </FormField>

        <!-- Nama Label Kelas -->
        <div class="sm:col-span-2 lg:col-span-4">
          <FormField label="Nama Lengkap Kelas" :error="getError('name')">
            <Input
              v-model="form.name"
              placeholder="Contoh: Kelas Pengantar Studi Islam (A)"
              maxlength="255"
              :disabled="loading"
            />
          </FormField>
        </div>
      </div>
    </Card>

    <!-- 3. Catatan Tambahan -->
    <Card>
      <template #header>
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
          3. Catatan Tambahan
        </h3>
      </template>

      <FormField label="Catatan Penyelenggaraan Kelas" :error="getError('notes')">
        <Textarea
          v-model="form.notes"
          placeholder="Catatan kelas, instruksi pengajaran, atau syarat khusus..."
          :rows="2"
          :disabled="loading"
        />
      </FormField>
    </Card>

    <!-- Form Actions -->
    <FormActions align="right">
      <Button variant="outline" size="md" :disabled="loading" @click="handleCancel">
        Batal
      </Button>
      <Button type="submit" variant="primary" size="md" :loading="loading">
        {{ isEdit ? 'Simpan Perubahan' : 'Buka Kelas Perkuliahan' }}
      </Button>
    </FormActions>
  </form>
</template>
