<script setup lang="ts">
import { ref, watch } from 'vue'
import { attendanceService } from '@/services/api/attendance'
import { roomService } from '@/services/api/rooms'
import { useToast } from '@/composables/useToast'
import type { TeachingSession } from '@/types/attendance'
import type { Room } from '@/types/room'
import type { Lecturer } from '@/types/lecturer'
import Modal from '@/components/ui/Modal.vue'
import FormField from '@/components/form/FormField.vue'
import Input from '@/components/ui/Input.vue'
import Textarea from '@/components/ui/Textarea.vue'
import Select from '@/components/ui/Select.vue'
import Button from '@/components/ui/Button.vue'
import Alert from '@/components/ui/Alert.vue'

const props = defineProps<{
  open: boolean
  academicClassId: number
  session?: TeachingSession | null
  nextMeetingNumber?: number
  defaultLecturerId?: number
  lecturers?: Lecturer[]
}>()

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void
  (e: 'saved'): void
}>()

const toast = useToast()
const rooms = ref<Room[]>([])
const loadingRooms = ref<boolean>(false)
const submitting = ref<boolean>(false)
const errorMessage = ref<string | null>(null)
const serverErrors = ref<Record<string, string[]>>({})

const form = ref<{
  academic_class_id: number
  schedule_id?: number | null
  lecturer_id: any
  meeting_number: number
  session_date: string
  start_time: string
  end_time: string
  topic: string
  notes: string
  teaching_method: any
  room_id: any
  status: any
}>({
  academic_class_id: props.academicClassId,
  lecturer_id: '',
  meeting_number: 1,
  session_date: new Date().toISOString().split('T')[0],
  start_time: '08:00',
  end_time: '09:40',
  topic: '',
  notes: '',
  teaching_method: 'offline',
  room_id: '',
  status: 'scheduled',
})

async function loadRooms() {
  loadingRooms.value = true
  try {
    const res = await roomService.list({ per_page: 100 })
    rooms.value = res.data || []
  } catch {
    rooms.value = []
  } finally {
    loadingRooms.value = false
  }
}

function initForm() {
  if (props.session) {
    form.value = {
      academic_class_id: props.session.academic_class_id,
      schedule_id: props.session.schedule_id,
      lecturer_id: props.session.lecturer_id,
      meeting_number: props.session.meeting_number,
      session_date: props.session.session_date,
      start_time: props.session.start_time || '08:00',
      end_time: props.session.end_time || '09:40',
      topic: props.session.topic || '',
      notes: props.session.notes || '',
      teaching_method: props.session.teaching_method || 'offline',
      room_id: props.session.room_id || '',
      status: props.session.status || 'scheduled',
    }
  } else {
    form.value = {
      academic_class_id: props.academicClassId,
      lecturer_id: props.defaultLecturerId || (props.lecturers && props.lecturers.length > 0 ? props.lecturers[0].id : ('' as any)),
      meeting_number: props.nextMeetingNumber || 1,
      session_date: new Date().toISOString().split('T')[0],
      start_time: '08:00',
      end_time: '09:40',
      topic: '',
      notes: '',
      teaching_method: 'offline',
      room_id: '',
      status: 'scheduled',
    }
  }
  errorMessage.value = null
  serverErrors.value = {}
}

async function handleSubmit() {
  if (!form.value.meeting_number || !form.value.session_date || !form.value.lecturer_id) return
  submitting.value = true
  errorMessage.value = null
  serverErrors.value = {}

  try {
    const payload = {
      ...form.value,
      academic_class_id: props.academicClassId,
      lecturer_id: Number(form.value.lecturer_id),
      room_id: form.value.room_id ? Number(form.value.room_id) : null,
    }

    if (props.session) {
      await attendanceService.updateSession(props.session.id, payload)
      toast.success('Berita Acara Perkuliahan berhasil diperbarui.')
    } else {
      await attendanceService.createSession(payload)
      toast.success(`Pertemuan ke-${form.value.meeting_number} berhasil dibuka.`)
    }

    emit('update:open', false)
    emit('saved')
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal menyimpan sesi perkuliahan.'
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
      loadRooms()
      initForm()
    }
  }
)
</script>

<template>
  <Modal
    :open="open"
    :title="session ? `Edit BAP Pertemuan ke-${session.meeting_number}` : `Buka Sesi Pertemuan ke-${nextMeetingNumber || 1}`"
    size="lg"
    @update:open="emit('update:open', $event)"
  >
    <form class="space-y-4 text-xs" @submit.prevent="handleSubmit">
      <Alert v-if="errorMessage" variant="danger">
        {{ errorMessage }}
      </Alert>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <!-- Meeting Number -->
        <FormField
          label="Pertemuan Ke"
          required
          :error="serverErrors.meeting_number?.[0]"
        >
          <Input
            v-model="form.meeting_number"
            type="number"
            min="1"
            max="32"
            required
            :disabled="submitting || !!session"
          />
        </FormField>

        <!-- Date -->
        <FormField
          label="Tanggal Perkuliahan"
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

        <!-- Teaching Method -->
        <FormField
          label="Metode Pembelajaran"
          required
          :error="serverErrors.teaching_method?.[0]"
        >
          <Select
            v-model="form.teaching_method"
            required
            :disabled="submitting"
          >
            <option value="offline">Luring (Tatap Muka)</option>
            <option value="online">Daring (Kuliah Online)</option>
            <option value="hybrid">Hybrid (Bauran)</option>
          </Select>
        </FormField>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <!-- Start Time -->
        <FormField
          label="Jam Mulai"
          :error="serverErrors.start_time?.[0]"
        >
          <Input
            v-model="form.start_time"
            type="time"
            :disabled="submitting"
          />
        </FormField>

        <!-- End Time -->
        <FormField
          label="Jam Selesai"
          :error="serverErrors.end_time?.[0]"
        >
          <Input
            v-model="form.end_time"
            type="time"
            :disabled="submitting"
          />
        </FormField>

        <!-- Room -->
        <FormField
          label="Ruangan"
          :error="serverErrors.room_id?.[0]"
        >
          <Select
            v-model="form.room_id"
            :disabled="submitting || loadingRooms"
          >
            <option value="">-- Tanpa Ruangan / Online --</option>
            <option v-for="r in rooms" :key="r.id" :value="r.id">
              {{ r.code }} — {{ r.name }}
            </option>
          </Select>
        </FormField>

        <!-- Status -->
        <FormField
          label="Status Sesi"
          :error="serverErrors.status?.[0]"
        >
          <Select
            v-model="form.status"
            :disabled="submitting"
          >
            <option value="scheduled">Dijadwalkan</option>
            <option value="open">Sedang Berlangsung</option>
            <option value="closed">Selesai / Terkunci</option>
            <option value="cancelled">Dibatalkan</option>
          </Select>
        </FormField>
      </div>

      <!-- Lecturer -->
      <FormField
        v-if="lecturers && lecturers.length > 0"
        label="Dosen Pengampu / Pengajar Sesi"
        required
        :error="serverErrors.lecturer_id?.[0]"
      >
        <Select
          v-model="form.lecturer_id"
          required
          :disabled="submitting"
        >
          <option v-for="l in lecturers" :key="l.id" :value="l.id">
            {{ l.full_name }} ({{ l.nidn || l.lecturer_number || '-' }})
          </option>
        </Select>
      </FormField>

      <!-- Topic / Subject -->
      <FormField
        label="Topik / Pokok Bahasan Perkuliahan"
        required
        :error="serverErrors.topic?.[0]"
      >
        <Input
          v-model="form.topic"
          placeholder="Contoh: Pengantar Algoritma Greedy, Analisis Kompleksitas Waktu..."
          required
          :disabled="submitting"
        />
      </FormField>

      <!-- Notes / BAP -->
      <FormField
        label="Berita Acara Perkuliahan (BAP) / Catatan Evaluasi Pembelajaran"
        :error="serverErrors.notes?.[0]"
      >
        <Textarea
          v-model="form.notes"
          placeholder="Tuliskan catatan jalannya perkuliahan, kehadiran mahasiswa, evaluasi pemahaman materi, tugas yang diberikan..."
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
          :loading="submitting"
          :disabled="!form.meeting_number || !form.session_date || !form.topic?.trim()"
        >
          <span>{{ session ? 'Simpan Perubahan BAP' : 'Buka Sesi Pertemuan' }}</span>
        </Button>
      </div>
    </form>
  </Modal>
</template>
