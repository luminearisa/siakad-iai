<script setup lang="ts">
import { ref } from 'vue'
import { studentService } from '@/services/api/students'
import { useToast } from '@/composables/useToast'
import type { Student, StudentFamily, StudentFamilyPayload } from '@/types/student'
import Modal from '@/components/ui/Modal.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import Textarea from '@/components/ui/Textarea.vue'
import FormField from '@/components/form/FormField.vue'
import Alert from '@/components/ui/Alert.vue'

interface Props {
  open: boolean
  student: Student
}

const props = defineProps<Props>()

const emit = defineEmits<{
  (e: 'update:open', val: boolean): void
  (e: 'success', family: StudentFamily): void
}>()

const toast = useToast()
const loading = ref<boolean>(false)
const errorMessage = ref<string | null>(null)
const serverErrors = ref<Record<string, string[]>>({})

const form = ref<StudentFamilyPayload>({
  relationship: 'father',
  full_name: '',
  phone: '',
  occupation: '',
  address: '',
  notes: '',
})

const relationshipOptions = [
  { label: 'Ayah Kandung', value: 'father' },
  { label: 'Ibu Kandung', value: 'mother' },
  { label: 'Wali Mahasiswa', value: 'guardian' },
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

  if (!form.value.full_name.trim()) {
    serverErrors.value.full_name = ['Nama lengkap keluarga wajib diisi.']
    return
  }

  loading.value = true
  try {
    const res = await studentService.addFamily(props.student.id, form.value)
    toast.success('Data anggota keluarga berhasil ditambahkan.')
    emit('success', res.data)
    emit('update:open', false)
    form.value = {
      relationship: 'father',
      full_name: '',
      phone: '',
      occupation: '',
      address: '',
      notes: '',
    }
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal menambahkan anggota keluarga.'
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
    title="Tambah Data Keluarga / Wali"
    size="md"
    @update:open="emit('update:open', $event)"
  >
    <form class="space-y-3.5" @submit.prevent="handleSubmit">
      <Alert v-if="errorMessage" variant="danger">
        {{ errorMessage }}
      </Alert>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <!-- Hubungan -->
        <FormField label="Hubungan Keluarga" required :error="getError('relationship')">
          <Select
            v-model="form.relationship"
            :options="relationshipOptions"
            required
            :disabled="loading"
          />
        </FormField>

        <!-- Nama Lengkap -->
        <FormField label="Nama Lengkap" required :error="getError('full_name')">
          <Input
            v-model="form.full_name"
            placeholder="Nama lengkap orang tua / wali"
            required
            :disabled="loading"
          />
        </FormField>

        <!-- No. Telepon -->
        <FormField label="No. Telepon / HP" :error="getError('phone')">
          <Input
            v-model="form.phone"
            placeholder="0812xxxxxxxx"
            :disabled="loading"
          />
        </FormField>

        <!-- Pekerjaan -->
        <FormField label="Pekerjaan" :error="getError('occupation')">
          <Input
            v-model="form.occupation"
            placeholder="Contoh: PNS, Wiraswasta, Guru..."
            :disabled="loading"
          />
        </FormField>

        <!-- Alamat -->
        <div class="sm:col-span-2">
          <FormField label="Alamat Domisili" :error="getError('address')">
            <Textarea
              v-model="form.address"
              placeholder="Alamat domisili orang tua / wali..."
              :rows="2"
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
        Simpan Anggota Keluarga
      </Button>
    </template>
  </Modal>
</template>
