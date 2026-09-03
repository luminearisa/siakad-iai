<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  X,
  Check,
} from 'lucide-vue-next'
import { thesisService } from '@/services/api/thesis'
import { studentService } from '@/services/api/students'
import { lecturerService } from '@/services/api/lecturers'
import { academicService } from '@/services/api/academic'
import { useToast } from '@/composables/useToast'
import type { Student } from '@/types/student'
import type { Lecturer } from '@/types/lecturer'
import type { Semester } from '@/types/academic'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import RichTextEditor from '@/components/ui/RichTextEditor.vue'
import SearchSelect from '@/components/ui/SearchSelect.vue'

const router = useRouter()
const toast = useToast()

const loading = ref<boolean>(false)
const saving = ref<boolean>(false)

const students = ref<Student[]>([])
const lecturers = ref<Lecturer[]>([])
const semesters = ref<Semester[]>([])

const studentOptions = computed(() => {
  return students.value.map((s) => ({
    value: s.id,
    label: `${s.student_number} - ${s.full_name}`,
    description: s.study_program?.name || 'Mahasiswa',
  }))
})

const lecturerOptions = computed(() => {
  return lecturers.value.map((l) => ({
    value: l.id,
    label: l.full_name,
    description: `NIDN: ${l.nidn || '-'}`,
  }))
})

const form = reactive({
  student_id: null as number | null,
  start_date: '2026-08-26',
  start_semester_id: null as number | null,
  submission_date: '2026-08-26',
  status: 'active' as 'active' | 'completed',
  title_id: '',
  title_en: '',
  topic_id: '',
  topic_en: '',
  proposal_file_path: '',
  supervisor_1_id: null as number | null,
  supervisor_2_id: null as number | null,

  // Completion fields
  completion_date: '',
  completion_semester_id: null as number | null,
  sk_date: '',
  sk_number: '',
  final_file_path: '',
})

async function loadDependencies() {
  loading.value = true
  try {
    const [studRes, lecRes, semRes] = await Promise.all([
      studentService.list({ per_page: 200 }),
      lecturerService.list({ per_page: 100 }),
      academicService.getSemesters(),
    ])

    students.value = studRes.data || []
    lecturers.value = lecRes.data || []
    semesters.value = semRes.data || []

    const activeSem = semesters.value.find((s) => s.is_active) || semesters.value.find((s) => s.name?.includes('2026/2027'))
    if (activeSem) {
      form.start_semester_id = activeSem.id
    }
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal memuat data referensi')
  } finally {
    loading.value = false
  }
}

function handleFileChange(event: Event, field: 'proposal_file_path' | 'final_file_path') {
  const target = event.target as HTMLInputElement
  if (target.files && target.files.length > 0) {
    form[field] = target.files[0].name
  }
}

async function handleSubmit() {
  if (!form.student_id) {
    toast.error('Pilih nama mahasiswa terlebih dahulu')
    return
  }
  if (!form.title_id) {
    toast.error('Judul tugas akhir (Indonesia) wajib diisi')
    return
  }
  if (!form.supervisor_1_id) {
    toast.error('Dosen pembimbing ke-1 wajib dipilih')
    return
  }

  saving.value = true
  try {
    const supervisorIds = [form.supervisor_1_id]
    if (form.supervisor_2_id) {
      supervisorIds.push(form.supervisor_2_id)
    }

    await thesisService.create({
      student_id: form.student_id,
      start_semester_id: form.start_semester_id,
      start_date: form.start_date,
      submission_date: form.submission_date,
      status: form.status,
      title_id: form.title_id,
      title_en: form.title_en || null,
      topic_id: form.topic_id || null,
      topic_en: form.topic_en || null,
      proposal_file_path: form.proposal_file_path || null,
      supervisor_ids: supervisorIds,

      completion_date: form.status === 'completed' ? form.completion_date : null,
      completion_semester_id: form.status === 'completed' ? form.completion_semester_id : null,
      sk_date: form.status === 'completed' ? form.sk_date : null,
      sk_number: form.status === 'completed' ? form.sk_number : null,
      final_file_path: form.status === 'completed' ? form.final_file_path : null,
    })

    toast.success('Data tugas akhir berhasil disimpan!')
    router.push('/thesis')
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal menyimpan data tugas akhir')
  } finally {
    saving.value = false
  }
}

function handleCancel() {
  router.push('/thesis')
}

onMounted(() => {
  loadDependencies()
})
</script>

<template>
  <PageContainer>
    <!-- Header (Matching Reference Screenshot 2) -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div>
        <nav class="flex items-center gap-1.5 text-xs text-slate-400 mb-1">
          <span class="text-brand-600 font-semibold tracking-wider uppercase">TAMBAH</span>
        </nav>
        <h1 class="text-xl font-bold text-slate-900">Tambah Tugas Akhir</h1>
      </div>

      <!-- Action Buttons: Batal & Simpan Data -->
      <div class="flex items-center gap-2">
        <Button
          variant="outline"
          size="sm"
          class="border-rose-300 text-rose-700 hover:bg-rose-50 flex items-center gap-1.5 font-semibold"
          @click="handleCancel"
        >
          <X class="w-3.5 h-3.5" />
          <span>Batal</span>
        </Button>
        <Button
          variant="primary"
          size="sm"
          :loading="saving"
          class="bg-brand-900 hover:bg-brand-950 text-white flex items-center gap-1.5 shadow-2xs font-semibold"
          @click="handleSubmit"
        >
          <Check class="w-3.5 h-3.5" />
          <span>Simpan Data</span>
        </Button>
      </div>
    </div>

    <form @submit.prevent="handleSubmit" class="space-y-6">
      <!-- Section 1: Informasi -->
      <Card class="border border-slate-200/80 shadow-2xs p-5 relative z-20 overflow-visible">
        <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-4 pb-2 border-b border-slate-100">
          Informasi
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-xs">
          <!-- Nama Mahasiswa (Searchable Select with Menu Icon) -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Nama Mahasiswa <span class="text-rose-500">*</span>
            </label>
            <SearchSelect
              v-model="form.student_id"
              :options="studentOptions"
              placeholder="Pilih mahasiswa"
              search-placeholder="Cari NIM atau nama mahasiswa..."
              required
              :show-menu-icon="true"
            />
          </div>

          <!-- Tanggal TA Dimulai -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Tanggal TA Dimulai <span class="text-rose-500">*</span>
            </label>
            <Input
              v-model="form.start_date"
              type="date"
              required
            />
          </div>

          <!-- Periode TA Dimulai -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Periode TA Dimulai <span class="text-rose-500">*</span>
            </label>
            <select
              v-model="form.start_semester_id"
              class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600 outline-none"
            >
              <option :value="null">Pilih Periode</option>
              <option v-for="sem in semesters" :key="sem.id" :value="sem.id">
                {{ sem.name }}
              </option>
            </select>
          </div>

          <!-- Tanggal Pengajuan -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Tanggal Pengajuan <span class="text-rose-500">*</span>
            </label>
            <Input
              v-model="form.submission_date"
              type="date"
              required
            />
          </div>
        </div>
      </Card>

      <!-- Section 2: Tugas Akhir -->
      <Card class="border border-slate-200/80 shadow-2xs p-5">
        <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-4 pb-2 border-b border-slate-100">
          Tugas Akhir
        </h3>

        <!-- Status Selector Cards (Matching Screenshot 2) -->
        <div class="mb-5">
          <label class="block text-xs font-semibold text-slate-700 mb-2">
            Status Tugas Akhir <span class="text-rose-500">*</span>
          </label>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Aktif -->
            <div
              class="p-4 rounded-xl border-2 transition-all cursor-pointer flex items-start gap-3 bg-white"
              :class="form.status === 'active' ? 'border-rose-600 shadow-xs' : 'border-slate-200 hover:border-slate-300'"
              @click="form.status = 'active'"
            >
              <div class="mt-0.5">
                <div
                  class="w-4 h-4 rounded-full border-2 flex items-center justify-center"
                  :class="form.status === 'active' ? 'border-rose-600' : 'border-slate-300'"
                >
                  <div
                    v-if="form.status === 'active'"
                    class="w-2 h-2 rounded-full bg-rose-600"
                  />
                </div>
              </div>
              <div>
                <div class="font-bold text-xs text-slate-900">Aktif</div>
                <div class="text-3xs text-slate-500 mt-0.5">
                  TA sedang di tahap bimbingan/ melengkapi berkas/ belum sidang.
                </div>
              </div>
            </div>

            <!-- Selesai -->
            <div
              class="p-4 rounded-xl border-2 transition-all cursor-pointer flex items-start gap-3 bg-white"
              :class="form.status === 'completed' ? 'border-rose-600 shadow-xs' : 'border-slate-200 hover:border-slate-300'"
              @click="form.status = 'completed'"
            >
              <div class="mt-0.5">
                <div
                  class="w-4 h-4 rounded-full border-2 flex items-center justify-center"
                  :class="form.status === 'completed' ? 'border-rose-600' : 'border-slate-300'"
                >
                  <div
                    v-if="form.status === 'completed'"
                    class="w-2 h-2 rounded-full bg-rose-600"
                  />
                </div>
              </div>
              <div>
                <div class="font-bold text-xs text-slate-900">Selesai</div>
                <div class="text-3xs text-slate-500 mt-0.5">
                  TA telah selesai dan dinyatakan lulus, mahasiswa melengkapi penyelesaian TA.
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Judul ID & EN (Side-by-side with Rich Text Editor) -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5 text-xs">
          <!-- Judul TA ID -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Judul Tugas Akhir (Indonesia) <span class="text-rose-500">*</span>
            </label>
            <RichTextEditor
              v-model="form.title_id"
              placeholder="Masukkan Judul Tugas Akhir dalam Bahasa Indonesia..."
              min-height="120px"
              required
            />
          </div>

          <!-- Judul TA EN -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Judul Tugas Akhir (English) <span class="text-rose-500">*</span>
            </label>
            <RichTextEditor
              v-model="form.title_en"
              placeholder="Masukkan Judul Tugas Akhir dalam Bahasa Inggris..."
              min-height="120px"
            />
          </div>
        </div>

        <!-- Topik ID & EN (Side-by-side) -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5 text-xs">
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Topik (Indonesia) <span class="text-rose-500">*</span>
            </label>
            <textarea
              v-model="form.topic_id"
              rows="3"
              class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600 outline-none resize-y"
              placeholder="Masukkan Topik Tugas Akhir"
            />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Topik (English) <span class="text-rose-500">*</span>
            </label>
            <textarea
              v-model="form.topic_en"
              rows="3"
              class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600 outline-none resize-y"
              placeholder="Masukkan Topik Tugas Akhir dalam Bahasa Inggris"
            />
          </div>
        </div>

        <!-- File Proposal & Pembimbing 1 (Side-by-side) -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 text-xs">
          <!-- File Proposal -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              File Proposal <span class="text-rose-500">*</span>
            </label>
            <div class="flex items-center gap-2">
              <input
                type="file"
                accept=".pdf"
                class="text-xs text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border file:border-slate-300 file:text-xs file:font-semibold file:bg-slate-50 file:text-slate-700 hover:file:bg-slate-100"
                @change="handleFileChange($event, 'proposal_file_path')"
              />
            </div>
            <p class="text-3xs text-slate-400 mt-1">Diizinkan PDF. Ukuran maksimal 10 MB</p>
          </div>

          <!-- Dosen Pembimbing ke-1 -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Dosen Pembimbing ke-1 <span class="text-rose-500">*</span>
            </label>
            <SearchSelect
              v-model="form.supervisor_1_id"
              :options="lecturerOptions"
              placeholder="Pilih Dosen Pembimbing"
              search-placeholder="Cari nama atau NIDN dosen..."
              required
              :show-menu-icon="false"
            />
            <p class="text-3xs text-slate-400 mt-1">Pilih mahasiswa terlebih dahulu untuk menentukan jumlah dosen pembimbing.</p>
          </div>
        </div>
      </Card>

      <!-- Section 3: Penyelesaian Tugas Akhir (Matching Screenshot 3) -->
      <Card
        class="border border-slate-200/80 shadow-2xs p-5 transition-opacity"
        :class="{ 'opacity-60 pointer-events-none bg-slate-50/50': form.status !== 'completed' }"
      >
        <div class="mb-4 pb-2 border-b border-slate-100 flex items-center justify-between">
          <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">
            Penyelesaian Tugas Akhir
          </h3>
          <span class="text-3xs text-slate-400">
            Dapat diisi saat status tugas akhir selesai.
          </span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-xs mb-4">
          <!-- Tanggal TA Selesai -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Tanggal TA Selesai <span class="text-rose-500">*</span>
            </label>
            <Input
              v-model="form.completion_date"
              type="date"
              placeholder="Pilih Tanggal Selesai"
              :disabled="form.status !== 'completed'"
            />
          </div>

          <!-- Periode TA Selesai -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Periode TA Selesai <span class="text-rose-500">*</span>
            </label>
            <select
              v-model="form.completion_semester_id"
              :disabled="form.status !== 'completed'"
              class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600 outline-none disabled:bg-slate-100"
            >
              <option :value="null">Pilih Periode Selesai</option>
              <option v-for="sem in semesters" :key="sem.id" :value="sem.id">
                {{ sem.name }}
              </option>
            </select>
          </div>

          <!-- Tanggal SK -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Tanggal SK <span class="text-rose-500">*</span>
            </label>
            <Input
              v-model="form.sk_date"
              type="date"
              placeholder="Pilih Tanggal SK"
              :disabled="form.status !== 'completed'"
            />
          </div>

          <!-- Nomor SK -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Nomor SK <span class="text-rose-500">*</span>
            </label>
            <Input
              v-model="form.sk_number"
              placeholder="Masukkan Nomor SK"
              :disabled="form.status !== 'completed'"
            />
          </div>
        </div>

        <!-- File TA Final -->
        <div class="text-xs">
          <label class="block font-semibold text-slate-700 mb-1.5">
            File TA Final <span class="text-rose-500">*</span>
          </label>
          <div class="flex items-center gap-2">
            <input
              type="file"
              accept=".pdf"
              :disabled="form.status !== 'completed'"
              class="text-xs text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border file:border-slate-300 file:text-xs file:font-semibold file:bg-slate-50 file:text-slate-700 hover:file:bg-slate-100 disabled:opacity-50"
              @change="handleFileChange($event, 'final_file_path')"
            />
          </div>
          <p class="text-3xs text-slate-400 mt-1">Diizinkan PDF. Ukuran maksimal 10 MB</p>
        </div>
      </Card>
    </form>
  </PageContainer>
</template>
