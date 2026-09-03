<script setup lang="ts">
import { Link2, Trash2, Plus, ArrowRight } from 'lucide-vue-next'
import { usePermissions } from '@/composables/usePermissions'
import type { Course } from '@/types/course'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'

interface Props {
  course: Course
}

defineProps<Props>()

const emit = defineEmits<{
  (e: 'add-prerequisite'): void
  (e: 'remove-prerequisite', prereqCourse: Course): void
}>()

const { can } = usePermissions()
</script>

<template>
  <div class="space-y-6">
    <!-- Prerequisite Courses Section (Mata Kuliah yang Menjadi Prasyarat) -->
    <div class="bg-white border border-slate-200 rounded-lg p-4 sm:p-5 shadow-subtle space-y-4">
      <div class="flex items-center justify-between">
        <div>
          <h3 class="text-sm font-bold text-slate-900 tracking-tight flex items-center gap-2">
            <Link2 class="w-4 h-4 text-brand-900" />
            <span>Mata Kuliah Prasyarat (Prerequisites)</span>
          </h3>
          <p class="text-2xs text-slate-500">
            Mata kuliah yang wajib ditempuh/lulus terlebih dahulu sebelum mengambil {{ course.name }}
          </p>
        </div>

        <Button
          v-if="can('courses.update')"
          variant="outline"
          size="sm"
          @click="emit('add-prerequisite')"
        >
          <Plus class="w-3.5 h-3.5" />
          <span>Atur Prasyarat</span>
        </Button>
      </div>

      <!-- Prerequisite List -->
      <div v-if="course.prerequisites && course.prerequisites.length > 0" class="divide-y divide-slate-100 border border-slate-100 rounded-md overflow-hidden">
        <div
          v-for="prereq in course.prerequisites"
          :key="prereq.id"
          class="p-3 bg-slate-50/50 hover:bg-slate-50 flex items-center justify-between gap-3 transition-colors"
        >
          <div class="flex items-center gap-3">
            <span class="font-mono font-bold text-xs text-brand-900 bg-brand-50 px-2 py-0.5 rounded border border-brand-200">
              {{ prereq.code }}
            </span>
            <div>
              <router-link
                :to="`/courses/${prereq.id}`"
                class="font-medium text-xs text-slate-900 hover:text-brand-900 transition-colors"
              >
                {{ prereq.name }}
              </router-link>
              <div class="flex items-center gap-2 text-2xs text-slate-500 mt-0.5">
                <span>{{ prereq.credits }} SKS</span>
                <span v-if="prereq.pivot?.minimum_grade" class="font-semibold text-amber-700 bg-amber-50 px-1.5 py-0.2 rounded">
                  Nilai Minimal: {{ prereq.pivot.minimum_grade }}
                </span>
              </div>
            </div>
          </div>

          <div class="flex items-center gap-1">
            <router-link :to="`/courses/${prereq.id}`">
              <Button variant="ghost" size="xs">
                <span>Detail</span>
                <ArrowRight class="w-3 h-3 ml-1" />
              </Button>
            </router-link>

            <Button
              v-if="can('courses.update')"
              variant="ghost"
              size="xs"
              class="text-rose-600 hover:bg-rose-50"
              title="Hapus Prasyarat Ini"
              @click="emit('remove-prerequisite', prereq)"
            >
              <Trash2 class="w-3.5 h-3.5" />
            </Button>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <EmptyState
        v-else
        :icon="Link2"
        title="Tidak Ada Prasyarat"
        description="Mata kuliah ini dapat diambil langsung tanpa prasyarat mata kuliah terdahulu."
      />
    </div>

    <!-- Dependent Courses Section (Mata Kuliah Lanjutan yang Membutuhkan MK Ini) -->
    <div v-if="course.dependents && course.dependents.length > 0" class="bg-white border border-slate-200 rounded-lg p-4 sm:p-5 shadow-subtle space-y-4">
      <div>
        <h3 class="text-sm font-bold text-slate-900 tracking-tight">
          Mata Kuliah Lanjutan (Dependent Courses)
        </h3>
        <p class="text-2xs text-slate-500">
          Mata kuliah tingkat atas yang mensyaratkan kelulusan mata kuliah {{ course.name }} ini
        </p>
      </div>

      <div class="divide-y divide-slate-100 border border-slate-100 rounded-md overflow-hidden">
        <div
          v-for="dep in course.dependents"
          :key="dep.id"
          class="p-3 bg-slate-50/50 hover:bg-slate-50 flex items-center justify-between gap-3 transition-colors"
        >
          <div class="flex items-center gap-3">
            <span class="font-mono font-bold text-xs text-slate-700 bg-slate-100 px-2 py-0.5 rounded">
              {{ dep.code }}
            </span>
            <div>
              <router-link
                :to="`/courses/${dep.id}`"
                class="font-medium text-xs text-slate-900 hover:text-brand-900 transition-colors"
              >
                {{ dep.name }}
              </router-link>
              <span class="text-2xs text-slate-500 block">{{ dep.credits }} SKS</span>
            </div>
          </div>

          <router-link :to="`/courses/${dep.id}`">
            <Button variant="ghost" size="xs">
              <span>Lihat MK</span>
              <ArrowRight class="w-3 h-3 ml-1" />
            </Button>
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>
