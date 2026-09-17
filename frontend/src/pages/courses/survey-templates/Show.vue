<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import {
  Plus,
  Edit2,
  Trash2,
  BookOpen,
  ArrowLeft,
  Eye,
  Layers,
  Check,
  Search,
} from 'lucide-vue-next'
import { surveyTemplateService } from '@/services/api/surveyTemplate'
import { courseService } from '@/services/api/courses'
import { useToast } from '@/composables/useToast'
import type {
  SurveyTemplate,
  SurveyTopic,
  SurveyQuestion,
  QuestionType,
} from '@/types/surveyTemplate'
import type { Course } from '@/types/course'
import PageContainer from '@/components/data-display/PageContainer.vue'
import Breadcrumb from '@/components/ui/Breadcrumb.vue'
import Tabs, { type TabItem } from '@/components/ui/Tabs.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Modal from '@/components/ui/Modal.vue'
import Badge from '@/components/ui/Badge.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import ConfirmModal from '@/components/feedback/ConfirmModal.vue'

const route = useRoute()
const toast = useToast()

const templateId = route.params.id as string
const loading = ref<boolean>(true)
const template = ref<SurveyTemplate | null>(null)
const activeTab = ref<string>('structure')

const tabs = ref<TabItem[]>([
  { id: 'structure', label: 'Judul & Pertanyaan' },
  { id: 'courses', label: 'Mata Kuliah Terkait', badge: 0 },
  { id: 'preview', label: 'Pratinjau Pengisian' },
])

// --- Modal Judul (Topic) ---
const showTopicModal = ref<boolean>(false)
const isEditingTopic = ref<boolean>(false)
const topicSaving = ref<boolean>(false)
const currentTopicId = ref<number | null>(null)
const topicForm = reactive({
  title: '',
  description: '',
  order_number: 1,
})

// --- Modal Pertanyaan (Question) ---
const showQuestionModal = ref<boolean>(false)
const isEditingQuestion = ref<boolean>(false)
const questionSaving = ref<boolean>(false)
const currentQuestionId = ref<number | null>(null)
const targetTopicForQuestion = ref<SurveyTopic | null>(null)
const questionForm = reactive({
  question: '',
  question_type: 'scale' as QuestionType,
  scale_min: 1,
  scale_max: 5,
  scale_min_label: 'Sangat Kurang',
  scale_max_label: 'Sangat Baik',
  is_required: true,
  order_number: 1,
})

// --- Modal Assign Courses ---
const assignModalOpen = ref<boolean>(false)
const coursesLoading = ref<boolean>(false)
const assignSaving = ref<boolean>(false)
const allCourses = ref<Course[]>([])
const courseSearch = ref<string>('')
const selectedCourseIds = ref<number[]>([])

// --- Delete Modals ---
const deleteTopicModalOpen = ref<boolean>(false)
const topicToDelete = ref<SurveyTopic | null>(null)
const deleteQuestionModalOpen = ref<boolean>(false)
const questionToDelete = ref<SurveyQuestion | null>(null)
const unassignModalOpen = ref<boolean>(false)
const courseToUnassign = ref<any | null>(null)
const actionLoading = ref<boolean>(false)

// --- Preview State ---
const previewAnswers = ref<Record<number, any>>({})

async function loadTemplate() {
  loading.value = true
  try {
    const res = await surveyTemplateService.getTemplate(templateId)
    template.value = res.data
    tabs.value[1].badge = res.data.courses?.length || 0
  } catch (err: any) {
    toast.error(err.response?.data?.message || err.message || 'Gagal memuat detail template survey.')
  } finally {
    loading.value = false
  }
}

// --- Topic Handlers ---
function openAddTopicModal() {
  isEditingTopic.value = false
  currentTopicId.value = null
  const maxOrder = template.value?.topics?.length || 0
  topicForm.title = ''
  topicForm.description = ''
  topicForm.order_number = maxOrder + 1
  showTopicModal.value = true
}

function openEditTopicModal(topic: SurveyTopic) {
  isEditingTopic.value = true
  currentTopicId.value = topic.id
  topicForm.title = topic.title
  topicForm.description = topic.description || ''
  topicForm.order_number = topic.order_number
  showTopicModal.value = true
}

async function handleSaveTopic() {
  if (!topicForm.title.trim()) {
    toast.warning('Nama judul topik wajib diisi.')
    return
  }

  topicSaving.value = true
  try {
    if (isEditingTopic.value && currentTopicId.value) {
      await surveyTemplateService.updateTopic(currentTopicId.value, topicForm)
      toast.success('Judul topik survey berhasil diperbarui.')
    } else {
      await surveyTemplateService.createTopic(templateId, topicForm)
      toast.success('Judul topik survey berhasil ditambahkan.')
    }
    showTopicModal.value = false
    await loadTemplate()
  } catch (err: any) {
    toast.error(err.response?.data?.message || err.message || 'Gagal menyimpan judul topik.')
  } finally {
    topicSaving.value = false
  }
}

function confirmDeleteTopic(topic: SurveyTopic) {
  topicToDelete.value = topic
  deleteTopicModalOpen.value = true
}

async function handleDeleteTopic() {
  if (!topicToDelete.value) return
  actionLoading.value = true
  try {
    await surveyTemplateService.deleteTopic(topicToDelete.value.id)
    toast.success('Judul topik beserta pertanyaan di dalamnya berhasil dihapus.')
    deleteTopicModalOpen.value = false
    await loadTemplate()
  } catch (err: any) {
    toast.error(err.response?.data?.message || err.message || 'Gagal menghapus judul topik.')
  } finally {
    actionLoading.value = false
  }
}

// --- Question Handlers ---
function openAddQuestionModal(topic: SurveyTopic) {
  targetTopicForQuestion.value = topic
  isEditingQuestion.value = false
  currentQuestionId.value = null
  const maxOrder = topic.questions?.length || 0
  questionForm.question = ''
  questionForm.question_type = 'scale'
  questionForm.scale_min = 1
  questionForm.scale_max = 5
  questionForm.scale_min_label = 'Sangat Kurang'
  questionForm.scale_max_label = 'Sangat Baik'
  questionForm.is_required = true
  questionForm.order_number = maxOrder + 1
  showQuestionModal.value = true
}

function openEditQuestionModal(topic: SurveyTopic, q: SurveyQuestion) {
  targetTopicForQuestion.value = topic
  isEditingQuestion.value = true
  currentQuestionId.value = q.id
  questionForm.question = q.question
  questionForm.question_type = q.question_type
  questionForm.scale_min = q.scale_min || 1
  questionForm.scale_max = q.scale_max || 5
  questionForm.scale_min_label = q.scale_min_label || 'Sangat Kurang'
  questionForm.scale_max_label = q.scale_max_label || 'Sangat Baik'
  questionForm.is_required = q.is_required
  questionForm.order_number = q.order_number
  showQuestionModal.value = true
}

async function handleSaveQuestion() {
  if (!questionForm.question.trim()) {
    toast.warning('Teks pertanyaan survey wajib diisi.')
    return
  }

  questionSaving.value = true
  try {
    if (isEditingQuestion.value && currentQuestionId.value) {
      await surveyTemplateService.updateQuestion(currentQuestionId.value, questionForm)
      toast.success('Pertanyaan survey berhasil diperbarui.')
    } else if (targetTopicForQuestion.value) {
      await surveyTemplateService.createQuestion(targetTopicForQuestion.value.id, questionForm)
      toast.success('Pertanyaan survey baru berhasil ditambahkan.')
    }
    showQuestionModal.value = false
    await loadTemplate()
  } catch (err: any) {
    toast.error(err.response?.data?.message || err.message || 'Gagal menyimpan pertanyaan.')
  } finally {
    questionSaving.value = false
  }
}

function confirmDeleteQuestion(q: SurveyQuestion) {
  questionToDelete.value = q
  deleteQuestionModalOpen.value = true
}

async function handleDeleteQuestion() {
  if (!questionToDelete.value) return
  actionLoading.value = true
  try {
    await surveyTemplateService.deleteQuestion(questionToDelete.value.id)
    toast.success('Pertanyaan survey berhasil dihapus.')
    deleteQuestionModalOpen.value = false
    await loadTemplate()
  } catch (err: any) {
    toast.error(err.response?.data?.message || err.message || 'Gagal menghapus pertanyaan.')
  } finally {
    actionLoading.value = false
  }
}

// --- Assigned Courses Handlers ---
async function openAssignModal() {
  assignModalOpen.value = true
  coursesLoading.value = true
  courseSearch.value = ''
  selectedCourseIds.value = template.value?.courses?.map((c) => c.id) || []

  try {
    const res = await courseService.list({ per_page: 200 })
    allCourses.value = res.data || []
  } catch (err: any) {
    toast.error(err.response?.data?.message || err.message || 'Gagal memuat mata kuliah.')
  } finally {
    coursesLoading.value = false
  }
}

const filteredCoursesForAssign = computed(() => {
  if (!courseSearch.value.trim()) return allCourses.value
  const q = courseSearch.value.toLowerCase()
  return allCourses.value.filter(
    (c) =>
      c.name.toLowerCase().includes(q) ||
      c.code.toLowerCase().includes(q) ||
      (c.study_program && c.study_program.name.toLowerCase().includes(q))
  )
})

function toggleCourseSelection(courseId: number) {
  const index = selectedCourseIds.value.indexOf(courseId)
  if (index > -1) {
    selectedCourseIds.value.splice(index, 1)
  } else {
    selectedCourseIds.value.push(courseId)
  }
}

function selectAllFilteredCourses() {
  const ids = filteredCoursesForAssign.value.map((c) => c.id)
  const allSelected = ids.every((id) => selectedCourseIds.value.includes(id))

  if (allSelected) {
    selectedCourseIds.value = selectedCourseIds.value.filter((id) => !ids.includes(id))
  } else {
    const set = new Set([...selectedCourseIds.value, ...ids])
    selectedCourseIds.value = Array.from(set)
  }
}

async function handleSaveAssignCourses() {
  assignSaving.value = true
  try {
    await surveyTemplateService.assignCourses(templateId, selectedCourseIds.value)
    toast.success('Mata kuliah berhasil di-assign ke template survey ini.')
    assignModalOpen.value = false
    await loadTemplate()
  } catch (err: any) {
    toast.error(err.response?.data?.message || err.message || 'Gagal meng-assign mata kuliah.')
  } finally {
    assignSaving.value = false
  }
}

function confirmUnassignCourse(course: any) {
  courseToUnassign.value = course
  unassignModalOpen.value = true
}

async function handleUnassignCourse() {
  if (!courseToUnassign.value) return
  actionLoading.value = true
  try {
    await surveyTemplateService.unassignCourse(templateId, courseToUnassign.value.id)
    toast.success(`Mata kuliah ${courseToUnassign.value.name} berhasil dilepas dari template survey.`)
    unassignModalOpen.value = false
    await loadTemplate()
  } catch (err: any) {
    toast.error(err.response?.data?.message || err.message || 'Gagal melepas mata kuliah.')
  } finally {
    actionLoading.value = false
  }
}

onMounted(() => {
  loadTemplate()
})
</script>

<template>
  <PageContainer>
    <!-- Breadcrumb -->
    <div class="mb-4">
      <Breadcrumb
        :items="[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Mata Kuliah', to: '/courses' },
          { label: 'Kelola Template Survey', to: '/courses/survey-templates' },
          { label: template ? template.name : 'Detail Template' },
        ]"
      />
    </div>

    <!-- Loading Skeleton -->
    <div v-if="loading" class="space-y-4">
      <Skeleton height="5rem" rounded="lg" />
      <Skeleton height="2.5rem" rounded="md" width="40%" />
      <Skeleton height="15rem" rounded="lg" />
    </div>

    <div v-else-if="template" class="space-y-5">
      <!-- Header Banner -->
      <div class="bg-white border border-slate-200/80 rounded-xl p-4 sm:p-5 shadow-xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <router-link
              to="/courses/survey-templates"
              class="inline-flex items-center text-slate-400 hover:text-slate-700 transition-colors mr-1"
              title="Kembali ke Daftar Template"
            >
              <ArrowLeft class="w-4 h-4" />
            </router-link>
            <h1 class="text-base sm:text-lg font-bold text-slate-900 tracking-tight">
              {{ template.name }}
            </h1>
            <Badge :variant="template.is_active ? 'success' : 'neutral'" size="xs">
              {{ template.is_active ? 'Aktif' : 'Nonaktif' }}
            </Badge>
          </div>
          <p v-if="template.description" class="text-xs text-slate-500 max-w-2xl">
            {{ template.description }}
          </p>
        </div>

        <div class="flex items-center gap-2 shrink-0">
          <Button
            variant="outline"
            size="sm"
            @click="openAssignModal"
            class="flex items-center gap-1.5 text-blue-700 border-blue-200 hover:bg-blue-50"
          >
            <BookOpen class="w-4 h-4" />
            Assign ke Mata Kuliah ({{ template.courses?.length || 0 }})
          </Button>

          <Button
            variant="primary"
            size="sm"
            @click="openAddTopicModal"
            class="flex items-center gap-1.5"
          >
            <Plus class="w-4 h-4" />
            Tambah Judul Baru
          </Button>
        </div>
      </div>

      <!-- Navigation Tabs -->
      <Tabs
        :tabs="tabs"
        :model-value="activeTab"
        @update:model-value="activeTab = $event as string"
      />

      <!-- TAB 1: STRUKTUR SURVEY (JUDUL & PERTANYAAN) -->
      <div v-if="activeTab === 'structure'" class="space-y-4">
        <!-- Empty State Topics -->
        <div
          v-if="!template.topics || template.topics.length === 0"
          class="bg-white border border-dashed border-slate-300 rounded-xl p-10 text-center"
        >
          <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-3">
            <Layers class="w-6 h-6" />
          </div>
          <h3 class="text-sm font-bold text-slate-800">Belum Ada Judul / Bagian Kuisioner</h3>
          <p class="text-xs text-slate-500 max-w-md mx-auto mt-1 mb-4">
            Buat judul/kategori pertanyaan terlebih dahulu (misal: <i>"Kompetensi Pedagogik"</i>, <i>"Kedisiplinan Waktu"</i>), lalu masukkan butir pertanyaan di dalamnya.
          </p>
          <Button variant="primary" size="sm" @click="openAddTopicModal">
            <Plus class="w-4 h-4 mr-1" />
            Tambah Judul Pertama
          </Button>
        </div>

        <!-- Topics List -->
        <div v-else class="space-y-4">
          <div
            v-for="(topic, tIdx) in template.topics"
            :key="topic.id"
            class="bg-white border border-slate-200 rounded-xl shadow-xs overflow-hidden"
          >
            <!-- Topic Header -->
            <div class="bg-slate-50/80 border-b border-slate-100 p-3.5 sm:px-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5">
              <div class="flex items-start gap-2.5">
                <span class="w-6 h-6 rounded-full bg-emerald-700 text-white font-bold text-2xs flex items-center justify-center shrink-0 mt-0.5">
                  {{ tIdx + 1 }}
                </span>
                <div>
                  <h3 class="text-xs sm:text-sm font-bold text-slate-900">
                    {{ topic.title }}
                  </h3>
                  <p v-if="topic.description" class="text-2xs text-slate-500 mt-0.5">
                    {{ topic.description }}
                  </p>
                </div>
              </div>

              <!-- Topic Actions -->
              <div class="flex items-center gap-1.5 self-end sm:self-center">
                <Button
                  variant="outline"
                  size="xs"
                  @click="openAddQuestionModal(topic)"
                  class="flex items-center gap-1 text-emerald-700 border-emerald-200 hover:bg-emerald-50"
                >
                  <Plus class="w-3.5 h-3.5" />
                  Tambah Pertanyaan
                </Button>

                <button
                  type="button"
                  @click="openEditTopicModal(topic)"
                  class="p-1.5 text-slate-500 hover:text-amber-600 hover:bg-amber-50 rounded-md transition-colors"
                  title="Edit Judul Topik"
                >
                  <Edit2 class="w-3.5 h-3.5" />
                </button>

                <button
                  type="button"
                  @click="confirmDeleteTopic(topic)"
                  class="p-1.5 text-slate-500 hover:text-rose-600 hover:bg-rose-50 rounded-md transition-colors"
                  title="Hapus Judul Topik"
                >
                  <Trash2 class="w-3.5 h-3.5" />
                </button>
              </div>
            </div>

            <!-- Questions in Topic -->
            <div class="p-3 sm:p-5">
              <div
                v-if="!topic.questions || topic.questions.length === 0"
                class="py-6 text-center text-xs text-slate-400 border border-dashed border-slate-200 rounded-lg"
              >
                Belum ada pertanyaan pada judul ini. Klik <b>"Tambah Pertanyaan"</b> di atas.
              </div>

              <div v-else class="space-y-3">
                <div
                  v-for="(q, qIdx) in topic.questions"
                  :key="q.id"
                  class="p-3.5 rounded-lg border border-slate-100 bg-slate-50/40 hover:bg-slate-50/80 transition-colors"
                >
                  <div class="flex items-start justify-between gap-3">
                    <div class="flex items-start gap-2.5 min-w-0">
                      <span class="text-xs font-semibold text-slate-400 mt-0.5">
                        {{ tIdx + 1 }}.{{ qIdx + 1 }}
                      </span>
                      <div class="space-y-2 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                          <p class="text-xs font-semibold text-slate-800 leading-snug">
                            {{ q.question }}
                          </p>
                          <span
                            v-if="q.is_required"
                            class="text-2xs font-semibold text-rose-600 bg-rose-50 border border-rose-100 px-1.5 py-0.2 rounded"
                          >
                            Wajib
                          </span>
                        </div>

                        <!-- Interactive Question Preview Display -->
                        <!-- 1. YES / NO -->
                        <div v-if="q.question_type === 'yes_no'" class="flex items-center gap-2 pt-1">
                          <span class="text-2xs font-semibold uppercase text-slate-400 tracking-wider">Tipe Yes/No:</span>
                          <div class="inline-flex rounded-md shadow-2xs border border-slate-200 p-0.5 bg-white">
                            <span class="px-2.5 py-1 text-2xs font-semibold text-emerald-700 bg-emerald-50 rounded">
                              ✓ Ya
                            </span>
                            <span class="px-2.5 py-1 text-2xs font-semibold text-slate-500 rounded">
                              ✗ Tidak
                            </span>
                          </div>
                        </div>

                        <!-- 2. RENTANG / SCALE -->
                        <div v-else class="space-y-1 pt-1">
                          <div class="flex items-center gap-2">
                            <span class="text-2xs font-semibold uppercase text-slate-400 tracking-wider">Tipe Rentang Skala:</span>
                            <span class="text-2xs text-slate-500 font-medium">
                              (Skala {{ q.scale_min }} s/d {{ q.scale_max }})
                            </span>
                          </div>
                          <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="text-2xs text-slate-400 font-medium mr-1">
                              {{ q.scale_min_label || 'Sangat Kurang' }}
                            </span>
                            <div class="flex items-center gap-1">
                              <span
                                v-for="num in (q.scale_max - q.scale_min + 1)"
                                :key="num"
                                class="w-7 h-7 rounded border border-slate-200 bg-white text-slate-700 text-xs font-bold flex items-center justify-center hover:border-emerald-500 hover:text-emerald-700 cursor-default"
                              >
                                {{ q.scale_min + num - 1 }}
                              </span>
                            </div>
                            <span class="text-2xs text-slate-400 font-medium ml-1">
                              {{ q.scale_max_label || 'Sangat Baik' }}
                            </span>
                          </div>
                        </div>
                      </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-1 shrink-0">
                      <button
                        type="button"
                        @click="openEditQuestionModal(topic, q)"
                        class="p-1.5 text-slate-400 hover:text-amber-600 hover:bg-amber-50 rounded transition-colors"
                        title="Edit Pertanyaan"
                      >
                        <Edit2 class="w-3.5 h-3.5" />
                      </button>
                      <button
                        type="button"
                        @click="confirmDeleteQuestion(q)"
                        class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded transition-colors"
                        title="Hapus Pertanyaan"
                      >
                        <Trash2 class="w-3.5 h-3.5" />
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB 2: MATA KULIAH TERKAIT -->
      <div v-else-if="activeTab === 'courses'" class="space-y-4">
        <div class="flex items-center justify-between gap-3">
          <div>
            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">
              Mata Kuliah Yang Menggunakan Template Ini
            </h3>
            <p class="text-2xs text-slate-500 mt-0.5">
              Seluruh mata kuliah di bawah ini akan memunculkan kuisioner dengan butir pertanyaan dari template ini.
            </p>
          </div>
          <Button variant="primary" size="sm" @click="openAssignModal">
            <BookOpen class="w-4 h-4 mr-1.5" />
            Kelola Mata Kuliah
          </Button>
        </div>

        <Card class="overflow-hidden">
          <div
            v-if="!template.courses || template.courses.length === 0"
            class="py-12 px-4 text-center"
          >
            <div class="w-12 h-12 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center mx-auto mb-3">
              <BookOpen class="w-6 h-6" />
            </div>
            <h4 class="text-sm font-bold text-slate-700">Belum Ada Mata Kuliah Di-assign</h4>
            <p class="text-xs text-slate-500 max-w-sm mx-auto mt-1 mb-4">
              Template ini belum ditautkan ke mata kuliah manapun.
            </p>
            <Button variant="primary" size="sm" @click="openAssignModal">
              Assign Mata Kuliah Sekarang
            </Button>
          </div>

          <div v-else class="overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider text-2xs border-b border-slate-100">
                <tr>
                  <th class="py-3 px-4 w-12 text-center">No</th>
                  <th class="py-3 px-4">Kode MK</th>
                  <th class="py-3 px-4">Nama Mata Kuliah</th>
                  <th class="py-3 px-4">Program Studi</th>
                  <th class="py-3 px-4 text-center">Bobot</th>
                  <th class="py-3 px-4 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr
                  v-for="(c, cIdx) in template.courses"
                  :key="c.id"
                  class="hover:bg-slate-50/50"
                >
                  <td class="py-2.5 px-4 text-center text-slate-400 font-medium">{{ cIdx + 1 }}</td>
                  <td class="py-2.5 px-4 font-mono font-bold text-slate-800">{{ c.code }}</td>
                  <td class="py-2.5 px-4 font-semibold text-slate-900">
                    <router-link
                      :to="`/courses/${c.id}`"
                      class="hover:text-emerald-700 hover:underline"
                    >
                      {{ c.name }}
                    </router-link>
                  </td>
                  <td class="py-2.5 px-4 text-slate-600">
                    {{ c.study_program?.name || '-' }}
                  </td>
                  <td class="py-2.5 px-4 text-center text-slate-700 font-medium">{{ c.credits }} SKS</td>
                  <td class="py-2.5 px-4 text-right">
                    <button
                      type="button"
                      @click="confirmUnassignCourse(c)"
                      class="text-2xs font-semibold text-rose-600 hover:text-rose-800 hover:underline px-2 py-1"
                    >
                      Lepas Template
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </Card>
      </div>

      <!-- TAB 3: PRATINJAU PENGISIAN (PREVIEW SURVEY) -->
      <div v-if="activeTab === 'preview'" class="space-y-4">
        <Card class="p-5 border-emerald-100 bg-gradient-to-r from-emerald-50/40 via-white to-white">
          <div class="flex items-center gap-2 mb-1">
            <Eye class="w-4 h-4 text-emerald-700" />
            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">
              Simulasi Pratinjau Evaluasi Perkuliahan (EDOM)
            </h3>
          </div>
          <p class="text-xs text-slate-500">
            Berikut adalah simulasi tampilan kuesioner yang akan dijawab oleh mahasiswa di portal mahasiswa untuk mata kuliah yang di-assign template ini.
          </p>
        </Card>

        <div
          v-if="!template.topics || template.topics.length === 0"
          class="p-8 text-center text-xs text-slate-400 bg-white border border-slate-200 rounded-xl"
        >
          Belum ada judul dan pertanyaan untuk ditampilkan pada pratinjau.
        </div>

        <div v-else class="space-y-5">
          <div
            v-for="(topic, tIdx) in template.topics"
            :key="topic.id"
            class="bg-white border border-slate-200 rounded-xl p-4 sm:p-5 space-y-4"
          >
            <div class="border-b border-slate-100 pb-2.5">
              <h4 class="text-xs font-bold text-slate-900 flex items-center gap-2">
                <span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-800 text-2xs font-bold flex items-center justify-center">
                  {{ tIdx + 1 }}
                </span>
                {{ topic.title }}
              </h4>
              <p v-if="topic.description" class="text-2xs text-slate-500 mt-1 pl-7">
                {{ topic.description }}
              </p>
            </div>

            <div class="space-y-4 pl-0 sm:pl-7">
              <div
                v-for="(q, qIdx) in topic.questions"
                :key="q.id"
                class="space-y-2 pb-3 border-b border-slate-50 last:border-0"
              >
                <p class="text-xs font-medium text-slate-800">
                  <span class="font-semibold mr-1">{{ tIdx + 1 }}.{{ qIdx + 1 }}.</span>
                  {{ q.question }}
                  <span v-if="q.is_required" class="text-rose-500 font-bold ml-0.5">*</span>
                </p>

                <!-- Input Yes / No -->
                <div v-if="q.question_type === 'yes_no'" class="flex items-center gap-3 pt-1">
                  <label
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border cursor-pointer text-xs font-semibold transition-all"
                    :class="previewAnswers[q.id] === 'yes' ? 'bg-emerald-600 text-white border-emerald-600 shadow-2xs' : 'bg-white text-slate-700 border-slate-200 hover:border-emerald-400'"
                  >
                    <input
                      type="radio"
                      :name="`q_${q.id}`"
                      value="yes"
                      v-model="previewAnswers[q.id]"
                      class="hidden"
                    />
                    ✓ Ya / Setuju
                  </label>

                  <label
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border cursor-pointer text-xs font-semibold transition-all"
                    :class="previewAnswers[q.id] === 'no' ? 'bg-rose-600 text-white border-rose-600 shadow-2xs' : 'bg-white text-slate-700 border-slate-200 hover:border-rose-400'"
                  >
                    <input
                      type="radio"
                      :name="`q_${q.id}`"
                      value="no"
                      v-model="previewAnswers[q.id]"
                      class="hidden"
                    />
                    ✗ Tidak / Belum
                  </label>
                </div>

                <!-- Input Scale / Rentang -->
                <div v-else class="space-y-1 pt-1">
                  <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-2xs text-slate-400 font-medium">
                      {{ q.scale_min_label || 'Sangat Kurang' }}
                    </span>
                    <div class="flex items-center gap-1.5">
                      <label
                        v-for="num in (q.scale_max - q.scale_min + 1)"
                        :key="num"
                        class="w-8 h-8 rounded-lg border cursor-pointer text-xs font-bold flex items-center justify-center transition-all"
                        :class="previewAnswers[q.id] === (q.scale_min + num - 1) ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs' : 'bg-white text-slate-700 border-slate-200 hover:border-emerald-500'"
                      >
                        <input
                          type="radio"
                          :name="`q_${q.id}`"
                          :value="q.scale_min + num - 1"
                          v-model="previewAnswers[q.id]"
                          class="hidden"
                        />
                        {{ q.scale_min + num - 1 }}
                      </label>
                    </div>
                    <span class="text-2xs text-slate-400 font-medium">
                      {{ q.scale_max_label || 'Sangat Baik' }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL TAMBAH / EDIT JUDUL TOPIK -->
    <Modal
      :open="showTopicModal"
      :title="isEditingTopic ? 'Edit Judul Topik Survey' : 'Tambah Judul Topik Baru'"
      @update:open="showTopicModal = $event"
    >
      <form @submit.prevent="handleSaveTopic" class="space-y-4">
        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1">
            Nama Judul / Bagian Topik <span class="text-rose-500">*</span>
          </label>
          <Input
            v-model="topicForm.title"
            placeholder="Contoh: Kompetensi Pedagogik & Penguasaan Materi"
            class="text-xs"
            required
          />
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1">
            Deskripsi / Petunjuk Pengisian
          </label>
          <textarea
            v-model="topicForm.description"
            rows="2"
            placeholder="Petunjuk singkat evaluasi untuk bagian ini..."
            class="w-full text-xs rounded-md border border-slate-200 p-2.5 focus:outline-none focus:ring-1 focus:ring-emerald-500"
          ></textarea>
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1">
            Nomor Urut Tampil
          </label>
          <Input
            type="number"
            v-model.number="topicForm.order_number"
            class="text-xs w-28"
            min="1"
          />
        </div>

        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
          <Button
            type="button"
            variant="outline"
            size="sm"
            @click="showTopicModal = false"
            :disabled="topicSaving"
          >
            Batal
          </Button>
          <Button
            type="submit"
            variant="primary"
            size="sm"
            :loading="topicSaving"
          >
            {{ isEditingTopic ? 'Simpan Perubahan' : 'Tambah Judul' }}
          </Button>
        </div>
      </form>
    </Modal>

    <!-- MODAL TAMBAH / EDIT PERTANYAAN (YES/NO ATAU RENTANG) -->
    <Modal
      :open="showQuestionModal"
      :title="isEditingQuestion ? 'Edit Pertanyaan Survey' : `Tambah Pertanyaan (${targetTopicForQuestion?.title || ''})`"
      @update:open="showQuestionModal = $event"
      width="max-w-xl"
    >
      <form @submit.prevent="handleSaveQuestion" class="space-y-4">
        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1">
            Teks Pertanyaan <span class="text-rose-500">*</span>
          </label>
          <textarea
            v-model="questionForm.question"
            rows="3"
            placeholder="Tuliskan butir pertanyaan survey evaluasi..."
            class="w-full text-xs rounded-md border border-slate-200 p-2.5 focus:outline-none focus:ring-1 focus:ring-emerald-500"
            required
          ></textarea>
        </div>

        <!-- Pilihan Jenis Pertanyaan: Yes / No vs Rentang -->
        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-2">
            Jenis Pertanyaan <span class="text-rose-500">*</span>
          </label>
          <div class="grid grid-cols-2 gap-3">
            <label
              class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-all"
              :class="questionForm.question_type === 'yes_no' ? 'border-emerald-500 bg-emerald-50/50 shadow-xs ring-1 ring-emerald-500' : 'border-slate-200 bg-white hover:border-slate-300'"
            >
              <input
                type="radio"
                name="question_type"
                value="yes_no"
                v-model="questionForm.question_type"
                class="text-emerald-600 focus:ring-emerald-500 w-4 h-4"
              />
              <div>
                <span class="text-xs font-bold text-slate-900 block">Yes / No (Ya / Tidak)</span>
                <span class="text-2xs text-slate-500">Pilihan biner ya atau tidak</span>
              </div>
            </label>

            <label
              class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-all"
              :class="questionForm.question_type === 'scale' ? 'border-emerald-500 bg-emerald-50/50 shadow-xs ring-1 ring-emerald-500' : 'border-slate-200 bg-white hover:border-slate-300'"
            >
              <input
                type="radio"
                name="question_type"
                value="scale"
                v-model="questionForm.question_type"
                class="text-emerald-600 focus:ring-emerald-500 w-4 h-4"
              />
              <div>
                <span class="text-xs font-bold text-slate-900 block">Rentang (Skala Nilai)</span>
                <span class="text-2xs text-slate-500">Skala angka (misal 1 s/d 5)</span>
              </div>
            </label>
          </div>
        </div>

        <!-- Parameter Skala (Hanya jika tipe = Rentang) -->
        <div v-if="questionForm.question_type === 'scale'" class="bg-slate-50 p-3 rounded-lg border border-slate-200 space-y-3">
          <span class="text-2xs font-bold uppercase tracking-wider text-slate-600 block">
            Pengaturan Rentang Nilai
          </span>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-2xs font-semibold text-slate-600 mb-1">Nilai Min</label>
              <Input
                type="number"
                v-model.number="questionForm.scale_min"
                class="text-xs"
                min="1"
                max="5"
              />
            </div>
            <div>
              <label class="block text-2xs font-semibold text-slate-600 mb-1">Nilai Max</label>
              <Input
                type="number"
                v-model.number="questionForm.scale_max"
                class="text-xs"
                min="2"
                max="10"
              />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-2xs font-semibold text-slate-600 mb-1">Label Nilai Minimum</label>
              <Input
                v-model="questionForm.scale_min_label"
                placeholder="Contoh: Sangat Kurang"
                class="text-xs"
              />
            </div>
            <div>
              <label class="block text-2xs font-semibold text-slate-600 mb-1">Label Nilai Maksimum</label>
              <Input
                v-model="questionForm.scale_max_label"
                placeholder="Contoh: Sangat Baik"
                class="text-xs"
              />
            </div>
          </div>
        </div>

        <!-- Opsi Wajib & Urutan -->
        <div class="flex items-center justify-between gap-4 pt-1">
          <div class="flex items-center gap-2">
            <input
              id="q-required-check"
              type="checkbox"
              v-model="questionForm.is_required"
              class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4"
            />
            <label for="q-required-check" class="text-xs font-medium text-slate-700 cursor-pointer">
              Wajib dijawab oleh mahasiswa
            </label>
          </div>

          <div class="flex items-center gap-1.5">
            <span class="text-2xs text-slate-500 font-medium">Urutan:</span>
            <Input
              type="number"
              v-model.number="questionForm.order_number"
              class="text-xs w-16"
              min="1"
            />
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
          <Button
            type="button"
            variant="outline"
            size="sm"
            @click="showQuestionModal = false"
            :disabled="questionSaving"
          >
            Batal
          </Button>
          <Button
            type="submit"
            variant="primary"
            size="sm"
            :loading="questionSaving"
          >
            {{ isEditingQuestion ? 'Simpan Perubahan' : 'Tambah Pertanyaan' }}
          </Button>
        </div>
      </form>
    </Modal>

    <!-- MODAL ASSIGN MATA KULIAH -->
    <Modal
      :open="assignModalOpen"
      :title="`Assign ke Mata Kuliah: ${template?.name || ''}`"
      @update:open="assignModalOpen = $event"
      width="max-w-2xl"
    >
      <div class="space-y-3">
        <p class="text-xs text-slate-600">
          Centang mata kuliah yang akan menggunakan template survey ini. Anda bisa mencari berdasarkan kode, nama mata kuliah, atau prodi.
        </p>

        <!-- Search & Actions -->
        <div class="flex flex-col sm:flex-row items-center gap-2 justify-between">
          <div class="relative w-full sm:w-72">
            <Search class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2" />
            <Input
              v-model="courseSearch"
              placeholder="Cari kode atau nama mata kuliah..."
              class="pl-8 text-xs py-1"
            />
          </div>

          <div class="flex items-center justify-between sm:justify-end w-full sm:w-auto gap-2">
            <span class="text-2xs text-slate-500 font-medium">
              Terpilih: <b>{{ selectedCourseIds.length }}</b> mata kuliah
            </span>
            <Button
              type="button"
              variant="outline"
              size="xs"
              @click="selectAllFilteredCourses"
            >
              Pilih / Batal Semua
            </Button>
          </div>
        </div>

        <!-- Course List Scroll -->
        <div class="border border-slate-200 rounded-lg max-h-72 overflow-y-auto divide-y divide-slate-100">
          <div v-if="coursesLoading" class="p-4 space-y-2">
            <Skeleton height="2rem" rounded="md" />
            <Skeleton height="2rem" rounded="md" />
            <Skeleton height="2rem" rounded="md" />
          </div>

          <div v-else-if="filteredCoursesForAssign.length === 0" class="p-6 text-center text-xs text-slate-400">
            Tidak ada mata kuliah yang cocok dengan pencarian.
          </div>

          <div
            v-else
            v-for="c in filteredCoursesForAssign"
            :key="c.id"
            @click="toggleCourseSelection(c.id)"
            class="p-2.5 flex items-center justify-between hover:bg-slate-50 cursor-pointer transition-colors"
            :class="{ 'bg-emerald-50/50': selectedCourseIds.includes(c.id) }"
          >
            <div class="flex items-center gap-2.5 min-w-0 pr-2">
              <div
                class="w-4 h-4 rounded border flex items-center justify-center shrink-0"
                :class="selectedCourseIds.includes(c.id) ? 'bg-emerald-600 border-emerald-600 text-white' : 'border-slate-300 bg-white'"
              >
                <Check v-if="selectedCourseIds.includes(c.id)" class="w-3 h-3" />
              </div>
              <div class="min-w-0">
                <div class="text-xs font-semibold text-slate-900 truncate flex items-center gap-1.5">
                  <span class="font-mono text-2xs px-1.5 py-0.2 bg-slate-100 rounded text-slate-700 font-normal">
                    {{ c.code }}
                  </span>
                  {{ c.name }}
                </div>
                <div class="text-2xs text-slate-400 flex items-center gap-2 mt-0.5">
                  <span>{{ c.credits }} SKS</span>
                  <span v-if="c.study_program">• {{ c.study_program.name }}</span>
                </div>
              </div>
            </div>
            <span
              v-if="selectedCourseIds.includes(c.id)"
              class="text-2xs font-semibold text-emerald-700 bg-emerald-100/70 px-2 py-0.5 rounded shrink-0"
            >
              Terpilih
            </span>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
          <Button
            type="button"
            variant="outline"
            size="sm"
            @click="assignModalOpen = false"
            :disabled="assignSaving"
          >
            Batal
          </Button>
          <Button
            type="button"
            variant="primary"
            size="sm"
            @click="handleSaveAssignCourses"
            :loading="assignSaving"
          >
            Simpan Assignment ({{ selectedCourseIds.length }} MK)
          </Button>
        </div>
      </div>
    </Modal>

    <!-- CONFIRM DELETE TOPIC MODAL -->
    <ConfirmModal
      :open="deleteTopicModalOpen"
      title="Hapus Judul Topik Survey"
      :message="`Apakah Anda yakin ingin menghapus topik '${topicToDelete?.title}'? Seluruh pertanyaan di dalamnya akan ikut terhapus.`"
      confirm-text="Ya, Hapus Topik"
      variant="danger"
      :loading="actionLoading"
      @update:open="deleteTopicModalOpen = $event"
      @confirm="handleDeleteTopic"
    />

    <!-- CONFIRM DELETE QUESTION MODAL -->
    <ConfirmModal
      :open="deleteQuestionModalOpen"
      title="Hapus Pertanyaan Survey"
      :message="`Apakah Anda yakin ingin menghapus butir pertanyaan ini?`"
      confirm-text="Ya, Hapus Pertanyaan"
      variant="danger"
      :loading="actionLoading"
      @update:open="deleteQuestionModalOpen = $event"
      @confirm="handleDeleteQuestion"
    />

    <!-- CONFIRM UNASSIGN COURSE MODAL -->
    <ConfirmModal
      :open="unassignModalOpen"
      title="Lepas Tautan Mata Kuliah"
      :message="`Apakah Anda yakin ingin melepas mata kuliah '${courseToUnassign?.name}' dari template survey ini?`"
      confirm-text="Ya, Lepas Template"
      variant="danger"
      :loading="actionLoading"
      @update:open="unassignModalOpen = $event"
      @confirm="handleUnassignCourse"
    />
  </PageContainer>
</template>
