<script setup lang="ts">
import { Plus, BookOpen } from 'lucide-vue-next'
import { usePermissions } from '@/composables/usePermissions'
import type { Curriculum, CurriculumSemester, CurriculumSubject } from '@/types/curriculum'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import CurriculumSubjectTable from '../components/CurriculumSubjectTable.vue'

interface Props {
  curriculum: Curriculum
}

defineProps<Props>()

const emit = defineEmits<{
  (e: 'add-semester'): void
  (e: 'add-subject', semester: CurriculumSemester): void
  (e: 'remove-subject', semester: CurriculumSemester, subject: CurriculumSubject): void
}>()

const { can } = usePermissions()
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h3 class="text-sm font-bold text-slate-900 tracking-tight">
          Distribusi Mata Kuliah per Semester
        </h3>
        <p class="text-2xs text-slate-500">
          Pemetaan mata kuliah wajib dan pilihan pada masing-masing semester kurikulum
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

    <!-- List of Semester Tables -->
    <div v-if="curriculum.semesters && curriculum.semesters.length > 0" class="space-y-5">
      <CurriculumSubjectTable
        v-for="sem in curriculum.semesters"
        :key="sem.id"
        :semester="sem"
        :is-read-only="curriculum.status === 'archived'"
        @add-subject="emit('add-subject', $event)"
        @remove-subject="(s, sub) => emit('remove-subject', s, sub)"
      />
    </div>

    <!-- Empty State -->
    <EmptyState
      v-else
      :icon="BookOpen"
      title="Belum Ada Distribusi Mata Kuliah"
      description="Silakan tambahkan paket semester terlebih dahulu untuk mendistribusikan mata kuliah ke dalam kurikulum."
    >
      <template #action>
        <Button
          v-if="can('curricula.update')"
          variant="primary"
          size="sm"
          @click="emit('add-semester')"
        >
          <Plus class="w-3.5 h-3.5" />
          <span>Tambah Semester Baru</span>
        </Button>
      </template>
    </EmptyState>
  </div>
</template>
