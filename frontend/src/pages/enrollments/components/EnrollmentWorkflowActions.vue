<script setup lang="ts">
import { ref } from 'vue'
import { Send, CheckCircle2, XCircle, RotateCcw, Lock } from 'lucide-vue-next'
import { usePermissions } from '@/composables/usePermissions'
import { useAuthStore } from '@/stores/auth'
import type { StudentEnrollment } from '@/types/enrollment'
import Button from '@/components/ui/Button.vue'
import Modal from '@/components/ui/Modal.vue'
import ConfirmModal from '@/components/feedback/ConfirmModal.vue'
import FormField from '@/components/form/FormField.vue'
import Textarea from '@/components/ui/Textarea.vue'

interface Props {
  enrollment: StudentEnrollment
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
})

const emit = defineEmits<{
  (e: 'submit-krs'): void
  (e: 'approve', notes?: string): void
  (e: 'reject', reason: string): void
  (e: 'request-revision', notes: string): void
  (e: 'lock'): void
}>()

const { can } = usePermissions()
const authStore = useAuthStore()

// Modals
const submitModalOpen = ref<boolean>(false)
const approveModalOpen = ref<boolean>(false)
const approveNotes = ref<string>('')

const rejectModalOpen = ref<boolean>(false)
const rejectReason = ref<string>('')

const revisionModalOpen = ref<boolean>(false)
const revisionNotes = ref<string>('')

const lockModalOpen = ref<boolean>(false)

const isStudentOwner = authStore.isStudent && authStore.user?.student?.id === props.enrollment.student_id

function handleApprove() {
  emit('approve', approveNotes.value)
  approveModalOpen.value = false
  approveNotes.value = ''
}

function handleReject() {
  if (!rejectReason.value.trim()) return
  emit('reject', rejectReason.value)
  rejectModalOpen.value = false
  rejectReason.value = ''
}

function handleRequestRevision() {
  if (!revisionNotes.value.trim()) return
  emit('request-revision', revisionNotes.value)
  revisionModalOpen.value = false
  revisionNotes.value = ''
}
</script>

<template>
  <div class="flex flex-wrap items-center gap-2">
    <!-- 1. Submit KRS (Student / Can Submit) -->
    <Button
      v-if="(enrollment.status === 'draft' || enrollment.status === 'revision_required') && (isStudentOwner || can('enrollments.submit'))"
      variant="primary"
      size="sm"
      :disabled="loading || (enrollment.items_count === 0 && (!enrollment.items || enrollment.items.length === 0))"
      @click="submitModalOpen = true"
    >
      <Send class="w-3.5 h-3.5" />
      <span>Ajukan KRS ke Dosen PA</span>
    </Button>

    <!-- 2. Approve KRS (Approver / Admin) -->
    <Button
      v-if="enrollment.status === 'submitted' && can('enrollments.approve')"
      variant="primary"
      size="sm"
      class="!bg-emerald-600 hover:!bg-emerald-700 text-white"
      :disabled="loading"
      @click="approveModalOpen = true"
    >
      <CheckCircle2 class="w-3.5 h-3.5" />
      <span>Setujui KRS</span>
    </Button>

    <!-- 3. Request Revision (Approver / Admin) -->
    <Button
      v-if="enrollment.status === 'submitted' && can('enrollments.revise')"
      variant="outline"
      size="sm"
      class="text-amber-700 border-amber-300 hover:bg-amber-50"
      :disabled="loading"
      @click="revisionModalOpen = true"
    >
      <RotateCcw class="w-3.5 h-3.5 text-amber-600" />
      <span>Minta Revisi</span>
    </Button>

    <!-- 4. Reject KRS (Approver / Admin) -->
    <Button
      v-if="enrollment.status === 'submitted' && can('enrollments.reject')"
      variant="outline"
      size="sm"
      class="text-rose-600 border-rose-200 hover:bg-rose-50"
      :disabled="loading"
      @click="rejectModalOpen = true"
    >
      <XCircle class="w-3.5 h-3.5 text-rose-500" />
      <span>Tolak KRS</span>
    </Button>

    <!-- 5. Lock KRS (Admin) -->
    <Button
      v-if="enrollment.status === 'approved' && can('enrollments.lock')"
      variant="outline"
      size="sm"
      class="text-slate-700 border-slate-300 hover:bg-slate-50"
      :disabled="loading"
      @click="lockModalOpen = true"
    >
      <Lock class="w-3.5 h-3.5 text-slate-500" />
      <span>Kunci KRS (Final)</span>
    </Button>

    <!-- MODAL: Submit KRS Confirmation -->
    <ConfirmModal
      :open="submitModalOpen"
      title="Ajukan Rencana Studi (KRS)"
      message="Apakah Anda yakin ingin mengajukan KRS ini untuk ditinjau dan disetujui oleh Dosen Pembimbing Akademik (PA)? Pastikan seluruh mata kuliah dan jadwal kelas sudah sesuai."
      confirm-text="Ya, Ajukan KRS"
      variant="primary"
      :loading="loading"
      @update:open="submitModalOpen = $event"
      @confirm="emit('submit-krs'); submitModalOpen = false"
    />

    <!-- MODAL: Approve KRS -->
    <Modal
      :open="approveModalOpen"
      title="Setujui Kartu Rencana Studi (KRS)"
      size="sm"
      @update:open="approveModalOpen = $event"
    >
      <div class="space-y-3.5 text-xs text-slate-600">
        <p>
          Anda akan menyetujui KRS mahasiswa <strong class="text-slate-900">{{ enrollment.student?.full_name }}</strong> dengan total beban studi <strong class="text-slate-900">{{ enrollment.total_credits }} SKS</strong>.
        </p>

        <FormField label="Catatan Pembimbing Akademik (Opsional)">
          <Textarea
            v-model="approveNotes"
            placeholder="Tambahkan pesan atau saran akademik untuk mahasiswa..."
            :rows="3"
          />
        </FormField>
      </div>

      <template #footer>
        <div class="flex justify-end gap-2">
          <Button variant="outline" size="sm" @click="approveModalOpen = false">Batal</Button>
          <Button variant="primary" size="sm" class="!bg-emerald-600 hover:!bg-emerald-700 text-white" :loading="loading" @click="handleApprove">
            Konfirmasi Setujui
          </Button>
        </div>
      </template>
    </Modal>

    <!-- MODAL: Request Revision -->
    <Modal
      :open="revisionModalOpen"
      title="Minta Revisi Rencana Studi"
      size="sm"
      @update:open="revisionModalOpen = $event"
    >
      <div class="space-y-3.5 text-xs text-slate-600">
        <p>
          KRS akan dikembalikan ke status <strong>Revision Required</strong> sehingga mahasiswa dapat mengubah pemilihan kelas perkuliahan.
        </p>

        <FormField label="Alasan / Instruksi Revisi" required>
          <Textarea
            v-model="revisionNotes"
            placeholder="Jelaskan mata kuliah apa yang perlu diganti atau diperbaiki..."
            :rows="3"
            required
          />
        </FormField>
      </div>

      <template #footer>
        <div class="flex justify-end gap-2">
          <Button variant="outline" size="sm" @click="revisionModalOpen = false">Batal</Button>
          <Button
            variant="primary"
            size="sm"
            class="!bg-amber-600 hover:!bg-amber-700 text-white"
            :disabled="!revisionNotes.trim()"
            :loading="loading"
            @click="handleRequestRevision"
          >
            Kirim Permintaan Revisi
          </Button>
        </div>
      </template>
    </Modal>

    <!-- MODAL: Reject KRS -->
    <Modal
      :open="rejectModalOpen"
      title="Tolak Pengajuan KRS"
      size="sm"
      @update:open="rejectModalOpen = $event"
    >
      <div class="space-y-3.5 text-xs text-slate-600">
        <p class="text-rose-600">
          Penolakan KRS bersifat final bagi pengajuan ini. Berikan alasan penolakan secara jelas.
        </p>

        <FormField label="Alasan Penolakan" required>
          <Textarea
            v-model="rejectReason"
            placeholder="Alasan penolakan rencana studi..."
            :rows="3"
            required
          />
        </FormField>
      </div>

      <template #footer>
        <div class="flex justify-end gap-2">
          <Button variant="outline" size="sm" @click="rejectModalOpen = false">Batal</Button>
          <Button
            variant="primary"
            size="sm"
            class="!bg-rose-600 hover:!bg-rose-700 text-white"
            :disabled="!rejectReason.trim()"
            :loading="loading"
            @click="handleReject"
          >
            Konfirmasi Tolak
          </Button>
        </div>
      </template>
    </Modal>

    <!-- MODAL: Lock KRS Confirmation -->
    <ConfirmModal
      :open="lockModalOpen"
      title="Kunci Kartu Rencana Studi (KRS)"
      message="Setelah dikunci (Locked), KRS tidak dapat lagi diubah oleh mahasiswa maupun dosen PA. Pastikan seluruh administrasi akademik telah selesai."
      confirm-text="Kunci KRS Sekarang"
      variant="primary"
      :loading="loading"
      @update:open="lockModalOpen = $event"
      @confirm="emit('lock'); lockModalOpen = false"
    />
  </div>
</template>
