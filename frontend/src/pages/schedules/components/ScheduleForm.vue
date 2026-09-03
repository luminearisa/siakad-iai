<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { classService } from '@/services/api/classes'
import { roomService } from '@/services/api/rooms'
import type { AcademicClass } from '@/types/class'
import type { Room } from '@/types/room'
import type { ClassSchedule, CreateSchedulePayload, UpdateSchedulePayload, DayOfWeek } from '@/types/schedule'
import Button from '@/components/ui/Button.vue'
import Select from '@/components/ui/Select.vue'
import Input from '@/components/ui/Input.vue'
import Textarea from '@/components/ui/Textarea.vue'
import Card from '@/components/ui/Card.vue'
import FormField from '@/components/form/FormField.vue'
import FormActions from '@/components/form/FormActions.vue'
import DaySelector from './DaySelector.vue'
import TimeRangePicker from './TimeRangePicker.vue'
import ScheduleConflictAlert from './ScheduleConflictAlert.vue'
import RoomCapacityBadge from '@/pages/rooms/components/RoomCapacityBadge.vue'

interface Props {
  initialData?: Partial<ClassSchedule | CreateSchedulePayload | UpdateSchedulePayload>
  isEdit?: boolean
  loading?: boolean
  serverErrors?: Record<string, string[]>
  conflicts?: string[] | Record<string, string[]> | null
  errorMessage?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  initialData: () => ({}),
  isEdit: false,
  loading: false,
  serverErrors: () => ({}),
  conflicts: null,
  errorMessage: null,
})

const emit = defineEmits<{
  (e: 'submit', payload: CreateSchedulePayload | UpdateSchedulePayload): void
  (e: 'cancel'): void
}>()

const router = useRouter()
const classes = ref<AcademicClass[]>([])
const rooms = ref<Room[]>([])

const form = ref<CreateSchedulePayload>({
  class_id: props.initialData.class_id || ('' as any),
  room_id: props.initialData.room_id || null,
  day_of_week: (props.initialData.day_of_week as DayOfWeek) || 'monday',
  start_time: props.initialData.start_time || '08:00',
  end_time: props.initialData.end_time || '09:40',
  effective_from: props.initialData.effective_from || null,
  effective_until: props.initialData.effective_until || null,
  status: props.initialData.status || 'active',
  notes: props.initialData.notes || '',
})

const selectedClass = computed(() => {
  return classes.value.find(c => c.id === Number(form.value.class_id)) || null
})

const selectedRoom = computed(() => {
  if (!form.value.room_id) return null
  return rooms.value.find(r => r.id === Number(form.value.room_id)) || null
})

const capacityWarning = computed(() => {
  if (selectedClass.value && selectedRoom.value) {
    if (selectedRoom.value.capacity < selectedClass.value.capacity) {
      return `Perhatian: Kapasitas ruangan (${selectedRoom.value.capacity} kursi) lebih kecil dari kuota kelas (${selectedClass.value.capacity} mahasiswa).`
    }
  }
  return null
})

const statusOptions = [
  { label: 'Aktif Berjalan (Active)', value: 'active' },
  { label: 'Dibatalkan (Cancelled)', value: 'cancelled' },
]

async function loadReferenceData() {
  try {
    const [classRes, roomRes] = await Promise.all([
      classService.list({ per_page: 100 }),
      roomService.list({ per_page: 100 }),
    ])
    classes.value = classRes.data || []
    rooms.value = roomRes.data || []
    if (!form.value.class_id && classes.value.length > 0 && !props.isEdit) {
      form.value.class_id = classes.value[0].id
    }
  } catch {
    // Fallback
  }
}

function getError(field: string): string | null {
  if (props.serverErrors && props.serverErrors[field] && props.serverErrors[field].length > 0) {
    return props.serverErrors[field][0]
  }
  return null
}

function handleSubmit() {
  emit('submit', { ...form.value })
}

function handleCancel() {
  emit('cancel')
  router.back()
}

onMounted(() => {
  loadReferenceData()
})
</script>

<template>
  <form class="space-y-5" @submit.prevent="handleSubmit">
    <!-- Conflict Warning Alert -->
    <ScheduleConflictAlert
      v-if="conflicts"
      :conflicts="conflicts"
    />

    <!-- 1. Pemilihan Kelas Perkuliahan -->
    <Card>
      <template #header>
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
          1. Kelas Perkuliahan
        </h3>
      </template>

      <div class="space-y-3">
        <FormField label="Pilih Kelas Perkuliahan" required :error="getError('class_id')">
          <Select
            v-model="form.class_id"
            required
            :disabled="loading || isEdit"
          >
            <option value="">-- Pilih Kelas Perkuliahan --</option>
            <option v-for="c in classes" :key="c.id" :value="c.id">
              {{ c.code }} — {{ c.course?.name || c.name }} (Kelas {{ c.section }}) · {{ c.semester?.name }}
            </option>
          </Select>
        </FormField>

        <!-- Preview Context Kelas Terpilih -->
        <div v-if="selectedClass" class="p-3.5 bg-brand-50/60 rounded-lg border border-brand-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
          <div>
            <span class="text-brand-900 font-bold block text-sm">
              {{ selectedClass.course?.name || selectedClass.name }} (Kelas {{ selectedClass.section }})
            </span>
            <span class="text-slate-600 text-2xs">
              {{ selectedClass.course?.code }} · {{ selectedClass.course?.credits || 0 }} SKS · {{ selectedClass.study_program?.name || 'Umum' }}
            </span>
          </div>

          <div class="flex items-center gap-2 font-mono text-2xs shrink-0">
            <span class="bg-white px-2 py-1 rounded border border-brand-200 text-slate-700">
              Semester: {{ selectedClass.semester?.name || '-' }}
            </span>
            <span class="bg-white px-2 py-1 rounded border border-brand-200 text-slate-700">
              Kuota: {{ selectedClass.capacity }} Mahasiswa
            </span>
          </div>
        </div>
      </div>
    </Card>

    <!-- 2. Alokasi Ruangan Perkuliahan -->
    <Card>
      <template #header>
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
          2. Alokasi Ruangan Perkuliahan
        </h3>
      </template>

      <div class="space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
          <FormField label="Ruangan Perkuliahan" :error="getError('room_id')">
            <Select
              v-model="form.room_id"
              :disabled="loading"
            >
              <option :value="null">-- Ruangan Belum Ditentukan (TBD) --</option>
              <option v-for="r in rooms" :key="r.id" :value="r.id">
                {{ r.code }} — {{ r.name }} ({{ r.building || 'Kampus' }} · {{ r.capacity }} Kursi)
              </option>
            </Select>
          </FormField>

          <!-- Room Preview -->
          <div v-if="selectedRoom" class="p-3 bg-slate-50 rounded-lg border border-slate-200 flex items-center justify-between text-xs">
            <div>
              <strong class="text-slate-900 font-mono">{{ selectedRoom.code }}</strong>
              <span class="text-slate-600 block text-2xs">{{ selectedRoom.building || 'Gedung Utama' }} <template v-if="selectedRoom.floor">· Lantai {{ selectedRoom.floor }}</template></span>
            </div>
            <RoomCapacityBadge :capacity="selectedRoom.capacity" />
          </div>
        </div>

        <!-- Warning kapasitas jika kuota kelas > kapasitas ruangan -->
        <p v-if="capacityWarning" class="text-xs text-amber-700 bg-amber-50 p-2.5 rounded border border-amber-200 font-medium">
          ⚠ {{ capacityWarning }}
        </p>
      </div>
    </Card>

    <!-- 3. Hari & Slot Waktu Perkuliahan -->
    <Card>
      <template #header>
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
          3. Hari & Slot Waktu Kuliah
        </h3>
      </template>

      <div class="space-y-4">
        <!-- Day Selector -->
        <FormField label="Pilih Hari Perkuliahan" required :error="getError('day_of_week')">
          <DaySelector
            v-model="form.day_of_week"
            :disabled="loading"
          />
        </FormField>

        <!-- Time Range Picker -->
        <TimeRangePicker
          v-model:start-time="form.start_time"
          v-model:end-time="form.end_time"
          :disabled="loading"
          :error="getError('start_time') || getError('end_time')"
        />
      </div>
    </Card>

    <!-- 4. Periode Keberlakuan & Catatan -->
    <Card>
      <template #header>
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
          4. Periode & Catatan Tambahan
        </h3>
      </template>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
        <FormField label="Berlaku Mulai Tanggal" :error="getError('effective_from')">
          <Input
            v-model="form.effective_from"
            type="date"
            :disabled="loading"
          />
        </FormField>

        <FormField label="Berlaku Sampai Tanggal" :error="getError('effective_until')">
          <Input
            v-model="form.effective_until"
            type="date"
            :disabled="loading"
          />
        </FormField>

        <FormField label="Status Jadwal" :error="getError('status')">
          <Select
            v-model="form.status"
            :options="statusOptions"
            :disabled="loading"
          />
        </FormField>

        <div class="sm:col-span-2 lg:col-span-3">
          <FormField label="Catatan Tambahan" :error="getError('notes')">
            <Textarea
              v-model="form.notes"
              placeholder="Catatan perkuliahan, instruksi ruangan, atau jadwal lab..."
              :rows="2"
              :disabled="loading"
            />
          </FormField>
        </div>
      </div>
    </Card>

    <!-- Form Actions -->
    <FormActions align="right">
      <Button variant="outline" size="md" :disabled="loading" @click="handleCancel">
        Batal
      </Button>
      <Button type="submit" variant="primary" size="md" :loading="loading">
        {{ isEdit ? 'Simpan Perubahan Jadwal' : 'Jadwalkan Perkuliahan' }}
      </Button>
    </FormActions>
  </form>
</template>
