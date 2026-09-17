<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ArrowLeft, Plus, MoreVertical, Trash2, Check, X } from 'lucide-vue-next'
import { curriculumService } from '@/services/api/curriculum'
import { courseService } from '@/services/api/courses'
import { useToast } from '@/composables/useToast'
import type { Curriculum, CurriculumSemester, CurriculumSubject } from '@/types/curriculum'
import type { Course } from '@/types/course'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'

const router = useRouter()
const route = useRoute()
const toast = useToast()

const loading = ref<boolean>(false)
const curriculum = ref<Curriculum | null>(null)
const courses = ref<Course[]>([])
const addSubjectModalOpen = ref<boolean>(false)
const selectedSemesterId = ref<number | null>(null)
const savingSubject = ref<boolean>(false)

// Daftar mata kuliah bisa sangat panjang -> pakai search select.
const courseOptions = computed(() =>
  courses.value.map((c) => ({
    value: c.id as number,
    label: `${c.code} — ${c.name} (${c.credits} SKS)`,
  })),
)

const subjectForm = reactive({
  course_id: '' as any,
  is_mandatory: true,
  subject_type: 'wajib' as 'wajib' | 'pilihan',
  is_package: true,
  minimum_grade: 'D',
  prerequisites_text: '',
  credits_override: null as number | null,
})

async function fetchCurriculum() {
  loading.value = true
  try {
    const res = await curriculumService.getCurriculum(Number(route.params.id))
    curriculum.value = res.data
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal memuat kurikulum')
  } finally {
    loading.value = false
  }
}

async function loadCourses() {
  try {
    const res = await courseService.list({ per_page: 200 })
    courses.value = res.data || []
  } catch (err) {
    console.error('Failed to load courses', err)
  }
}

function openAddSubjectModal(semesterId: number) {
  selectedSemesterId.value = semesterId
  subjectForm.course_id = courses.value.length > 0 ? courses.value[0].id : ''
  subjectForm.is_mandatory = true
  subjectForm.subject_type = 'wajib'
  subjectForm.is_package = true
  subjectForm.minimum_grade = 'D'
  subjectForm.prerequisites_text = ''
  subjectForm.credits_override = null
  addSubjectModalOpen.value = true
}

async function handleSaveSubject() {
  if (!selectedSemesterId.value || !subjectForm.course_id) {
    toast.error('Pilih mata kuliah terlebih dahulu')
    return
  }

  savingSubject.value = true
  try {
    await curriculumService.addSubjectToSemester(selectedSemesterId.value, {
      course_id: Number(subjectForm.course_id),
      is_mandatory: subjectForm.subject_type === 'wajib',
      subject_type: subjectForm.subject_type,
      is_package: subjectForm.is_package,
      minimum_grade: subjectForm.minimum_grade,
      prerequisites_text: subjectForm.prerequisites_text || null,
      credits_override: subjectForm.credits_override || null,
    })
    toast.success('Mata kuliah berhasil ditambahkan ke semester')
    addSubjectModalOpen.value = false
    fetchCurriculum()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal menambahkan mata kuliah ke kurikulum')
  } finally {
    savingSubject.value = false
  }
}

async function handleRemoveSubject(semesterId: number, subject: CurriculumSubject) {
  if (!confirm(`Hapus mata kuliah "${subject.course?.name}" dari semester ini?`)) return
  try {
    await curriculumService.removeSubjectFromSemester(semesterId, subject.id)
    toast.success('Mata kuliah berhasil dihapus dari kurikulum')
    fetchCurriculum()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal menghapus mata kuliah')
  }
}

function calculateSemesterCredits(sem: CurriculumSemester): number {
  if (!sem.subjects) return 0
  return sem.subjects.reduce((sum, s) => sum + (s.effective_credits || s.credits_override || s.course?.credits || 0), 0)
}

function calculateWajibCount(sem: CurriculumSemester): number {
  if (!sem.subjects) return 0
  return sem.subjects.filter((s) => s.subject_type === 'wajib' || s.is_mandatory).length
}

function calculatePilihanCount(sem: CurriculumSemester): number {
  if (!sem.subjects) return 0
  return sem.subjects.filter((s) => s.subject_type === 'pilihan' && !s.is_mandatory).length
}

function calculatePaketCount(sem: CurriculumSemester): number {
  if (!sem.subjects) return 0
  return sem.subjects.filter((s) => s.is_package !== false).length
}

onMounted(() => {
  fetchCurriculum()
  loadCourses()
})
</script>

<template>
  <PageContainer>
    <!-- Top Header (Matching Image 5) -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div>
        <h1 class="text-xl font-bold text-slate-900">
          {{ curriculum?.name || 'Kurikulum Program Studi' }}
          <span v-if="curriculum?.study_program" class="text-slate-700 font-semibold">
            {{ curriculum.study_program.degree || 'S1' }} - {{ curriculum.study_program.name }}
          </span>
        </h1>
        <p class="text-xs text-slate-500 mt-0.5">Manajemen Kurikulum Program Studi</p>
      </div>

      <div class="flex items-center gap-2 self-start sm:self-auto">
        <Button
          variant="outline"
          size="sm"
          class="text-xs flex items-center gap-1.5 bg-white text-slate-700 shadow-2xs"
          @click="router.push('/curriculum/study-programs')"
        >
          <ArrowLeft class="w-3.5 h-3.5" />
          Kembali
        </Button>
        <Button
          variant="primary"
          size="sm"
          class="bg-brand-700 hover:bg-brand-800 text-white flex items-center gap-1.5 shadow-2xs font-semibold text-xs"
          @click="router.push('/curricula/create')"
        >
          <Plus class="w-4 h-4" />
          Tambah Data
        </Button>
      </div>
    </div>

    <div v-if="loading" class="py-16 text-center text-slate-400 text-xs">
      Memuat struktur semester dan mata kuliah kurikulum...
    </div>

    <!-- Semester Cards List (Matching Image 5) -->
    <div v-else class="space-y-6">
      <Card
        v-for="sem in curriculum?.semesters || []"
        :key="sem.id"
        class="border border-slate-200/80 shadow-2xs overflow-hidden"
      >
        <!-- Semester Card Header -->
        <template #header>
          <div class="flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-900">
              Semester {{ sem.semester_number }}
            </h3>
            <button
              type="button"
              class="text-3xs font-bold text-brand-700 hover:text-brand-800 bg-brand-50 hover:bg-brand-100 px-2.5 py-1 rounded-md border border-brand-200 flex items-center gap-1 transition-colors"
              @click="openAddSubjectModal(sem.id)"
            >
              <Plus class="w-3 h-3" />
              Tambah Mata Kuliah
            </button>
          </div>
        </template>

        <!-- Semester Subjects Table (Matching Image 5) -->
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="bg-slate-50/90 border-b border-slate-200/80 text-slate-600 font-bold uppercase tracking-wider text-3xs">
                <th class="py-3 px-4 w-12 text-center">NO</th>
                <th class="py-3 px-4 w-24">KODE</th>
                <th class="py-3 px-4">MATA KULIAH</th>
                <th class="py-3 px-4 w-16 text-center">SKS</th>
                <th class="py-3 px-4 w-44">JENIS MATA KULIAH</th>
                <th class="py-3 px-4 w-28 text-center">NILAI MINIMAL</th>
                <th class="py-3 px-4 w-36">PRASYARAT</th>
                <th class="py-3 px-4 w-24 text-center">AKSI</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-700">
              <tr v-if="!sem.subjects || sem.subjects.length === 0" class="hover:bg-transparent">
                <td colspan="8" class="py-8 text-center text-slate-400">
                  Belum ada mata kuliah yang dialokasikan di Semester {{ sem.semester_number }}.
                </td>
              </tr>
              <tr
                v-for="(sub, sIdx) in sem.subjects || []"
                :key="sub.id"
                class="hover:bg-slate-50/80 transition-colors"
              >
                <td class="py-3 px-4 text-center font-medium text-slate-500">
                  {{ sIdx + 1 }}
                </td>
                <td class="py-3 px-4 font-mono font-bold text-slate-800">
                  {{ sub.course?.code || '-' }}
                </td>
                <td class="py-3 px-4 font-semibold text-slate-900">
                  {{ sub.course?.name || '-' }}
                </td>
                <td class="py-3 px-4 text-center font-bold text-slate-800">
                  {{ sub.effective_credits || sub.credits_override || sub.course?.credits || 0 }}
                </td>
                <td class="py-3 px-4">
                  <div class="flex items-center gap-1.5">
                    <!-- Badge Wajib / Pilihan -->
                    <span
                      class="px-2 py-0.5 rounded text-3xs font-bold"
                      :class="sub.subject_type === 'pilihan' ? 'bg-amber-100 text-amber-800' : 'bg-cyan-100 text-cyan-800'"
                    >
                      {{ sub.subject_type === 'pilihan' ? 'Pilihan' : 'Wajib' }}
                    </span>
                    <!-- Badge Paket -->
                    <span
                      v-if="sub.is_package !== false"
                      class="px-1.5 py-0.5 rounded text-3xs font-medium bg-slate-200 text-slate-700"
                    >
                      Paket
                    </span>
                  </div>
                </td>
                <td class="py-3 px-4 text-center font-bold text-slate-800">
                  {{ sub.minimum_grade || 'D' }}
                </td>
                <td class="py-3 px-4">
                  <span
                    v-if="sub.prerequisites_text"
                    class="text-brand-700 underline font-semibold cursor-pointer"
                  >
                    {{ sub.prerequisites_text }}
                  </span>
                  <span v-else class="text-slate-400">-</span>
                </td>
                <td class="py-3 px-4">
                  <div class="flex items-center justify-center gap-1.5">
                    <button
                      type="button"
                      class="p-1.5 text-slate-500 hover:bg-slate-100 rounded-md border border-slate-200 transition-colors"
                      title="Menu Aksi"
                    >
                      <MoreVertical class="w-3.5 h-3.5" />
                    </button>
                    <button
                      type="button"
                      class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-md border border-rose-200 transition-colors"
                      title="Hapus dari Semester"
                      @click="handleRemoveSubject(sem.id, sub)"
                    >
                      <Trash2 class="w-3.5 h-3.5" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
            <!-- Total SKS Footer Row (Matching Image 5) -->
            <tfoot>
              <tr class="bg-slate-50/90 border-t border-slate-200 font-bold text-slate-800 text-xs">
                <td colspan="3" class="py-3 px-4 text-right">Total SKS</td>
                <td class="py-3 px-4 text-center font-bold text-brand-700 text-sm">
                  {{ calculateSemesterCredits(sem) }}
                </td>
                <td colspan="4" class="py-3 px-4 text-slate-600 font-medium">
                  Wajib : {{ calculateWajibCount(sem) }} | Pilihan : {{ calculatePilihanCount(sem) }} | Paket : {{ calculatePaketCount(sem) }}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </Card>
    </div>

    <!-- Modal Tambah Mata Kuliah ke Semester -->
    <div
      v-if="addSubjectModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4"
    >
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden animate-scale-up">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
          <div>
            <h3 class="text-sm font-bold text-slate-900">Tambah Mata Kuliah ke Semester</h3>
            <p class="text-3xs text-slate-500">Alokasikan mata kuliah dari master ke dalam struktur semester</p>
          </div>
          <button
            type="button"
            class="p-1 rounded-md text-slate-400 hover:text-slate-600"
            @click="addSubjectModalOpen = false"
          >
            <X class="w-4 h-4" />
          </button>
        </div>

        <div class="p-5 space-y-4 text-xs">
          <!-- Pilih Mata Kuliah -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Pilih Mata Kuliah <span class="text-rose-500">*</span>
            </label>
            <Select
              v-model="subjectForm.course_id"
              :options="courseOptions"
              placeholder="Pilih Mata Kuliah"
              search-placeholder="Cari kode / nama mata kuliah..."
            />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <!-- Jenis Mata Kuliah -->
            <div>
              <label class="block font-semibold text-slate-700 mb-1.5">
                Jenis Mata Kuliah
              </label>
              <select
                v-model="subjectForm.subject_type"
                class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600 outline-none"
              >
                <option value="wajib">Wajib</option>
                <option value="pilihan">Pilihan</option>
              </select>
            </div>

            <!-- Paket -->
            <div>
              <label class="block font-semibold text-slate-700 mb-1.5">
                Paket Semester
              </label>
              <select
                v-model="subjectForm.is_package"
                class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600 outline-none"
              >
                <option :value="true">Ya (Paket)</option>
                <option :value="false">Tidak</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <!-- Nilai Minimal -->
            <div>
              <label class="block font-semibold text-slate-700 mb-1.5">
                Nilai Minimal Kelulusan
              </label>
              <select
                v-model="subjectForm.minimum_grade"
                class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600 outline-none"
              >
                <option value="D">D (1.00)</option>
                <option value="C">C (2.00)</option>
                <option value="C+">C+ (2.50)</option>
                <option value="B">B (3.00)</option>
                <option value="B+">B+ (3.50)</option>
                <option value="A">A (4.00)</option>
              </select>
            </div>

            <!-- Prasyarat Text -->
            <div>
              <label class="block font-semibold text-slate-700 mb-1.5">
                Keterangan Prasyarat (Opsional)
              </label>
              <Input
                v-model="subjectForm.prerequisites_text"
                placeholder="Contoh: 2 Prasyarat / Lulus MK Dasar"
              />
            </div>
          </div>
        </div>

        <div class="p-4 border-t border-slate-100 bg-slate-50/50 flex items-center justify-end gap-2">
          <Button variant="outline" size="sm" @click="addSubjectModalOpen = false">
            Batal
          </Button>
          <Button
            variant="primary"
            size="sm"
            :loading="savingSubject"
            class="bg-brand-700 hover:bg-brand-800 text-white flex items-center gap-1.5 font-semibold"
            @click="handleSaveSubject"
          >
            <Check class="w-4 h-4" />
            Tambahkan ke Semester
          </Button>
        </div>
      </div>
    </div>
  </PageContainer>
</template>
