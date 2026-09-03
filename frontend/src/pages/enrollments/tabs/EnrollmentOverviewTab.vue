<script setup lang="ts">
import { computed } from 'vue'
import { Calendar, User } from 'lucide-vue-next'
import type { StudentEnrollment } from '@/types/enrollment'
import Card from '@/components/ui/Card.vue'
import EnrollmentStatusBadge from '../components/EnrollmentStatusBadge.vue'
import EnrollmentSummary from '../components/EnrollmentSummary.vue'
import { formatDate } from '@/utils/format'

interface Props {
  enrollment: StudentEnrollment
}

const props = defineProps<Props>()

const approverName = computed(() => {
  return props.enrollment.approver?.name || 'Dosen Pembimbing Akademik'
})
</script>

<template>
  <div class="space-y-5">
    <!-- Top Summary Cards -->
    <EnrollmentSummary :enrollment="enrollment" />

    <!-- Grid Detail -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
      <!-- 1. Identitas Mahasiswa & Program Studi -->
      <Card>
        <template #header>
          <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800 flex items-center gap-1.5">
            <User class="w-3.5 h-3.5 text-brand-900" />
            Identitas Mahasiswa
          </h3>
        </template>

        <dl class="divide-y divide-slate-100 text-xs">
          <div class="py-2.5 grid grid-cols-3 gap-2">
            <dt class="text-slate-500 font-medium">Nama Lengkap</dt>
            <dd class="col-span-2 font-bold text-slate-900">{{ enrollment.student?.full_name || '-' }}</dd>
          </div>

          <div class="py-2.5 grid grid-cols-3 gap-2">
            <dt class="text-slate-500 font-medium">Nomor Induk Mahasiswa (NIM)</dt>
            <dd class="col-span-2 font-mono font-semibold text-brand-900">{{ enrollment.student?.student_number || '-' }}</dd>
          </div>

          <div class="py-2.5 grid grid-cols-3 gap-2">
            <dt class="text-slate-500 font-medium">Program Studi</dt>
            <dd class="col-span-2 text-slate-800">
              {{ enrollment.student?.study_program?.name || '-' }} (Jenjang {{ enrollment.student?.study_program?.degree || 'S1' }})
            </dd>
          </div>

          <div class="py-2.5 grid grid-cols-3 gap-2">
            <dt class="text-slate-500 font-medium">Fakultas</dt>
            <dd class="col-span-2 text-slate-800">{{ enrollment.student?.study_program?.faculty?.name || '-' }}</dd>
          </div>

          <div class="py-2.5 grid grid-cols-3 gap-2">
            <dt class="text-slate-500 font-medium">Status Akademik</dt>
            <dd class="col-span-2">
              <span class="font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded text-2xs uppercase">
                {{ enrollment.student?.status || 'Active' }}
              </span>
            </dd>
          </div>
        </dl>
      </Card>

      <!-- 2. Periode & Status Rencana Studi -->
      <Card>
        <template #header>
          <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800 flex items-center gap-1.5">
            <Calendar class="w-3.5 h-3.5 text-brand-900" />
            Informasi Semester & Persetujuan
          </h3>
        </template>

        <dl class="divide-y divide-slate-100 text-xs">
          <div class="py-2.5 grid grid-cols-3 gap-2">
            <dt class="text-slate-500 font-medium">Semester Akademik</dt>
            <dd class="col-span-2 font-semibold text-slate-900">{{ enrollment.semester?.name || '-' }}</dd>
          </div>

          <div class="py-2.5 grid grid-cols-3 gap-2">
            <dt class="text-slate-500 font-medium">Tahun Akademik</dt>
            <dd class="col-span-2 text-slate-800">{{ enrollment.semester?.academic_year?.name || '-' }}</dd>
          </div>

          <div class="py-2.5 grid grid-cols-3 gap-2">
            <dt class="text-slate-500 font-medium">Status KRS</dt>
            <dd class="col-span-2">
              <EnrollmentStatusBadge :status="enrollment.status" size="xs" />
            </dd>
          </div>

          <div v-if="enrollment.submitted_at" class="py-2.5 grid grid-cols-3 gap-2">
            <dt class="text-slate-500 font-medium">Tanggal Pengajuan</dt>
            <dd class="col-span-2 font-mono text-slate-800">{{ formatDate(enrollment.submitted_at) }}</dd>
          </div>

          <div v-if="enrollment.approved_at" class="py-2.5 grid grid-cols-3 gap-2">
            <dt class="text-slate-500 font-medium">Tanggal Disetujui</dt>
            <dd class="col-span-2 font-mono text-slate-800">{{ formatDate(enrollment.approved_at) }}</dd>
          </div>

          <div v-if="enrollment.approved_by" class="py-2.5 grid grid-cols-3 gap-2">
            <dt class="text-slate-500 font-medium">Disetujui Oleh</dt>
            <dd class="col-span-2 font-medium text-slate-800">{{ approverName }}</dd>
          </div>

          <div v-if="enrollment.notes" class="py-2.5 grid grid-cols-3 gap-2">
            <dt class="text-slate-500 font-medium">Catatan Reviewer</dt>
            <dd class="col-span-2 text-amber-900 bg-amber-50 p-2 rounded border border-amber-200 whitespace-pre-line font-medium">
              {{ enrollment.notes }}
            </dd>
          </div>
        </dl>
      </Card>
    </div>
  </div>
</template>
