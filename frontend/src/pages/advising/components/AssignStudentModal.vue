<script setup lang="ts">
import { ref, watch } from 'vue'
import { AlertTriangle, UserCheck } from 'lucide-vue-next'
import { studentService } from '@/services/api/students'
import { advisingService } from '@/services/api/advising'
import { useToast } from '@/composables/useToast'
import type { Student } from '@/types/student'
import type { Lecturer } from '@/types/lecturer'
import Modal from '@/components/ui/Modal.vue'
import FormField from '@/components/form/FormField.vue'
import Select from '@/components/ui/Select.vue'
import Input from '@/components/ui/Input.vue'
import Textarea from '@/components/ui/Textarea.vue'
import Button from '@/components/ui/Button.vue'
import Alert from '@/components/ui/Alert.vue'

const props = defineProps<{
  open: boolean
  lecturer: Lecturer
}>()

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void
  (e: 'assigned'): void
}>()

const toast = useToast()
const students = ref<Student[]>([])
const loadingStudents = ref<boolean>(false)
const submitting = ref<boolean>(false)
const errorMessage = ref<string | null>(null)
const serverErrors = ref<Record<string, string[]>>({})

const selectedStudentId = ref<number | string>('')
const startDate = ref<string>(new Date().toISOString().split('T')[0])
const notes = ref<string>('')
const currentAdvisorWarning = ref<string | null>(null)
const checkingAdvisor = ref<boolean>(false)

async function loadStudents() {
  loadingStudents.value = true
  try {
    const res = await studentService.list({ per_page: 100, status: 'active' })
    students.value = res.data || []
  } catch {
    students.value = []
  } finally {
    loadingStudents.value = false
  }
}

async function checkStudentAdvisor(studentId: number | string) {
  currentAdvisorWarning.value = null
  if (!studentId) return

  checkingAdvisor.value = true
  try {
    const res = await advisingService.getStudentAdvisor(studentId)
    if (res.data && res.data.lecturer) {
      if (res.data.lecturer_id === props.lecturer.id) {
        currentAdvisorWarning.value = `Mahasiswa ini sudah dibimbing oleh ${props.lecturer.full_name}.`
      } else {
        currentAdvisorWarning.value = `Perhatian: Mahasiswa ini saat ini memiliki Dosen PA aktif: ${res.data.lecturer.full_name}. Penugasan baru akan mengalihkan status PA lama menjadi dialihkan (transferred).`
      }
    }
  } catch {
    // No active advisor found (404 is normal)
  } finally {
    checkingAdvisor.value = false
  }
}

async function handleSubmit() {
  if (!selectedStudentId.value) return
  submitting.value = true
  errorMessage.value = null
  serverErrors.value = {}

  try {
    await advisingService.assignAdvisor({
      student_id: Number(selectedStudentId.value),
      lecturer_id: props.lecturer.id,
      start_date: startDate.value,
      notes: notes.value || undefined,
    })

    toast.success('Mahasiswa bimbingan berhasil ditambahkan.')
    emit('update:open', false)
    emit('assigned')
    resetForm()
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal menetapkan mahasiswa bimbingan.'
    if (err.errors) {
      serverErrors.value = err.errors
    }
  } finally {
    submitting.value = false
  }
}

function resetForm() {
  selectedStudentId.value = ''
  startDate.value = new Date().toISOString().split('T')[0]
  notes.value = ''
  currentAdvisorWarning.value = null
  errorMessage.value = null
  serverErrors.value = {}
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      loadStudents()
    } else {
      resetForm()
    }
  }
)

watch(selectedStudentId, (newId) => {
  if (newId) {
    checkStudentAdvisor(newId)
  } else {
    currentAdvisorWarning.value = null
  }
})
</script>

<template>
  <Modal
    :open="open"
    title="Tambah Mahasiswa Bimbingan Akademik"
    size="md"
    @update:open="emit('update:open', $event)"
  >
    <form class="space-y-4 text-xs" @submit.prevent="handleSubmit">
      <Alert v-if="errorMessage" variant="danger">
        {{ errorMessage }}
      </Alert>

      <div class="bg-slate-50 border border-slate-200 rounded-md p-3 text-slate-700">
        <span class="text-2xs text-slate-400 font-semibold uppercase block">Dosen Pembimbing Akademik:</span>
        <span class="font-bold text-slate-900 text-sm">{{ lecturer.full_name }}</span>
        <span class="text-2xs text-slate-500 block">NIDN: {{ lecturer.nidn || '-' }} · {{ lecturer.homebase_study_program?.name || 'Homebase Prodi' }}</span>
      </div>

      <!-- Student Selector -->
      <FormField
        label="Pilih Mahasiswa"
        required
        :error="serverErrors.student_id?.[0]"
      >
        <Select
          v-model="selectedStudentId"
          required
          :disabled="submitting || loadingStudents"
        >
          <option value="">-- Pilih Mahasiswa Aktif --</option>
          <option v-for="s in students" :key="s.id" :value="s.id">
            {{ s.student_number }} — {{ s.full_name }} ({{ s.study_program?.name || 'Prodi' }})
          </option>
        </Select>
      </FormField>

      <!-- Warning if student already has an advisor -->
      <div
        v-if="currentAdvisorWarning"
        class="p-2.5 rounded-md bg-amber-50 border border-amber-200 text-amber-900 text-xs flex items-start gap-2"
      >
        <AlertTriangle class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
        <p class="leading-relaxed">{{ currentAdvisorWarning }}</p>
      </div>

      <!-- Start Date -->
      <FormField
        label="Tanggal Mulai Penugasan"
        required
        :error="serverErrors.start_date?.[0]"
      >
        <Input
          v-model="startDate"
          type="date"
          required
          :disabled="submitting"
        />
      </FormField>

      <!-- Notes -->
      <FormField
        label="Catatan Penugasan (Opsional)"
        :error="serverErrors.notes?.[0]"
      >
        <Textarea
          v-model="notes"
          placeholder="Catatan penugasan khusus, SK Dekan, atau arahan bimbingan..."
          :rows="3"
          :disabled="submitting"
        />
      </FormField>

      <!-- Modal Footer inside Form -->
      <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
        <Button variant="outline" size="sm" :disabled="submitting" @click="emit('update:open', false)">
          Batal
        </Button>
        <Button
          type="submit"
          variant="primary"
          size="sm"
          :loading="submitting"
          :disabled="!selectedStudentId || checkingAdvisor"
        >
          <UserCheck class="w-3.5 h-3.5" />
          <span>Tetapkan Sebagai Dosen PA</span>
        </Button>
      </div>
    </form>
  </Modal>
</template>
