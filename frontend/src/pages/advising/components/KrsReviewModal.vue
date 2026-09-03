<script setup lang="ts">
import { ref, watch } from 'vue'
import type { StudentEnrollment } from '@/types/enrollment'
import Modal from '@/components/ui/Modal.vue'
import FormField from '@/components/form/FormField.vue'
import Textarea from '@/components/ui/Textarea.vue'
import Button from '@/components/ui/Button.vue'

const props = defineProps<{
  open: boolean
  enrollment: StudentEnrollment | null
  actionType: 'approve' | 'revision' | 'reject'
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void
  (e: 'confirm', data: { actionType: 'approve' | 'revision' | 'reject'; notes: string }): void
}>()

const notes = ref<string>('')

function handleConfirm() {
  if (props.actionType !== 'approve' && !notes.value.trim()) return
  emit('confirm', {
    actionType: props.actionType,
    notes: notes.value,
  })
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      notes.value = ''
    }
  }
)
</script>

<template>
  <Modal
    :open="open"
    :title="
      actionType === 'approve'
        ? 'Setujui Rencana Studi (KRS)'
        : actionType === 'revision'
        ? 'Minta Revisi Rencana Studi'
        : 'Tolak Rencana Studi (KRS)'
    "
    size="sm"
    @update:open="emit('update:open', $event)"
  >
    <div v-if="enrollment" class="space-y-3.5 text-xs text-slate-600">
      <div class="p-3 rounded-md bg-slate-50 border border-slate-200">
        <span class="text-2xs text-slate-400 font-semibold uppercase block">Mahasiswa:</span>
        <span class="font-bold text-slate-900 text-sm">{{ enrollment.student?.full_name }}</span>
        <span class="text-2xs text-slate-500 font-mono block">
          NIM: {{ enrollment.student?.student_number }} · {{ enrollment.semester?.name }} ({{ enrollment.total_credits }} SKS)
        </span>
      </div>

      <p v-if="actionType === 'approve'">
        Anda akan menyetujui KRS mahasiswa ini. Mahasiswa akan dinyatakan resmi terdaftar pada seluruh kelas perkuliahan yang dipilih.
      </p>

      <p v-else-if="actionType === 'revision'" class="text-amber-800">
        KRS akan dikembalikan ke status <strong>Revision Required</strong> agar mahasiswa dapat menyesuaikan kembali pemilihan mata kuliah sesuai instruksi Anda.
      </p>

      <p v-else class="text-rose-600">
        Penolakan KRS ini bersifat final untuk periode semester ini. Berikan alasan penolakan secara jelas.
      </p>

      <!-- Notes input -->
      <FormField
        :label="
          actionType === 'approve'
            ? 'Catatan Pembimbing Akademik (Opsional)'
            : actionType === 'revision'
            ? 'Instruksi / Catatan Revisi'
            : 'Alasan Penolakan'
        "
        :required="actionType !== 'approve'"
      >
        <Textarea
          v-model="notes"
          :placeholder="
            actionType === 'approve'
              ? 'Tambahkan pesan atau saran bimbingan...'
              : actionType === 'revision'
              ? 'Jelaskan kelas atau mata kuliah yang perlu diganti...'
              : 'Jelaskan alasan penolakan...'
          "
          :rows="3"
          :required="actionType !== 'approve'"
        />
      </FormField>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button variant="outline" size="sm" :disabled="loading" @click="emit('update:open', false)">
          Batal
        </Button>
        <Button
          v-if="actionType === 'approve'"
          variant="primary"
          size="sm"
          class="!bg-emerald-600 hover:!bg-emerald-700 text-white"
          :loading="loading"
          @click="handleConfirm"
        >
          Konfirmasi Setujui
        </Button>
        <Button
          v-else-if="actionType === 'revision'"
          variant="primary"
          size="sm"
          class="!bg-amber-600 hover:!bg-amber-700 text-white"
          :disabled="!notes.trim()"
          :loading="loading"
          @click="handleConfirm"
        >
          Kirim Permintaan Revisi
        </Button>
        <Button
          v-else
          variant="primary"
          size="sm"
          class="!bg-rose-600 hover:!bg-rose-700 text-white"
          :disabled="!notes.trim()"
          :loading="loading"
          @click="handleConfirm"
        >
          Konfirmasi Tolak
        </Button>
      </div>
    </template>
  </Modal>
</template>
