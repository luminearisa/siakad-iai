<script setup lang="ts">
import type { Curriculum } from '@/types/curriculum'
import Card from '@/components/ui/Card.vue'
import CurriculumStatusBadge from '../components/CurriculumStatusBadge.vue'
import { formatDate } from '@/utils/format'

interface Props {
  curriculum: Curriculum
}

defineProps<Props>()
</script>

<template>
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    <!-- Identitas Kurikulum -->
    <Card>
      <template #header>
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
          Dokumen & Identitas Kurikulum
        </h3>
      </template>

      <dl class="divide-y divide-slate-100 text-xs">
        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">Kode Kurikulum</dt>
          <dd class="col-span-2 font-mono font-bold text-slate-900">{{ curriculum.code }}</dd>
        </div>

        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">Nama Kurikulum</dt>
          <dd class="col-span-2 font-bold text-slate-900">{{ curriculum.name }}</dd>
        </div>

        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">Program Studi</dt>
          <dd class="col-span-2 font-semibold text-slate-800">
            {{ curriculum.study_program?.name || '-' }} ({{ curriculum.study_program?.degree || '-' }})
          </dd>
        </div>

        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">Fakultas</dt>
          <dd class="col-span-2 text-slate-800">{{ curriculum.study_program?.faculty?.name || '-' }}</dd>
        </div>

        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">Versi</dt>
          <dd class="col-span-2 text-slate-800">{{ curriculum.version || '-' }}</dd>
        </div>

        <div class="py-2.5 grid grid-cols-3 gap-2">
          <dt class="text-slate-500 font-medium">Status Dokumen</dt>
          <dd class="col-span-2">
            <CurriculumStatusBadge :status="curriculum.status" size="xs" />
          </dd>
        </div>
      </dl>
    </Card>

    <!-- Masa Berlaku & Statistik -->
    <div class="space-y-5">
      <Card>
        <template #header>
          <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
            Periode Keberlakuan & Total Beban SKS
          </h3>
        </template>

        <dl class="divide-y divide-slate-100 text-xs">
          <div class="py-2.5 grid grid-cols-3 gap-2">
            <dt class="text-slate-500 font-medium">Tahun Berlaku</dt>
            <dd class="col-span-2 text-slate-900 font-semibold">
              {{ curriculum.start_year || '-' }} s/d {{ curriculum.end_year || 'Seterusnya' }}
            </dd>
          </div>

          <div class="py-2.5 grid grid-cols-3 gap-2">
            <dt class="text-slate-500 font-medium">Tanggal Efektif</dt>
            <dd class="col-span-2 text-slate-800">{{ formatDate(curriculum.effective_date) }}</dd>
          </div>

          <div class="py-2.5 grid grid-cols-3 gap-2">
            <dt class="text-slate-500 font-medium">Tanggal Kedaluwarsa</dt>
            <dd class="col-span-2 text-slate-800">{{ formatDate(curriculum.expiry_date) }}</dd>
          </div>

          <div class="py-2.5 grid grid-cols-3 gap-2">
            <dt class="text-slate-500 font-medium">Total SKS Kurikulum</dt>
            <dd class="col-span-2 font-bold text-brand-900 text-sm">
              {{ curriculum.total_credits || 0 }} SKS
            </dd>
          </div>
        </dl>
      </Card>

      <Card v-if="curriculum.description">
        <template #header>
          <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
            Deskripsi & Landasan Kurikulum
          </h3>
        </template>
        <p class="text-xs text-slate-700 leading-relaxed whitespace-pre-line">{{ curriculum.description }}</p>
      </Card>
    </div>
  </div>
</template>
