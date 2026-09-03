<script setup lang="ts">
import { ref } from 'vue'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'

const degreeLevels = ref([
  { id: 1, code: 'D3', name: 'Diploma Tiga', level: 5, status: 'active' },
  { id: 2, code: 'D4', name: 'Sarjana Terapan', level: 6, status: 'active' },
  { id: 3, code: 'S1', name: 'Sarjana', level: 6, status: 'active' },
  { id: 4, code: 'S2', name: 'Magister', level: 8, status: 'active' },
  { id: 5, code: 'S3', name: 'Doktor', level: 9, status: 'active' },
  { id: 6, code: 'Profesi', name: 'Profesi', level: 7, status: 'active' },
])
</script>

<template>
  <PageContainer>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div>
        <nav class="flex items-center gap-1.5 text-xs text-slate-400 mb-1">
          <span class="text-brand-600 font-medium">Akademik</span>
        </nav>
        <h1 class="text-xl font-bold text-slate-900">Jenjang Pendidikan</h1>
        <p class="text-xs text-slate-500 mt-0.5">Konfigurasi jenjang studi perguruan tinggi (KKNI)</p>
      </div>
    </div>

    <Card class="border border-slate-200/80 shadow-2xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-100/80 border-b border-slate-200/80 text-slate-600 font-bold uppercase tracking-wider text-3xs">
              <th class="py-3 px-4 w-12 text-center">NO</th>
              <th class="py-3 px-4 w-32">KODE</th>
              <th class="py-3 px-4">NAMA JENJANG</th>
              <th class="py-3 px-4 text-center w-28">LEVEL KKNI</th>
              <th class="py-3 px-4 text-center w-28">STATUS</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <tr
              v-for="(item, idx) in degreeLevels"
              :key="item.id"
              class="hover:bg-slate-50/80 transition-colors"
            >
              <td class="py-3.5 px-4 text-center font-medium text-slate-500">{{ idx + 1 }}</td>
              <td class="py-3.5 px-4 font-mono font-bold text-slate-900">{{ item.code }}</td>
              <td class="py-3.5 px-4 font-bold text-slate-900">{{ item.name }}</td>
              <td class="py-3.5 px-4 text-center font-mono font-semibold text-slate-700">Level {{ item.level }}</td>
              <td class="py-3.5 px-4 text-center">
                <span class="inline-flex items-center justify-center px-4 py-1 rounded text-3xs font-bold text-white bg-emerald-600 shadow-2xs">
                  Aktif
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </Card>
  </PageContainer>
</template>
