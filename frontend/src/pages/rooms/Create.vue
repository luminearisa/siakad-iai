<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { roomService } from '@/services/api/rooms'
import { useToast } from '@/composables/useToast'
import type { RoomCreatePayload } from '@/types/room'
import PageContainer from '@/components/data-display/PageContainer.vue'
import PageHeader from '@/components/data-display/PageHeader.vue'
import RoomForm from './components/RoomForm.vue'

const router = useRouter()
const toast = useToast()

const loading = ref<boolean>(false)
const errorMessage = ref<string | null>(null)
const serverErrors = ref<Record<string, string[]>>({})

async function handleSubmit(payload: any) {
  errorMessage.value = null
  serverErrors.value = {}
  loading.value = true

  try {
    const res = await roomService.create(payload as RoomCreatePayload)
    toast.success(`Ruangan ${res.data.name} (${res.data.code}) berhasil ditambahkan.`)
    router.push(`/rooms/${res.data.id}`)
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal menyimpan ruangan.'
    if (err.errors) {
      serverErrors.value = err.errors
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <PageContainer>
    <PageHeader
      title="Tambah Ruangan Baru"
      subtitle="Definisikan kode ruangan, nama ruangan, kapasitas, lokasi gedung, dan tipe ruangan"
      :breadcrumbs="[
        { label: 'Dashboard', to: '/dashboard' },
        { label: 'Ruangan', to: '/rooms' },
        { label: 'Tambah Baru' },
      ]"
    />

    <RoomForm
      :loading="loading"
      :error-message="errorMessage"
      :server-errors="serverErrors"
      @submit="handleSubmit"
    />
  </PageContainer>
</template>
