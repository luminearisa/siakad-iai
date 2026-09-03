<script setup lang="ts">
import { ArrowLeft, Edit3, Trash2, CheckCircle2, Archive, Layers } from 'lucide-vue-next'
import { usePermissions } from '@/composables/usePermissions'
import type { Curriculum } from '@/types/curriculum'
import Button from '@/components/ui/Button.vue'
import CurriculumStatusBadge from './CurriculumStatusBadge.vue'

interface Props {
  curriculum: Curriculum
}

defineProps<Props>()

const emit = defineEmits<{
  (e: 'activate'): void
  (e: 'archive'): void
  (e: 'delete'): void
}>()

const { can } = usePermissions()
</script>

<template>
  <div class="bg-white border border-slate-200 rounded-lg p-4 sm:p-5 shadow-subtle mb-5">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <!-- Left Info -->
      <div class="flex items-start sm:items-center gap-3.5">
        <div class="w-12 h-12 rounded-lg bg-brand-50 text-brand-900 flex items-center justify-center shrink-0 border border-brand-100">
          <Layers class="w-6 h-6" />
        </div>

        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-2 mb-1">
            <span class="font-mono font-bold text-xs text-brand-900 bg-brand-50 px-2 py-0.5 rounded border border-brand-200">
              {{ curriculum.code }}
            </span>
            <h1 class="text-base sm:text-lg font-bold text-slate-900 tracking-tight truncate">
              {{ curriculum.name }}
            </h1>
            <span v-if="curriculum.version" class="text-xs font-semibold text-slate-600 bg-slate-100 px-1.5 py-0.5 rounded">
              v{{ curriculum.version }}
            </span>
            <CurriculumStatusBadge :status="curriculum.status" size="xs" />
          </div>

          <div class="flex flex-wrap items-center gap-y-1 gap-x-2.5 text-xs text-slate-500">
            <span class="font-semibold text-slate-700">
              {{ curriculum.study_program?.name || 'Program Studi' }}
            </span>
            <span>•</span>
            <span v-if="curriculum.start_year">
              Berlaku: {{ curriculum.start_year }} <template v-if="curriculum.end_year">- {{ curriculum.end_year }}</template>
            </span>
            <span>•</span>
            <span class="font-bold text-slate-900">
              {{ curriculum.total_credits || 0 }} SKS Kurikulum
            </span>
          </div>
        </div>
      </div>

      <!-- Right Actions -->
      <div class="flex flex-wrap items-center gap-2 pt-3 md:pt-0 border-t md:border-t-0 border-slate-100 shrink-0">
        <router-link to="/curriculum">
          <Button variant="outline" size="sm">
            <ArrowLeft class="w-3.5 h-3.5" />
            <span class="hidden sm:inline">Daftar</span>
          </Button>
        </router-link>

        <!-- Activate Action -->
        <Button
          v-if="can('curricula.activate') && curriculum.status !== 'active' && curriculum.status !== 'archived'"
          variant="outline"
          size="sm"
          class="text-emerald-700 hover:bg-emerald-50 border-emerald-300"
          @click="emit('activate')"
        >
          <CheckCircle2 class="w-3.5 h-3.5" />
          <span>Aktivasi Kurikulum</span>
        </Button>

        <!-- Archive Action -->
        <Button
          v-if="can('curricula.archive') && curriculum.status === 'active'"
          variant="outline"
          size="sm"
          class="text-slate-600 hover:bg-slate-100"
          @click="emit('archive')"
        >
          <Archive class="w-3.5 h-3.5" />
          <span>Arsipkan</span>
        </Button>

        <!-- Edit -->
        <router-link v-if="can('curricula.update') && curriculum.status !== 'archived'" :to="`/curriculum/${curriculum.id}/edit`">
          <Button variant="outline" size="sm">
            <Edit3 class="w-3.5 h-3.5 text-slate-500" />
            <span>Edit</span>
          </Button>
        </router-link>

        <!-- Delete -->
        <Button
          v-if="can('curricula.delete')"
          variant="ghost"
          size="sm"
          class="text-rose-600 hover:bg-rose-50"
          @click="emit('delete')"
        >
          <Trash2 class="w-3.5 h-3.5" />
        </Button>
      </div>
    </div>
  </div>
</template>
