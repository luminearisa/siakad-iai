<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { roomService } from '@/services/api/rooms'
import { useToast } from '@/composables/useToast'
import type { Room } from '@/types/room'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Breadcrumb from '@/components/ui/Breadcrumb.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import Alert from '@/components/ui/Alert.vue'
import Card from '@/components/ui/Card.vue'
import ConfirmModal from '@/components/feedback/ConfirmModal.vue'
import RoomHeader from './components/RoomHeader.vue'
import RoomStatusBadge from './components/RoomStatusBadge.vue'
import RoomTypeBadge from './components/RoomTypeBadge.vue'
import RoomCapacityBadge from './components/RoomCapacityBadge.vue'
import ChangeRoomStatusModal from './components/ChangeRoomStatusModal.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const roomId = route.params.id as string
const room = ref<Room | null>(null)
const loading = ref<boolean>(true)
const errorMessage = ref<string | null>(null)

// Modals
const statusModalOpen = ref<boolean>(false)
const deleteModalOpen = ref<boolean>(false)
const deleteLoading = ref<boolean>(false)

async function loadRoom() {
  loading.value = true
  errorMessage.value = null
  try {
    const res = await roomService.get(roomId)
    room.value = res.data
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal memuat data ruangan.'
  } finally {
    loading.value = false
  }
}

function handleStatusSuccess(updated: Room) {
  room.value = updated
}

async function handleDelete() {
  if (!room.value) return
  deleteLoading.value = true
  try {
    await roomService.delete(room.value.id)
    toast.success('Ruangan berhasil dihapus.')
    deleteModalOpen.value = false
    router.push('/rooms')
  } catch (err: any) {
    toast.error(err.message || 'Gagal menghapus ruangan.')
  } finally {
    deleteLoading.value = false
  }
}

onMounted(() => {
  loadRoom()
})
</script>

<template>
  <PageContainer>
    <div class="mb-3">
      <Breadcrumb
        :items="[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Ruangan', to: '/rooms' },
          { label: room ? `${room.code} — ${room.name}` : 'Detail Ruangan' },
        ]"
      />
    </div>

    <!-- Loading Skeleton -->
    <div v-if="loading" class="space-y-4">
      <Skeleton height="5rem" rounded="lg" />
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <Skeleton height="15rem" rounded="lg" />
        <Skeleton height="15rem" rounded="lg" />
      </div>
    </div>

    <!-- Error State -->
    <Alert v-else-if="errorMessage" variant="danger">
      {{ errorMessage }}
    </Alert>

    <!-- Content -->
    <div v-else-if="room" class="space-y-5">
      <!-- Header -->
      <RoomHeader
        :room="room"
        @change-status="statusModalOpen = true"
        @delete="deleteModalOpen = true"
      />

      <!-- Detail Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <!-- Identitas & Lokasi -->
        <Card>
          <template #header>
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
              Identitas & Lokasi Gedung
            </h3>
          </template>

          <dl class="divide-y divide-slate-100 text-xs">
            <div class="py-2.5 grid grid-cols-3 gap-2">
              <dt class="text-slate-500 font-medium">Kode Ruangan</dt>
              <dd class="col-span-2 font-mono font-bold text-slate-900">{{ room.code }}</dd>
            </div>

            <div class="py-2.5 grid grid-cols-3 gap-2">
              <dt class="text-slate-500 font-medium">Nama Ruangan</dt>
              <dd class="col-span-2 font-bold text-slate-900">{{ room.name }}</dd>
            </div>

            <div class="py-2.5 grid grid-cols-3 gap-2">
              <dt class="text-slate-500 font-medium">Gedung / Blok</dt>
              <dd class="col-span-2 text-slate-800 font-medium">{{ room.building || '-' }}</dd>
            </div>

            <div class="py-2.5 grid grid-cols-3 gap-2">
              <dt class="text-slate-500 font-medium">Lantai Ke-</dt>
              <dd class="col-span-2 text-slate-800">{{ room.floor ? `Lantai ${room.floor}` : '-' }}</dd>
            </div>

            <div class="py-2.5 grid grid-cols-3 gap-2">
              <dt class="text-slate-500 font-medium">Institusi / Kampus</dt>
              <dd class="col-span-2 text-slate-800">{{ room.institution?.name || 'Kampus Utama' }}</dd>
            </div>
          </dl>
        </Card>

        <!-- Kapasitas & Status -->
        <Card>
          <template #header>
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
              Kapasitas Kursi & Klasifikasi Ruang
            </h3>
          </template>

          <dl class="divide-y divide-slate-100 text-xs">
            <div class="py-2.5 grid grid-cols-3 gap-2">
              <dt class="text-slate-500 font-medium">Kapasitas Kursi</dt>
              <dd class="col-span-2">
                <RoomCapacityBadge :capacity="room.capacity" />
              </dd>
            </div>

            <div class="py-2.5 grid grid-cols-3 gap-2">
              <dt class="text-slate-500 font-medium">Tipe Ruangan</dt>
              <dd class="col-span-2">
                <RoomTypeBadge :type="room.room_type" size="xs" />
              </dd>
            </div>

            <div class="py-2.5 grid grid-cols-3 gap-2">
              <dt class="text-slate-500 font-medium">Status Ketersediaan</dt>
              <dd class="col-span-2">
                <RoomStatusBadge :status="room.status" size="xs" />
              </dd>
            </div>
          </dl>
        </Card>
      </div>
    </div>

    <!-- Modals -->
    <ChangeRoomStatusModal
      v-if="room"
      :open="statusModalOpen"
      :room="room"
      @update:open="statusModalOpen = $event"
      @success="handleStatusSuccess"
    />

    <ConfirmModal
      :open="deleteModalOpen"
      title="Hapus Ruangan"
      :message="`Apakah Anda yakin ingin menghapus ruangan ${room?.name} (${room?.code})?`"
      confirm-text="Ya, Hapus Ruangan"
      variant="danger"
      :loading="deleteLoading"
      @update:open="deleteModalOpen = $event"
      @confirm="handleDelete"
    />
  </PageContainer>
</template>
