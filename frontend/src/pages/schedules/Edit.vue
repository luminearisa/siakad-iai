<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { scheduleService } from '@/services/api/schedules'
import { useToast } from '@/composables/useToast'
import type { ClassSchedule, UpdateSchedulePayload } from '@/types/schedule'
import PageContainer from '@/components/data-display/PageContainer.vue'
import PageHeader from '@/components/data-display/PageHeader.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import Alert from '@/components/ui/Alert.vue'
import ScheduleForm from './components/ScheduleForm.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const scheduleId = route.params.id as string
const schedule = ref<ClassSchedule | null>(null)
const pageLoading = ref<boolean>(true)
const saveLoading = ref<boolean>(false)
const errorMessage = ref<string | null>(null)
const serverErrors = ref<Record<string, string[]>>({})
const conflicts = ref<string[] | Record<string, string[]> | null>(null)

async function loadSchedule() {
  pageLoading.value = true
  errorMessage.value = null
  try {
    const res = await scheduleService.get(scheduleId)
    schedule.value = res.data
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal memuat data jadwal perkuliahan.'
  } finally {
    pageLoading.value = false
  }
}

async function handleSubmit(payload: any) {
  errorMessage.value = null
  serverErrors.value = {}
  conflicts.value = null
  saveLoading.value = true

  try {
    const res = await scheduleService.update(scheduleId, payload as UpdateSchedulePayload)
    toast.success('Jadwal perkuliahan berhasil diperbarui.')
    router.push(`/schedules/${res.data.id}`)
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal memperbarui jadwal perkuliahan.'
    if (err.errors) {
      serverErrors.value = err.errors
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
    saveLoading.value = false
  }
}

onMounted(() => {
  loadSchedule()
})
</script>

<template>
  <PageContainer>
    <PageHeader
      title="Edit Jadwal Perkuliahan"
      :subtitle="schedule ? `Memperbarui jadwal ${schedule.academic_class?.course?.name || ''} (${schedule.day_of_week})` : 'Memperbarui data jadwal'"
      :breadcrumbs="[
        { label: 'Dashboard', to: '/dashboard' },
        { label: 'Jadwal Kuliah', to: '/schedules' },
        { label: schedule ? `${schedule.day_of_week}` : 'Detail', to: `/schedules/${scheduleId}` },
        { label: 'Edit' },
      ]"
    />

    <!-- Skeleton Loading -->
    <div v-if="pageLoading" class="space-y-4">
      <Skeleton height="12rem" rounded="lg" />
      <Skeleton height="8rem" rounded="lg" />
    </div>

    <!-- Error Alert -->
    <Alert v-else-if="errorMessage && !schedule" variant="danger">
      {{ errorMessage }}
    </Alert>

    <!-- Edit Form -->
    <ScheduleForm
      v-else-if="schedule"
      :initial-data="schedule"
      is-edit
      :loading="saveLoading"
      :error-message="errorMessage"
      :server-errors="serverErrors"
      :conflicts="conflicts"
      @submit="handleSubmit"
    />
  </PageContainer>
</template>
