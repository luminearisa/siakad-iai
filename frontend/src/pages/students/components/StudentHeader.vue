<script setup lang="ts">
import { ArrowLeft, Edit3, RefreshCw, Trash2, Mail, Phone, KeyRound, UserPlus, ShieldCheck, AlertCircle } from 'lucide-vue-next'
import { usePermissions } from '@/composables/usePermissions'
import type { Student } from '@/types/student'
import Avatar from '@/components/ui/Avatar.vue'
import Button from '@/components/ui/Button.vue'
import StudentStatusBadge from './StudentStatusBadge.vue'

interface Props {
  student: Student
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
          :name="student.full_name"
          :src="student.photo_path || undefined"
          size="lg"
          class="shrink-0"
        />

        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-2 mb-1">
            <h1 class="text-base sm:text-lg font-bold text-slate-900 tracking-tight truncate">
              {{ student.full_name }}
            </h1>
            <span v-if="student.nickname" class="text-xs text-slate-500 font-normal">
              ({{ student.nickname }})
            </span>
            <StudentStatusBadge :status="student.status" size="xs" />

            <!-- Account Status Badge -->
            <span
              v-if="student.user"
              class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-3xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200"
              title="Akun portal mahasiswa aktif"
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
            <div class="font-mono font-medium text-slate-700 bg-slate-100 px-1.5 py-0.5 rounded-sm">
              NIM: {{ student.student_number }}
            </div>
            <span>•</span>
            <span class="font-medium text-slate-700">
              {{ student.study_program?.name || 'Program Studi' }}
            </span>
            <span v-if="student.admission_year">•</span>
            <span v-if="student.admission_year">Angkatan {{ student.admission_year }}</span>
          </div>

          <div v-if="student.email || student.phone" class="flex flex-wrap items-center gap-3 mt-2 text-2xs text-slate-500">
            <span v-if="student.email" class="inline-flex items-center gap-1">
              <Mail class="w-3 h-3 text-slate-400" />
              {{ student.email }}
            </span>
            <span v-if="student.phone" class="inline-flex items-center gap-1">
              <Phone class="w-3 h-3 text-slate-400" />
              {{ student.phone }}
            </span>
          </div>
        </div>
      </div>

      <!-- Right Actions -->
      <div class="flex flex-wrap items-center gap-2 pt-3 md:pt-0 border-t md:border-t-0 border-slate-100 shrink-0">
        <router-link to="/students">
          <Button variant="outline" size="sm">
            <ArrowLeft class="w-3.5 h-3.5" />
            <span class="hidden sm:inline">Daftar</span>
          </Button>
        </router-link>

        <!-- Account Action: Ganti Password or Buat Akun -->
        <Button
          v-if="student.user"
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
          v-if="can('students.change_status')"
          variant="outline"
          size="sm"
          @click="emit('change-status')"
        >
          <RefreshCw class="w-3.5 h-3.5 text-slate-500" />
          <span>Ubah Status</span>
        </Button>

        <router-link v-if="can('students.update')" :to="`/students/${student.id}/edit`">
          <Button variant="outline" size="sm">
            <Edit3 class="w-3.5 h-3.5 text-slate-500" />
            <span>Edit Profil</span>
          </Button>
        </router-link>

        <Button
          v-if="can('students.delete')"
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
