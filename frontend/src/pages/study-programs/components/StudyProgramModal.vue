<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { academicService } from '@/services/api/academic'
import { useToast } from '@/composables/useToast'
import type { Faculty, StudyProgram } from '@/types/academic'
import Modal from '@/components/ui/Modal.vue'
import FormField from '@/components/form/FormField.vue'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import Button from '@/components/ui/Button.vue'
import Alert from '@/components/ui/Alert.vue'

const props = defineProps<{
  open: boolean
  studyProgram?: StudyProgram | null
}>()

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void
  (e: 'saved'): void
}>()

const toast = useToast()
const faculties = ref<Faculty[]>([])
const loadingFaculties = ref<boolean>(false)
const submitting = ref<boolean>(false)
const errorMessage = ref<string | null>(null)
const serverErrors = ref<Record<string, string[]>>({})

const form = ref({
  faculty_id: '' as any,
  code: '',
  name: '',
  degree: 'S1',
  status: 'active',
})

async function loadFaculties() {
  loadingFaculties.value = true
  try {
    const res = await academicService.getFaculties({ per_page: 100 })
    faculties.value = res.data || []
  } catch {
    faculties.value = []
  } finally {
    loadingFaculties.value = false
  }
}

function initForm() {
  if (props.studyProgram) {
    form.value = {
      faculty_id: props.studyProgram.faculty_id,
      code: props.studyProgram.code,
      name: props.studyProgram.name,
      degree: props.studyProgram.degree || 'S1',
      status: props.studyProgram.status || 'active',
    }
  } else {
    form.value = {
      faculty_id: faculties.value.length > 0 ? faculties.value[0].id : '',
      code: '',
      name: '',
      degree: 'S1',
      status: 'active',
    }
  }
  errorMessage.value = null
  serverErrors.value = {}
}

async function handleSubmit() {
  if (!form.value.faculty_id || !form.value.code.trim() || !form.value.name.trim()) return
  submitting.value = true
  errorMessage.value = null
  serverErrors.value = {}

  try {
    const payload = {
      faculty_id: Number(form.value.faculty_id),
      code: form.value.code.trim().toUpperCase(),
      name: form.value.name.trim(),
      degree: form.value.degree,
      status: form.value.status,
    }

    if (props.studyProgram) {
      await academicService.updateStudyProgram(props.studyProgram.id, payload)
      toast.success('Program Studi berhasil diperbarui.')
    } else {
      await academicService.createStudyProgram(payload)
      toast.success('Program Studi baru berhasil ditambahkan.')
    }

    emit('update:open', false)
    emit('saved')
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal menyimpan Program Studi.'
    if (err.errors) {
      serverErrors.value = err.errors
    }
  } finally {
    submitting.value = false
  }
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      if (faculties.value.length === 0) {
        loadFaculties().then(() => initForm())
      } else {
        initForm()
      }
    }
  }
)

onMounted(() => {
  loadFaculties()
})
</script>

<template>
  <Modal
    :open="open"
    :title="studyProgram ? 'Edit Program Studi' : 'Tambah Program Studi Baru'"
    size="md"
    @update:open="emit('update:open', $event)"
  >
    <form class="space-y-4 text-xs" @submit.prevent="handleSubmit">
      <Alert v-if="errorMessage" variant="danger">
        {{ errorMessage }}
      </Alert>

      <!-- Faculty -->
      <FormField
        label="Fakultas Naungan"
        required
        :error="serverErrors.faculty_id?.[0]"
      >
        <Select
          v-model="form.faculty_id"
          required
          :disabled="submitting || loadingFaculties"
        >
          <option value="">-- Pilih Fakultas --</option>
          <option v-for="f in faculties" :key="f.id" :value="f.id">
            {{ f.name }} ({{ f.code }})
          </option>
        </Select>
      </FormField>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <!-- Code -->
        <FormField
          label="Kode Program Studi"
          required
          :error="serverErrors.code?.[0]"
        >
          <Input
            v-model="form.code"
            placeholder="Contoh: PAI, PBA, HKI, ES, TI"
            required
            :disabled="submitting"
            class="uppercase font-mono"
          />
        </FormField>

        <!-- Degree -->
        <FormField
          label="Jenjang Pendidikan"
          required
          :error="serverErrors.degree?.[0]"
        >
          <Select
            v-model="form.degree"
            required
            :disabled="submitting"
          >
            <option value="D3">Diploma 3 (D3)</option>
            <option value="D4">Diploma 4 (D4)</option>
            <option value="S1">Sarjana (S1)</option>
            <option value="S2">Magister (S2)</option>
            <option value="S3">Doktor (S3)</option>
          </Select>
        </FormField>
      </div>

      <!-- Name -->
      <FormField
        label="Nama Program Studi"
        required
        :error="serverErrors.name?.[0]"
      >
        <Input
          v-model="form.name"
          placeholder="Contoh: Pendidikan Agama Islam, Teknik Informatika..."
          required
          :disabled="submitting"
        />
      </FormField>

      <!-- Status -->
      <FormField
        label="Status Operasional"
        required
        :error="serverErrors.status?.[0]"
      >
        <Select
          v-model="form.status"
          required
          :disabled="submitting"
        >
          <option value="active">Aktif (Bisa Menerima Mahasiswa & Buka Kelas)</option>
          <option value="inactive">Non-Aktif</option>
        </Select>
      </FormField>

      <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
        <Button variant="outline" size="sm" :disabled="submitting" @click="emit('update:open', false)">
          Batal
        </Button>
        <Button
          type="submit"
          variant="primary"
          size="sm"
          :loading="submitting"
          :disabled="!form.faculty_id || !form.code.trim() || !form.name.trim()"
        >
          <span>{{ studyProgram ? 'Simpan Perubahan' : 'Tambah Program Studi' }}</span>
        </Button>
      </div>
    </form>
  </Modal>
</template>
