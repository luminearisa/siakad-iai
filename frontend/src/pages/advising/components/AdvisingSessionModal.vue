<script setup lang="ts">
import { ref, watch } from 'vue'
import { MessageSquarePlus, Edit2 } from 'lucide-vue-next'
import { advisingService } from '@/services/api/advising'
import { useToast } from '@/composables/useToast'
import type { AcademicAdvisor, AdvisingSession, AdvisingSessionStatus } from '@/types/advising'
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
  advisees: AcademicAdvisor[]
  sessionToEdit?: AdvisingSession | null
  presetAdvisor?: AcademicAdvisor | null
}>()

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void
  (e: 'saved'): void
}>()

const toast = useToast()
const submitting = ref<boolean>(false)
const errorMessage = ref<string | null>(null)
const serverErrors = ref<Record<string, string[]>>({})

const form = ref({
  student_id: '' as any,
  session_date: new Date().toISOString().split('T')[0],
  topic: '',
  notes: '',
  status: 'completed' as AdvisingSessionStatus,
})

const isEditing = ref<boolean>(false)

function initForm() {
  if (props.sessionToEdit) {
    isEditing.value = true
    form.value = {
      student_id: props.sessionToEdit.student_id,
      session_date: props.sessionToEdit.session_date,
      topic: props.sessionToEdit.topic || '',
      notes: props.sessionToEdit.notes || '',
      status: props.sessionToEdit.status || 'completed',
    }
  } else {
    isEditing.value = false
    form.value = {
      student_id: props.presetAdvisor?.student_id || (props.advisees.length > 0 ? props.advisees[0].student_id : ''),
      session_date: new Date().toISOString().split('T')[0],
      topic: 'Konsultasi Rencana Studi & Perkuliahan',
      notes: '',
      status: 'completed',
    }
  }
  errorMessage.value = null
  serverErrors.value = {}
}

async function handleSubmit() {
  if (!form.value.student_id || !form.value.notes.trim()) return
  submitting.value = true
  errorMessage.value = null
  serverErrors.value = {}

  try {
    if (isEditing.value && props.sessionToEdit) {
      await advisingService.updateSession(props.sessionToEdit.id, {
        session_date: form.value.session_date,
        topic: form.value.topic || undefined,
        notes: form.value.notes,
        status: form.value.status,
      })
      toast.success('Sesi bimbingan akademik berhasil diperbarui.')
    } else {
      await advisingService.createSession({
        student_id: Number(form.value.student_id),
        lecturer_id: props.lecturer.id,
        session_date: form.value.session_date,
        topic: form.value.topic || undefined,
        notes: form.value.notes,
        status: form.value.status,
      })
      toast.success('Sesi bimbingan akademik berhasil dicatat.')
    }

    emit('update:open', false)
    emit('saved')
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal menyimpan sesi bimbingan.'
    if (err.errors) {
      serverErrors.value = err.errors
    }
  } finally {
    submitting.value = false
  }
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      initForm()
    }
  }
)
</script>

<template>
  <Modal
    :open="open"
    :title="isEditing ? 'Edit Catatan Sesi Bimbingan' : 'Catat Sesi Bimbingan Akademik'"
    size="md"
    @update:open="emit('update:open', $event)"
  >
    <form class="space-y-4 text-xs" @submit.prevent="handleSubmit">
      <Alert v-if="errorMessage" variant="danger">
        {{ errorMessage }}
      </Alert>

      <!-- Student Selector (Disabled during edit) -->
      <FormField
        label="Mahasiswa Bimbingan"
        required
        :error="serverErrors.student_id?.[0]"
      >
        <Select
          v-model="form.student_id"
          required
          :disabled="isEditing || submitting"
        >
          <option value="">-- Pilih Mahasiswa Bimbingan --</option>
          <option v-for="adv in advisees" :key="adv.id" :value="adv.student_id">
            {{ adv.student?.student_number }} — {{ adv.student?.full_name }} ({{ adv.student?.study_program?.name || 'Prodi' }})
          </option>
        </Select>
      </FormField>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <!-- Session Date -->
        <FormField
          label="Tanggal Bimbingan"
          required
          :error="serverErrors.session_date?.[0]"
        >
          <Input
            v-model="form.session_date"
            type="date"
            required
            :disabled="submitting"
          />
        </FormField>

        <!-- Status -->
        <FormField
          label="Status Sesi"
          required
          :error="serverErrors.status?.[0]"
        >
          <Select
            v-model="form.status"
            required
            :disabled="submitting"
          >
            <option value="completed">Selesai (Completed)</option>
            <option value="scheduled">Dijadwalkan (Scheduled)</option>
            <option value="cancelled">Dibatalkan (Cancelled)</option>
          </Select>
        </FormField>
      </div>

      <!-- Topic -->
      <FormField
        label="Topik / Pokok Bimbingan"
        :error="serverErrors.topic?.[0]"
      >
        <Input
          v-model="form.topic"
          placeholder="Contoh: Peninjauan Beban SKS Semester Gasal, Evaluasi IPK..."
          :disabled="submitting"
        />
      </FormField>

      <!-- Notes / Recommendations -->
      <FormField
        label="Catatan Konsultasi & Rekomendasi Dosen PA"
        required
        :error="serverErrors.notes?.[0]"
      >
        <Textarea
          v-model="form.notes"
          placeholder="Tuliskan catatan arahan akademik, saran mata kuliah, kendala studi, atau tindak lanjut bimbingan..."
          :rows="4"
          required
          :disabled="submitting"
        />
      </FormField>

      <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
        <Button variant="outline" size="sm" :disabled="submitting" @click="emit('update:open', false)">
          Batal
        </Button>
        <Button
          type="submit"
          variant="primary"
          size="sm"
          :loading="submitting"
          :disabled="!form.student_id || !form.notes.trim()"
        >
          <component :is="isEditing ? Edit2 : MessageSquarePlus" class="w-3.5 h-3.5" />
          <span>{{ isEditing ? 'Simpan Perubahan' : 'Simpan Catatan Bimbingan' }}</span>
        </Button>
      </div>
    </form>
  </Modal>
</template>
