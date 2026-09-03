<script setup lang="ts">
import { useRouter } from 'vue-router'
import { CheckCircle2, RotateCcw, XCircle, Eye, Clock, Building2 } from 'lucide-vue-next'
import type { StudentEnrollment } from '@/types/enrollment'
import Button from '@/components/ui/Button.vue'
import Avatar from '@/components/ui/Avatar.vue'
import EnrollmentStatusBadge from '@/pages/enrollments/components/EnrollmentStatusBadge.vue'
import { usePermissions } from '@/composables/usePermissions'

defineProps<{
  enrollment: StudentEnrollment
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'approve', enrollment: StudentEnrollment): void
  (e: 'request-revision', enrollment: StudentEnrollment): void
  (e: 'reject', enrollment: StudentEnrollment): void
  (e: 'view-detail', enrollment: StudentEnrollment): void
}>()

const router = useRouter()
const { can } = usePermissions()
</script>

<template>
  <div class="bg-white border border-slate-200 rounded-lg p-4 shadow-subtle space-y-3.5 hover:border-slate-300 transition-colors">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 pb-2.5 border-b border-slate-100">
      <div class="flex items-center gap-2.5 min-w-0">
        <Avatar
          :name="enrollment.student?.full_name || 'M'"
          :src="enrollment.student?.photo_path || undefined"
          size="sm"
          class="shrink-0"
        />
        <div class="min-w-0">
          <div class="flex items-center gap-2">
            <span class="font-bold text-slate-900 text-xs truncate">
              {{ enrollment.student?.full_name }}
            </span>
            <span class="text-2xs font-mono text-slate-500">
              ({{ enrollment.student?.student_number }})
            </span>
          </div>
          <span class="text-2xs text-slate-500 block truncate">
            {{ enrollment.student?.study_program?.name || '-' }} · {{ enrollment.semester?.name }}
          </span>
        </div>
      </div>

      <div class="flex items-center gap-2 self-end sm:self-center">
        <EnrollmentStatusBadge :status="enrollment.status" />
        <span class="px-2 py-0.5 rounded-full text-2xs font-bold bg-brand-50 text-brand-900 border border-brand-200">
          {{ enrollment.total_credits }} SKS
        </span>
      </div>
    </div>

    <!-- Enrolled Courses Summary List -->
    <div class="space-y-1.5 text-xs">
      <div class="flex items-center justify-between text-2xs font-semibold uppercase tracking-wider text-slate-400">
        <span>Mata Kuliah Terdaftar ({{ enrollment.items?.length || enrollment.items_count || 0 }} Kelas)</span>
        <span>SKS</span>
      </div>

      <div
        v-if="!enrollment.items || enrollment.items.length === 0"
        class="text-2xs text-slate-400 italic py-1"
      >
        Belum ada mata kuliah yang dipilih.
      </div>

      <div
        v-for="item in enrollment.items"
        :key="item.id"
        class="flex items-center justify-between py-1.5 px-2 rounded bg-slate-50 border border-slate-100 text-xs"
      >
        <div class="min-w-0 pr-2">
          <div class="flex items-center gap-1.5">
            <span class="font-mono text-2xs font-bold text-brand-900">
              {{ item.academic_class?.course?.code || item.course?.code }}
            </span>
            <span class="font-medium text-slate-800 truncate">
              {{ item.academic_class?.course?.name || item.course?.name }}
            </span>
            <span class="text-2xs font-semibold px-1 rounded bg-slate-200 text-slate-700">
              Kls {{ item.academic_class?.section || 'A' }}
            </span>
          </div>
          <div v-if="item.academic_class?.schedules?.[0]" class="text-2xs text-slate-500 flex items-center gap-2 mt-0.5">
            <span class="inline-flex items-center gap-0.5">
              <Clock class="w-3 h-3 text-slate-400" />
              <span class="capitalize">{{ item.academic_class.schedules[0].day_of_week }}</span> ({{ item.academic_class.schedules[0].start_time }} - {{ item.academic_class.schedules[0].end_time }})
            </span>
            <span v-if="item.academic_class.schedules[0].room" class="inline-flex items-center gap-0.5">
              <Building2 class="w-3 h-3 text-slate-400" />
              {{ item.academic_class.schedules[0].room.code }}
            </span>
          </div>
        </div>

        <span class="font-bold text-slate-900 shrink-0">
          {{ item.credits }} SKS
        </span>
      </div>
    </div>

    <!-- Notes if any -->
    <div v-if="enrollment.notes" class="p-2 rounded bg-amber-50/50 border border-amber-100 text-2xs text-slate-600">
      <span class="font-semibold text-slate-700">Catatan Mahasiswa: </span>
      <span>{{ enrollment.notes }}</span>
    </div>

    <!-- Actions Bar -->
    <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-slate-100">
      <Button
        variant="ghost"
        size="xs"
        class="text-brand-900 hover:bg-brand-50"
        @click="router?.push(`/enrollments/${enrollment.id}`)"
      >
        <Eye class="w-3 h-3" />
        <span>Buka Workspace KRS</span>
      </Button>

      <div class="flex items-center gap-1.5">
        <!-- Request Revision -->
        <Button
          v-if="enrollment.status === 'submitted' && can('enrollments.revise')"
          variant="outline"
          size="xs"
          class="text-amber-700 border-amber-300 hover:bg-amber-50"
          :disabled="loading"
          @click="emit('request-revision', enrollment)"
        >
          <RotateCcw class="w-3 h-3 text-amber-600" />
          <span>Minta Revisi</span>
        </Button>

        <!-- Reject -->
        <Button
          v-if="enrollment.status === 'submitted' && can('enrollments.reject')"
          variant="outline"
          size="xs"
          class="text-rose-600 border-rose-200 hover:bg-rose-50"
          :disabled="loading"
          @click="emit('reject', enrollment)"
        >
          <XCircle class="w-3 h-3 text-rose-500" />
          <span>Tolak</span>
        </Button>

        <!-- Approve -->
        <Button
          v-if="enrollment.status === 'submitted' && can('enrollments.approve')"
          variant="primary"
          size="xs"
          class="!bg-emerald-600 hover:!bg-emerald-700 text-white"
          :disabled="loading"
          @click="emit('approve', enrollment)"
        >
          <CheckCircle2 class="w-3 h-3" />
          <span>Setujui KRS</span>
        </Button>
      </div>
    </div>
  </div>
</template>
