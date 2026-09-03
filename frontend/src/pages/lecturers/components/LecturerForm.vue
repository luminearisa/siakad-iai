<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { academicService } from '@/services/api/academic'
import type { StudyProgram } from '@/types/academic'
import type { Lecturer, LecturerCreatePayload, LecturerUpdatePayload } from '@/types/lecturer'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import Textarea from '@/components/ui/Textarea.vue'
import Card from '@/components/ui/Card.vue'
import FormField from '@/components/form/FormField.vue'
import FormActions from '@/components/form/FormActions.vue'
import PhotoUpload from '@/components/form/PhotoUpload.vue'
import Alert from '@/components/ui/Alert.vue'

interface Props {
  initialData?: Partial<Lecturer | LecturerCreatePayload | LecturerUpdatePayload>
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
  (e: 'submit', payload: LecturerCreatePayload | LecturerUpdatePayload): void
  (e: 'cancel'): void
}>()

const router = useRouter()
const studyPrograms = ref<StudyProgram[]>([])

const form = ref<LecturerCreatePayload>({
  full_name: props.initialData.full_name || '',
  academic_degree: props.initialData.academic_degree || '',
  gender: (props.initialData.gender as any) || 'male',
  nidn: props.initialData.nidn || '',
  nidk: props.initialData.nidk || '',
  nip: props.initialData.nip || '',
  lecturer_number: props.initialData.lecturer_number || '',
  homebase_study_program_id: props.initialData.homebase_study_program_id || ('' as any),
  functional_position: props.initialData.functional_position || 'Tenaga Pengajar',
  birth_place: props.initialData.birth_place || '',
  birth_date: props.initialData.birth_date || '',
  phone: props.initialData.phone || '',
  email: props.initialData.email || '',
  address: props.initialData.address || '',
  status: (props.initialData.status as any) || 'active',
  join_date: props.initialData.join_date || new Date().toISOString().substring(0, 10),
  photo_path: props.initialData.photo_path || null,
  notes: props.initialData.notes || '',
})

const genderOptions = [
  { label: 'Laki-Laki (Pria)', value: 'male' },
  { label: 'Perempuan (Wanita)', value: 'female' },
]

const functionalPositionOptions = [
  { label: 'Tenaga Pengajar', value: 'Tenaga Pengajar' },
  { label: 'Asisten Ahli', value: 'Asisten Ahli' },
  { label: 'Lektor', value: 'Lektor' },
  { label: 'Lektor Kepala', value: 'Lektor Kepala' },
  { label: 'Guru Besar / Profesor', value: 'Guru Besar' },
]

const statusOptions = [
  { label: 'Aktif Mengajar', value: 'active' },
  { label: 'Non-Aktif', value: 'inactive' },
  { label: 'Purnatugas / Pensiun', value: 'retired' },
  { label: 'Mengundurkan Diri', value: 'resigned' },
  { label: 'Wafat', value: 'deceased' },
]

async function loadStudyPrograms() {
  try {
    const res = await academicService.getStudyPrograms()
    studyPrograms.value = res.data || []
    if (!form.value.homebase_study_program_id && studyPrograms.value.length > 0) {
      form.value.homebase_study_program_id = studyPrograms.value[0].id
    }
  } catch {
    studyPrograms.value = []
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
  loadStudyPrograms()
})
</script>

<template>
  <form class="space-y-5" @submit.prevent="handleSubmit">
    <!-- Global Error Alert -->
    <Alert v-if="errorMessage" variant="danger">
      {{ errorMessage }}
    </Alert>

    <!-- 1. Identitas Pribadi Dosen -->
    <Card>
      <template #header>
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
          1. Data Identitas Pribadi
        </h3>
      </template>

      <!-- Foto Dosen Upload -->
      <div class="mb-5 pb-5 border-b border-slate-100">
        <PhotoUpload
          v-model="form.photo_path"
          :name="form.full_name || 'Dosen'"
          label="Foto Profil / Pas Foto Dosen"
          helpText="Format JPG/PNG/WebP, maksimal 2MB. Disarankan foto resmi formal/berjas."
          :disabled="loading"
        />
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
        <!-- Nama Lengkap -->
        <FormField label="Nama Lengkap Dosen" required :error="getError('full_name')">
          <Input
            v-model="form.full_name"
            placeholder="Nama lengkap tanpa gelar"
            required
            :disabled="loading"
          />
        </FormField>

        <!-- Gelar Akademik -->
        <FormField label="Gelar Akademik" :error="getError('academic_degree')">
          <Input
            v-model="form.academic_degree"
            placeholder="Contoh: M.Pd.I / Dr., M.Ag"
            :disabled="loading"
          />
        </FormField>

        <!-- Jenis Kelamin -->
        <FormField label="Jenis Kelamin" required :error="getError('gender')">
          <Select
            v-model="form.gender"
            :options="genderOptions"
            required
            :disabled="loading"
          />
        </FormField>

        <!-- NIDN -->
        <FormField label="NIDN (Nomor Induk Dosen Nasional)" :error="getError('nidn')">
          <Input
            v-model="form.nidn"
            placeholder="10 digit NIDN"
            maxlength="50"
            :disabled="loading"
          />
        </FormField>

        <!-- NIDK -->
        <FormField label="NIDK (Nomor Induk Dosen Khusus)" :error="getError('nidk')">
          <Input
            v-model="form.nidk"
            placeholder="NIDK (jika dosen khusus)"
            maxlength="50"
            :disabled="loading"
          />
        </FormField>

        <!-- NIP -->
        <FormField label="NIP (Nomor Induk Pegawai)" :error="getError('nip')">
          <Input
            v-model="form.nip"
            placeholder="NIP Pegawai / Yayasan"
            maxlength="50"
            :disabled="loading"
          />
        </FormField>

        <!-- Kode Dosen Internal -->
        <FormField label="Kode Dosen Internal" :error="getError('lecturer_number')">
          <Input
            v-model="form.lecturer_number"
            placeholder="Contoh: DOS-001"
            maxlength="50"
            :disabled="loading"
          />
        </FormField>

        <!-- Tempat Lahir -->
        <FormField label="Tempat Lahir" :error="getError('birth_place')">
          <Input
            v-model="form.birth_place"
            placeholder="Kota / Kabupaten Lahir"
            :disabled="loading"
          />
        </FormField>

        <!-- Tanggal Lahir -->
        <FormField label="Tanggal Lahir" :error="getError('birth_date')">
          <Input
            v-model="form.birth_date"
            type="date"
            :disabled="loading"
          />
        </FormField>
      </div>
    </Card>

    <!-- 2. Data Akademik & Jabatan -->
    <Card>
      <template #header>
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
          2. Data Akademik & Jabatan Fungsional
        </h3>
      </template>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
        <!-- Homebase Program Studi -->
        <FormField label="Homebase Program Studi" :error="getError('homebase_study_program_id')">
          <Select
            v-model="form.homebase_study_program_id"
            :disabled="loading"
          >
            <option value="">-- Belum Ditentukan --</option>
            <option v-for="sp in studyPrograms" :key="sp.id" :value="sp.id">
              {{ sp.code }} — {{ sp.name }} ({{ sp.degree }})
            </option>
          </Select>
        </FormField>

        <!-- Jabatan Fungsional -->
        <FormField label="Jabatan Fungsional" :error="getError('functional_position')">
          <Select
            v-model="form.functional_position"
            :options="functionalPositionOptions"
            :disabled="loading"
          />
        </FormField>

        <!-- Status Dosen -->
        <FormField label="Status Dosen" :error="getError('status')">
          <Select
            v-model="form.status"
            :options="statusOptions"
            :disabled="loading"
          />
        </FormField>

        <!-- Tanggal Bergabung -->
        <FormField label="Tanggal Mulai Bertugas" :error="getError('join_date')">
          <Input
            v-model="form.join_date"
            type="date"
            :disabled="loading"
          />
        </FormField>

        <!-- Catatan Tambahan -->
        <div class="sm:col-span-2">
          <FormField label="Catatan Tambahan" :error="getError('notes')">
            <Textarea
              v-model="form.notes"
              placeholder="Catatan tambahan mengenai penugasan dosen..."
              :rows="2"
              :disabled="loading"
            />
          </FormField>
        </div>
      </div>
    </Card>

    <!-- 3. Kontak & Alamat -->
    <Card>
      <template #header>
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
          3. Kontak & Alamat Domisili
        </h3>
      </template>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
        <!-- Email -->
        <FormField label="Alamat Email" :error="getError('email')">
          <Input
            v-model="form.email"
            type="email"
            placeholder="nama.dosen@institusi.ac.id"
            :disabled="loading"
          />
        </FormField>

        <!-- Telepon -->
        <FormField label="No. Telepon / WhatsApp" :error="getError('phone')">
          <Input
            v-model="form.phone"
            placeholder="0812xxxxxxxx"
            :disabled="loading"
          />
        </FormField>

        <!-- Alamat Lengkap -->
        <div class="sm:col-span-2 lg:col-span-3">
          <FormField label="Alamat Domisili" :error="getError('address')">
            <Textarea
              v-model="form.address"
              placeholder="Alamat domisili lengkap..."
              :rows="2"
              :disabled="loading"
            />
          </FormField>
        </div>
      </div>
    </Card>

    <!-- Form Actions -->
    <FormActions align="right">
      <Button variant="outline" size="md" :disabled="loading" @click="handleCancel">
        Batal
      </Button>
      <Button type="submit" variant="primary" size="md" :loading="loading">
        {{ isEdit ? 'Simpan Perubahan' : 'Simpan Data Dosen' }}
      </Button>
    </FormActions>
  </form>
</template>
