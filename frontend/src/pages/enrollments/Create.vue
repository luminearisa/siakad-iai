<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { academicService } from '@/services/api/academic'
import { studentService } from '@/services/api/students'
import { enrollmentService } from '@/services/api/enrollments'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import type { Semester } from '@/types/academic'
import type { Student } from '@/types/student'
import PageContainer from '@/components/data-display/PageContainer.vue'
import PageHeader from '@/components/data-display/PageHeader.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Select from '@/components/ui/Select.vue'
import Textarea from '@/components/ui/Textarea.vue'
import FormField from '@/components/form/FormField.vue'
import FormActions from '@/components/form/FormActions.vue'
import Alert from '@/components/ui/Alert.vue'

const router = useRouter()
const authStore = useAuthStore()
const toast = useToast()

const semesters = ref<Semester[]>([])
const students = ref<Student[]>([])
const loading = ref<boolean>(false)
const errorMessage = ref<string | null>(null)
const serverErrors = ref<Record<string, string[]>>({})

const isStudent = computed(() => authStore.isStudent)

const form = ref({
  semester_id: '' as any,
  student_id: '' as any,
  notes: '',
})

async function loadReferenceData() {
  try {
    const semRes = await academicService.getSemesters()
    semesters.value = semRes.data || []
    // Auto-select active semester
    const activeSem = semesters.value.find((s) => s.status === 'active')
    if (activeSem) {
      form.value.semester_id = activeSem.id
    } else if (semesters.value.length > 0) {
      form.value.semester_id = semesters.value[0].id
    }

    if (!isStudent.value) {
      const studRes = await studentService.list({ per_page: 100 })
      students.value = studRes.data || []
      if (students.value.length > 0) {
        form.value.student_id = students.value[0].id
      }
    }
  } catch {
    // Fallback
  }
}

async function handleSubmit() {
  errorMessage.value = null
  serverErrors.value = {}
  loading.value = true

  try {
    const payload: any = {
      semester_id: Number(form.value.semester_id),
      notes: form.value.notes || null,
    }

    let res
    if (!isStudent.value && form.value.student_id) {
      payload.student_id = Number(form.value.student_id)
      res = await enrollmentService.create(payload)
    } else {
      res = await enrollmentService.create(payload)
    }

    toast.success('Rencana Studi (KRS) berhasil dibuat. Silakan pilih kelas perkuliahan.')
    router.push(`/enrollments/${res.data.id}`)
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal membuat KRS baru.'
    if (err.errors) {
      serverErrors.value = err.errors
    }
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadReferenceData()
})
</script>

<template>
  <PageContainer>
    <PageHeader
      :title="isStudent ? 'Buka KRS Semester Baru' : 'Buat KRS Mahasiswa'"
      subtitle="Buka lembar Kartu Rencana Studi baru untuk memulai pemilihan kelas dan mata kuliah"
      :breadcrumbs="[
        { label: 'Dashboard', to: '/dashboard' },
        { label: 'KRS / Enrollment', to: '/enrollments' },
        { label: 'Buka KRS Baru' },
      ]"
    />

    <form class="space-y-5 max-w-2xl" @submit.prevent="handleSubmit">
      <Alert v-if="errorMessage" variant="danger">
        {{ errorMessage }}
      </Alert>

      <Card>
        <template #header>
          <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
            Konteks Akademik & Periode KRS
          </h3>
        </template>

        <div class="space-y-4">
          <!-- Student Selector (Admin only) -->
          <FormField
            v-if="!isStudent"
            label="Pilih Mahasiswa"
            required
            :error="serverErrors.student_id?.[0]"
          >
            <Select
              v-model="form.student_id"
              required
              :disabled="loading"
            >
              <option value="">-- Pilih Mahasiswa --</option>
              <option v-for="s in students" :key="s.id" :value="s.id">
                {{ s.student_number }} — {{ s.full_name }} ({{ s.study_program?.name || 'Prodi' }})
              </option>
            </Select>
          </FormField>

          <!-- Semester Selector -->
          <FormField
            label="Semester Akademik"
            required
            :error="serverErrors.semester_id?.[0]"
          >
            <Select
              v-model="form.semester_id"
              required
              :disabled="loading"
            >
              <option value="">-- Pilih Semester Akademik --</option>
              <option v-for="sem in semesters" :key="sem.id" :value="sem.id">
                {{ sem.name }} ({{ sem.academic_year?.name }} · {{ sem.status === 'active' ? 'Semester Aktif' : 'Non-Aktif' }})
              </option>
            </Select>
          </FormField>

          <!-- Notes -->
          <FormField
            label="Catatan Rencana Studi (Opsional)"
            :error="serverErrors.notes?.[0]"
          >
            <Textarea
              v-model="form.notes"
              placeholder="Catatan konsentrasi, peminatan, atau rencana percepatan studi..."
              :rows="3"
              :disabled="loading"
            />
          </FormField>
        </div>
      </Card>

      <FormActions align="right">
        <Button variant="outline" size="md" :disabled="loading" @click="router.back()">
          Batal
        </Button>
        <Button type="submit" variant="primary" size="md" :loading="loading">
          Buat Lembar KRS & Pilih Kelas
        </Button>
      </FormActions>
    </form>
  </PageContainer>
</template>
