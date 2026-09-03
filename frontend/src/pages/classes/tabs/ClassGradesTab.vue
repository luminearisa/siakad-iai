<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue'
import {
  Award,
  Save,
  Lock,
  Sliders,
  AlertCircle,
  FileCheck,
} from 'lucide-vue-next'
import { assessmentService } from '@/services/api/assessment'
import { useToast } from '@/composables/useToast'
import type { AcademicClass } from '@/types/class'
import type {
  ClassGradeRecap,
  AssessmentComponent,
  AssessmentScheme,
  BatchGradeEntry,
} from '@/types/assessment'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Badge from '@/components/ui/Badge.vue'
import Alert from '@/components/ui/Alert.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import ConfirmModal from '@/components/feedback/ConfirmModal.vue'

interface Props {
  academicClass: AcademicClass
  isReadOnly?: boolean
}

const props = defineProps<Props>()

const toast = useToast()
const loading = ref<boolean>(true)
const saving = ref<boolean>(false)
const submitting = ref<boolean>(false)
const finalizing = ref<boolean>(false)
const errorMessage = ref<string | null>(null)

const gradeRecap = ref<ClassGradeRecap | null>(null)
const scheme = ref<AssessmentScheme | null>(null)
const components = ref<AssessmentComponent[]>([])

// Reactive inputs matrix: { [studentId]: { [componentId]: score } }
const gradeInputs = reactive<Record<number, Record<number, number | string>>>({})
const hasUnsavedChanges = ref<boolean>(false)

// Modals
const submitModalOpen = ref<boolean>(false)
const finalizeModalOpen = ref<boolean>(false)
const createSchemeModalOpen = ref<boolean>(false)
const creatingScheme = ref<boolean>(false)

async function loadClassGrades() {
  loading.value = true
  errorMessage.value = null
  try {
    const [gradesRes, schemeRes, compRes] = await Promise.allSettled([
      assessmentService.getClassGrades(props.academicClass.id),
      assessmentService.getClassScheme(props.academicClass.id),
      assessmentService.getClassComponents(props.academicClass.id),
    ])

    if (gradesRes.status === 'fulfilled') {
      const resVal: any = gradesRes.value
      const recap = resVal?.data ?? resVal
      if (recap && typeof recap === 'object') {
        gradeRecap.value = recap
        initGradeInputs(recap)
      }
    }

    if (schemeRes.status === 'fulfilled') {
      const resVal: any = schemeRes.value
      scheme.value = resVal?.data ?? resVal
    }

    if (compRes.status === 'fulfilled') {
      const resVal: any = compRes.value
      const compData = resVal?.data ?? resVal
      components.value = Array.isArray(compData) ? compData : []
    }
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal memuat data nilai kelas perkuliahan.'
  } finally {
    loading.value = false
    hasUnsavedChanges.value = false
  }
}

function initGradeInputs(recap: ClassGradeRecap) {
  const list = recap?.grades || recap?.students || []
  for (const st of list) {
    if (!gradeInputs[st.student.id]) {
      gradeInputs[st.student.id] = {}
    }
    if (st.components) {
      for (const comp of st.components) {
        gradeInputs[st.student.id][comp.component_id] = comp.raw_score > 0 ? comp.raw_score : ''
      }
    }
  }
}

function handleInputChange(studentId: number, componentId: number) {
  hasUnsavedChanges.value = true
  const val = gradeInputs[studentId]?.[componentId]
  if (val !== '' && val !== undefined) {
    let num = Number(val)
    if (num < 0) gradeInputs[studentId][componentId] = 0
    if (num > 100) gradeInputs[studentId][componentId] = 100
  }
}

// Student grades list
const studentGrades = computed(() => {
  return gradeRecap.value?.grades || gradeRecap.value?.students || []
})

// Active components from scheme, list, or first student
const activeComponents = computed(() => {
  if (scheme.value?.items && scheme.value.items.length > 0) {
    return scheme.value.items.map((item) => ({
      id: item.assessment_component_id,
      name: item.component?.name || 'Komponen',
      code: item.component?.code || 'KMP',
      weight: item.weight,
      max_score: item.component?.max_score || 100,
    }))
  }
  if (components.value.length > 0) {
    return components.value.map((c) => ({
      id: c.id,
      name: c.name,
      code: c.code,
      weight: 100 / components.value.length,
      max_score: c.max_score,
    }))
  }
  if (studentGrades.value.length > 0 && studentGrades.value[0].components?.length > 0) {
    return studentGrades.value[0].components.map((c) => ({
      id: c.component_id,
      name: c.component_name,
      code: c.component_code,
      weight: c.weight,
      max_score: 100,
    }))
  }
  return []
})

// Calculate live estimated final score and letter grade for a student
function getLiveStudentCalculation(studentId: number) {
  if (!activeComponents.value || activeComponents.value.length === 0) {
    return { finalScore: 0, letterGrade: 'E', isPassed: false }
  }

  let totalWeighted = 0
  let totalWeightCounted = 0

  for (const comp of activeComponents.value) {
    const raw = Number(gradeInputs[studentId]?.[comp.id] ?? 0)
    if (!isNaN(raw) && raw >= 0) {
      totalWeighted += (raw / 100) * comp.weight
      totalWeightCounted += comp.weight
    }
  }

  const finalScore = Math.round(totalWeighted * 100) / 100
  let letterGrade = 'E'
  if (finalScore >= 85) letterGrade = 'A'
  else if (finalScore >= 75) letterGrade = 'B+'
  else if (finalScore >= 65) letterGrade = 'B'
  else if (finalScore >= 60) letterGrade = 'C+'
  else if (finalScore >= 55) letterGrade = 'C'
  else if (finalScore >= 40) letterGrade = 'D'
  else letterGrade = 'E'

  const isPassed = ['A', 'B+', 'B', 'C+', 'C'].includes(letterGrade)
  return { finalScore, letterGrade, isPassed }
}

// Class Summary Stats
const summaryStats = computed(() => {
  const students = studentGrades.value
  if (students.length === 0) {
    return {
      average: 0,
      total: 0,
      passed: 0,
      failed: 0,
      distribution: { A: 0, 'B+': 0, B: 0, 'C+': 0, C: 0, D: 0, E: 0 },
    }
  }

  let sum = 0
  let passed = 0
  const dist: Record<string, number> = { A: 0, 'B+': 0, B: 0, 'C+': 0, C: 0, D: 0, E: 0 }

  for (const st of students) {
    const calc = getLiveStudentCalculation(st.student.id)
    sum += calc.finalScore
    if (calc.isPassed) passed++
    if (dist[calc.letterGrade] !== undefined) {
      dist[calc.letterGrade]++
    }
  }

  return {
    average: Math.round((sum / students.length) * 100) / 100,
    total: students.length,
    passed,
    failed: students.length - passed,
    distribution: dist,
  }
})

// Grade Status
const classGradeStatus = computed<'draft' | 'submitted' | 'finalized'>(() => {
  const students = studentGrades.value
  if (students.length === 0) return 'draft'
  const allFinalized = students.every((s) => s.components?.every((c) => c.status === 'finalized' || c.status === 'final'))
  if (allFinalized && students.length > 0) return 'finalized'
  const allSubmitted = students.every((s) => s.components?.some((c) => c.status === 'submitted' || c.status === 'finalized' || c.status === 'final'))
  if (allSubmitted && students.length > 0) return 'submitted'
  return 'draft'
})

// Batch Save Action
async function handleSaveGrades() {
  const entries: BatchGradeEntry[] = []
  for (const studentIdStr of Object.keys(gradeInputs)) {
    const studentId = Number(studentIdStr)
    const comps = gradeInputs[studentId]
    for (const compIdStr of Object.keys(comps)) {
      const compId = Number(compIdStr)
      const val = comps[compId]
      if (val !== '' && val !== undefined && !isNaN(Number(val))) {
        entries.push({
          student_id: studentId,
          assessment_component_id: compId,
          score: Number(val),
        })
      }
    }
  }

  if (entries.length === 0) {
    toast.warning('Belum ada nilai yang dimasukkan.')
    return
  }

  saving.value = true
  try {
    await assessmentService.saveBatchGrades(props.academicClass.id, { grades: entries })
    toast.success(`Berhasil menyimpan ${entries.length} data nilai mahasiswa.`)
    hasUnsavedChanges.value = false
    await loadClassGrades()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menyimpan draf nilai.')
  } finally {
    saving.value = false
  }
}

// Submit Grades to Prodi Action
async function handleSubmitGrades() {
  submitting.value = true
  try {
    await assessmentService.submitClassGrades(props.academicClass.id)
    toast.success('Nilai perkuliahan berhasil disubmit untuk verifikasi.')
    submitModalOpen.value = false
    await loadClassGrades()
  } catch (err: any) {
    toast.error(err.message || 'Gagal submit nilai perkuliahan.')
  } finally {
    submitting.value = false
  }
}

// Finalize Grades Action
async function handleFinalizeGrades() {
  finalizing.value = true
  try {
    await assessmentService.finalizeClassGrades(props.academicClass.id)
    toast.success('Nilai perkuliahan berhasil difinalisasi dan diterbitkan ke KHS!')
    finalizeModalOpen.value = false
    await loadClassGrades()
  } catch (err: any) {
    toast.error(err.message || 'Gagal finalisasi nilai kelas.')
  } finally {
    finalizing.value = false
  }
}

// Initialize standard assessment scheme if empty
async function handleCreateDefaultScheme() {
  creatingScheme.value = true
  try {
    await assessmentService.createClassScheme(props.academicClass.id, {
      name: `Skema Penilaian ${props.academicClass.code}`,
      description: 'Skema penilaian standar (Kehadiran 10%, Tugas 20%, UTS 30%, UAS 40%)',
      is_active: true,
      items: [
        {
          component: {
            name: 'Kehadiran & Keaktifan',
            code: 'ABS',
            type: 'attendance',
            max_score: 100,
            is_required: true,
            sequence: 1,
          },
          weight: 10,
        },
        {
          component: {
            name: 'Tugas & Kuis',
            code: 'TGS',
            type: 'assignment',
            max_score: 100,
            is_required: true,
            sequence: 2,
          },
          weight: 20,
        },
        {
          component: {
            name: 'Ujian Tengah Semester (UTS)',
            code: 'UTS',
            type: 'midterm',
            max_score: 100,
            is_required: true,
            sequence: 3,
          },
          weight: 30,
        },
        {
          component: {
            name: 'Ujian Akhir Semester (UAS)',
            code: 'UAS',
            type: 'final',
            max_score: 100,
            is_required: true,
            sequence: 4,
          },
          weight: 40,
        },
      ],
    })
    toast.success('Skema komponen penilaian standar berhasil dibuat.')
    createSchemeModalOpen.value = false
    await loadClassGrades()
  } catch (err: any) {
    toast.error(err.message || 'Gagal membuat skema komponen penilaian.')
  } finally {
    creatingScheme.value = false
  }
}

onMounted(() => {
  loadClassGrades()
})

watch(() => props.academicClass.id, () => {
  loadClassGrades()
})
</script>

<template>
  <div class="space-y-5">
    <!-- Loading State -->
    <div v-if="loading" class="space-y-4">
      <Skeleton height="5rem" rounded="lg" />
      <Skeleton height="16rem" rounded="lg" />
    </div>

    <!-- Error Alert -->
    <Alert v-else-if="errorMessage" variant="danger" :title="errorMessage" />

    <!-- Main Content -->
    <div v-else class="space-y-5">
      <!-- Top Control & Status Card -->
      <Card class="p-4 bg-white border border-slate-200 rounded-xl shadow-2xs">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div class="space-y-1">
            <div class="flex items-center gap-2">
              <h2 class="text-sm font-bold text-slate-900 tracking-tight">
                Lembar Input & Rekapitulasi Nilai Mahasiswa
              </h2>
              <Badge
                :variant="classGradeStatus === 'finalized' ? 'success' : classGradeStatus === 'submitted' ? 'info' : 'warning'"
                size="xs"
                dot
              >
                {{
                  classGradeStatus === 'finalized'
                    ? 'Nilai Final & Terkunci'
                    : classGradeStatus === 'submitted'
                    ? 'Submitted (Verifikasi Prodi)'
                    : 'Draf Penilaian Dosen'
                }}
              </Badge>
            </div>
            <p class="text-2xs text-slate-500">
              Input nilai angka (0–100) per komponen bobot. Nilai akhir dan huruf mutu dihitung secara otomatis.
            </p>
          </div>

          <!-- Action Buttons -->
          <div class="flex flex-wrap items-center gap-2 shrink-0">
            <Button
              v-if="activeComponents.length === 0"
              variant="primary"
              size="sm"
              class="gap-1.5 text-xs"
              @click="createSchemeModalOpen = true"
            >
              <Sliders class="w-3.5 h-3.5" />
              Atur Bobot Komponen
            </Button>

            <template v-else>
              <Button
                variant="secondary"
                size="sm"
                class="gap-1.5 text-xs"
                :loading="saving"
                :disabled="classGradeStatus === 'finalized' || isReadOnly"
                @click="handleSaveGrades"
              >
                <Save class="w-3.5 h-3.5 text-slate-500" />
                Simpan Draf Nilai
              </Button>

              <Button
                v-if="classGradeStatus === 'draft'"
                variant="primary"
                size="sm"
                class="gap-1.5 text-xs"
                :disabled="isReadOnly"
                @click="submitModalOpen = true"
              >
                <FileCheck class="w-3.5 h-3.5" />
                Submit Nilai ke Prodi
              </Button>

              <Button
                v-if="classGradeStatus === 'submitted'"
                variant="primary"
                size="sm"
                class="gap-1.5 text-xs bg-emerald-600 hover:bg-emerald-700 text-white"
                :disabled="isReadOnly"
                @click="finalizeModalOpen = true"
              >
                <Lock class="w-3.5 h-3.5" />
                Finalisasi & Kunci Nilai
              </Button>
            </template>
          </div>
        </div>

        <!-- Unsaved Changes Warning Banner -->
        <div
          v-if="hasUnsavedChanges"
          class="mt-3.5 p-2.5 bg-amber-50 border border-amber-200 rounded-lg flex items-center justify-between gap-3 text-xs text-amber-800"
        >
          <div class="flex items-center gap-2">
            <AlertCircle class="w-4 h-4 text-amber-600 shrink-0" />
            <span>Ada perubahan nilai yang belum disimpan ke database.</span>
          </div>
          <Button
            variant="primary"
            size="xs"
            class="bg-amber-600 hover:bg-amber-700 text-white gap-1"
            :loading="saving"
            @click="handleSaveGrades"
          >
            Simpan Sekarang
          </Button>
        </div>
      </Card>

      <!-- Scheme Components & Weight Recap Bar -->
      <div v-if="activeComponents.length > 0" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        <Card
          v-for="comp in activeComponents"
          :key="comp.id"
          class="p-3 bg-white border border-slate-200 rounded-xl shadow-2xs"
        >
          <div class="flex items-center justify-between text-slate-500 mb-1">
            <span class="text-3xs uppercase font-bold tracking-wider">{{ comp.code }}</span>
            <span class="text-xs font-bold text-brand-600 font-mono">{{ comp.weight }}%</span>
          </div>
          <div class="text-xs font-bold text-slate-800 truncate" :title="comp.name">
            {{ comp.name }}
          </div>
          <div class="text-3xs text-slate-400 mt-0.5">
            Skor Maks: {{ comp.max_score }}
          </div>
        </Card>
      </div>

      <!-- Quick Metrics Summary Grid -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <Card class="p-3.5 bg-white border border-slate-200 shadow-2xs">
          <div class="text-3xs uppercase font-semibold text-slate-500 mb-1">Total Peserta</div>
          <div class="text-xl font-bold text-slate-900 font-mono">
            {{ summaryStats.total }} <span class="text-xs font-normal text-slate-500">Mahasiswa</span>
          </div>
        </Card>

        <Card class="p-3.5 bg-white border border-slate-200 shadow-2xs">
          <div class="text-3xs uppercase font-semibold text-slate-500 mb-1">Rata-Rata Kelas</div>
          <div class="text-xl font-bold text-brand-700 font-mono">
            {{ summaryStats.average.toFixed(2) }} <span class="text-xs font-normal text-slate-500">/ 100</span>
          </div>
        </Card>

        <Card class="p-3.5 bg-white border border-slate-200 shadow-2xs">
          <div class="text-3xs uppercase font-semibold text-slate-500 mb-1">Tingkat Kelulusan</div>
          <div class="text-xl font-bold text-emerald-600 font-mono">
            {{ summaryStats.passed }} <span class="text-xs font-normal text-slate-500">Lulus</span>
          </div>
          <div class="text-3xs text-slate-400">
            {{ summaryStats.total > 0 ? Math.round((summaryStats.passed / summaryStats.total) * 100) : 0 }}% Lulus (Nilai ≥ C)
          </div>
        </Card>

        <Card class="p-3.5 bg-white border border-slate-200 shadow-2xs">
          <div class="text-3xs uppercase font-semibold text-slate-500 mb-1">Sebaran Nilai Huruf</div>
          <div class="flex items-center gap-1.5 text-2xs font-mono font-bold mt-1">
            <span class="text-emerald-700">A:{{ summaryStats.distribution.A }}</span>
            <span class="text-brand-600">B+:{{ summaryStats.distribution['B+'] }}</span>
            <span class="text-blue-600">B:{{ summaryStats.distribution.B }}</span>
            <span class="text-amber-600">C:{{ (summaryStats.distribution['C+'] || 0) + (summaryStats.distribution.C || 0) }}</span>
            <span class="text-rose-600">D/E:{{ (summaryStats.distribution.D || 0) + (summaryStats.distribution.E || 0) }}</span>
          </div>
        </Card>
      </div>

      <!-- Main Grading Matrix Table -->
      <Card class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-2xs">
        <template #header>
          <div class="w-full flex items-center justify-between">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800 flex items-center gap-2">
              <Award class="w-4 h-4 text-brand-600" />
              Matriks Penilaian Peserta Kelas
            </h3>
            <span class="text-2xs text-slate-500 font-medium">
              {{ studentGrades.length }} Mahasiswa Terdaftar
            </span>
          </div>
        </template>

        <div v-if="!studentGrades || studentGrades.length === 0" class="text-center py-12 text-slate-400 text-xs">
          <AlertCircle class="w-8 h-8 mx-auto text-slate-300 mb-2" />
          <p class="font-semibold text-slate-700">Belum ada mahasiswa yang terdaftar di kelas ini.</p>
          <p class="text-slate-400 text-2xs mt-0.5">Mahasiswa akan muncul setelah KRS disetujui.</p>
        </div>

        <div v-else-if="activeComponents.length === 0" class="text-center py-12 text-slate-500 text-xs space-y-3">
          <Sliders class="w-8 h-8 mx-auto text-brand-500 mb-1" />
          <div>
            <p class="font-bold text-slate-800">Skema Komponen Penilaian Belum Diatur</p>
            <p class="text-slate-500 text-2xs mt-0.5">Silakan atur bobot komponen penilaian (Absensi, Tugas, UTS, UAS) untuk memulai input nilai.</p>
          </div>
          <Button variant="primary" size="sm" class="gap-1 text-xs" @click="createSchemeModalOpen = true">
            Buat Skema Penilaian Standar
          </Button>
        </div>

        <!-- Table Responsive Container -->
        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="bg-slate-50/80 border-b border-slate-200 text-3xs font-semibold text-slate-600 uppercase tracking-wider">
                <th class="py-2.5 px-3 w-10 text-center">No</th>
                <th class="py-2.5 px-3 w-32">NIM</th>
                <th class="py-2.5 px-3 min-w-[180px]">Nama Mahasiswa</th>
                <th
                  v-for="comp in activeComponents"
                  :key="comp.id"
                  class="py-2.5 px-3 text-center w-28"
                >
                  <div class="font-bold text-slate-800">{{ comp.name }}</div>
                  <div class="text-3xs text-brand-600 font-normal">({{ comp.weight }}%)</div>
                </th>
                <th class="py-2.5 px-3 w-28 text-center bg-slate-100/60 font-bold">
                  Nilai Akhir
                </th>
                <th class="py-2.5 px-3 w-20 text-center bg-slate-100/60 font-bold">
                  Huruf
                </th>
                <th class="py-2.5 px-3 w-28 text-center bg-slate-100/60 font-bold">
                  Status
                </th>
              </tr>
            </thead>

            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="(st, idx) in studentGrades"
                :key="st.student.id"
                class="hover:bg-slate-50/60 transition-colors"
              >
                <!-- Nomor -->
                <td class="py-2 px-3 text-center text-slate-400 font-mono text-3xs">
                  {{ idx + 1 }}
                </td>

                <!-- NIM -->
                <td class="py-2 px-3 font-mono font-medium text-slate-700 whitespace-nowrap">
                  {{ st.student.student_number }}
                </td>

                <!-- Nama -->
                <td class="py-2 px-3">
                  <div class="font-bold text-slate-900 leading-tight">
                    {{ st.student.full_name }}
                  </div>
                  <div class="text-3xs text-slate-400">
                    {{ st.student.study_program || 'Reguler' }}
                  </div>
                </td>

                <!-- Komponen Penilaian Input Cells -->
                <td
                  v-for="comp in activeComponents"
                  :key="comp.id"
                  class="py-2 px-2 text-center"
                >
                  <input
                    v-model.number="gradeInputs[st.student.id][comp.id]"
                    type="number"
                    min="0"
                    max="100"
                    step="0.1"
                    placeholder="0"
                    :disabled="classGradeStatus === 'finalized' || isReadOnly"
                    :class="[
                      'w-20 text-center font-mono text-xs py-1 px-1.5 rounded border transition-all outline-none',
                      classGradeStatus === 'finalized' || isReadOnly
                        ? 'bg-slate-100 text-slate-600 border-slate-200 cursor-not-allowed'
                        : 'bg-white text-slate-900 border-slate-300 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 hover:border-slate-400'
                    ]"
                    @input="handleInputChange(st.student.id, comp.id)"
                  />
                </td>

                <!-- Nilai Akhir Otomatis (Live) -->
                <td class="py-2 px-3 text-center bg-slate-50/40 font-mono font-bold text-slate-900 text-sm">
                  {{ getLiveStudentCalculation(st.student.id).finalScore.toFixed(2) }}
                </td>

                <!-- Huruf Mutu (Live) -->
                <td class="py-2 px-3 text-center bg-slate-50/40 font-mono font-black text-sm">
                  <span
                    :class="[
                      getLiveStudentCalculation(st.student.id).letterGrade === 'A'
                        ? 'text-emerald-700'
                        : getLiveStudentCalculation(st.student.id).letterGrade.startsWith('B')
                        ? 'text-brand-600'
                        : getLiveStudentCalculation(st.student.id).letterGrade.startsWith('C')
                        ? 'text-amber-600'
                        : 'text-rose-600'
                    ]"
                  >
                    {{ getLiveStudentCalculation(st.student.id).letterGrade }}
                  </span>
                </td>

                <!-- Status Kelulusan (Live) -->
                <td class="py-2 px-3 text-center bg-slate-50/40 whitespace-nowrap">
                  <Badge
                    :variant="getLiveStudentCalculation(st.student.id).isPassed ? 'success' : 'danger'"
                    size="xs"
                  >
                    {{ getLiveStudentCalculation(st.student.id).isPassed ? 'Lulus' : 'Tidak Lulus' }}
                  </Badge>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </Card>
    </div>

    <!-- Modals -->
    <!-- Submit Confirmation Modal -->
    <ConfirmModal
      :open="submitModalOpen"
      title="Submit Nilai Perkuliahan"
      message="Apakah Anda yakin ingin mengirim nilai kelas ini ke Program Studi? Setelah disubmit, draf nilai akan diverifikasi sebelum difinalisasi."
      confirm-text="Ya, Submit Nilai"
      variant="primary"
      :loading="submitting"
      @update:open="submitModalOpen = $event"
      @confirm="handleSubmitGrades"
    />

    <!-- Finalize Confirmation Modal -->
    <ConfirmModal
      :open="finalizeModalOpen"
      title="Finalisasi & Kunci Nilai"
      message="Apakah Anda yakin ingin memfinalisasi nilai perkuliahan ini? Nilai akhir akan dikunci dan otomatis masuk ke KHS mahasiswa."
      confirm-text="Finalisasi & Kunci"
      variant="primary"
      :loading="finalizing"
      @update:open="finalizeModalOpen = $event"
      @confirm="handleFinalizeGrades"
    />

    <!-- Create Default Scheme Modal -->
    <ConfirmModal
      :open="createSchemeModalOpen"
      title="Buat Skema Penilaian Standar"
      message="Buat skema komponen bobot penilaian standar (Kehadiran: 10%, Tugas: 20%, UTS: 30%, UAS: 40%) untuk kelas ini?"
      confirm-text="Buat Skema Standar"
      variant="primary"
      :loading="creatingScheme"
      @update:open="createSchemeModalOpen = $event"
      @confirm="handleCreateDefaultScheme"
    />
  </div>
</template>
