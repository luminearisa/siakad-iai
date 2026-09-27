<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Eye, PlayCircle, MapPin, Users2 } from 'lucide-vue-next'
import { mbkmService } from '@/services/api/mbkm'
import { useToast } from '@/composables/useToast'
import { PARTICIPANT_STATUS_LABELS, PARTICIPANT_STATUS_VARIANTS, optionsFrom } from '@/types/mbkm'
import type { MbkmParticipant } from '@/types/mbkm'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import MbkmStatusBadge from '@/pages/mbkm/components/MbkmStatusBadge.vue'
import MbkmFilterBar, { type MbkmFilters } from '@/pages/mbkm/components/MbkmFilterBar.vue'
import ExportMenu from '@/components/data-display/ExportMenu.vue'
import { useFeederExport } from '@/composables/useFeederExport'
// Unduhan data pelaporan PDDikti / Neo Feeder untuk halaman ini.
const { exportOptions } = useFeederExport('activities', 'Aktivitas MBKM', {
  note: 'seluruh peserta MBKM',
})

const router = useRouter()
const toast = useToast()

const loading = ref(false)
const participants = ref<MbkmParticipant[]>([])
const totalData = ref(0)
const perPage = ref(10)
const currentPage = ref(1)
const searchQuery = ref('')
const filters = ref<MbkmFilters>({})

async function loadData() {
  loading.value = true
  try {
    const params: Record<string, unknown> = {
      page: currentPage.value,
      per_page: perPage.value,
      ...Object.fromEntries(
        Object.entries(filters.value).filter(([, v]) => v !== null && v !== undefined && v !== '')
      ),
    }
    if (searchQuery.value.trim()) params.search = searchQuery.value.trim()

    const res = await mbkmService.getParticipants(params)
    participants.value = res.data || []
    totalData.value = res.meta?.total ?? participants.value.length
  } catch (err: any) {
    toast.error(err.message || 'Gagal memuat daftar peserta MBKM')
  } finally {
    loading.value = false
  }
}

async function handleStart(participant: MbkmParticipant) {
  if (!confirm(`Mulai pelaksanaan MBKM untuk ${participant.student?.full_name}?`)) return
  try {
    await mbkmService.startParticipant(participant.id)
    toast.success('Pelaksanaan MBKM dimulai')
    await loadData()
  } catch (err: any) {
    toast.error(err.message || 'Gagal memulai pelaksanaan')
  }
}

function handleFilterChange() {
  currentPage.value = 1
  loadData()
}

function supervisorNames(p: MbkmParticipant) {
  const rows = p.supervisors ?? []
  if (rows.length === 0) return '—'
  return rows
    .map((s) => s.lecturer?.full_name ?? s.external_name ?? '-')
    .join(', ')
}

onMounted(loadData)
</script>

<template>
  <PageContainer>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div>
        <h1 class="text-xl font-bold text-slate-900 tracking-tight">Peserta MBKM</h1>
        <p class="text-xs text-slate-500 mt-1">
          Penempatan, pembimbing, pelaksanaan, hingga penyelesaian peserta
        </p>
      </div>
      <ExportMenu :options="exportOptions" class="ml-auto sm:ml-0" />
      <button
        type="button"
        class="text-xs font-semibold text-brand-900 hover:text-brand-950 flex items-center gap-1.5"
        @click="router.push('/mbkm/applications')"
      >
        <Users2 class="w-3.5 h-3.5" />
        Tetapkan peserta dari pendaftar
      </button>
    </div>

    <Card class="border border-slate-200/80 shadow-2xs overflow-visible">
      <div class="p-4 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-2 text-xs text-slate-600">
          <span>Baris</span>
          <select v-model="perPage" class="px-2.5 py-1 bg-white border border-slate-300 rounded-lg text-xs outline-none" @change="handleFilterChange">
            <option :value="10">10</option>
            <option :value="25">25</option>
            <option :value="50">50</option>
          </select>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
          <MbkmFilterBar
            v-model="filters"
            :fields="['program_id', 'study_program_id', 'partner_id', 'lecturer_id', 'status']"
            :status-options="optionsFrom(PARTICIPANT_STATUS_LABELS)"
            @change="handleFilterChange"
          />
          <div class="flex items-center">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Cari no. peserta / NIM / nama..."
              class="w-48 sm:w-64 px-3 py-1.5 bg-white border border-slate-300 rounded-l-lg text-xs outline-none focus:ring-1 focus:ring-brand-500"
              @keyup.enter="handleFilterChange"
            />
            <button type="button" class="px-3.5 py-1.5 bg-white hover:bg-slate-50 border border-l-0 border-slate-300 rounded-r-lg text-xs font-semibold text-rose-700" @click="handleFilterChange">
              Cari
            </button>
          </div>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
          <thead>
            <tr class="bg-slate-50/80 text-slate-600 font-semibold border-b border-slate-200 text-3xs uppercase tracking-wider">
              <th class="py-3 px-4 w-10 text-center">NO</th>
              <th class="py-3 px-4">NO. PESERTA</th>
              <th class="py-3 px-4">MAHASISWA</th>
              <th class="py-3 px-4">PROGRAM</th>
              <th class="py-3 px-4">PENEMPATAN</th>
              <th class="py-3 px-4">PEMBIMBING</th>
              <th class="py-3 px-4 text-center">NILAI AKHIR</th>
              <th class="py-3 px-4 text-center">SKS DIAKUI</th>
              <th class="py-3 px-4 text-center">STATUS</th>
              <th class="py-3 px-4 text-center w-20">AKSI</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <tr v-if="loading">
              <td colspan="10" class="py-8 text-center text-slate-400">Memuat data peserta...</td>
            </tr>
            <tr v-else-if="participants.length === 0">
              <td colspan="10" class="py-8 text-center text-slate-400">Belum ada peserta MBKM.</td>
            </tr>
            <tr v-for="(item, index) in participants" :key="item.id" class="hover:bg-slate-50/80 transition-colors">
              <td class="py-3.5 px-4 text-center text-slate-500 font-medium">{{ index + 1 }}</td>
              <td class="py-3.5 px-4 font-mono font-semibold text-slate-900">{{ item.participant_number }}</td>
              <td class="py-3.5 px-4">
                <p class="font-semibold text-slate-900">{{ item.student?.full_name ?? '-' }}</p>
                <p class="text-2xs text-slate-500">
                  {{ item.student?.student_number }} • {{ item.student?.study_program?.name ?? '-' }}
                </p>
              </td>
              <td class="py-3.5 px-4">
                <p class="text-slate-800">{{ item.program?.name ?? '-' }}</p>
                <p class="text-2xs text-slate-400">{{ item.program?.program_type?.name ?? '' }}</p>
              </td>
              <td class="py-3.5 px-4">
                <p v-if="item.placement?.partner" class="text-slate-800 flex items-center gap-1">
                  <MapPin class="w-3 h-3 text-rose-500" />
                  {{ item.placement.partner.name }}
                </p>
                <p v-else class="text-slate-400">Belum ditempatkan</p>
                <p v-if="item.placement?.location" class="text-2xs text-slate-500">{{ item.placement.location.name }}</p>
              </td>
              <td class="py-3.5 px-4 text-slate-700 text-2xs">{{ supervisorNames(item) }}</td>
              <td class="py-3.5 px-4 text-center">
                <span v-if="item.final_score != null" class="font-bold text-slate-900">{{ item.final_score }}</span>
                <span v-else class="text-slate-400">—</span>
                <p v-if="item.letter_grade" class="text-2xs font-semibold text-emerald-700">{{ item.letter_grade }}</p>
              </td>
              <td class="py-3.5 px-4 text-center font-semibold text-slate-800">{{ item.recognized_credits ?? 0 }}</td>
              <td class="py-3.5 px-4 text-center">
                <MbkmStatusBadge :value="item.status" :labels="PARTICIPANT_STATUS_LABELS" :variants="PARTICIPANT_STATUS_VARIANTS" />
              </td>
              <td class="py-3.5 px-4">
                <div class="flex items-center justify-center gap-1.5">
                  <button
                    type="button"
                    class="p-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50"
                    title="Detail Peserta"
                    @click="router.push(`/mbkm/participants/${item.id}`)"
                  >
                    <Eye class="w-3.5 h-3.5" />
                  </button>
                  <button
                    v-if="item.status === 'assigned'"
                    type="button"
                    class="p-1.5 rounded-lg border border-emerald-200 text-emerald-600 hover:bg-emerald-50"
                    title="Mulai Pelaksanaan"
                    @click="handleStart(item)"
                  >
                    <PlayCircle class="w-3.5 h-3.5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="p-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
        <div>Total {{ totalData }} peserta</div>
        <div class="flex items-center gap-1">
          <button
            class="w-7 h-7 flex items-center justify-center rounded-lg border border-slate-200 text-slate-400 hover:bg-slate-50 disabled:opacity-40"
            :disabled="currentPage === 1"
            @click="currentPage--; loadData()"
          >
            ‹
          </button>
          <span class="w-7 h-7 flex items-center justify-center rounded-lg bg-brand-900 text-white font-bold text-xs">{{ currentPage }}</span>
          <button
            class="w-7 h-7 flex items-center justify-center rounded-lg border border-slate-200 text-slate-400 hover:bg-slate-50 disabled:opacity-40"
            :disabled="participants.length < perPage"
            @click="currentPage++; loadData()"
          >
            ›
          </button>
        </div>
      </div>
    </Card>
  </PageContainer>
</template>
