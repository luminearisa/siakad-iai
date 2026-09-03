<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { roomService } from '@/services/api/rooms'
import { useToast } from '@/composables/useToast'
import type { Room, RoomUpdatePayload } from '@/types/room'
import PageContainer from '@/components/data-display/PageContainer.vue'
import PageHeader from '@/components/data-display/PageHeader.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import Alert from '@/components/ui/Alert.vue'
import RoomForm from './components/RoomForm.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const roomId = route.params.id as string
const room = ref<Room | null>(null)
const pageLoading = ref<boolean>(true)
const saveLoading = ref<boolean>(false)
const errorMessage = ref<string | null>(null)
const serverErrors = ref<Record<string, string[]>>({})

async function loadRoom() {
  pageLoading.value = true
  errorMessage.value = null
  try {
    const res = await roomService.get(roomId)
    room.value = res.data
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal memuat data ruangan.'
  } finally {
    pageLoading.value = false
  }
}

async function handleSubmit(payload: any) {
  errorMessage.value = null
  serverErrors.value = {}
  saveLoading.value = true

  try {
    const res = await roomService.update(roomId, payload as RoomUpdatePayload)
    toast.success(`Ruangan ${res.data.name} (${res.data.code}) berhasil diperbarui.`)
    router.push(`/rooms/${roomId}`)
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal memperbarui data ruangan.'
    if (err.errors) {
      serverErrors.value = err.errors
    }
  } finally {
    saveLoading.value = false
  }
}

onMounted(() => {
  loadRoom()
})
</script>

<template>
  <PageContainer>
    <PageHeader
      title="Edit Ruangan"
      :subtitle="room ? `Memperbarui konfigurasi ${room.code} — ${room.name}` : 'Memperbarui data ruangan'"
      :breadcrumbs="[
        { label: 'Dashboard', to: '/dashboard' },
        { label: 'Ruangan', to: '/rooms' },
        { label: room ? room.code : 'Detail', to: `/rooms/${roomId}` },
        { label: 'Edit' },
      ]"
    />

    <!-- Skeleton Loading -->
    <div v-if="pageLoading" class="space-y-4">
      <Skeleton height="12rem" rounded="lg" />
      <Skeleton height="8rem" rounded="lg" />
    </div>

    <!-- Error Alert -->
    <Alert v-else-if="errorMessage && !room" variant="danger">
      {{ errorMessage }}
    </Alert>

    <!-- Edit Form -->
    <RoomForm
      v-else-if="room"
      :initial-data="room"
      is-edit
      :loading="saveLoading"
      :error-message="errorMessage"
      :server-errors="serverErrors"
      @submit="handleSubmit"
    />
  </PageContainer>
</template>
