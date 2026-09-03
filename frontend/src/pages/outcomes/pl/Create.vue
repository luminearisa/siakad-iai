<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { Sparkles, X, Check, CalendarDays } from 'lucide-vue-next'
import { outcomeService } from '@/services/api/outcomes'
import { academicService } from '@/services/api/academic'
import { useToast } from '@/composables/useToast'
import type { StudyProgram } from '@/types/academic'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import AiOutcomeGeneratorModal from '@/components/outcomes/AiOutcomeGeneratorModal.vue'

const router = useRouter()
const route = useRoute()
const toast = useToast()

const isEdit = computed(() => !!route.params.id)
const loading = ref<boolean>(false)
const saving = ref<boolean>(false)
const studyPrograms = ref<StudyProgram[]>([])
const aiModalOpen = ref<boolean>(false)

const form = reactive({
  study_program_id: null as number | null,
  code: (route.query.code as string) || '',
  name: (route.query.name as string) || '',
  profession: (route.query.profession as string) || '',
  description: (route.query.description as string) || '',
  status: 'active',
})

async function loadData() {
  loading.value = true
  try {
    const prodiRes = await academicService.getStudyPrograms({ per_page: 100 })
    studyPrograms.value = prodiRes.data || []

    if (route.params.id) {
      const res = await outcomeService.getGraduateProfile(route.params.id as string)
      const data = res.data
      form.study_program_id = data.study_program_id || null
      form.code = data.code
      form.name = data.name
      form.profession = data.profession || ''
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
  form.profession = suggestion.profession || form.profession
  form.description = suggestion.description || form.description
  toast.success('Rumusan taksonomi AI berhasil diterapkan ke formulir!')
}

async function handleSave() {
  if (!form.code || !form.name || !form.description) {
    toast.warning('Mohon lengkapi seluruh isian bertanda bintang (*).')
    return
  }

  saving.value = true
  try {
    if (isEdit.value) {
      await outcomeService.updateGraduateProfile(route.params.id as string, form)
      toast.success('Profil Lulusan berhasil diperbarui.')
    } else {
      await outcomeService.createGraduateProfile(form)
      toast.success('Profil Lulusan berhasil disimpan.')
    }
    router.push('/outcomes/pl')
  } catch (err: any) {
    toast.error(err.message || 'Gagal menyimpan Profil Lulusan.')
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
      <!-- Breadcrumb Header matching Image 2 -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200/80 pb-4">
        <div>
          <div class="flex items-center gap-2 text-xs text-slate-500 font-medium mb-1">
            <CalendarDays class="w-4 h-4 text-brand-600" />
            <span>Akademik</span>
            <span>&rsaquo;</span>
            <span class="text-slate-900 font-semibold">{{ isEdit ? 'Edit' : 'Tambah' }}</span>
          </div>
          <h1 class="text-xl font-bold text-slate-900 tracking-tight">
            {{ isEdit ? 'Edit Profil Lulusan (PL)' : 'Tambah Profil Lulusan (PL)' }}
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

      <!-- Main Form matching Image 2 -->
      <Card class="p-6 bg-white border border-slate-200 rounded-xl shadow-2xs">
        <form class="space-y-5" @submit.prevent="handleSave">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Kode Profil Lulusan -->
            <div class="space-y-1.5">
              <label class="block text-xs font-semibold text-slate-800">
                Kode Profil Lulusan <span class="text-rose-500">*</span>
              </label>
              <Input
                v-model="form.code"
                type="text"
                placeholder="Masukkan Kode Profil Lulusan"
                required
              />
            </div>

            <!-- Profesi -->
            <div class="space-y-1.5">
              <label class="block text-xs font-semibold text-slate-800">
                Profesi
              </label>
              <Input
                v-model="form.profession"
                type="text"
                placeholder="Masukkan Profesi"
              />
            </div>

            <!-- Nama Profil Lulusan -->
            <div class="space-y-1.5">
              <label class="block text-xs font-semibold text-slate-800">
                Nama Profil Lulusan <span class="text-rose-500">*</span>
              </label>
              <Input
                v-model="form.name"
                type="text"
                placeholder="Masukkan Nama Profil Lulusan"
                required
              />
            </div>

            <!-- Program Studi -->
            <div class="space-y-1.5">
              <label class="block text-xs font-semibold text-slate-800">
                Program Studi
              </label>
              <select
                v-model="form.study_program_id"
                class="w-full text-xs py-2 px-3 border border-slate-300 rounded-lg text-slate-900 bg-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none"
              >
                <option :value="null">Semua Program Studi</option>
                <option v-for="p in studyPrograms" :key="p.id" :value="p.id">
                  {{ p.name }} ({{ p.degree || 'S1' }})
                </option>
              </select>
            </div>
          </div>

          <!-- Deskripsi -->
          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-800">
              Deskripsi <span class="text-rose-500">*</span>
            </label>
            <textarea
              v-model="form.description"
              rows="4"
              placeholder="Masukkan Deskripsi"
              class="w-full text-xs py-2 px-3 border border-slate-300 rounded-lg text-slate-900 bg-white focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none"
              required
            />
          </div>

          <!-- Action Buttons matching Image 2 -->
          <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
            <Button
              type="button"
              variant="secondary"
              size="sm"
              :disabled="saving"
              @click="router.push('/outcomes/pl')"
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
      type="pl"
      :study-programs="studyPrograms"
      :initial-prodi-id="form.study_program_id"
      @select="handleAiSelect"
    />
  </PageContainer>
</template>
