<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import {
  Plus,
  Edit2,
  Trash2,
  HelpCircle,
  CheckCircle2,
  RotateCcw,
  Eye,
  Sliders,
  FileQuestion,
  Layers,
  Sparkles,
  MessageSquare,
  Star,
  ListOrdered,
  X,
} from 'lucide-vue-next'
import { courseQuestionnaireService } from '@/services/api/courseQuestionnaire'
import { useToast } from '@/composables/useToast'
import type { Course } from '@/types/course'
import type { CourseQuestionnaireTopic, CourseQuestionnaireQuestion, QuestionType } from '@/types/courseQuestionnaire'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Modal from '@/components/ui/Modal.vue'
import Skeleton from '@/components/ui/Skeleton.vue'

const props = defineProps<{
  course: Course
}>()

const toast = useToast()

const loading = ref<boolean>(true)
const topics = ref<CourseQuestionnaireTopic[]>([])

// Modals
const showTopicModal = ref<boolean>(false)
const isEditingTopic = ref<boolean>(false)
const topicFormLoading = ref<boolean>(false)
const currentTopicId = ref<number | null>(null)
const topicForm = reactive({
  title: '',
  description: '',
  order_number: 1,
  is_active: true,
})

const showQuestionModal = ref<boolean>(false)
const isEditingQuestion = ref<boolean>(false)
const questionFormLoading = ref<boolean>(false)
const currentQuestionId = ref<number | null>(null)
const selectedTopicForQuestion = ref<CourseQuestionnaireTopic | null>(null)
const questionForm = reactive({
  question: '',
  question_type: 'likert' as QuestionType,
  scale_min: 1,
  scale_max: 5,
  scale_min_label: 'Sangat Kurang',
  scale_max_label: 'Sangat Baik',
  options: [] as string[],
  newOptionText: '',
  is_required: true,
  order_number: 1,
})

// Preview Modal
const showPreviewModal = ref<boolean>(false)
const previewAnswers = ref<Record<number, any>>({})

// Stats
const totalQuestions = computed(() => {
  return topics.value.reduce((acc, t) => acc + (t.questions?.length || 0), 0)
})

async function loadQuestionnaires() {
  loading.value = true
  try {
    const res = await courseQuestionnaireService.getQuestionnaires(props.course.id)
    topics.value = res.data || []
  } catch (err: any) {
    toast.error(err.response?.data?.message || err.message || 'Gagal memuat kuisioner mata kuliah.')
  } finally {
    loading.value = false
  }
}

// Reset to standard template
async function handleResetTemplate() {
  if (!confirm('Apakah Anda yakin ingin mengatur ulang kuisioner ke template standar EDOM (Evaluasi Dosen oleh Mahasiswa)? Semua perubahan kustom saat ini akan diperbarui.')) {
    return
  }
  loading.value = true
  try {
    const res = await courseQuestionnaireService.resetTemplate(props.course.id)
    topics.value = res.data || []
    toast.success('Template standar EDOM berhasil digenerate.')
  } catch (err: any) {
    toast.error(err.response?.data?.message || err.message || 'Gagal mereset template kuisioner.')
  } finally {
    loading.value = false
  }
}

// Topic handlers
function openAddTopic() {
  isEditingTopic.value = false
  currentTopicId.value = null
  topicForm.title = ''
  topicForm.description = ''
  topicForm.order_number = topics.value.length + 1
  topicForm.is_active = true
  showTopicModal.value = true
}

function openEditTopic(topic: CourseQuestionnaireTopic) {
  isEditingTopic.value = true
  currentTopicId.value = topic.id
  topicForm.title = topic.title
  topicForm.description = topic.description || ''
  topicForm.order_number = topic.order_number
  topicForm.is_active = topic.is_active
  showTopicModal.value = true
}

async function handleSaveTopic() {
  if (!topicForm.title.trim()) {
    toast.error('Judul topik wajib diisi.')
    return
  }

  topicFormLoading.value = true
  try {
    if (isEditingTopic.value && currentTopicId.value) {
      await courseQuestionnaireService.updateTopic(currentTopicId.value, topicForm)
      toast.success('Topik kuisioner berhasil diperbarui.')
    } else {
      await courseQuestionnaireService.createTopic(props.course.id, topicForm)
      toast.success('Topik kuisioner baru berhasil ditambahkan.')
    }
    showTopicModal.value = false
    await loadQuestionnaires()
  } catch (err: any) {
    toast.error(err.response?.data?.message || err.message || 'Gagal menyimpan topik.')
  } finally {
    topicFormLoading.value = false
  }
}

async function handleDeleteTopic(topic: CourseQuestionnaireTopic) {
  if (!confirm(`Hapus topik "${topic.title}" beserta seluruh soal pertanyaan di dalamnya?`)) {
    return
  }

  try {
    await courseQuestionnaireService.deleteTopic(topic.id)
    toast.success('Topik berhasil dihapus.')
    await loadQuestionnaires()
  } catch (err: any) {
    toast.error(err.response?.data?.message || err.message || 'Gagal menghapus topik.')
  }
}

async function handleToggleTopicActive(topic: CourseQuestionnaireTopic) {
  try {
    topic.is_active = !topic.is_active
    await courseQuestionnaireService.updateTopic(topic.id, { is_active: topic.is_active })
    toast.success(`Topik "${topic.title}" sekarang ${topic.is_active ? 'Aktif' : 'Nonaktif'}.`)
  } catch (err: any) {
    topic.is_active = !topic.is_active
    toast.error('Gagal mengubah status topik.')
  }
}

// Question handlers
function openAddQuestion(topic: CourseQuestionnaireTopic) {
  selectedTopicForQuestion.value = topic
  isEditingQuestion.value = false
  currentQuestionId.value = null
  questionForm.question = ''
  questionForm.question_type = 'likert'
  questionForm.scale_min = 1
  questionForm.scale_max = 5
  questionForm.scale_min_label = 'Sangat Kurang'
  questionForm.scale_max_label = 'Sangat Baik'
  questionForm.options = []
  questionForm.newOptionText = ''
  questionForm.is_required = true
  questionForm.order_number = (topic.questions?.length || 0) + 1
  showQuestionModal.value = true
}

function openEditQuestion(topic: CourseQuestionnaireTopic, q: CourseQuestionnaireQuestion) {
  selectedTopicForQuestion.value = topic
  isEditingQuestion.value = true
  currentQuestionId.value = q.id
  questionForm.question = q.question
  questionForm.question_type = q.question_type
  questionForm.scale_min = q.scale_min
  questionForm.scale_max = q.scale_max
  questionForm.scale_min_label = q.scale_min_label || 'Sangat Kurang'
  questionForm.scale_max_label = q.scale_max_label || 'Sangat Baik'
  questionForm.options = q.options ? [...q.options] : []
  questionForm.newOptionText = ''
  questionForm.is_required = q.is_required
  questionForm.order_number = q.order_number
  showQuestionModal.value = true
}

function addOption() {
  if (questionForm.newOptionText.trim()) {
    questionForm.options.push(questionForm.newOptionText.trim())
    questionForm.newOptionText = ''
  }
}

function removeOption(idx: number) {
  questionForm.options.splice(idx, 1)
}

async function handleSaveQuestion() {
  if (!questionForm.question.trim()) {
    toast.error('Teks pertanyaan wajib diisi.')
    return
  }

  if (questionForm.question_type === 'multiple_choice' && questionForm.options.length < 2) {
    toast.error('Pertanyaan pilihan ganda harus memiliki minimal 2 opsi jawaban.')
    return
  }

  questionFormLoading.value = true
  try {
    const payload = {
      question: questionForm.question,
      question_type: questionForm.question_type,
      scale_min: questionForm.scale_min,
      scale_max: questionForm.scale_max,
      scale_min_label: questionForm.scale_min_label,
      scale_max_label: questionForm.scale_max_label,
      options: questionForm.question_type === 'multiple_choice' ? questionForm.options : null,
      is_required: questionForm.is_required,
      order_number: questionForm.order_number,
    }

    if (isEditingQuestion.value && currentQuestionId.value) {
      await courseQuestionnaireService.updateQuestion(currentQuestionId.value, payload)
      toast.success('Pertanyaan kuisioner berhasil diperbarui.')
    } else if (selectedTopicForQuestion.value) {
      await courseQuestionnaireService.createQuestion(selectedTopicForQuestion.value.id, payload)
      toast.success('Pertanyaan baru berhasil ditambahkan.')
    }
    showQuestionModal.value = false
    await loadQuestionnaires()
  } catch (err: any) {
    toast.error(err.response?.data?.message || err.message || 'Gagal menyimpan pertanyaan.')
  } finally {
    questionFormLoading.value = false
  }
}

async function handleDeleteQuestion(q: CourseQuestionnaireQuestion) {
  if (!confirm('Hapus pertanyaan ini dari kuisioner?')) {
    return
  }

  try {
    await courseQuestionnaireService.deleteQuestion(q.id)
    toast.success('Pertanyaan berhasil dihapus.')
    await loadQuestionnaires()
  } catch (err: any) {
    toast.error(err.response?.data?.message || err.message || 'Gagal menghapus pertanyaan.')
  }
}

// Preview Modal Open
function openPreview() {
  previewAnswers.value = {}
  showPreviewModal.value = true
}

onMounted(() => {
  loadQuestionnaires()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Top Summary Banner & Quick Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
      <div class="p-3.5 bg-white border border-slate-200/80 rounded-xl shadow-2xs flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-700 shrink-0">
          <Layers class="w-5 h-5" />
        </div>
        <div>
          <span class="text-3xs font-semibold uppercase tracking-wider text-slate-500 block">Total Topik</span>
          <span class="text-lg font-bold text-slate-900 leading-tight">{{ topics.length }} Topik</span>
        </div>
      </div>

      <div class="p-3.5 bg-white border border-slate-200/80 rounded-xl shadow-2xs flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-sky-50 border border-sky-100 flex items-center justify-center text-sky-700 shrink-0">
          <FileQuestion class="w-5 h-5" />
        </div>
        <div>
          <span class="text-3xs font-semibold uppercase tracking-wider text-slate-500 block">Total Pertanyaan</span>
          <span class="text-lg font-bold text-slate-900 leading-tight">{{ totalQuestions }} Soal</span>
        </div>
      </div>

      <div class="p-3.5 bg-white border border-slate-200/80 rounded-xl shadow-2xs flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-amber-50 border border-amber-100 flex items-center justify-center text-amber-700 shrink-0">
          <Star class="w-5 h-5" />
        </div>
        <div>
          <span class="text-3xs font-semibold uppercase tracking-wider text-slate-500 block">Skala Penilaian</span>
          <span class="text-lg font-bold text-slate-900 leading-tight">1 - 5 Likert</span>
        </div>
      </div>

      <div class="p-3.5 bg-white border border-slate-200/80 rounded-xl shadow-2xs flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-700 shrink-0">
          <Sparkles class="w-5 h-5" />
        </div>
        <div>
          <span class="text-3xs font-semibold uppercase tracking-wider text-slate-500 block">Tipe Kuisioner</span>
          <span class="text-xs font-bold text-slate-900 leading-tight">EDOM (Evaluasi Dosen & MK)</span>
        </div>
      </div>
    </div>

    <!-- Main Toolbar & Header -->
    <Card class="bg-white border border-slate-200/80 rounded-xl shadow-2xs overflow-hidden">
      <div class="p-4 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-3 bg-slate-50/50">
        <div>
          <h2 class="text-sm font-bold text-slate-900 flex items-center gap-2">
            <Sliders class="w-4 h-4 text-emerald-700" />
            Topik & Butir Pertanyaan Evaluasi Mata Kuliah
          </h2>
          <p class="text-xs text-slate-500 mt-0.5">
            Atur topik evaluasi, butir instrumen soal, jenis rentang skala Likert, dan pertanyaan umpan balik mahasiswa.
          </p>
        </div>

        <div class="flex items-center flex-wrap gap-2">
          <!-- Reset Template Button -->
          <Button
            variant="outline"
            size="sm"
            class="text-xs border-slate-300 text-slate-700 hover:bg-slate-100 gap-1.5"
            @click="handleResetTemplate"
          >
            <RotateCcw class="w-3.5 h-3.5" />
            Template Standar
          </Button>

          <!-- Preview Button -->
          <Button
            variant="outline"
            size="sm"
            class="text-xs border-sky-300 text-sky-700 bg-sky-50/50 hover:bg-sky-100 gap-1.5 font-medium"
            @click="openPreview"
          >
            <Eye class="w-3.5 h-3.5" />
            Pratinjau Mahasiswa
          </Button>

          <!-- Add Topic Button -->
          <Button
            variant="primary"
            size="sm"
            class="bg-emerald-700 hover:bg-emerald-800 text-white gap-1.5 text-xs font-semibold shadow-xs"
            @click="openAddTopic"
          >
            <Plus class="w-3.5 h-3.5" />
            Tambah Topik
          </Button>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="p-6 space-y-4">
        <Skeleton height="6rem" rounded="lg" />
        <Skeleton height="6rem" rounded="lg" />
        <Skeleton height="6rem" rounded="lg" />
      </div>

      <!-- Empty State -->
      <div v-else-if="topics.length === 0" class="py-14 text-center text-slate-400">
        <HelpCircle class="w-10 h-10 mx-auto text-slate-300 mb-2" />
        <p class="font-bold text-slate-700 text-sm">Belum Ada Topik Kuisioner</p>
        <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
          Klik tombol "+ Tambah Topik" atau gunakan "Template Standar" untuk mengisi instrumen evaluasi otomatis.
        </p>
        <Button
          variant="primary"
          size="sm"
          class="mt-4 bg-emerald-700 hover:bg-emerald-800 text-white gap-1.5 text-xs"
          @click="handleResetTemplate"
        >
          <RotateCcw class="w-3.5 h-3.5" />
          Terapkan Template Standar EDOM
        </Button>
      </div>

      <!-- Topics List -->
      <div v-else class="p-4 sm:p-5 space-y-5 divide-y divide-slate-100">
        <div
          v-for="(topic, tIdx) in topics"
          :key="topic.id"
          class="pt-5 first:pt-0"
        >
          <!-- Topic Card Header -->
          <div class="p-4 rounded-xl border border-slate-200/90 bg-slate-50/70 mb-3 flex flex-col md:flex-row md:items-center justify-between gap-3">
            <div class="flex items-start gap-3 min-w-0">
              <div class="w-7 h-7 rounded-lg bg-emerald-700 text-white font-bold text-xs flex items-center justify-center shrink-0 shadow-2xs">
                {{ tIdx + 1 }}
              </div>
              <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <h3 class="text-xs font-bold text-slate-900">
                    {{ topic.title }}
                  </h3>
                  <span
                    :class="[
                      'px-2 py-0.5 text-3xs font-semibold rounded-full border',
                      topic.is_active
                        ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                        : 'bg-slate-100 text-slate-500 border-slate-200'
                    ]"
                  >
                    {{ topic.is_active ? 'Aktif' : 'Nonaktif' }}
                  </span>
                  <span class="px-2 py-0.5 text-3xs font-medium rounded-full bg-slate-100 text-slate-600">
                    {{ topic.questions?.length || 0 }} Pertanyaan
                  </span>
                </div>
                <p v-if="topic.description" class="text-2xs text-slate-500 mt-1 leading-relaxed">
                  {{ topic.description }}
                </p>
              </div>
            </div>

            <!-- Topic Actions -->
            <div class="flex items-center gap-1.5 self-end md:self-center shrink-0">
              <button
                type="button"
                :class="[
                  'px-2.5 py-1 text-2xs font-medium rounded-lg border transition-colors cursor-pointer',
                  topic.is_active
                    ? 'border-slate-200 text-slate-600 hover:bg-slate-100'
                    : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50'
                ]"
                @click="handleToggleTopicActive(topic)"
              >
                {{ topic.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
              </button>

              <Button
                variant="outline"
                size="sm"
                class="text-2xs h-7 px-2.5 border-slate-200 text-slate-700 hover:bg-white gap-1"
                @click="openEditTopic(topic)"
              >
                <Edit2 class="w-3 h-3" />
                Edit
              </Button>

              <Button
                variant="outline"
                size="sm"
                class="text-2xs h-7 px-2.5 border-rose-200 text-rose-600 hover:bg-rose-50"
                @click="handleDeleteTopic(topic)"
              >
                <Trash2 class="w-3 h-3" />
              </Button>

              <Button
                variant="primary"
                size="sm"
                class="text-2xs h-7 px-3 bg-emerald-700 hover:bg-emerald-800 text-white font-medium gap-1 ml-1"
                @click="openAddQuestion(topic)"
              >
                <Plus class="w-3 h-3" />
                Tambah Soal
              </Button>
            </div>
          </div>

          <!-- Questions List for this Topic -->
          <div v-if="!topic.questions || topic.questions.length === 0" class="p-4 text-center rounded-lg border border-dashed border-slate-200 text-2xs text-slate-400">
            Belum ada butir pertanyaan pada topik ini. Klik <strong>"+ Tambah Soal"</strong> untuk menambahkan pertanyaan.
          </div>

          <div v-else class="space-y-2.5 pl-2 sm:pl-4">
            <div
              v-for="(q, qIdx) in topic.questions"
              :key="q.id"
              class="p-3.5 bg-white border border-slate-200/80 rounded-xl hover:border-emerald-300/80 transition-all group flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-2xs"
            >
              <div class="flex items-start gap-3 min-w-0">
                <span class="w-5 h-5 rounded-full bg-slate-100 text-slate-600 font-mono font-bold text-3xs flex items-center justify-center shrink-0 mt-0.5">
                  {{ qIdx + 1 }}
                </span>

                <div class="space-y-1.5 min-w-0">
                  <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-xs font-semibold text-slate-900 leading-snug">
                      {{ q.question }}
                    </span>
                    <span
                      v-if="q.is_required"
                      class="text-3xs text-rose-500 font-bold"
                      title="Wajib Diisi"
                    >
                      *Wajib
                    </span>
                  </div>

                  <!-- Question Type and Scale Preview -->
                  <div class="flex items-center gap-2 flex-wrap text-2xs">
                    <!-- Likert Scale Preview -->
                    <template v-if="q.question_type === 'likert'">
                      <span class="px-2 py-0.5 rounded-md bg-amber-50 text-amber-800 border border-amber-200 font-medium text-3xs">
                        Skala Likert ({{ q.scale_min }} s/d {{ q.scale_max }})
                      </span>
                      <span class="text-slate-400 text-3xs">
                        ({{ q.scale_min_label || 'Min' }} ⟶ {{ q.scale_max_label || 'Max' }})
                      </span>
                      <div class="flex items-center gap-1 ml-1">
                        <span
                          v-for="s in (q.scale_max - q.scale_min + 1)"
                          :key="s"
                          class="w-5 h-5 rounded-md bg-slate-100 text-slate-600 font-mono text-3xs font-semibold flex items-center justify-center border border-slate-200"
                        >
                          {{ q.scale_min + s - 1 }}
                        </span>
                      </div>
                    </template>

                    <!-- Multiple Choice Preview -->
                    <template v-else-if="q.question_type === 'multiple_choice'">
                      <span class="px-2 py-0.5 rounded-md bg-sky-50 text-sky-800 border border-sky-200 font-medium text-3xs">
                        Pilihan Ganda
                      </span>
                      <span class="text-slate-500 text-3xs">
                        {{ q.options?.length || 0 }} Opsi: {{ q.options?.join(', ') }}
                      </span>
                    </template>

                    <!-- Essay Preview -->
                    <template v-else-if="q.question_type === 'essay'">
                      <span class="px-2 py-0.5 rounded-md bg-purple-50 text-purple-800 border border-purple-200 font-medium text-3xs">
                        Teks / Esai Terbuka
                      </span>
                      <span class="text-slate-400 text-3xs">
                        Input isian komentar & masukan bebas
                      </span>
                    </template>
                  </div>
                </div>
              </div>

              <!-- Question Actions -->
              <div class="flex items-center gap-1 shrink-0 self-end sm:self-center">
                <button
                  type="button"
                  class="p-1.5 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-lg transition-colors cursor-pointer"
                  title="Edit Pertanyaan"
                  @click="openEditQuestion(topic, q)"
                >
                  <Edit2 class="w-3.5 h-3.5" />
                </button>
                <button
                  type="button"
                  class="p-1.5 text-rose-500 hover:text-rose-700 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer"
                  title="Hapus Pertanyaan"
                  @click="handleDeleteQuestion(q)"
                >
                  <Trash2 class="w-3.5 h-3.5" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Card>

    <!-- Modal Tambah / Edit Topik -->
    <Modal
      v-model:open="showTopicModal"
      :title="isEditingTopic ? 'Edit Topik Kuisioner' : 'Tambah Topik Kuisioner'"
      size="md"
    >
      <form @submit.prevent="handleSaveTopic" class="space-y-4 text-xs">
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">
            Judul Topik / Dimensi Evaluasi <span class="text-rose-500">*</span>
          </label>
          <Input
            v-model="topicForm.title"
            placeholder="Contoh: Kompetensi Pedagogik & Penguasaan Materi"
            required
          />
        </div>

        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">
            Deskripsi / Ruang Lingkup Topik
          </label>
          <textarea
            v-model="topicForm.description"
            rows="2"
            placeholder="Jelaskan ringkas fokus evaluasi pada topik ini..."
            class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all placeholder:text-slate-400"
          ></textarea>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Urutan Tampil
            </label>
            <Input
              v-model.number="topicForm.order_number"
              type="number"
              min="1"
            />
          </div>

          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Status Topik
            </label>
            <label class="flex items-center gap-2 mt-2 cursor-pointer select-none">
              <input
                v-model="topicForm.is_active"
                type="checkbox"
                class="w-4 h-4 rounded text-emerald-600 focus:ring-emerald-500 border-slate-300"
              />
              <span class="text-xs text-slate-700 font-medium">Aktifkan Topik</span>
            </label>
          </div>
        </div>
      </form>

      <template #footer>
        <div class="flex justify-end gap-2">
          <Button
            variant="outline"
            size="sm"
            @click="showTopicModal = false"
          >
            Batal
          </Button>
          <Button
            variant="primary"
            size="sm"
            :loading="topicFormLoading"
            class="bg-emerald-700 hover:bg-emerald-800 text-white font-semibold px-4"
            @click="handleSaveTopic"
          >
            {{ isEditingTopic ? 'Simpan Perubahan' : 'Tambah Topik' }}
          </Button>
        </div>
      </template>
    </Modal>

    <!-- Modal Tambah / Edit Pertanyaan -->
    <Modal
      v-model:open="showQuestionModal"
      :title="isEditingQuestion ? 'Edit Pertanyaan Kuisioner' : 'Tambah Pertanyaan Kuisioner'"
      size="lg"
    >
      <form @submit.prevent="handleSaveQuestion" class="space-y-4 text-xs">
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">
            Topik Evaluasi
          </label>
          <div class="p-2.5 bg-slate-50 border border-slate-200 rounded-lg font-bold text-slate-800 text-xs">
            {{ selectedTopicForQuestion?.title }}
          </div>
        </div>

        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">
            Teks Pertanyaan / Butir Pernyataan <span class="text-rose-500">*</span>
          </label>
          <textarea
            v-model="questionForm.question"
            rows="3"
            placeholder="Contoh: Dosen menguasai materi perkuliahan dengan baik dan mampu menjelaskannya secara sistematis."
            required
            class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all placeholder:text-slate-400"
          ></textarea>
        </div>

        <!-- Question Type Selection -->
        <div>
          <label class="block font-semibold text-slate-700 mb-1.5">
            Tipe Soal / Skala Penilaian <span class="text-rose-500">*</span>
          </label>
          <div class="grid grid-cols-3 gap-2.5">
            <label
              :class="[
                'p-3 rounded-xl border cursor-pointer transition-all flex flex-col items-center text-center gap-1.5',
                questionForm.question_type === 'likert'
                  ? 'border-emerald-600 bg-emerald-50/60 ring-2 ring-emerald-500/20 text-emerald-900 font-bold'
                  : 'border-slate-200 hover:border-slate-300 text-slate-700 bg-white'
              ]"
            >
              <input
                v-model="questionForm.question_type"
                type="radio"
                value="likert"
                class="sr-only"
              />
              <Star class="w-5 h-5 text-amber-500" />
              <span>Skala Likert (Rating)</span>
              <span class="text-3xs font-normal text-slate-500">Rentang nilai 1 s/d 5</span>
            </label>

            <label
              :class="[
                'p-3 rounded-xl border cursor-pointer transition-all flex flex-col items-center text-center gap-1.5',
                questionForm.question_type === 'multiple_choice'
                  ? 'border-emerald-600 bg-emerald-50/60 ring-2 ring-emerald-500/20 text-emerald-900 font-bold'
                  : 'border-slate-200 hover:border-slate-300 text-slate-700 bg-white'
              ]"
            >
              <input
                v-model="questionForm.question_type"
                type="radio"
                value="multiple_choice"
                class="sr-only"
              />
              <ListOrdered class="w-5 h-5 text-sky-600" />
              <span>Pilihan Ganda</span>
              <span class="text-3xs font-normal text-slate-500">Kustom opsi jawaban</span>
            </label>

            <label
              :class="[
                'p-3 rounded-xl border cursor-pointer transition-all flex flex-col items-center text-center gap-1.5',
                questionForm.question_type === 'essay'
                  ? 'border-emerald-600 bg-emerald-50/60 ring-2 ring-emerald-500/20 text-emerald-900 font-bold'
                  : 'border-slate-200 hover:border-slate-300 text-slate-700 bg-white'
              ]"
            >
              <input
                v-model="questionForm.question_type"
                type="radio"
                value="essay"
                class="sr-only"
              />
              <MessageSquare class="w-5 h-5 text-purple-600" />
              <span>Esai / Masukan Bebas</span>
              <span class="text-3xs font-normal text-slate-500">Kritik & saran tekstual</span>
            </label>
          </div>
        </div>

        <!-- Likert Scale Settings -->
        <div v-if="questionForm.question_type === 'likert'" class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-3">
          <div class="font-bold text-slate-800 text-2xs uppercase tracking-wider">
            Pengaturan Rentang Skala Likert
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-semibold text-slate-700 mb-1">Nilai Terendah (Min)</label>
              <Input v-model.number="questionForm.scale_min" type="number" min="1" max="5" />
            </div>
            <div>
              <label class="block font-semibold text-slate-700 mb-1">Nilai Tertinggi (Max)</label>
              <Input v-model.number="questionForm.scale_max" type="number" min="2" max="10" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-semibold text-slate-700 mb-1">Label Nilai Minimum</label>
              <Input v-model="questionForm.scale_min_label" placeholder="Contoh: Sangat Kurang / Tidak Setuju" />
            </div>
            <div>
              <label class="block font-semibold text-slate-700 mb-1">Label Nilai Maksimum</label>
              <Input v-model="questionForm.scale_max_label" placeholder="Contoh: Sangat Baik / Sangat Setuju" />
            </div>
          </div>
        </div>

        <!-- Multiple Choice Options -->
        <div v-if="questionForm.question_type === 'multiple_choice'" class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-3">
          <div class="font-bold text-slate-800 text-2xs uppercase tracking-wider">
            Opsi Pilihan Jawaban
          </div>

          <div class="flex items-center gap-2">
            <Input
              v-model="questionForm.newOptionText"
              placeholder="Ketik teks opsi lalu tekan Enter atau Tambah"
              @keydown.enter.prevent="addOption"
            />
            <Button
              type="button"
              variant="outline"
              size="sm"
              class="border-slate-300 shrink-0"
              @click="addOption"
            >
              <Plus class="w-3.5 h-3.5" />
              Tambah
            </Button>
          </div>

          <div v-if="questionForm.options.length > 0" class="space-y-1.5 mt-2">
            <div
              v-for="(opt, oIdx) in questionForm.options"
              :key="oIdx"
              class="flex items-center justify-between p-2 bg-white rounded-lg border border-slate-200 text-xs"
            >
              <div class="flex items-center gap-2">
                <span class="w-4 h-4 rounded bg-slate-100 text-slate-500 font-mono text-3xs flex items-center justify-center">
                  {{ String.fromCharCode(65 + oIdx) }}
                </span>
                <span>{{ opt }}</span>
              </div>
              <button
                type="button"
                class="text-rose-500 hover:text-rose-700 p-1 cursor-pointer"
                @click="removeOption(oIdx)"
              >
                <X class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>
        </div>

        <!-- Mandatory & Order -->
        <div class="grid grid-cols-2 gap-3 pt-2">
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Urutan Soal
            </label>
            <Input
              v-model.number="questionForm.order_number"
              type="number"
              min="1"
            />
          </div>

          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">
              Kewajiban Pengisian
            </label>
            <label class="flex items-center gap-2 mt-2 cursor-pointer select-none">
              <input
                v-model="questionForm.is_required"
                type="checkbox"
                class="w-4 h-4 rounded text-emerald-600 focus:ring-emerald-500 border-slate-300"
              />
              <span class="text-xs text-slate-700 font-medium">Wajib Diisi oleh Mahasiswa</span>
            </label>
          </div>
        </div>
      </form>

      <template #footer>
        <div class="flex justify-end gap-2">
          <Button
            variant="outline"
            size="sm"
            @click="showQuestionModal = false"
          >
            Batal
          </Button>
          <Button
            variant="primary"
            size="sm"
            :loading="questionFormLoading"
            class="bg-emerald-700 hover:bg-emerald-800 text-white font-semibold px-4"
            @click="handleSaveQuestion"
          >
            {{ isEditingQuestion ? 'Simpan Pertanyaan' : 'Tambah Pertanyaan' }}
          </Button>
        </div>
      </template>
    </Modal>

    <!-- Modal Pratinjau Mahasiswa (Student Questionnaire View Mock) -->
    <Modal
      v-model:open="showPreviewModal"
      title="Pratinjau Kuisioner Evaluasi Pembelajaran (Tampilan Mahasiswa)"
      size="xl"
    >
      <div class="space-y-6 text-xs">
        <!-- Student Instructions Banner -->
        <div class="p-4 bg-emerald-50/70 border border-emerald-200 rounded-xl flex items-start gap-3">
          <div class="w-8 h-8 rounded-lg bg-emerald-700 text-white flex items-center justify-center shrink-0">
            <CheckCircle2 class="w-4 h-4" />
          </div>
          <div>
            <h4 class="font-bold text-emerald-950 text-xs">
              Kuesioner Evaluasi Dosen & Mata Kuliah (EDOM)
            </h4>
            <p class="text-2xs text-emerald-800 mt-0.5 leading-relaxed">
              Mata Kuliah: <strong>{{ course.code }} — {{ course.name }} ({{ course.credits }} SKS)</strong>.
              Penilaian Anda bersifat <strong>anonim dan rahasia</strong>, semata-mata digunakan untuk evaluasi mutu pembelajaran dan peningkatan kualitas akademik.
            </p>
          </div>
        </div>

        <!-- Topics & Questions Survey Form -->
        <div class="space-y-6">
          <div
            v-for="(topic, tIdx) in topics.filter(t => t.is_active)"
            :key="topic.id"
            class="p-4 bg-white rounded-xl border border-slate-200 space-y-4 shadow-2xs"
          >
            <div class="border-b border-slate-100 pb-2.5 flex items-center justify-between">
              <div>
                <span class="text-3xs font-bold uppercase tracking-wider text-emerald-700 block">
                  Bagian {{ tIdx + 1 }}
                </span>
                <h3 class="font-bold text-slate-900 text-xs">
                  {{ topic.title }}
                </h3>
              </div>
              <span class="text-3xs text-slate-400">
                {{ topic.questions?.length || 0 }} Butir Soal
              </span>
            </div>

            <!-- Questions Inside Topic -->
            <div class="space-y-4">
              <div
                v-for="(q, qIdx) in topic.questions"
                :key="q.id"
                class="p-3.5 bg-slate-50/70 rounded-xl border border-slate-150 space-y-3"
              >
                <div class="flex items-start gap-2.5">
                  <span class="font-bold text-slate-600 text-xs">{{ qIdx + 1 }}.</span>
                  <div class="font-medium text-slate-800 text-xs leading-relaxed">
                    {{ q.question }}
                    <span v-if="q.is_required" class="text-rose-500 font-bold">*</span>
                  </div>
                </div>

                <!-- Likert Input Rating Bubbles -->
                <div v-if="q.question_type === 'likert'" class="pt-1">
                  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 max-w-xl">
                    <span class="text-3xs font-medium text-slate-400 italic">
                      {{ q.scale_min_label || 'Sangat Kurang' }}
                    </span>

                    <div class="flex items-center gap-2">
                      <button
                        v-for="s in (q.scale_max - q.scale_min + 1)"
                        :key="s"
                        type="button"
                        :class="[
                          'w-9 h-9 rounded-xl font-bold text-xs flex items-center justify-center transition-all cursor-pointer border',
                          previewAnswers[q.id] === (q.scale_min + s - 1)
                            ? 'bg-emerald-700 text-white border-emerald-700 shadow-xs ring-2 ring-emerald-500/20 scale-105'
                            : 'bg-white text-slate-700 border-slate-300 hover:border-emerald-500 hover:bg-emerald-50/50'
                        ]"
                        @click="previewAnswers[q.id] = (q.scale_min + s - 1)"
                      >
                        {{ q.scale_min + s - 1 }}
                      </button>
                    </div>

                    <span class="text-3xs font-medium text-slate-400 italic">
                      {{ q.scale_max_label || 'Sangat Baik' }}
                    </span>
                  </div>
                </div>

                <!-- Multiple Choice Radio Buttons -->
                <div v-else-if="q.question_type === 'multiple_choice'" class="space-y-1.5 pt-1">
                  <label
                    v-for="(opt, oIdx) in (q.options || [])"
                    :key="oIdx"
                    :class="[
                      'p-2.5 rounded-lg border flex items-center gap-2.5 cursor-pointer transition-all',
                      previewAnswers[q.id] === opt
                        ? 'bg-emerald-50/80 border-emerald-500 text-emerald-950 font-semibold'
                        : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50'
                    ]"
                  >
                    <input
                      v-model="previewAnswers[q.id]"
                      type="radio"
                      :name="`q_${q.id}`"
                      :value="opt"
                      class="text-emerald-600 focus:ring-emerald-500"
                    />
                    <span>{{ opt }}</span>
                  </label>
                </div>

                <!-- Essay Textarea -->
                <div v-else-if="q.question_type === 'essay'" class="pt-1">
                  <textarea
                    v-model="previewAnswers[q.id]"
                    rows="3"
                    placeholder="Tuliskan ulasan atau tanggapan Anda di sini..."
                    class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all placeholder:text-slate-400"
                  ></textarea>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <template #footer>
        <div class="flex items-center justify-between w-full">
          <span class="text-2xs text-slate-500">
            *Mode Pratinjau — Data jawaban tidak akan disimpan ke server.
          </span>
          <Button
            variant="outline"
            size="sm"
            @click="showPreviewModal = false"
          >
            Tutup Pratinjau
          </Button>
        </div>
      </template>
    </Modal>
  </div>
</template>
