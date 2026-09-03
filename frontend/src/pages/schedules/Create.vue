<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { scheduleService } from '@/services/api/schedules'
import { useToast } from '@/composables/useToast'
import type { CreateSchedulePayload } from '@/types/schedule'
import PageContainer from '@/components/data-display/PageContainer.vue'
import PageHeader from '@/components/data-display/PageHeader.vue'
import ScheduleForm from './components/ScheduleForm.vue'

const router = useRouter()
const toast = useToast()

const loading = ref<boolean>(false)
const errorMessage = ref<string | null>(null)
const serverErrors = ref<Record<string, string[]>>({})
const conflicts = ref<string[] | Record<string, string[]> | null>(null)

async function handleSubmit(payload: any) {
  errorMessage.value = null
  serverErrors.value = {}
  conflicts.value = null
  loading.value = true

  try {
    const res = await scheduleService.create(payload as CreateSchedulePayload)
    toast.success('Jadwal perkuliahan berhasil dibuat.')
    router.push(`/schedules/${res.data.id}`)
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal membuat jadwal perkuliahan.'
    if (err.errors) {
      serverErrors.value = err.errors
      // Extract conflict errors for the alert banner
      const conflictKeys = ['schedule', 'room_id', 'lecturer', 'class_id']
      const detectedConflicts: string[] = []
      Object.keys(err.errors).forEach((key) => {
        if (conflictKeys.includes(key)) {
          detectedConflicts.push(...err.errors[key])
        }
      })
      if (detectedConflicts.length > 0) {
        conflicts.value = detectedConflicts
      }
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <PageContainer>
    <PageHeader
      title="Tambah Jadwal Perkuliahan"
      subtitle="Tetapkan alokasi ruangan, hari, dan slot waktu perkuliahan kelas"
      :breadcrumbs="[
        { label: 'Dashboard', to: '/dashboard' },
        { label: 'Jadwal Kuliah', to: '/schedules' },
        { label: 'Tambah Baru' },
      ]"
    />

    <ScheduleForm
      :loading="loading"
      :error-message="errorMessage"
      :server-errors="serverErrors"
      :conflicts="conflicts"
      @submit="handleSubmit"
    />
  </PageContainer>
</template>
