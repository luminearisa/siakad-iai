<script setup lang="ts">
import { Plus, Trash2, UserCheck } from 'lucide-vue-next'
import { usePermissions } from '@/composables/usePermissions'
import type { AcademicClass, ClassLecturer } from '@/types/class'
import Button from '@/components/ui/Button.vue'
import Badge from '@/components/ui/Badge.vue'
import Avatar from '@/components/ui/Avatar.vue'
import EmptyState from '@/components/ui/EmptyState.vue'

interface Props {
  academicClass: AcademicClass
  classLecturers: ClassLecturer[]
  isReadOnly?: boolean
}

defineProps<Props>()

const emit = defineEmits<{
  (e: 'add-lecturer'): void
  (e: 'remove-lecturer', lecturerId: number, lecturerName: string): void
}>()

const { can } = usePermissions()

function getRoleBadge(role: string) {
  switch (role) {
    case 'primary':
      return { label: 'Dosen Utama (Pengampu)', variant: 'primary' as const }
    case 'co_lecturer':
      return { label: 'Dosen Anggota (Team)', variant: 'info' as const }
    case 'assistant':
      return { label: 'Asisten Dosen', variant: 'neutral' as const }
    default:
      return { label: role, variant: 'neutral' as const }
  }
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h3 class="text-sm font-bold text-slate-900 tracking-tight">
          Dosen Pengampu Kelas
        </h3>
        <p class="text-2xs text-slate-500">
          Penetapan dosen pengampu utama, dosen tim (team teaching), dan asisten kelas
        </p>
      </div>

      <Button
        v-if="can('classes.assign_lecturer') && !isReadOnly"
        variant="outline"
        size="sm"
        @click="emit('add-lecturer')"
      >
        <Plus class="w-3.5 h-3.5" />
        <span>Tugaskan Dosen</span>
      </Button>
    </div>

    <!-- Lecturers List -->
    <div v-if="classLecturers && classLecturers.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
      <div
        v-for="cl in classLecturers"
        :key="cl.id"
        class="bg-white border border-slate-200 rounded-lg p-4 shadow-subtle flex flex-col justify-between"
      >
        <div class="flex items-start gap-3">
          <Avatar
            :name="cl.lecturer?.full_name || 'Dosen'"
            :src="cl.lecturer?.photo_path || undefined"
            size="md"
            class="shrink-0 mt-0.5"
          />

          <div class="min-w-0 flex-1">
            <h4 class="text-xs font-bold text-slate-900 truncate">
              {{ cl.lecturer?.full_name || 'Dosen Pengampu' }}<span v-if="cl.lecturer?.academic_degree">, {{ cl.lecturer.academic_degree }}</span>
            </h4>
            <span v-if="cl.lecturer?.nidn" class="block font-mono text-2xs text-slate-500">
              NIDN: {{ cl.lecturer.nidn }}
            </span>
            <span v-else-if="cl.lecturer?.nip" class="block font-mono text-2xs text-slate-500">
              NIP: {{ cl.lecturer.nip }}
            </span>

            <div class="mt-2">
              <Badge :variant="getRoleBadge(cl.role).variant" size="xs">
                {{ getRoleBadge(cl.role).label }}
              </Badge>
            </div>
          </div>
        </div>

        <div v-if="!isReadOnly && can('classes.assign_lecturer')" class="pt-3 mt-3 border-t border-slate-100 flex items-center justify-end">
          <Button
            variant="ghost"
            size="xs"
            class="text-rose-600 hover:bg-rose-50"
            @click="emit('remove-lecturer', cl.lecturer_id, cl.lecturer?.full_name || 'Dosen')"
          >
            <Trash2 class="w-3.5 h-3.5" />
            <span>Lepas Tugas</span>
          </Button>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <EmptyState
      v-else
      :icon="UserCheck"
      title="Belum Ada Dosen Pengampu"
      description="Kelas ini belum ditugaskan kepada dosen pengampu manapun."
    >
      <template #action>
        <Button
          v-if="can('classes.assign_lecturer') && !isReadOnly"
          variant="primary"
          size="sm"
          @click="emit('add-lecturer')"
        >
          <Plus class="w-3.5 h-3.5" />
          <span>Tugaskan Dosen Sekarang</span>
        </Button>
      </template>
    </EmptyState>
  </div>
</template>
