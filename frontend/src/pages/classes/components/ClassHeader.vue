<script setup lang="ts">
import { ArrowLeft, Edit3, Trash2, CheckCircle, XCircle, Slash, Users } from 'lucide-vue-next'
import { usePermissions } from '@/composables/usePermissions'
import type { AcademicClass } from '@/types/class'
import Button from '@/components/ui/Button.vue'
import ClassStatusBadge from './ClassStatusBadge.vue'
import ClassCapacityBadge from './ClassCapacityBadge.vue'

interface Props {
  academicClass: AcademicClass
}

defineProps<Props>()

const emit = defineEmits<{
  (e: 'open-class'): void
  (e: 'close-class'): void
  (e: 'cancel-class'): void
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
          <Users class="w-6 h-6" />
        </div>

        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-2 mb-1">
            <span class="font-mono font-bold text-xs text-brand-900 bg-brand-50 px-2 py-0.5 rounded border border-brand-200">
              Kelas {{ academicClass.section }}
            </span>
            <h1 class="text-base sm:text-lg font-bold text-slate-900 tracking-tight truncate">
              {{ academicClass.course?.name || academicClass.name || 'Kelas Perkuliahan' }}
            </h1>
            <ClassStatusBadge :status="academicClass.status" size="xs" />
          </div>

          <div class="flex flex-wrap items-center gap-y-1 gap-x-2.5 text-xs text-slate-500">
            <span class="font-mono text-slate-700 font-semibold">
              {{ academicClass.course?.code }}
            </span>
            <span>•</span>
            <span class="font-medium text-slate-800">
              {{ academicClass.semester?.name || 'Semester' }}
            </span>
            <span v-if="academicClass.study_program">•</span>
            <span v-if="academicClass.study_program" class="text-slate-600 font-medium">
              {{ academicClass.study_program.name }}
            </span>
            <span>•</span>
            <ClassCapacityBadge
              :capacity="academicClass.capacity"
              :enrolled="academicClass.enrolled_count || 0"
            />
          </div>
        </div>
      </div>

      <!-- Right Actions -->
      <div class="flex flex-wrap items-center gap-2 pt-3 md:pt-0 border-t md:border-t-0 border-slate-100 shrink-0">
        <router-link to="/classes">
          <Button variant="outline" size="sm">
            <ArrowLeft class="w-3.5 h-3.5" />
            <span class="hidden sm:inline">Daftar</span>
          </Button>
        </router-link>

        <!-- Status Action: Open -->
        <Button
          v-if="can('classes.open') && (academicClass.status === 'draft' || academicClass.status === 'closed')"
          variant="outline"
          size="sm"
          class="text-emerald-700 hover:bg-emerald-50 border-emerald-300"
          @click="emit('open-class')"
        >
          <CheckCircle class="w-3.5 h-3.5" />
          <span>Buka Kelas</span>
        </Button>

        <!-- Status Action: Close -->
        <Button
          v-if="can('classes.close') && academicClass.status === 'open'"
          variant="outline"
          size="sm"
          class="text-amber-700 hover:bg-amber-50 border-amber-300"
          @click="emit('close-class')"
        >
          <XCircle class="w-3.5 h-3.5" />
          <span>Tutup Kelas</span>
        </Button>

        <!-- Status Action: Cancel -->
        <Button
          v-if="can('classes.cancel') && (academicClass.status === 'open' || academicClass.status === 'draft')"
          variant="outline"
          size="sm"
          class="text-rose-700 hover:bg-rose-50 border-rose-300"
          @click="emit('cancel-class')"
        >
          <Slash class="w-3.5 h-3.5" />
          <span>Batalkan Kelas</span>
        </Button>

        <!-- Edit -->
        <router-link
          v-if="can('classes.update') && academicClass.status !== 'cancelled' && academicClass.status !== 'completed'"
          :to="`/classes/${academicClass.id}/edit`"
        >
          <Button variant="outline" size="sm">
            <Edit3 class="w-3.5 h-3.5 text-slate-500" />
            <span>Edit</span>
          </Button>
        </router-link>

        <!-- Delete -->
        <Button
          v-if="can('classes.delete')"
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
