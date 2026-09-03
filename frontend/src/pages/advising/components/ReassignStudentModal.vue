<script setup lang="ts">
import { ref, watch } from 'vue'
import { RefreshCw } from 'lucide-vue-next'
import { lecturerService } from '@/services/api/lecturers'
import { advisingService } from '@/services/api/advising'
import { useToast } from '@/composables/useToast'
import type { AcademicAdvisor } from '@/types/advising'
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
  advisor: AcademicAdvisor | null
}>()

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void
  (e: 'reassigned'): void
}>()

const toast = useToast()
const lecturers = ref<Lecturer[]>([])
const loadingLecturers = ref<boolean>(false)
const submitting = ref<boolean>(false)
const errorMessage = ref<string | null>(null)
const serverErrors = ref<Record<string, string[]>>({})

const newLecturerId = ref<number | string>('')
const changeDate = ref<string>(new Date().toISOString().split('T')[0])
const reason = ref<string>('')

async function loadLecturers() {
  loadingLecturers.value = true
  try {
    const res = await lecturerService.list({ per_page: 100, status: 'active' })
    lecturers.value = res.data || []
  } catch {
    lecturers.value = []
  } finally {
    loadingLecturers.value = false
  }
}

async function handleSubmit() {
  if (!props.advisor || !newLecturerId.value) return
  submitting.value = true
  errorMessage.value = null
  serverErrors.value = {}

  try {
    await advisingService.changeStudentAdvisor(props.advisor.student_id, {
      new_lecturer_id: Number(newLecturerId.value),
      change_date: changeDate.value,
      reason: reason.value || undefined,
    })

    toast.success('Dosen Pembimbing Akademik mahasiswa berhasil dialihkan.')
    emit('update:open', false)
    emit('reassigned')
    resetForm()
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal mengalihkan Dosen PA.'
    if (err.errors) {
      serverErrors.value = err.errors
    }
  } finally {
    submitting.value = false
  }
}

function resetForm() {
  newLecturerId.value = ''
  changeDate.value = new Date().toISOString().split('T')[0]
  reason.value = ''
  errorMessage.value = null
  serverErrors.value = {}
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      loadLecturers()
    } else {
      resetForm()
    }
  }
)
</script>

<template>
  <Modal
    :open="open"
    title="Alihkan Dosen Pembimbing Akademik (Reassign)"
    size="md"
    @update:open="emit('update:open', $event)"
  >
    <form v-if="advisor" class="space-y-4 text-xs" @submit.prevent="handleSubmit">
      <Alert v-if="errorMessage" variant="danger">
        {{ errorMessage }}
      </Alert>

      <!-- Student Context -->
      <div class="p-3 rounded-md bg-slate-50 border border-slate-200">
        <span class="text-2xs text-slate-400 font-semibold uppercase block">Mahasiswa yang Dialihkan:</span>
        <span class="font-bold text-slate-900 text-sm">{{ advisor.student?.full_name }}</span>
        <span class="text-2xs text-slate-500 font-mono block">NIM: {{ advisor.student?.student_number }} · {{ advisor.student?.study_program?.name }}</span>
      </div>

      <!-- Current Advisor to New Advisor Flow -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3 bg-amber-50/60 border border-amber-200 rounded-md">
        <div>
          <span class="text-2xs font-semibold uppercase tracking-wider text-slate-500 block">Dosen PA Saat Ini:</span>
          <span class="font-bold text-slate-800 text-xs mt-0.5 block">{{ advisor.lecturer?.full_name }}</span>
          <span class="text-2xs text-slate-500">NIDN: {{ advisor.lecturer?.nidn || '-' }}</span>
        </div>

        <div class="sm:border-l sm:border-amber-200 sm:pl-3">
          <span class="text-2xs font-semibold uppercase tracking-wider text-brand-900 block">Dosen PA Baru:</span>
          <FormField
            required
            :error="serverErrors.new_lecturer_id?.[0]"
            class="mt-1"
          >
            <Select
              v-model="newLecturerId"
              required
              :disabled="submitting || loadingLecturers"
              class="text-xs"
            >
              <option value="">-- Pilih Dosen PA Baru --</option>
              <option
                v-for="l in lecturers.filter(lec => lec.id !== advisor?.lecturer_id)"
                :key="l.id"
                :value="l.id"
              >
                {{ l.full_name }} ({{ l.nidn || 'NIDN' }})
              </option>
            </Select>
          </FormField>
        </div>
      </div>

      <!-- Change Date -->
      <FormField
        label="Tanggal Efektif Pengalihan"
        required
        :error="serverErrors.change_date?.[0]"
      >
        <Input
          v-model="changeDate"
          type="date"
          required
          :disabled="submitting"
        />
      </FormField>

      <!-- Reason -->
      <FormField
        label="Alasan Pengalihan Dosen PA"
        :error="serverErrors.reason?.[0]"
      >
        <Textarea
          v-model="reason"
          placeholder="Jelaskan alasan rotasi / pengalihan pembimbing akademik (mis. cuti dosen, mutasi prodi, penyesuaian kuota)..."
          :rows="3"
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
          class="!bg-amber-600 hover:!bg-amber-700 text-white"
          :loading="submitting"
          :disabled="!newLecturerId"
        >
          <RefreshCw class="w-3.5 h-3.5" />
          <span>Konfirmasi Pengalihan PA</span>
        </Button>
      </div>
    </form>
  </Modal>
</template>
