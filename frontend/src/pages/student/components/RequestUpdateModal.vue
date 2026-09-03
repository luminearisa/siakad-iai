<script setup lang="ts">
import { ref, reactive, watch } from 'vue'
import Modal from '@/components/ui/Modal.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import FormField from '@/components/form/FormField.vue'
import Textarea from '@/components/ui/Textarea.vue'
import Alert from '@/components/ui/Alert.vue'
import { studentPortalApi } from '@/services/api/student-portal'
import type { StudentFullProfile } from '@/types/student-portal'

const props = defineProps<{
  show: boolean
  student: StudentFullProfile | null
}>()

const emit = defineEmits<{
  (e: 'update:show', value: boolean): void
  (e: 'success'): void
}>()

const submitting = ref(false)
const errorMessage = ref<string | null>(null)
const successMessage = ref<string | null>(null)

const form = reactive({
  phone_number: '',
  email: '',
  address: '',
  notes: '',
})

watch(
  () => props.student,
  (st) => {
    if (st) {
      form.phone_number = st.phone || st.phone_number || ''
      form.email = st.email || ''
      form.address = st.address || ''
      form.notes = ''
    }
  },
  { immediate: true }
)

async function handleSubmit() {
  submitting.value = true
  errorMessage.value = null
  successMessage.value = null

  try {
    await studentPortalApi.requestUpdate({
      phone_number: form.phone_number,
      email: form.email,
      address: form.address,
      notes: form.notes,
    })

    successMessage.value = 'Perubahan data profil berhasil diajukan dan diperbarui.'
    emit('success')
    setTimeout(() => {
      emit('update:show', false)
    }, 1200)
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Gagal mengajukan perubahan data profil.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <Modal
    :open="show"
    title="Ajukan Perubahan Data Profil"
    size="lg"
    @update:open="(val: boolean) => emit('update:show', val)"
    @close="emit('update:show', false)"
  >
    <form @submit.prevent="handleSubmit" class="space-y-4">
      <Alert v-if="errorMessage" type="danger" dismissible @dismiss="errorMessage = null">
        {{ errorMessage }}
      </Alert>

      <Alert v-if="successMessage" type="success">
        {{ successMessage }}
      </Alert>

      <p class="text-xs text-slate-400">
        Perubahan data kontak dan domisili akan langsung diperbarui dan tercatat pada sistem log audit akademik.
      </p>

      <div class="space-y-3">
        <FormField label="Nomor Telepon / WhatsApp" required>
          <Input
            v-model="form.phone_number"
            placeholder="Contoh: 081234567890"
            :disabled="submitting"
            required
          />
        </FormField>

        <FormField label="Alamat Email Aktif" required>
          <Input
            v-model="form.email"
            type="email"
            placeholder="nama@email.com"
            :disabled="submitting"
            required
          />
        </FormField>

        <FormField label="Alamat Domisili / Tempat Tinggal" required>
          <Textarea
            v-model="form.address"
            placeholder="Tuliskan alamat lengkap tempat tinggal saat ini..."
            :rows="3"
            :disabled="submitting"
            required
          />
        </FormField>

        <FormField label="Catatan / Alasan Perubahan Data (Opsional)">
          <Textarea
            v-model="form.notes"
            placeholder="Contoh: Pindah alamat domisili atau nomor telepon hilang..."
            :rows="2"
            :disabled="submitting"
          />
        </FormField>
      </div>

      <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-700/60">
        <Button
          type="button"
          variant="secondary"
          :disabled="submitting"
          @click="emit('update:show', false)"
        >
          Batal
        </Button>
        <Button
          type="submit"
          variant="primary"
          :loading="submitting"
        >
          Simpan & Ajukan
        </Button>
      </div>
    </form>
  </Modal>
</template>
