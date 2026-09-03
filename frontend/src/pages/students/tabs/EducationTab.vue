<script setup lang="ts">
import { Plus, GraduationCap } from 'lucide-vue-next'
import { usePermissions } from '@/composables/usePermissions'
import type { Student } from '@/types/student'
import Button from '@/components/ui/Button.vue'
import Badge from '@/components/ui/Badge.vue'
import EmptyState from '@/components/ui/EmptyState.vue'

interface Props {
  student: Student
}

defineProps<Props>()

const emit = defineEmits<{
  (e: 'add-education'): void
}>()

const { can } = usePermissions()
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h3 class="text-sm font-bold text-slate-900 tracking-tight">
          Riwayat Pendidikan Sebelumnya
        </h3>
        <p class="text-2xs text-slate-500">
          Daftar sekolah menengah (SMA/MA/SMK) atau perguruan tinggi asal
        </p>
      </div>

      <Button
        v-if="can('students.update')"
        variant="primary"
        size="sm"
        @click="emit('add-education')"
      >
        <Plus class="w-3.5 h-3.5" />
        <span>Tambah Pendidikan</span>
      </Button>
    </div>

    <!-- Timeline / List of Educations -->
    <div v-if="student.educations && student.educations.length > 0" class="relative pl-6 space-y-6 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200">
      <div
        v-for="edu in student.educations"
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
              <Badge variant="outline" size="xs">
                {{ edu.level }}
              </Badge>
              <h4 class="text-sm font-bold text-slate-900">
                {{ edu.institution_name }}
              </h4>
            </div>

            <span v-if="edu.graduation_year" class="text-xs font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md">
              Lulus {{ edu.graduation_year }}
            </span>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs text-slate-600 mt-2 pt-2 border-t border-slate-100">
            <div v-if="edu.major">
              <span class="text-slate-400">Jurusan / Program:</span>
              <span class="font-medium text-slate-800 ml-1">{{ edu.major }}</span>
            </div>
            <div v-if="edu.certificate_number">
              <span class="text-slate-400">No. Ijazah:</span>
              <span class="font-mono text-slate-800 ml-1">{{ edu.certificate_number }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <EmptyState
      v-else
      :icon="GraduationCap"
      title="Belum Ada Riwayat Pendidikan"
      description="Data riwayat pendidikan asal untuk mahasiswa ini belum ditambahkan."
    >
      <template v-if="can('students.update')" #action>
        <Button variant="outline" size="sm" @click="emit('add-education')">
          <Plus class="w-3.5 h-3.5" />
          <span>Tambah Riwayat Pendidikan</span>
        </Button>
      </template>
    </EmptyState>
  </div>
</template>
