<script setup lang="ts">
import { ref, watch } from 'vue'
import { lecturerService } from '@/services/api/lecturers'
import { useToast } from '@/composables/useToast'
import type { Lecturer, LecturerStatus } from '@/types/lecturer'
import Modal from '@/components/ui/Modal.vue'
import Button from '@/components/ui/Button.vue'
import Select from '@/components/ui/Select.vue'
import Textarea from '@/components/ui/Textarea.vue'
import FormField from '@/components/form/FormField.vue'
import LecturerStatusBadge from './LecturerStatusBadge.vue'
import Alert from '@/components/ui/Alert.vue'

interface Props {
  open: boolean
  lecturer: Lecturer
}

const props = defineProps<Props>()

const emit = defineEmits<{
  (e: 'update:open', val: boolean): void
  (e: 'success', updatedLecturer: Lecturer): void
}>()

const toast = useToast()
const loading = ref<boolean>(false)
const errorMessage = ref<string | null>(null)
const newStatus = ref<LecturerStatus>(props.lecturer.status)
const notes = ref<string>('')

const statusOptions = [
  { label: 'Aktif Mengajar', value: 'active' },
  { label: 'Non-Aktif', value: 'inactive' },
  { label: 'Purnatugas / Pensiun', value: 'retired' },
  { label: 'Mengundurkan Diri', value: 'resigned' },
  { label: 'Wafat', value: 'deceased' },
]

watch(
  () => props.lecturer,
  (lec) => {
    if (lec) {
      newStatus.value = lec.status
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
    const res = await lecturerService.changeStatus(props.lecturer.id, {
      status: newStatus.value,
      notes: notes.value,
    })
    toast.success('Status dosen berhasil diperbarui.')
    emit('success', res.data)
    emit('update:open', false)
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal memperbarui status dosen.'
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
    title="Ubah Status Dosen"
    size="md"
    @update:open="emit('update:open', $event)"
  >
    <div class="space-y-4">
      <Alert v-if="errorMessage" variant="danger">
        {{ errorMessage }}
      </Alert>

      <!-- Current Context -->
      <div class="p-3 bg-slate-50 rounded-lg border border-slate-200 flex items-center justify-between text-xs">
        <div>
          <span class="text-slate-500 block text-2xs">Nama Dosen:</span>
          <strong class="text-slate-800">{{ lecturer.full_name }}</strong>
        </div>
        <div class="text-right">
          <span class="text-slate-500 block text-2xs mb-0.5">Status Saat Ini:</span>
          <LecturerStatusBadge :status="lecturer.status" size="xs" />
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
          placeholder="Contoh: Pengajuan purnatugas disetujui Senat Akademik..."
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
