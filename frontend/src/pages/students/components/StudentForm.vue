<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { academicService } from '@/services/api/academic'
import { INDONESIA_REGIONS } from '@/constants/regions'
import type { StudyProgram } from '@/types/academic'
import type { Student, StudentCreatePayload, StudentUpdatePayload } from '@/types/student'
import { Heart, Users, MapPin, GraduationCap, ShieldCheck } from 'lucide-vue-next'
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
  initialData?: Partial<Student | StudentCreatePayload | StudentUpdatePayload>
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
  (e: 'submit', payload: StudentCreatePayload | StudentUpdatePayload): void
  (e: 'cancel'): void
}>()

const router = useRouter()
const studyPrograms = ref<StudyProgram[]>([])

const form = ref<StudentCreatePayload>({
  student_number: props.initialData.student_number || '',
  full_name: props.initialData.full_name || '',
  nickname: props.initialData.nickname || '',
  gender: (props.initialData.gender as any) || 'male',
  study_program_id: props.initialData.study_program_id || ('' as any),
  national_student_number: props.initialData.national_student_number || '',
  national_id: props.initialData.national_id || '',
  mother_name: (props.initialData as any).mother_name || '',
  birth_place: props.initialData.birth_place || '',
  birth_date: props.initialData.birth_date || '',
  religion: props.initialData.religion || 'Islam',
  marital_status: props.initialData.marital_status || 'Single',
  phone: props.initialData.phone || '',
  email: props.initialData.email || '',
  address: props.initialData.address || '',
  province: (props.initialData as any).province || 'Jawa Barat',
  city: (props.initialData as any).city || 'Kab. Cirebon',
  district: (props.initialData as any).district || 'Kedawung',
  postal_code: props.initialData.postal_code || '',
  status: (props.initialData.status as any) || 'active',
  admission_year: props.initialData.admission_year || new Date().getFullYear(),
  entry_date: props.initialData.entry_date || new Date().toISOString().substring(0, 10),
  graduation_date: props.initialData.graduation_date || '',
  photo_path: props.initialData.photo_path || null,
  notes: props.initialData.notes || '',
})

// Data Orang Tua & Wali (Langsung Bisa Input di Sini)
const parentForm = reactive({
  // Data Ibu
  mother_phone: '',
  mother_occupation: '',
  // Data Ayah
  father_name: '',
  father_phone: '',
  father_occupation: '',
  // Data Wali
  guardian_name: '',
  guardian_relationship: 'Paman',
  guardian_phone: '',
  guardian_occupation: '',
  guardian_address: '',
})

// Cascading Regions Dropdowns (Provinsi -> Kab/Kota -> Kecamatan)
const provinceOptions = Object.keys(INDONESIA_REGIONS).map((p) => ({ label: p, value: p }))

const cityOptions = computed(() => {
  const prov = form.value.province
  if (!prov || !INDONESIA_REGIONS[prov]) return []
  return Object.keys(INDONESIA_REGIONS[prov]).map((c) => ({ label: c, value: c }))
})

const districtOptions = computed(() => {
  const prov = form.value.province
  const city = form.value.city
  if (!prov || !city || !INDONESIA_REGIONS[prov] || !INDONESIA_REGIONS[prov][city]) return []
  return INDONESIA_REGIONS[prov][city].map((d) => ({ label: d, value: d }))
})

function onProvinceChange() {
  const availableCities = cityOptions.value
  if (availableCities.length > 0) {
    form.value.city = availableCities[0].value
    const availableDistricts = districtOptions.value
    form.value.district = availableDistricts.length > 0 ? availableDistricts[0].value : ''
  } else {
    form.value.city = ''
    form.value.district = ''
  }
}

function onCityChange() {
  const availableDistricts = districtOptions.value
  if (availableDistricts.length > 0) {
    form.value.district = availableDistricts[0].value
  } else {
    form.value.district = ''
  }
}

const genderOptions = [
  { label: 'Laki-Laki (Pria)', value: 'male' },
  { label: 'Perempuan (Wanita)', value: 'female' },
]

const religionOptions = [
  { label: 'Islam', value: 'Islam' },
  { label: 'Kristen Protestan', value: 'Kristen' },
  { label: 'Katolik', value: 'Katolik' },
  { label: 'Hindu', value: 'Hindu' },
  { label: 'Buddha', value: 'Buddha' },
  { label: 'Konghucu', value: 'Konghucu' },
]

const maritalOptions = [
  { label: 'Belum Menikah (Single)', value: 'Single' },
  { label: 'Menikah', value: 'Menikah' },
  { label: 'Duda / Janda', value: 'Duda / Janda' },
]

const statusOptions = [
  { label: 'Aktif', value: 'active' },
  { label: 'Calon Mahasiswa', value: 'prospective' },
  { label: 'Cuti', value: 'leave' },
  { label: 'Non-Aktif', value: 'inactive' },
  { label: 'Lulus', value: 'graduated' },
  { label: 'Mengundurkan Diri', value: 'withdrawn' },
  { label: 'Dikeluarkan (DO)', value: 'dismissed' },
  { label: 'Wafat', value: 'deceased' },
]

async function loadStudyPrograms() {
  try {
    const res = await academicService.getStudyPrograms()
    studyPrograms.value = res.data || []
    if (!form.value.study_program_id && studyPrograms.value.length > 0) {
      form.value.study_program_id = studyPrograms.value[0].id
    }
  } catch (err) {
    console.error('Failed to load study programs:', err)
  }
}

function populateExistingFamilies() {
  if (props.initialData && (props.initialData as any).families) {
    const families = (props.initialData as any).families || []
    families.forEach((f: any) => {
      if (f.relationship === 'mother') {
        if (!form.value.mother_name) form.value.mother_name = f.full_name
        parentForm.mother_phone = f.phone || ''
        parentForm.mother_occupation = f.occupation || ''
      } else if (f.relationship === 'father') {
        parentForm.father_name = f.full_name || ''
        parentForm.father_phone = f.phone || ''
        parentForm.father_occupation = f.occupation || ''
      } else if (f.relationship === 'guardian') {
        parentForm.guardian_name = f.full_name || ''
        parentForm.guardian_phone = f.phone || ''
        parentForm.guardian_occupation = f.occupation || ''
        parentForm.guardian_address = f.address || ''
        parentForm.guardian_relationship = f.notes || 'Wali'
      }
    })
  }
}

function getError(field: string): string | null {
  if (props.serverErrors && props.serverErrors[field] && props.serverErrors[field].length > 0) {
    return props.serverErrors[field][0]
  }
  return null
}

function handleSubmit() {
  // Enforce mandatory fields client-side validation
  if (
    !form.value.full_name ||
    !form.value.birth_place ||
    !form.value.birth_date ||
    !form.value.gender ||
    !(form.value as any).mother_name ||
    !form.value.religion ||
    !form.value.national_student_number ||
    !form.value.email ||
    !form.value.phone ||
    !(form.value as any).province ||
    !(form.value as any).city ||
    !(form.value as any).district ||
    !form.value.student_number ||
    !form.value.study_program_id
  ) {
    alert('Mohon lengkapi seluruh isian wajib bertanda bintang (*): Nama, TTL, Jenis Kelamin, Nama Ibu Kandung, Agama, NISN, Email, No. HP, Provinsi, Kabupaten/Kota, dan Kecamatan.')
    return
  }

  // Compile families payload directly from inline inputs
  const compiledFamilies: any[] = []

  // 1. Ibu Kandung (Wajib)
  if ((form.value as any).mother_name) {
    compiledFamilies.push({
      relationship: 'mother',
      full_name: (form.value as any).mother_name,
      phone: parentForm.mother_phone || null,
      occupation: parentForm.mother_occupation || null,
    })
  }

  // 2. Ayah Kandung (Opsional)
  if (parentForm.father_name.trim()) {
    compiledFamilies.push({
      relationship: 'father',
      full_name: parentForm.father_name.trim(),
      phone: parentForm.father_phone || null,
      occupation: parentForm.father_occupation || null,
    })
  }

  // 3. Wali Mahasiswa (Opsional)
  if (parentForm.guardian_name.trim()) {
    compiledFamilies.push({
      relationship: 'guardian',
      full_name: parentForm.guardian_name.trim(),
      phone: parentForm.guardian_phone || null,
      occupation: parentForm.guardian_occupation || null,
      address: parentForm.guardian_address || null,
      notes: parentForm.guardian_relationship || null,
    })
  }

  const payload: any = {
    ...form.value,
    families: compiledFamilies,
  }

  emit('submit', payload)
}

function handleCancel() {
  emit('cancel')
  router.back()
}

onMounted(() => {
  loadStudyPrograms()
  populateExistingFamilies()
  // Ensure default region selection if empty
  if (!form.value.province) {
    form.value.province = 'Jawa Barat'
    onProvinceChange()
  }
})
</script>

<template>
  <form class="space-y-5" @submit.prevent="handleSubmit">
    <!-- Global Error Alert -->
    <Alert v-if="errorMessage" variant="danger">
      {{ errorMessage }}
    </Alert>

    <!-- 1. Identitas Diri Mahasiswa -->
    <Card>
      <template #header>
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <GraduationCap class="w-4 h-4 text-brand-600" />
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
              1. Data Identitas Pribadi Mahasiswa
            </h3>
          </div>
          <span class="text-3xs text-rose-600 font-semibold">* Isian Wajib Dilengkapi</span>
        </div>
      </template>

      <!-- Pas Foto Upload -->
      <div class="mb-5 pb-5 border-b border-slate-100">
        <PhotoUpload
          v-model="form.photo_path"
          :name="form.full_name || 'Mahasiswa'"
          label="Pas Foto Mahasiswa (3x4)"
          helpText="Format JPG/PNG/WebP, maksimal 2MB. Pas foto latar belakang merah/biru disarankan."
          :disabled="loading"
        />
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <!-- Nama Lengkap (WAJIB) -->
        <div class="sm:col-span-2">
          <FormField label="Nama Lengkap Sesuai Ijazah" required :error="getError('full_name')">
            <Input
              v-model="form.full_name"
              placeholder="Contoh: Muhammad Raihan Al-Farisi"
              required
              :disabled="loading"
            />
          </FormField>
        </div>

        <!-- Nama Panggilan / Alias -->
        <FormField label="Nama Panggilan" :error="getError('nickname')">
          <Input
            v-model="form.nickname"
            placeholder="Contoh: Raihan"
            :disabled="loading"
          />
        </FormField>

        <!-- Tempat Lahir (WAJIB) -->
        <FormField label="Tempat Lahir (Kota/Kab)" required :error="getError('birth_place')">
          <Input
            v-model="form.birth_place"
            placeholder="Contoh: Cirebon"
            required
            :disabled="loading"
          />
        </FormField>

        <!-- Tanggal Lahir (WAJIB) -->
        <FormField label="Tanggal Lahir" required :error="getError('birth_date')">
          <Input
            v-model="form.birth_date"
            type="date"
            required
            :disabled="loading"
          />
        </FormField>

        <!-- Jenis Kelamin (WAJIB) -->
        <FormField label="Jenis Kelamin" required :error="getError('gender')">
          <Select
            v-model="form.gender"
            :options="genderOptions"
            required
            :disabled="loading"
          />
        </FormField>

        <!-- Agama (WAJIB) -->
        <FormField label="Agama" required :error="getError('religion')">
          <Select
            v-model="form.religion"
            :options="religionOptions"
            required
            :disabled="loading"
          />
        </FormField>

        <!-- NISN (WAJIB) -->
        <FormField label="NISN (Nomor Induk Siswa Nasional)" required :error="getError('national_student_number')">
          <Input
            v-model="form.national_student_number"
            placeholder="10 digit NISN sekolah asal"
            maxlength="20"
            required
            :disabled="loading"
          />
        </FormField>

        <!-- NIK / No. KTP -->
        <FormField label="NIK (Nomor Induk Kependudukan)" :error="getError('national_id')">
          <Input
            v-model="form.national_id"
            placeholder="16 digit NIK KTP"
            maxlength="16"
            :disabled="loading"
          />
        </FormField>

        <!-- Status Pernikahan -->
        <FormField label="Status Pernikahan" :error="getError('marital_status')">
          <Select
            v-model="form.marital_status"
            :options="maritalOptions"
            :disabled="loading"
          />
        </FormField>
      </div>
    </Card>

    <!-- 2. DATA KHUSUS IBU KANDUNG (WAJIB) & ORANG TUA / WALI (OPSIONAL) -->
    <Card class="border border-emerald-200/90 shadow-2xs">
      <template #header>
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <Heart class="w-4 h-4 text-rose-500 fill-rose-500/20" />
            <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-950">
              2. Data Orang Tua & Wali Mahasiswa
            </h3>
          </div>
          <span class="text-3xs font-bold text-rose-700 bg-rose-50 border border-rose-200 px-2 py-0.5 rounded-md">
            Nama Ibu Kandung WAJIB
          </span>
        </div>
      </template>

      <div class="space-y-6">
        <!-- SEKSI KHUSUS IBU KANDUNG (WAJIB) -->
        <div class="p-4 rounded-xl bg-gradient-to-br from-rose-50/40 via-amber-50/30 to-emerald-50/40 border border-rose-200/70 space-y-3.5">
          <div class="flex items-center gap-2">
            <ShieldCheck class="w-4 h-4 text-rose-600" />
            <span class="text-xs font-bold text-slate-900">Data Ibu Kandung Mahasiswa (Verifikasi PDDIKTI & Ijazah)</span>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
            <!-- Nama Ibu Kandung (WAJIB) -->
            <div class="sm:col-span-1">
              <FormField label="Nama Lengkap Ibu Kandung" required :error="getError('mother_name')">
                <Input
                  v-model="(form as any).mother_name"
                  placeholder="Nama ibu kandung sesuai Akta / KK"
                  required
                  :disabled="loading"
                />
              </FormField>
            </div>

            <!-- No. HP Ibu (Opsional) -->
            <div>
              <FormField label="No. HP / WhatsApp Ibu (Opsional)" :error="getError('mother_phone')">
                <Input
                  v-model="parentForm.mother_phone"
                  placeholder="08xxxxxxxxxx"
                  :disabled="loading"
                />
              </FormField>
            </div>

            <!-- Pekerjaan Ibu (Opsional) -->
            <div>
              <FormField label="Pekerjaan Ibu (Opsional)" :error="getError('mother_occupation')">
                <Input
                  v-model="parentForm.mother_occupation"
                  placeholder="Contoh: Ibu Rumah Tangga / Guru / PNS"
                  :disabled="loading"
                />
              </FormField>
            </div>
          </div>
        </div>

        <!-- SEKSI AYAH KANDUNG (OPSIONAL) -->
        <div class="p-4 rounded-xl bg-slate-50/80 border border-slate-200/80 space-y-3.5">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <Users class="w-4 h-4 text-slate-600" />
              <span class="text-xs font-bold text-slate-800">Data Ayah Kandung (Opsional)</span>
            </div>
            <span class="text-3xs text-slate-400">Bisa dilengkapi nanti</span>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
            <div>
              <FormField label="Nama Lengkap Ayah" :error="getError('father_name')">
                <Input
                  v-model="parentForm.father_name"
                  placeholder="Nama lengkap ayah kandung"
                  :disabled="loading"
                />
              </FormField>
            </div>

            <div>
              <FormField label="No. HP / WhatsApp Ayah" :error="getError('father_phone')">
                <Input
                  v-model="parentForm.father_phone"
                  placeholder="08xxxxxxxxxx"
                  :disabled="loading"
                />
              </FormField>
            </div>

            <div>
              <FormField label="Pekerjaan Ayah" :error="getError('father_occupation')">
                <Input
                  v-model="parentForm.father_occupation"
                  placeholder="Contoh: Wiraswasta / Karyawan / PNS"
                  :disabled="loading"
                />
              </FormField>
            </div>
          </div>
        </div>

        <!-- SEKSI WALI MAHASISWA (OPSIONAL) -->
        <div class="p-4 rounded-xl bg-slate-50/80 border border-slate-200/80 space-y-3.5">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <Users class="w-4 h-4 text-slate-600" />
              <span class="text-xs font-bold text-slate-800">Data Wali Mahasiswa (Opsional Jika Tinggal Bersama Wali)</span>
            </div>
            <span class="text-3xs text-slate-400">Opsional</span>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
            <div>
              <FormField label="Nama Lengkap Wali" :error="getError('guardian_name')">
                <Input
                  v-model="parentForm.guardian_name"
                  placeholder="Nama wali mahasiswa"
                  :disabled="loading"
                />
              </FormField>
            </div>

            <div>
              <FormField label="Hubungan Keluarga" :error="getError('guardian_relationship')">
                <Input
                  v-model="parentForm.guardian_relationship"
                  placeholder="Contoh: Paman / Kakek / Kakak"
                  :disabled="loading"
                />
              </FormField>
            </div>

            <div>
              <FormField label="No. HP / WhatsApp Wali" :error="getError('guardian_phone')">
                <Input
                  v-model="parentForm.guardian_phone"
                  placeholder="08xxxxxxxxxx"
                  :disabled="loading"
                />
              </FormField>
            </div>

            <div>
              <FormField label="Pekerjaan Wali" :error="getError('guardian_occupation')">
                <Input
                  v-model="parentForm.guardian_occupation"
                  placeholder="Contoh: Pensiunan / Wiraswasta"
                  :disabled="loading"
                />
              </FormField>
            </div>
          </div>
        </div>
      </div>
    </Card>

    <!-- 3. Kontak & Wilayah Domisili (WAJIB & DROPDOWN OTOMATIS) -->
    <Card>
      <template #header>
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <MapPin class="w-4 h-4 text-brand-600" />
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
              3. Kontak & Wilayah Domisili (Dropdown Otomatis Bertingkat)
            </h3>
          </div>
          <span class="text-3xs text-rose-600 font-semibold">* Isian Wajib Dilengkapi</span>
        </div>
      </template>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <!-- Email (WAJIB) -->
        <FormField label="Alamat Email Mahasiswa" required :error="getError('email')">
          <Input
            v-model="form.email"
            type="email"
            placeholder="contoh: raihan@student.ac.id"
            required
            :disabled="loading"
          />
        </FormField>

        <!-- No. HP / WhatsApp (WAJIB) -->
        <FormField label="No. Handphone / WhatsApp Mahasiswa" required :error="getError('phone')">
          <Input
            v-model="form.phone"
            placeholder="Contoh: 081234567890"
            required
            :disabled="loading"
          />
        </FormField>

        <!-- Kode Pos -->
        <FormField label="Kode Pos" :error="getError('postal_code')">
          <Input
            v-model="form.postal_code"
            placeholder="Contoh: 45153"
            maxlength="10"
            :disabled="loading"
          />
        </FormField>

        <!-- Provinsi (Dropdown Otomatis WAJIB) -->
        <FormField label="Provinsi Domisili" required :error="getError('province')">
          <Select
            v-model="(form as any).province"
            :options="provinceOptions"
            required
            :disabled="loading"
            @change="onProvinceChange"
          />
        </FormField>

        <!-- Kabupaten / Kota (Dropdown Otomatis WAJIB) -->
        <FormField label="Kabupaten / Kota" required :error="getError('city')">
          <Select
            v-model="(form as any).city"
            :options="cityOptions"
            required
            :disabled="loading"
            @change="onCityChange"
          />
        </FormField>

        <!-- Kecamatan (Dropdown Otomatis WAJIB) -->
        <FormField label="Kecamatan" required :error="getError('district')">
          <Select
            v-model="(form as any).district"
            :options="districtOptions"
            required
            :disabled="loading"
          />
        </FormField>

        <!-- Alamat Lengkap / Jalan & RT/RW -->
        <div class="sm:col-span-2 lg:col-span-3">
          <FormField label="Alamat Detail (Jalan, RT/RW, Dusun/Kelurahan)" :error="getError('address')">
            <Textarea
              v-model="form.address"
              placeholder="Contoh: Jl. Ki Hajar Dewantara No. 12, RT 02 / RW 04, Kel. Tuparev"
              :rows="2"
              :disabled="loading"
            />
          </FormField>
        </div>
      </div>
    </Card>

    <!-- 4. Data Akademik & Penerimaan -->
    <Card>
      <template #header>
        <div class="flex items-center gap-2">
          <GraduationCap class="w-4 h-4 text-brand-600" />
          <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
            4. Data Akademik & Penerimaan Mahasiswa
          </h3>
        </div>
      </template>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <!-- NIM (WAJIB) -->
        <FormField label="Nomor Induk Mahasiswa (NIM)" required :error="getError('student_number')">
          <Input
            v-model="form.student_number"
            placeholder="Contoh: 202601001"
            required
            :disabled="loading"
          />
        </FormField>

        <!-- Program Studi (WAJIB) -->
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

        <!-- Tahun Angkatan (WAJIB) -->
        <FormField label="Tahun Angkatan" required :error="getError('admission_year')">
          <Input
            v-model="form.admission_year"
            type="number"
            placeholder="2026"
            required
            :disabled="loading"
          />
        </FormField>

        <!-- Tanggal Masuk -->
        <FormField label="Tanggal Terdaftar / Masuk" :error="getError('entry_date')">
          <Input
            v-model="form.entry_date"
            type="date"
            :disabled="loading"
          />
        </FormField>

        <!-- Status Mahasiswa -->
        <FormField label="Status Mahasiswa" :error="getError('status')">
          <Select
            v-model="form.status"
            :options="statusOptions"
            :disabled="loading"
          />
        </FormField>

        <!-- Tanggal Kelulusan -->
        <FormField label="Tanggal Kelulusan (Opsional)" :error="getError('graduation_date')">
          <Input
            v-model="form.graduation_date"
            type="date"
            :disabled="loading"
          />
        </FormField>

        <!-- Catatan Tambahan -->
        <div class="sm:col-span-2 lg:col-span-3">
          <FormField label="Catatan Khusus Mahasiswa" :error="getError('notes')">
            <Textarea
              v-model="form.notes"
              placeholder="Catatan tambahan, beasiswa, atau riwayat khusus..."
              :rows="2"
              :disabled="loading"
            />
          </FormField>
        </div>
      </div>
    </Card>

    <!-- Actions -->
    <FormActions align="right">
      <Button variant="outline" size="md" :disabled="loading" @click="handleCancel">
        Batal
      </Button>
      <Button type="submit" variant="primary" size="md" :loading="loading" class="bg-brand-700 hover:bg-brand-800 text-white font-semibold">
        {{ isEdit ? 'Simpan Perubahan Data' : 'Simpan Data Mahasiswa' }}
      </Button>
    </FormActions>
  </form>
</template>
