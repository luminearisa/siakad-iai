<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { academicService } from '@/services/api/academic'
import { curriculumService } from '@/services/api/curriculum'
import type { StudyProgram } from '@/types/academic'
import type {
  Curriculum,
  CurriculumCreatePayload,
  CurriculumUpdatePayload,
  CurriculumYear,
  CreditLimit,
  GradeScale,
} from '@/types/curriculum'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import Textarea from '@/components/ui/Textarea.vue'
import Card from '@/components/ui/Card.vue'
import FormField from '@/components/form/FormField.vue'
import FormActions from '@/components/form/FormActions.vue'
import Alert from '@/components/ui/Alert.vue'

interface Props {
  initialData?: Partial<Curriculum | CurriculumCreatePayload | CurriculumUpdatePayload>
  isEdit?: boolean
  loading?: boolean
  serverErrors?: Record<string, string[]>
  errorMessage?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  initialData: () => ({}),
  isEdit: false,
  loading: false,
  serverErrors: () => ({}),
  errorMessage: null,
})

const emit = defineEmits<{
  (e: 'submit', payload: CurriculumCreatePayload | CurriculumUpdatePayload): void
  (e: 'cancel'): void
}>()

const router = useRouter()
const studyPrograms = ref<StudyProgram[]>([])
const curriculumYears = ref<CurriculumYear[]>([])
const creditLimits = ref<CreditLimit[]>([])
const gradeScales = ref<GradeScale[]>([])

const form = ref<CurriculumCreatePayload>({
  study_program_id: props.initialData.study_program_id || ('' as any),
  curriculum_year_id: props.initialData.curriculum_year_id || null,
  credit_limit_id: props.initialData.credit_limit_id || null,
  grade_scale_id: props.initialData.grade_scale_id || null,
  code: props.initialData.code || '',
  name: props.initialData.name || '',
  version: props.initialData.version || new Date().getFullYear().toString(),
  description: props.initialData.description || '',
  start_year: props.initialData.start_year || new Date().getFullYear(),
  end_year: props.initialData.end_year || new Date().getFullYear() + 4,
  status: props.initialData.status || 'draft',
  effective_date: props.initialData.effective_date || new Date().toISOString().substring(0, 10),
  expiry_date: props.initialData.expiry_date || '',
})

const statusOptions = [
  { label: 'Draft (Penyusunan)', value: 'draft' },
  { label: 'Aktif Berlaku', value: 'active' },
  { label: 'Non-Aktif', value: 'inactive' },
  { label: 'Diarsipkan', value: 'archived' },
]

async function loadDropdowns() {
  try {
    const [spRes, yRes, clRes, gsRes] = await Promise.all([
      academicService.getStudyPrograms(),
      curriculumService.getYears(),
      curriculumService.getCreditLimits(),
      curriculumService.getGradeScales(),
    ])

    studyPrograms.value = spRes.data || []
    if (!form.value.study_program_id && studyPrograms.value.length > 0) {
      form.value.study_program_id = studyPrograms.value[0].id
    }

    curriculumYears.value = yRes.data || []
    if (!form.value.curriculum_year_id && curriculumYears.value.length > 0) {
      form.value.curriculum_year_id = curriculumYears.value[0].id
    }

    creditLimits.value = clRes.data || []
    if (!form.value.credit_limit_id && creditLimits.value.length > 0) {
      form.value.credit_limit_id = creditLimits.value[0].id
    }

    gradeScales.value = gsRes.data || []
    if (!form.value.grade_scale_id && gradeScales.value.length > 0) {
      form.value.grade_scale_id = gradeScales.value[0].id
    }
  } catch {
    // Graceful fallback
  }
}

function getError(field: string): string | null {
  if (props.serverErrors && props.serverErrors[field] && props.serverErrors[field].length > 0) {
    return props.serverErrors[field][0]
  }
  return null
}

function handleSubmit() {
  emit('submit', { ...form.value })
}

function handleCancel() {
  emit('cancel')
  router.back()
}

onMounted(() => {
  loadDropdowns()
})
</script>

<template>
  <form class="space-y-5" @submit.prevent="handleSubmit">
    <!-- Server Error Alert -->
    <Alert v-if="errorMessage" variant="danger">
      {{ errorMessage }}
    </Alert>

    <!-- 1. Identitas Dokumen Kurikulum -->
    <Card>
      <template #header>
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
          1. Identitas Dokumen Kurikulum
        </h3>
      </template>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
        <!-- Program Studi -->
        <FormField label="Program Studi (Homebase)" required :error="getError('study_program_id')">
          <Select
            v-model="form.study_program_id"
            required
            :disabled="loading"
          >
            <option v-for="sp in studyPrograms" :key="sp.id" :value="sp.id">
              {{ sp.code }} — {{ sp.name }} ({{ sp.degree || 'S1' }})
            </option>
          </Select>
        </FormField>

        <!-- Tahun Kurikulum Referensi -->
        <FormField label="Tahun Kurikulum" :error="getError('curriculum_year_id')">
          <Select
            v-model="form.curriculum_year_id"
            :disabled="loading"
          >
            <option :value="null">-- Pilih Tahun Kurikulum --</option>
            <option v-for="y in curriculumYears" :key="y.id" :value="y.id">
              {{ y.name }} ({{ y.year }})
            </option>
          </Select>
        </FormField>

        <!-- Kode Kurikulum -->
        <FormField label="Kode Kurikulum" required :error="getError('code')">
          <Input
            v-model="form.code"
            placeholder="Contoh: KUR-2026-DKV"
            required
            maxlength="50"
            :disabled="loading"
          />
        </FormField>

        <!-- Nama Kurikulum -->
        <FormField label="Nama Kurikulum" required :error="getError('name')">
          <Input
            v-model="form.name"
            placeholder="Contoh: Kurikulum 2026 S1 - Desain Komunikasi Visual"
            required
            maxlength="255"
            :disabled="loading"
          />
        </FormField>

        <!-- Versi Kurikulum -->
        <FormField label="Versi / Edisi" :error="getError('version')">
          <Input
            v-model="form.version"
            placeholder="Contoh: 2026 / 1.0"
            maxlength="20"
            :disabled="loading"
          />
        </FormField>

        <!-- Status Kurikulum -->
        <FormField label="Status Kurikulum" :error="getError('status')">
          <Select
            v-model="form.status"
            :options="statusOptions"
            :disabled="loading"
          />
        </FormField>
      </div>
    </Card>

    <!-- 2. Master Batas SKS & Skala Penilaian -->
    <Card>
      <template #header>
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
          2. Master Batas SKS & Skala Penilaian
        </h3>
      </template>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <!-- Batas SKS -->
        <FormField label="Batas SKS (Ambang Beban Mahasiswa)" :error="getError('credit_limit_id')">
          <Select
            v-model="form.credit_limit_id"
            :disabled="loading"
          >
            <option :value="null">-- Pilih Standar Batas SKS --</option>
            <option v-for="cl in creditLimits" :key="cl.id" :value="cl.id">
              {{ cl.name }}
            </option>
          </Select>
        </FormField>

        <!-- Skala Penilaian -->
        <FormField label="Skala Nilai Kelulusan" :error="getError('grade_scale_id')">
          <Select
            v-model="form.grade_scale_id"
            :disabled="loading"
          >
            <option :value="null">-- Pilih Standar Skala Nilai --</option>
            <option v-for="gs in gradeScales" :key="gs.id" :value="gs.id">
              {{ gs.name }}
            </option>
          </Select>
        </FormField>
      </div>
    </Card>

    <!-- 3. Periode Berlaku -->
    <Card>
      <template #header>
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
          3. Periode Keberlakuan Kurikulum
        </h3>
      </template>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
        <!-- Tahun Mulai -->
        <FormField label="Tahun Mulai" :error="getError('start_year')">
          <Input
            v-model.number="form.start_year"
            type="number"
            min="1950"
            max="2100"
            placeholder="2026"
            :disabled="loading"
          />
        </FormField>

        <!-- Tahun Selesai -->
        <FormField label="Tahun Berakhir" :error="getError('end_year')">
          <Input
            v-model.number="form.end_year"
            type="number"
            min="1950"
            max="2100"
            placeholder="2030"
            :disabled="loading"
          />
        </FormField>

        <!-- Tanggal Efektif -->
        <FormField label="Tanggal Efektif" :error="getError('effective_date')">
          <Input
            v-model="form.effective_date"
            type="date"
            :disabled="loading"
          />
        </FormField>

        <!-- Tanggal Kedaluwarsa -->
        <FormField label="Tanggal Kedaluwarsa" :error="getError('expiry_date')">
          <Input
            v-model="form.expiry_date"
            type="date"
            :disabled="loading"
          />
        </FormField>
      </div>
    </Card>

    <!-- 4. Deskripsi & Landasan -->
    <Card>
      <template #header>
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
          4. Deskripsi & Landasan Pengembangan
        </h3>
      </template>

      <FormField label="Deskripsi / Landasan Hukum Kurikulum" :error="getError('description')">
        <Textarea
          v-model="form.description"
          placeholder="Penjelasan landasan hukum SN-Dikti, capaian profil lulusan, atau tujuan kurikulum..."
          :rows="3"
          :disabled="loading"
        />
      </FormField>
    </Card>

    <!-- Form Actions -->
    <FormActions align="right">
      <Button variant="outline" size="md" :disabled="loading" @click="handleCancel">
        Batal
      </Button>
      <Button type="submit" variant="primary" size="md" :loading="loading" class="bg-brand-700 hover:bg-brand-800 text-white font-semibold">
        {{ isEdit ? 'Simpan Perubahan' : 'Simpan Dokumen Kurikulum' }}
      </Button>
    </FormActions>
  </form>
</template>
