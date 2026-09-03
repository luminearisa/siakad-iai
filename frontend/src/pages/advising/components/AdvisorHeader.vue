<script setup lang="ts">
import { ArrowLeft, GraduationCap, Building2, UserPlus, MessageSquarePlus } from 'lucide-vue-next'
import type { Lecturer } from '@/types/lecturer'
import Button from '@/components/ui/Button.vue'
import Avatar from '@/components/ui/Avatar.vue'
import { usePermissions } from '@/composables/usePermissions'

defineProps<{
  lecturer: Lecturer
  activeAdviseesCount?: number
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'assign-student'): void
  (e: 'create-session'): void
}>()

const { can } = usePermissions()
</script>

<template>
  <div class="bg-white border border-slate-200 rounded-lg p-4 sm:p-5 shadow-subtle">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <!-- Left: Lecturer info -->
      <div class="flex items-start sm:items-center gap-3.5 min-w-0">
        <Button variant="ghost" size="sm" class="!p-2 -ml-2 text-slate-500 hover:text-slate-900 shrink-0" @click="$router.push('/advising')">
          <ArrowLeft class="w-4 h-4" />
        </Button>

        <Avatar
          :name="lecturer.full_name"
          :src="lecturer.photo_path || undefined"
          size="lg"
          class="shrink-0 ring-2 ring-slate-100"
        />

        <div class="space-y-1 min-w-0">
          <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-base sm:text-lg font-bold text-slate-900 tracking-tight truncate">
              {{ lecturer.full_name }}
            </h1>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-2xs font-semibold bg-brand-50 text-brand-800 border border-brand-200">
              Dosen Pembimbing Akademik
            </span>
          </div>

          <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
            <span class="inline-flex items-center gap-1 font-mono">
              <span class="text-slate-400 font-sans">NIDN:</span>
              <strong class="text-slate-700">{{ lecturer.nidn || '-' }}</strong>
            </span>

            <span v-if="lecturer.lecturer_number" class="inline-flex items-center gap-1 font-mono">
              <span class="text-slate-400 font-sans">NIP/NIDN:</span>
              <strong class="text-slate-700">{{ lecturer.lecturer_number }}</strong>
            </span>

            <span v-if="lecturer.homebase_study_program" class="inline-flex items-center gap-1 truncate">
              <GraduationCap class="w-3.5 h-3.5 text-slate-400 shrink-0" />
              <span>{{ lecturer.homebase_study_program.name }}</span>
            </span>

            <span v-if="lecturer.homebase_study_program?.faculty" class="inline-flex items-center gap-1 truncate">
              <Building2 class="w-3.5 h-3.5 text-slate-400 shrink-0" />
              <span>{{ lecturer.homebase_study_program.faculty.name }}</span>
            </span>
          </div>
        </div>
      </div>

      <!-- Right: Action buttons -->
      <div class="flex flex-wrap items-center gap-2 shrink-0 pt-2 md:pt-0 border-t md:border-t-0 border-slate-100">
        <Button
          v-if="can('advising.create_session')"
          variant="outline"
          size="sm"
          :disabled="loading"
          @click="emit('create-session')"
        >
          <MessageSquarePlus class="w-3.5 h-3.5" />
          <span>Buat Sesi Bimbingan</span>
        </Button>

        <Button
          v-if="can('advising.assign')"
          variant="primary"
          size="sm"
          :disabled="loading"
          @click="emit('assign-student')"
        >
          <UserPlus class="w-3.5 h-3.5" />
          <span>Tambah Mahasiswa Bimbingan</span>
        </Button>
      </div>
    </div>
  </div>
</template>
