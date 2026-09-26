<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { SlidersHorizontal } from 'lucide-vue-next'
import { academicService } from '@/services/api/academic'
import { lecturerService } from '@/services/api/lecturers'
import { mbkmService } from '@/services/api/mbkm'
import type { Semester } from '@/types/academic'
import type { MbkmPartner, MbkmProgram, MbkmProgramType } from '@/types/mbkm'
import Button from '@/components/ui/Button.vue'

export interface MbkmFilters {
  semester_id?: number | null
  faculty_id?: number | null
  study_program_id?: number | null
  program_id?: number | null
  program_type_id?: number | null
  partner_id?: number | null
  status?: string
  admission_year?: number | null
  lecturer_id?: number | null
}

interface Props {
  modelValue: MbkmFilters
  /** Which selects to render. */
  fields?: string[]
  /** Options for the status select; omit to hide it. */
  statusOptions?: Array<{ value: string; label: string }>
}

const props = withDefaults(defineProps<Props>(), {
  fields: () => ['semester_id', 'study_program_id', 'program_id', 'program_type_id', 'partner_id', 'status'],
  statusOptions: () => [],
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: MbkmFilters): void
  (e: 'change'): void
}>()

const open = ref(false)
const semesters = ref<Semester[]>([])
const studyPrograms = ref<Array<{ id: number; name: string }>>([])
const faculties = ref<Array<{ id: number; name: string }>>([])
const programs = ref<MbkmProgram[]>([])
const programTypes = ref<MbkmProgramType[]>([])
const partners = ref<MbkmPartner[]>([])
const lecturers = ref<Array<{ id: number; full_name: string }>>([])

function has(field: string) {
  return props.fields.includes(field)
}

function update(field: keyof MbkmFilters, value: unknown) {
  emit('update:modelValue', { ...props.modelValue, [field]: value })
  emit('change')
}

function reset() {
  const cleared: MbkmFilters = {}
  props.fields.forEach((f) => ((cleared as Record<string, unknown>)[f] = null))
  emit('update:modelValue', cleared)
  emit('change')
}

const activeCount = ref(0)

function recount() {
  activeCount.value = Object.values(props.modelValue).filter(
    (v) => v !== null && v !== undefined && v !== ''
  ).length
}

watch(() => props.modelValue, recount, { deep: true })

onMounted(async () => {
  recount()
  try {
    const [semRes, spRes, facRes, progRes, typeRes, partnerRes, lecRes] = await Promise.allSettled([
      academicService.getSemesters(),
      academicService.getStudyPrograms(),
      academicService.getFaculties(),
      mbkmService.getPrograms({ per_page: 200 }),
      mbkmService.getProgramTypes({ per_page: 100 }),
      mbkmService.getPartners({ per_page: 200 }),
      props.fields.includes('lecturer_id')
        ? lecturerService.list({ per_page: 300 } as never)
        : Promise.resolve({ data: [] }),
    ])

    if (semRes.status === 'fulfilled') semesters.value = semRes.value.data || []
    if (spRes.status === 'fulfilled') studyPrograms.value = (spRes.value.data || []) as any
    if (facRes.status === 'fulfilled') faculties.value = (facRes.value.data || []) as any
    if (progRes.status === 'fulfilled') programs.value = progRes.value.data || []
    if (typeRes.status === 'fulfilled') programTypes.value = typeRes.value.data || []
    if (partnerRes.status === 'fulfilled') partners.value = partnerRes.value.data || []
    if (lecRes.status === 'fulfilled') lecturers.value = (lecRes.value.data || []) as any
  } catch {
    // Filters degrade gracefully — the page still loads without them.
  }
})
</script>

<template>
  <div>
    <div class="flex items-center gap-2">
      <Button
        variant="outline"
        size="sm"
        class="border-slate-300 text-slate-700 hover:bg-slate-50 flex items-center gap-1.5 font-medium relative"
        @click="open = !open"
      >
        <SlidersHorizontal class="w-3.5 h-3.5 text-rose-600" />
        <span>Filter</span>
        <span
          v-if="activeCount > 0"
          class="ml-0.5 px-1.5 py-0.2 rounded-full bg-rose-500 text-white text-4xs font-bold"
        >
          {{ activeCount }}
        </span>
      </Button>
      <button
        v-if="activeCount > 0"
        type="button"
        class="text-2xs text-slate-500 hover:text-rose-600 font-medium"
        @click="reset"
      >
        Reset
      </button>
    </div>

    <div
      v-if="open"
      class="mt-3 p-4 bg-slate-50 border border-slate-200 rounded-lg grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs animate-fade-in"
    >
      <div v-if="has('semester_id')">
        <label class="block font-semibold text-slate-700 mb-1">Periode Akademik</label>
        <select
          :value="props.modelValue.semester_id ?? ''"
          class="w-full px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg text-xs outline-none focus:ring-1 focus:ring-brand-500"
          @change="update('semester_id', ($event.target as HTMLSelectElement).value ? Number(($event.target as HTMLSelectElement).value) : null)"
        >
          <option value="">Semua Periode</option>
          <option v-for="s in semesters" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
      </div>

      <div v-if="has('faculty_id')">
        <label class="block font-semibold text-slate-700 mb-1">Fakultas</label>
        <select
          :value="props.modelValue.faculty_id ?? ''"
          class="w-full px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg text-xs outline-none"
          @change="update('faculty_id', ($event.target as HTMLSelectElement).value ? Number(($event.target as HTMLSelectElement).value) : null)"
        >
          <option value="">Semua Fakultas</option>
          <option v-for="f in faculties" :key="f.id" :value="f.id">{{ f.name }}</option>
        </select>
      </div>

      <div v-if="has('study_program_id')">
        <label class="block font-semibold text-slate-700 mb-1">Program Studi</label>
        <select
          :value="props.modelValue.study_program_id ?? ''"
          class="w-full px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg text-xs outline-none"
          @change="update('study_program_id', ($event.target as HTMLSelectElement).value ? Number(($event.target as HTMLSelectElement).value) : null)"
        >
          <option value="">Semua Prodi</option>
          <option v-for="p in studyPrograms" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
      </div>

      <div v-if="has('program_id')">
        <label class="block font-semibold text-slate-700 mb-1">Program MBKM</label>
        <select
          :value="props.modelValue.program_id ?? ''"
          class="w-full px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg text-xs outline-none"
          @change="update('program_id', ($event.target as HTMLSelectElement).value ? Number(($event.target as HTMLSelectElement).value) : null)"
        >
          <option value="">Semua Program</option>
          <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
      </div>

      <div v-if="has('program_type_id')">
        <label class="block font-semibold text-slate-700 mb-1">Jenis Program</label>
        <select
          :value="props.modelValue.program_type_id ?? ''"
          class="w-full px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg text-xs outline-none"
          @change="update('program_type_id', ($event.target as HTMLSelectElement).value ? Number(($event.target as HTMLSelectElement).value) : null)"
        >
          <option value="">Semua Jenis</option>
          <option v-for="t in programTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
        </select>
      </div>

      <div v-if="has('partner_id')">
        <label class="block font-semibold text-slate-700 mb-1">Mitra</label>
        <select
          :value="props.modelValue.partner_id ?? ''"
          class="w-full px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg text-xs outline-none"
          @change="update('partner_id', ($event.target as HTMLSelectElement).value ? Number(($event.target as HTMLSelectElement).value) : null)"
        >
          <option value="">Semua Mitra</option>
          <option v-for="p in partners" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
      </div>

      <div v-if="has('status') && props.statusOptions.length > 0">
        <label class="block font-semibold text-slate-700 mb-1">Status</label>
        <select
          :value="props.modelValue.status ?? ''"
          class="w-full px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg text-xs outline-none"
          @change="update('status', ($event.target as HTMLSelectElement).value || null)"
        >
          <option value="">Semua Status</option>
          <option v-for="o in props.statusOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
        </select>
      </div>

      <div v-if="has('admission_year')">
        <label class="block font-semibold text-slate-700 mb-1">Angkatan</label>
        <input
          :value="props.modelValue.admission_year ?? ''"
          type="number"
          placeholder="mis. 2023"
          class="w-full px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg text-xs outline-none"
          @change="update('admission_year', ($event.target as HTMLInputElement).value ? Number(($event.target as HTMLInputElement).value) : null)"
        />
      </div>

      <div v-if="has('lecturer_id')">
        <label class="block font-semibold text-slate-700 mb-1">Dosen Pembimbing</label>
        <select
          :value="props.modelValue.lecturer_id ?? ''"
          class="w-full px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg text-xs outline-none"
          @change="update('lecturer_id', ($event.target as HTMLSelectElement).value ? Number(($event.target as HTMLSelectElement).value) : null)"
        >
          <option value="">Semua Dosen</option>
          <option v-for="l in lecturers" :key="l.id" :value="l.id">{{ l.full_name }}</option>
        </select>
      </div>
    </div>
  </div>
</template>
