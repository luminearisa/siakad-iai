<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import {
  Plus,
  Edit2,
  Trash2,
} from 'lucide-vue-next'
import { yudisiumService } from '@/services/api/yudisium'
import { academicService } from '@/services/api/academic'
import { useToast } from '@/composables/useToast'
import type { YudisiumRequirement } from '@/types/yudisium'
import type { StudyProgram } from '@/types/academic'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Modal from '@/components/ui/Modal.vue'

const toast = useToast()

const loading = ref<boolean>(false)
const requirements = ref<YudisiumRequirement[]>([])
const studyPrograms = ref<StudyProgram[]>([])
const searchQuery = ref<string>('')
const filterProdiId = ref<number | null>(null)

// Modal state
const showModal = ref<boolean>(false)
const isEditing = ref<boolean>(false)
const editingId = ref<number | null>(null)
const modalLoading = ref<boolean>(false)

const form = reactive({
  study_program_id: null as number | null,
  name: '',
  code: '',
  is_document: true,
  is_mandatory: true,
  min_credits: 144,
  min_gpa: 2.00,
})

async function loadData() {
  loading.value = true
  try {
    const params: any = {}
    if (filterProdiId.value) {
      params.study_program_id = filterProdiId.value
    }
    if (searchQuery.value.trim()) {
      params.search = searchQuery.value.trim()
    }

    const res = await yudisiumService.getRequirements(params)
    requirements.value = res.data || []
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal memuat syarat yudisium')
  } finally {
    loading.value = false
  }
}

async function loadDependencies() {
  try {
    const spRes = await academicService.getStudyPrograms()
    studyPrograms.value = spRes.data || []
  } catch (err) {
    console.error(err)
  }
}

function openCreateModal() {
  isEditing.value = false
  editingId.value = null
  form.study_program_id = null
  form.name = ''
  form.code = ''
  form.is_document = true
  form.is_mandatory = true
  form.min_credits = 144
  form.min_gpa = 2.00
  showModal.value = true
}

function openEditModal(req: YudisiumRequirement) {
  isEditing.value = true
  editingId.value = req.id
  form.study_program_id = req.study_program_id || null
  form.name = req.name
  form.code = req.code || ''
  form.is_document = req.is_document
  form.is_mandatory = req.is_mandatory
  form.min_credits = req.min_credits
  form.min_gpa = req.min_gpa
  showModal.value = true
}

async function handleSave() {
  if (!form.name.trim()) {
    toast.error('Nama syarat yudisium wajib diisi')
    return
  }

  modalLoading.value = true
  try {
    if (isEditing.value && editingId.value) {
      await yudisiumService.updateRequirement(editingId.value, form)
      toast.success('Syarat yudisium berhasil diperbarui')
    } else {
      await yudisiumService.createRequirement(form)
      toast.success('Syarat yudisium berhasil ditambahkan')
    }
    showModal.value = false
    loadData()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal menyimpan syarat yudisium')
  } finally {
    modalLoading.value = false
  }
}

async function handleDelete(req: YudisiumRequirement) {
  if (!confirm(`Hapus syarat yudisium "${req.name}"?`)) return

  try {
    await yudisiumService.deleteRequirement(req.id)
    toast.success('Syarat yudisium berhasil dihapus')
    loadData()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal menghapus syarat yudisium')
  }
}

onMounted(() => {
  loadData()
  loadDependencies()
})
</script>

<template>
  <PageContainer>
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div>
        <h1 class="text-xl font-bold text-slate-900 tracking-tight">Syarat Yudisium</h1>
        <p class="text-xs text-slate-500 mt-1">Konfigurasi syarat kelulusan dan dokumen berkas yudisium</p>
      </div>

      <div>
        <Button
          variant="primary"
          size="sm"
          class="bg-brand-900 hover:bg-brand-950 text-white flex items-center gap-1.5 shadow-2xs font-semibold px-4 py-2"
          @click="openCreateModal"
        >
          <Plus class="w-4 h-4" />
          <span>Tambah Syarat</span>
        </Button>
      </div>
    </div>

    <!-- Main Table Card -->
    <Card class="border border-slate-200/80 shadow-2xs overflow-visible">
      <!-- Toolbar -->
      <div class="p-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <!-- Prodi Filter -->
        <div class="flex items-center gap-2 text-xs">
          <span class="font-semibold text-slate-600">Program Studi:</span>
          <select
            v-model="filterProdiId"
            class="px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs outline-none focus:ring-1 focus:ring-brand-500"
            @change="loadData"
          >
            <option :value="null">Semua Program Studi (Umum)</option>
            <option v-for="sp in studyPrograms" :key="sp.id" :value="sp.id">
              {{ sp.name }}
            </option>
          </select>
        </div>

        <!-- Search Input -->
        <div class="flex items-center">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cari syarat..."
            class="w-48 sm:w-64 px-3 py-1.5 bg-white border border-slate-300 rounded-l-lg text-xs outline-none focus:ring-1 focus:ring-brand-500"
            @keyup.enter="loadData"
          />
          <button
            type="button"
            class="px-3.5 py-1.5 bg-white hover:bg-slate-50 border border-l-0 border-slate-300 rounded-r-lg text-xs font-semibold text-rose-700 transition-colors"
            @click="loadData"
          >
            Cari
          </button>
        </div>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
          <thead>
            <tr class="bg-slate-50/80 text-slate-600 font-semibold border-b border-slate-200 text-3xs uppercase tracking-wider">
              <th class="py-3 px-4 w-12 text-center">NO</th>
              <th class="py-3 px-4">NAMA SYARAT</th>
              <th class="py-3 px-4">PROGRAM STUDI</th>
              <th class="py-3 px-4 text-center">JENIS</th>
              <th class="py-3 px-4 text-center">KEWAJIBAN</th>
              <th class="py-3 px-4 text-center w-24">AKSI</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <tr v-if="loading">
              <td colspan="6" class="py-8 text-center text-slate-400">
                Memuat data syarat yudisium...
              </td>
            </tr>
            <tr v-else-if="requirements.length === 0">
              <td colspan="6" class="py-8 text-center text-slate-400">
                Belum ada syarat yudisium dikonfigurasi.
              </td>
            </tr>
            <tr
              v-for="(req, index) in requirements"
              :key="req.id"
              class="hover:bg-slate-50/80 transition-colors"
            >
              <td class="py-3.5 px-4 text-center text-slate-500 font-medium">
                {{ index + 1 }}
              </td>
              <td class="py-3.5 px-4">
                <div class="font-bold text-slate-900">{{ req.name }}</div>
                <div v-if="req.code" class="text-3xs text-slate-400 font-mono mt-0.5">{{ req.code }}</div>
              </td>
              <td class="py-3.5 px-4 text-slate-800">
                {{ req.study_program?.name || 'Semua Program Studi (Umum)' }}
              </td>
              <td class="py-3.5 px-4 text-center">
                <span
                  class="px-2 py-0.5 rounded text-3xs font-semibold"
                  :class="req.is_document ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-slate-100 text-slate-700 border border-slate-200'"
                >
                  {{ req.is_document ? 'Upload Dokumen' : 'Cek Nilai/Akademik' }}
                </span>
              </td>
              <td class="py-3.5 px-4 text-center">
                <span
                  class="px-2 py-0.5 rounded text-3xs font-bold"
                  :class="req.is_mandatory ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-slate-100 text-slate-500'"
                >
                  {{ req.is_mandatory ? 'Wajib' : 'Opsional' }}
                </span>
              </td>
              <td class="py-3.5 px-4 text-center">
                <div class="flex items-center justify-center gap-1.5">
                  <button
                    type="button"
                    class="p-1.5 rounded-lg border border-slate-200 text-blue-600 hover:bg-blue-50 transition-colors"
                    title="Edit Syarat"
                    @click="openEditModal(req)"
                  >
                    <Edit2 class="w-3.5 h-3.5" />
                  </button>
                  <button
                    type="button"
                    class="p-1.5 rounded-lg border border-slate-200 text-rose-600 hover:bg-rose-50 transition-colors"
                    title="Hapus Syarat"
                    @click="handleDelete(req)"
                  >
                    <Trash2 class="w-3.5 h-3.5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </Card>

    <!-- Modal Form Syarat -->
    <Modal
      v-model:open="showModal"
      :title="isEditing ? 'Edit Syarat Yudisium' : 'Tambah Syarat Yudisium'"
      size="md"
    >
      <form @submit.prevent="handleSave" class="space-y-4 text-xs">
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">
            Nama Syarat <span class="text-rose-500">*</span>
          </label>
          <Input
            v-model="form.name"
            placeholder="Contoh: Bebas Pustaka / Sertifikat TOEFL"
            required
          />
        </div>

        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Kode Syarat</label>
          <Input
            v-model="form.code"
            placeholder="Contoh: REQ-LIB"
          />
        </div>

        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">Program Studi</label>
          <select
            v-model="form.study_program_id"
            class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none focus:ring-2 focus:ring-brand-500"
          >
            <option :value="null">Semua Program Studi (Umum)</option>
            <option v-for="sp in studyPrograms" :key="sp.id" :value="sp.id">
              {{ sp.name }}
            </option>
          </select>
        </div>

        <div class="flex items-center gap-6 pt-2">
          <label class="flex items-center gap-2 cursor-pointer select-none">
            <input
              v-model="form.is_document"
              type="checkbox"
              class="w-4 h-4 rounded text-brand-900 border-slate-300 focus:ring-brand-500"
            />
            <span class="font-medium text-slate-800">Memerlukan Upload Berkas</span>
          </label>

          <label class="flex items-center gap-2 cursor-pointer select-none">
            <input
              v-model="form.is_mandatory"
              type="checkbox"
              class="w-4 h-4 rounded text-brand-900 border-slate-300 focus:ring-brand-500"
            />
            <span class="font-medium text-slate-800">Syarat Wajib</span>
          </label>
        </div>
      </form>

      <template #footer>
        <div class="flex justify-end gap-2">
          <Button variant="outline" size="sm" @click="showModal = false">
            Batal
          </Button>
          <Button
            variant="primary"
            size="sm"
            :loading="modalLoading"
            class="bg-brand-900 text-white"
            @click="handleSave"
          >
            {{ isEditing ? 'Simpan Perubahan' : 'Simpan Syarat' }}
          </Button>
        </div>
      </template>
    </Modal>
  </PageContainer>
</template>
