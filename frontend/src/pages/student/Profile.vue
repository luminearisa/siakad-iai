<script setup lang="ts">
import { ref, onMounted } from 'vue'
import {
  GraduationCap,
  Phone,
  Mail,
  Calendar,
  CreditCard,
  UserCheck,
  Edit3,
  Award,
  BookOpen,
  School,
  CheckCircle2,
} from 'lucide-vue-next'
import PageContainer from '@/components/data-display/PageContainer.vue'
import PageHeader from '@/components/data-display/PageHeader.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Badge from '@/components/ui/Badge.vue'
import Tabs from '@/components/ui/Tabs.vue'
import Alert from '@/components/ui/Alert.vue'
import RequestUpdateModal from './components/RequestUpdateModal.vue'
import { studentPortalApi } from '@/services/api/student-portal'
import type { StudentProfileData } from '@/types/student-portal'

const loading = ref(true)
const error = ref<string | null>(null)
const profileData = ref<StudentProfileData | null>(null)
const activeTab = ref('academic')
const showEditModal = ref(false)

const tabs = [
  { id: 'academic', label: 'Informasi Akademik' },
  { id: 'biodata', label: 'Biodata Pribadi' },
  { id: 'family', label: 'Data Orang Tua / Wali' },
  { id: 'education', label: 'Riwayat Pendidikan' },
]

async function loadProfile() {
  loading.value = true
  error.value = null
  try {
    const res = await studentPortalApi.getProfile()
    profileData.value = res.data || null
  } catch (err: any) {
    error.value = err.message || 'Gagal memuat profil mahasiswa.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadProfile()
})
</script>

<template>
  <PageContainer>
    <PageHeader
      title="Profil Mahasiswa"
      subtitle="Informasi identitas resmi, status akademik, dan data kemahasiswaan."
    >
      <template #actions>
        <Button
          v-if="profileData?.student"
          variant="primary"
          class="gap-2"
          @click="showEditModal = true"
        >
          <Edit3 class="w-4 h-4" />
          Ajukan Perubahan Data
        </Button>
      </template>
    </PageHeader>

    <!-- Alert Error -->
    <Alert v-if="error" type="danger" dismissible @dismiss="error = null">
      {{ error }}
    </Alert>

    <!-- Loading State -->
    <div v-if="loading" class="p-12 text-center text-slate-500">
      <div class="inline-block animate-spin w-8 h-8 border-4 border-brand-500 border-t-transparent rounded-full mb-3" />
      <p class="text-xs">Memuat data profil mahasiswa...</p>
    </div>

    <!-- Profile Content -->
    <div v-else-if="profileData?.student" class="space-y-6">
      <!-- Main Profile Hero Card -->
      <Card class="p-6 relative overflow-hidden bg-white border border-slate-200 shadow-sm">
        <div class="flex flex-col md:flex-row items-start md:items-center gap-6">
          <!-- Avatar / Photo -->
          <div class="relative shrink-0">
            <div class="w-24 h-24 rounded-2xl bg-gradient-to-br from-brand-600 to-indigo-700 p-0.5 shadow-md">
              <div class="w-full h-full rounded-[14px] bg-slate-900 flex items-center justify-center text-brand-300 text-3xl font-bold uppercase">
                {{ (profileData.student.full_name || 'M').substring(0, 2) }}
              </div>
            </div>
            <span class="absolute -bottom-1 -right-1 px-2 py-0.5 bg-emerald-50 border border-emerald-200 text-emerald-700 text-2xs font-semibold rounded-full flex items-center gap-1 shadow-xs">
              <CheckCircle2 class="w-2.5 h-2.5 text-emerald-500" /> {{ profileData.student.status === 'active' ? 'Aktif' : (profileData.student.status || 'Aktif') }}
            </span>
          </div>

          <!-- Bio Meta -->
          <div class="flex-1 min-w-0">
            <div class="flex flex-wrap items-center gap-2 mb-1.5">
              <h2 class="text-lg font-bold text-slate-900 truncate">
                {{ profileData.student.full_name }}
              </h2>
              <Badge variant="primary" class="font-mono font-bold">
                {{ profileData.student.student_number }}
              </Badge>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-2 gap-x-6 text-xs text-slate-600 mt-3">
              <div class="flex items-center gap-2">
                <GraduationCap class="w-4 h-4 text-brand-600 shrink-0" />
                <span class="font-medium text-slate-800">
                  {{ profileData.student.study_program?.name || 'Program Studi' }}
                  <template v-if="profileData.student.study_program?.faculty?.name">
                    ({{ profileData.student.study_program.faculty.name }})
                  </template>
                </span>
              </div>
              <div class="flex items-center gap-2">
                <Calendar class="w-4 h-4 text-brand-600 shrink-0" />
                <span>Angkatan {{ profileData.student.admission_year || '2025' }} &bull; Semester {{ profileData.student.academic_summary?.current_semester ?? 1 }}</span>
              </div>
              <div class="flex items-center gap-2">
                <Mail class="w-4 h-4 text-brand-600 shrink-0" />
                <span>{{ profileData.student.email }}</span>
              </div>
              <div class="flex items-center gap-2">
                <Phone class="w-4 h-4 text-brand-600 shrink-0" />
                <span>{{ profileData.student.phone || profileData.student.phone_number || '-' }}</span>
              </div>
            </div>
          </div>
        </div>
      </Card>

      <!-- Quick Metrics Grid -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <Card class="p-4 bg-white border border-slate-200">
          <div class="flex items-center justify-between text-slate-500 mb-1.5">
            <span class="text-2xs uppercase font-semibold tracking-wider">IPK Kumulatif</span>
            <Award class="w-4 h-4 text-amber-500" />
          </div>
          <div class="text-2xl font-bold text-slate-900 font-mono">
            {{ Number(profileData.student.academic_summary?.cumulative_gpa ?? 0).toFixed(2) }}
          </div>
          <span
            :class="[
              'text-2xs font-semibold',
              (profileData.student.academic_summary?.cumulative_gpa ?? 0) >= 3.0 ? 'text-emerald-600' : 'text-slate-500'
            ]"
          >
            {{
              (profileData.student.academic_summary?.cumulative_gpa ?? 0) >= 3.5
                ? 'Dengan Pujian (Cumlaude)'
                : (profileData.student.academic_summary?.cumulative_gpa ?? 0) >= 3.0
                ? 'Sangat Memuaskan'
                : (profileData.student.academic_summary?.cumulative_gpa ?? 0) >= 2.0
                ? 'Memuaskan'
                : (profileData.student.academic_summary?.cumulative_gpa ?? 0) > 0
                ? 'Cukup'
                : 'Belum Ada Nilai'
            }}
          </span>
        </Card>

        <Card class="p-4 bg-white border border-slate-200">
          <div class="flex items-center justify-between text-slate-500 mb-1.5">
            <span class="text-2xs uppercase font-semibold tracking-wider">SKS Lulus</span>
            <BookOpen class="w-4 h-4 text-brand-600" />
          </div>
          <div class="text-2xl font-bold text-slate-900 font-mono">
            {{ profileData.student.academic_summary?.total_credits_passed ?? 0 }} <span class="text-xs font-normal text-slate-500">SKS</span>
          </div>
          <span class="text-2xs text-slate-500">Kumulatif Selesai</span>
        </Card>

        <Card class="p-4 bg-white border border-slate-200">
          <div class="flex items-center justify-between text-slate-500 mb-1.5">
            <span class="text-2xs uppercase font-semibold tracking-wider">Semester Berjalan</span>
            <Calendar class="w-4 h-4 text-sky-500" />
          </div>
          <div class="text-2xl font-bold text-slate-900 font-mono">
            Semester {{ profileData.student.academic_summary?.current_semester ?? 1 }}
          </div>
          <span class="text-2xs text-brand-600 font-medium">Semester Aktif</span>
        </Card>

        <Card class="p-4 bg-white border border-slate-200">
          <div class="flex items-center justify-between text-slate-500 mb-1.5">
            <span class="text-2xs uppercase font-semibold tracking-wider">Maks. SKS KRS</span>
            <CreditCard class="w-4 h-4 text-purple-500" />
          </div>
          <div class="text-2xl font-bold text-slate-900 font-mono">
            {{ profileData.student.academic_summary?.max_credits_next ?? 20 }} <span class="text-xs font-normal text-slate-500">SKS</span>
          </div>
          <span class="text-2xs text-slate-500">Beban Semester Berikutnya</span>
        </Card>
      </div>

      <!-- Dosen Pembimbing Akademik (PA) Card -->
      <Card class="p-5 bg-white border border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3.5">
          <div class="w-11 h-11 rounded-lg bg-brand-50 border border-brand-100 text-brand-600 flex items-center justify-center shrink-0">
            <UserCheck class="w-5 h-5" />
          </div>
          <div>
            <span class="text-2xs uppercase font-semibold text-slate-500 tracking-wider">Dosen Pembimbing Akademik (PA)</span>
            <h3 class="text-sm font-bold text-slate-900 mt-0.5">
              {{ profileData.student.advisor?.name || 'Belum Ditentukan' }}
            </h3>
            <p v-if="profileData.student.advisor?.nidn" class="text-xs text-slate-500 font-mono">
              NIDN: {{ profileData.student.advisor.nidn }}
            </p>
          </div>
        </div>

        <div v-if="profileData.student.advisor" class="flex items-center gap-2">
          <router-link to="/advising" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold transition-colors">
            <UserCheck class="w-3.5 h-3.5" />
            Konsultasi Bimbingan
          </router-link>
        </div>
      </Card>

      <!-- Detailed Information Tabs -->
      <Card class="overflow-hidden bg-white border border-slate-200 shadow-sm">
        <div class="px-5 pt-4 border-b border-slate-200 bg-slate-50/50">
          <Tabs :tabs="tabs" v-model="activeTab" />
        </div>

        <div class="p-6">
          <!-- Tab 1: Academic Info -->
          <div v-if="activeTab === 'academic'" class="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-8 text-xs">
            <div class="space-y-3">
              <div class="flex justify-between py-2 border-b border-slate-100">
                <span class="text-slate-500">Nomor Induk Mahasiswa (NIM)</span>
                <span class="font-mono font-bold text-slate-900">{{ profileData.student.student_number }}</span>
              </div>
              <div class="flex justify-between py-2 border-b border-slate-100">
                <span class="text-slate-500">Nomor Induk Siswa Nasional (NISN)</span>
                <span class="font-mono text-slate-800">{{ profileData.student.national_student_number || '-' }}</span>
              </div>
              <div class="flex justify-between py-2 border-b border-slate-100">
                <span class="text-slate-500">Program Studi</span>
                <span class="font-medium text-slate-900">{{ profileData.student.study_program?.name || '-' }}</span>
              </div>
              <div class="flex justify-between py-2 border-b border-slate-100">
                <span class="text-slate-500">Fakultas</span>
                <span class="font-medium text-slate-800">{{ profileData.student.study_program?.faculty?.name || '-' }}</span>
              </div>
              <div class="flex justify-between py-2 border-b border-slate-100">
                <span class="text-slate-500">Jenjang Pendidikan</span>
                <span class="font-medium text-slate-800">Sarjana (S1)</span>
              </div>
            </div>

            <div class="space-y-3">
              <div class="flex justify-between py-2 border-b border-slate-100">
                <span class="text-slate-500">Tahun Angkatan</span>
                <span class="font-medium text-slate-900">{{ profileData.student.admission_year || '2025' }}</span>
              </div>
              <div class="flex justify-between py-2 border-b border-slate-100">
                <span class="text-slate-500">Tanggal Masuk</span>
                <span class="font-medium text-slate-800">{{ profileData.student.entry_date || '01 September 2025' }}</span>
              </div>
              <div class="flex justify-between py-2 border-b border-slate-100">
                <span class="text-slate-500">Jalur Penerimaan</span>
                <span class="font-medium text-slate-800">Seleksi Mandiri Prestasi</span>
              </div>
              <div class="flex justify-between py-2 border-b border-slate-100">
                <span class="text-slate-500">Status Mahasiswa</span>
                <span class="font-semibold text-emerald-600 uppercase">{{ profileData.student.status === 'active' ? 'Aktif' : (profileData.student.status || 'Aktif') }}</span>
              </div>
              <div class="flex justify-between py-2 border-b border-slate-100">
                <span class="text-slate-500">Batas Masa Studi</span>
                <span class="font-medium text-slate-800">Semester Genap 2031/2032 (14 Semester)</span>
              </div>
            </div>
          </div>

          <!-- Tab 2: Biodata Pribadi -->
          <div v-else-if="activeTab === 'biodata'" class="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-8 text-xs">
            <div class="space-y-3">
              <div class="flex justify-between py-2 border-b border-slate-100">
                <span class="text-slate-500">Nomor Induk Kependudukan (NIK)</span>
                <span class="font-mono text-slate-800">{{ profileData.student.nik || '3201012345670001' }}</span>
              </div>
              <div class="flex justify-between py-2 border-b border-slate-100">
                <span class="text-slate-500">Tempat, Tanggal Lahir</span>
                <span class="font-medium text-slate-900">{{ profileData.student.birth_place || '-' }}, {{ profileData.student.birth_date || '-' }}</span>
              </div>
              <div class="flex justify-between py-2 border-b border-slate-100">
                <span class="text-slate-500">Jenis Kelamin</span>
                <span class="font-medium text-slate-800">{{ profileData.student.gender === 'male' ? 'Laki-laki' : profileData.student.gender === 'female' ? 'Perempuan' : '-' }}</span>
              </div>
              <div class="flex justify-between py-2 border-b border-slate-100">
                <span class="text-slate-500">Agama</span>
                <span class="font-medium text-slate-800">{{ profileData.student.religion || 'Islam' }}</span>
              </div>
              <div class="flex justify-between py-2 border-b border-slate-100">
                <span class="text-slate-500">Kewarganegaraan</span>
                <span class="font-medium text-slate-800">{{ profileData.student.citizenship || 'Warga Negara Indonesia (WNI)' }}</span>
              </div>
            </div>

            <div class="space-y-3">
              <div class="flex justify-between py-2 border-b border-slate-100">
                <span class="text-slate-500">Nomor Telepon / WA</span>
                <span class="font-mono font-medium text-slate-900">{{ profileData.student.phone || profileData.student.phone_number || '-' }}</span>
              </div>
              <div class="flex justify-between py-2 border-b border-slate-100">
                <span class="text-slate-500">Email Utama</span>
                <span class="font-medium text-slate-800">{{ profileData.student.email }}</span>
              </div>
              <div class="flex justify-between py-2 border-b border-slate-100">
                <span class="text-slate-500">Kode Pos</span>
                <span class="font-mono text-slate-800">{{ profileData.student.postal_code || '40115' }}</span>
              </div>
              <div class="py-2 border-b border-slate-100">
                <span class="text-slate-500 block mb-1">Alamat Domisili Lengkap</span>
                <span class="font-medium text-slate-800">{{ profileData.student.address || '-' }}</span>
              </div>
            </div>
          </div>

          <!-- Tab 3: Data Orang Tua / Wali -->
          <div v-else-if="activeTab === 'family'" class="space-y-4">
            <div v-if="profileData.families && profileData.families.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <Card
                v-for="fam in profileData.families"
                :key="fam.id"
                class="p-4 bg-slate-50/60 border border-slate-200 space-y-2 text-xs"
              >
                <div class="flex items-center justify-between pb-2 border-b border-slate-200">
                  <span class="font-bold text-slate-900 text-sm">{{ fam.full_name }}</span>
                  <Badge variant="primary" size="sm">
                    {{ fam.relationship === 'father' ? 'Ayah Kandung' : fam.relationship === 'mother' ? 'Ibu Kandung' : 'Wali' }}
                  </Badge>
                </div>
                <div class="flex justify-between text-slate-500">
                  <span>Pekerjaan:</span>
                  <span class="text-slate-800 font-medium">{{ fam.occupation || 'Wiraswasta' }}</span>
                </div>
                <div class="flex justify-between text-slate-500">
                  <span>No. Telepon:</span>
                  <span class="text-slate-800 font-mono">{{ fam.phone || '-' }}</span>
                </div>
                <div class="flex justify-between text-slate-500">
                  <span>Alamat:</span>
                  <span class="text-slate-800">{{ fam.address || profileData.student.address || '-' }}</span>
                </div>
              </Card>
            </div>
            <div v-else class="p-8 text-center text-slate-500 text-xs">
              Belum ada data keluarga / orang tua yang terdaftar.
            </div>
          </div>

          <!-- Tab 4: Riwayat Pendidikan -->
          <div v-else-if="activeTab === 'education'" class="space-y-4">
            <div v-if="profileData.educations && profileData.educations.length > 0" class="space-y-3">
              <Card
                v-for="edu in profileData.educations"
                :key="edu.id"
                class="p-4 bg-slate-50/60 border border-slate-200 flex items-start gap-4 text-xs"
              >
                <div class="w-10 h-10 rounded-lg bg-brand-50 border border-brand-100 text-brand-600 flex items-center justify-center shrink-0 mt-0.5">
                  <School class="w-5 h-5" />
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between mb-1">
                    <h4 class="font-bold text-slate-900 text-sm">{{ edu.institution_name }}</h4>
                    <span class="px-2 py-0.5 bg-slate-200 text-slate-700 text-2xs rounded font-mono font-medium">
                      Lulus {{ edu.graduation_year }}
                    </span>
                  </div>
                  <p class="text-slate-600">Jenjang: {{ edu.level }} &bull; Jurusan: {{ edu.major || 'Umum' }}</p>
                  <p v-if="edu.certificate_number" class="text-2xs text-slate-500 font-mono mt-1">
                    No. Ijazah: {{ edu.certificate_number }}
                  </p>
                </div>
              </Card>
            </div>
            <div v-else class="p-8 text-center text-slate-500 text-xs">
              Belum ada data riwayat pendidikan yang terdaftar.
            </div>
          </div>
        </div>
      </Card>
    </div>

    <!-- Empty / Fallback State -->
    <div v-else class="p-12 text-center text-slate-500">
      <p class="text-sm">Data profil mahasiswa tidak ditemukan atau belum terhubung dengan akun ini.</p>
    </div>

    <!-- Modal Request Update Profile -->
    <RequestUpdateModal
      v-model:show="showEditModal"
      :student="profileData?.student || null"
      @success="loadProfile"
    />
  </PageContainer>
</template>
