<script setup lang="ts">
import { ref, watch } from 'vue'
import { studentService } from '@/services/api/students'
import { useToast } from '@/composables/useToast'
import type { Student, StudentStatus } from '@/types/student'
import Modal from '@/components/ui/Modal.vue'
import Button from '@/components/ui/Button.vue'
import Select from '@/components/ui/Select.vue'
import Textarea from '@/components/ui/Textarea.vue'
import FormField from '@/components/form/FormField.vue'
import StudentStatusBadge from './StudentStatusBadge.vue'
import Alert from '@/components/ui/Alert.vue'

interface Props {
  open: boolean
  student: Student
}

const props = defineProps<Props>()

const emit = defineEmits<{
  (e: 'update:open', val: boolean): void
  (e: 'success', updatedStudent: Student): void
}>()

const toast = useToast()
const loading = ref<boolean>(false)
const errorMessage = ref<string | null>(null)
const newStatus = ref<StudentStatus>(props.student.status)
const notes = ref<string>('')

const statusOptions = [
  { label: 'Aktif', value: 'active' },
  { label: 'Calon Mahasiswa', value: 'prospective' },
  { label: 'Cuti Akademik', value: 'leave' },
  { label: 'Non-Aktif', value: 'inactive' },
  { label: 'Lulus', value: 'graduated' },
  { label: 'Mengundurkan Diri', value: 'withdrawn' },
  { label: 'Dikeluarkan (DO)', value: 'dismissed' },
  { label: 'Wafat', value: 'deceased' },
]

watch(
  () => props.student,
  (st) => {
    if (st) {
      newStatus.value = st.status
      notes.value = ''
      errorMessage.value = null
    }
  },
  { immediate: true }
)

async function handleSubmit() {
  errorMessage.value = null
  loading.value = true
  try {
    const res = await studentService.changeStatus(props.student.id, {
      status: newStatus.value,
      notes: notes.value,
    })
    toast.success('Status mahasiswa berhasil diperbarui.')
    emit('success', res.data)
    emit('update:open', false)
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal mengubah status mahasiswa.'
  } finally {
    loading.value = false
  }
}

function handleClose() {
  emit('update:open', false)
}
</script>

<template>
  <Modal
    :open="open"
    title="Ubah Status Mahasiswa"
    size="md"
    @update:open="emit('update:open', $event)"
  >
    <div class="space-y-4">
      <Alert v-if="errorMessage" variant="danger">
        {{ errorMessage }}
      </Alert>

      <!-- Current Status Context -->
      <div class="p-3 bg-slate-50 rounded-lg border border-slate-200 flex items-center justify-between text-xs">
        <div>
          <span class="text-slate-500 block text-2xs">Mahasiswa:</span>
          <strong class="text-slate-800">{{ student.full_name }} ({{ student.student_number }})</strong>
        </div>
        <div class="text-right">
          <span class="text-slate-500 block text-2xs mb-0.5">Status Saat Ini:</span>
          <StudentStatusBadge :status="student.status" size="xs" />
        </div>
      </div>

      <!-- New Status Selector -->
      <FormField label="Pilih Status Baru" required>
        <Select
          v-model="newStatus"
          :options="statusOptions"
          required
          :disabled="loading"
          size="md"
        />
      </FormField>

      <!-- Reason / Notes -->
      <FormField label="Alasan / Catatan Perubahan Status">
        <Textarea
          v-model="notes"
          placeholder="Contoh: Pengajuan cuti semester ganjil disetujui Kaprodi..."
          :rows="3"
          :disabled="loading"
        />
      </FormField>
    </div>

    <template #footer>
      <Button variant="outline" size="sm" :disabled="loading" @click="handleClose">
        Batal
      </Button>
      <Button variant="primary" size="sm" :loading="loading" @click="handleSubmit">
        Simpan Status
      </Button>
    </template>
  </Modal>
</template>
