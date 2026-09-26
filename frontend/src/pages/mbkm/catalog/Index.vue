<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  CheckCircle2,
  XCircle,
  Users,
  CalendarRange,
  MapPin,
  Send,
  Info,
  FileUp,
  Upload,
} from 'lucide-vue-next'
import { mbkmService } from '@/services/api/mbkm'
import { useToast } from '@/composables/useToast'
import {
  APPLICATION_STATUS_LABELS,
  APPLICATION_STATUS_VARIANTS,
  LOCATION_MODE_LABELS,
  PROGRAM_STATUS_LABELS,
  PROGRAM_STATUS_VARIANTS,
} from '@/types/mbkm'
import type { MbkmApplication, MbkmCatalogItem } from '@/types/mbkm'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Modal from '@/components/ui/Modal.vue'
import Textarea from '@/components/ui/Textarea.vue'
import Input from '@/components/ui/Input.vue'
import MbkmStatusBadge from '@/pages/mbkm/components/MbkmStatusBadge.vue'

const router = useRouter()
const toast = useToast()

const loading = ref(false)
const saving = ref(false)
const catalog = ref<MbkmCatalogItem[]>([])
const myApplications = ref<MbkmApplication[]>([])
const search = ref('')
const filterType = ref<number | null>(null)
const programTypes = ref<Array<{ id: number; name: string }>>([])

const showDetail = ref(false)
const selectedItem = ref<MbkmCatalogItem | null>(null)

const showApplyModal = ref(false)
const applyTarget = ref<MbkmCatalogItem | null>(null)
const motivation = ref('')
const createdApplication = ref<MbkmApplication | null>(null)

const showDocumentModal = ref(false)
const documentFile = ref<File | null>(null)
const documentCategory = ref('')

async function load() {
  loading.value = true
  try {
    const params: Record<string, unknown> = {}
    if (search.value.trim()) params.search = search.value.trim()
    if (filterType.value) params.program_type_id = filterType.value

    const [catRes, appRes, typeRes] = await Promise.all([
      mbkmService.getCatalog(params),
      mbkmService.getApplications({ per_page: 100 }),
      mbkmService.getProgramTypes({ per_page: 100 }),
    ])
    catalog.value = catRes.data || []
    myApplications.value = appRes.data || []
    programTypes.value = (typeRes.data || []) as any
  } catch (err: any) {
    toast.error(err.message || 'Gagal memuat katalog program MBKM')
  } finally {
    loading.value = false
  }
}

function applicationFor(programId: number) {
  return myApplications.value.find((a) => a.program_id === programId && a.status !== 'withdrawn')
}

function openDetail(item: MbkmCatalogItem) {
  selectedItem.value = item
  showDetail.value = true
}

function openApply(item: MbkmCatalogItem) {
  applyTarget.value = item
  motivation.value = ''
  createdApplication.value = applicationFor(item.program.id) ?? null
  showApplyModal.value = true
}

async function handleApply() {
  if (!applyTarget.value) return
  saving.value = true
  try {
    if (createdApplication.value) {
      // Resume an existing draft.
      await mbkmService.updateApplication(createdApplication.value.id, { motivation_statement: motivation.value })
    } else {
      const res = await mbkmService.createApplication({
        program_id: applyTarget.value.program.id,
        motivation_statement: motivation.value,
      })
      createdApplication.value = res.data
    }
    toast.success('Pendaftaran tersimpan. Lengkapi dokumen lalu ajukan.')
    await load()
  } catch (err: any) {
    toast.error(err.message || 'Gagal menyimpan pendaftaran')
  } finally {
    saving.value = false
  }
}

async function handleSubmit() {
  if (!createdApplication.value) return
  saving.value = true
  try {
    await mbkmService.submitApplication(createdApplication.value.id)
    toast.success('Pendaftaran berhasil diajukan')
    showApplyModal.value = false
    await load()
  } catch (err: any) {
    toast.error(err.message || 'Pengajuan ditolak — periksa kelayakan dan dokumen wajib')
  } finally {
    saving.value = false
  }
}

function openDocumentModal(category: string) {
  documentCategory.value = category
  documentFile.value = null
  showDocumentModal.value = true
}

function onFileSelected(event: Event) {
  const input = event.target as HTMLInputElement
  documentFile.value = input.files?.[0] ?? null
}

async function handleUploadDocument() {
  if (!createdApplication.value || !documentFile.value) {
    toast.error('Pilih berkas terlebih dahulu')
    return
  }
  saving.value = true
  try {
    await mbkmService.uploadDocument({
      documentable_type: 'application',
      documentable_id: createdApplication.value.id,
      category: documentCategory.value,
      file: documentFile.value,
    })
    toast.success('Dokumen berhasil diunggah')
    showDocumentModal.value = false
    await load()
  } catch (err: any) {
    toast.error(err.message || 'Gagal mengunggah dokumen')
  } finally {
    saving.value = false
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

onMounted(load)
</script>

<template>
  <PageContainer>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div>
        <h1 class="text-xl font-bold text-slate-900 tracking-tight">Katalog Program MBKM</h1>
        <p class="text-xs text-slate-500 mt-1">
          Hanya program yang relevan untuk Anda, lengkap dengan status kelayakan yang dapat dijelaskan
        </p>
      </div>
      <button
        type="button"
        class="text-xs font-semibold text-brand-900 hover:text-brand-950 flex items-center gap-1.5"
        @click="router.push('/mbkm/my')"
      >
        <FileUp class="w-3.5 h-3.5" />
        Pendaftaran &amp; kegiatan saya
      </button>
    </div>

    <Card class="border border-slate-200/80 shadow-2xs mb-5">
      <div class="flex flex-col sm:flex-row sm:items-center gap-3">
        <select
          v-model="filterType"
          class="px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs outline-none"
          @change="load"
        >
          <option :value="null">Semua Jenis Program</option>
          <option v-for="t in programTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
        </select>
        <div class="flex items-center flex-1">
          <input
            v-model="search"
            type="text"
            placeholder="Cari program MBKM..."
            class="flex-1 px-3 py-1.5 bg-white border border-slate-300 rounded-l-lg text-xs outline-none focus:ring-1 focus:ring-brand-500"
            @keyup.enter="load"
          />
          <button type="button" class="px-3.5 py-1.5 bg-white hover:bg-slate-50 border border-l-0 border-slate-300 rounded-r-lg text-xs font-semibold text-rose-700" @click="load">
            Cari
          </button>
        </div>
      </div>
    </Card>

    <div v-if="loading" class="py-12 text-center text-sm text-slate-400">Memuat katalog program...</div>

    <div v-else-if="catalog.length === 0" class="py-12 text-center text-sm text-slate-400">
      Belum ada program MBKM yang tersedia untuk Anda saat ini.
    </div>

    <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-5">
      <Card
        v-for="item in catalog"
        :key="item.program.id"
        class="border shadow-2xs transition-colors"
        :class="item.is_eligible ? 'border-slate-200/80' : 'border-amber-200'"
      >
        <div class="flex items-start justify-between gap-3 mb-3">
          <div class="min-w-0">
            <p class="font-bold text-slate-900 text-sm leading-snug">{{ item.program.name }}</p>
            <p class="text-2xs text-slate-500 mt-1">
              <span class="font-mono">{{ item.program.code }}</span> •
              {{ item.program.program_type?.name ?? '-' }}
            </p>
          </div>
          <MbkmStatusBadge
            :value="item.program.status"
            :labels="PROGRAM_STATUS_LABELS"
            :variants="PROGRAM_STATUS_VARIANTS"
          />
        </div>

        <div class="grid grid-cols-2 gap-2.5 text-2xs text-slate-600 mb-3">
          <span class="flex items-center gap-1.5">
            <CalendarRange class="w-3 h-3 text-slate-400" />
            {{ formatDate(item.program.start_date) }} – {{ formatDate(item.program.end_date) }}
          </span>
          <span class="flex items-center gap-1.5">
            <MapPin class="w-3 h-3 text-slate-400" />
            {{ LOCATION_MODE_LABELS[item.program.location_mode] ?? item.program.location_mode }}
          </span>
          <span v-if="item.participant_count !== null" class="flex items-center gap-1.5">
            <Users class="w-3 h-3 text-slate-400" />
            {{ item.participant_count }} peserta
          </span>
          <span class="flex items-center gap-1.5">
            <Users class="w-3 h-3 text-slate-400" />
            Kuota {{ item.quota.used }}/{{ item.quota.quota ?? '∞' }}
          </span>
        </div>

        <!-- Explainable eligibility -->
        <div
          class="p-3 rounded-lg border mb-3"
          :class="item.is_eligible ? 'bg-emerald-50/70 border-emerald-200' : 'bg-amber-50/70 border-amber-200'"
        >
          <p class="flex items-center gap-1.5 font-semibold text-xs mb-1.5" :class="item.is_eligible ? 'text-emerald-800' : 'text-amber-800'">
            <CheckCircle2 v-if="item.is_eligible" class="w-3.5 h-3.5" />
            <XCircle v-else class="w-3.5 h-3.5" />
            {{ item.is_eligible ? 'Anda memenuhi syarat' : 'Anda belum memenuhi syarat' }}
          </p>
          <ul v-if="!item.is_eligible" class="space-y-1">
            <li v-for="(reason, i) in item.eligibility_reasons" :key="i" class="text-2xs text-amber-800 flex gap-1.5">
              <span>•</span><span>{{ reason }}</span>
            </li>
          </ul>
          <p v-else class="text-2xs text-emerald-700">
            Semua persyaratan akademik, administratif, dan dokumen terpenuhi.
          </p>
        </div>

        <div v-if="applicationFor(item.program.id)" class="mb-3 p-2.5 bg-slate-50 rounded-lg border border-slate-200 flex items-center justify-between gap-2">
          <span class="text-2xs text-slate-600">Pendaftaran Anda:</span>
          <MbkmStatusBadge
            :value="applicationFor(item.program.id)?.status"
            :labels="APPLICATION_STATUS_LABELS"
            :variants="APPLICATION_STATUS_VARIANTS"
          />
        </div>

        <div class="flex items-center gap-2">
          <Button variant="outline" size="sm" @click="openDetail(item)">
            <Info class="w-3.5 h-3.5 mr-1" /> Detail &amp; Syarat
          </Button>
          <Button
            v-if="!applicationFor(item.program.id)"
            variant="primary"
            size="sm"
            class="bg-brand-900 hover:bg-brand-950 text-white"
            :disabled="!item.registration_open || !item.is_eligible"
            @click="openApply(item)"
          >
            <Send class="w-3.5 h-3.5 mr-1" />
            {{ item.registration_open ? 'Daftar' : 'Pendaftaran Ditutup' }}
          </Button>
          <Button
            v-else
            variant="primary"
            size="sm"
            class="bg-brand-900 hover:bg-brand-950 text-white"
            @click="openApply(item)"
          >
            Lanjutkan Pendaftaran
          </Button>
        </div>
      </Card>
    </div>

    <!-- Detail modal -->
    <Modal v-model:open="showDetail" title="Detail &amp; Persyaratan Program" size="xl">
      <div v-if="selectedItem" class="space-y-5 text-xs">
        <div>
          <p class="font-bold text-slate-900 text-sm">{{ selectedItem.program.name }}</p>
          <p class="text-2xs text-slate-500 mt-0.5">
            {{ selectedItem.program.program_type?.name }} • {{ selectedItem.program.organizer_name || 'Internal' }}
          </p>
        </div>

        <p v-if="selectedItem.program.description" class="text-slate-700 leading-relaxed whitespace-pre-line">
          {{ selectedItem.program.description }}
        </p>

        <div>
          <p class="font-bold text-slate-800 mb-2">Hasil Pemeriksaan Kelayakan</p>
          <div class="space-y-1.5">
            <div
              v-for="check in selectedItem.eligibility_checks"
              :key="check.code"
              class="p-2.5 rounded-lg border flex items-start gap-2"
              :class="check.passed ? 'bg-emerald-50/60 border-emerald-200' : 'bg-rose-50/60 border-rose-200'"
            >
              <CheckCircle2 v-if="check.passed" class="w-3.5 h-3.5 text-emerald-600 shrink-0 mt-0.5" />
              <XCircle v-else class="w-3.5 h-3.5 text-rose-600 shrink-0 mt-0.5" />
              <div class="min-w-0">
                <p class="font-semibold text-slate-900">{{ check.label }}</p>
                <p class="text-2xs text-slate-600 mt-0.5">{{ check.message }}</p>
              </div>
            </div>
          </div>
        </div>

        <div v-if="(selectedItem.program.requirements ?? []).length > 0">
          <p class="font-bold text-slate-800 mb-2">Persyaratan Program</p>
          <ul class="space-y-1.5">
            <li
              v-for="req in selectedItem.program.requirements"
              :key="req.id"
              class="flex items-start justify-between gap-3 p-2.5 border border-slate-200 rounded-lg"
            >
              <div class="min-w-0">
                <p class="font-medium text-slate-800">{{ req.name }}</p>
                <p v-if="req.description" class="text-2xs text-slate-500 mt-0.5">{{ req.description }}</p>
              </div>
              <span
                :class="[
                  'shrink-0 px-1.5 py-0.5 rounded text-4xs font-bold border',
                  req.is_mandatory
                    ? 'bg-rose-50 text-rose-700 border-rose-200'
                    : 'bg-slate-50 text-slate-500 border-slate-200',
                ]"
              >
                {{ req.is_mandatory ? 'WAJIB' : 'OPSIONAL' }}
              </span>
            </li>
          </ul>
        </div>

        <div v-if="(selectedItem.program.locations ?? []).length > 0">
          <p class="font-bold text-slate-800 mb-2">Lokasi Tersedia</p>
          <ul class="space-y-1.5">
            <li
              v-for="loc in selectedItem.program.locations"
              :key="loc.id"
              class="p-2.5 border border-slate-200 rounded-lg"
            >
              <p class="font-medium text-slate-800 flex items-center gap-1.5">
                <MapPin class="w-3.5 h-3.5 text-rose-500" /> {{ loc.name }}
              </p>
              <p class="text-2xs text-slate-500 mt-0.5">
                {{ LOCATION_MODE_LABELS[loc.location_mode] ?? loc.location_mode }} •
                {{ [loc.city, loc.province, loc.country].filter(Boolean).join(', ') }}
              </p>
            </li>
          </ul>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3 bg-slate-50 rounded-lg border border-slate-200">
          <div>
            <p class="text-2xs text-slate-500">Kuota</p>
            <p class="font-semibold text-slate-900">{{ selectedItem.program.quota ?? 'Tidak dibatasi' }}</p>
          </div>
          <div>
            <p class="text-2xs text-slate-500">IPK Minimum</p>
            <p class="font-semibold text-slate-900">{{ selectedItem.program.min_gpa ?? '-' }}</p>
          </div>
          <div>
            <p class="text-2xs text-slate-500">SKS Minimum</p>
            <p class="font-semibold text-slate-900">{{ selectedItem.program.min_credits ?? '-' }}</p>
          </div>
          <div>
            <p class="text-2xs text-slate-500">Maks SKS Diakui</p>
            <p class="font-semibold text-slate-900">{{ selectedItem.program.max_recognized_credits ?? '-' }}</p>
          </div>
        </div>
      </div>

      <template #footer>
        <div class="flex items-center justify-end gap-2">
          <Button variant="outline" size="sm" @click="showDetail = false">Tutup</Button>
          <Button
            v-if="selectedItem && !applicationFor(selectedItem.program.id)"
            variant="primary"
            size="sm"
            class="bg-brand-900 text-white"
            :disabled="!selectedItem.registration_open || !selectedItem.is_eligible"
            @click="showDetail = false; openApply(selectedItem)"
          >
            Daftar Program
          </Button>
        </div>
      </template>
    </Modal>

    <!-- Apply modal -->
    <Modal v-model:open="showApplyModal" title="Pendaftaran Program MBKM" size="lg">
      <div v-if="applyTarget" class="space-y-5 text-xs">
        <div class="p-3 bg-slate-50 rounded-lg border border-slate-200">
          <p class="font-semibold text-slate-900">{{ applyTarget.program.name }}</p>
          <p class="text-2xs text-slate-500 mt-0.5">
            {{ applyTarget.program.program_type?.name }} •
            {{ formatDate(applyTarget.program.start_date) }} – {{ formatDate(applyTarget.program.end_date) }}
          </p>
        </div>

        <div v-if="createdApplication" class="p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-emerald-800">
          <p class="font-semibold">Nomor pendaftaran: <span class="font-mono">{{ createdApplication.registration_number }}</span></p>
          <p class="text-2xs mt-0.5">
            Status: {{ APPLICATION_STATUS_LABELS[createdApplication.status] ?? createdApplication.status }}
          </p>
        </div>

        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Pernyataan Motivasi</label>
          <Textarea v-model="motivation" :rows="5" placeholder="Jelaskan alasan dan target Anda mengikuti program ini" />
        </div>

        <div v-if="createdApplication && applyTarget.program.requires_documents">
          <p class="font-bold text-slate-800 mb-2">Dokumen Wajib</p>
          <div class="space-y-1.5">
            <div
              v-for="req in (applyTarget.program.requirements ?? []).filter((r) => r.is_document && r.is_mandatory)"
              :key="req.id"
              class="flex items-center justify-between gap-3 p-2.5 border border-slate-200 rounded-lg"
            >
              <div class="min-w-0">
                <p class="font-medium text-slate-800">{{ req.name }}</p>
                <p class="text-2xs text-slate-500 font-mono">{{ req.code }}</p>
              </div>
              <Button variant="outline" size="sm" @click="openDocumentModal(req.code || req.name)">
                <Upload class="w-3.5 h-3.5 mr-1" /> Unggah
              </Button>
            </div>
            <p v-if="(applyTarget.program.requirements ?? []).filter((r) => r.is_document && r.is_mandatory).length === 0" class="text-slate-500">
              Tidak ada dokumen wajib yang dikonfigurasi untuk program ini.
            </p>
          </div>
        </div>

        <div v-if="createdApplication && createdApplication.documents && createdApplication.documents.length > 0">
          <p class="font-bold text-slate-800 mb-2">Dokumen Terunggah</p>
          <ul class="space-y-1">
            <li v-for="doc in createdApplication.documents" :key="doc.id" class="text-slate-700">
              • {{ doc.title || doc.original_name }} <span class="text-slate-400">({{ doc.category }})</span>
            </li>
          </ul>
        </div>

        <p class="text-2xs text-slate-500">
          Validasi kelayakan dilakukan ulang di server saat pengajuan: status mahasiswa, periode pendaftaran,
          prodi/jenjang/angkatan/semester, IPK, SKS, mata kuliah prasyarat, dokumen wajib, dan batas keikutsertaan.
        </p>
      </div>

      <template #footer>
        <div class="flex items-center justify-end gap-2">
          <Button variant="outline" size="sm" @click="showApplyModal = false">Tutup</Button>
          <Button variant="outline" size="sm" :loading="saving" @click="handleApply">
            Simpan Draft
          </Button>
          <Button
            v-if="createdApplication"
            variant="primary"
            size="sm"
            class="bg-brand-900 text-white"
            :loading="saving"
            @click="handleSubmit"
          >
            <Send class="w-3.5 h-3.5 mr-1" /> Ajukan Pendaftaran
          </Button>
        </div>
      </template>
    </Modal>

    <!-- Document modal -->
    <Modal v-model:open="showDocumentModal" title="Unggah Dokumen" size="md">
      <div class="space-y-4 text-xs">
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Kategori</label>
          <Input v-model="documentCategory" readonly />
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Berkas <span class="text-rose-500">*</span></label>
          <input
            type="file"
            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.zip"
            class="w-full text-xs file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-brand-900 file:text-white file:text-xs file:font-semibold"
            @change="onFileSelected"
          />
          <p class="mt-1.5 text-2xs text-slate-500">Maksimum 10 MB.</p>
        </div>
      </div>
      <template #footer>
        <div class="flex items-center justify-end gap-2">
          <Button variant="outline" size="sm" @click="showDocumentModal = false">Batal</Button>
          <Button variant="primary" size="sm" :loading="saving" class="bg-brand-900 text-white" @click="handleUploadDocument">
            Unggah
          </Button>
        </div>
      </template>
    </Modal>
  </PageContainer>
</template>
