<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { CalendarClock, CheckCircle, QrCode, RefreshCw } from 'lucide-vue-next'
import { attendanceService } from '@/services/api/attendance'
import { useToast } from '@/composables/useToast'
import { getErrorMessage } from '@/utils/errors'
import { formatDate, formatTime } from '@/utils/format'
import type { CheckInOption } from '@/types/attendance'
import Modal from '@/components/ui/Modal.vue'
import FormField from '@/components/form/FormField.vue'
import Input from '@/components/ui/Input.vue'
import Button from '@/components/ui/Button.vue'
import Alert from '@/components/ui/Alert.vue'

/**
 * Student-facing check-in.
 *
 * Students never receive `check_in_code` from the session endpoints, so the
 * only session a student can pick is one that `my-attendance` already flagged
 * with `is_check_in_active`. Those sessions are handed in through `activeSessions`
 * and one of them is always pre-selected: the modal can never silently reject a
 * submit because it does not know which session to post to.
 */
const props = withDefaults(defineProps<{
  open: boolean
  activeSessions?: CheckInOption[]
  defaultSessionId?: number | null
}>(), {
  activeSessions: () => [],
  defaultSessionId: null,
})

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void
  (e: 'success'): void
  /** Ask the page to reload `my-attendance`: new tokens appear while open. */
  (e: 'refresh'): void
  /** The student asked for a check-in but no session is open: let the page explain. */
  (e: 'no-active-session'): void
}>()

const toast = useToast()
/** Kept as a string because a native select yields strings; ids are numbers on submit. */
const selectedSessionKey = ref<string>('')
const code = ref<string>('')
const submitting = ref<boolean>(false)
const errorMessage = ref<string | null>(null)

const options = computed<CheckInOption[]>(() => props.activeSessions || [])

const hasActiveSessions = computed<boolean>(() => options.value.length > 0)

const selectedSession = computed<CheckInOption | null>(() =>
  options.value.find((option) => String(option.session_id) === selectedSessionKey.value) ?? null,
)

function pickSession(): void {
  const preferred = props.defaultSessionId
  if (preferred !== null && preferred !== undefined) {
    const match = options.value.find((option) => option.session_id === preferred)
    if (match) {
      selectedSessionKey.value = String(match.session_id)
      return
    }
  }
  const first = options.value[0]
  selectedSessionKey.value = first ? String(first.session_id) : ''
}

watch(
  () => [props.open, options.value.length] as const,
  ([isOpen]) => {
    if (isOpen) {
      pickSession()
      errorMessage.value = null
    }
  },
  { immediate: true },
)

async function handleSubmit() {
  errorMessage.value = null

  if (!hasActiveSessions.value) {
    emit('update:open', false)
    emit('no-active-session')
    return
  }

  if (selectedSessionKey.value === '') pickSession()

  const sessionId = Number(selectedSessionKey.value)
  if (!Number.isFinite(sessionId) || sessionId <= 0) {
    errorMessage.value = 'Sesi presensi tidak dapat ditentukan. Muat ulang halaman ini.'
    return
  }

  if (code.value.trim().length === 0) {
    errorMessage.value = 'Masukkan kode token presensi yang dibacakan dosen.'
    return
  }

  submitting.value = true
  try {
    const res = await attendanceService.selfCheckIn({
      teaching_session_id: sessionId,
      check_in_code: code.value.trim().toUpperCase(),
    })

    toast.success(res.message || 'Presensi mandiri berhasil! Anda tercatat HADIR.')
    emit('success')
    emit('update:open', false)
    code.value = ''
  } catch (err: unknown) {
    errorMessage.value = getErrorMessage(err, 'Gagal melakukan presensi mandiri. Periksa kembali kode token Anda.')
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
          Ketik 6-karakter kode token presensi yang ditampilkan oleh Dosen Pengajar di kelas.
        </p>
      </div>

      <Alert v-if="!hasActiveSessions" variant="warning" title="Tidak ada sesi presensi yang buka">
        Belum ada pertemuan dengan token presensi aktif. Tunggu dosen membuka token pada pertemuan yang sedang
        berjalan, lalu segarkan daftar ini.
        <button
          type="button"
          data-test="checkin-refresh"
          class="mt-2 inline-flex items-center gap-1 rounded-md border border-amber-300 bg-white px-2 py-1 text-2xs font-bold text-amber-800 hover:bg-amber-100"
          @click="emit('refresh')"
        >
          <RefreshCw class="w-3 h-3" />
          <span>Muat ulang sesi aktif</span>
        </button>
      </Alert>

      <Alert v-else-if="errorMessage" variant="danger">
        {{ errorMessage }}
      </Alert>

      <template v-if="hasActiveSessions">
        <!-- Which meeting is being entered: never a blank, never a typed id -->
        <FormField label="Pertemuan" :required="options.length > 1">
          <select
            v-if="options.length > 1"
            v-model="selectedSessionKey"
            data-test="checkin-session-select"
            :disabled="submitting"
            class="w-full px-2.5 py-2 bg-white border border-slate-300 rounded-md text-xs outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600"
          >
            <option
              v-for="option in options"
              :key="option.session_id"
              :value="String(option.session_id)"
            >
              {{ option.course_label }} · Pertemuan {{ option.meeting_number }}{{ option.start_time ? ` · ${formatTime(option.start_time)}` : '' }}
            </option>
          </select>
          <div
            v-else
            class="rounded-md border border-slate-200 bg-slate-50 px-2.5 py-2 text-2xs"
          >
            <span class="flex items-center gap-1.5 font-bold text-slate-800">
              <CalendarClock class="w-3.5 h-3.5 text-brand-700" />
              {{ selectedSession?.course_label || 'Pertemuan aktif' }}
            </span>
            <span class="block text-slate-500 mt-0.5">
              Pertemuan {{ selectedSession?.meeting_number }} ·
              {{ selectedSession?.session_date ? formatDate(selectedSession.session_date) : 'hari ini' }}
              <span v-if="selectedSession?.start_time"> · {{ formatTime(selectedSession.start_time) }}</span>
            </span>
          </div>
        </FormField>

        <FormField label="Kode Token Presensi (6 Karakter)" required>
          <Input
            v-model="code"
            data-test="checkin-code-input"
            placeholder="CONTOH: HADIR5"
            required
            maxlength="10"
            :disabled="submitting"
            class="text-center font-mono font-extrabold text-lg uppercase tracking-widest text-brand-900 h-12"
          />
        </FormField>
      </template>

      <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
        <Button variant="outline" size="sm" :disabled="submitting" @click="emit('update:open', false)">
          Batal
        </Button>
        <Button
          type="submit"
          variant="primary"
          size="sm"
          data-test="checkin-submit"
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
