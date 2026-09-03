<script setup lang="ts">
import { Sparkles, Award } from 'lucide-vue-next'
import type { Lecturer } from '@/types/lecturer'
import Card from '@/components/ui/Card.vue'
import EmptyState from '@/components/ui/EmptyState.vue'

interface Props {
  lecturer: Lecturer
}

defineProps<Props>()
</script>

<template>
  <div class="space-y-4">
    <div>
      <h3 class="text-sm font-bold text-slate-900 tracking-tight">
        Bidang Kepakaran & Fokus Riset
      </h3>
      <p class="text-2xs text-slate-500">
        Keahlian akademik, peminatan riset, dan fokus pengajaran dosen
      </p>
    </div>

    <!-- Expertise Grid -->
    <div v-if="lecturer.expertises && lecturer.expertises.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
      <Card v-for="exp in lecturer.expertises" :key="exp.id" dense class="border-l-4 border-l-brand-600">
        <div class="flex items-start gap-2.5">
          <div class="w-7 h-7 rounded-md bg-brand-50 text-brand-700 flex items-center justify-center shrink-0 mt-0.5">
            <Sparkles class="w-3.5 h-3.5" />
          </div>
          <div>
            <h4 class="text-xs font-bold text-slate-900">
              {{ exp.name }}
            </h4>
            <p v-if="exp.description" class="text-2xs text-slate-500 mt-0.5 leading-relaxed">
              {{ exp.description }}
            </p>
          </div>
        </div>
      </Card>
    </div>

    <!-- Empty State -->
    <EmptyState
      v-else
      :icon="Award"
      title="Belum Ada Bidang Kepakaran"
      description="Data bidang keahlian dan fokus riset dosen ini belum ditambahkan."
    />
  </div>
</template>
