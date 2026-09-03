<script setup lang="ts">
import type { AcademicAdvisor } from '@/types/advising'
import AdvisorAssignmentHistory from '../components/AdvisorAssignmentHistory.vue'

defineProps<{
  history: AcademicAdvisor[]
  loading?: boolean
}>()
</script>

<template>
  <div class="space-y-4">
    <div class="bg-white border border-slate-200 rounded-lg p-3 sm:p-4 shadow-subtle">
      <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">
        Riwayat Penugasan & Mutasi Mahasiswa Bimbingan
      </h3>
      <p class="text-2xs text-slate-500">
        Jejak audit kronologis penugasan, pengalihan (transferred), dan riwayat mahasiswa bimbingan yang pernah diasuh oleh Dosen Pembimbing Akademik ini.
      </p>
    </div>

    <AdvisorAssignmentHistory
      :history="history"
      :loading="loading"
    />
  </div>
</template>
