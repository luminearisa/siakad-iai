<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Download, FileBarChart2, RefreshCw, Table2 } from 'lucide-vue-next'
import { mbkmService } from '@/services/api/mbkm'
import { useToast } from '@/composables/useToast'
import { REPORT_LABELS } from '@/types/mbkm'
import type { MbkmReport } from '@/types/mbkm'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import MbkmFilterBar, { type MbkmFilters } from '@/pages/mbkm/components/MbkmFilterBar.vue'

const toast = useToast()

const loading = ref(false)
const exporting = ref(false)
const types = ref<string[]>([])
const activeType = ref<string>('')
const report = ref<MbkmReport | null>(null)
const filters = ref<MbkmFilters>({})

/** Columns derived from the first row — the backend returns row projections. */
const columns = computed<string[]>(() => {
  const first = report.value?.rows?.[0]
  if (!first) return []
  return Object.keys(first)
})

function columnLabel(key: string) {
  return key
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (c) => c.toUpperCase())
}

function cellValue(row: Record<string, unknown>, key: string): string {
  const value = row[key]
  if (value === null || value === undefined) return '—'
  if (typeof value === 'boolean') return value ? 'Ya' : 'Tidak'
  if (typeof value === 'object') return JSON.stringify(value)
  return String(value)
}

async function loadTypes() {
  try {
    const res = await mbkmService.getReportTypes()
    types.value = res.data || []
    if (types.value.length > 0 && !activeType.value) {
      activeType.value = types.value[0]
      await loadReport()
    }
  } catch (err: any) {
    toast.error(err.message || 'Gagal memuat jenis laporan MBKM')
  }
}

/** Only send filters the user actually set. */
function activeFilterParams(): Record<string, unknown> {
  return Object.fromEntries(
    Object.entries(filters.value).filter(([, v]) => v !== null && v !== undefined && v !== '')
  )
}

async function loadReport() {
  if (!activeType.value) return
  loading.value = true
  try {
    const res = await mbkmService.getReport(activeType.value, activeFilterParams())
    report.value = res.data
  } catch (err: any) {
    toast.error(err.message || 'Gagal membuat laporan MBKM')
    report.value = null
  } finally {
    loading.value = false
  }
}

/**
 * Export the currently selected report with the currently applied filters, so
 * the downloaded CSV matches exactly what is on screen.
 */
async function exportReport() {
  if (!activeType.value) return
  exporting.value = true
  try {
    await mbkmService.exportReport(activeType.value, activeFilterParams())
    toast.success('Laporan berhasil diunduh sebagai CSV.')
  } catch (err: any) {
    toast.error(err.message || 'Gagal mengunduh laporan MBKM')
  } finally {
    exporting.value = false
  }
}

function selectType(type: string) {
  activeType.value = type
  loadReport()
}

function handleFilterChange() {
  loadReport()
}

onMounted(loadTypes)
</script>

<template>
  <PageContainer>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div>
        <h1 class="text-xl font-bold text-slate-900 tracking-tight">Laporan MBKM</h1>
        <p class="text-xs text-slate-500 mt-1">
          Program, pendaftar, peserta, progress, presensi, logbook, penilaian, rekognisi, dan penyelesaian
        </p>
      </div>
      <Button variant="outline" size="sm" :loading="loading" @click="loadReport">
        <RefreshCw class="w-3.5 h-3.5 mr-1" /> Muat Ulang
      </Button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-5">
      <!-- Report type list -->
      <Card class="border border-slate-200/80 shadow-2xs lg:col-span-1">
        <template #header>
          <p class="font-bold text-slate-800 text-sm flex items-center gap-1.5">
            <FileBarChart2 class="w-3.5 h-3.5 text-rose-600" /> Jenis Laporan
          </p>
        </template>
        <nav class="space-y-1">
          <button
            v-for="type in types"
            :key="type"
            type="button"
            class="w-full text-left px-3 py-2 rounded-lg text-xs transition-colors"
            :class="
              activeType === type
                ? 'bg-brand-900 text-white font-semibold'
                : 'text-slate-700 hover:bg-slate-50'
            "
            @click="selectType(type)"
          >
            {{ REPORT_LABELS[type] ?? type }}
          </button>
          <p v-if="types.length === 0" class="text-xs text-slate-400 py-4 text-center">
            Tidak ada laporan tersedia.
          </p>
        </nav>
      </Card>

      <!-- Report content -->
      <div class="lg:col-span-3 space-y-5">
        <Card class="border border-slate-200/80 shadow-2xs">
          <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
              <p class="font-bold text-slate-800 text-sm">
                {{ REPORT_LABELS[activeType] ?? activeType ?? 'Pilih laporan' }}
              </p>
              <p v-if="report?.generated_at" class="text-2xs text-slate-500 mt-0.5">
                Dibuat {{ new Date(report.generated_at).toLocaleString('id-ID') }}
              </p>
            </div>
            <MbkmFilterBar
              v-model="filters"
              :fields="['semester_id', 'faculty_id', 'study_program_id', 'program_id', 'program_type_id', 'partner_id', 'admission_year', 'lecturer_id']"
              @change="handleFilterChange"
            />
          </div>
        </Card>

        <Card class="border border-slate-200/80 shadow-2xs">
          <template #header>
            <p class="font-bold text-slate-800 text-sm flex items-center gap-1.5">
              <Table2 class="w-3.5 h-3.5 text-slate-500" />
              Hasil
              <span v-if="report?.rows" class="font-normal text-slate-500">({{ report.rows.length }} baris)</span>
            </p>
            <Button
              v-if="activeType"
              variant="outline"
              size="sm"
              :loading="exporting"
              :disabled="loading"
              @click="exportReport"
            >
              <Download class="w-3.5 h-3.5 mr-1" /> Export CSV
            </Button>
          </template>

          <div v-if="loading" class="py-12 text-center text-xs text-slate-400">Membuat laporan...</div>

          <div v-else-if="!report" class="py-12 text-center text-xs text-slate-400">
            Pilih jenis laporan untuk menampilkan data.
          </div>

          <!-- Summary report renders the admin dashboard counters -->
          <div v-else-if="report.type === 'summary'" class="space-y-5 text-xs">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
              <div
                v-for="(value, key) in (report.summary?.counters ?? {})"
                :key="key"
                class="p-3 bg-slate-50 border border-slate-200 rounded-lg"
              >
                <p class="text-2xs text-slate-500">{{ columnLabel(String(key)) }}</p>
                <p class="text-xl font-bold text-slate-900 mt-1">{{ value }}</p>
              </div>
            </div>

            <div v-if="(report.summary?.participants_by_study_program ?? []).length > 0">
              <p class="font-bold text-slate-800 mb-2">Peserta per Program Studi</p>
              <div class="space-y-1.5">
                <div
                  v-for="row in report.summary?.participants_by_study_program"
                  :key="row.id"
                  class="flex items-center justify-between py-1.5 px-3 bg-slate-50 rounded-lg border border-slate-200"
                >
                  <span class="text-slate-700">{{ row.name }}</span>
                  <span class="font-bold text-slate-900">{{ row.total }}</span>
                </div>
              </div>
            </div>

            <div v-if="(report.summary?.participants_by_partner ?? []).length > 0">
              <p class="font-bold text-slate-800 mb-2">Peserta per Mitra</p>
              <div class="space-y-1.5">
                <div
                  v-for="row in report.summary?.participants_by_partner"
                  :key="row.id"
                  class="flex items-center justify-between py-1.5 px-3 bg-slate-50 rounded-lg border border-slate-200"
                >
                  <span class="text-slate-700">{{ row.name }}</span>
                  <span class="font-bold text-slate-900">{{ row.total }}</span>
                </div>
              </div>
            </div>

            <div v-if="(report.summary?.action_items ?? []).length > 0">
              <p class="font-bold text-slate-800 mb-2">Butuh Tindakan</p>
              <div class="space-y-1.5">
                <div
                  v-for="item in report.summary?.action_items"
                  :key="item.code"
                  class="flex items-center justify-between py-1.5 px-3 bg-amber-50 rounded-lg border border-amber-200"
                >
                  <span class="text-amber-800">{{ item.label }}</span>
                  <span class="font-bold text-amber-900">{{ item.total }}</span>
                </div>
              </div>
            </div>
          </div>

          <div v-else-if="report.rows.length === 0" class="py-12 text-center text-xs text-slate-400">
            Tidak ada data untuk filter yang dipilih.
          </div>

          <div v-else class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
              <thead>
                <tr class="bg-slate-50/80 text-slate-600 font-semibold border-b border-slate-200 text-3xs uppercase tracking-wider">
                  <th class="py-2.5 px-3 w-12 text-center">NO</th>
                  <th v-for="col in columns" :key="col" class="py-2.5 px-3 whitespace-nowrap">
                    {{ columnLabel(col) }}
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="(row, index) in report.rows" :key="index" class="hover:bg-slate-50/80">
                  <td class="py-2.5 px-3 text-center text-slate-500">{{ index + 1 }}</td>
                  <td v-for="col in columns" :key="col" class="py-2.5 px-3 text-slate-700">
                    {{ cellValue(row, col) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </Card>
      </div>
    </div>
  </PageContainer>
</template>
