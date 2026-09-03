<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { courseService } from '@/services/api/courses'
import { curriculumService } from '@/services/api/curriculum'
import { useToast } from '@/composables/useToast'
import type { Course } from '@/types/course'
import type { CurriculumSemester, CurriculumSubject, AddCurriculumSubjectPayload } from '@/types/curriculum'
import Modal from '@/components/ui/Modal.vue'
import Button from '@/components/ui/Button.vue'
import Select from '@/components/ui/Select.vue'
import Input from '@/components/ui/Input.vue'
import Textarea from '@/components/ui/Textarea.vue'
import FormField from '@/components/form/FormField.vue'
import Alert from '@/components/ui/Alert.vue'

interface Props {
  open: boolean
  semester: CurriculumSemester
}

const props = defineProps<Props>()

const emit = defineEmits<{
  (e: 'update:open', val: boolean): void
  (e: 'success', newSubject: CurriculumSubject): void
}>()

const toast = useToast()
const courses = ref<Course[]>([])
const loading = ref<boolean>(false)
const errorMessage = ref<string | null>(null)

const form = ref<{
  course_id: number | string
  is_mandatory: string
  credits_override: number | null
  minimum_grade: string
  notes: string
}>({
  course_id: '',
  is_mandatory: 'true',
  credits_override: null,
  minimum_grade: 'C',
  notes: '',
})

const mandatoryOptions = [
  { label: 'Wajib (Mandatory)', value: 'true' },
  { label: 'Pilihan (Elective)', value: 'false' },
]

const gradeOptions = [
  { label: 'Minimal D', value: 'D' },
  { label: 'Minimal C (Standar)', value: 'C' },
  { label: 'Minimal B', value: 'B' },
  { label: 'Minimal A', value: 'A' },
]

async function loadCourses() {
  try {
    const res = await courseService.list({ per_page: 200 })
    courses.value = res.data || []
    if (courses.value.length > 0) {
      form.value.course_id = courses.value[0].id
    }
  } catch {
    courses.value = []
  }
}

async function handleSubmit() {
  errorMessage.value = null
  loading.value = true

  const payload: AddCurriculumSubjectPayload = {
    course_id: Number(form.value.course_id),
    is_mandatory: String(form.value.is_mandatory) === 'true',
    credits_override: form.value.credits_override ? Number(form.value.credits_override) : null,
    minimum_grade: form.value.minimum_grade || 'C',
    notes: form.value.notes || null,
  }

  try {
    const res = await curriculumService.addSubject(props.semester.id, payload)
    toast.success('Mata kuliah berhasil ditambahkan ke semester kurikulum.')
    emit('success', res.data)
    emit('update:open', false)
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal menambahkan mata kuliah ke kurikulum.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadCourses()
})
</script>

<template>
  <Modal
    :open="open"
    :title="`Tambah Mata Kuliah — Semester ${semester.semester_number}`"
    size="md"
    @update:open="emit('update:open', $event)"
  >
    <form class="space-y-4" @submit.prevent="handleSubmit">
      <Alert v-if="errorMessage" variant="danger">
        {{ errorMessage }}
      </Alert>

      <!-- Pilih Mata Kuliah -->
      <FormField label="Pilih Mata Kuliah" required>
        <Select v-model="form.course_id" required :disabled="loading">
          <option v-for="c in courses" :key="c.id" :value="c.id">
            {{ c.code }} — {{ c.name }} ({{ c.credits }} SKS)
          </option>
        </Select>
      </FormField>

      <!-- Sifat Mata Kuliah -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
        <FormField label="Sifat Mata Kuliah" required>
          <Select
            v-model="form.is_mandatory"
            :options="mandatoryOptions"
            required
            :disabled="loading"
          />
        </FormField>

        <FormField label="Nilai Minimal Kelulusan">
          <Select
            v-model="form.minimum_grade"
            :options="gradeOptions"
            :disabled="loading"
          />
        </FormField>
      </div>

      <!-- Override SKS -->
      <FormField label="Override Bobot SKS (Opsional)">
        <Input
          v-model.number="form.credits_override"
          type="number"
          min="1"
          max="20"
          placeholder="Biarkan kosong jika mengikuti SKS default mata kuliah"
          :disabled="loading"
        />
      </FormField>

      <!-- Catatan Distribusi -->
      <FormField label="Catatan Tambahan">
        <Textarea
          v-model="form.notes"
          placeholder="Contoh: Mata kuliah paket semester gasal..."
          :rows="2"
          :disabled="loading"
        />
      </FormField>
    </form>

    <template #footer>
      <Button variant="outline" size="sm" :disabled="loading" @click="emit('update:open', false)">
        Batal
      </Button>
      <Button variant="primary" size="sm" :loading="loading" @click="handleSubmit">
        Tambahkan ke Kurikulum
      </Button>
    </template>
  </Modal>
</template>
