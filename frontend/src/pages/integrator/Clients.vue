<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { Copy, KeyRound, Plus, RefreshCw, Trash2, Ban, Pencil } from 'lucide-vue-next'
import { integratorService } from '@/services/api/integrator'
import { useToast } from '@/composables/useToast'
import { usePermissions } from '@/composables/usePermissions'
import type { ApiClient, ApiKey, ApiKeyScopeCatalog, ApiClientPayload } from '@/types/integrator'
import PageContainer from '@/components/data-display/PageContainer.vue'
import PageHeader from '@/components/data-display/PageHeader.vue'
import DataTable, { type Column } from '@/components/data-display/DataTable.vue'
import ExportMenu, { type ExportOption } from '@/components/data-display/ExportMenu.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Badge from '@/components/ui/Badge.vue'
import Checkbox from '@/components/ui/Checkbox.vue'
import Drawer from '@/components/ui/Drawer.vue'
import Modal from '@/components/ui/Modal.vue'
import ConfirmModal from '@/components/feedback/ConfirmModal.vue'

const toast = useToast()
const { can } = usePermissions()

const loading = ref(false)
const saving = ref(false)
const clients = ref<ApiClient[]>([])
const scopeCatalog = ref<ApiKeyScopeCatalog | null>(null)

const clientDrawerOpen = ref(false)
const editingClientId = ref<number | null>(null)
const clientForm = reactive({
  name: '',
  slug: '',
  contact_email: '',
  description: '',
  allowed_ips: '',
  rate_limit_per_minute: 120,
  is_active: true,
})

const keysDrawerOpen = ref(false)
const selectedClient = ref<ApiClient | null>(null)
const keys = ref<ApiKey[]>([])
const keysLoading = ref(false)

const issueModalOpen = ref(false)
const issueForm = reactive({
  name: 'Kunci Integrator',
  scopes: [] as string[],
  expires_at: '',
})

const issuedToken = ref<string | null>(null)
const issuedWarning = ref('')
const tokenModalOpen = ref(false)

const confirm = reactive({
  open: false,
  kind: '' as 'client' | 'key' | 'revoke',
  targetId: 0,
  title: '',
  message: '',
  loading: false,
})

const canManageClients = computed(() => can('integrator.clients.manage'))
const canManageKeys = computed(() => can('integrator.keys.manage'))

const clientColumns: Column<ApiClient>[] = [
  { key: 'name', label: 'Nama Klien' },
  { key: 'slug', label: 'Slug' },
  { key: 'is_active', label: 'Status', align: 'center' },
  { key: 'allowed_ips', label: 'Allow-list IP' },
  { key: 'rate_limit_per_minute', label: 'Limit/menit', align: 'right' },
  { key: 'keys_count', label: 'Kunci', align: 'center' },
  { key: 'last_used_at', label: 'Terakhir dipakai' },
]

const keyColumns: Column<ApiKey>[] = [
  { key: 'name', label: 'Nama Kunci' },
  { key: 'key_prefix', label: 'Prefix' },
  { key: 'status_label', label: 'Status', align: 'center' },
  { key: 'scopes', label: 'Scope' },
  { key: 'request_count', label: 'Permintaan', align: 'right' },
  { key: 'last_used_at', label: 'Terakhir dipakai' },
]

const groupedScopes = computed(() => {
  const groups = scopeCatalog.value?.groups ?? {}
  const scopes = scopeCatalog.value?.scopes ?? []

  return Object.entries(groups).map(([group, values]) => ({
    group,
    items: scopes.filter((scope) => values.includes(scope.value)),
  }))
})

function errorMessage(error: unknown, fallback: string): string {
  const err = error as { message?: string }
  return err?.message || fallback
}

async function fetchClients() {
  loading.value = true
  try {
    const response = await integratorService.listClients({ per_page: 50 })
    clients.value = response.data || []
  } catch (error) {
    toast.error(errorMessage(error, 'Gagal memuat daftar klien API'))
  } finally {
    loading.value = false
  }
}

async function fetchScopes() {
  try {
    const response = await integratorService.scopes()
    scopeCatalog.value = response.data
  } catch {
    scopeCatalog.value = null
  }
}

function openCreateClient() {
  editingClientId.value = null
  clientForm.name = ''
  clientForm.slug = ''
  clientForm.contact_email = ''
  clientForm.description = ''
  clientForm.allowed_ips = ''
  clientForm.rate_limit_per_minute = 120
  clientForm.is_active = true
  clientDrawerOpen.value = true
}

function openEditClient(client: ApiClient) {
  editingClientId.value = client.id
  clientForm.name = client.name
  clientForm.slug = client.slug
  clientForm.contact_email = client.contact_email || ''
  clientForm.description = client.description || ''
  clientForm.allowed_ips = (client.allowed_ips || []).join(', ')
  clientForm.rate_limit_per_minute = client.rate_limit_per_minute
  clientForm.is_active = client.is_active
  clientDrawerOpen.value = true
}

async function saveClient() {
  if (!clientForm.name.trim()) {
    toast.error('Nama klien wajib diisi')
    return
  }

  saving.value = true
  try {
    const payload: ApiClientPayload = {
      name: clientForm.name.trim(),
      slug: clientForm.slug.trim() || null,
      contact_email: clientForm.contact_email.trim() || null,
      description: clientForm.description.trim() || null,
      is_active: clientForm.is_active,
      rate_limit_per_minute: Number(clientForm.rate_limit_per_minute) || 120,
      allowed_ips: clientForm.allowed_ips
        .split(',')
        .map((value) => value.trim())
        .filter((value) => value.length > 0),
    }

    if (editingClientId.value) {
      await integratorService.updateClient(editingClientId.value, payload)
      toast.success('Klien API diperbarui')
    } else {
      await integratorService.createClient(payload)
      toast.success('Klien API dibuat')
    }

    clientDrawerOpen.value = false
    await fetchClients()
  } catch (error) {
    toast.error(errorMessage(error, 'Gagal menyimpan klien API'))
  } finally {
    saving.value = false
  }
}

async function openKeys(client: ApiClient) {
  selectedClient.value = client
  keysDrawerOpen.value = true
  await loadKeys(client.id)
}

async function loadKeys(clientId: number | string) {
  keysLoading.value = true
  try {
    const response = await integratorService.listClientKeys(clientId, { per_page: 50 })
    keys.value = response.data || []
  } catch (error) {
    toast.error(errorMessage(error, 'Gagal memuat kunci API'))
  } finally {
    keysLoading.value = false
  }
}

function openIssueModal() {
  issueForm.name = 'Kunci Integrator'
  issueForm.scopes = scopeCatalog.value?.defaults ? [...scopeCatalog.value.defaults] : []
  issueForm.expires_at = ''
  issueModalOpen.value = true
}

async function issueKey() {
  if (!selectedClient.value) return

  if (issueForm.scopes.length === 0) {
    toast.error('Pilih minimal satu scope')
    return
  }

  saving.value = true
  try {
    const response = await integratorService.issueKey(selectedClient.value.id, {
      name: issueForm.name.trim() || 'Kunci Integrator',
      scopes: issueForm.scopes,
      expires_at: issueForm.expires_at || null,
    })

    issueModalOpen.value = false
    issuedToken.value = response.data.plain_key
    issuedWarning.value = response.data.warning
    tokenModalOpen.value = true
    await loadKeys(selectedClient.value.id)
    await fetchClients()
  } catch (error) {
    toast.error(errorMessage(error, 'Gagal menerbitkan kunci API'))
  } finally {
    saving.value = false
  }
}

async function rotateKey(key: ApiKey) {
  saving.value = true
  try {
    const response = await integratorService.rotateKey(key.id, { reason: 'Rotasi manual dari UI' })
    issuedToken.value = response.data.plain_key
    issuedWarning.value = response.data.warning
    tokenModalOpen.value = true
    if (selectedClient.value) {
      await loadKeys(selectedClient.value.id)
    }
    toast.success('Kunci dirotasi. Token lama langsung tidak berlaku.')
  } catch (error) {
    toast.error(errorMessage(error, 'Gagal merotasi kunci API'))
  } finally {
    saving.value = false
  }
}

function askDeleteClient(client: ApiClient) {
  confirm.kind = 'client'
  confirm.targetId = client.id
  confirm.title = 'Hapus klien API'
  confirm.message = `Hapus klien "${client.name}"? Klien dengan kunci aktif tidak dapat dihapus.`
  confirm.open = true
}

function askRevokeKey(key: ApiKey) {
  confirm.kind = 'revoke'
  confirm.targetId = key.id
  confirm.title = 'Cabut kunci API'
  confirm.message = `Cabut kunci "${key.name}" (${key.key_prefix})? Sistem yang memakainya akan langsung kehilangan akses.`
  confirm.open = true
}

function askDeleteKey(key: ApiKey) {
  confirm.kind = 'key'
  confirm.targetId = key.id
  confirm.title = 'Hapus kunci API'
  confirm.message = `Hapus permanen kunci "${key.key_prefix}"? Hanya kunci yang sudah dicabut yang bisa dihapus.`
  confirm.open = true
}

async function runConfirmedAction() {
  confirm.loading = true
  try {
    if (confirm.kind === 'client') {
      await integratorService.deleteClient(confirm.targetId)
      toast.success('Klien API dihapus')
      await fetchClients()
    } else if (confirm.kind === 'revoke') {
      await integratorService.revokeKey(confirm.targetId, 'Dicabut dari UI SIAKAD')
      toast.success('Kunci API dicabut')
      if (selectedClient.value) await loadKeys(selectedClient.value.id)
    } else if (confirm.kind === 'key') {
      await integratorService.deleteKey(confirm.targetId)
      toast.success('Kunci API dihapus')
      if (selectedClient.value) await loadKeys(selectedClient.value.id)
    }

    confirm.open = false
  } catch (error) {
    toast.error(errorMessage(error, 'Aksi gagal dijalankan'))
  } finally {
    confirm.loading = false
  }
}

async function copyToken() {
  if (!issuedToken.value) return

  try {
    await navigator.clipboard.writeText(issuedToken.value)
    toast.success('Token disalin ke clipboard')
  } catch {
    toast.error('Clipboard tidak tersedia. Salin token secara manual.')
  }
}

function formatDate(value?: string | null): string {
  if (!value) return '-'
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString('id-ID')
}

function statusVariant(status: string): 'success' | 'danger' | 'warning' | 'neutral' {
  switch (status) {
    case 'active':
      return 'success'
    case 'revoked':
      return 'danger'
    case 'expired':
      return 'warning'
    default:
      return 'neutral'
  }
}

/**
 * Unduhan daftar klien & kunci API — berkas pendukung saat audit akses data
 * PDDikti/Neo Feeder. Bila satu klien sedang dibuka kuncinya, kunci klien itu
 * bisa diunduh terpisah.
 */
const exportOptions = computed<ExportOption[]>(() => {
  const options: ExportOption[] = [
    {
      label: 'Klien integrasi — CSV (Excel)',
      description: 'Termasuk jumlah kunci dan kunci aktif',
      run: () => integratorService.exportClients('csv'),
    },
    {
      label: 'Klien integrasi — JSON',
      description: 'Arsip terstruktur untuk tim integrator',
      run: () => integratorService.exportClients('json'),
    },
    {
      label: 'Semua kunci API — CSV (Excel)',
      description: 'Prefix, scope, masa berlaku, pemakaian terakhir (tanpa token)',
      run: () => integratorService.exportKeys({}, 'csv'),
    },
    {
      label: 'Semua kunci API — JSON',
      run: () => integratorService.exportKeys({}, 'json'),
    },
  ]

  if (selectedClient.value) {
    options.splice(2, 0, {
      label: `Kunci: ${selectedClient.value.name} — CSV (Excel)`,
      description: 'Hanya kunci milik klien yang sedang dibuka',
      run: () => integratorService.exportKeys({ api_client_id: selectedClient.value?.id }, 'csv'),
    })
  }

  return options
})

onMounted(async () => {
  await Promise.all([fetchClients(), fetchScopes()])
})
</script>

<template>
  <PageContainer max-width="7xl">
    <PageHeader
      title="Klien & Kunci API"
      subtitle="Sistem eksternal yang boleh menarik data SIAKAD (mis. integrator Neo Feeder PDDikti) beserta kredensialnya."
    >
      <template #actions>
        <ExportMenu :options="exportOptions" :disabled="loading" />
        <Button v-if="canManageClients" variant="primary" size="sm" @click="openCreateClient">
          <Plus class="w-4 h-4 mr-1" />
          Tambah Klien
        </Button>
      </template>
    </PageHeader>

    <Card class="mt-4">
      <div class="flex flex-wrap gap-4 items-center justify-between">
        <div class="flex flex-wrap gap-2">
          <Badge variant="info">Total klien: {{ clients.length }}</Badge>
          <Badge variant="success">
            Kunci aktif: {{ clients.reduce((total, client) => total + (client.active_keys_count || 0), 0) }}
          </Badge>
        </div>
        <p class="text-xs text-slate-500 max-w-xl">
          Token API hanya ditampilkan sekali saat diterbitkan. SIAKAD menyimpan hash-nya, bukan token aslinya.
          Setiap permintaan (termasuk yang ditolak) tercatat di <router-link class="text-brand-900 underline" to="/integrator/logs">Log Akses</router-link>.
        </p>
      </div>
    </Card>

    <div class="mt-4">
      <DataTable
        :columns="clientColumns"
        :rows="clients"
        :loading="loading"
        empty-title="Belum ada klien API"
        empty-description="Tambahkan klien untuk menerbitkan API key bagi aplikasi integrator."
      >
        <template #cell-name="{ row }">
          <div class="font-medium text-slate-900">{{ row.name }}</div>
          <div v-if="row.description" class="text-2xs text-slate-500">{{ row.description }}</div>
          <div v-if="row.contact_email" class="text-2xs text-slate-500">{{ row.contact_email }}</div>
        </template>

        <template #cell-slug="{ value }">
          <code class="text-2xs bg-slate-100 px-1.5 py-0.5 rounded">{{ value }}</code>
        </template>

        <template #cell-is_active="{ value }">
          <Badge :variant="value ? 'success' : 'neutral'" size="xs">{{ value ? 'Aktif' : 'Nonaktif' }}</Badge>
        </template>

        <template #cell-allowed_ips="{ value }">
          <span v-if="value && value.length" class="text-2xs">{{ value.join(', ') }}</span>
          <span v-else class="text-2xs text-slate-500">semua IP</span>
        </template>

        <template #cell-rate_limit_per_minute="{ value }">
          <span class="text-xs">{{ value }}</span>
        </template>

        <template #cell-keys_count="{ row }">
          <Badge variant="info" size="xs">{{ row.active_keys_count || 0 }} / {{ row.keys_count || 0 }}</Badge>
        </template>

        <template #cell-last_used_at="{ value }">
          <span class="text-2xs text-slate-500">{{ formatDate(value) }}</span>
        </template>

        <template #actions="{ row }">
          <div class="flex items-center justify-end gap-1">
            <button
              type="button"
              class="p-1 rounded-xs text-slate-400 hover:text-brand-800 hover:bg-slate-100 transition-colors cursor-pointer"
              title="Kelola kunci API"
              aria-label="Kelola kunci API"
              @click="openKeys(row)"
            >
              <KeyRound class="w-3.5 h-3.5" />
            </button>

            <button
              v-if="canManageClients"
              type="button"
              class="p-1 rounded-xs text-slate-400 hover:text-brand-800 hover:bg-slate-100 transition-colors cursor-pointer"
              title="Edit klien"
              aria-label="Edit klien"
              @click="openEditClient(row)"
            >
              <Pencil class="w-3.5 h-3.5" />
            </button>

            <button
              v-if="canManageClients"
              type="button"
              class="p-1 rounded-xs text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"
              title="Hapus klien"
              aria-label="Hapus klien"
              @click="askDeleteClient(row)"
            >
              <Trash2 class="w-3.5 h-3.5" />
            </button>
          </div>
        </template>
      </DataTable>
    </div>

    <!-- Client form -->
    <Drawer :open="clientDrawerOpen" :title="editingClientId ? 'Edit Klien API' : 'Tambah Klien API'" @update:open="clientDrawerOpen = $event">
      <div class="p-5 space-y-4">
        <div>
          <label class="text-xs font-semibold text-slate-700">Nama klien</label>
          <Input v-model="clientForm.name" placeholder="Integrator Neo Feeder" />
        </div>
        <div>
          <label class="text-xs font-semibold text-slate-700">Slug (opsional)</label>
          <Input v-model="clientForm.slug" placeholder="otomatis dari nama" />
        </div>
        <div>
          <label class="text-xs font-semibold text-slate-700">Email kontak</label>
          <Input v-model="clientForm.contact_email" type="email" placeholder="operator@kampus.ac.id" />
        </div>
        <div>
          <label class="text-xs font-semibold text-slate-700">Deskripsi</label>
          <Input v-model="clientForm.description" placeholder="Jembatan data ke Neo Feeder PDDikti" />
        </div>
        <div>
          <label class="text-xs font-semibold text-slate-700">Allow-list IP (pisahkan dengan koma)</label>
          <Input v-model="clientForm.allowed_ips" placeholder="10.0.0.5, 10.0.0.0/24" />
          <p class="text-2xs text-slate-400 mt-1">Kosongkan bila klien boleh mengakses dari IP mana pun.</p>
        </div>
        <div>
          <label class="text-xs font-semibold text-slate-700">Rate limit per menit</label>
          <Input v-model="clientForm.rate_limit_per_minute" type="number" />
        </div>
        <Checkbox v-model="clientForm.is_active" label="Klien aktif" description="Nonaktifkan untuk memblokir seluruh kunci milik klien ini." />
      </div>

      <template #footer>
        <div class="flex justify-end gap-2 p-4">
          <Button variant="outline" size="sm" @click="clientDrawerOpen = false">Batal</Button>
          <Button variant="primary" size="sm" :loading="saving" @click="saveClient">Simpan</Button>
        </div>
      </template>
    </Drawer>

    <!-- Keys drawer -->
    <Drawer
      :open="keysDrawerOpen"
      :title="selectedClient ? `Kunci API — ${selectedClient.name}` : 'Kunci API'"
      width="720px"
      @update:open="keysDrawerOpen = $event"
    >
      <div class="p-5 space-y-4">
        <div class="flex items-center justify-between">
          <p class="text-xs text-slate-500">
            Kunci yang dicabut tetap tersimpan sebagai riwayat. Rotasi membuat kunci baru dan mencabut yang lama.
          </p>
          <Button v-if="canManageKeys" variant="primary" size="sm" @click="openIssueModal">
            <Plus class="w-4 h-4 mr-1" />
            Terbitkan Kunci
          </Button>
        </div>

        <DataTable
          :columns="keyColumns"
          :rows="keys"
          :loading="keysLoading"
          empty-title="Belum ada kunci"
          empty-description="Terbitkan kunci pertama untuk klien ini."
        >
          <template #cell-key_prefix="{ value }">
            <code class="text-2xs bg-slate-100 px-1.5 py-0.5 rounded">{{ value }}</code>
          </template>

          <template #cell-status_label="{ row }">
            <Badge :variant="statusVariant(row.status)" size="xs">{{ row.status_label }}</Badge>
            <div v-if="row.revoked_at" class="text-2xs text-slate-500">{{ row.revoked_reason }}</div>
          </template>

          <template #cell-scopes="{ row }">
            <div class="flex flex-wrap gap-1 max-w-[240px]">
              <Badge v-for="scope in row.scopes" :key="scope" variant="outline" size="xs">{{ scope }}</Badge>
            </div>
          </template>

          <template #cell-request_count="{ value }">
            <span class="text-xs">{{ value }}</span>
          </template>

          <template #cell-last_used_at="{ row }">
            <span class="text-2xs text-slate-500">{{ formatDate(row.last_used_at) }}</span>
            <span v-if="row.last_used_ip" class="block text-2xs text-slate-400">{{ row.last_used_ip }}</span>
          </template>

          <template #actions="{ row }">
            <div class="flex items-center justify-end gap-1">
              <button
                v-if="canManageKeys && row.status === 'active'"
                type="button"
                class="p-1 rounded-xs text-slate-400 hover:text-amber-600 hover:bg-slate-100 transition-colors cursor-pointer"
                title="Rotasi kunci"
                aria-label="Rotasi kunci"
                @click="rotateKey(row)"
              >
                <RefreshCw class="w-3.5 h-3.5" />
              </button>

              <button
                v-if="canManageKeys && row.status === 'active'"
                type="button"
                class="p-1 rounded-xs text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"
                title="Cabut kunci"
                aria-label="Cabut kunci"
                @click="askRevokeKey(row)"
              >
                <Ban class="w-3.5 h-3.5" />
              </button>

              <button
                v-if="canManageKeys && row.status !== 'active'"
                type="button"
                class="p-1 rounded-xs text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"
                title="Hapus kunci"
                aria-label="Hapus kunci"
                @click="askDeleteKey(row)"
              >
                <Trash2 class="w-3.5 h-3.5" />
              </button>
            </div>
          </template>
        </DataTable>
      </div>
    </Drawer>

    <!-- Issue key -->
    <Modal :open="issueModalOpen" title="Terbitkan Kunci API" size="lg" @update:open="issueModalOpen = $event">
      <div class="p-5 space-y-4">
        <div>
          <label class="text-xs font-semibold text-slate-700">Nama kunci</label>
          <Input v-model="issueForm.name" placeholder="Kunci produksi Neo Feeder" />
        </div>

        <div>
          <label class="text-xs font-semibold text-slate-700">Kedaluwarsa (opsional)</label>
          <Input v-model="issueForm.expires_at" type="date" />
          <p class="text-2xs text-slate-400 mt-1">Biarkan kosong untuk kunci tanpa kedaluwarsa.</p>
        </div>

        <div class="space-y-3">
          <div v-for="group in groupedScopes" :key="group.group">
            <div class="text-2xs font-semibold uppercase tracking-wide text-slate-500 mb-1">{{ group.group }}</div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
              <Checkbox
                v-for="scope in group.items"
                :key="scope.value"
                v-model="issueForm.scopes"
                :value="scope.value"
                :label="scope.value"
                :description="scope.sensitive ? `${scope.label} (data pribadi)` : scope.label"
              />
            </div>
          </div>
        </div>

        <p class="text-2xs text-slate-500">
          Berikan scope sekecil mungkin. Scope <code>students.pii</code> membuka NIK dan alamat asli,
          dan sebaiknya hanya untuk sinkronisasi PDDikti yang memang membutuhkannya.
        </p>
      </div>

      <template #footer>
        <div class="flex justify-end gap-2 p-4">
          <Button variant="outline" size="sm" @click="issueModalOpen = false">Batal</Button>
          <Button variant="primary" size="sm" :loading="saving" @click="issueKey">Terbitkan</Button>
        </div>
      </template>
    </Modal>

    <!-- Token shown once -->
    <Modal :open="tokenModalOpen" title="Simpan token sekarang" size="lg" @update:open="tokenModalOpen = $event">
      <div class="p-5 space-y-4">
        <div class="p-3 rounded-md bg-amber-50 border border-amber-200 text-xs text-amber-800">
          {{ issuedWarning }}
        </div>
        <div class="flex items-center gap-2">
          <code class="flex-1 text-xs bg-slate-900 text-slate-100 p-3 rounded break-all">{{ issuedToken }}</code>
          <Button variant="secondary" size="sm" @click="copyToken">
            <Copy class="w-4 h-4 mr-1" />
            Salin
          </Button>
        </div>
        <p class="text-2xs text-slate-500">
          Tempelkan ke halaman Pengaturan aplikasi integrator sebagai "API key SIAKAD" (header
          <code>X-API-Key</code>). Setelah modal ini ditutup, token tidak dapat ditampilkan lagi.
        </p>
      </div>

      <template #footer>
        <div class="flex justify-end gap-2 p-4">
          <Button variant="primary" size="sm" @click="tokenModalOpen = false">Saya sudah menyimpannya</Button>
        </div>
      </template>
    </Modal>

    <ConfirmModal
      :open="confirm.open"
      :title="confirm.title"
      :message="confirm.message"
      :loading="confirm.loading"
      :variant="confirm.kind === 'revoke' ? 'danger' : 'danger'"
      @update:open="confirm.open = $event"
      @confirm="runConfirmedAction"
    />
  </PageContainer>
</template>
