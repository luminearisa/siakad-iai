<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { Plus, Trash2, Check } from 'lucide-vue-next'
import { curriculumService } from '@/services/api/curriculum'
import { useToast } from '@/composables/useToast'
import type { GradeScaleItem } from '@/types/curriculum'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Textarea from '@/components/ui/Textarea.vue'

const router = useRouter()
const route = useRoute()
const toast = useToast()

const isEdit = computed(() => !!route.params.id)
const loading = ref<boolean>(false)
const saving = ref<boolean>(false)

const form = reactive({
  name: '',
  description: '',
  status: 'active' as 'active' | 'inactive',
  items: [
    { grade_letter: 'A', grade_point: 4.00, min_score: 85.00, max_score: 100.00, is_whitewash: false },
    { grade_letter: 'B+', grade_point: 3.50, min_score: 75.00, max_score: 84.99, is_whitewash: false },
    { grade_letter: 'B', grade_point: 3.00, min_score: 65.00, max_score: 74.99, is_whitewash: false },
    { grade_letter: 'C+', grade_point: 2.50, min_score: 60.00, max_score: 64.99, is_whitewash: false },
    { grade_letter: 'C', grade_point: 2.00, min_score: 55.00, max_score: 59.99, is_whitewash: false },
    { grade_letter: 'D', grade_point: 1.00, min_score: 40.00, max_score: 54.99, is_whitewash: false },
    { grade_letter: 'E', grade_point: 0.00, min_score: 0.00, max_score: 39.99, is_whitewash: false },
  ] as GradeScaleItem[],
})

function addItemRow() {
  form.items.push({
    grade_letter: '',
    grade_point: 0,
    min_score: 0,
    max_score: 0,
    is_whitewash: false,
  })
}

function removeItemRow(index: number) {
  if (form.items.length > 1) {
    form.items.splice(index, 1)
  }
}

async function loadData() {
  if (!isEdit.value) return
  loading.value = true
  try {
    const res = await curriculumService.getGradeScale(Number(route.params.id))
    const scale = res.data
    form.name = scale.name
    form.description = scale.description || ''
    form.status = scale.status
    if (scale.items && scale.items.length > 0) {
      form.items = scale.items
    }
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal memuat skala nilai')
  } finally {
    loading.value = false
  }
}

async function handleSave() {
  if (!form.name.trim()) {
    toast.error('Nama Skala Nilai wajib diisi')
    return
  }

  for (const item of form.items) {
    if (!item.grade_letter.trim()) {
      toast.error('Nilai Huruf pada seluruh baris wajib diisi')
      return
    }
  }

  saving.value = true
  try {
    if (isEdit.value) {
      await curriculumService.updateGradeScale(Number(route.params.id), form)
      toast.success('Skala nilai berhasil diperbarui')
    } else {
      await curriculumService.createGradeScale(form)
      toast.success('Skala nilai berhasil ditambahkan')
    }
    router.push('/curriculum/grade-scales')
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal menyimpan skala nilai')
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
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div>
        <nav class="flex items-center gap-1.5 text-xs text-slate-400 mb-1">
          <span>Akademik</span>
          <span>&rsaquo;</span>
          <router-link to="/curriculum/grade-scales" class="hover:text-brand-600">Skala Nilai</router-link>
          <span>&rsaquo;</span>
          <span class="text-slate-600">{{ isEdit ? 'Edit' : 'Tambah' }}</span>
        </nav>
        <h1 class="text-xl font-bold text-slate-900">
          {{ isEdit ? 'Edit Skala Nilai' : 'Tambah Skala Nilai' }}
        </h1>
      </div>

      <div class="flex items-center gap-2">
        <Button
          variant="outline"
          size="sm"
          class="text-xs"
          @click="router.push('/curriculum/grade-scales')"
        >
          Batal
        </Button>
        <Button
          variant="primary"
          size="sm"
          :loading="saving"
          class="bg-brand-700 hover:bg-brand-800 text-white flex items-center gap-1.5 text-xs shadow-2xs"
          @click="handleSave"
        >
          <Check class="w-4 h-4" />
          Simpan Data
        </Button>
      </div>
    </div>

    <div v-if="loading" class="py-12 text-center text-slate-400 text-xs">
      Memuat formulir skala nilai...
    </div>

    <!-- Form Container (Matching Image 3) -->
    <Card v-else class="border border-slate-200/80 shadow-2xs p-6 space-y-6">
      <!-- Nama Skala Nilai -->
      <div>
        <label class="block text-xs font-semibold text-slate-700 mb-1.5">
          Nama Skala Nilai <span class="text-rose-500">*</span>
        </label>
        <Input
          v-model="form.name"
          placeholder="Masukkan Nama Skala Nilai"
          class="text-xs"
          required
        />
      </div>

      <!-- Keterangan -->
      <div>
        <label class="block text-xs font-semibold text-slate-700 mb-1.5">
          Keterangan
        </label>
        <Textarea
          v-model="form.description"
          placeholder="Masukkan Keterangan"
          :rows="3"
          class="text-xs"
        />
      </div>

      <!-- Skala Nilai Table -->
      <div class="space-y-3 pt-2">
        <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">
          Skala Nilai
        </h3>

        <!-- Dynamic Rows -->
        <div class="space-y-3">
          <div
            v-for="(item, idx) in form.items"
            :key="idx"
            class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end p-3 rounded-xl bg-slate-50/70 border border-slate-200/80 shadow-2xs"
          >
            <!-- Nilai Huruf -->
            <div class="sm:col-span-3">
              <label class="block text-3xs font-bold text-slate-700 mb-1">
                Nilai Huruf <span class="text-rose-500">*</span>
              </label>
              <Input
                v-model="item.grade_letter"
                placeholder="A, B, C, dst"
                class="text-xs py-1.5 font-bold text-slate-900 bg-white"
                required
              />
            </div>

            <!-- Nilai Angka -->
            <div class="sm:col-span-2">
              <label class="block text-3xs font-bold text-slate-700 mb-1">
                Nilai Angka <span class="text-rose-500">*</span>
              </label>
              <Input
                v-model.number="item.grade_point"
                type="number"
                step="0.01"
                placeholder="4.00"
                class="text-xs py-1.5 font-bold text-brand-700 bg-white"
                required
              />
            </div>

            <!-- Batas Bawah -->
            <div class="sm:col-span-2">
              <label class="block text-3xs font-bold text-slate-700 mb-1">
                Batas Bawah <span class="text-rose-500">*</span>
              </label>
              <Input
                v-model.number="item.min_score"
                type="number"
                step="0.01"
                placeholder="Contoh: 85 (untuk A: 85)"
                class="text-xs py-1.5 bg-white"
                required
              />
            </div>

            <!-- Batas Atas -->
            <div class="sm:col-span-2">
              <label class="block text-3xs font-bold text-slate-700 mb-1">
                Batas Atas <span class="text-rose-500">*</span>
              </label>
              <Input
                v-model.number="item.max_score"
                type="number"
                step="0.01"
                placeholder="Contoh: 100 (untuk A: 85 - 100)"
                class="text-xs py-1.5 bg-white"
                required
              />
            </div>

            <!-- Nilai Pemutihan Toggle -->
            <div class="sm:col-span-2 flex flex-col justify-end pb-1.5">
              <label class="block text-3xs font-bold text-slate-700 mb-1.5">
                Nilai Pemutihan
              </label>
              <label class="inline-flex items-center gap-2 cursor-pointer">
                <input
                  v-model="item.is_whitewash"
                  type="checkbox"
                  class="sr-only peer"
                />
                <div class="w-9 h-5 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-brand-600 relative"></div>
                <span class="text-xs text-slate-600 font-medium">
                  {{ item.is_whitewash ? 'Ya' : 'Tidak' }}
                </span>
              </label>
            </div>

            <!-- Delete Button -->
            <div class="sm:col-span-1 flex justify-center pb-2">
              <button
                type="button"
                class="p-2 text-rose-500 hover:bg-rose-50 rounded-lg border border-rose-200 transition-colors"
                title="Hapus Baris Nilai"
                @click="removeItemRow(idx)"
              >
                <Trash2 class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>

        <!-- Add Button matching Image 3 -->
        <div class="pt-2">
          <Button
            type="button"
            variant="primary"
            size="sm"
            class="bg-brand-700 hover:bg-brand-800 text-white flex items-center gap-1.5 text-xs shadow-2xs font-semibold px-4"
            @click="addItemRow"
          >
            <Plus class="w-4 h-4" />
            Tambah
          </Button>
        </div>
      </div>
    </Card>
  </PageContainer>
</template>
