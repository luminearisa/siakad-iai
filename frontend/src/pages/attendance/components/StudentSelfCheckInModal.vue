<script setup lang="ts">
import { ref } from 'vue'
import { CheckCircle, QrCode } from 'lucide-vue-next'
import { attendanceService } from '@/services/api/attendance'
import { useToast } from '@/composables/useToast'
import Modal from '@/components/ui/Modal.vue'
import FormField from '@/components/form/FormField.vue'
import Input from '@/components/ui/Input.vue'
import Button from '@/components/ui/Button.vue'
import Alert from '@/components/ui/Alert.vue'

const props = defineProps<{
  open: boolean
  defaultSessionId?: number | null
}>()

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void
  (e: 'success'): void
}>()

const toast = useToast()
const sessionId = ref<any>(props.defaultSessionId || '')
const code = ref<string>('')
const submitting = ref<boolean>(false)
const errorMessage = ref<string | null>(null)

async function handleSubmit() {
  if (!code.value.trim() || !sessionId.value) return
  submitting.value = true
  errorMessage.value = null

  try {
    const res = await attendanceService.selfCheckIn({
      teaching_session_id: Number(sessionId.value),
      check_in_code: code.value.trim().toUpperCase(),
    })

    toast.success(res.message || 'Presensi mandiri berhasil! Anda tercatat HADIR.')
    emit('success')
    emit('update:open', false)
    code.value = ''
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal melakukan presensi mandiri. Periksa kembali kode token Anda.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <Modal
    :open="open"
    title="Presensi Mandiri Mahasiswa"
    size="sm"
    @update:open="emit('update:open', $event)"
  >
    <form class="space-y-4 text-xs" @submit.prevent="handleSubmit">
      <div class="text-center pb-1">
        <div class="w-12 h-12 rounded-full bg-brand-50 text-brand-900 flex items-center justify-center mx-auto mb-2 border border-brand-200">
          <QrCode class="w-6 h-6" />
        </div>
        <h4 class="font-bold text-slate-800 text-sm">Masukkan Kode Presensi</h4>
        <p class="text-2xs text-slate-500 mt-0.5">
          Ketik 6-digit kode token presensi yang ditampilkan oleh Dosen Pengajar di kelas.
        </p>
      </div>

      <Alert v-if="errorMessage" variant="danger">
        {{ errorMessage }}
      </Alert>

      <FormField label="Kode Token Presensi (6 Karakter)" required>
        <Input
          v-model="code"
          placeholder="CONTOH: HADIR5"
          required
          maxlength="10"
          :disabled="submitting"
          class="text-center font-mono font-extrabold text-lg uppercase tracking-widest text-brand-900 h-12"
        />
      </FormField>

      <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
        <Button variant="outline" size="sm" :disabled="submitting" @click="emit('update:open', false)">
          Batal
        </Button>
        <Button
          type="submit"
          variant="primary"
          size="sm"
          :loading="submitting"
          :disabled="!code.trim()"
        >
          <CheckCircle class="w-4 h-4 mr-1" />
          <span>Kirim Presensi</span>
        </Button>
      </div>
    </form>
  </Modal>
</template>
