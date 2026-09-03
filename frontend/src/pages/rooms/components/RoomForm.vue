<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import type { Room, RoomCreatePayload, RoomUpdatePayload } from '@/types/room'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import Card from '@/components/ui/Card.vue'
import FormField from '@/components/form/FormField.vue'
import FormActions from '@/components/form/FormActions.vue'
import Alert from '@/components/ui/Alert.vue'

interface Props {
  initialData?: Partial<Room | RoomCreatePayload | RoomUpdatePayload>
  isEdit?: boolean
  loading?: boolean
  serverErrors?: Record<string, string[]>
  errorMessage?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  initialData: () => ({}),
  isEdit: false,
  loading: false,
  serverErrors: () => ({}),
  errorMessage: null,
})

const emit = defineEmits<{
  (e: 'submit', payload: RoomCreatePayload | RoomUpdatePayload): void
  (e: 'cancel'): void
}>()

const router = useRouter()

const form = ref<RoomCreatePayload>({
  code: props.initialData.code || '',
  name: props.initialData.name || '',
  building: props.initialData.building || 'Gedung Utama',
  floor: props.initialData.floor ?? 1,
  capacity: props.initialData.capacity ?? 40,
  room_type: props.initialData.room_type || 'classroom',
  status: props.initialData.status || 'active',
})

const typeOptions = [
  { label: 'Ruang Kelas Teori', value: 'classroom' },
  { label: 'Laboratorium Komputer/Sains', value: 'laboratory' },
  { label: 'Auditorium / Aula', value: 'auditorium' },
]

const statusOptions = [
  { label: 'Tersedia (Aktif)', value: 'active' },
  { label: 'Pemeliharaan (Maintenance)', value: 'maintenance' },
  { label: 'Non-Aktif', value: 'inactive' },
]

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
</script>

<template>
  <form class="space-y-5" @submit.prevent="handleSubmit">
    <!-- Server Error Alert -->
    <Alert v-if="errorMessage" variant="danger">
      {{ errorMessage }}
    </Alert>

    <!-- 1. Identitas Ruangan -->
    <Card>
      <template #header>
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
          1. Identitas & Nama Ruangan
        </h3>
      </template>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
        <!-- Kode Ruangan -->
        <FormField label="Kode Ruangan" required :error="getError('code')">
          <Input
            v-model="form.code"
            placeholder="Contoh: R.101 / LAB-KOM-1"
            required
            maxlength="50"
            :disabled="loading"
          />
        </FormField>

        <!-- Nama Ruangan -->
        <FormField label="Nama Ruangan" required :error="getError('name')">
          <Input
            v-model="form.name"
            placeholder="Contoh: Ruang Kuliah 101"
            required
            maxlength="255"
            :disabled="loading"
          />
        </FormField>
      </div>
    </Card>

    <!-- 2. Lokasi & Kapasitas -->
    <Card>
      <template #header>
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
          2. Lokasi Gedung & Kapasitas Kursi
        </h3>
      </template>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
        <!-- Gedung -->
        <FormField label="Gedung / Blok" :error="getError('building')">
          <Input
            v-model="form.building"
            placeholder="Contoh: Gedung Tarbiyah A"
            maxlength="100"
            :disabled="loading"
          />
        </FormField>

        <!-- Lantai -->
        <FormField label="Lantai Ke-" :error="getError('floor')">
          <Input
            v-model.number="form.floor"
            type="number"
            min="-5"
            max="100"
            placeholder="1"
            :disabled="loading"
          />
        </FormField>

        <!-- Kapasitas -->
        <FormField label="Kapasitas Kursi" required :error="getError('capacity')">
          <Input
            v-model.number="form.capacity"
            type="number"
            min="1"
            max="2000"
            required
            :disabled="loading"
          />
        </FormField>

        <!-- Tipe Ruangan -->
        <FormField label="Tipe Ruangan" :error="getError('room_type')">
          <Select
            v-model="form.room_type"
            :options="typeOptions"
            :disabled="loading"
          />
        </FormField>

        <!-- Status -->
        <FormField label="Status Ruangan" :error="getError('status')">
          <Select
            v-model="form.status"
            :options="statusOptions"
            :disabled="loading"
          />
        </FormField>
      </div>
    </Card>

    <!-- Form Actions -->
    <FormActions align="right">
      <Button variant="outline" size="md" :disabled="loading" @click="handleCancel">
        Batal
      </Button>
      <Button type="submit" variant="primary" size="md" :loading="loading">
        {{ isEdit ? 'Simpan Perubahan' : 'Simpan Ruangan' }}
      </Button>
    </FormActions>
  </form>
</template>
