<script setup lang="ts">
import { GraduationCap } from 'lucide-vue-next'
import type { Lecturer } from '@/types/lecturer'
import Badge from '@/components/ui/Badge.vue'
import EmptyState from '@/components/ui/EmptyState.vue'

interface Props {
  lecturer: Lecturer
}

defineProps<Props>()
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h3 class="text-sm font-bold text-slate-900 tracking-tight">
          Riwayat Pendidikan Formal
        </h3>
        <p class="text-2xs text-slate-500">
          Kualifikasi jenjang pendidikan tinggi (S1, S2, S3, Spesialis) dosen
        </p>
      </div>
    </div>

    <!-- Timeline / List of Educations -->
    <div v-if="lecturer.educations && lecturer.educations.length > 0" class="relative pl-6 space-y-6 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200">
      <div
        v-for="edu in lecturer.educations"
        :key="edu.id"
        class="relative"
      >
        <!-- Bullet Indicator -->
        <div class="absolute -left-6 top-1 w-4 h-4 rounded-full bg-white border-2 border-brand-600 flex items-center justify-center">
          <span class="w-1.5 h-1.5 rounded-full bg-brand-600" />
        </div>

        <div class="bg-white border border-slate-200 rounded-lg p-4 shadow-subtle">
          <div class="flex flex-wrap items-center justify-between gap-2 mb-1.5">
            <div class="flex items-center gap-2">
              <Badge variant="primary" size="xs">
                {{ edu.degree }}
              </Badge>
              <h4 class="text-sm font-bold text-slate-900">
                {{ edu.institution_name }}
              </h4>
            </div>

            <span v-if="edu.graduation_year" class="text-xs font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md">
              Lulus {{ edu.graduation_year }}
            </span>
          </div>

          <div v-if="edu.major" class="text-xs text-slate-600 mt-2 pt-2 border-t border-slate-100">
            <span class="text-slate-400">Bidang / Program Studi:</span>
            <span class="font-medium text-slate-800 ml-1">{{ edu.major }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <EmptyState
      v-else
      :icon="GraduationCap"
      title="Belum Ada Riwayat Pendidikan"
      description="Data kualifikasi pendidikan formal dosen ini belum tercatat."
    />
  </div>
</template>
