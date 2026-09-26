<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeft,
  Plus,
  Trash2,
  MapPin,
  Save,
  Users,
  CalendarRange,
} from 'lucide-vue-next'
import { mbkmService } from '@/services/api/mbkm'
import { useToast } from '@/composables/useToast'
import {
  ASSESSOR_TYPE_LABELS,
  COMPONENT_TYPE_LABELS,
  LOCATION_MODE_LABELS,
  ORGANIZER_TYPE_LABELS,
  PROGRAM_STATUS_LABELS,
  PROGRAM_STATUS_VARIANTS,
  REQUIREMENT_TYPE_LABELS,
  labelOf,
  optionsFrom,
} from '@/types/mbkm'
import type {
  LocationMode,
  MbkmAssessmentComponent,
  MbkmProgram,
  MbkmSelectionCriteria,
} from '@/types/mbkm'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Textarea from '@/components/ui/Textarea.vue'
import Modal from '@/components/ui/Modal.vue'
import Tabs, { type TabItem } from '@/components/ui/Tabs.vue'
import MbkmStatusBadge from '@/pages/mbkm/components/MbkmStatusBadge.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const programId = Number(route.params.id)

const loading = ref(true)
const saving = ref(false)
const program = ref<MbkmProgram | null>(null)
const activeTab = ref<string>('informasi')

const tabs: TabItem[] = [
  { id: 'informasi', label: 'Informasi' },
  { id: 'lokasi', label: 'Lokasi' },
  { id: 'persyaratan', label: 'Persyaratan' },
  { id: 'kriteria', label: 'Kriteria Seleksi' },
  { id: 'penilaian', label: 'Komponen Penilaian' },
]

// ---- Location form
const showLocationModal = ref(false)
const locationForm = reactive({
  name: '',
  location_mode: 'off_campus' as LocationMode,
  address: '',
  city: '',
  province: '',
  country: 'Indonesia',
  is_remote: false,
  notes: '',
})

// ---- Requirement form
const showRequirementModal = ref(false)
const requirementForm = reactive({
  type: 'academic',
  code: '',
  name: '',
  description: '',
  is_mandatory: true,
  is_document: false,
  rule_field: '',
  rule_operator: '>=',
  rule_value: '',
})

// ---- Selection criteria (editable grid)
const criteriaRows = ref<Array<{ name: string; description: string; weight: number; max_score: number }>>([])
const criteriaTotal = computed(() => criteriaRows.value.reduce((sum, r) => sum + Number(r.weight || 0), 0))

// ---- Assessment components (editable grid)
const componentRows = ref<
  Array<{ code: string; name: string; type: string; weight: number; max_score: number; assessor_type: string }>
>([])
const componentTotal = computed(() => componentRows.value.reduce((sum, r) => sum + Number(r.weight || 0), 0))

const RULE_FIELDS = [
  { value: '', label: 'Tanpa aturan otomatis' },
  { value: 'gpa', label: 'IPK Kumulatif' },
  { value: 'total_credits', label: 'Total SKS Lulus' },
  { value: 'current_semester', label: 'Semester Saat Ini' },
  { value: 'admission_year', label: 'Tahun Angkatan' },
  { value: 'study_program_id', label: 'Program Studi' },
  { value: 'student_status', label: 'Status Mahasiswa' },
  { value: 'passed_course_count', label: 'Jumlah MK Lulus' },
]

const RULE_OPERATORS = ['>=', '<=', '>', '<', '==', '!=', 'in', 'not_in']

async function load() {
  loading.value = true
  try {
    const res = await mbkmService.getProgram(programId)
    program.value = res.data
    syncCriteriaRows(res.data.selection_criteria ?? [])
    syncComponentRows(res.data.assessment_components ?? [])
  } catch (err: any) {
    toast.error(err.message || 'Gagal memuat detail program MBKM')
  } finally {
    loading.value = false
  }
}

function syncCriteriaRows(rows: MbkmSelectionCriteria[]) {
  criteriaRows.value = rows.map((c) => ({
    name: c.name,
    description: c.description ?? '',
    weight: Number(c.weight),
    max_score: Number(c.max_score),
  }))
}

function syncComponentRows(rows: MbkmAssessmentComponent[]) {
  componentRows.value = rows.map((c) => ({
    code: c.code,
    name: c.name,
    type: c.type,
    weight: Number(c.weight),
    max_score: Number(c.max_score),
    assessor_type: c.assessor_type,
  }))
}

function addCriteriaRow() {
  criteriaRows.value.push({ name: '', description: '', weight: 0, max_score: 100 })
}

function addComponentRow() {
  componentRows.value.push({
    code: '',
    name: '',
    type: 'performance',
    weight: 0,
    max_score: 100,
    assessor_type: 'internal_supervisor',
  })
}

async function saveCriteria() {
  if (criteriaRows.value.length === 0) {
    toast.error('Minimal satu kriteria seleksi')
    return
  }
  if (criteriaRows.value.some((r) => !r.name.trim())) {
    toast.error('Nama kriteria wajib diisi')
    return
  }
  saving.value = true
  try {
    await mbkmService.syncSelectionCriteria(
      programId,
      criteriaRows.value.map((r, index) => ({
        name: r.name,
        description: r.description || undefined,
        weight: Number(r.weight),
        max_score: Number(r.max_score),
        sort_order: index,
      }))
    )
    toast.success('Kriteria seleksi berhasil disimpan')
    await load()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menyimpan kriteria seleksi')
  } finally {
    saving.value = false
  }
}

async function saveComponents() {
  if (componentRows.value.length === 0) {
    toast.error('Minimal satu komponen penilaian')
    return
  }
  if (Math.abs(componentTotal.value - 100) > 0.01) {
    toast.error(`Total bobot komponen harus 100% (saat ini ${componentTotal.value}%)`)
    return
  }
  if (componentRows.value.some((r) => !r.name.trim())) {
    toast.error('Nama komponen wajib diisi')
    return
  }
  saving.value = true
  try {
    await mbkmService.syncAssessmentComponents(
      programId,
      componentRows.value.map((r, index) => ({
        code: r.code || undefined,
        name: r.name,
        type: r.type,
        weight: Number(r.weight),
        max_score: Number(r.max_score),
        assessor_type: r.assessor_type,
        sort_order: index,
      }))
    )
    toast.success('Komponen penilaian berhasil disimpan')
    await load()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menyimpan komponen penilaian')
  } finally {
    saving.value = false
  }
}

async function handleSaveLocation() {
  if (!locationForm.name.trim()) {
    toast.error('Nama lokasi wajib diisi')
    return
  }
  saving.value = true
  try {
    await mbkmService.storeProgramLocation(programId, { ...locationForm })
    toast.success('Lokasi berhasil ditambahkan')
    showLocationModal.value = false
    Object.assign(locationForm, {
      name: '',
      location_mode: 'off_campus',
      address: '',
      city: '',
      province: '',
      country: 'Indonesia',
      is_remote: false,
      notes: '',
    })
    await load()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menambah lokasi')
  } finally {
    saving.value = false
  }
}

async function handleDeleteLocation(id: number) {
  if (!confirm('Hapus lokasi ini?')) return
  try {
    await mbkmService.deleteProgramLocation(programId, id)
    toast.success('Lokasi berhasil dihapus')
    await load()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menghapus lokasi')
  }
}

async function handleSaveRequirement() {
  if (!requirementForm.name.trim()) {
    toast.error('Nama persyaratan wajib diisi')
    return
  }
  saving.value = true
  try {
    const payload: Record<string, unknown> = {
      type: requirementForm.type,
      code: requirementForm.code || undefined,
      name: requirementForm.name,
      description: requirementForm.description || undefined,
      is_mandatory: requirementForm.is_mandatory,
      is_document: requirementForm.is_document,
    }
    if (requirementForm.rule_field) {
      payload.rule = {
        field: requirementForm.rule_field,
        operator: requirementForm.rule_operator,
        value: requirementForm.rule_value,
      }
    }
    await mbkmService.storeProgramRequirement(programId, payload)
    toast.success('Persyaratan berhasil ditambahkan')
    showRequirementModal.value = false
    Object.assign(requirementForm, {
      type: 'academic',
      code: '',
      name: '',
      description: '',
      is_mandatory: true,
      is_document: false,
      rule_field: '',
      rule_operator: '>=',
      rule_value: '',
    })
    await load()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menambah persyaratan')
  } finally {
    saving.value = false
  }
}

async function handleDeleteRequirement(id: number) {
  if (!confirm('Hapus persyaratan ini?')) return
  try {
    await mbkmService.deleteProgramRequirement(programId, id)
    toast.success('Persyaratan berhasil dihapus')
    await load()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menghapus persyaratan')
  }
}

function formatDate(value?: string | null) {
  if (!value) return '-'
  try {
    return new Date(value).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' })
  } catch {
    return value
  }
}

onMounted(load)
</script>

<template>
  <PageContainer>
    <div class="flex items-start gap-3 mb-6">
      <button
        type="button"
        class="p-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 mt-0.5"
        @click="router.push('/mbkm/programs')"
      >
        <ArrowLeft class="w-4 h-4" />
      </button>
      <div class="flex-1 min-w-0">
        <h1 class="text-xl font-bold text-slate-900 tracking-tight truncate">
          {{ program?.name ?? 'Detail Program MBKM' }}
        </h1>
        <p class="text-xs text-slate-500 mt-1 flex items-center gap-2 flex-wrap">
          <span class="font-mono">{{ program?.code }}</span>
          <span>•</span>
          <span>{{ program?.program_type?.name ?? '-' }}</span>
          <MbkmStatusBadge
            v-if="program"
            :value="program.status"
            :labels="PROGRAM_STATUS_LABELS"
            :variants="PROGRAM_STATUS_VARIANTS"
          />
        </p>
      </div>
    </div>

    <div v-if="loading" class="py-12 text-center text-sm text-slate-400">Memuat detail program...</div>

    <template v-else-if="program">
      <Card class="border border-slate-200/80 shadow-2xs mb-5">
        <Tabs v-model="activeTab" :tabs="tabs" />
      </Card>

      <!-- Informasi -->
      <div v-if="activeTab === 'informasi'" class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <Card class="border border-slate-200/80 shadow-2xs lg:col-span-2">
          <template #header>
            <p class="font-bold text-slate-800 text-sm">Informasi Program</p>
          </template>
          <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3.5 text-xs">
            <div>
              <dt class="text-slate-500">Penyelenggara</dt>
              <dd class="font-medium text-slate-900 mt-0.5">
                {{ labelOf(ORGANIZER_TYPE_LABELS, program.organizer_type) }}
                <span v-if="program.organizer_name" class="text-slate-500"> — {{ program.organizer_name }}</span>
              </dd>
            </div>
            <div>
              <dt class="text-slate-500">Mode Lokasi</dt>
              <dd class="font-medium text-slate-900 mt-0.5">{{ labelOf(LOCATION_MODE_LABELS, program.location_mode) }}</dd>
            </div>
            <div>
              <dt class="text-slate-500">Fakultas</dt>
              <dd class="font-medium text-slate-900 mt-0.5">{{ program.faculty?.name ?? 'Semua Fakultas' }}</dd>
            </div>
            <div>
              <dt class="text-slate-500">Program Studi</dt>
              <dd class="font-medium text-slate-900 mt-0.5">{{ program.study_program?.name ?? 'Semua Prodi' }}</dd>
            </div>
            <div>
              <dt class="text-slate-500">Periode Akademik</dt>
              <dd class="font-medium text-slate-900 mt-0.5">{{ program.semester?.name ?? '-' }}</dd>
            </div>
            <div>
              <dt class="text-slate-500">Periode Logbook</dt>
              <dd class="font-medium text-slate-900 mt-0.5">{{ program.logbook_period }}</dd>
            </div>
            <div>
              <dt class="text-slate-500">Pendaftaran</dt>
              <dd class="font-medium text-slate-900 mt-0.5 flex items-center gap-1.5">
                <CalendarRange class="w-3.5 h-3.5 text-slate-400" />
                {{ formatDate(program.registration_start_date) }} – {{ formatDate(program.registration_end_date) }}
              </dd>
            </div>
            <div>
              <dt class="text-slate-500">Pelaksanaan</dt>
              <dd class="font-medium text-slate-900 mt-0.5 flex items-center gap-1.5">
                <CalendarRange class="w-3.5 h-3.5 text-slate-400" />
                {{ formatDate(program.start_date) }} – {{ formatDate(program.end_date) }}
              </dd>
            </div>
          </dl>

          <div v-if="program.description" class="mt-5 pt-4 border-t border-slate-100">
            <p class="text-slate-500 text-xs mb-1.5">Deskripsi</p>
            <p class="text-xs text-slate-800 leading-relaxed whitespace-pre-line">{{ program.description }}</p>
          </div>
        </Card>

        <div class="space-y-5">
          <Card class="border border-slate-200/80 shadow-2xs">
            <template #header>
              <p class="font-bold text-slate-800 text-sm">Kapasitas &amp; Kelayakan</p>
            </template>
            <dl class="space-y-2.5 text-xs">
              <div class="flex items-center justify-between">
                <dt class="text-slate-500 flex items-center gap-1.5"><Users class="w-3.5 h-3.5" /> Peserta / Kuota</dt>
                <dd class="font-semibold text-slate-900">
                  {{ program.quota_used ?? 0 }} / {{ program.quota ?? '∞' }}
                </dd>
              </div>
              <div class="flex items-center justify-between">
                <dt class="text-slate-500">Pendaftar</dt>
                <dd class="font-semibold text-slate-900">{{ program.applications_count ?? 0 }}</dd>
              </div>
              <div class="flex items-center justify-between">
                <dt class="text-slate-500">Semester</dt>
                <dd class="font-semibold text-slate-900">
                  {{ program.min_semester ?? '-' }} – {{ program.max_semester ?? '-' }}
                </dd>
              </div>
              <div class="flex items-center justify-between">
                <dt class="text-slate-500">IPK Minimum</dt>
                <dd class="font-semibold text-slate-900">{{ program.min_gpa ?? '-' }}</dd>
              </div>
              <div class="flex items-center justify-between">
                <dt class="text-slate-500">SKS Lulus Minimum</dt>
                <dd class="font-semibold text-slate-900">{{ program.min_credits ?? '-' }}</dd>
              </div>
              <div class="flex items-center justify-between">
                <dt class="text-slate-500">Maks SKS Diakui</dt>
                <dd class="font-semibold text-slate-900">{{ program.max_recognized_credits ?? '-' }}</dd>
              </div>
              <div class="flex items-center justify-between">
                <dt class="text-slate-500">Min % Kehadiran</dt>
                <dd class="font-semibold text-slate-900">{{ program.min_attendance_percentage ?? '-' }}</dd>
              </div>
            </dl>
          </Card>

          <Card class="border border-slate-200/80 shadow-2xs">
            <template #header>
              <p class="font-bold text-slate-800 text-sm">Tahapan Wajib</p>
            </template>
            <ul class="space-y-1.5 text-xs">
              <li v-for="flag in [
                ['Dokumen pendaftaran', program.requires_documents],
                ['Learning agreement', program.requires_learning_agreement],
                ['Presensi MBKM', program.requires_attendance],
                ['Logbook aktivitas', program.requires_logbook],
                ['Penilaian', program.requires_assessment],
                ['Laporan akhir', program.requires_final_report],
                ['Rekognisi SKS', program.requires_recognition],
              ]" :key="String(flag[0])" class="flex items-center justify-between">
                <span class="text-slate-600">{{ flag[0] }}</span>
                <span
                  :class="[
                    'px-1.5 py-0.5 rounded text-4xs font-bold border',
                    flag[1]
                      ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                      : 'bg-slate-50 text-slate-500 border-slate-200',
                  ]"
                >
                  {{ flag[1] ? 'WAJIB' : 'OPSIONAL' }}
                </span>
              </li>
            </ul>
          </Card>
        </div>
      </div>

      <!-- Lokasi -->
      <Card v-else-if="activeTab === 'lokasi'" class="border border-slate-200/80 shadow-2xs">
        <template #header>
          <p class="font-bold text-slate-800 text-sm">Lokasi Program</p>
          <Button variant="primary" size="sm" class="bg-brand-900 text-white" @click="showLocationModal = true">
            <Plus class="w-3.5 h-3.5 mr-1" /> Tambah Lokasi
          </Button>
        </template>

        <div v-if="(program.locations ?? []).length === 0" class="py-8 text-center text-xs text-slate-400">
          Belum ada lokasi. Satu program dapat memiliki banyak lokasi (dalam/luar kampus, dalam/luar negeri, daring, hybrid).
        </div>

        <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div
            v-for="loc in program.locations"
            :key="loc.id"
            class="p-3.5 border border-slate-200 rounded-lg hover:border-brand-300 transition-colors"
          >
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <p class="font-semibold text-slate-900 text-xs flex items-center gap-1.5">
                  <MapPin class="w-3.5 h-3.5 text-rose-500" />
                  {{ loc.name }}
                </p>
                <p class="text-2xs text-slate-500 mt-1">{{ labelOf(LOCATION_MODE_LABELS, loc.location_mode) }}</p>
                <p class="text-2xs text-slate-500 mt-1">
                  {{ [loc.city, loc.province, loc.country].filter(Boolean).join(', ') || 'Alamat belum diisi' }}
                </p>
              </div>
              <button
                type="button"
                class="p-1.5 rounded-lg border border-rose-200 text-rose-600 hover:bg-rose-50 shrink-0"
                @click="handleDeleteLocation(loc.id)"
              >
                <Trash2 class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>
        </div>
      </Card>

      <!-- Persyaratan -->
      <Card v-else-if="activeTab === 'persyaratan'" class="border border-slate-200/80 shadow-2xs">
        <template #header>
          <p class="font-bold text-slate-800 text-sm">Persyaratan Program</p>
          <Button variant="primary" size="sm" class="bg-brand-900 text-white" @click="showRequirementModal = true">
            <Plus class="w-3.5 h-3.5 mr-1" /> Tambah Persyaratan
          </Button>
        </template>

        <div v-if="(program.requirements ?? []).length === 0" class="py-8 text-center text-xs text-slate-400">
          Belum ada persyaratan khusus. Ambang IPK/SKS/semester di tab Informasi tetap diberlakukan.
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="bg-slate-50/80 text-slate-600 font-semibold border-b border-slate-200 text-3xs uppercase tracking-wider">
                <th class="py-2.5 px-3">NAMA</th>
                <th class="py-2.5 px-3">TIPE</th>
                <th class="py-2.5 px-3 text-center">WAJIB</th>
                <th class="py-2.5 px-3 text-center">DOKUMEN</th>
                <th class="py-2.5 px-3">ATURAN OTOMATIS</th>
                <th class="py-2.5 px-3 text-center w-16">AKSI</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="req in program.requirements" :key="req.id">
                <td class="py-3 px-3">
                  <p class="font-semibold text-slate-900">{{ req.name }}</p>
                  <p v-if="req.code" class="text-2xs text-slate-500 font-mono">{{ req.code }}</p>
                </td>
                <td class="py-3 px-3 text-slate-700">{{ labelOf(REQUIREMENT_TYPE_LABELS, req.type) }}</td>
                <td class="py-3 px-3 text-center">
                  <span :class="req.is_mandatory ? 'text-emerald-600 font-bold' : 'text-slate-400'">
                    {{ req.is_mandatory ? 'Ya' : 'Tidak' }}
                  </span>
                </td>
                <td class="py-3 px-3 text-center">
                  <span :class="req.is_document ? 'text-blue-600 font-bold' : 'text-slate-400'">
                    {{ req.is_document ? 'Ya' : 'Tidak' }}
                  </span>
                </td>
                <td class="py-3 px-3 text-slate-600 font-mono text-2xs">
                  <span v-if="req.rule">
                    {{ (req.rule as any).field }} {{ (req.rule as any).operator }} {{ (req.rule as any).value }}
                  </span>
                  <span v-else class="text-slate-400">—</span>
                </td>
                <td class="py-3 px-3 text-center">
                  <button
                    type="button"
                    class="p-1.5 rounded-lg border border-rose-200 text-rose-600 hover:bg-rose-50"
                    @click="handleDeleteRequirement(req.id)"
                  >
                    <Trash2 class="w-3.5 h-3.5" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </Card>

      <!-- Kriteria seleksi -->
      <Card v-else-if="activeTab === 'kriteria'" class="border border-slate-200/80 shadow-2xs">
        <template #header>
          <div>
            <p class="font-bold text-slate-800 text-sm">Kriteria Seleksi</p>
            <p class="text-2xs text-slate-500 mt-0.5">
              Bobot kriteria tidak dipaksakan — keputusan seleksi tetap manual oleh panitia.
            </p>
          </div>
          <div class="flex items-center gap-2">
            <span
              :class="[
                'text-2xs font-bold px-2 py-1 rounded border',
                Math.abs(criteriaTotal - 100) < 0.01
                  ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                  : 'bg-amber-50 text-amber-700 border-amber-200',
              ]"
            >
              Total bobot: {{ criteriaTotal }}%
            </span>
            <Button variant="outline" size="sm" @click="addCriteriaRow">
              <Plus class="w-3.5 h-3.5 mr-1" /> Baris
            </Button>
            <Button variant="primary" size="sm" class="bg-brand-900 text-white" :loading="saving" @click="saveCriteria">
              <Save class="w-3.5 h-3.5 mr-1" /> Simpan
            </Button>
          </div>
        </template>

        <div v-if="criteriaRows.length === 0" class="py-8 text-center text-xs text-slate-400">
          Belum ada kriteria seleksi.
        </div>

        <div v-else class="space-y-2.5">
          <div
            v-for="(row, index) in criteriaRows"
            :key="index"
            class="grid grid-cols-1 sm:grid-cols-12 gap-2.5 items-start p-3 border border-slate-200 rounded-lg"
          >
            <div class="sm:col-span-5">
              <label class="block text-2xs font-semibold text-slate-600 mb-1">Nama Kriteria</label>
              <Input v-model="row.name" placeholder="mis. Kesesuaian Bidang" />
            </div>
            <div class="sm:col-span-4">
              <label class="block text-2xs font-semibold text-slate-600 mb-1">Keterangan</label>
              <Input v-model="row.description" placeholder="Opsional" />
            </div>
            <div class="sm:col-span-1">
              <label class="block text-2xs font-semibold text-slate-600 mb-1">Bobot %</label>
              <Input v-model.number="row.weight" type="number" min="0" max="100" step="0.01" />
            </div>
            <div class="sm:col-span-1">
              <label class="block text-2xs font-semibold text-slate-600 mb-1">Maks</label>
              <Input v-model.number="row.max_score" type="number" min="1" />
            </div>
            <div class="sm:col-span-1 flex sm:pt-5">
              <button
                type="button"
                class="p-2 rounded-lg border border-rose-200 text-rose-600 hover:bg-rose-50 w-full flex justify-center"
                @click="criteriaRows.splice(index, 1)"
              >
                <Trash2 class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>
        </div>
      </Card>

      <!-- Komponen penilaian -->
      <Card v-else-if="activeTab === 'penilaian'" class="border border-slate-200/80 shadow-2xs">
        <template #header>
          <div>
            <p class="font-bold text-slate-800 text-sm">Komponen Penilaian</p>
            <p class="text-2xs text-slate-500 mt-0.5">
              Bobot per komponen dikonfigurasi per program. Total bobot harus tepat 100%.
            </p>
          </div>
          <div class="flex items-center gap-2">
            <span
              :class="[
                'text-2xs font-bold px-2 py-1 rounded border',
                Math.abs(componentTotal - 100) < 0.01
                  ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                  : 'bg-rose-50 text-rose-700 border-rose-200',
              ]"
            >
              Total bobot: {{ componentTotal }}%
            </span>
            <Button variant="outline" size="sm" @click="addComponentRow">
              <Plus class="w-3.5 h-3.5 mr-1" /> Baris
            </Button>
            <Button variant="primary" size="sm" class="bg-brand-900 text-white" :loading="saving" @click="saveComponents">
              <Save class="w-3.5 h-3.5 mr-1" /> Simpan
            </Button>
          </div>
        </template>

        <div v-if="componentRows.length === 0" class="py-8 text-center text-xs text-slate-400">
          Belum ada komponen penilaian. Program yang memerlukan penilaian harus memiliki komponen sebelum dipublikasikan.
        </div>

        <div v-else class="space-y-2.5">
          <div
            v-for="(row, index) in componentRows"
            :key="index"
            class="grid grid-cols-1 sm:grid-cols-12 gap-2.5 items-start p-3 border border-slate-200 rounded-lg"
          >
            <div class="sm:col-span-1">
              <label class="block text-2xs font-semibold text-slate-600 mb-1">Kode</label>
              <Input v-model="row.code" placeholder="PERF" />
            </div>
            <div class="sm:col-span-3">
              <label class="block text-2xs font-semibold text-slate-600 mb-1">Nama Komponen</label>
              <Input v-model="row.name" placeholder="mis. Kinerja" />
            </div>
            <div class="sm:col-span-2">
              <label class="block text-2xs font-semibold text-slate-600 mb-1">Tipe</label>
              <select
                v-model="row.type"
                class="w-full px-2.5 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none"
              >
                <option v-for="t in optionsFrom(COMPONENT_TYPE_LABELS)" :key="t.value" :value="t.value">{{ t.label }}</option>
              </select>
            </div>
            <div class="sm:col-span-2">
              <label class="block text-2xs font-semibold text-slate-600 mb-1">Penilai</label>
              <select
                v-model="row.assessor_type"
                class="w-full px-2.5 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none"
              >
                <option v-for="a in optionsFrom(ASSESSOR_TYPE_LABELS)" :key="a.value" :value="a.value">
                  {{ a.label }}
                </option>
              </select>
            </div>
            <div class="sm:col-span-1">
              <label class="block text-2xs font-semibold text-slate-600 mb-1">Bobot %</label>
              <Input v-model.number="row.weight" type="number" min="0" max="100" step="0.01" />
            </div>
            <div class="sm:col-span-1">
              <label class="block text-2xs font-semibold text-slate-600 mb-1">Maks</label>
              <Input v-model.number="row.max_score" type="number" min="1" />
            </div>
            <div class="sm:col-span-1 flex sm:pt-5">
              <button
                type="button"
                class="p-2 rounded-lg border border-rose-200 text-rose-600 hover:bg-rose-50 w-full flex justify-center"
                @click="componentRows.splice(index, 1)"
              >
                <Trash2 class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>
        </div>
      </Card>
    </template>

    <!-- Location modal -->
    <Modal v-model:open="showLocationModal" title="Tambah Lokasi Program" size="lg">
      <div class="space-y-4 text-xs">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Nama Lokasi <span class="text-rose-500">*</span></label>
            <Input v-model="locationForm.name" placeholder="mis. Kantor Pusat PT Contoh" />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Mode Lokasi</label>
            <select
              v-model="locationForm.location_mode"
              class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none"
            >
              <option v-for="o in optionsFrom(LOCATION_MODE_LABELS)" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
          </div>
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Alamat</label>
          <Textarea v-model="locationForm.address" :rows="2" />
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Kota</label>
            <Input v-model="locationForm.city" />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Provinsi</label>
            <Input v-model="locationForm.province" />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Negara</label>
            <Input v-model="locationForm.country" />
          </div>
        </div>
        <label class="flex items-center gap-2 cursor-pointer">
          <input v-model="locationForm.is_remote" type="checkbox" class="w-4 h-4 rounded border-slate-300 text-brand-900" />
          <span class="text-slate-800">Lokasi daring (remote)</span>
        </label>
      </div>
      <template #footer>
        <div class="flex items-center justify-end gap-2">
          <Button variant="outline" size="sm" @click="showLocationModal = false">Batal</Button>
          <Button variant="primary" size="sm" :loading="saving" class="bg-brand-900 text-white" @click="handleSaveLocation">
            Simpan Lokasi
          </Button>
        </div>
      </template>
    </Modal>

    <!-- Requirement modal -->
    <Modal v-model:open="showRequirementModal" title="Tambah Persyaratan Program" size="lg">
      <div class="space-y-4 text-xs">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Nama Persyaratan <span class="text-rose-500">*</span></label>
            <Input v-model="requirementForm.name" placeholder="mis. Kartu Tanda Mahasiswa" />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Kode</label>
            <Input v-model="requirementForm.code" placeholder="mis. KTM" />
          </div>
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Tipe</label>
          <select
            v-model="requirementForm.type"
            class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none"
          >
            <option v-for="o in optionsFrom(REQUIREMENT_TYPE_LABELS)" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Keterangan</label>
          <Textarea v-model="requirementForm.description" :rows="2" />
        </div>
        <div class="flex items-center gap-5">
          <label class="flex items-center gap-2 cursor-pointer">
            <input v-model="requirementForm.is_mandatory" type="checkbox" class="w-4 h-4 rounded border-slate-300 text-brand-900" />
            <span class="text-slate-800">Wajib</span>
          </label>
          <label class="flex items-center gap-2 cursor-pointer">
            <input v-model="requirementForm.is_document" type="checkbox" class="w-4 h-4 rounded border-slate-300 text-brand-900" />
            <span class="text-slate-800">Berupa dokumen yang harus diunggah</span>
          </label>
        </div>
        <div class="pt-3 border-t border-slate-100">
          <p class="font-semibold text-slate-700 mb-2">Aturan Otomatis (opsional)</p>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <select
              v-model="requirementForm.rule_field"
              class="px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none"
            >
              <option v-for="f in RULE_FIELDS" :key="f.value" :value="f.value">{{ f.label }}</option>
            </select>
            <select
              v-model="requirementForm.rule_operator"
              :disabled="!requirementForm.rule_field"
              class="px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none disabled:bg-slate-50"
            >
              <option v-for="op in RULE_OPERATORS" :key="op" :value="op">{{ op }}</option>
            </select>
            <Input v-model="requirementForm.rule_value" :disabled="!requirementForm.rule_field" placeholder="Nilai" />
          </div>
          <p class="mt-1.5 text-2xs text-slate-500">
            Aturan dievaluasi di server terhadap data akademik mahasiswa saat pendaftaran.
          </p>
        </div>
      </div>
      <template #footer>
        <div class="flex items-center justify-end gap-2">
          <Button variant="outline" size="sm" @click="showRequirementModal = false">Batal</Button>
          <Button variant="primary" size="sm" :loading="saving" class="bg-brand-900 text-white" @click="handleSaveRequirement">
            Simpan Persyaratan
          </Button>
        </div>
      </template>
    </Modal>
  </PageContainer>
</template>
