<script setup lang="ts">
import { ref, onMounted } from 'vue'
import {
  QrCode,
  BookOpen,
  ChevronDown,
  ChevronUp,
  Award,
} from 'lucide-vue-next'
import { attendanceService } from '@/services/api/attendance'
import { useToast } from '@/composables/useToast'
import type { StudentAttendanceRecap } from '@/types/attendance'
import PageContainer from '@/components/data-display/PageContainer.vue'
import PageHeader from '@/components/data-display/PageHeader.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Badge from '@/components/ui/Badge.vue'
import AttendanceStatusBadge from './components/AttendanceStatusBadge.vue'
import ExamEligibilityBadge from './components/ExamEligibilityBadge.vue'
import StudentSelfCheckInModal from './components/StudentSelfCheckInModal.vue'

const toast = useToast()
const recap = ref<StudentAttendanceRecap | null>(null)
const loading = ref<boolean>(false)
const expandedClasses = ref<Record<number, boolean>>({})

// Self check-in modal
const selfCheckInModalOpen = ref<boolean>(false)
const activeSessionForCheckIn = ref<number | null>(null)

async function loadMyAttendance() {
  loading.value = true
  try {
    const res = await attendanceService.getMyAttendance()
    recap.value = res.data || null

    // Expand first class by default
    if (recap.value && recap.value.classes.length > 0) {
      expandedClasses.value[recap.value.classes[0].class_id] = true
    }
  } catch (err: any) {
    toast.error(err.message || 'Gagal memuat rekapitulasi kehadiran mahasiswa.')
  } finally {
    loading.value = false
  }
}

function toggleExpand(classId: number) {
  expandedClasses.value[classId] = !expandedClasses.value[classId]
}

function openSelfCheckIn(sessionId?: number) {
  activeSessionForCheckIn.value = sessionId || null
  selfCheckInModalOpen.value = true
}

onMounted(() => {
  loadMyAttendance()
})
</script>

<template>
  <PageContainer>
    <!-- Header -->
    <PageHeader
      title="Rekapitulasi Kehadiran Saya"
      subtitle="Pantau persentase kehadiran seluruh mata kuliah semester aktif, riwayat pertemuan perkuliahan, dan status kelayakan Ujian Akhir Semester (UAS)"
      :breadcrumbs="[
        { label: 'Dashboard', to: '/dashboard' },
        { label: 'Kehadiran Saya' },
      ]"
    >
      <template #actions>
        <Button
          variant="primary"
          size="md"
          @click="openSelfCheckIn()"
        >
          <QrCode class="w-4 h-4" />
          <span>Presensi Mandiri (Input Token)</span>
        </Button>
      </template>
    </PageHeader>

    <div class="space-y-5">
      <!-- 1. Metric Summary Cards -->
      <div v-if="recap" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <!-- Overall Percentage -->
        <div class="col-span-2 sm:col-span-1 lg:col-span-2 bg-gradient-to-br from-brand-900 via-brand-800 to-indigo-950 text-white rounded-xl p-4 shadow-sm border border-brand-700">
          <div class="flex items-center justify-between">
            <span class="text-2xs font-semibold uppercase tracking-wider text-brand-200">Rata-rata Kehadiran</span>
            <Award class="w-4 h-4 text-emerald-400" />
          </div>
          <div class="mt-2 flex items-baseline gap-2">
            <span class="text-3xl font-extrabold font-mono">{{ recap.summary.overall_percentage }}%</span>
            <Badge :variant="recap.summary.is_eligible_overall ? 'success' : 'danger'" size="sm">
              {{ recap.summary.is_eligible_overall ? 'Layak UAS (≥75%)' : 'Perhatian (<75%)' }}
            </Badge>
          </div>
          <p class="text-2xs text-brand-200 mt-1">Total {{ recap.summary.total_classes }} Kelas Mata Kuliah</p>
        </div>

        <!-- Total Sessions -->
        <div class="bg-white border border-slate-200 rounded-xl p-3.5 shadow-subtle">
          <span class="text-2xs font-bold text-slate-400 uppercase tracking-wider block">Total Pertemuan</span>
          <span class="text-xl font-mono font-extrabold text-slate-900 mt-1 block">{{ recap.summary.total_sessions }}</span>
          <span class="text-2xs text-slate-400 mt-0.5 block">Sesi Terlaksana</span>
        </div>

        <!-- Present (H) -->
        <div class="bg-white border border-slate-200 rounded-xl p-3.5 shadow-subtle">
          <span class="text-2xs font-bold text-emerald-600 uppercase tracking-wider block">Hadir (H)</span>
          <span class="text-xl font-mono font-extrabold text-emerald-700 mt-1 block">{{ recap.summary.total_present }}</span>
          <span class="text-2xs text-slate-400 mt-0.5 block">Pertemuan</span>
        </div>

        <!-- Permit & Sick (I/S) -->
        <div class="bg-white border border-slate-200 rounded-xl p-3.5 shadow-subtle">
          <span class="text-2xs font-bold text-blue-600 uppercase tracking-wider block">Izin / Sakit</span>
          <span class="text-xl font-mono font-extrabold text-blue-700 mt-1 block">{{ recap.summary.total_permit + recap.summary.total_sick }}</span>
          <span class="text-2xs text-slate-400 mt-0.5 block">I: {{ recap.summary.total_permit }} · S: {{ recap.summary.total_sick }}</span>
        </div>

        <!-- Absent (A) -->
        <div class="bg-white border border-slate-200 rounded-xl p-3.5 shadow-subtle">
          <span class="text-2xs font-bold text-rose-600 uppercase tracking-wider block">Alpa (A)</span>
          <span class="text-xl font-mono font-extrabold text-rose-700 mt-1 block">{{ recap.summary.total_absent }}</span>
          <span class="text-2xs text-slate-400 mt-0.5 block">Tanpa Keterangan</span>
        </div>
      </div>

      <!-- 2. Course Attendance Cards & Breakdown -->
      <div v-if="loading" class="text-center py-12 bg-white rounded-xl border border-slate-200">
        <span class="text-xs text-slate-400">Memuat data kehadiran...</span>
      </div>

      <div v-else-if="!recap || recap.classes.length === 0" class="text-center py-12 bg-white rounded-xl border border-slate-200">
        <BookOpen class="w-8 h-8 text-slate-300 mx-auto mb-2" />
        <h4 class="font-bold text-sm text-slate-800">Belum Ada Perkuliahan Terdaftar</h4>
        <p class="text-xs text-slate-500 mt-1">Data presensi akan tampil setelah KRS Anda disetujui dan dosen memulai pertemuan kelas.</p>
      </div>

      <div v-else class="space-y-4">
        <Card
          v-for="c in recap.classes"
          :key="c.class_id"
          class="overflow-hidden"
        >
          <!-- Card Header & Progress Bar -->
          <div class="p-4 bg-white hover:bg-slate-50/40 transition-colors cursor-pointer" @click="toggleExpand(c.class_id)">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
              <div class="space-y-1">
                <div class="flex items-center gap-2">
                  <span class="font-mono font-extrabold text-xs text-brand-900 bg-brand-50 px-2 py-0.5 rounded border border-brand-200">
                    {{ c.course_code }}
                  </span>
                  <h3 class="font-bold text-sm text-slate-900">
                    {{ c.course_name }} (Kelas {{ c.section }})
                  </h3>
                </div>

                <p class="text-2xs text-slate-500">
                  Pengajar: <strong>{{ c.lecturers?.map(l => l.full_name).join(', ') || 'Dosen Pengampu' }}</strong> · {{ c.credits || 0 }} SKS
                </p>
              </div>

              <!-- Percentage & Progress -->
              <div class="flex items-center gap-4">
                <div class="w-40 sm:w-56 text-right">
                  <div class="flex items-center justify-between text-2xs mb-1">
                    <span class="text-slate-400">Kehadiran ({{ c.present_count + c.permit_count + c.sick_count }}/{{ c.total_sessions }} Sesi)</span>
                    <span class="font-mono font-bold" :class="c.is_eligible ? 'text-emerald-700' : 'text-rose-700'">
                      {{ c.percentage }}%
                    </span>
                  </div>
                  <!-- Progress Bar -->
                  <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                    <div
                      class="h-2 rounded-full transition-all duration-300"
                      :class="c.percentage >= 75 ? 'bg-emerald-500' : (c.percentage >= 50 ? 'bg-amber-500' : 'bg-rose-500')"
                      :style="{ width: `${Math.min(c.percentage, 100)}%` }"
                    />
                  </div>
                </div>

                <ExamEligibilityBadge :eligible="c.is_eligible" size="md" />

                <button type="button" class="text-slate-400 hover:text-slate-600 p-1">
                  <ChevronUp v-if="expandedClasses[c.class_id]" class="w-4 h-4" />
                  <ChevronDown v-else class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>

          <!-- Expanded Meeting Details -->
          <div v-if="expandedClasses[c.class_id]" class="border-t border-slate-100 bg-slate-50/50 p-4">
            <h4 class="text-2xs font-bold uppercase tracking-wider text-slate-500 mb-3">
              Riwayat Pertemuan & Presensi:
            </h4>

            <div v-if="c.meetings.length === 0" class="text-center py-4 text-xs text-slate-400 italic">
              Belum ada catatan pertemuan yang dibuka untuk kelas ini.
            </div>

            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5">
              <div
                v-for="m in c.meetings"
                :key="m.session_id"
                class="bg-white border rounded-lg p-3 space-y-2 shadow-2xs"
                :class="m.status === 'present' ? 'border-emerald-200' : (m.status === 'absent' ? 'border-rose-200' : 'border-slate-200')"
              >
                <div class="flex items-center justify-between text-2xs">
                  <span class="font-mono font-bold text-slate-800 bg-slate-100 px-1.5 py-0.5 rounded">
                    Pertemuan {{ m.meeting_number }}
                  </span>
                  <AttendanceStatusBadge :status="m.status" size="sm" show-code />
                </div>

                <div>
                  <h5 class="font-bold text-xs text-slate-900 line-clamp-1">
                    {{ m.topic || 'Materi Perkuliahan' }}
                  </h5>
                  <span class="text-2xs text-slate-400 font-mono block mt-0.5">
                    {{ m.session_date }} · {{ m.start_time || '08:00' }}
                  </span>
                </div>

                <!-- Self check-in active button if available -->
                <div v-if="m.is_check_in_active" class="pt-1">
                  <Button
                    variant="primary"
                    size="xs"
                    class="w-full justify-center"
                    @click.stop="openSelfCheckIn(m.session_id)"
                  >
                    <QrCode class="w-3 h-3 mr-1" />
                    <span>Presensi Sekarang</span>
                  </Button>
                </div>
              </div>
            </div>
          </div>
        </Card>
      </div>
    </div>

    <!-- Student Self Check-In Modal -->
    <StudentSelfCheckInModal
      :open="selfCheckInModalOpen"
      :default-session-id="activeSessionForCheckIn"
      @update:open="selfCheckInModalOpen = $event"
      @success="loadMyAttendance"
    />
  </PageContainer>
</template>
