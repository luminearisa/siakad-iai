<script setup lang="ts">
import { Plus, Layers } from 'lucide-vue-next'
import { usePermissions } from '@/composables/usePermissions'
import type { Curriculum, CurriculumSemester } from '@/types/curriculum'
import Button from '@/components/ui/Button.vue'
import Card from '@/components/ui/Card.vue'
import EmptyState from '@/components/ui/EmptyState.vue'

interface Props {
  curriculum: Curriculum
}

defineProps<Props>()

const emit = defineEmits<{
  (e: 'add-semester'): void
  (e: 'select-semester', semester: CurriculumSemester): void
}>()

const { can } = usePermissions()
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h3 class="text-sm font-bold text-slate-900 tracking-tight">
          Struktur Distribusi Semester
        </h3>
        <p class="text-2xs text-slate-500">
          Struktur pemaketan semester dan beban kredit SKS per tingkat perkuliahan
        </p>
      </div>

      <Button
        v-if="can('curricula.update')"
        variant="outline"
        size="sm"
        @click="emit('add-semester')"
      >
        <Plus class="w-3.5 h-3.5" />
        <span>Tambah Semester</span>
      </Button>
    </div>

    <!-- Semester Cards Grid -->
    <div
      v-if="curriculum.semesters && curriculum.semesters.length > 0"
      class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5"
    >
      <Card
        v-for="sem in curriculum.semesters"
        :key="sem.id"
        class="border-t-4 border-t-brand-900 hover:shadow-md transition-shadow cursor-pointer"
        @click="emit('select-semester', sem)"
      >
        <div class="flex items-center justify-between mb-2">
          <span class="w-7 h-7 rounded-full bg-brand-50 text-brand-900 font-bold text-xs flex items-center justify-center">
            {{ sem.semester_number }}
          </span>
          <span class="font-bold text-xs text-slate-900">
            {{ sem.total_credits || sem.subjects?.reduce((acc, s) => acc + (s.effective_credits || s.course?.credits || 0), 0) || 0 }} SKS
          </span>
        </div>

        <h4 class="text-xs font-bold text-slate-900 truncate mb-1">
          {{ sem.name || `Semester ${sem.semester_number}` }}
        </h4>

        <div class="flex items-center justify-between text-2xs text-slate-500 pt-2 border-t border-slate-100">
          <span>{{ sem.subjects?.length || 0 }} Mata Kuliah</span>
          <span v-if="sem.recommended_credits" class="text-slate-400">
            Target: {{ sem.recommended_credits }} SKS
          </span>
        </div>
      </Card>
    </div>

    <!-- Empty State -->
    <EmptyState
      v-else
      :icon="Layers"
      title="Belum Ada Struktur Semester"
      description="Struktur semester belum dibuat untuk kurikulum ini."
    >
      <template #action>
        <Button
          v-if="can('curricula.update')"
          variant="primary"
          size="sm"
          @click="emit('add-semester')"
        >
          <Plus class="w-3.5 h-3.5" />
          <span>Buat Semester Pertama</span>
        </Button>
      </template>
    </EmptyState>
  </div>
</template>
