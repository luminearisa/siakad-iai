<script setup lang="ts">
import { Calendar, User, Clock } from 'lucide-vue-next'
import type { AcademicAdvisor } from '@/types/advising'
import AdvisorStatusBadge from './AdvisorStatusBadge.vue'
import { formatDate } from '@/utils/format'

defineProps<{
  history: AcademicAdvisor[]
  loading?: boolean
}>()
</script>

<template>
  <div class="space-y-3">
    <div v-if="history.length === 0 && !loading" class="text-center py-8 text-slate-500 text-xs">
      Belum ada riwayat penugasan pembimbing akademik.
    </div>

    <div v-else class="relative pl-6 space-y-4 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200">
      <div
        v-for="item in history"
        :key="item.id"
        class="relative bg-white border border-slate-200 rounded-lg p-3.5 shadow-subtle space-y-2 text-xs"
      >
        <!-- Timeline node bullet -->
        <div
          class="absolute -left-6 top-3.5 w-2.5 h-2.5 rounded-full ring-4 ring-white"
          :class="item.status === 'active' ? 'bg-emerald-500' : 'bg-slate-400'"
        />

        <div class="flex flex-wrap items-center justify-between gap-2">
          <div class="flex items-center gap-2">
            <span class="font-bold text-slate-900">{{ item.student?.full_name || 'Mahasiswa' }}</span>
            <span class="text-2xs font-mono text-slate-500">({{ item.student?.student_number || '-' }})</span>
          </div>
          <AdvisorStatusBadge :status="item.status" />
        </div>

        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-slate-600 text-2xs">
          <span class="flex items-center gap-1">
            <Calendar class="w-3 h-3 text-slate-400" />
            <span>Mulai: {{ formatDate(item.start_date) }}</span>
          </span>

          <span v-if="item.end_date" class="flex items-center gap-1">
            <Clock class="w-3 h-3 text-slate-400" />
            <span>Selesai: {{ formatDate(item.end_date) }}</span>
          </span>

          <span v-if="item.lecturer" class="flex items-center gap-1">
            <User class="w-3 h-3 text-slate-400" />
            <span>Dosen PA: {{ item.lecturer.full_name }}</span>
          </span>
        </div>

        <div v-if="item.notes" class="p-2 rounded bg-slate-50 border border-slate-100 text-2xs text-slate-600">
          <span class="font-semibold text-slate-700">Catatan: </span>
          <span>{{ item.notes }}</span>
        </div>
      </div>
    </div>
  </div>
</template>
