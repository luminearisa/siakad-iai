<script setup lang="ts">
import { ArrowLeft, GraduationCap, Calendar } from 'lucide-vue-next'
import type { StudentEnrollment } from '@/types/enrollment'
import Button from '@/components/ui/Button.vue'
import Avatar from '@/components/ui/Avatar.vue'
import EnrollmentStatusBadge from './EnrollmentStatusBadge.vue'

interface Props {
  enrollment: StudentEnrollment
}

defineProps<Props>()
</script>

<template>
  <div class="bg-white border border-slate-200 rounded-lg p-4 sm:p-5 shadow-subtle mb-5">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <!-- Left: Student & Semester Info -->
      <div class="flex items-start sm:items-center gap-3.5">
        <Avatar
          :name="enrollment.student?.full_name || 'Mahasiswa'"
          size="lg"
          class="shrink-0"
        />

        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-2 mb-1">
            <span class="font-mono font-bold text-xs text-brand-900 bg-brand-50 px-2 py-0.5 rounded border border-brand-200">
              NIM: {{ enrollment.student?.student_number || '-' }}
            </span>
            <EnrollmentStatusBadge :status="enrollment.status" size="xs" />
            <span class="font-mono text-2xs font-bold text-slate-700 bg-slate-100 px-2 py-0.5 rounded">
              {{ enrollment.total_credits || 0 }} SKS
            </span>
          </div>

          <h1 class="text-base sm:text-lg font-bold text-slate-900 tracking-tight truncate">
            {{ enrollment.student?.full_name || 'Kartu Rencana Studi' }}
          </h1>

          <div class="flex flex-wrap items-center gap-y-1 gap-x-2.5 text-xs text-slate-500 mt-0.5">
            <span v-if="enrollment.student?.study_program" class="inline-flex items-center gap-1 font-medium text-slate-700">
              <GraduationCap class="w-3.5 h-3.5 text-slate-400" />
              {{ enrollment.student.study_program.name }} ({{ enrollment.student.study_program.degree }})
            </span>
            <span>•</span>
            <span class="inline-flex items-center gap-1 text-slate-600">
              <Calendar class="w-3.5 h-3.5 text-slate-400" />
              {{ enrollment.semester?.name || 'Semester Aktif' }}
            </span>
          </div>
        </div>
      </div>

      <!-- Right: Action Back -->
      <div class="flex items-center gap-2 pt-3 md:pt-0 border-t md:border-t-0 border-slate-100 shrink-0">
        <router-link to="/enrollments">
          <Button variant="outline" size="sm">
            <ArrowLeft class="w-3.5 h-3.5" />
            <span>Daftar KRS</span>
          </Button>
        </router-link>
      </div>
    </div>
  </div>
</template>
