<script setup lang="ts">
import { ref } from 'vue'
import { curriculumService } from '@/services/api/curriculum'
import { useToast } from '@/composables/useToast'
import type { Curriculum, CurriculumSemester, CreateCurriculumSemesterPayload } from '@/types/curriculum'
import Modal from '@/components/ui/Modal.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import FormField from '@/components/form/FormField.vue'
import Alert from '@/components/ui/Alert.vue'

interface Props {
  open: boolean
  curriculum: Curriculum
  nextSemesterNumber?: number
}

const props = withDefaults(defineProps<Props>(), {
  nextSemesterNumber: 1,
})

const emit = defineEmits<{
  (e: 'update:open', val: boolean): void
  (e: 'success', newSemester: CurriculumSemester): void
}>()

const toast = useToast()
const loading = ref<boolean>(false)
const errorMessage = ref<string | null>(null)

const form = ref<CreateCurriculumSemesterPayload>({
  semester_number: props.nextSemesterNumber,
  name: `Semester ${props.nextSemesterNumber}`,
  recommended_credits: 20,
})

async function handleSubmit() {
  errorMessage.value = null
  loading.value = true
  try {
    const res = await curriculumService.createSemester(props.curriculum.id, form.value)
    toast.success(`Paket Semester ${res.data.semester_number} berhasil ditambahkan ke kurikulum.`)
    emit('success', res.data)
    emit('update:open', false)
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal menambahkan semester kurikulum.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <Modal
    :open="open"
    title="Tambah Semester Kurikulum"
    size="md"
    @update:open="emit('update:open', $event)"
  >
    <form class="space-y-4" @submit.prevent="handleSubmit">
      <Alert v-if="errorMessage" variant="danger">
        {{ errorMessage }}
      </Alert>

      <!-- Semester Number -->
      <FormField label="Nomor Semester (Tingkat)" required>
        <Input
          v-model.number="form.semester_number"
          type="number"
          min="1"
          max="20"
          required
          :disabled="loading"
        />
      </FormField>

      <!-- Semester Name -->
      <FormField label="Label / Nama Semester">
        <Input
          v-model="form.name"
          placeholder="Contoh: Semester 1 (Gasal Tahun I)"
          :disabled="loading"
        />
      </FormField>

      <!-- Target Credits -->
      <FormField label="Target Beban SKS Semester">
        <Input
          v-model.number="form.recommended_credits"
          type="number"
          min="0"
          max="30"
          placeholder="Contoh: 20"
          :disabled="loading"
        />
      </FormField>
    </form>

    <template #footer>
      <Button variant="outline" size="sm" :disabled="loading" @click="emit('update:open', false)">
        Batal
      </Button>
      <Button variant="primary" size="sm" :loading="loading" @click="handleSubmit">
        Tambahkan Semester
      </Button>
    </template>
  </Modal>
</template>
