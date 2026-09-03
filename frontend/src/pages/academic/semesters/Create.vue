<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { CalendarDays, X, Check } from 'lucide-vue-next'
import { academicService } from '@/services/api/academic'
import { useToast } from '@/composables/useToast'
import type { AcademicYear } from '@/types/academic'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Alert from '@/components/ui/Alert.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const isEditMode = computed(() => !!route.params.id)
const semesterId = computed(() => route.params.id as string)

const loading = ref<boolean>(false)
const saving = ref<boolean>(false)
const errorMessage = ref<string | null>(null)
const academicYears = ref<AcademicYear[]>([])

const form = reactive({
  academic_year_id: '' as number | '',
  name: '',
  type: 'ganjil' as 'ganjil' | 'genap' | 'pendek',
  start_date: '',
  end_date: '',
  krs_start_date: '',
  krs_end_date: '',
  kprs_start_date: '',
  kprs_end_date: '',
  lecture_start_date: '',
  lecture_end_date: '',
  uts_start_date: '',
  uts_end_date: '',
  uas_start_date: '',
  uas_end_date: '',
  min_attendance_uts_percentage: 50,
  min_attendance_uas_percentage: 80,
  total_teaching_weeks: 16,
  status: 'active',
})

async function loadDependencies() {
  loading.value = true
  try {
    const yearsRes = await academicService.getAcademicYears({ per_page: 50 })
    academicYears.value = yearsRes.data || []

    if (isEditMode.value) {
      const semRes = await academicService.getSemester(Number(semesterId.value))
      const data = semRes.data
      if (data) {
        form.academic_year_id = data.academic_year_id
        form.name = data.name
        form.type = data.type
        form.start_date = data.start_date ? data.start_date.substring(0, 10) : ''
        form.end_date = data.end_date ? data.end_date.substring(0, 10) : ''
        form.krs_start_date = data.krs_start_date ? data.krs_start_date.substring(0, 10) : ''
        form.krs_end_date = data.krs_end_date ? data.krs_end_date.substring(0, 10) : ''
        form.kprs_start_date = data.kprs_start_date ? data.kprs_start_date.substring(0, 10) : ''
        form.kprs_end_date = data.kprs_end_date ? data.kprs_end_date.substring(0, 10) : ''
        form.lecture_start_date = data.lecture_start_date ? data.lecture_start_date.substring(0, 10) : ''
        form.lecture_end_date = data.lecture_end_date ? data.lecture_end_date.substring(0, 10) : ''
        form.uts_start_date = data.uts_start_date ? data.uts_start_date.substring(0, 10) : ''
        form.uts_end_date = data.uts_end_date ? data.uts_end_date.substring(0, 10) : ''
        form.uas_start_date = data.uas_start_date ? data.uas_start_date.substring(0, 10) : ''
        form.uas_end_date = data.uas_end_date ? data.uas_end_date.substring(0, 10) : ''
        form.min_attendance_uts_percentage = data.min_attendance_uts_percentage ?? 50
        form.min_attendance_uas_percentage = data.min_attendance_uas_percentage ?? 80
        form.total_teaching_weeks = data.total_teaching_weeks ?? 16
        form.status = data.status || 'active'
      }
    }
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal memuat data formulir.'
  } finally {
    loading.value = false
  }
}

// Auto generate period name when year or semester type changes
function syncPeriodName() {
  if (isEditMode.value) return
  const yearObj = academicYears.value.find((y) => y.id === Number(form.academic_year_id))
  const semName = form.type === 'ganjil' ? 'Ganjil' : form.type === 'genap' ? 'Genap' : 'Pendek'
  if (yearObj) {
    form.name = `Semester ${semName} ${yearObj.name}`
  }
}

async function handleSubmit() {
  errorMessage.value = null

  if (!form.academic_year_id || !form.type || !form.start_date || !form.end_date) {
    errorMessage.value = 'Mohon lengkapi seluruh isian wajib bertanda bintang (*).'
    return
  }

  // Ensure name exists
  if (!form.name) {
    syncPeriodName()
  }

  saving.value = true
  try {
    const payload = {
      ...form,
      academic_year_id: Number(form.academic_year_id),
      min_attendance_uts_percentage: Number(form.min_attendance_uts_percentage),
      min_attendance_uas_percentage: Number(form.min_attendance_uas_percentage),
      total_teaching_weeks: Number(form.total_teaching_weeks),
    }

    if (isEditMode.value) {
      await academicService.updateSemester(Number(semesterId.value), payload)
      toast.success('Periode akademik berhasil diperbarui.')
    } else {
      await academicService.createSemester(payload)
      toast.success('Periode akademik baru berhasil ditambahkan.')
    }

    router.push('/academic/semesters')
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal menyimpan periode akademik.'
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  loadDependencies()
})
</script>

<template>
  <PageContainer>
    <div class="space-y-4">
      <!-- Breadcrumb Header -->
      <div class="flex items-center justify-between border-b border-slate-200/80 pb-4">
        <div>
          <div class="flex items-center gap-2 text-xs text-slate-500 font-medium mb-1">
            <CalendarDays class="w-4 h-4 text-brand-600" />
            <router-link to="/academic/semesters" class="hover:text-brand-600 transition-colors">
              Akademik
            </router-link>
            <span>&rsaquo;</span>
            <router-link to="/academic/semesters" class="hover:text-brand-600 transition-colors">
              Periode Akademik
            </router-link>
            <span>&rsaquo;</span>
            <span class="text-slate-900 font-semibold">
              {{ isEditMode ? 'Edit' : 'Tambah' }}
            </span>
          </div>
          <h1 class="text-xl font-bold text-slate-900 tracking-tight">
            {{ isEditMode ? 'Edit Periode Akademik' : 'Tambah Periode Akademik' }}
          </h1>
        </div>

        <div class="flex items-center gap-2">
          <Button
            type="button"
            variant="secondary"
            size="sm"
            :disabled="saving"
            @click="router.push('/academic/semesters')"
          >
            <X class="w-4 h-4 text-rose-500 mr-1" />
            Batal
          </Button>

          <Button
            type="button"
            variant="primary"
            size="sm"
            class="bg-brand-700 hover:bg-brand-800 text-white gap-1.5"
            :loading="saving"
            @click="handleSubmit"
          >
            <Check class="w-4 h-4" />
            Simpan Data
          </Button>
        </div>
      </div>

      <Alert v-if="errorMessage" variant="danger" :title="errorMessage" />

      <form class="space-y-5" @submit.prevent="handleSubmit">
        <!-- 1. Bagian Semester (Persis seperti Gambar 2) -->
        <Card class="p-5 bg-white border border-slate-200 rounded-xl shadow-2xs space-y-4">
          <div class="border-b border-slate-100 pb-2">
            <h2 class="text-sm font-bold text-slate-900">Semester</h2>
            <p class="text-2xs text-slate-500">Pilih tahun ajaran dan jenis semester perkuliahan</p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="block text-xs font-semibold text-slate-800">
                Tahun Ajaran <span class="text-rose-500">*</span>
              </label>
              <select
                v-model="form.academic_year_id"
                class="w-full text-xs py-2 px-3 border border-slate-300 rounded-lg text-slate-900 bg-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none"
                required
                @change="syncPeriodName"
              >
                <option value="" disabled>-- Pilih Tahun Ajaran --</option>
                <option v-for="y in academicYears" :key="y.id" :value="y.id">
                  {{ y.name }}
                </option>
              </select>
            </div>

            <div class="space-y-1.5">
              <label class="block text-xs font-semibold text-slate-800">
                Semester <span class="text-rose-500">*</span>
              </label>
              <select
                v-model="form.type"
                class="w-full text-xs py-2 px-3 border border-slate-300 rounded-lg text-slate-900 bg-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none"
                required
                @change="syncPeriodName"
              >
                <option value="ganjil">Ganjil (Gasal)</option>
                <option value="genap">Genap</option>
                <option value="pendek">Pendek (Antara)</option>
              </select>
            </div>
          </div>
        </Card>

        <!-- 2. Bagian Tanggal (Persis seperti Gambar 2: 2 Kolom) -->
        <Card class="p-5 bg-white border border-slate-200 rounded-xl shadow-2xs space-y-4">
          <div class="border-b border-slate-100 pb-2">
            <h2 class="text-sm font-bold text-slate-900">Tanggal</h2>
            <p class="text-2xs text-slate-500">Rentang waktu kalender akademik, perkuliahan, evaluasi ujian, dan kuota kehadiran</p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Kolom Kiri -->
            <div class="space-y-4">
              <div class="space-y-1.5">
                <label class="block text-xs font-semibold text-slate-800">
                  Tanggal Awal Periode <span class="text-rose-500">*</span>
                </label>
                <input
                  v-model="form.start_date"
                  type="date"
                  class="w-full text-xs py-2 px-3 border border-slate-300 rounded-lg text-slate-900 bg-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none"
                  required
                />
              </div>

              <div class="space-y-1.5">
                <label class="block text-xs font-semibold text-slate-800">
                  Tanggal Akhir Periode <span class="text-rose-500">*</span>
                </label>
                <input
                  v-model="form.end_date"
                  type="date"
                  class="w-full text-xs py-2 px-3 border border-slate-300 rounded-lg text-slate-900 bg-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none"
                  required
                />
              </div>

              <!-- Jadwal Pengisian KRS -->
              <div class="p-3 bg-emerald-50/60 border border-emerald-200 rounded-xl space-y-3">
                <div class="font-semibold text-2xs uppercase tracking-wider text-emerald-800 flex items-center gap-1.5">
                  <CalendarDays class="w-3.5 h-3.5 text-emerald-600" />
                  Jadwal Pengisian KRS (Online)
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div class="space-y-1">
                    <label class="block text-3xs font-semibold text-slate-700">Tanggal Buka KRS</label>
                    <input
                      v-model="form.krs_start_date"
                      type="date"
                      class="w-full text-xs py-1.5 px-2.5 border border-slate-300 rounded-lg bg-white focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none"
                    />
                  </div>
                  <div class="space-y-1">
                    <label class="block text-3xs font-semibold text-slate-700">Batas Waktu (Deadline) KRS</label>
                    <input
                      v-model="form.krs_end_date"
                      type="date"
                      class="w-full text-xs py-1.5 px-2.5 border border-slate-300 rounded-lg bg-white focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none"
                    />
                  </div>
                </div>
              </div>

              <!-- Jadwal Perubahan KRS (KPRS) -->
              <div class="p-3 bg-blue-50/60 border border-blue-200 rounded-xl space-y-3">
                <div class="font-semibold text-2xs uppercase tracking-wider text-blue-800 flex items-center gap-1.5">
                  <CalendarDays class="w-3.5 h-3.5 text-blue-600" />
                  Jadwal Perubahan KRS (KPRS / Batal-Tambah)
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div class="space-y-1">
                    <label class="block text-3xs font-semibold text-slate-700">Awal Perubahan KRS</label>
                    <input
                      v-model="form.kprs_start_date"
                      type="date"
                      class="w-full text-xs py-1.5 px-2.5 border border-slate-300 rounded-lg bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none"
                    />
                  </div>
                  <div class="space-y-1">
                    <label class="block text-3xs font-semibold text-slate-700">Batas Akhir Perubahan KRS</label>
                    <input
                      v-model="form.kprs_end_date"
                      type="date"
                      class="w-full text-xs py-1.5 px-2.5 border border-slate-300 rounded-lg bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none"
                    />
                  </div>
                </div>
              </div>

              <div class="space-y-1.5">
                <label class="block text-xs font-semibold text-slate-800">
                  Tanggal Awal Kuliah <span class="text-rose-500">*</span>
                </label>
                <input
                  v-model="form.lecture_start_date"
                  type="date"
                  class="w-full text-xs py-2 px-3 border border-slate-300 rounded-lg text-slate-900 bg-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none"
                />
              </div>

              <div class="space-y-1.5">
                <label class="block text-xs font-semibold text-slate-800">
                  Tanggal Akhir Kuliah <span class="text-rose-500">*</span>
                </label>
                <input
                  v-model="form.lecture_end_date"
                  type="date"
                  class="w-full text-xs py-2 px-3 border border-slate-300 rounded-lg text-slate-900 bg-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none"
                />
              </div>

              <div class="space-y-1.5">
                <label class="block text-xs font-semibold text-slate-800">
                  Kehadiran Minimal UTS (%) <span class="text-rose-500">*</span>
                </label>
                <input
                  v-model.number="form.min_attendance_uts_percentage"
                  type="number"
                  min="0"
                  max="100"
                  placeholder="50"
                  class="w-full text-xs py-2 px-3 border border-slate-300 rounded-lg text-slate-900 bg-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none font-mono"
                  required
                />
              </div>

              <div class="space-y-1.5">
                <label class="block text-xs font-semibold text-slate-800">
                  Kehadiran Minimal UAS (%) <span class="text-rose-500">*</span>
                </label>
                <input
                  v-model.number="form.min_attendance_uas_percentage"
                  type="number"
                  min="0"
                  max="100"
                  placeholder="80"
                  class="w-full text-xs py-2 px-3 border border-slate-300 rounded-lg text-slate-900 bg-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none font-mono"
                  required
                />
              </div>
            </div>

            <!-- Kolom Kanan -->
            <div class="space-y-4">
              <div class="space-y-1.5">
                <label class="block text-xs font-semibold text-slate-800">
                  Tanggal Awal UTS
                </label>
                <input
                  v-model="form.uts_start_date"
                  type="date"
                  class="w-full text-xs py-2 px-3 border border-slate-300 rounded-lg text-slate-900 bg-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none"
                />
              </div>

              <div class="space-y-1.5">
                <label class="block text-xs font-semibold text-slate-800">
                  Tanggal Akhir UTS
                </label>
                <input
                  v-model="form.uts_end_date"
                  type="date"
                  class="w-full text-xs py-2 px-3 border border-slate-300 rounded-lg text-slate-900 bg-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none"
                />
              </div>

              <div class="space-y-1.5">
                <label class="block text-xs font-semibold text-slate-800">
                  Tanggal Awal UAS
                </label>
                <input
                  v-model="form.uas_start_date"
                  type="date"
                  class="w-full text-xs py-2 px-3 border border-slate-300 rounded-lg text-slate-900 bg-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none"
                />
              </div>

              <div class="space-y-1.5">
                <label class="block text-xs font-semibold text-slate-800">
                  Tanggal Akhir UAS
                </label>
                <input
                  v-model="form.uas_end_date"
                  type="date"
                  class="w-full text-xs py-2 px-3 border border-slate-300 rounded-lg text-slate-900 bg-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none"
                />
              </div>

              <div class="space-y-1.5">
                <label class="block text-xs font-semibold text-slate-800">
                  Jumlah Minggu Perkuliahan <span class="text-rose-500">*</span>
                </label>
                <input
                  v-model.number="form.total_teaching_weeks"
                  type="number"
                  min="1"
                  max="30"
                  placeholder="16"
                  class="w-full text-xs py-2 px-3 border border-slate-300 rounded-lg text-slate-900 bg-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none font-mono"
                  required
                />
              </div>

              <div class="space-y-1.5">
                <label class="block text-xs font-semibold text-slate-800">
                  Status Keaktifan
                </label>
                <select
                  v-model="form.status"
                  class="w-full text-xs py-2 px-3 border border-slate-300 rounded-lg text-slate-900 bg-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none"
                >
                  <option value="active">Aktif</option>
                  <option value="inactive">Nonaktif</option>
                </select>
              </div>
            </div>
          </div>
        </Card>
      </form>
    </div>
  </PageContainer>
</template>
