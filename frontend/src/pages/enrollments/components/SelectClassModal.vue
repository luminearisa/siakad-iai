<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { Search, Plus, Check, Clock, User, BookOpen, AlertCircle, ShieldAlert } from 'lucide-vue-next'
import { enrollmentService } from '@/services/api/enrollments'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import type { AvailableClass } from '@/types/enrollment'
import type { StudentEnrollment } from '@/types/enrollment'
import Modal from '@/components/ui/Modal.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'

interface Props {
  open: boolean
  enrollment: StudentEnrollment
}

const props = defineProps<Props>()

const emit = defineEmits<{
  (e: 'update:open', val: boolean): void
  (e: 'item-added'): void
}>()

const toast = useToast()
const authStore = useAuthStore()
const loading = ref<boolean>(false)
const addingId = ref<number | null>(null)
const classes = ref<AvailableClass[]>([])
const search = ref<string>('')
const activeTab = ref<'my_program' | 'all'>('my_program')

// Mahasiswa tidak boleh lintas prodi
const isStudent = authStore.isStudent
// Jika mahasiswa, selalu paksa tab my_program
const canViewAllPrograms = !isStudent

const studyProgramName = computed(() => {
  return props.enrollment.student?.study_program?.name || 'Program Studi'
})

const studentStudyProgramId = computed(() => props.enrollment.student?.study_program_id ?? null)

const enrolledClassIds = computed(() => {
  return (props.enrollment.items || []).map((item) => item.class_id || item.academic_class?.id)
})

/** A class is selectable when the backend flagged it eligible (defaults to true for legacy payloads). */
function isEligible(cls: AvailableClass): boolean {
  return cls.is_eligible !== false
}

function eligibilityReason(cls: AvailableClass): string {
  return cls.eligibility_reason || cls.eligibility_reasons?.[0] || 'Tidak memenuhi syarat pengambilan mata kuliah.'
}

const dayLabels: Record<string, string> = {
  monday: 'Senin',
  tuesday: 'Selasa',
  wednesday: 'Rabu',
  thursday: 'Kamis',
  friday: 'Jumat',
  saturday: 'Sabtu',
  sunday: 'Minggu',
}

function scheduleLabel(cls: AvailableClass): string {
  const schedule = cls.schedules?.[0]
  if (!schedule) return 'Jadwal menyusul'
  const day = dayLabels[String(schedule.day_of_week || '').toLowerCase()] || schedule.day_of_week || ''
  const start = schedule.start_time ? String(schedule.start_time).slice(0, 5) : ''
  const end = schedule.end_time ? String(schedule.end_time).slice(0, 5) : ''
  return `${day} ${start} - ${end}`.trim()
}

const filteredClasses = computed(() => {
  let list = classes.value

  // Tab "Prodi Saya" — general (university-wide) classes have no study program.
  if (activeTab.value === 'my_program' && studentStudyProgramId.value) {
    list = list.filter(
      (c) => !c.study_program_id || c.study_program_id === studentStudyProgramId.value
    )
  }

  if (!search.value.trim()) return list

  const q = search.value.toLowerCase()
  return list.filter(
    (c) =>
      c.course?.name?.toLowerCase().includes(q) ||
      c.course?.code?.toLowerCase().includes(q) ||
      c.code?.toLowerCase().includes(q) ||
      c.section?.toLowerCase().includes(q) ||
      c.lecturers?.some((l) => l.full_name?.toLowerCase().includes(q))
  )
})

async function loadClasses() {
  loading.value = true
  try {
    // The endpoint returns the catalog already annotated with eligibility, so the
    // UI never offers a class the API would reject with a 422 on submit.
    const res = await enrollmentService.availableClasses(props.enrollment.id)
    classes.value = res.data || []
  } catch (err: any) {
    classes.value = []
    toast.error(err.response?.data?.message || 'Gagal memuat daftar kelas perkuliahan')
  } finally {
    loading.value = false
  }
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      search.value = ''
      // Paksa tab my_program untuk mahasiswa
      activeTab.value = isStudent ? 'my_program' : activeTab.value
      loadClasses()
    }
  },
  { immediate: true }
)

watch(activeTab, () => {
  // Mahasiswa tidak boleh switch ke tab 'all'
  if (isStudent && activeTab.value === 'all') {
    activeTab.value = 'my_program'
  }
})

async function handleAddClass(cls: AvailableClass) {
  if (!isEligible(cls)) {
    toast.error(eligibilityReason(cls))
    return
  }

  const currentCredits = props.enrollment.total_credits || 0
  const maxCredits = props.enrollment.max_credits || 24
  const courseCredits = cls.course?.credits || 2

  if (currentCredits + courseCredits > maxCredits) {
    toast.error(`SKS melebihi batas maksimal (${currentCredits + courseCredits}/${maxCredits} SKS).`)
    return
  }

  addingId.value = cls.id
  try {
    await enrollmentService.addItem(props.enrollment.id, cls.id)
    toast.success(`Kelas ${cls.course?.name || cls.name} (${cls.section}) berhasil ditambahkan ke KRS!`)
    emit('item-added')
    // Refresh so the newly added class is flagged as already taken.
    await loadClasses()
  } catch (err: any) {
    const errorData = err.response?.data
    if (errorData?.errors) {
      const firstError = Object.values(errorData.errors).flat()[0] as string
      toast.error(firstError || errorData.message || 'Gagal menambahkan mata kuliah ke KRS')
    } else {
      toast.error(errorData?.message || err.message || 'Gagal menambahkan mata kuliah ke KRS')
    }
    // Eligibility may have changed (quota filled, schedule added, ...).
    loadClasses()
  } finally {
    addingId.value = null
  }
}
</script>

<template>
  <Modal
    :open="open"
    title="Pilih & Ambil Mata Kuliah (Kelas Perkuliahan)"
    size="lg"
    @update:open="emit('update:open', $event)"
  >
    <div class="space-y-4 text-xs">
      <!-- SKS Quota Indicator -->
      <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between">
        <div>
          <span class="text-3xs font-semibold text-slate-500 uppercase tracking-wider">Kuota SKS Mahasiswa</span>
          <div class="font-bold text-slate-900 text-sm">
            {{ enrollment.total_credits || 0 }} / {{ enrollment.max_credits || 24 }} SKS
          </div>
        </div>
        <div class="text-right">
          <span class="text-3xs font-semibold text-slate-500 uppercase tracking-wider">Sisa Kuota</span>
          <div class="font-bold text-emerald-700 text-sm">
            {{ Math.max(0, (enrollment.max_credits || 24) - (enrollment.total_credits || 0)) }} SKS
          </div>
        </div>
      </div>

      <!-- Tab Switcher: hanya tampilkan tab lintas prodi untuk admin/dosen -->
      <div class="flex items-center gap-1.5 p-1 bg-slate-100/90 rounded-xl border border-slate-200 text-xs">
        <button
          type="button"
          class="flex-1 py-1.5 px-3 rounded-lg font-semibold transition-all flex items-center justify-center gap-1.5"
          :class="activeTab === 'my_program' ? 'bg-white text-brand-700 shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
          @click="activeTab = 'my_program'"
        >
          <BookOpen class="w-3.5 h-3.5" />
          <span>Prodi Saya ({{ studyProgramName }})</span>
        </button>

        <button
          v-if="canViewAllPrograms"
          type="button"
          class="flex-1 py-1.5 px-3 rounded-lg font-semibold transition-all flex items-center justify-center gap-1.5"
          :class="activeTab === 'all' ? 'bg-white text-brand-700 shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
          @click="activeTab = 'all'"
        >
          <span>Semua Prodi / Lintas Jurusan</span>
        </button>

        <!-- Info untuk mahasiswa: tidak bisa lintas prodi tanpa izin -->
        <div
          v-else
          class="flex-1 py-1.5 px-3 text-center text-3xs text-slate-400 italic"
        >
          Lintas prodi: hubungi Dosen PA
        </div>
      </div>

      <!-- Search & Filter Controls -->
      <div class="flex flex-col sm:flex-row items-center gap-2">
        <div class="relative flex-1 w-full">
          <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <Input
            v-model="search"
            placeholder="Cari kode/nama mata kuliah atau dosen..."
            class="pl-9 w-full text-xs"
          />
        </div>

        <span class="text-3xs text-slate-500 whitespace-nowrap">
          {{ filteredClasses.filter(isEligible).length }} dari {{ filteredClasses.length }} kelas dapat diambil
        </span>
      </div>

      <!-- Classes List -->
      <div class="max-h-[380px] overflow-y-auto border border-slate-200 rounded-xl divide-y divide-slate-100">
        <div v-if="loading" class="p-8 text-center text-slate-400">
          Memuat kelas perkuliahan yang ditawarkan...
        </div>

        <div v-else-if="filteredClasses.length === 0" class="p-8 text-center text-slate-400 space-y-2">
          <AlertCircle class="w-6 h-6 mx-auto text-slate-400" />
          <p class="font-medium text-slate-700">
            {{ activeTab === 'my_program' ? `Belum ada kelas perkuliahan yang dibuka untuk ${studyProgramName}` : 'Tidak ada kelas perkuliahan yang ditemukan' }}
          </p>
          <p class="text-3xs text-slate-500 max-w-md mx-auto">
            {{ activeTab === 'my_program' ? 'Pastikan admin atau program studi telah membuka status kelas di menu Perkuliahan, atau beralih ke tab "Semua Prodi / Lintas Jurusan" jika mengambil mata kuliah pilihan.' : 'Pastikan admin/program studi telah membuka kelas perkuliahan untuk semester ini di menu Perkuliahan.' }}
          </p>
        </div>

        <div
          v-for="cls in filteredClasses"
          :key="cls.id"
          class="p-3.5 hover:bg-slate-50 flex items-center justify-between gap-3 transition-colors"
        >
          <div class="space-y-1 flex-1 min-w-0">
            <div class="flex items-center gap-2">
              <span class="font-mono text-3xs font-bold text-brand-700 bg-brand-50 border border-brand-200 px-1.5 py-0.5 rounded">
                {{ cls.course?.code || cls.code }}
              </span>
              <h4 class="font-bold text-slate-900 truncate">{{ cls.course?.name || cls.name }}</h4>
              <span class="px-2 py-0.5 rounded text-3xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                Kelas {{ cls.section }}
              </span>
              <span
                class="px-1.5 py-0.5 rounded text-3xs font-semibold"
                :class="cls.status === 'open' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200'"
              >
                {{ cls.status === 'open' ? 'Dibuka' : 'Draft / Belum Buka' }}
              </span>
            </div>

            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-2xs text-slate-500">
              <span class="inline-flex items-center gap-1 font-semibold text-slate-700">
                <BookOpen class="w-3 h-3 text-slate-400" />
                {{ cls.course?.credits || 2 }} SKS
              </span>
              <span>•</span>
              <span v-if="cls.lecturers && cls.lecturers.length > 0" class="inline-flex items-center gap-1">
                <User class="w-3 h-3 text-slate-400" />
                {{ cls.lecturers.map(l => l.full_name).join(', ') }}
              </span>
              <span v-else class="text-slate-400">Dosen belum diplot</span>
              <span>•</span>
              <span class="inline-flex items-center gap-1">
                <Clock class="w-3 h-3 text-slate-400" />
                {{ scheduleLabel(cls) }}
              </span>
            </div>

            <!-- Reason why the class cannot be taken -->
            <div
              v-if="!isEligible(cls) && !enrolledClassIds.includes(cls.id)"
              class="flex items-start gap-1 text-2xs text-rose-600 bg-rose-50 border border-rose-100 rounded-lg px-2 py-1"
            >
              <ShieldAlert class="w-3 h-3 shrink-0 mt-0.5" />
              <span>{{ eligibilityReason(cls) }}</span>
            </div>
          </div>

          <div class="shrink-0">
            <!-- Already Enrolled Badge -->
            <button
              v-if="enrolledClassIds.includes(cls.id)"
              type="button"
              disabled
              class="inline-flex items-center gap-1 px-3 py-1.5 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-lg text-2xs font-semibold"
            >
              <Check class="w-3.5 h-3.5" />
              Sudah Diambil
            </button>

            <!-- Disabled if class status is not open -->
            <button
              v-else-if="cls.status !== 'open'"
              type="button"
              disabled
              title="Kelas belum dibuka oleh admin di menu Perkuliahan"
              class="inline-flex items-center gap-1 px-3 py-1.5 bg-slate-100 text-slate-400 border border-slate-200 rounded-lg text-2xs font-medium cursor-not-allowed"
            >
              Kelas Belum Dibuka
            </button>

            <!-- Disabled when the class fails a KRS rule (curriculum, quota, schedule, ...) -->
            <button
              v-else-if="!isEligible(cls)"
              type="button"
              disabled
              :title="eligibilityReason(cls)"
              class="inline-flex items-center gap-1 px-3 py-1.5 bg-rose-50 text-rose-600 border border-rose-200 rounded-lg text-2xs font-semibold cursor-not-allowed"
            >
              <ShieldAlert class="w-3.5 h-3.5" />
              Tidak Memenuhi Syarat
            </button>

            <!-- Add Button -->
            <Button
              v-else
              variant="primary"
              size="sm"
              :loading="addingId === cls.id"
              class="bg-brand-700 hover:bg-brand-800 text-white text-2xs font-semibold gap-1 px-3"
              @click="handleAddClass(cls)"
            >
              <Plus class="w-3.5 h-3.5" />
              Ambil Kelas
            </Button>
          </div>
        </div>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end">
        <Button
          variant="outline"
          size="sm"
          @click="emit('update:open', false)"
        >
          Selesai
        </Button>
      </div>
    </template>
  </Modal>
</template>
