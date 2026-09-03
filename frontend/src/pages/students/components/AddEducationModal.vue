<script setup lang="ts">
import { ref } from 'vue'
import { studentService } from '@/services/api/students'
import { useToast } from '@/composables/useToast'
import type { Student, StudentEducation, StudentEducationPayload } from '@/types/student'
import Modal from '@/components/ui/Modal.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import FormField from '@/components/form/FormField.vue'
import Alert from '@/components/ui/Alert.vue'

interface Props {
  open: boolean
  student: Student
}

const props = defineProps<Props>()

const emit = defineEmits<{
  (e: 'update:open', val: boolean): void
  (e: 'success', education: StudentEducation): void
}>()

const toast = useToast()
const loading = ref<boolean>(false)
const errorMessage = ref<string | null>(null)
const serverErrors = ref<Record<string, string[]>>({})

const form = ref<StudentEducationPayload>({
  institution_name: '',
  level: 'SMA',
  major: '',
  graduation_year: new Date().getFullYear() - 1,
  certificate_number: '',
  notes: '',
})

const levelOptions = [
  { label: 'SMA / MA / Sederajat', value: 'SMA' },
  { label: 'Madrasah Aliyah (MA)', value: 'MA' },
  { label: 'SMK', value: 'SMK' },
  { label: 'Pondok Pesantren / Muadalah', value: 'Pesantren' },
  { label: 'Diploma (D1/D2/D3)', value: 'Diploma' },
  { label: 'Sarjana (S1)', value: 'S1' },
  { label: 'Lainnya', value: 'Lainnya' },
]

function getError(field: string): string | null {
  if (serverErrors.value[field] && serverErrors.value[field].length > 0) {
    return serverErrors.value[field][0]
  }
  return null
}

async function handleSubmit() {
  errorMessage.value = null
  serverErrors.value = {}

  if (!form.value.institution_name.trim()) {
    serverErrors.value.institution_name = ['Nama institusi/sekolah wajib diisi.']
    return
  }

  loading.value = true
  try {
    const res = await studentService.addEducation(props.student.id, form.value)
    toast.success('Riwayat pendidikan berhasil ditambahkan.')
    emit('success', res.data)
    emit('update:open', false)
    form.value = {
      institution_name: '',
      level: 'SMA',
      major: '',
      graduation_year: new Date().getFullYear() - 1,
      certificate_number: '',
      notes: '',
    }
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal menambahkan riwayat pendidikan.'
    if (err.errors) {
      serverErrors.value = err.errors
    }
  } finally {
    loading.value = false
  }
}

function handleClose() {
  emit('update:open', false)
}
</script>

<template>
  <Modal
    :open="open"
    title="Tambah Riwayat Pendidikan Asal"
    size="md"
    @update:open="emit('update:open', $event)"
  >
    <form class="space-y-3.5" @submit.prevent="handleSubmit">
      <Alert v-if="errorMessage" variant="danger">
        {{ errorMessage }}
      </Alert>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <!-- Jenjang -->
        <FormField label="Jenjang Pendidikan" required :error="getError('level')">
          <Select
            v-model="form.level"
            :options="levelOptions"
            required
            :disabled="loading"
          />
        </FormField>

        <!-- Nama Institusi -->
        <FormField label="Nama Sekolah / Institusi" required :error="getError('institution_name')">
          <Input
            v-model="form.institution_name"
            placeholder="e.g. SMAN 1 Bandung, MAN 2..."
            required
            :disabled="loading"
          />
        </FormField>

        <!-- Jurusan -->
        <FormField label="Jurusan / Program Keahlian" :error="getError('major')">
          <Input
            v-model="form.major"
            placeholder="e.g. IPA, IPS, Keagamaan..."
            :disabled="loading"
          />
        </FormField>

        <!-- Tahun Kelulusan -->
        <FormField label="Tahun Kelulusan" :error="getError('graduation_year')">
          <Input
            v-model="form.graduation_year"
            type="number"
            placeholder="2024"
            :disabled="loading"
          />
        </FormField>

        <!-- No Ijazah -->
        <div class="sm:col-span-2">
          <FormField label="Nomor Ijazah / Sertifikat" :error="getError('certificate_number')">
            <Input
              v-model="form.certificate_number"
              placeholder="Nomor seri ijazah kelulusan"
              :disabled="loading"
            />
          </FormField>
        </div>
      </div>
    </form>

    <template #footer>
      <Button variant="outline" size="sm" :disabled="loading" @click="handleClose">
        Batal
      </Button>
      <Button variant="primary" size="sm" :loading="loading" @click="handleSubmit">
        Simpan Riwayat Pendidikan
      </Button>
    </template>
  </Modal>
</template>
