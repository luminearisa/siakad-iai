<script setup lang="ts">
import { Plus, Trash2, ArrowRight } from 'lucide-vue-next'
import { usePermissions } from '@/composables/usePermissions'
import type { CurriculumSemester, CurriculumSubject } from '@/types/curriculum'
import Button from '@/components/ui/Button.vue'
import Badge from '@/components/ui/Badge.vue'
import CourseTypeBadge from '@/pages/courses/components/CourseTypeBadge.vue'

interface Props {
  semester: CurriculumSemester
  isReadOnly?: boolean
}

defineProps<Props>()

const emit = defineEmits<{
  (e: 'add-subject', semester: CurriculumSemester): void
  (e: 'remove-subject', semester: CurriculumSemester, subject: CurriculumSubject): void
}>()

const { can } = usePermissions()
</script>

<template>
  <div class="bg-white border border-slate-200 rounded-lg overflow-hidden shadow-subtle">
    <!-- Header -->
    <div class="p-3.5 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-2.5">
        <span class="w-6 h-6 rounded bg-brand-900 text-white font-bold text-xs flex items-center justify-center">
          {{ semester.semester_number }}
        </span>
        <div>
          <h4 class="text-xs font-bold text-slate-900">
            {{ semester.name || `Semester ${semester.semester_number}` }}
          </h4>
          <span class="text-2xs text-slate-500">
            {{ semester.subjects?.length || 0 }} Mata Kuliah ·
            <strong class="text-slate-800 font-semibold">{{ semester.total_credits || semester.subjects?.reduce((acc, s) => acc + (s.effective_credits || s.course?.credits || 0), 0) || 0 }} SKS</strong>
            <template v-if="semester.recommended_credits">
              (Target: {{ semester.recommended_credits }} SKS)
            </template>
          </span>
        </div>
      </div>

      <Button
        v-if="can('curricula.manage_subjects') && !isReadOnly"
        variant="outline"
        size="xs"
        @click="emit('add-subject', semester)"
      >
        <Plus class="w-3 h-3" />
        <span>Tambah Mata Kuliah</span>
      </Button>
    </div>

    <!-- Table of subjects -->
    <div class="overflow-x-auto">
      <table class="w-full text-left text-xs border-collapse">
        <thead>
          <tr class="border-b border-slate-100 bg-slate-50/50 text-2xs uppercase tracking-wider text-slate-400 font-semibold">
            <th class="py-2.5 px-3">Kode MK</th>
            <th class="py-2.5 px-3">Nama Mata Kuliah</th>
            <th class="py-2.5 px-3 text-center">SKS</th>
            <th class="py-2.5 px-3 text-center">Sifat</th>
            <th class="py-2.5 px-3 text-center">Min. Nilai</th>
            <th class="py-2.5 px-3">Catatan</th>
            <th v-if="!isReadOnly" class="py-2.5 px-3 text-right">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <template v-if="semester.subjects && semester.subjects.length > 0">
            <tr
              v-for="sub in semester.subjects"
              :key="sub.id"
              class="hover:bg-slate-50/50 transition-colors"
            >
              <!-- Kode MK -->
              <td class="py-2.5 px-3 font-mono font-bold text-brand-900">
                <router-link
                  v-if="sub.course"
                  :to="`/courses/${sub.course.id}`"
                  class="hover:underline"
                >
                  {{ sub.course.code }}
                </router-link>
                <span v-else>-</span>
              </td>

              <!-- Nama MK -->
              <td class="py-2.5 px-3">
                <div class="font-medium text-slate-900">
                  {{ sub.course?.name || '-' }}
                </div>
                <div v-if="sub.course" class="flex items-center gap-1.5 mt-0.5">
                  <CourseTypeBadge :type="sub.course.type" size="xs" />
                  <span v-if="sub.course.category" class="text-2xs text-slate-400">
                    {{ sub.course.category }}
                  </span>
                </div>
              </td>

              <!-- SKS -->
              <td class="py-2.5 px-3 text-center font-bold text-slate-800">
                {{ sub.effective_credits || sub.course?.credits || 0 }} SKS
                <span v-if="sub.credits_override" class="block text-2xs text-amber-600 font-normal">
                  (Override)
                </span>
              </td>

              <!-- Sifat (Mandatory / Elective) -->
              <td class="py-2.5 px-3 text-center">
                <Badge :variant="sub.is_mandatory ? 'primary' : 'neutral'" size="xs">
                  {{ sub.is_mandatory ? 'Wajib' : 'Pilihan' }}
                </Badge>
              </td>

              <!-- Minimum Grade -->
              <td class="py-2.5 px-3 text-center">
                <span v-if="sub.minimum_grade" class="font-bold text-2xs text-slate-700 bg-slate-100 px-1.5 py-0.5 rounded">
                  {{ sub.minimum_grade }}
                </span>
                <span v-else class="text-slate-400 text-2xs">-</span>
              </td>

              <!-- Catatan -->
              <td class="py-2.5 px-3 text-slate-500 text-2xs">
                {{ sub.notes || '-' }}
              </td>

              <!-- Actions -->
              <td v-if="!isReadOnly" class="py-2.5 px-3 text-right">
                <div class="flex items-center justify-end gap-1">
                  <router-link v-if="sub.course" :to="`/courses/${sub.course.id}`">
                    <Button variant="ghost" size="xs" title="Lihat Master Mata Kuliah">
                      <ArrowRight class="w-3 h-3" />
                    </Button>
                  </router-link>

                  <Button
                    v-if="can('curricula.manage_subjects')"
                    variant="ghost"
                    size="xs"
                    class="text-rose-600 hover:bg-rose-50"
                    title="Hapus dari Semester Ini"
                    @click="emit('remove-subject', semester, sub)"
                  >
                    <Trash2 class="w-3.5 h-3.5" />
                  </Button>
                </div>
              </td>
            </tr>
          </template>

          <tr v-else>
            <td :colspan="isReadOnly ? 6 : 7" class="py-4 text-center text-xs text-slate-400 italic">
              Belum ada mata kuliah yang didistribusikan pada semester ini.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
