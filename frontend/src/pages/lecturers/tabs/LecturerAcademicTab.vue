<script setup lang="ts">
import type { Lecturer } from '@/types/lecturer'
import Card from '@/components/ui/Card.vue'
import LecturerStatusBadge from '../components/LecturerStatusBadge.vue'
import { formatDate } from '@/utils/format'

interface Props {
  lecturer: Lecturer
}

defineProps<Props>()
</script>

<template>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <!-- Homebase & Struktur Organisasi -->
    <Card>
      <template #header>
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
          Homebase Program Studi & Fakultas
        </h3>
      </template>

      <dl class="divide-y divide-slate-100 text-xs">
        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">Program Studi</dt>
          <dd class="col-span-2 font-bold text-slate-900">
            {{ lecturer.homebase_study_program?.name || 'Belum Ditentukan' }}
            <span v-if="lecturer.homebase_study_program?.code" class="text-slate-500 font-normal">
              ({{ lecturer.homebase_study_program.code }})
            </span>
          </dd>
        </div>

        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">Jenjang Prodi</dt>
          <dd class="col-span-2 text-slate-800 font-medium">{{ lecturer.homebase_study_program?.degree || '-' }}</dd>
        </div>

        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">Fakultas</dt>
          <dd class="col-span-2 text-slate-800">{{ lecturer.homebase_study_program?.faculty?.name || '-' }}</dd>
        </div>
      </dl>
    </Card>

    <!-- Jabatan & Kepegawaian -->
    <Card>
      <template #header>
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
          Jabatan Fungsional & Status Kepegawaian
        </h3>
      </template>

      <dl class="divide-y divide-slate-100 text-xs">
        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">Status Dosen</dt>
          <dd class="col-span-2">
            <LecturerStatusBadge :status="lecturer.status" size="xs" />
          </dd>
        </div>

        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">Jabatan Fungsional</dt>
          <dd class="col-span-2 font-bold text-slate-900">{{ lecturer.functional_position || 'Tenaga Pengajar' }}</dd>
        </div>

        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">Tanggal Bergabung</dt>
          <dd class="col-span-2 text-slate-800">{{ formatDate(lecturer.join_date) }}</dd>
        </div>
      </dl>
    </Card>
  </div>
</template>
