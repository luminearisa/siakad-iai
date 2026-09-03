<script setup lang="ts">
import { ref, watch } from 'vue'
import { CheckCircle2, Save } from 'lucide-vue-next'
import { attendanceService } from '@/services/api/attendance'
import { useToast } from '@/composables/useToast'
import type { AttendanceStatusCode, StudentAttendance, TeachingSession } from '@/types/attendance'
import Modal from '@/components/ui/Modal.vue'
import Button from '@/components/ui/Button.vue'
import Alert from '@/components/ui/Alert.vue'

const props = defineProps<{
  open: boolean
  session: TeachingSession | null
}>()

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void
  (e: 'saved'): void
}>()

const toast = useToast()
const attendances = ref<StudentAttendance[]>([])
const loading = ref<boolean>(false)
const saving = ref<boolean>(false)
const errorMessage = ref<string | null>(null)

async function loadSessionStudents() {
  if (!props.session) return
  loading.value = true
  errorMessage.value = null
  try {
    const res = await attendanceService.getSessionStudents(props.session.id)
    attendances.value = res.data || []
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal memuat data presensi mahasiswa.'
  } finally {
    loading.value = false
  }
}

function setAllStatus(status: AttendanceStatusCode) {
  attendances.value.forEach((item) => {
    item.status = status
  })
}

async function handleSave() {
  if (!props.session) return
  saving.value = true
  errorMessage.value = null
  try {
    const payload = {
      attendances: attendances.value.map((item) => ({
        student_id: item.student_id,
        status: item.status,
        notes: item.notes || undefined,
      })),
    }

    await attendanceService.recordBatch(props.session.id, payload)
    toast.success('Presensi kelas berhasil disimpan.')
    emit('saved')
    emit('update:open', false)
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal menyimpan presensi.'
  } finally {
    saving.value = false
  }
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      loadSessionStudents()
    }
  }
)
</script>

<template>
  <Modal
    :open="open"
    :title="session ? `Input Presensi — Pertemuan ke-${session.meeting_number}` : 'Input Presensi Mahasiswa'"
    size="xl"
    @update:open="emit('update:open', $event)"
  >
    <div class="space-y-4">
      <Alert v-if="errorMessage" variant="danger">
        {{ errorMessage }}
      </Alert>

      <!-- Quick Action Toolbar -->
      <div class="flex flex-wrap items-center justify-between gap-2 p-3 bg-slate-50 border border-slate-200 rounded-lg text-xs">
        <div class="flex items-center gap-2">
          <span class="font-bold text-slate-700">Aksi Cepat:</span>
          <Button variant="outline" size="xs" class="text-emerald-700 hover:bg-emerald-50 border-emerald-300" @click="setAllStatus('present')">
            <CheckCircle2 class="w-3.5 h-3.5 mr-1" />
            <span>Set Semua Hadir</span>
          </Button>
          <Button variant="ghost" size="xs" class="text-slate-600" @click="setAllStatus('absent')">
            <span>Set Semua Alpa</span>
          </Button>
        </div>

        <div class="flex items-center gap-2 text-2xs text-slate-500 font-medium">
          <span>Total Mahasiswa: <strong>{{ attendances.length }}</strong></span>
        </div>
      </div>

      <!-- Students Attendance Table -->
      <div class="border border-slate-200 rounded-lg overflow-hidden max-h-[420px] overflow-y-auto">
        <table class="w-full text-left text-xs border-collapse">
          <thead class="bg-slate-100/80 sticky top-0 z-10 border-b border-slate-200 text-2xs uppercase tracking-wider text-slate-600 font-bold">
            <tr>
              <th class="py-2.5 px-3 w-12 text-center">No</th>
              <th class="py-2.5 px-3 w-32">NIM</th>
              <th class="py-2.5 px-3">Nama Mahasiswa</th>
              <th class="py-2.5 px-3 w-72 text-center">Status Kehadiran</th>
              <th class="py-2.5 px-3 w-48">Keterangan</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 bg-white">
            <tr v-if="loading" class="text-center">
              <td colspan="5" class="py-8 text-slate-400">
                Memuat daftar mahasiswa...
              </td>
            </tr>
            <tr v-else-if="attendances.length === 0" class="text-center">
              <td colspan="5" class="py-8 text-slate-400">
                Belum ada mahasiswa yang terdaftar di kelas ini.
              </td>
            </tr>
            <tr
              v-for="(att, idx) in attendances"
              :key="att.id || att.student_id"
              class="hover:bg-slate-50/70 transition-colors"
            >
              <td class="py-2 px-3 text-center text-slate-400 font-mono text-2xs">
                {{ idx + 1 }}
              </td>
              <td class="py-2 px-3 font-mono font-bold text-slate-800 text-2xs">
                {{ att.student?.student_number || '-' }}
              </td>
              <td class="py-2 px-3">
                <span class="font-semibold text-slate-900 block">{{ att.student?.full_name || '-' }}</span>
                <span class="text-2xs text-slate-400">{{ att.student?.study_program?.name || 'Mahasiswa' }}</span>
              </td>
              <td class="py-2 px-3 text-center">
                <!-- Status Toggle Radios -->
                <div class="inline-flex items-center gap-1 bg-slate-100 p-1 rounded-md border border-slate-200">
                  <button
                    type="button"
                    :class="[
                      'px-2.5 py-1 rounded text-2xs font-bold transition-all',
                      att.status === 'present' ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900',
                    ]"
                    @click="att.status = 'present'"
                  >
                    Hadir (H)
                  </button>
                  <button
                    type="button"
                    :class="[
                      'px-2.5 py-1 rounded text-2xs font-bold transition-all',
                      att.status === 'permit' ? 'bg-blue-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900',
                    ]"
                    @click="att.status = 'permit'"
                  >
                    Izin (I)
                  </button>
                  <button
                    type="button"
                    :class="[
                      'px-2.5 py-1 rounded text-2xs font-bold transition-all',
                      att.status === 'sick' ? 'bg-amber-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900',
                    ]"
                    @click="att.status = 'sick'"
                  >
                    Sakit (S)
                  </button>
                  <button
                    type="button"
                    :class="[
                      'px-2.5 py-1 rounded text-2xs font-bold transition-all',
                      att.status === 'absent' ? 'bg-rose-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900',
                    ]"
                    @click="att.status = 'absent'"
                  >
                    Alpa (A)
                  </button>
                </div>
              </td>
              <td class="py-2 px-3">
                <input
                  v-model="att.notes"
                  type="text"
                  placeholder="Catatan..."
                  class="w-full bg-slate-50 border border-slate-200 focus:bg-white focus:border-brand-500 rounded px-2 py-1 text-2xs outline-none"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex items-center justify-between pt-3 border-t border-slate-100 text-xs">
        <span class="text-2xs text-slate-400">Pastikan status presensi mahasiswa telah sesuai sebelum menyimpan.</span>
        <div class="flex items-center gap-2">
          <Button variant="outline" size="sm" :disabled="saving" @click="emit('update:open', false)">
            Batal
          </Button>
          <Button
            variant="primary"
            size="sm"
            :loading="saving"
            :disabled="attendances.length === 0"
            @click="handleSave"
          >
            <Save class="w-3.5 h-3.5 mr-1" />
            <span>Simpan Presensi Kelas</span>
          </Button>
        </div>
      </div>
    </div>
  </Modal>
</template>
