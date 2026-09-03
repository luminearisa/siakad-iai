<script setup lang="ts">
import { computed } from 'vue'
import { Users, Calendar, FileCheck, AlertCircle } from 'lucide-vue-next'
import Card from '@/components/ui/Card.vue'

const props = withDefaults(
  defineProps<{
    totalAdvisees?: number
    activeAdvisees?: number
    totalSessions?: number
    pendingKrsCount?: number
    attentionCount?: number
  }>(),
  {
    totalAdvisees: 0,
    activeAdvisees: 0,
    totalSessions: 0,
    pendingKrsCount: 0,
    attentionCount: 0,
  }
)

const activeRate = computed(() => {
  if (props.totalAdvisees === 0) return 100
  return Math.round((props.activeAdvisees / props.totalAdvisees) * 100)
})
</script>

<template>
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
    <!-- Card 1: Total Mahasiswa Bimbingan -->
    <Card class="!p-3 border-slate-200">
      <div class="flex items-center justify-between">
        <div>
          <span class="text-2xs font-semibold uppercase tracking-wider text-slate-500 block">
            Mahasiswa Bimbingan
          </span>
          <div class="mt-1 flex items-baseline gap-1.5">
            <span class="text-xl font-bold text-slate-900">{{ activeAdvisees }}</span>
            <span class="text-xs text-slate-400 font-normal">/ {{ totalAdvisees }} Total</span>
          </div>
        </div>
        <div class="p-2 rounded-md bg-brand-50 text-brand-800">
          <Users class="w-4 h-4" />
        </div>
      </div>
      <div class="mt-2 text-2xs text-slate-500">
        <span class="font-medium text-brand-900">{{ activeRate }}%</span> status penugasan aktif
      </div>
    </Card>

    <!-- Card 2: Sesi Bimbingan -->
    <Card class="!p-3 border-slate-200">
      <div class="flex items-center justify-between">
        <div>
          <span class="text-2xs font-semibold uppercase tracking-wider text-slate-500 block">
            Konsultasi / Sesi
          </span>
          <div class="mt-1 flex items-baseline gap-1.5">
            <span class="text-xl font-bold text-slate-900">{{ totalSessions }}</span>
            <span class="text-xs text-slate-400 font-normal">Sesi</span>
          </div>
        </div>
        <div class="p-2 rounded-md bg-emerald-50 text-emerald-700">
          <Calendar class="w-4 h-4" />
        </div>
      </div>
      <div class="mt-2 text-2xs text-slate-500">
        Riwayat catatan bimbingan akademik
      </div>
    </Card>

    <!-- Card 3: KRS Perlu Review -->
    <Card class="!p-3 border-slate-200">
      <div class="flex items-center justify-between">
        <div>
          <span class="text-2xs font-semibold uppercase tracking-wider text-slate-500 block">
            KRS Menunggu Review
          </span>
          <div class="mt-1 flex items-baseline gap-1.5">
            <span class="text-xl font-bold text-amber-600">{{ pendingKrsCount }}</span>
            <span class="text-xs text-slate-400 font-normal">Mahasiswa</span>
          </div>
        </div>
        <div class="p-2 rounded-md bg-amber-50 text-amber-600">
          <FileCheck class="w-4 h-4" />
        </div>
      </div>
      <div class="mt-2 text-2xs text-slate-500">
        <span v-if="pendingKrsCount > 0" class="text-amber-700 font-medium">Perlu persetujuan Dosen PA</span>
        <span v-else class="text-emerald-700 font-medium">Semua KRS telah ditinjau</span>
      </div>
    </Card>

    <!-- Card 4: Perhatian Akademik -->
    <Card class="!p-3 border-slate-200">
      <div class="flex items-center justify-between">
        <div>
          <span class="text-2xs font-semibold uppercase tracking-wider text-slate-500 block">
            Perlu Perhatian
          </span>
          <div class="mt-1 flex items-baseline gap-1.5">
            <span class="text-xl font-bold" :class="attentionCount > 0 ? 'text-rose-600' : 'text-slate-900'">
              {{ attentionCount }}
            </span>
            <span class="text-xs text-slate-400 font-normal">Mahasiswa</span>
          </div>
        </div>
        <div class="p-2 rounded-md" :class="attentionCount > 0 ? 'bg-rose-50 text-rose-600' : 'bg-slate-100 text-slate-600'">
          <AlertCircle class="w-4 h-4" />
        </div>
      </div>
      <div class="mt-2 text-2xs text-slate-500">
        KRS bermasalah / non-aktif
      </div>
    </Card>
  </div>
</template>
