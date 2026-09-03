<script setup lang="ts">
import type { Lecturer } from '@/types/lecturer'
import Card from '@/components/ui/Card.vue'
import { formatDate } from '@/utils/format'

interface Props {
  lecturer: Lecturer
}

defineProps<Props>()
</script>

<template>
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    <!-- 1. Biodata & Identitas -->
    <Card>
      <template #header>
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
          Data Identitas Pribadi
        </h3>
      </template>

      <dl class="divide-y divide-slate-100 text-xs">
        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">Nama Lengkap</dt>
          <dd class="col-span-2 font-bold text-slate-900">{{ lecturer.full_name }}</dd>
        </div>

        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">Gelar Akademik</dt>
          <dd class="col-span-2 font-medium text-slate-800">{{ lecturer.academic_degree || '-' }}</dd>
        </div>

        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">NIDN</dt>
          <dd class="col-span-2 font-mono font-semibold text-slate-900">{{ lecturer.nidn || '-' }}</dd>
        </div>

        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">NIDK</dt>
          <dd class="col-span-2 font-mono text-slate-800">{{ lecturer.nidk || '-' }}</dd>
        </div>

        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">NIP</dt>
          <dd class="col-span-2 font-mono text-slate-800">{{ lecturer.nip || '-' }}</dd>
        </div>

        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">Kode Dosen</dt>
          <dd class="col-span-2 font-mono text-slate-800">{{ lecturer.lecturer_number || '-' }}</dd>
        </div>

        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">Jenis Kelamin</dt>
          <dd class="col-span-2 text-slate-800 capitalize">{{ lecturer.gender === 'male' ? 'Laki-Laki' : 'Perempuan' }}</dd>
        </div>

        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">Tempat, Tgl Lahir</dt>
          <dd class="col-span-2 text-slate-800">
            {{ lecturer.birth_place || '-' }}, {{ formatDate(lecturer.birth_date) }}
          </dd>
        </div>
      </dl>
    </Card>

    <!-- 2. Kontak & Alamat -->
    <div class="space-y-5">
      <Card>
        <template #header>
          <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
            Kontak & Alamat Domisili
          </h3>
        </template>

        <dl class="divide-y divide-slate-100 text-xs">
          <div class="py-2.5 grid grid-cols-3 gap-2">
            <dt class="text-slate-500 font-medium">Alamat Email</dt>
            <dd class="col-span-2 font-medium text-slate-900">{{ lecturer.email || '-' }}</dd>
          </div>

          <div class="py-2.5 grid grid-cols-3 gap-2">
            <dt class="text-slate-500 font-medium">No. Telepon / HP</dt>
            <dd class="col-span-2 font-medium text-slate-900">{{ lecturer.phone || '-' }}</dd>
          </div>

          <div class="py-2.5 grid grid-cols-3 gap-2">
            <dt class="text-slate-500 font-medium">Alamat Lengkap</dt>
            <dd class="col-span-2 text-slate-800 leading-relaxed">{{ lecturer.address || '-' }}</dd>
          </div>
        </dl>
      </Card>

      <!-- Catatan Tambahan -->
      <Card v-if="lecturer.notes">
        <template #header>
          <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
            Catatan Tambahan
          </h3>
        </template>
        <p class="text-xs text-slate-700 leading-relaxed whitespace-pre-line">{{ lecturer.notes }}</p>
      </Card>
    </div>
  </div>
</template>
