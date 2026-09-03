<script setup lang="ts">
import { ArrowLeft, Edit3, Trash2, BookOpen } from 'lucide-vue-next'
import { usePermissions } from '@/composables/usePermissions'
import type { Course } from '@/types/course'
import Button from '@/components/ui/Button.vue'
import Badge from '@/components/ui/Badge.vue'
import CourseTypeBadge from './CourseTypeBadge.vue'

interface Props {
  course: Course
}

defineProps<Props>()

const emit = defineEmits<{
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
          <BookOpen class="w-6 h-6" />
        </div>

        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-2 mb-1">
            <span class="font-mono font-bold text-xs text-brand-900 bg-brand-50 px-2 py-0.5 rounded border border-brand-200">
              {{ course.code }}
            </span>
            <h1 class="text-base sm:text-lg font-bold text-slate-900 tracking-tight truncate">
              {{ course.name }}
            </h1>
            <Badge :variant="course.status === 'active' ? 'success' : 'neutral'" size="xs" dot>
              {{ course.status === 'active' ? 'Aktif' : 'Non-Aktif' }}
            </Badge>
          </div>

          <div class="flex flex-wrap items-center gap-y-1 gap-x-2.5 text-xs text-slate-500">
            <span class="font-semibold text-slate-700">
              {{ course.credits }} SKS
            </span>
            <span>•</span>
            <span>Teori: {{ course.theory_credits }} SKS</span>
            <span>•</span>
            <span>Praktik: {{ course.practical_credits }} SKS</span>
            <span>•</span>
            <CourseTypeBadge :type="course.type" size="xs" />
            <template v-if="course.category">
              <span>•</span>
              <span class="text-slate-600 font-medium">{{ course.category }}</span>
            </template>
          </div>
        </div>
      </div>

      <!-- Right Actions -->
      <div class="flex flex-wrap items-center gap-2 pt-3 md:pt-0 border-t md:border-t-0 border-slate-100 shrink-0">
        <router-link to="/courses">
          <Button variant="outline" size="sm">
            <ArrowLeft class="w-3.5 h-3.5" />
            <span class="hidden sm:inline">Daftar</span>
          </Button>
        </router-link>

        <router-link v-if="can('courses.update')" :to="`/courses/${course.id}/edit`">
          <Button variant="outline" size="sm">
            <Edit3 class="w-3.5 h-3.5 text-slate-500" />
            <span>Edit Mata Kuliah</span>
          </Button>
        </router-link>

        <Button
          v-if="can('courses.delete')"
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
