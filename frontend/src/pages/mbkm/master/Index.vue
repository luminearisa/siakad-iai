<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { Plus, Edit2, Trash2, Save } from 'lucide-vue-next'
import { mbkmService } from '@/services/api/mbkm'
import { useToast } from '@/composables/useToast'
import { COOPERATION_TYPE_LABELS, PARTNER_TYPE_LABELS, optionsFrom } from '@/types/mbkm'
import type { MbkmCooperation, MbkmPartner, MbkmProgram, MbkmProgramType } from '@/types/mbkm'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Textarea from '@/components/ui/Textarea.vue'
import Modal from '@/components/ui/Modal.vue'
import Tabs, { type TabItem } from '@/components/ui/Tabs.vue'

const toast = useToast()

const activeTab = ref('jenis')
const tabs: TabItem[] = [
  { id: 'jenis', label: 'Jenis Program' },
  { id: 'mitra', label: 'Mitra MBKM' },
  { id: 'kerjasama', label: 'Dokumen Kerja Sama' },
]

const loading = ref(false)
const saving = ref(false)
const programTypes = ref<MbkmProgramType[]>([])
const partners = ref<MbkmPartner[]>([])
const cooperations = ref<MbkmCooperation[]>([])
const programs = ref<MbkmProgram[]>([])

// ---- Program type
const showTypeModal = ref(false)
const editingTypeId = ref<number | null>(null)
const typeForm = reactive({ code: '', name: '', description: '', is_active: true, sort_order: 0 })

// ---- Partner
const showPartnerModal = ref(false)
const editingPartnerId = ref<number | null>(null)
const partnerForm = reactive({
  code: '',
  name: '',
  type: 'company',
  address: '',
  city: '',
  province: '',
  country: 'Indonesia',
  phone: '',
  email: '',
  website: '',
  contact_person_name: '',
  contact_person_position: '',
  contact_person_email: '',
  contact_person_phone: '',
  status: 'active',
  notes: '',
})

// ---- Cooperation
const showCoopModal = ref(false)
const editingCoopId = ref<number | null>(null)
const coopForm = reactive({
  partner_id: null as number | null,
  program_id: null as number | null,
  type: 'mou',
  number: '',
  title: '',
  start_date: '',
  end_date: '',
  status: 'active',
  notes: '',
})

async function loadAll() {
  loading.value = true
  try {
    const [typeRes, partnerRes, coopRes, progRes] = await Promise.all([
      mbkmService.getProgramTypes({ per_page: 200 }),
      mbkmService.getPartners({ per_page: 300 }),
      mbkmService.getCooperations({ per_page: 300 }),
      mbkmService.getPrograms({ per_page: 300 }),
    ])
    programTypes.value = typeRes.data || []
    partners.value = partnerRes.data || []
    cooperations.value = coopRes.data || []
    programs.value = progRes.data || []
  } catch (err: any) {
    toast.error(err.message || 'Gagal memuat data master MBKM')
  } finally {
    loading.value = false
  }
}

// ------------------------------------------------------------------
// Program types
// ------------------------------------------------------------------
function openTypeModal(row?: MbkmProgramType) {
  editingTypeId.value = row?.id ?? null
  Object.assign(typeForm, {
    code: row?.code ?? '',
    name: row?.name ?? '',
    description: row?.description ?? '',
    is_active: row?.is_active ?? true,
    sort_order: row?.sort_order ?? 0,
  })
  showTypeModal.value = true
}

async function handleSaveType() {
  if (!typeForm.code.trim() || !typeForm.name.trim()) {
    toast.error('Kode dan nama jenis program wajib diisi')
    return
  }
  saving.value = true
  try {
    if (editingTypeId.value) {
      await mbkmService.updateProgramType(editingTypeId.value, { ...typeForm })
      toast.success('Jenis program berhasil diperbarui')
    } else {
      await mbkmService.createProgramType({ ...typeForm })
      toast.success('Jenis program berhasil ditambahkan')
    }
    showTypeModal.value = false
    await loadAll()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menyimpan jenis program')
  } finally {
    saving.value = false
  }
}

async function handleDeleteType(row: MbkmProgramType) {
  if (!confirm(`Hapus jenis program "${row.name}"?`)) return
  try {
    await mbkmService.deleteProgramType(row.id)
    toast.success('Jenis program berhasil dihapus')
    await loadAll()
  } catch (err: any) {
    toast.error(err.message || 'Jenis program masih dipakai oleh program lain')
  }
}

// ------------------------------------------------------------------
// Partners
// ------------------------------------------------------------------
function openPartnerModal(row?: MbkmPartner) {
  editingPartnerId.value = row?.id ?? null
  Object.assign(partnerForm, {
    code: row?.code ?? '',
    name: row?.name ?? '',
    type: row?.type ?? 'company',
    address: row?.address ?? '',
    city: row?.city ?? '',
    province: row?.province ?? '',
    country: row?.country ?? 'Indonesia',
    phone: row?.phone ?? '',
    email: row?.email ?? '',
    website: row?.website ?? '',
    contact_person_name: row?.contact_person_name ?? '',
    contact_person_position: row?.contact_person_position ?? '',
    contact_person_email: row?.contact_person_email ?? '',
    contact_person_phone: row?.contact_person_phone ?? '',
    status: row?.status ?? 'active',
    notes: row?.notes ?? '',
  })
  showPartnerModal.value = true
}

async function handleSavePartner() {
  if (!partnerForm.code.trim() || !partnerForm.name.trim()) {
    toast.error('Kode dan nama mitra wajib diisi')
    return
  }
  saving.value = true
  try {
    if (editingPartnerId.value) {
      await mbkmService.updatePartner(editingPartnerId.value, { ...partnerForm })
      toast.success('Mitra berhasil diperbarui')
    } else {
      await mbkmService.createPartner({ ...partnerForm })
      toast.success('Mitra berhasil ditambahkan')
    }
    showPartnerModal.value = false
    await loadAll()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menyimpan mitra')
  } finally {
    saving.value = false
  }
}

async function handleDeletePartner(row: MbkmPartner) {
  if (!confirm(`Hapus mitra "${row.name}"?`)) return
  try {
    await mbkmService.deletePartner(row.id)
    toast.success('Mitra berhasil dihapus')
    await loadAll()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menghapus mitra')
  }
}

// ------------------------------------------------------------------
// Cooperations
// ------------------------------------------------------------------
function openCoopModal(row?: MbkmCooperation) {
  editingCoopId.value = row?.id ?? null
  Object.assign(coopForm, {
    partner_id: row?.partner_id ?? null,
    program_id: row?.program_id ?? null,
    type: row?.type ?? 'mou',
    number: row?.number ?? '',
    title: row?.title ?? '',
    start_date: row?.start_date ?? '',
    end_date: row?.end_date ?? '',
    status: row?.status ?? 'active',
    notes: row?.notes ?? '',
  })
  showCoopModal.value = true
}

async function handleSaveCoop() {
  if (!coopForm.partner_id || !coopForm.title.trim()) {
    toast.error('Mitra dan judul kerja sama wajib diisi')
    return
  }
  saving.value = true
  try {
    const payload: Record<string, unknown> = { ...coopForm }
    Object.keys(payload).forEach((k) => {
      if (payload[k] === '') payload[k] = null
    })
    if (editingCoopId.value) {
      await mbkmService.updateCooperation(editingCoopId.value, payload)
      toast.success('Dokumen kerja sama berhasil diperbarui')
    } else {
      await mbkmService.createCooperation(payload)
      toast.success('Dokumen kerja sama berhasil ditambahkan')
    }
    showCoopModal.value = false
    await loadAll()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menyimpan dokumen kerja sama')
  } finally {
    saving.value = false
  }
}

async function handleDeleteCoop(row: MbkmCooperation) {
  if (!confirm(`Hapus dokumen kerja sama "${row.title}"?`)) return
  try {
    await mbkmService.deleteCooperation(row.id)
    toast.success('Dokumen kerja sama berhasil dihapus')
    await loadAll()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menghapus dokumen kerja sama')
  }
}

function formatDate(value?: string | null) {
  if (!value) return '-'
  try {
    return new Date(value).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
  } catch {
    return value
  }
}

onMounted(loadAll)
</script>

<template>
  <PageContainer>
    <div class="mb-6">
      <h1 class="text-xl font-bold text-slate-900 tracking-tight">Data Master MBKM</h1>
      <p class="text-xs text-slate-500 mt-1">
        Jenis program, mitra, dan dokumen kerja sama — semua data referensi, bukan kondisi yang di-hardcode
      </p>
    </div>

    <Card class="border border-slate-200/80 shadow-2xs mb-5">
      <Tabs v-model="activeTab" :tabs="tabs" />
    </Card>

    <div v-if="loading" class="py-12 text-center text-sm text-slate-400">Memuat data master...</div>

    <template v-else>
      <!-- Program types -->
      <Card v-if="activeTab === 'jenis'" class="border border-slate-200/80 shadow-2xs">
        <template #header>
          <p class="font-bold text-slate-800 text-sm">Jenis Program MBKM</p>
          <Button variant="primary" size="sm" class="bg-brand-900 text-white" @click="openTypeModal()">
            <Plus class="w-3.5 h-3.5 mr-1" /> Tambah Jenis
          </Button>
        </template>
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="bg-slate-50/80 text-slate-600 font-semibold border-b border-slate-200 text-3xs uppercase tracking-wider">
                <th class="py-2.5 px-3 w-16 text-center">NO</th>
                <th class="py-2.5 px-3 w-40">KODE</th>
                <th class="py-2.5 px-3">NAMA</th>
                <th class="py-2.5 px-3">DESKRIPSI</th>
                <th class="py-2.5 px-3 w-24 text-center">AKTIF</th>
                <th class="py-2.5 px-3 w-24 text-center">AKSI</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="programTypes.length === 0">
                <td colspan="6" class="py-8 text-center text-slate-400">Belum ada jenis program.</td>
              </tr>
              <tr v-for="(row, index) in programTypes" :key="row.id">
                <td class="py-3 px-3 text-center text-slate-500">{{ index + 1 }}</td>
                <td class="py-3 px-3 font-mono text-slate-700">{{ row.code }}</td>
                <td class="py-3 px-3 font-semibold text-slate-900">{{ row.name }}</td>
                <td class="py-3 px-3 text-slate-600">{{ row.description || '-' }}</td>
                <td class="py-3 px-3 text-center">
                  <span :class="row.is_active ? 'text-emerald-600 font-bold' : 'text-slate-400'">
                    {{ row.is_active ? 'Aktif' : 'Nonaktif' }}
                  </span>
                </td>
                <td class="py-3 px-3">
                  <div class="flex items-center justify-center gap-1.5">
                    <button type="button" class="p-1.5 rounded-lg border border-blue-200 text-blue-600 hover:bg-blue-50" @click="openTypeModal(row)">
                      <Edit2 class="w-3.5 h-3.5" />
                    </button>
                    <button type="button" class="p-1.5 rounded-lg border border-rose-200 text-rose-600 hover:bg-rose-50" @click="handleDeleteType(row)">
                      <Trash2 class="w-3.5 h-3.5" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </Card>

      <!-- Partners -->
      <Card v-else-if="activeTab === 'mitra'" class="border border-slate-200/80 shadow-2xs">
        <template #header>
          <p class="font-bold text-slate-800 text-sm">Mitra MBKM</p>
          <Button variant="primary" size="sm" class="bg-brand-900 text-white" @click="openPartnerModal()">
            <Plus class="w-3.5 h-3.5 mr-1" /> Tambah Mitra
          </Button>
        </template>
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="bg-slate-50/80 text-slate-600 font-semibold border-b border-slate-200 text-3xs uppercase tracking-wider">
                <th class="py-2.5 px-3 w-16 text-center">NO</th>
                <th class="py-2.5 px-3 w-32">KODE</th>
                <th class="py-2.5 px-3">NAMA MITRA</th>
                <th class="py-2.5 px-3 w-36">TIPE</th>
                <th class="py-2.5 px-3">KONTAK</th>
                <th class="py-2.5 px-3 w-24 text-center">STATUS</th>
                <th class="py-2.5 px-3 w-24 text-center">AKSI</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="partners.length === 0">
                <td colspan="7" class="py-8 text-center text-slate-400">Belum ada mitra MBKM.</td>
              </tr>
              <tr v-for="(row, index) in partners" :key="row.id">
                <td class="py-3 px-3 text-center text-slate-500">{{ index + 1 }}</td>
                <td class="py-3 px-3 font-mono text-slate-700">{{ row.code }}</td>
                <td class="py-3 px-3">
                  <p class="font-semibold text-slate-900">{{ row.name }}</p>
                  <p class="text-2xs text-slate-500">
                    {{ [row.city, row.province, row.country].filter(Boolean).join(', ') }}
                  </p>
                </td>
                <td class="py-3 px-3 text-slate-700">{{ PARTNER_TYPE_LABELS[row.type] ?? row.type }}</td>
                <td class="py-3 px-3 text-slate-600 text-2xs">
                  <p v-if="row.contact_person_name">{{ row.contact_person_name }}</p>
                  <p v-if="row.email">{{ row.email }}</p>
                  <p v-if="row.phone">{{ row.phone }}</p>
                </td>
                <td class="py-3 px-3 text-center">
                  <span :class="row.status === 'active' ? 'text-emerald-600 font-bold' : 'text-slate-400'">
                    {{ row.status === 'active' ? 'Aktif' : row.status }}
                  </span>
                </td>
                <td class="py-3 px-3">
                  <div class="flex items-center justify-center gap-1.5">
                    <button type="button" class="p-1.5 rounded-lg border border-blue-200 text-blue-600 hover:bg-blue-50" @click="openPartnerModal(row)">
                      <Edit2 class="w-3.5 h-3.5" />
                    </button>
                    <button type="button" class="p-1.5 rounded-lg border border-rose-200 text-rose-600 hover:bg-rose-50" @click="handleDeletePartner(row)">
                      <Trash2 class="w-3.5 h-3.5" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </Card>

      <!-- Cooperations -->
      <Card v-else-if="activeTab === 'kerjasama'" class="border border-slate-200/80 shadow-2xs">
        <template #header>
          <p class="font-bold text-slate-800 text-sm">Dokumen Kerja Sama (MoU / MoA / IA / PKS)</p>
          <Button variant="primary" size="sm" class="bg-brand-900 text-white" @click="openCoopModal()">
            <Plus class="w-3.5 h-3.5 mr-1" /> Tambah Dokumen
          </Button>
        </template>
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="bg-slate-50/80 text-slate-600 font-semibold border-b border-slate-200 text-3xs uppercase tracking-wider">
                <th class="py-2.5 px-3 w-16 text-center">NO</th>
                <th class="py-2.5 px-3 w-24">TIPE</th>
                <th class="py-2.5 px-3">JUDUL</th>
                <th class="py-2.5 px-3 w-40">MITRA</th>
                <th class="py-2.5 px-3 w-28">NOMOR</th>
                <th class="py-2.5 px-3 w-48">PERIODE</th>
                <th class="py-2.5 px-3 w-24 text-center">AKSI</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="cooperations.length === 0">
                <td colspan="7" class="py-8 text-center text-slate-400">Belum ada dokumen kerja sama.</td>
              </tr>
              <tr v-for="(row, index) in cooperations" :key="row.id">
                <td class="py-3 px-3 text-center text-slate-500">{{ index + 1 }}</td>
                <td class="py-3 px-3 font-semibold text-slate-800 uppercase text-2xs">
                  {{ COOPERATION_TYPE_LABELS[row.type] ?? row.type }}
                </td>
                <td class="py-3 px-3">
                  <p class="font-semibold text-slate-900">{{ row.title }}</p>
                  <p v-if="row.program" class="text-2xs text-slate-500">{{ row.program.name }}</p>
                </td>
                <td class="py-3 px-3 text-slate-700">{{ row.partner?.name ?? '-' }}</td>
                <td class="py-3 px-3 font-mono text-2xs text-slate-600">{{ row.number || '-' }}</td>
                <td class="py-3 px-3 text-slate-700 text-2xs">
                  {{ formatDate(row.start_date) }} – {{ formatDate(row.end_date) }}
                </td>
                <td class="py-3 px-3">
                  <div class="flex items-center justify-center gap-1.5">
                    <button type="button" class="p-1.5 rounded-lg border border-blue-200 text-blue-600 hover:bg-blue-50" @click="openCoopModal(row)">
                      <Edit2 class="w-3.5 h-3.5" />
                    </button>
                    <button type="button" class="p-1.5 rounded-lg border border-rose-200 text-rose-600 hover:bg-rose-50" @click="handleDeleteCoop(row)">
                      <Trash2 class="w-3.5 h-3.5" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </Card>
    </template>

    <!-- Type modal -->
    <Modal v-model:open="showTypeModal" :title="editingTypeId ? 'Edit Jenis Program' : 'Tambah Jenis Program'" size="md">
      <div class="space-y-4 text-xs">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Kode <span class="text-rose-500">*</span></label>
            <Input v-model="typeForm.code" placeholder="mis. MAGANG" />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Urutan</label>
            <Input v-model.number="typeForm.sort_order" type="number" min="0" />
          </div>
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Nama <span class="text-rose-500">*</span></label>
          <Input v-model="typeForm.name" placeholder="mis. Magang / Praktik Kerja" />
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Deskripsi</label>
          <Textarea v-model="typeForm.description" :rows="2" />
        </div>
        <label class="flex items-center gap-2 cursor-pointer">
          <input v-model="typeForm.is_active" type="checkbox" class="w-4 h-4 rounded border-slate-300 text-brand-900" />
          <span class="text-slate-800">Aktif</span>
        </label>
      </div>
      <template #footer>
        <div class="flex items-center justify-end gap-2">
          <Button variant="outline" size="sm" @click="showTypeModal = false">Batal</Button>
          <Button variant="primary" size="sm" :loading="saving" class="bg-brand-900 text-white" @click="handleSaveType">
            <Save class="w-3.5 h-3.5 mr-1" /> Simpan
          </Button>
        </div>
      </template>
    </Modal>

    <!-- Partner modal -->
    <Modal v-model:open="showPartnerModal" :title="editingPartnerId ? 'Edit Mitra' : 'Tambah Mitra'" size="xl">
      <div class="space-y-4 text-xs">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Kode <span class="text-rose-500">*</span></label>
            <Input v-model="partnerForm.code" placeholder="mis. PT-CONTOH" />
          </div>
          <div class="sm:col-span-2">
            <label class="block font-semibold text-slate-700 mb-1.5">Nama Mitra <span class="text-rose-500">*</span></label>
            <Input v-model="partnerForm.name" placeholder="mis. PT Contoh Sejahtera" />
          </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Tipe Mitra</label>
            <select v-model="partnerForm.type" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none">
              <option v-for="o in optionsFrom(PARTNER_TYPE_LABELS)" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Status</label>
            <select v-model="partnerForm.status" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none">
              <option value="active">Aktif</option>
              <option value="inactive">Nonaktif</option>
            </select>
          </div>
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Alamat</label>
          <Textarea v-model="partnerForm.address" :rows="2" />
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Kota</label>
            <Input v-model="partnerForm.city" />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Provinsi</label>
            <Input v-model="partnerForm.province" />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Negara</label>
            <Input v-model="partnerForm.country" />
          </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Email</label>
            <Input v-model="partnerForm.email" type="email" />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Telepon</label>
            <Input v-model="partnerForm.phone" />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Website</label>
            <Input v-model="partnerForm.website" />
          </div>
        </div>
        <div class="pt-3 border-t border-slate-100">
          <p class="font-bold text-slate-800 mb-3">Narahubung</p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Input v-model="partnerForm.contact_person_name" placeholder="Nama" />
            <Input v-model="partnerForm.contact_person_position" placeholder="Jabatan" />
            <Input v-model="partnerForm.contact_person_email" placeholder="Email" />
            <Input v-model="partnerForm.contact_person_phone" placeholder="Telepon" />
          </div>
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Catatan</label>
          <Textarea v-model="partnerForm.notes" :rows="2" />
        </div>
      </div>
      <template #footer>
        <div class="flex items-center justify-end gap-2">
          <Button variant="outline" size="sm" @click="showPartnerModal = false">Batal</Button>
          <Button variant="primary" size="sm" :loading="saving" class="bg-brand-900 text-white" @click="handleSavePartner">
            <Save class="w-3.5 h-3.5 mr-1" /> Simpan
          </Button>
        </div>
      </template>
    </Modal>

    <!-- Cooperation modal -->
    <Modal v-model:open="showCoopModal" :title="editingCoopId ? 'Edit Dokumen Kerja Sama' : 'Tambah Dokumen Kerja Sama'" size="lg">
      <div class="space-y-4 text-xs">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Mitra <span class="text-rose-500">*</span></label>
            <select v-model="coopForm.partner_id" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none">
              <option :value="null">Pilih mitra</option>
              <option v-for="p in partners" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Program Terkait (opsional)</label>
            <select v-model="coopForm.program_id" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none">
              <option :value="null">Tidak dikaitkan</option>
              <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Tipe Dokumen</label>
            <select v-model="coopForm.type" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none">
              <option v-for="o in optionsFrom(COOPERATION_TYPE_LABELS)" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Nomor</label>
            <Input v-model="coopForm.number" placeholder="mis. 001/MoU/2026" />
          </div>
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Judul <span class="text-rose-500">*</span></label>
          <Input v-model="coopForm.title" placeholder="mis. Kerja Sama Magang Mahasiswa" />
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Mulai</label>
            <Input v-model="coopForm.start_date" type="date" />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Berakhir</label>
            <Input v-model="coopForm.end_date" type="date" />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Status</label>
            <select v-model="coopForm.status" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none">
              <option value="active">Aktif</option>
              <option value="expired">Kadaluarsa</option>
              <option value="terminated">Dihentikan</option>
            </select>
          </div>
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Catatan</label>
          <Textarea v-model="coopForm.notes" :rows="2" />
        </div>
      </div>
      <template #footer>
        <div class="flex items-center justify-end gap-2">
          <Button variant="outline" size="sm" @click="showCoopModal = false">Batal</Button>
          <Button variant="primary" size="sm" :loading="saving" class="bg-brand-900 text-white" @click="handleSaveCoop">
            <Save class="w-3.5 h-3.5 mr-1" /> Simpan
          </Button>
        </div>
      </template>
    </Modal>
  </PageContainer>
</template>
