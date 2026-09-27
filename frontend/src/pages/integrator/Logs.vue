<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RefreshCw, Search } from 'lucide-vue-next'
import { integratorService } from '@/services/api/integrator'
import { useToast } from '@/composables/useToast'
import type { ApiClient, ApiRequestLog, IntegratorLogStats } from '@/types/integrator'
import PageContainer from '@/components/data-display/PageContainer.vue'
import PageHeader from '@/components/data-display/PageHeader.vue'
import DataTable, { type Column } from '@/components/data-display/DataTable.vue'
import Pagination from '@/components/data-display/Pagination.vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'

const toast = useToast()

const loading = ref(false)
const logs = ref<ApiRequestLog[]>([])
const stats = ref<IntegratorLogStats | null>(null)
const clients = ref<ApiClient[]>([])

const filters = reactive({
  api_client_id: '' as string | number,
  status: '' as '' | 'success' | 'failed',
  search: '',
  from: '',
  to: '',
  days: 7,
})

const meta = reactive({
  current_page: 1,
  last_page: 1,
  total: 0,
  per_page: 25,
  from: 0 as number | null,
  to: 0 as number | null,
})

const columns: Column<ApiRequestLog>[] = [
  { key: 'created_at', label: 'Waktu' },
  { key: 'method', label: 'Metode', align: 'center' },
  { key: 'path', label: 'Endpoint' },
  { key: 'status_code', label: 'Status', align: 'center' },
  { key: 'duration_ms', label: 'Durasi', align: 'right' },
  { key: 'ip_address', label: 'IP' },
  { key: 'client', label: 'Klien' },
  { key: 'error_message', label: 'Pesan' },
]

const clientOptions = computed(() => [
  { value: '', label: 'Semua klien' },
  ...clients.value.map((client) => ({ value: client.id, label: `${client.name} (${client.slug})` })),
])

const statusOptions = [
  { value: '', label: 'Semua status' },
  { value: 'success', label: 'Berhasil (2xx)' },
  { value: 'failed', label: 'Gagal (4xx/5xx)' },
]

function errorMessage(error: unknown, fallback: string): string {
  const err = error as { message?: string }
  return err?.message || fallback
}

function formatDate(value?: string | null): string {
  if (!value) return '-'
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString('id-ID')
}

function statusVariant(code: number): 'success' | 'danger' | 'warning' {
  if (code >= 200 && code < 300) return 'success'
  if (code === 429) return 'warning'
  return 'danger'
}

async function fetchLogs(page = 1) {
  loading.value = true
  try {
    const response = await integratorService.logs({
      page,
      per_page: meta.per_page,
      api_client_id: filters.api_client_id || undefined,
      successful: filters.status === '' ? undefined : filters.status === 'success',
      search: filters.search || undefined,
      from: filters.from || undefined,
      to: filters.to || undefined,
      sort: 'created_at',
      direction: 'desc',
    })

    logs.value = response.data || []
    meta.current_page = response.meta?.current_page ?? 1
    meta.last_page = response.meta?.last_page ?? 1
    meta.total = response.meta?.total ?? 0
    meta.per_page = response.meta?.per_page ?? meta.per_page
    meta.from = response.meta?.from ?? null
    meta.to = response.meta?.to ?? null
  } catch (error) {
    toast.error(errorMessage(error, 'Gagal memuat log akses'))
  } finally {
    loading.value = false
  }
}

async function fetchStats() {
  try {
    const response = await integratorService.logStats({ days: filters.days })
    stats.value = response.data
  } catch {
    stats.value = null
  }
}

async function fetchClients() {
  try {
    const response = await integratorService.listClients({ per_page: 100 })
    clients.value = response.data || []
  } catch {
    clients.value = []
  }
}

async function applyFilters() {
  await fetchLogs(1)
}

async function resetFilters() {
  filters.api_client_id = ''
  filters.status = ''
  filters.search = ''
  filters.from = ''
  filters.to = ''
  await fetchLogs(1)
}

async function refreshAll() {
  await Promise.all([fetchStats(), fetchLogs(meta.current_page)])
}

onMounted(async () => {
  await Promise.all([fetchClients(), fetchStats(), fetchLogs(1)])
})
</script>

<template>
  <PageContainer max-width="7xl">
    <PageHeader
      title="Log Akses Integrasi"
      subtitle="Setiap permintaan ke endpoint integrasi — termasuk yang ditolak karena scope, IP, atau rate limit."
    >
      <template #actions>
        <Button variant="secondary" size="sm" :loading="loading" @click="refreshAll">
          <RefreshCw class="w-4 h-4 mr-1" />
          Muat ulang
        </Button>
      </template>
    </PageHeader>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mt-4">
      <Card dense>
        <div class="text-2xs uppercase tracking-wide text-slate-500">Permintaan ({{ stats?.window_days ?? filters.days }} hari)</div>
        <div class="text-xl font-semibold text-slate-900">{{ stats?.totals.requests ?? 0 }}</div>
      </Card>
      <Card dense>
        <div class="text-2xs uppercase tracking-wide text-slate-500">Tingkat keberhasilan</div>
        <div class="text-xl font-semibold text-emerald-700">{{ stats?.totals.success_rate ?? 0 }}%</div>
      </Card>
      <Card dense>
        <div class="text-2xs uppercase tracking-wide text-slate-500">Rata-rata durasi</div>
        <div class="text-xl font-semibold text-slate-900">{{ stats?.totals.avg_duration_ms ?? 0 }} ms</div>
      </Card>
      <Card dense>
        <div class="text-2xs uppercase tracking-wide text-slate-500">Galat klien / server</div>
        <div class="text-xl font-semibold text-rose-600">
          {{ stats?.totals.client_errors ?? 0 }} / {{ stats?.totals.server_errors ?? 0 }}
        </div>
      </Card>
    </div>

    <Card class="mt-4">
      <div class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
        <div>
          <label class="text-xs font-semibold text-slate-700">Klien</label>
          <Select v-model="filters.api_client_id" :options="clientOptions" searchable />
        </div>
        <div>
          <label class="text-xs font-semibold text-slate-700">Status</label>
          <Select v-model="filters.status" :options="statusOptions" />
        </div>
        <div>
          <label class="text-xs font-semibold text-slate-700">Cari path / IP / pesan</label>
          <Input v-model="filters.search" placeholder="students" />
        </div>
        <div>
          <label class="text-xs font-semibold text-slate-700">Dari tanggal</label>
          <Input v-model="filters.from" type="date" />
        </div>
        <div>
          <label class="text-xs font-semibold text-slate-700">Sampai tanggal</label>
          <Input v-model="filters.to" type="date" />
        </div>
      </div>

      <div class="flex items-center gap-2 mt-4">
        <Button variant="primary" size="sm" @click="applyFilters">
          <Search class="w-4 h-4 mr-1" />
          Terapkan
        </Button>
        <Button variant="outline" size="sm" @click="resetFilters">Reset</Button>
        <span class="text-2xs text-slate-500">{{ meta.total }} baris</span>
      </div>
    </Card>

    <div class="mt-4">
      <DataTable
        :columns="columns"
        :rows="logs"
        :loading="loading"
        empty-title="Belum ada permintaan"
        empty-description="Log akan terisi begitu aplikasi integrator memanggil endpoint dengan API key."
      >
        <template #cell-created_at="{ value }">
          <span class="text-2xs text-slate-500 whitespace-nowrap">{{ formatDate(value) }}</span>
        </template>

        <template #cell-method="{ value }">
          <Badge variant="outline" size="xs">{{ value }}</Badge>
        </template>

        <template #cell-path="{ value }">
          <code class="text-2xs bg-slate-100 px-1.5 py-0.5 rounded">{{ value }}</code>
        </template>

        <template #cell-status_code="{ value }">
          <Badge :variant="statusVariant(value)" size="xs">{{ value }}</Badge>
        </template>

        <template #cell-duration_ms="{ value }">
          <span class="text-2xs">{{ value }} ms</span>
        </template>

        <template #cell-ip_address="{ value }">
          <span class="text-2xs text-slate-500">{{ value || '-' }}</span>
        </template>

        <template #cell-client="{ row }">
          <span class="text-2xs">{{ row.client?.name || '-' }}</span>
          <span v-if="row.key" class="block text-2xs text-slate-500">{{ row.key.key_prefix }}</span>
        </template>

        <template #cell-error_message="{ value }">
          <span v-if="value" class="text-2xs text-rose-600">{{ value }}</span>
          <span v-else class="text-2xs text-slate-500">—</span>
        </template>

        <template #pagination>
          <Pagination
            :current-page="meta.current_page"
            :last-page="meta.last_page"
            :total="meta.total"
            :per-page="meta.per_page"
            :from="meta.from"
            :to="meta.to"
            @page-change="fetchLogs"
          />
        </template>
      </DataTable>
    </div>

    <div v-if="stats && (stats.top_paths.length || stats.recent_errors.length)" class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-4">
      <Card>
        <h3 class="text-sm font-semibold text-slate-800 mb-2">Endpoint tersering</h3>
        <ul class="text-xs text-slate-600 space-y-1">
          <li v-for="path in stats.top_paths" :key="path.path" class="flex justify-between gap-3">
            <code class="text-2xs bg-slate-100 px-1.5 py-0.5 rounded">{{ path.path }}</code>
            <span>{{ path.hits }}×</span>
          </li>
          <li v-if="!stats.top_paths.length" class="text-slate-500">Belum ada data.</li>
        </ul>
      </Card>

      <Card>
        <h3 class="text-sm font-semibold text-slate-800 mb-2">Galat terbaru</h3>
        <ul class="text-xs text-slate-600 space-y-2">
          <li v-for="error in stats.recent_errors" :key="error.id">
            <div class="flex items-center gap-2">
              <Badge variant="danger" size="xs">{{ error.status_code }}</Badge>
              <code class="text-2xs">{{ error.path }}</code>
            </div>
            <div class="text-2xs text-rose-600">{{ error.error_message }}</div>
            <div class="text-2xs text-slate-500">{{ formatDate(error.created_at) }} · {{ error.client || '-' }}</div>
          </li>
          <li v-if="!stats.recent_errors.length" class="text-slate-500">Tidak ada galat pada periode ini.</li>
        </ul>
      </Card>
    </div>
  </PageContainer>
</template>
