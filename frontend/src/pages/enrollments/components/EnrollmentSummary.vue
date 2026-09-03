<script setup lang="ts">
import { computed } from 'vue'
import { BookOpen, Award, CheckCircle2, AlertCircle } from 'lucide-vue-next'
import type { StudentEnrollment } from '@/types/enrollment'

interface Props {
  enrollment: StudentEnrollment
  maxSks?: number
}

const props = withDefaults(defineProps<Props>(), {
  maxSks: 24,
})

const totalCourses = computed(() => {
  if (props.enrollment.items_count !== undefined) return props.enrollment.items_count
  return props.enrollment.items?.length || 0
})

const totalCredits = computed(() => {
  return props.enrollment.total_credits || 0
})

const remainingSks = computed(() => {
  return Math.max(0, props.maxSks - totalCredits.value)
})

const percentage = computed(() => {
  return Math.min(100, Math.round((totalCredits.value / props.maxSks) * 100))
})
</script>

<template>
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
    <!-- Card 1: Total Courses -->
    <div class="bg-white p-3.5 rounded-lg border border-slate-200 shadow-subtle flex items-center gap-3">
      <div class="w-10 h-10 rounded-lg bg-brand-50 text-brand-900 flex items-center justify-center shrink-0 border border-brand-100">
        <BookOpen class="w-5 h-5" />
      </div>
      <div>
        <span class="text-3xs font-semibold uppercase tracking-wider text-slate-500 block">Total Mata Kuliah</span>
        <span class="text-base font-bold text-slate-900">{{ totalCourses }} <span class="text-xs font-normal text-slate-500">Kelas</span></span>
      </div>
    </div>

    <!-- Card 2: Total SKS Diambil -->
    <div class="bg-white p-3.5 rounded-lg border border-slate-200 shadow-subtle flex items-center gap-3">
      <div class="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 border border-emerald-100">
        <Award class="w-5 h-5" />
      </div>
      <div>
        <span class="text-3xs font-semibold uppercase tracking-wider text-slate-500 block">Beban SKS Diambil</span>
        <span class="text-base font-bold text-emerald-700 font-mono">{{ totalCredits }} <span class="text-xs font-normal text-slate-500">SKS</span></span>
      </div>
    </div>

    <!-- Card 3: Batas Maksimal SKS -->
    <div class="bg-white p-3.5 rounded-lg border border-slate-200 shadow-subtle flex items-center gap-3">
      <div class="w-10 h-10 rounded-lg bg-slate-100 text-slate-700 flex items-center justify-center shrink-0 border border-slate-200">
        <CheckCircle2 class="w-5 h-5" />
      </div>
      <div>
        <span class="text-3xs font-semibold uppercase tracking-wider text-slate-500 block">Batas Maksimal</span>
        <span class="text-base font-bold text-slate-800 font-mono">{{ maxSks }} <span class="text-xs font-normal text-slate-500">SKS</span></span>
      </div>
    </div>

    <!-- Card 4: Sisa Kuota SKS -->
    <div class="bg-white p-3.5 rounded-lg border border-slate-200 shadow-subtle flex items-center gap-3">
      <div
        :class="[
          'w-10 h-10 rounded-lg flex items-center justify-center shrink-0 border',
          remainingSks > 0 ? 'bg-indigo-50 text-indigo-700 border-indigo-100' : 'bg-amber-50 text-amber-700 border-amber-100',
        ]"
      >
        <AlertCircle class="w-5 h-5" />
      </div>
      <div class="min-w-0 flex-1">
        <div class="flex items-center justify-between">
          <span class="text-3xs font-semibold uppercase tracking-wider text-slate-500">Sisa Kuota SKS</span>
          <span class="text-3xs font-mono font-bold text-slate-500">{{ percentage }}%</span>
        </div>
        <span class="text-base font-bold text-slate-900 font-mono">{{ remainingSks }} <span class="text-xs font-normal text-slate-500">SKS</span></span>
        <!-- Mini Progress Bar -->
        <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden mt-1">
          <div
            :class="[
              'h-full rounded-full transition-all duration-300',
              percentage >= 100 ? 'bg-amber-500' : 'bg-brand-900',
            ]"
            :style="{ width: `${percentage}%` }"
          />
        </div>
      </div>
    </div>
  </div>
</template>
