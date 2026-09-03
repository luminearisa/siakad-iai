<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import {
  Award,
  BookOpen,
  CreditCard,
  Printer,
  FileSpreadsheet,
  CheckCircle2,
  Sparkles,
} from 'lucide-vue-next'
import PageContainer from '@/components/data-display/PageContainer.vue'
import PageHeader from '@/components/data-display/PageHeader.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Select from '@/components/ui/Select.vue'
import Badge from '@/components/ui/Badge.vue'
import Alert from '@/components/ui/Alert.vue'
import { studentPortalApi } from '@/services/api/student-portal'
import type { StudentKhsData } from '@/types/student-portal'

const loading = ref(true)
const error = ref<string | null>(null)
const khsData = ref<StudentKhsData | null>(null)
const selectedSemesterId = ref<string>('')

const semesterOptions = ref<Array<{ value: string | number; label: string }>>([])

async function loadKHS(semesterId?: number) {
  loading.value = true
  error.value = null
  try {
    const res = await studentPortalApi.getKhs(semesterId)
    const data = res.data || null
    khsData.value = data

    if (data) {
      semesterOptions.value = data.semesters.map(s => ({
        value: String(s.id),
        label: `${s.name} (${s.academic_year})`,
      }))

      if (data.selected_semester && !selectedSemesterId.value) {
        selectedSemesterId.value = String(data.selected_semester.id)
      }
    }
  } catch (err: any) {
    error.value = err.message || 'Gagal memuat Kartu Hasil Studi.'
  } finally {
    loading.value = false
  }
}

watch(selectedSemesterId, (newVal) => {
  if (newVal && khsData.value?.selected_semester?.id !== Number(newVal)) {
    loadKHS(Number(newVal))
  }
})

function handlePrint() {
  window.print()
}

function getGradeBadgeVariant(letter: string): 'success' | 'primary' | 'warning' | 'danger' | 'neutral' {
  switch (letter) {
    case 'A':
      return 'success'
    case 'B+':
    case 'B':
      return 'primary'
    case 'C+':
    case 'C':
      return 'warning'
    default:
      return 'danger'
  }
}

onMounted(() => {
  loadKHS()
})
</script>

<template>
  <PageContainer>
    <!-- Header with Semester Filter -->
    <PageHeader
      title="Kartu Hasil Studi (KHS)"
      subtitle="Laporan capaian indeks prestasi, huruf mutu, dan nilai perkuliahan mahasiswa per semester."
    >
      <template #actions>
        <div class="flex items-center gap-3">
          <div class="w-56">
            <Select
              v-model="selectedSemesterId"
              :options="semesterOptions"
              placeholder="Pilih Semester"
              :disabled="loading"
            />
          </div>

          <Button
            variant="secondary"
            class="gap-1.5 shrink-0 print:hidden"
            @click="handlePrint"
          >
            <Printer class="w-4 h-4 text-slate-600" />
            Cetak KHS
          </Button>
        </div>
      </template>
    </PageHeader>

    <!-- Alert Error -->
    <Alert v-if="error" type="danger" dismissible @dismiss="error = null">
      {{ error }}
    </Alert>

    <!-- Loading State -->
    <div v-if="loading" class="p-12 text-center text-slate-500">
      <div class="inline-block animate-spin w-8 h-8 border-4 border-brand-500 border-t-transparent rounded-full mb-3" />
      <p class="text-xs">Memuat data KHS & Indeks Prestasi...</p>
    </div>

    <!-- KHS Content -->
    <div v-else-if="khsData" class="space-y-6">
      <!-- Performance Metrics Summary Grid -->
      <div class="grid grid-cols-2 sm:grid-cols-5 gap-3.5">
        <Card class="p-4 bg-gradient-to-br from-brand-600 to-indigo-700 text-white shadow-sm relative overflow-hidden">
          <div class="flex items-center justify-between text-brand-100 mb-1.5">
            <span class="text-2xs uppercase font-semibold tracking-wider">IPS Semester</span>
            <Sparkles class="w-4 h-4 text-brand-200" />
          </div>
          <div class="text-2xl font-black font-mono">
            {{ Number(khsData.summary.semester_gpa).toFixed(2) }}
          </div>
          <span class="text-2xs text-brand-100 font-medium">Indeks Prestasi Semester</span>
        </Card>

        <Card class="p-4 bg-white border border-slate-200">
          <div class="flex items-center justify-between text-slate-500 mb-1.5">
            <span class="text-2xs uppercase font-semibold tracking-wider">IPK Kumulatif</span>
            <Award class="w-4 h-4 text-amber-500" />
          </div>
          <div class="text-2xl font-bold text-slate-900 font-mono">
            {{ Number(khsData.summary.cumulative_gpa).toFixed(2) }}
          </div>
          <span class="text-2xs text-slate-500">Indeks Prestasi Kumulatif</span>
        </Card>

        <Card class="p-4 bg-white border border-slate-200">
          <div class="flex items-center justify-between text-slate-500 mb-1.5">
            <span class="text-2xs uppercase font-semibold tracking-wider">SKS Semester</span>
            <BookOpen class="w-4 h-4 text-sky-500" />
          </div>
          <div class="text-2xl font-bold text-slate-900 font-mono">
            {{ khsData.summary.semester_credits }} <span class="text-xs font-normal text-slate-500">SKS</span>
          </div>
          <span class="text-2xs text-slate-500">Diambil Semester Ini</span>
        </Card>

        <Card class="p-4 bg-white border border-slate-200">
          <div class="flex items-center justify-between text-slate-500 mb-1.5">
            <span class="text-2xs uppercase font-semibold tracking-wider">Total SKS Lulus</span>
            <CheckCircle2 class="w-4 h-4 text-emerald-500" />
          </div>
          <div class="text-2xl font-bold text-slate-900 font-mono">
            {{ khsData.summary.cumulative_credits }} <span class="text-xs font-normal text-slate-500">SKS</span>
          </div>
          <span class="text-2xs text-slate-500">Total Kumulatif Lulus</span>
        </Card>

        <Card class="p-4 bg-white border border-slate-200 col-span-2 sm:col-span-1">
          <div class="flex items-center justify-between text-slate-500 mb-1.5">
            <span class="text-2xs uppercase font-semibold tracking-wider">Maks. SKS Berikut</span>
            <CreditCard class="w-4 h-4 text-purple-500" />
          </div>
          <div class="text-2xl font-bold text-slate-900 font-mono">
            {{ khsData.summary.max_credits_next }} <span class="text-xs font-normal text-slate-500">SKS</span>
          </div>
          <span class="text-2xs text-emerald-600 font-medium">Beban SKS Semester Depan</span>
        </Card>
      </div>

      <!-- KHS Courses Table Card -->
      <Card class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
        <div class="px-5 py-4 border-b border-slate-200 bg-slate-50/60 flex items-center justify-between">
          <div class="flex items-center gap-2.5">
            <FileSpreadsheet class="w-4 h-4 text-brand-600" />
            <h3 class="text-sm font-bold text-slate-900">
              Daftar Nilai Mata Kuliah &bull; {{ khsData.selected_semester?.name || 'Semester Aktif' }}
            </h3>
          </div>
          <span class="text-xs text-slate-500 font-medium">
            Total {{ khsData.courses.length }} Mata Kuliah
          </span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="border-b border-slate-200 bg-slate-100/75 text-2xs font-semibold uppercase tracking-wider text-slate-600">
                <th class="py-3 px-4 text-center w-12">No</th>
                <th class="py-3 px-4">Kode MK</th>
                <th class="py-3 px-4">Nama Mata Kuliah</th>
                <th class="py-3 px-4 text-center">SKS</th>
                <th class="py-3 px-4 text-center">Nilai Angka</th>
                <th class="py-3 px-4 text-center">Huruf Mutu</th>
                <th class="py-3 px-4 text-center">Bobot</th>
                <th class="py-3 px-4 text-center">SKS &times; Bobot</th>
                <th class="py-3 px-4 text-center">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="(course, idx) in khsData.courses"
                :key="course.enrollment_item_id"
                class="hover:bg-slate-50/80 transition-colors"
              >
                <td class="py-3.5 px-4 text-center text-slate-400 font-mono">{{ idx + 1 }}</td>
                <td class="py-3.5 px-4 font-mono font-bold text-brand-600">{{ course.course_code }}</td>
                <td class="py-3.5 px-4 font-medium text-slate-900">
                  <div>{{ course.course_name }}</div>
                  <div v-if="course.lecturers.length > 0" class="text-2xs text-slate-500 mt-0.5">
                    Dosen: {{ course.lecturers.join(', ') }}
                  </div>
                </td>
                <td class="py-3.5 px-4 text-center font-bold text-slate-800">{{ course.credits }}</td>
                <td class="py-3.5 px-4 text-center font-mono font-bold text-slate-900">
                  {{ Number(course.final_score).toFixed(1) }}
                </td>
                <td class="py-3.5 px-4 text-center">
                  <Badge :variant="getGradeBadgeVariant(course.letter_grade)" size="md" class="font-bold">
                    {{ course.letter_grade }}
                  </Badge>
                </td>
                <td class="py-3.5 px-4 text-center font-mono text-slate-700">
                  {{ Number(course.grade_point).toFixed(2) }}
                </td>
                <td class="py-3.5 px-4 text-center font-mono font-bold text-brand-700">
                  {{ Number(course.quality_points).toFixed(2) }}
                </td>
                <td class="py-3.5 px-4 text-center">
                  <Badge :variant="course.is_passed ? 'success' : 'danger'" size="sm">
                    {{ course.status }}
                  </Badge>
                </td>
              </tr>

              <tr v-if="khsData.courses.length === 0">
                <td colspan="9" class="p-8 text-center text-slate-500">
                  Belum ada data nilai mata kuliah pada semester yang dipilih.
                </td>
              </tr>
            </tbody>
            <tfoot class="border-t-2 border-slate-200 bg-slate-50 font-semibold text-xs text-slate-700">
              <tr>
                <td colspan="3" class="py-3 px-4 text-right text-slate-500">Total Semester:</td>
                <td class="py-3 px-4 text-center font-bold text-slate-900 font-mono">{{ khsData.summary.semester_credits }} SKS</td>
                <td colspan="3" class="py-3 px-4 text-right text-slate-500">Total Bobot Mutu:</td>
                <td class="py-3 px-4 text-center font-bold text-brand-700 font-mono">{{ Number(khsData.summary.semester_quality_points).toFixed(2) }}</td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </Card>
    </div>
  </PageContainer>
</template>
