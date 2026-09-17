<script setup lang="ts">
import { ref, onMounted } from 'vue'
import {
  ClipboardCheck,
  Plus,
  Sliders,
  Trash2,
  ExternalLink,
  Layers,
} from 'lucide-vue-next'
import { surveyTemplateService } from '@/services/api/surveyTemplate'
import { useToast } from '@/composables/useToast'
import type { Course } from '@/types/course'
import type { SurveyTemplate } from '@/types/surveyTemplate'
import Button from '@/components/ui/Button.vue'
import Modal from '@/components/ui/Modal.vue'
import Badge from '@/components/ui/Badge.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import ConfirmModal from '@/components/feedback/ConfirmModal.vue'

const props = defineProps<{
  course: Course
}>()

const toast = useToast()

const loading = ref<boolean>(true)
const assignedTemplates = ref<SurveyTemplate[]>([])
const availableTemplates = ref<
  Array<{ id: number; name: string; description: string | null; topics_count: number; questions_count: number }>
>([])

// Modal Select Template
const selectModalOpen = ref<boolean>(false)
const selectedTemplateId = ref<number | null>(null)
const assignSaving = ref<boolean>(false)

// Confirm Unassign Modal
const unassignModalOpen = ref<boolean>(false)
const templateToUnassign = ref<SurveyTemplate | null>(null)
const unassignLoading = ref<boolean>(false)

async function loadSurveyAssignment() {
  loading.value = true
  try {
    const res = await surveyTemplateService.getCourseSurveyAssignment(props.course.id)
    assignedTemplates.value = res.data?.assigned_templates || []
    availableTemplates.value = res.data?.available_templates || []
  } catch (err: any) {
    toast.error(err.response?.data?.message || err.message || 'Gagal memuat template survey mata kuliah.')
  } finally {
    loading.value = false
  }
}

function openSelectModal() {
  selectedTemplateId.value = assignedTemplates.value.length > 0 ? assignedTemplates.value[0].id : null
  selectModalOpen.value = true
}

async function handleAssignTemplate() {
  if (!selectedTemplateId.value) {
    toast.warning('Pilih salah satu template survey terlebih dahulu.')
    return
  }

  assignSaving.value = true
  try {
    await surveyTemplateService.assignToCourse(props.course.id, selectedTemplateId.value, true)
    toast.success('Template survey berhasil di-assign ke mata kuliah ini.')
    selectModalOpen.value = false
    await loadSurveyAssignment()
  } catch (err: any) {
    toast.error(err.response?.data?.message || err.message || 'Gagal meng-assign template survey.')
  } finally {
    assignSaving.value = false
  }
}

function confirmUnassign(tpl: SurveyTemplate) {
  templateToUnassign.value = tpl
  unassignModalOpen.value = true
}

async function handleUnassign() {
  if (!templateToUnassign.value) return
  unassignLoading.value = true
  try {
    await surveyTemplateService.unassignFromCourse(props.course.id, templateToUnassign.value.id)
    toast.success('Template survey berhasil dilepas dari mata kuliah ini.')
    unassignModalOpen.value = false
    await loadSurveyAssignment()
  } catch (err: any) {
    toast.error(err.response?.data?.message || err.message || 'Gagal melepas template survey.')
  } finally {
    unassignLoading.value = false
  }
}

onMounted(() => {
  loadSurveyAssignment()
})
</script>

<template>
  <div class="space-y-4">
    <!-- Loading State -->
    <div v-if="loading" class="space-y-3">
      <Skeleton height="5rem" rounded="lg" />
      <Skeleton height="10rem" rounded="lg" />
    </div>

    <!-- Content State -->
    <div v-else class="space-y-4">
      <!-- EMPTY STATE: BELUM ADA TEMPLATE DI-ASSIGN -->
      <div
        v-if="assignedTemplates.length === 0"
        class="bg-white border border-slate-200 rounded-xl p-8 sm:p-10 text-center shadow-xs"
      >
        <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-3">
          <ClipboardCheck class="w-6 h-6" />
        </div>
        <h3 class="text-sm font-bold text-slate-800">Mata Kuliah Belum Memiliki Template Survey</h3>
        <p class="text-xs text-slate-500 max-w-md mx-auto mt-1 mb-5 leading-relaxed">
          Pilih template survey master (seperti EDOM Perkuliahan Teori atau Praktikum) yang akan digunakan mahasiswa untuk mengevaluasi mata kuliah ini.
        </p>

        <div class="flex items-center justify-center gap-3">
          <Button variant="primary" size="sm" @click="openSelectModal">
            <Plus class="w-4 h-4 mr-1.5" />
            Assign Template Survey Sekarang
          </Button>

          <router-link
            to="/courses/survey-templates"
            class="text-xs font-semibold text-emerald-700 hover:text-emerald-800 hover:underline inline-flex items-center gap-1 px-3 py-1.5"
          >
            Kelola Master Template
            <ExternalLink class="w-3.5 h-3.5" />
          </router-link>
        </div>
      </div>

      <!-- HAS ASSIGNED TEMPLATE(S) -->
      <div v-else class="space-y-5">
        <div
          v-for="tpl in assignedTemplates"
          :key="tpl.id"
          class="bg-white border border-slate-200 rounded-xl shadow-xs overflow-hidden"
        >
          <!-- Assigned Template Header -->
          <div class="p-4 sm:p-5 bg-gradient-to-r from-emerald-50/50 via-white to-white border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-start gap-3">
              <div class="w-10 h-10 rounded-lg bg-emerald-700 text-white flex items-center justify-center shrink-0 shadow-2xs">
                <ClipboardCheck class="w-5 h-5" />
              </div>
              <div>
                <div class="flex items-center gap-2">
                  <span class="text-2xs font-bold uppercase tracking-wider text-emerald-700">Template Aktif</span>
                  <Badge variant="success" size="xs">Terhubung</Badge>
                </div>
                <h3 class="text-sm sm:text-base font-bold text-slate-900 mt-0.5">
                  {{ tpl.name }}
                </h3>
                <p v-if="tpl.description" class="text-xs text-slate-500 mt-0.5 max-w-2xl">
                  {{ tpl.description }}
                </p>
              </div>
            </div>

            <div class="flex items-center gap-2 shrink-0 self-end sm:self-center">
              <Button
                variant="outline"
                size="sm"
                @click="openSelectModal"
                class="text-xs flex items-center gap-1.5"
              >
                <Sliders class="w-3.5 h-3.5" />
                Ganti Template
              </Button>

              <router-link
                :to="`/courses/survey-templates/${tpl.id}`"
                class="px-2.5 py-1.5 rounded-md border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50 inline-flex items-center gap-1.5 transition-colors"
                title="Buka dan edit master template ini"
              >
                <ExternalLink class="w-3.5 h-3.5 text-slate-500" />
                Edit Master
              </router-link>

              <button
                type="button"
                @click="confirmUnassign(tpl)"
                class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded transition-colors"
                title="Lepas template dari mata kuliah ini"
              >
                <Trash2 class="w-4 h-4" />
              </button>
            </div>
          </div>

          <!-- Preview Topics & Questions Inside Template -->
          <div class="p-4 sm:p-5 space-y-4">
            <div class="flex items-center justify-between">
              <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                <Layers class="w-4 h-4 text-emerald-600" />
                Daftar Judul & Pertanyaan Evaluasi ({{ tpl.topics?.length || 0 }} Judul)
              </h4>
            </div>

            <div
              v-if="!tpl.topics || tpl.topics.length === 0"
              class="py-6 text-center text-xs text-slate-400 border border-dashed border-slate-200 rounded-lg"
            >
              Template ini belum memiliki judul pertanyaan. Silakan buka master template untuk melengkapi pertanyaan.
            </div>

            <div v-else class="space-y-4">
              <div
                v-for="(topic, tIdx) in tpl.topics"
                :key="topic.id"
                class="border border-slate-200 rounded-lg p-3.5 sm:p-4 bg-slate-50/40 space-y-3"
              >
                <!-- Judul Topik -->
                <div class="flex items-center gap-2 border-b border-slate-200/70 pb-2">
                  <span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-800 text-2xs font-bold flex items-center justify-center shrink-0">
                    {{ tIdx + 1 }}
                  </span>
                  <h5 class="text-xs font-bold text-slate-900">
                    {{ topic.title }}
                  </h5>
                  <span v-if="topic.description" class="text-2xs text-slate-500 hidden sm:inline">
                    — {{ topic.description }}
                  </span>
                </div>

                <!-- Pertanyaan di bawah Judul -->
                <div class="space-y-2.5 pl-0 sm:pl-7">
                  <div
                    v-for="(q, qIdx) in topic.questions"
                    :key="q.id"
                    class="p-2.5 rounded bg-white border border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs"
                  >
                    <div class="min-w-0 pr-2">
                      <span class="font-medium text-slate-800">
                        {{ tIdx + 1 }}.{{ qIdx + 1 }}. {{ q.question }}
                      </span>
                      <span v-if="q.is_required" class="text-2xs text-rose-500 font-bold ml-1">*</span>
                    </div>

                    <!-- Tipe Preview -->
                    <div class="shrink-0">
                      <!-- Yes / No -->
                      <span
                        v-if="q.question_type === 'yes_no'"
                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-2xs font-bold bg-amber-50 text-amber-800 border border-amber-200"
                      >
                        ✓ Tipe: Yes / No
                      </span>
                      <!-- Scale -->
                      <span
                        v-else
                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-2xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200"
                      >
                        ★ Skala {{ q.scale_min }}–{{ q.scale_max }} ({{ q.scale_min_label || 'Kurang' }} s/d {{ q.scale_max_label || 'Baik' }})
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL PILIH / GANTI TEMPLATE SURVEY -->
    <Modal
      :open="selectModalOpen"
      title="Pilih Template Survey Untuk Mata Kuliah Ini"
      @update:open="selectModalOpen = $event"
      width="max-w-lg"
    >
      <div class="space-y-3">
        <p class="text-xs text-slate-600">
          Pilih salah satu template survey yang tersedia untuk diterapkan pada mata kuliah <b>{{ course.name }}</b>:
        </p>

        <div
          v-if="availableTemplates.length === 0"
          class="p-6 text-center text-xs text-slate-500 bg-slate-50 rounded-lg"
        >
          Belum ada template survey aktif di sistem.
          <router-link to="/courses/survey-templates" class="text-emerald-700 font-bold block mt-2">
            + Buat Template di Kelola Template Survey
          </router-link>
        </div>

        <div v-else class="space-y-2 max-h-80 overflow-y-auto pr-1">
          <label
            v-for="tpl in availableTemplates"
            :key="tpl.id"
            class="p-3 rounded-lg border flex items-start gap-3 cursor-pointer transition-all"
            :class="selectedTemplateId === tpl.id ? 'border-emerald-500 bg-emerald-50/50 shadow-xs ring-1 ring-emerald-500' : 'border-slate-200 bg-white hover:border-slate-300'"
          >
            <input
              type="radio"
              name="select_template_id"
              :value="tpl.id"
              v-model="selectedTemplateId"
              class="text-emerald-600 focus:ring-emerald-500 w-4 h-4 mt-0.5"
            />
            <div class="min-w-0 flex-1">
              <h5 class="text-xs font-bold text-slate-900">
                {{ tpl.name }}
              </h5>
              <p v-if="tpl.description" class="text-2xs text-slate-500 mt-0.5 line-clamp-2">
                {{ tpl.description }}
              </p>
              <div class="flex items-center gap-3 text-2xs text-slate-400 font-medium mt-1.5">
                <span>{{ tpl.topics_count || 0 }} Judul</span>
                <span>•</span>
                <span>{{ tpl.questions_count || 0 }} Pertanyaan</span>
              </div>
            </div>
          </label>
        </div>

        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
          <Button
            type="button"
            variant="outline"
            size="sm"
            @click="selectModalOpen = false"
            :disabled="assignSaving"
          >
            Batal
          </Button>
          <Button
            type="button"
            variant="primary"
            size="sm"
            @click="handleAssignTemplate"
            :loading="assignSaving"
            :disabled="!selectedTemplateId"
          >
            Terapkan Template
          </Button>
        </div>
      </div>
    </Modal>

    <!-- CONFIRM UNASSIGN MODAL -->
    <ConfirmModal
      :open="unassignModalOpen"
      title="Lepas Template Survey"
      :message="`Apakah Anda yakin ingin melepas template '${templateToUnassign?.name}' dari mata kuliah ini? Mahasiswa tidak akan dapat mengisi kuisioner ini sampai template baru di-assign.`"
      confirm-text="Ya, Lepas Template"
      variant="danger"
      :loading="unassignLoading"
      @update:open="unassignModalOpen = $event"
      @confirm="handleUnassign"
    />
  </div>
</template>
