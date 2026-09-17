<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { Sparkles, X, Check, CalendarDays } from 'lucide-vue-next'
import { outcomeService } from '@/services/api/outcomes'
import { useToast } from '@/composables/useToast'
import type { CourseLearningOutcome } from '@/types/outcomes'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import AiOutcomeGeneratorModal from '@/components/outcomes/AiOutcomeGeneratorModal.vue'

const router = useRouter()
const route = useRoute()
const toast = useToast()

const isEdit = computed(() => !!route.params.id)
const loading = ref<boolean>(false)
const saving = ref<boolean>(false)
const cpmks = ref<CourseLearningOutcome[]>([])
const aiModalOpen = ref<boolean>(false)

// Daftar CPMK bisa sangat panjang -> pakai search select.
const cpmkOptions = computed(() => [
  { value: null as number | null, label: 'Pilih CPMK Induk' },
  ...cpmks.value.map((c) => ({
    value: c.id as number | null,
    label: `${c.code} - ${c.name}`,
  })),
])

const form = reactive({
  course_learning_outcome_id: null as number | null,
  code: (route.query.code as string) || '',
  name: (route.query.name as string) || '',
  description: (route.query.description as string) || '',
  status: 'active',
})

async function loadData() {
  loading.value = true
  try {
    const cpmkRes = await outcomeService.listCpmk()
    cpmks.value = cpmkRes.data || []

    if (route.params.id) {
      const res = await outcomeService.getSubCpmk(route.params.id as string)
      const data = res.data
      form.course_learning_outcome_id = data.course_learning_outcome_id || null
      form.code = data.code
      form.name = data.name
      form.description = data.description || ''
      form.status = data.status || 'active'
    }
  } catch (err: any) {
    toast.error(err.message || 'Gagal memuat data.')
  } finally {
    loading.value = false
  }
}

function handleAiSelect(suggestion: any) {
  form.code = suggestion.code || form.code
  form.name = suggestion.name || form.name
  form.description = suggestion.description || form.description
  toast.success('Rumusan taksonomi Sub-CPMK AI berhasil diterapkan!')
}

async function handleSave() {
  if (!form.code || !form.name || !form.description) {
    toast.warning('Mohon lengkapi seluruh isian bertanda bintang (*).')
    return
  }

  saving.value = true
  try {
    if (isEdit.value) {
      await outcomeService.updateSubCpmk(route.params.id as string, form)
      toast.success('Sub-CPMK berhasil diperbarui.')
    } else {
      await outcomeService.createSubCpmk(form)
      toast.success('Sub-CPMK berhasil disimpan.')
    }
    router.push('/outcomes/sub-cpmk')
  } catch (err: any) {
    toast.error(err.message || 'Gagal menyimpan Sub-CPMK.')
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <PageContainer>
    <div class="space-y-4">
      <!-- Breadcrumb Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200/80 pb-4">
        <div>
          <div class="flex items-center gap-2 text-xs text-slate-500 font-medium mb-1">
            <CalendarDays class="w-4 h-4 text-brand-600" />
            <span>Akademik</span>
            <span>&rsaquo;</span>
            <span class="text-slate-900 font-semibold">{{ isEdit ? 'Edit' : 'Tambah' }}</span>
          </div>
          <h1 class="text-xl font-bold text-slate-900 tracking-tight">
            {{ isEdit ? 'Edit Sub-CPMK' : 'Tambah Sub-CPMK' }}
          </h1>
        </div>

        <Button
          variant="outline"
          size="sm"
          class="gap-1.5 border-emerald-300 text-emerald-800 hover:bg-emerald-50 self-start sm:self-auto"
          @click="aiModalOpen = true"
        >
          <Sparkles class="w-4 h-4 text-amber-500" />
          Generate dengan AI
        </Button>
      </div>

      <!-- Main Form -->
      <Card class="p-6 bg-white border border-slate-200 rounded-xl shadow-2xs">
        <form class="space-y-5" @submit.prevent="handleSave">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Kode Sub-CPMK -->
            <div class="space-y-1.5">
              <label class="block text-xs font-semibold text-slate-800">
                Kode Sub-CPMK <span class="text-rose-500">*</span>
              </label>
              <Input
                v-model="form.code"
                type="text"
                placeholder="Masukkan Kode Sub-CPMK (contoh: Sub-CPMK 1.1)"
                required
              />
            </div>

            <!-- CPMK Induk -->
            <div class="space-y-1.5">
              <label class="block text-xs font-semibold text-slate-800">
                CPMK Induk
              </label>
              <Select
                v-model="form.course_learning_outcome_id"
                :options="cpmkOptions"
                placeholder="Pilih CPMK Induk"
                search-placeholder="Cari CPMK..."
              />
            </div>
          </div>

          <!-- Nama Sub-CPMK -->
          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-800">
              Nama Sub-CPMK <span class="text-rose-500">*</span>
            </label>
            <textarea
              v-model="form.name"
              rows="3"
              placeholder="Masukkan Kemampuan Akhir Tiap Tahapan Pembelajaran"
              class="w-full text-xs py-2 px-3 border border-slate-300 rounded-lg text-slate-900 bg-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none"
              required
            />
          </div>

          <!-- Indikator Penilaian -->
          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-800">
              Indikator & Kriteria Penilaian <span class="text-rose-500">*</span>
            </label>
            <textarea
              v-model="form.description"
              rows="4"
              placeholder="Masukkan kriteria dan indikator evaluasi..."
              class="w-full text-xs py-2 px-3 border border-slate-300 rounded-lg text-slate-900 bg-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none"
              required
            />
          </div>

          <!-- Action Buttons -->
          <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
            <Button
              type="button"
              variant="secondary"
              size="sm"
              :disabled="saving"
              @click="router.push('/outcomes/sub-cpmk')"
            >
              <X class="w-3.5 h-3.5 text-rose-500 mr-1" />
              Batal
            </Button>
            <Button
              type="submit"
              variant="primary"
              size="sm"
              class="bg-brand-700 hover:bg-brand-800 text-white gap-1.5"
              :loading="saving"
            >
              <Check class="w-3.5 h-3.5" />
              Simpan Data
            </Button>
          </div>
        </form>
      </Card>
    </div>

    <!-- AI Generator Modal -->
    <AiOutcomeGeneratorModal
      v-model:open="aiModalOpen"
      type="sub_cpmk"
      @select="handleAiSelect"
    />
  </PageContainer>
</template>
