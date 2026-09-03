<script setup lang="ts">
import { ref, watch, onUnmounted } from 'vue'
import { QrCode, Timer, Copy, Check, Lock } from 'lucide-vue-next'
import { attendanceService } from '@/services/api/attendance'
import { useToast } from '@/composables/useToast'
import type { TeachingSession } from '@/types/attendance'
import Modal from '@/components/ui/Modal.vue'
import Button from '@/components/ui/Button.vue'

const props = defineProps<{
  open: boolean
  session: TeachingSession | null
}>()

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void
  (e: 'updated'): void
}>()

const toast = useToast()
const durationMinutes = ref<number>(15)
const loading = ref<boolean>(false)
const copied = ref<boolean>(false)
const timerInterval = ref<any>(null)
const remainingSeconds = ref<number>(0)

function calculateRemaining() {
  if (!props.session?.check_in_expires_at) {
    remainingSeconds.value = 0
    return
  }
  const diff = Math.floor((new Date(props.session.check_in_expires_at).getTime() - new Date().getTime()) / 1000)
  remainingSeconds.value = diff > 0 ? diff : 0
}

function startTimer() {
  stopTimer()
  calculateRemaining()
  timerInterval.value = setInterval(() => {
    calculateRemaining()
    if (remainingSeconds.value <= 0) {
      stopTimer()
    }
  }, 1000)
}

function stopTimer() {
  if (timerInterval.value) {
    clearInterval(timerInterval.value)
    timerInterval.value = null
  }
}

function formatTime(seconds: number): string {
  const m = Math.floor(seconds / 60)
  const s = seconds % 60
  return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`
}

async function handleOpenToken() {
  if (!props.session) return
  loading.value = true
  try {
    const res = await attendanceService.openCheckIn(props.session.id, durationMinutes.value)
    if (res.data) {
      toast.success(`Presensi Mandiri dibuka selama ${durationMinutes.value} menit. Kode: ${res.data.check_in_code}`)
      emit('updated')
      startTimer()
    }
  } catch (err: any) {
    toast.error(err.message || 'Gagal membuka presensi mandiri.')
  } finally {
    loading.value = false
  }
}

async function handleCloseSession() {
  if (!props.session) return
  loading.value = true
  try {
    await attendanceService.closeSession(props.session.id)
    toast.success('Sesi presensi perkuliahan telah ditutup.')
    stopTimer()
    emit('updated')
    emit('update:open', false)
  } catch (err: any) {
    toast.error(err.message || 'Gagal menutup sesi presensi.')
  } finally {
    loading.value = false
  }
}

function copyCode() {
  if (!props.session?.check_in_code) return
  navigator.clipboard.writeText(props.session.check_in_code)
  copied.value = true
  toast.success('Kode token disalin ke clipboard.')
  setTimeout(() => {
    copied.value = false
  }, 2000)
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen && props.session?.is_check_in_active) {
      startTimer()
    } else {
      stopTimer()
    }
  }
)

onUnmounted(() => {
  stopTimer()
})
</script>

<template>
  <Modal
    :open="open"
    :title="session ? `Presensi Mandiri — Pertemuan ke-${session.meeting_number}` : 'Presensi Mandiri Mahasiswa'"
    size="md"
    @update:open="emit('update:open', $event)"
  >
    <div v-if="session" class="space-y-4 text-center">
      <!-- Active Token View -->
      <div v-if="session.check_in_code && remainingSeconds > 0" class="bg-gradient-to-br from-brand-900 via-brand-800 to-indigo-950 text-white rounded-xl p-6 shadow-md border border-brand-700 relative overflow-hidden">
        <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-white/5 rounded-full pointer-events-none" />

        <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-white/10 rounded-full text-2xs font-semibold uppercase tracking-wider mb-3">
          <Timer class="w-3 h-3 text-emerald-400 animate-pulse" />
          <span>Sesi Presensi Aktif</span>
        </div>

        <p class="text-xs text-brand-200 mb-1">Kode Presensi Mandiri (Tampilkan di Kelas):</p>
        <div class="my-3 flex items-center justify-center gap-3">
          <span class="text-4xl font-extrabold tracking-widest font-mono bg-black/30 px-6 py-2 rounded-lg border border-white/20 select-all">
            {{ session.check_in_code }}
          </span>
          <button
            type="button"
            class="p-2.5 rounded-lg bg-white/10 hover:bg-white/20 transition text-white border border-white/10"
            title="Salin Kode"
            @click="copyCode"
          >
            <Check v-if="copied" class="w-5 h-5 text-emerald-400" />
            <Copy v-else class="w-5 h-5" />
          </button>
        </div>

        <!-- Live Countdown -->
        <div class="flex items-center justify-center gap-2 text-sm text-brand-100 mt-2 font-mono">
          <span>Sisa Waktu:</span>
          <span class="font-bold text-emerald-300 text-base bg-white/10 px-2 py-0.5 rounded">
            {{ formatTime(remainingSeconds) }}
          </span>
        </div>
      </div>

      <!-- Generator / Expired View -->
      <div v-else class="space-y-4 py-2">
        <div class="w-12 h-12 rounded-full bg-brand-50 text-brand-900 flex items-center justify-center mx-auto border border-brand-200">
          <QrCode class="w-6 h-6" />
        </div>
        <div>
          <h4 class="font-bold text-sm text-slate-800">Buka Presensi Mandiri</h4>
          <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
            Hasilkan 6-digit kode token berbatas waktu agar mahasiswa dapat melakukan presensi mandiri dari perangkat masing-masing.
          </p>
        </div>

        <div class="flex items-center justify-center gap-3 max-w-xs mx-auto text-xs">
          <label class="font-medium text-slate-700">Durasi:</label>
          <select
            v-model="durationMinutes"
            class="bg-white border border-slate-300 rounded px-2.5 py-1 text-xs outline-none focus:ring-2 focus:ring-brand-500"
          >
            <option :value="10">10 Menit</option>
            <option :value="15">15 Menit (Standar)</option>
            <option :value="30">30 Menit</option>
            <option :value="45">45 Menit</option>
            <option :value="60">60 Menit</option>
          </select>
        </div>

        <Button
          variant="primary"
          size="md"
          :loading="loading"
          class="w-full justify-center"
          @click="handleOpenToken"
        >
          <Timer class="w-4 h-4 mr-1.5" />
          <span>Generate Kode Presensi Mandiri</span>
        </Button>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center justify-between pt-3 border-t border-slate-100">
        <Button
          v-if="session.check_in_code && remainingSeconds > 0"
          variant="danger"
          size="xs"
          :loading="loading"
          @click="handleCloseSession"
        >
          <Lock class="w-3 h-3 mr-1" />
          <span>Tutup / Kunci Presensi Sekarang</span>
        </Button>
        <div v-else />

        <Button variant="outline" size="xs" @click="emit('update:open', false)">
          Tutup Dialog
        </Button>
      </div>
    </div>
  </Modal>
</template>
