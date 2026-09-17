<script setup lang="ts">
import { ArrowLeft, Edit3, RefreshCw, Trash2, Mail, Phone, KeyRound, UserPlus, ShieldCheck, AlertCircle } from 'lucide-vue-next'
import { usePermissions } from '@/composables/usePermissions'
import type { Lecturer } from '@/types/lecturer'
import Avatar from '@/components/ui/Avatar.vue'
import Button from '@/components/ui/Button.vue'
import LecturerStatusBadge from './LecturerStatusBadge.vue'

interface Props {
  lecturer: Lecturer
}

defineProps<Props>()

const emit = defineEmits<{
  (e: 'change-status'): void
  (e: 'delete'): void
  (e: 'create-account'): void
  (e: 'reset-password'): void
}>()

const { can } = usePermissions()
</script>

<template>
  <div class="bg-white border border-slate-200 rounded-lg p-4 sm:p-5 shadow-subtle mb-5">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <!-- Left Profile Context -->
      <div class="flex items-start sm:items-center gap-3.5">
        <Avatar
          :name="lecturer.full_name"
          :src="lecturer.photo_path || undefined"
          size="lg"
          class="shrink-0"
        />

        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-2 mb-1">
            <h1 class="text-base sm:text-lg font-bold text-slate-900 tracking-tight truncate">
              {{ lecturer.full_name }}<span v-if="lecturer.academic_degree">, {{ lecturer.academic_degree }}</span>
            </h1>
            <LecturerStatusBadge :status="lecturer.status" size="xs" />

            <!-- Account Status Badge -->
            <span
              v-if="lecturer.user"
              class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-3xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200"
              title="Akun portal dosen aktif"
            >
              <ShieldCheck class="w-3 h-3 text-emerald-600" />
              Akun Aktif
            </span>
            <span
              v-else
              class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-3xs font-semibold bg-amber-50 text-amber-700 border border-amber-200"
              title="Belum memiliki akun login portal"
            >
              <AlertCircle class="w-3 h-3 text-amber-600" />
              Belum Punya Akun
            </span>
          </div>

          <div class="flex flex-wrap items-center gap-y-1 gap-x-3 text-xs text-slate-500">
            <div v-if="lecturer.nidn" class="font-mono font-medium text-slate-700 bg-slate-100 px-1.5 py-0.5 rounded-sm">
              NIDN: {{ lecturer.nidn }}
            </div>
            <div v-else-if="lecturer.nip" class="font-mono font-medium text-slate-700 bg-slate-100 px-1.5 py-0.5 rounded-sm">
              NIP: {{ lecturer.nip }}
            </div>
            <span v-if="lecturer.nidn || lecturer.nip">•</span>
            <span class="font-medium text-slate-700">
              {{ lecturer.homebase_study_program?.name || 'Homebase Belum Ditentukan' }}
            </span>
            <span v-if="lecturer.functional_position">•</span>
            <span v-if="lecturer.functional_position" class="text-slate-600 font-medium">
              {{ lecturer.functional_position }}
            </span>
          </div>

          <div v-if="lecturer.email || lecturer.phone" class="flex flex-wrap items-center gap-3 mt-2 text-2xs text-slate-500">
            <span v-if="lecturer.email" class="inline-flex items-center gap-1">
              <Mail class="w-3 h-3 text-slate-400" />
              {{ lecturer.email }}
            </span>
            <span v-if="lecturer.phone" class="inline-flex items-center gap-1">
              <Phone class="w-3 h-3 text-slate-400" />
              {{ lecturer.phone }}
            </span>
          </div>
        </div>
      </div>

      <!-- Right Actions -->
      <div class="flex flex-wrap items-center gap-2 pt-3 md:pt-0 border-t md:border-t-0 border-slate-100 shrink-0">
        <router-link to="/lecturers">
          <Button variant="outline" size="sm">
            <ArrowLeft class="w-3.5 h-3.5" />
            <span class="hidden sm:inline">Daftar</span>
          </Button>
        </router-link>

        <!-- Account Action: Ganti Password or Buat Akun -->
        <Button
          v-if="lecturer.user"
          variant="outline"
          size="sm"
          class="border-emerald-300 text-emerald-700 hover:bg-emerald-50 font-medium"
          @click="emit('reset-password')"
        >
          <KeyRound class="w-3.5 h-3.5 text-emerald-600" />
          <span>Ganti Password</span>
        </Button>

        <Button
          v-else
          variant="primary"
          size="sm"
          class="bg-emerald-700 hover:bg-emerald-800 text-white font-medium shadow-2xs"
          @click="emit('create-account')"
        >
          <UserPlus class="w-3.5 h-3.5" />
          <span>Buat Akun Portal</span>
        </Button>

        <Button
          v-if="can('lecturers.change_status')"
          variant="outline"
          size="sm"
          @click="emit('change-status')"
        >
          <RefreshCw class="w-3.5 h-3.5 text-slate-500" />
          <span>Ubah Status</span>
        </Button>

        <router-link v-if="can('lecturers.update')" :to="`/lecturers/${lecturer.id}/edit`">
          <Button variant="outline" size="sm">
            <Edit3 class="w-3.5 h-3.5 text-slate-500" />
            <span>Edit Profil</span>
          </Button>
        </router-link>

        <Button
          v-if="can('lecturers.delete')"
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
