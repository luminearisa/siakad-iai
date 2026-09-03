<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { lecturerService } from '@/services/api/lecturers'
import { classService } from '@/services/api/classes'
import { useToast } from '@/composables/useToast'
import type { Lecturer } from '@/types/lecturer'
import type { AcademicClass, ClassLecturer, AssignClassLecturerPayload, ClassLecturerRole } from '@/types/class'
import Modal from '@/components/ui/Modal.vue'
import Button from '@/components/ui/Button.vue'
import Select from '@/components/ui/Select.vue'
import FormField from '@/components/form/FormField.vue'
import Alert from '@/components/ui/Alert.vue'

interface Props {
  open: boolean
  academicClass: AcademicClass
}

const props = defineProps<Props>()

const emit = defineEmits<{
  (e: 'update:open', val: boolean): void
  (e: 'success', newAssignment: ClassLecturer): void
}>()

const toast = useToast()
const lecturers = ref<Lecturer[]>([])
const loading = ref<boolean>(false)
const errorMessage = ref<string | null>(null)

const form = ref<{
  lecturer_id: number | string
  role: ClassLecturerRole
}>({
  lecturer_id: '',
  role: 'primary',
})

const roleOptions = [
  { label: 'Dosen Utama (Pengampu)', value: 'primary' },
  { label: 'Dosen Tim (Team Teaching / Co-Lecturer)', value: 'co_lecturer' },
  { label: 'Asisten Dosen / Lab', value: 'assistant' },
]

async function loadLecturers() {
  try {
    const res = await lecturerService.list({ per_page: 200 })
    lecturers.value = res.data || []
    if (lecturers.value.length > 0) {
      form.value.lecturer_id = lecturers.value[0].id
    }
  } catch {
    lecturers.value = []
  }
}

async function handleSubmit() {
  errorMessage.value = null
  loading.value = true

  const payload: AssignClassLecturerPayload = {
    lecturer_id: Number(form.value.lecturer_id),
    role: form.value.role,
  }

  try {
    const res = await classService.assignLecturer(props.academicClass.id, payload)
    toast.success('Dosen pengampu berhasil ditugaskan ke kelas ini.')
    emit('success', res.data)
    emit('update:open', false)
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal menugaskan dosen pengampu.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadLecturers()
})
</script>

<template>
  <Modal
    :open="open"
    :title="`Tugaskan Dosen Pengampu — Kelas ${academicClass.section}`"
    size="md"
    @update:open="emit('update:open', $event)"
  >
    <form class="space-y-4" @submit.prevent="handleSubmit">
      <Alert v-if="errorMessage" variant="danger">
        {{ errorMessage }}
      </Alert>

      <div class="p-3 bg-slate-50 rounded-lg border border-slate-200 text-xs">
        <span class="text-slate-500 text-2xs block">Mata Kuliah:</span>
        <strong class="text-slate-900">{{ academicClass.course?.name || academicClass.name }}</strong>
        <span class="text-slate-500 ml-1">({{ academicClass.course?.code }}) — Kelas {{ academicClass.section }}</span>
      </div>

      <!-- Pilih Dosen -->
      <FormField label="Pilih Dosen Pengampu" required>
        <Select v-model="form.lecturer_id" required :disabled="loading">
          <option v-for="lec in lecturers" :key="lec.id" :value="lec.id">
            {{ lec.full_name }}<template v-if="lec.academic_degree">, {{ lec.academic_degree }}</template> (NIDN: {{ lec.nidn || lec.nip || '-' }})
          </option>
        </Select>
      </FormField>

      <!-- Peran Pengajaran -->
      <FormField label="Peran / Beban Tugas Pengajaran" required>
        <Select
          v-model="form.role"
          :options="roleOptions"
          required
          :disabled="loading"
        />
      </FormField>
    </form>

    <template #footer>
      <Button variant="outline" size="sm" :disabled="loading" @click="emit('update:open', false)">
        Batal
      </Button>
      <Button variant="primary" size="sm" :loading="loading" @click="handleSubmit">
        Tugaskan Dosen
      </Button>
    </template>
  </Modal>
</template>
