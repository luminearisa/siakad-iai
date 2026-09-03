<script setup lang="ts">
import { ref, watch } from 'vue'
import { roomService } from '@/services/api/rooms'
import { useToast } from '@/composables/useToast'
import type { Room, RoomStatus } from '@/types/room'
import Modal from '@/components/ui/Modal.vue'
import Button from '@/components/ui/Button.vue'
import Select from '@/components/ui/Select.vue'
import FormField from '@/components/form/FormField.vue'
import RoomStatusBadge from './RoomStatusBadge.vue'
import Alert from '@/components/ui/Alert.vue'

interface Props {
  open: boolean
  room: Room
}

const props = defineProps<Props>()

const emit = defineEmits<{
  (e: 'update:open', val: boolean): void
  (e: 'success', updatedRoom: Room): void
}>()

const toast = useToast()
const loading = ref<boolean>(false)
const errorMessage = ref<string | null>(null)
const newStatus = ref<RoomStatus>(props.room.status)

const statusOptions = [
  { label: 'Tersedia (Aktif)', value: 'active' },
  { label: 'Pemeliharaan (Maintenance)', value: 'maintenance' },
  { label: 'Non-Aktif', value: 'inactive' },
]

watch(
  () => props.room,
  (r) => {
    if (r) {
      newStatus.value = r.status
      errorMessage.value = null
    }
  },
  { immediate: true }
)

async function handleSubmit() {
  errorMessage.value = null
  loading.value = true
  try {
    const res = await roomService.changeStatus(props.room.id, newStatus.value)
    toast.success('Status ruangan berhasil diperbarui.')
    emit('success', res.data)
    emit('update:open', false)
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal mengubah status ruangan.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <Modal
    :open="open"
    title="Ubah Status Ruangan"
    size="md"
    @update:open="emit('update:open', $event)"
  >
    <div class="space-y-4">
      <Alert v-if="errorMessage" variant="danger">
        {{ errorMessage }}
      </Alert>

      <div class="p-3 bg-slate-50 rounded-lg border border-slate-200 flex items-center justify-between text-xs">
        <div>
          <span class="text-slate-500 block text-2xs">Ruangan:</span>
          <strong class="text-slate-900 font-mono">{{ room.code }}</strong>
          <span class="text-slate-700 ml-1 font-medium">— {{ room.name }}</span>
        </div>
        <div class="text-right">
          <span class="text-slate-500 block text-2xs mb-0.5">Status Saat Ini:</span>
          <RoomStatusBadge :status="room.status" size="xs" />
        </div>
      </div>

      <!-- New Status Selector -->
      <FormField label="Pilih Status Ruangan Baru" required>
        <Select
          v-model="newStatus"
          :options="statusOptions"
          required
          :disabled="loading"
          size="md"
        />
      </FormField>
    </div>

    <template #footer>
      <Button variant="outline" size="sm" :disabled="loading" @click="emit('update:open', false)">
        Batal
      </Button>
      <Button variant="primary" size="sm" :loading="loading" @click="handleSubmit">
        Simpan Status
      </Button>
    </template>
  </Modal>
</template>
