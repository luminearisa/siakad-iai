<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { advisingService } from '@/services/api/advising'
import { useToast } from '@/composables/useToast'
import type { AcademicAdvisor } from '@/types/advising'
import PageContainer from '@/components/data-display/PageContainer.vue'
import PageHeader from '@/components/data-display/PageHeader.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Textarea from '@/components/ui/Textarea.vue'
import FormField from '@/components/form/FormField.vue'
import FormActions from '@/components/form/FormActions.vue'
import Alert from '@/components/ui/Alert.vue'
import Skeleton from '@/components/ui/Skeleton.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const advisorId = route.params.id as string
const advisor = ref<AcademicAdvisor | null>(null)
const pageLoading = ref<boolean>(true)
const submitting = ref<boolean>(false)
const errorMessage = ref<string | null>(null)

const form = ref({
  end_date: '',
  notes: '',
})

async function loadAdvisor() {
  pageLoading.value = true
  try {
    const res = await advisingService.getAdvisor(advisorId)
    advisor.value = res.data
    form.value.end_date = res.data.end_date || new Date().toISOString().split('T')[0]
    form.value.notes = res.data.notes || ''
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal memuat data penugasan.'
  } finally {
    pageLoading.value = false
  }
}

async function handleEndAssignment() {
  submitting.value = true
  errorMessage.value = null
  try {
    await advisingService.endAdvisorAssignment(advisorId, {
      end_date: form.value.end_date,
      notes: form.value.notes,
    })
    toast.success('Penugasan Dosen PA berhasil diakhiri.')
    router.push('/advising')
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal mengakhiri penugasan.'
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  loadAdvisor()
})
</script>

<template>
  <PageContainer>
    <PageHeader
      title="Kelola Penugasan Dosen PA"
      subtitle="Akhiri masa penugasan atau perbarui catatan pembimbing akademik"
      :breadcrumbs="[
        { label: 'Dashboard', to: '/dashboard' },
        { label: 'Bimbingan Akademik', to: '/advising' },
        { label: 'Edit Penugasan' },
      ]"
    />

    <div v-if="pageLoading" class="space-y-4 max-w-2xl">
      <Skeleton height="15rem" rounded="lg" />
    </div>

    <form v-else-if="advisor" class="space-y-5 max-w-2xl" @submit.prevent="handleEndAssignment">
      <Alert v-if="errorMessage" variant="danger">
        {{ errorMessage }}
      </Alert>

      <Card>
        <template #header>
          <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
            Detail Mahasiswa & Dosen PA
          </h3>
        </template>

        <div class="space-y-4 text-xs">
          <div class="grid grid-cols-2 gap-3 p-3 bg-slate-50 border border-slate-200 rounded-md">
            <div>
              <span class="text-slate-400 text-2xs block uppercase font-semibold">Mahasiswa:</span>
              <strong class="text-slate-900 block mt-0.5">{{ advisor.student?.full_name }}</strong>
              <span class="text-slate-500 text-2xs font-mono">NIM: {{ advisor.student?.student_number }}</span>
            </div>
            <div>
              <span class="text-slate-400 text-2xs block uppercase font-semibold">Dosen PA:</span>
              <strong class="text-slate-900 block mt-0.5">{{ advisor.lecturer?.full_name }}</strong>
              <span class="text-slate-500 text-2xs font-mono">NIDN: {{ advisor.lecturer?.nidn || '-' }}</span>
            </div>
          </div>

          <FormField label="Tanggal Berakhir Penugasan" required>
            <Input
              v-model="form.end_date"
              type="date"
              required
              :disabled="submitting"
            />
          </FormField>

          <FormField label="Catatan Akhir Penugasan">
            <Textarea
              v-model="form.notes"
              placeholder="Catatan penyelesaian masa bimbingan, kelulusan mahasiswa, atau rotasi pembimbing..."
              :rows="3"
              :disabled="submitting"
            />
          </FormField>
        </div>
      </Card>

      <FormActions align="right">
        <Button variant="outline" size="md" :disabled="submitting" @click="router.back()">
          Batal
        </Button>
        <Button
          type="submit"
          variant="danger"
          size="md"
          :loading="submitting"
        >
          Akhiri Masa Penugasan PA
        </Button>
      </FormActions>
    </form>
  </PageContainer>
</template>
