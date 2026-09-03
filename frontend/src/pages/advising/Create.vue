<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { AlertTriangle, UserCheck } from 'lucide-vue-next'
import { lecturerService } from '@/services/api/lecturers'
import { studentService } from '@/services/api/students'
import { advisingService } from '@/services/api/advising'
import { useToast } from '@/composables/useToast'
import type { Lecturer } from '@/types/lecturer'
import type { Student } from '@/types/student'
import PageContainer from '@/components/data-display/PageContainer.vue'
import PageHeader from '@/components/data-display/PageHeader.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Select from '@/components/ui/Select.vue'
import Input from '@/components/ui/Input.vue'
import Textarea from '@/components/ui/Textarea.vue'
import FormField from '@/components/form/FormField.vue'
import FormActions from '@/components/form/FormActions.vue'
import Alert from '@/components/ui/Alert.vue'

const router = useRouter()
const toast = useToast()

const lecturers = ref<Lecturer[]>([])
const students = ref<Student[]>([])
const loadingRefs = ref<boolean>(false)
const submitting = ref<boolean>(false)
const errorMessage = ref<string | null>(null)
const serverErrors = ref<Record<string, string[]>>({})

const form = ref({
  lecturer_id: '' as any,
  student_id: '' as any,
  start_date: new Date().toISOString().split('T')[0],
  notes: '',
})

const currentAdvisorWarning = ref<string | null>(null)
const checkingAdvisor = ref<boolean>(false)

async function loadReferenceData() {
  loadingRefs.value = true
  try {
    const [lecRes, studRes] = await Promise.all([
      lecturerService.list({ per_page: 100, status: 'active' }),
      studentService.list({ per_page: 100, status: 'active' }),
    ])
    lecturers.value = lecRes.data || []
    students.value = studRes.data || []
  } catch {
    // Fallback
  } finally {
    loadingRefs.value = false
  }
}

async function checkStudentAdvisor(studentId: number | string) {
  currentAdvisorWarning.value = null
  if (!studentId) return

  checkingAdvisor.value = true
  try {
    const res = await advisingService.getStudentAdvisor(studentId)
    if (res.data && res.data.lecturer) {
      currentAdvisorWarning.value = `Perhatian: Mahasiswa ini saat ini memiliki Dosen PA aktif: ${res.data.lecturer.full_name} (${res.data.lecturer.nidn || '-'}). Penugasan baru ini akan mengalihkan status penugasan lama secara otomatis.`
    }
  } catch {
    // No active advisor
  } finally {
    checkingAdvisor.value = false
  }
}

async function handleSubmit() {
  errorMessage.value = null
  serverErrors.value = {}
  submitting.value = true

  try {
    await advisingService.assignAdvisor({
      student_id: Number(form.value.student_id),
      lecturer_id: Number(form.value.lecturer_id),
      start_date: form.value.start_date,
      notes: form.value.notes || undefined,
    })

    toast.success('Penugasan Dosen Pembimbing Akademik berhasil disimpan.')
    router.push(`/advising/${form.value.lecturer_id}`)
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal menyimpan penugasan Dosen PA.'
    if (err.errors) {
      serverErrors.value = err.errors
    }
  } finally {
    submitting.value = false
  }
}

watch(
  () => form.value.student_id,
  (newId) => {
    if (newId) {
      checkStudentAdvisor(newId)
    } else {
      currentAdvisorWarning.value = null
    }
  }
)

onMounted(() => {
  loadReferenceData()
})
</script>

<template>
  <PageContainer>
    <PageHeader
      title="Penugasan Dosen Pembimbing Akademik"
      subtitle="Tetapkan mahasiswa kepada Dosen Pembimbing Akademik (PA) untuk bimbingan rencana studi dan perkuliahan"
      :breadcrumbs="[
        { label: 'Dashboard', to: '/dashboard' },
        { label: 'Bimbingan Akademik', to: '/advising' },
        { label: 'Penugasan Baru' },
      ]"
    />

    <form class="space-y-5 max-w-2xl" @submit.prevent="handleSubmit">
      <Alert v-if="errorMessage" variant="danger">
        {{ errorMessage }}
      </Alert>

      <Card>
        <template #header>
          <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
            Formulir Penugasan Dosen PA
          </h3>
        </template>

        <div class="space-y-4">
          <!-- Lecturer Selector -->
          <FormField
            label="Pilih Dosen Pembimbing Akademik"
            required
            :error="serverErrors.lecturer_id?.[0]"
          >
            <Select
              v-model="form.lecturer_id"
              required
              :disabled="submitting || loadingRefs"
            >
              <option value="">-- Pilih Dosen PA Aktif --</option>
              <option v-for="l in lecturers" :key="l.id" :value="l.id">
                {{ l.full_name }} (NIDN: {{ l.nidn || '-' }} · {{ l.homebase_study_program?.name || 'Prodi' }})
              </option>
            </Select>
          </FormField>

          <!-- Student Selector -->
          <FormField
            label="Pilih Mahasiswa Bimbingan"
            required
            :error="serverErrors.student_id?.[0]"
          >
            <Select
              v-model="form.student_id"
              required
              :disabled="submitting || loadingRefs"
            >
              <option value="">-- Pilih Mahasiswa Aktif --</option>
              <option v-for="s in students" :key="s.id" :value="s.id">
                {{ s.student_number }} — {{ s.full_name }} ({{ s.study_program?.name || 'Prodi' }})
              </option>
            </Select>
          </FormField>

          <!-- Advisor Conflict Warning -->
          <div
            v-if="currentAdvisorWarning"
            class="p-2.5 rounded-md bg-amber-50 border border-amber-200 text-amber-900 text-xs flex items-start gap-2"
          >
            <AlertTriangle class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
            <p class="leading-relaxed">{{ currentAdvisorWarning }}</p>
          </div>

          <!-- Start Date -->
          <FormField
            label="Tanggal Mulai Penugasan"
            required
            :error="serverErrors.start_date?.[0]"
          >
            <Input
              v-model="form.start_date"
              type="date"
              required
              :disabled="submitting"
            />
          </FormField>

          <!-- Notes -->
          <FormField
            label="Catatan / Keterangan Penugasan (Opsional)"
            :error="serverErrors.notes?.[0]"
          >
            <Textarea
              v-model="form.notes"
              placeholder="Nomor SK Dekan, arahan khusus bimbingan, atau catatan lainnya..."
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
          variant="primary"
          size="md"
          :loading="submitting"
          :disabled="!form.lecturer_id || !form.student_id || checkingAdvisor"
        >
          <UserCheck class="w-4 h-4" />
          <span>Simpan Penugasan Dosen PA</span>
        </Button>
      </FormActions>
    </form>
  </PageContainer>
</template>
