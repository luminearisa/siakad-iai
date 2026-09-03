<script setup lang="ts">
import type { Student } from '@/types/student'
import Card from '@/components/ui/Card.vue'
import StudentStatusBadge from '../components/StudentStatusBadge.vue'
import { formatDate } from '@/utils/format'

interface Props {
  student: Student
}

defineProps<Props>()
</script>

<template>
  <div class="space-y-5">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
      <!-- Struktur Akademik & Program Studi -->
      <Card>
        <template #header>
          <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
            Program Studi & Homebase
          </h3>
        </template>

        <dl class="divide-y divide-slate-100 text-xs">
          <div class="py-2.5 grid grid-cols-3 gap-2">
            <dt class="text-slate-500 font-medium">Program Studi</dt>
            <dd class="col-span-2 font-bold text-slate-900">
              {{ student.study_program?.name || '-' }} ({{ student.study_program?.code || '-' }})
            </dd>
          </div>

          <div class="py-2.5 grid grid-cols-3 gap-2">
            <dt class="text-slate-500 font-medium">Jenjang Pendidikan</dt>
            <dd class="col-span-2 text-slate-800 font-medium">{{ student.study_program?.degree || '-' }}</dd>
          </div>

          <div class="py-2.5 grid grid-cols-3 gap-2">
            <dt class="text-slate-500 font-medium">Fakultas</dt>
            <dd class="col-span-2 text-slate-800">{{ student.study_program?.faculty?.name || '-' }}</dd>
          </div>
        </dl>
      </Card>

      <!-- Status Pendaftaran & Riwayat -->
      <Card>
        <template #header>
          <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
            Penerimaan & Status Akademik
          </h3>
        </template>

        <dl class="divide-y divide-slate-100 text-xs">
          <div class="py-2.5 grid grid-cols-3 gap-2">
            <dt class="text-slate-500 font-medium">Status Akademik</dt>
            <dd class="col-span-2">
              <StudentStatusBadge :status="student.status" size="xs" />
            </dd>
          </div>

          <div class="py-2.5 grid grid-cols-3 gap-2">
            <dt class="text-slate-500 font-medium">Tahun Angkatan</dt>
            <dd class="col-span-2 font-medium text-slate-900">{{ student.admission_year || '-' }}</dd>
          </div>

          <div class="py-2.5 grid grid-cols-3 gap-2">
            <dt class="text-slate-500 font-medium">Tanggal Terdaftar</dt>
            <dd class="col-span-2 text-slate-800">{{ formatDate(student.entry_date) }}</dd>
          </div>

          <div class="py-2.5 grid grid-cols-3 gap-2">
            <dt class="text-slate-500 font-medium">Tanggal Kelulusan</dt>
            <dd class="col-span-2 text-slate-800">{{ formatDate(student.graduation_date) }}</dd>
          </div>
        </dl>
      </Card>
    </div>
  </div>
</template>
