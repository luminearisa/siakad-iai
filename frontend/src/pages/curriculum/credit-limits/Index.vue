<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { Plus, Edit2, Trash2, X, Check, Sliders } from 'lucide-vue-next'
import { curriculumService } from '@/services/api/curriculum'
import { useToast } from '@/composables/useToast'
import type { CreditLimit, CreditLimitRule } from '@/types/curriculum'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Textarea from '@/components/ui/Textarea.vue'

const toast = useToast()
const loading = ref<boolean>(false)
const limits = ref<CreditLimit[]>([])
const search = ref<string>('')
const perPage = ref<number>(10)
const drawerOpen = ref<boolean>(false)
const editingId = ref<number | null>(null)
const saving = ref<boolean>(false)

const form = reactive({
  name: '',
  description: '',
  rules: [
    { min_gpa: 3.0, max_gpa: 4.0, max_sks: 24 },
    { min_gpa: 2.5, max_gpa: 2.99, max_sks: 21 },
    { min_gpa: 2.0, max_gpa: 2.49, max_sks: 18 },
    { min_gpa: 0.0, max_gpa: 1.99, max_sks: 15 },
  ] as CreditLimitRule[],
  status: 'active' as 'active' | 'inactive',
})

const filteredLimits = computed(() => {
  if (!search.value) return limits.value
  const q = search.value.toLowerCase()
  return limits.value.filter(
    (l) =>
      l.name.toLowerCase().includes(q) ||
      (l.description && l.description.toLowerCase().includes(q))
  )
})

async function fetchLimits() {
  loading.value = true
  try {
    const res = await curriculumService.getCreditLimits()
    limits.value = res.data || []
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal memuat data batas SKS')
  } finally {
    loading.value = false
  }
}

function openCreateDrawer() {
  editingId.value = null
  form.name = ''
  form.description = ''
  form.rules = [
    { min_gpa: 3.0, max_gpa: 4.0, max_sks: 24 },
    { min_gpa: 2.5, max_gpa: 2.99, max_sks: 21 },
    { min_gpa: 2.0, max_gpa: 2.49, max_sks: 18 },
    { min_gpa: 0.0, max_gpa: 1.99, max_sks: 15 },
  ]
  form.status = 'active'
  drawerOpen.value = true
}

function openEditDrawer(item: CreditLimit) {
  editingId.value = item.id
  form.name = item.name
  form.description = item.description || ''
  form.rules = item.rules && item.rules.length > 0 ? [...item.rules] : [
    { min_gpa: 3.0, max_gpa: 4.0, max_sks: 24 },
  ]
  form.status = item.status
  drawerOpen.value = true
}

function addRuleRow() {
  form.rules.push({ min_gpa: 0, max_gpa: 0, max_sks: 12 })
}

function removeRuleRow(index: number) {
  if (form.rules.length > 1) {
    form.rules.splice(index, 1)
  }
}

function closeDrawer() {
  drawerOpen.value = false
}

async function handleSave() {
  if (!form.name) {
    toast.error('Mohon isi nama batas SKS')
    return
  }

  saving.value = true
  try {
    if (editingId.value) {
      await curriculumService.updateCreditLimit(editingId.value, form)
      toast.success('Batas SKS berhasil diperbarui')
    } else {
      await curriculumService.createCreditLimit(form)
      toast.success('Batas SKS berhasil ditambahkan')
    }
    drawerOpen.value = false
    fetchLimits()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal menyimpan batas SKS')
  } finally {
    saving.value = false
  }
}

async function handleDelete(item: CreditLimit) {
  if (!confirm(`Apakah Anda yakin ingin menghapus "${item.name}"?`)) return
  try {
    await curriculumService.deleteCreditLimit(item.id)
    toast.success('Batas SKS berhasil dihapus')
    fetchLimits()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal menghapus batas SKS')
  }
}

onMounted(() => {
  fetchLimits()
})
</script>

<template>
  <PageContainer>
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div>
        <nav class="flex items-center gap-1.5 text-xs text-slate-400 mb-1">
          <span>Akademik</span>
        </nav>
        <h1 class="text-xl font-bold text-slate-900">Batas SKS</h1>
        <p class="text-xs text-slate-500 mt-0.5">Manajemen Batas SKS</p>
      </div>

      <Button
        variant="primary"
        size="sm"
        class="bg-brand-700 hover:bg-brand-800 text-white flex items-center gap-1.5 self-start sm:self-auto shadow-2xs"
        @click="openCreateDrawer"
      >
        <Plus class="w-4 h-4" />
        Tambah Data
      </Button>
    </div>

    <!-- Main Card -->
    <Card class="border border-slate-200/80 shadow-2xs overflow-hidden">
      <!-- Toolbar -->
      <div class="p-4 border-b border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 bg-slate-50/50">
        <div class="flex items-center gap-2 w-full sm:w-auto text-xs text-slate-600">
          <span>Baris</span>
          <select
            v-model="perPage"
            class="px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg text-xs font-medium focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600 outline-none"
          >
            <option :value="10">10</option>
            <option :value="25">25</option>
            <option :value="50">50</option>
          </select>
        </div>

        <div class="flex items-center gap-2 w-full sm:w-72">
          <div class="relative flex-1">
            <Input
              v-model="search"
              placeholder="Cari data..."
              class="w-full text-xs pr-8"
            />
          </div>
          <Button variant="primary" size="sm" class="bg-brand-700 hover:bg-brand-800 text-white text-xs px-3">
            Cari
          </Button>
        </div>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-100/80 border-b border-slate-200/80 text-slate-600 font-bold uppercase tracking-wider text-3xs">
              <th class="py-3 px-4 w-12 text-center">NO</th>
              <th class="py-3 px-4">NAMA BATAS SKS</th>
              <th class="py-3 px-4">KETERANGAN</th>
              <th class="py-3 px-4 text-center">JUMLAH BATAS SKS</th>
              <th class="py-3 px-4 text-center">KURIKULUM YANG MENGGUNAKAN</th>
              <th class="py-3 px-4 w-28 text-center">AKSI</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <tr v-if="loading" class="hover:bg-transparent">
              <td colspan="6" class="py-12 text-center text-slate-400">
                Memuat data batas SKS...
              </td>
            </tr>
            <tr v-else-if="filteredLimits.length === 0" class="hover:bg-transparent">
              <td colspan="6" class="py-12 text-center text-slate-400">
                Belum ada data batas SKS
              </td>
            </tr>
            <tr
              v-for="(item, idx) in filteredLimits.slice(0, perPage)"
              :key="item.id"
              class="hover:bg-slate-50/80 transition-colors"
            >
              <td class="py-3.5 px-4 text-center font-medium text-slate-500">
                {{ idx + 1 }}
              </td>
              <td class="py-3.5 px-4 font-bold text-slate-900">
                {{ item.name }}
              </td>
              <td class="py-3.5 px-4 text-slate-600">
                {{ item.description || '--' }}
              </td>
              <td class="py-3.5 px-4 text-center font-semibold text-slate-800">
                {{ item.rules?.length || 1 }}
              </td>
              <td class="py-3.5 px-4 text-center font-semibold text-slate-800">
                {{ item.curricula_count || 0 }}
              </td>
              <td class="py-3.5 px-4">
                <div class="flex items-center justify-center gap-1.5">
                  <button
                    type="button"
                    class="p-1.5 text-brand-600 hover:bg-brand-50 rounded-md border border-brand-200 transition-colors"
                    title="Edit Batas SKS"
                    @click="openEditDrawer(item)"
                  >
                    <Edit2 class="w-3.5 h-3.5 text-rose-600" />
                  </button>
                  <button
                    type="button"
                    class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-md border border-rose-200 transition-colors"
                    title="Hapus Batas SKS"
                    @click="handleDelete(item)"
                  >
                    <Trash2 class="w-3.5 h-3.5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Footer -->
      <div class="p-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500 bg-white">
        <span>Menampilkan 1 hingga {{ Math.min(perPage, filteredLimits.length) }} dari total {{ filteredLimits.length }} data</span>
        <div class="flex items-center gap-1">
          <span class="px-2.5 py-1 rounded bg-brand-700 text-white font-bold">1</span>
        </div>
      </div>
    </Card>

    <!-- Slide-over Drawer -->
    <div
      v-if="drawerOpen"
      class="fixed inset-0 z-50 flex justify-end bg-slate-900/40 backdrop-blur-xs transition-opacity"
      @click.self="closeDrawer"
    >
      <div class="w-full max-w-lg bg-white shadow-2xl h-full flex flex-col animate-slide-left">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
          <div>
            <h3 class="text-sm font-bold text-slate-900">
              {{ editingId ? 'Edit Batas SKS' : 'Tambah Batas SKS' }}
            </h3>
            <p class="text-3xs text-slate-500 mt-0.5">Konfigurasi batas beban SKS berdasarkan IP semester</p>
          </div>
          <button
            type="button"
            class="p-1 rounded-md text-slate-400 hover:text-slate-600 hover:bg-slate-100"
            @click="closeDrawer"
          >
            <X class="w-4 h-4" />
          </button>
        </div>

        <div class="p-5 overflow-y-auto flex-1 space-y-4 text-xs">
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Nama Batas SKS <span class="text-rose-500">*</span>
            </label>
            <Input
              v-model="form.name"
              placeholder="Contoh: Batas SKS 2026"
              required
            />
          </div>

          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Keterangan
            </label>
            <Textarea
              v-model="form.description"
              placeholder="Deskripsi aturan batas SKS..."
              :rows="2"
            />
          </div>

          <!-- Rules Table -->
          <div class="border border-slate-200 rounded-xl p-3.5 bg-slate-50/50 space-y-3">
            <div class="flex items-center justify-between">
              <span class="font-bold text-slate-800 flex items-center gap-1.5">
                <Sliders class="w-3.5 h-3.5 text-brand-600" />
                Jenjang Batas IP & SKS
              </span>
              <button
                type="button"
                class="text-3xs font-semibold text-brand-700 hover:text-brand-800 bg-brand-50 hover:bg-brand-100 px-2 py-1 rounded-md border border-brand-200"
                @click="addRuleRow"
              >
                + Tambah Jenjang
              </button>
            </div>

            <div class="space-y-2">
              <div
                v-for="(rule, idx) in form.rules"
                :key="idx"
                class="flex items-center gap-2 bg-white p-2.5 rounded-lg border border-slate-200/80 shadow-2xs"
              >
                <div class="flex-1 grid grid-cols-3 gap-2">
                  <div>
                    <label class="text-3xs text-slate-400 block mb-0.5">IP Min</label>
                    <Input
                      v-model.number="rule.min_gpa"
                      type="number"
                      step="0.01"
                      placeholder="0.00"
                      class="text-xs py-1"
                    />
                  </div>
                  <div>
                    <label class="text-3xs text-slate-400 block mb-0.5">IP Max</label>
                    <Input
                      v-model.number="rule.max_gpa"
                      type="number"
                      step="0.01"
                      placeholder="4.00"
                      class="text-xs py-1"
                    />
                  </div>
                  <div>
                    <label class="text-3xs text-slate-400 block mb-0.5">Max SKS</label>
                    <Input
                      v-model.number="rule.max_sks"
                      type="number"
                      placeholder="24"
                      class="text-xs py-1 font-bold text-brand-700"
                    />
                  </div>
                </div>

                <button
                  type="button"
                  class="p-1.5 text-rose-500 hover:bg-rose-50 rounded-md border border-rose-200 self-end"
                  title="Hapus Jenjang"
                  @click="removeRuleRow(idx)"
                >
                  <Trash2 class="w-3.5 h-3.5" />
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="p-4 border-t border-slate-100 bg-slate-50/50 flex items-center justify-end gap-2">
          <Button variant="outline" size="sm" @click="closeDrawer">
            Batal
          </Button>
          <Button
            variant="primary"
            size="sm"
            :loading="saving"
            class="bg-brand-700 hover:bg-brand-800 text-white flex items-center gap-1.5"
            @click="handleSave"
          >
            <Check class="w-4 h-4" />
            Simpan Data
          </Button>
        </div>
      </div>
    </div>
  </PageContainer>
</template>
